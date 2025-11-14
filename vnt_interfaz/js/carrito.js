// Sistema de carrito de compras
let carrito = [];
let asientosVendidos = new Set();
let descuentos = [];
let descuentoSeleccionado = null;

// Variables para selección múltiple
let ultimoAsientoSeleccionado = null;
let modoSeleccionMultiple = false;

// Cargar asientos vendidos al inicio
async function cargarAsientosVendidos() {
    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');

    if (!idEvento) return;

    try {
        const response = await fetch(`obtener_asientos_vendidos.php?id_evento=${idEvento}`);
        const data = await response.json();

        if (data.success) {
            asientosVendidos = new Set(data.asientos);
            marcarAsientosVendidos();
        }
    } catch (error) {
        console.error('Error al cargar asientos vendidos:', error);
    }
}

// Cargar descuentos disponibles
async function cargarDescuentos() {
    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');

    console.log('Cargando descuentos para evento:', idEvento);

    if (!idEvento) {
        console.log('No hay evento seleccionado');
        return;
    }

    try {
        const response = await fetch(`obtener_descuentos.php?id_evento=${idEvento}`);
        console.log('Response status:', response.status);
        
        const data = await response.json();
        console.log('Datos recibidos:', data);

        if (data.success) {
            descuentos = data.descuentos;
            console.log('Descuentos cargados:', descuentos.length);
            actualizarSelectDescuentos();
        } else {
            console.error('Error en respuesta:', data.message);
        }
    } catch (error) {
        console.error('Error al cargar descuentos:', error);
    }
}

// Actualizar el select de descuentos
function actualizarSelectDescuentos() {
    const select = document.getElementById('selectDescuento');
    if (!select) {
        console.error('No se encontró el elemento selectDescuento');
        return;
    }

    console.log('Actualizando select con', descuentos.length, 'descuentos');

    select.innerHTML = '<option value="">-- Sin descuento --</option>';

    descuentos.forEach(desc => {
        const option = document.createElement('option');
        option.value = desc.id_promocion;
        
        let texto = desc.nombre;
        if (desc.modo_calculo === 'porcentaje') {
            texto += ` (-${desc.valor}%)`;
        } else {
            texto += ` (-$${parseFloat(desc.valor).toFixed(2)})`;
        }
        
        if (desc.nombre_categoria) {
            texto += ` [${desc.nombre_categoria}]`;
        }
        
        if (desc.tipo_regla === 'codigo' && desc.codigo) {
            texto += ` (Código: ${desc.codigo})`;
        }
        
        if (desc.min_cantidad > 1) {
            texto += ` (Mín. ${desc.min_cantidad} boletos)`;
        }
        
        option.textContent = texto;
        select.appendChild(option);
    });
}

// Aplicar descuento seleccionado
function aplicarDescuento() {
    const select = document.getElementById('selectDescuento');
    const infoElement = document.getElementById('descuentoInfo');
    
    if (!select.value) {
        descuentoSeleccionado = null;
        infoElement.textContent = '';
        actualizarCarrito();
        return;
    }

    descuentoSeleccionado = descuentos.find(d => d.id_promocion == select.value);
    
    if (descuentoSeleccionado) {
        // Verificar cantidad mínima
        if (carrito.length < descuentoSeleccionado.min_cantidad) {
            notify.warning(`Este descuento requiere al menos ${descuentoSeleccionado.min_cantidad} boleto(s). Actualmente tienes ${carrito.length}.`);
            select.value = '';
            descuentoSeleccionado = null;
            infoElement.textContent = '';
            actualizarCarrito();
            return;
        }
        
        let infoTexto = '';
        if (descuentoSeleccionado.modo_calculo === 'porcentaje') {
            infoTexto = `Descuento del ${descuentoSeleccionado.valor}% aplicado`;
        } else {
            infoTexto = `Descuento de $${parseFloat(descuentoSeleccionado.valor).toFixed(2)} aplicado`;
        }
        
        if (descuentoSeleccionado.nombre_categoria) {
            infoTexto += ` (solo ${descuentoSeleccionado.nombre_categoria})`;
        }
        
        infoElement.textContent = infoTexto;
    }
    
    actualizarCarrito();
}

// Calcular descuento para un item
function calcularDescuentoItem(item) {
    if (!descuentoSeleccionado || !item.descuentoAplicado) return 0;

    // Si el descuento es para una categoría específica, verificar
    if (descuentoSeleccionado.id_categoria && 
        descuentoSeleccionado.id_categoria != item.categoriaId) {
        return 0;
    }

    let descuento = 0;
    if (descuentoSeleccionado.modo_calculo === 'porcentaje') {
        descuento = item.precio * (parseFloat(descuentoSeleccionado.valor) / 100);
    } else {
        descuento = parseFloat(descuentoSeleccionado.valor);
    }

    // No permitir que el descuento sea mayor al precio
    return Math.min(descuento, item.precio);
}

// Marcar visualmente los asientos vendidos
function marcarAsientosVendidos() {
    document.querySelectorAll('.seat').forEach(seat => {
        const asientoId = seat.dataset.asientoId;
        if (asientosVendidos.has(asientoId)) {
            seat.classList.add('vendido');
            seat.style.pointerEvents = 'none';
        }
    });
}

// Agregar asiento al carrito
function agregarAlCarrito(asientoId, categoriaId) {
    // Verificar si ya está vendido
    if (asientosVendidos.has(asientoId)) {
        notify.error('Este asiento ya está vendido');
        return false;
    }

    // Verificar si ya está en el carrito
    if (carrito.find(item => item.asiento === asientoId)) {
        notify.warning('Este asiento ya está en tu carrito');
        return false;
    }

    const categoriaInfo = CATEGORIAS_INFO[categoriaId] || CATEGORIAS_INFO[DEFAULT_CAT_ID];

    carrito.push({
        asiento: asientoId,
        categoria: categoriaInfo.nombre,
        precio: parseFloat(categoriaInfo.precio),
        categoriaId: categoriaId
    });

    actualizarCarrito();
    return true;
}

// Remover asiento del carrito
function removerDelCarrito(asientoId) {
    carrito = carrito.filter(item => item.asiento !== asientoId);

    // Remover clase selected del asiento
    const seatElement = document.querySelector(`[data-asiento-id="${asientoId}"]`);
    if (seatElement) {
        seatElement.classList.remove('selected');
    }

    // Si el carrito queda vacío, resetear descuento
    if (carrito.length === 0) {
        descuentoSeleccionado = null;
        const selectDescuento = document.getElementById('selectDescuento');
        if (selectDescuento) selectDescuento.value = '';
        const descuentoInfo = document.getElementById('descuentoInfo');
        if (descuentoInfo) descuentoInfo.textContent = '';
    }

    actualizarCarrito();
}

// Actualizar vista del carrito
function actualizarCarrito() {
    const carritoContainer = document.getElementById('carritoItems');
    const totalElement = document.getElementById('totalCompra');
    const btnPagar = document.getElementById('btnPagar');

    if (carrito.length === 0) {
        carritoContainer.innerHTML = '<div class="carrito-vacio">No hay asientos seleccionados</div>';
        totalElement.textContent = '$0.00';
        btnPagar.disabled = true;
        
        // Resetear descuento cuando el carrito está vacío
        descuentoSeleccionado = null;
        const selectDescuento = document.getElementById('selectDescuento');
        if (selectDescuento) selectDescuento.value = '';
        const descuentoInfo = document.getElementById('descuentoInfo');
        if (descuentoInfo) descuentoInfo.textContent = '';
        
        // Mostrar botones de acciones cuando no hay asientos seleccionados
        mostrarBotonesAcciones(true);
        return;
    }
    
    // Ocultar botones de acciones cuando hay asientos seleccionados
    mostrarBotonesAcciones(false);

    let html = '';
    let subtotal = 0;
    let totalDescuento = 0;

    carrito.forEach(item => {
        const descuentoItem = calcularDescuentoItem(item);
        const precioFinal = item.precio - descuentoItem;
        
        subtotal += item.precio;
        totalDescuento += descuentoItem;
        
        html += `
            <div class="carrito-item">
                <div class="asiento-info">
                    <strong>${item.asiento}</strong>
                    <small style="display: block;">${item.categoria}</small>
                    <span class="text-success" style="font-size: 0.9rem;">$${item.precio.toFixed(2)}</span>
                    ${descuentoItem > 0 ? `<small class="text-danger" style="display: block;">-$${descuentoItem.toFixed(2)}</small>` : ''}
                    ${descuentoItem > 0 ? `<strong class="text-primary" style="font-size: 0.9rem;">$${precioFinal.toFixed(2)}</strong>` : ''}
                </div>
                <button class="btn-remove" onclick="removerDelCarrito('${item.asiento}')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
    });

    const total = subtotal - totalDescuento;

    // Agregar resumen si hay descuento
    if (totalDescuento > 0) {
        html += `
            <div class="mt-2 pt-2" style="border-top: 1px solid #dee2e6;">
                <div class="d-flex justify-content-between">
                    <small>Subtotal:</small>
                    <small>$${subtotal.toFixed(2)}</small>
                </div>
                <div class="d-flex justify-content-between text-danger">
                    <small>Descuento:</small>
                    <small>-$${totalDescuento.toFixed(2)}</small>
                </div>
            </div>
        `;
    }

    carritoContainer.innerHTML = html;
    totalElement.textContent = `$${total.toFixed(2)}`;
    btnPagar.disabled = false;
    
    // Actualizar estadísticas
    actualizarEstadisticas();
}

// Procesar pago - Abre modal para seleccionar tipo de boleto
function procesarPago() {
    if (carrito.length === 0) {
        notify.warning('No hay asientos en el carrito');
        return;
    }

    // Abrir modal para seleccionar tipo de boleto
    abrirModalTipoBoleto();
}

// Abrir modal para seleccionar tipo de boleto
function abrirModalTipoBoleto() {
    const modalHTML = `
        <div class="modal fade" id="modalTipoBoleto" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-ticket-perforated"></i> Tipo de Boleto
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i>
                            <strong>Selecciona el tipo de boleto para cada asiento</strong><br>
                            <small>Esta información es necesaria para los reportes. Las cortesías son gratuitas.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-tag"></i> Aplicar a todos:
                            </label>
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-primary" onclick="aplicarTipoATodos('adulto')">
                                    Adulto
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="aplicarTipoATodos('nino')">
                                    Niño
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="aplicarTipoATodos('adulto_mayor')">
                                    Adulto Mayor
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="aplicarTipoATodos('discapacitado')">
                                    Discapacitado
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="aplicarTipoATodos('cortesia')">
                                    Cortesía (Gratis)
                                </button>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div id="listaBoletosTipo" class="lista-boletos-tipo">
                            <!-- Se llenará dinámicamente -->
                        </div>
                        
                        <div class="alert alert-warning mt-3" id="alertCortesia" style="display: none;">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Atención:</strong> Los boletos de cortesía tienen precio $0.00
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="me-auto">
                            <strong>Total a pagar: <span id="totalModalPago">$0.00</span></strong>
                        </div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success btn-lg" onclick="confirmarYProcesarPago()">
                            <i class="bi bi-credit-card"></i> Confirmar y Procesar Pago
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal anterior si existe
    const modalAnterior = document.getElementById('modalTipoBoleto');
    if (modalAnterior) {
        modalAnterior.remove();
    }
    
    // Agregar modal al DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Llenar lista de boletos
    llenarListaBoletosTipo();
    
    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('modalTipoBoleto'));
    modal.show();
    
    // Limpiar al cerrar
    document.getElementById('modalTipoBoleto').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

// Llenar lista de boletos con selectores de tipo
function llenarListaBoletosTipo() {
    const lista = document.getElementById('listaBoletosTipo');
    if (!lista) return;
    
    let html = '';
    
    carrito.forEach((item, index) => {
        const descuentoItem = calcularDescuentoItem(item);
        let precioBase = item.precio - descuentoItem;
        
        // Inicializar tipo de boleto si no existe
        if (!item.tipo_boleto) {
            item.tipo_boleto = 'adulto';
        }
        
        // Si es cortesía, el precio es 0
        const precioFinal = item.tipo_boleto === 'cortesia' ? 0 : precioBase;
        
        html += `
            <div class="boleto-tipo-item mb-3 p-3 border rounded" data-index="${index}">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="fw-bold fs-5">${item.asiento}</div>
                        <small class="text-muted">${item.categoria}</small>
                        <div class="mt-1">
                            <span class="precio-display text-success fw-bold" data-precio-base="${precioBase.toFixed(2)}">$${precioFinal.toFixed(2)}</span>
                            ${descuentoItem > 0 ? `<small class="text-danger d-block">Descuento: -$${descuentoItem.toFixed(2)}</small>` : ''}
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small mb-1">Tipo de Boleto:</label>
                        <select class="form-select tipo-boleto-select" data-index="${index}">
                            <option value="adulto" ${item.tipo_boleto === 'adulto' ? 'selected' : ''}>
                                Adulto
                            </option>
                            <option value="nino" ${item.tipo_boleto === 'nino' ? 'selected' : ''}>
                                Niño
                            </option>
                            <option value="adulto_mayor" ${item.tipo_boleto === 'adulto_mayor' ? 'selected' : ''}>
                                Adulto Mayor
                            </option>
                            <option value="discapacitado" ${item.tipo_boleto === 'discapacitado' ? 'selected' : ''}>
                                Discapacitado
                            </option>
                            <option value="cortesia" ${item.tipo_boleto === 'cortesia' ? 'selected' : ''}>
                                Cortesía (Gratis)
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        `;
    });
    
    lista.innerHTML = html;
    
    // Agregar event listeners a los selectores
    document.querySelectorAll('.tipo-boleto-select').forEach(select => {
        select.addEventListener('change', function() {
            const index = parseInt(this.dataset.index);
            if (carrito[index]) {
                carrito[index].tipo_boleto = this.value;
                actualizarPrecioEnModal(index);
            }
        });
    });
    
    // Actualizar total inicial
    actualizarTotalModal();
}

// Actualizar precio en el modal cuando cambia el tipo
function actualizarPrecioEnModal(index) {
    const item = carrito[index];
    if (!item) return;
    
    const boletoItem = document.querySelector(`.boleto-tipo-item[data-index="${index}"]`);
    if (!boletoItem) return;
    
    const precioDisplay = boletoItem.querySelector('.precio-display');
    const precioBase = parseFloat(precioDisplay.dataset.precioBase);
    
    // Si es cortesía, precio = 0
    const precioFinal = item.tipo_boleto === 'cortesia' ? 0 : precioBase;
    
    precioDisplay.textContent = '$' + precioFinal.toFixed(2);
    
    // Mostrar/ocultar alerta de cortesía
    const alertCortesia = document.getElementById('alertCortesia');
    const hayCortesia = carrito.some(i => i.tipo_boleto === 'cortesia');
    if (alertCortesia) {
        alertCortesia.style.display = hayCortesia ? 'block' : 'none';
    }
    
    // Actualizar total
    actualizarTotalModal();
}

// Actualizar total en el modal
function actualizarTotalModal() {
    let total = 0;
    
    carrito.forEach((item, index) => {
        const descuentoItem = calcularDescuentoItem(item);
        const precioBase = item.precio - descuentoItem;
        
        // Si es cortesía, no suma al total
        if (item.tipo_boleto !== 'cortesia') {
            total += precioBase;
        }
    });
    
    const totalElement = document.getElementById('totalModalPago');
    if (totalElement) {
        totalElement.textContent = '$' + total.toFixed(2);
    }
}

// Aplicar tipo de boleto a todos
function aplicarTipoATodos(tipo) {
    carrito.forEach((item, index) => {
        item.tipo_boleto = tipo;
        actualizarPrecioEnModal(index);
    });
    
    // Actualizar todos los selectores
    document.querySelectorAll('.tipo-boleto-select').forEach(select => {
        select.value = tipo;
    });
    
    const tipoNombre = {
        'adulto': 'Adulto',
        'nino': 'Niño',
        'adulto_mayor': 'Adulto Mayor',
        'discapacitado': 'Discapacitado',
        'cortesia': 'Cortesía (Gratis)'
    };
    
    notify.success(`Tipo "${tipoNombre[tipo]}" aplicado a todos los boletos`);
}

// Confirmar y procesar pago
async function confirmarYProcesarPago() {
    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');

    if (!idEvento) {
        notify.error('Error: No se ha seleccionado un evento');
        return;
    }

    // Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalTipoBoleto'));
    if (modal) {
        modal.hide();
    }

    const btnPagar = document.getElementById('btnPagar');
    btnPagar.disabled = true;
    btnPagar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

    // Preparar datos con descuentos y tipo de boleto
    const asientosConDescuento = carrito.map(item => {
        const descuentoItem = calcularDescuentoItem(item);
        return {
            ...item,
            descuento_aplicado: descuentoItem,
            precio_final: item.precio - descuentoItem,
            id_promocion: descuentoSeleccionado ? descuentoSeleccionado.id_promocion : null,
            tipo_boleto: item.tipo_boleto || 'normal'
        };
    });

    try {
        const response = await fetch('procesar_compra.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_evento: idEvento,
                asientos: asientosConDescuento
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Error del servidor:', errorText);
            throw new Error(`Error del servidor (${response.status}): ${errorText.substring(0, 200)}`);
        }

        const data = await response.json();

        if (data.success) {
            console.log('Boletos recibidos:', data.boletos);
            notify.success(`¡Compra exitosa! Se generaron ${data.boletos.length} boleto(s)`);

            // Agregar asientos vendidos al set
            carrito.forEach(item => asientosVendidos.add(item.asiento));

            // Limpiar carrito y descuento
            document.querySelectorAll('.seat.selected').forEach(s => s.classList.remove('selected'));
            carrito = [];
            descuentoSeleccionado = null;
            document.getElementById('selectDescuento').value = '';
            document.getElementById('descuentoInfo').textContent = '';
            actualizarCarrito();
            marcarAsientosVendidos();

            // Mostrar códigos QR
            mostrarBoletosGenerados(data.boletos);
        } else {
            notify.error('Error al procesar la compra: ' + data.message);
        }
    } catch (error) {
        console.error('Error completo:', error);
        notify.error('Error al procesar la compra: ' + error.message);
    } finally {
        btnPagar.disabled = false;
        btnPagar.innerHTML = '<i class="bi bi-credit-card"></i> Procesar Pago';
    }
}

// Mostrar boletos generados
function mostrarBoletosGenerados(boletos) {
    let html = '<div class="modal fade" id="modalBoletos" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">';
    html += '<div class="modal-header"><h5 class="modal-title">Boletos Generados</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
    html += '<div class="modal-body"><div class="row">';

    boletos.forEach(boleto => {
        html += `
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="fw-bold">${boleto.asiento}</h6>
                        <img src="../boletos_qr/${boleto.codigo_unico}.png" alt="QR" class="img-fluid mb-2" style="max-width: 200px;">
                        <p class="mb-2"><small class="text-muted">Código: ${boleto.codigo_unico}</small></p>
                        <p class="text-success fw-bold mb-3">$${boleto.precio.toFixed(2)}</p>
                        <a href="descargar_boleto.php?codigo=${boleto.codigo_unico}" 
                           class="btn btn-primary btn-sm w-100" 
                           target="_blank">
                            <i class="bi bi-download"></i> Descargar Boleto
                        </a>
                    </div>
                </div>
            </div>
        `;
    });

    html += '</div></div><div class="modal-footer">';
    html += '<button type="button" class="btn btn-secondary" onclick="descargarTodosBoletos()"><i class="bi bi-download"></i> Descargar Todos</button>';
    html += '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cerrar</button>';
    html += '</div></div></div></div>';

    document.body.insertAdjacentHTML('beforeend', html);
    const modal = new bootstrap.Modal(document.getElementById('modalBoletos'));
    modal.show();

    // Guardar boletos en variable global para descargar todos
    window.boletosActuales = boletos;

    // Remover modal del DOM al cerrarse
    document.getElementById('modalBoletos').addEventListener('hidden.bs.modal', function () {
        this.remove();
        delete window.boletosActuales;
    });
}

// Descargar todos los boletos en un solo PDF
function descargarTodosBoletos() {
    if (!window.boletosActuales || window.boletosActuales.length === 0) return;

    // Crear string con todos los códigos separados por comas
    const codigos = window.boletosActuales.map(b => b.codigo_unico).join(',');
    
    // Abrir el PDF con todos los boletos
    window.open(`descargar_todos_boletos.php?codigos=${codigos}`, '_blank');
}

// Función para actualizar estadísticas
function actualizarEstadisticas() {
    const statAsientos = document.getElementById('statAsientos');
    const statTotal = document.getElementById('statTotal');
    
    if (statAsientos) {
        statAsientos.textContent = carrito.length;
    }
    
    if (statTotal) {
        const totalElement = document.getElementById('totalCompra');
        if (totalElement) {
            statTotal.textContent = totalElement.textContent;
        }
    }
}

// Función para limpiar toda la selección
function limpiarSeleccion() {
    if (carrito.length === 0) {
        notify.info('No hay asientos seleccionados');
        return;
    }
    
    const cantidad = carrito.length;
    
    // Remover clase selected de todos los asientos
    document.querySelectorAll('.seat.selected').forEach(seat => {
        seat.classList.remove('selected');
    });
    
    // Limpiar carrito
    carrito = [];
    ultimoAsientoSeleccionado = null;
    
    // Limpiar descuento
    descuentoSeleccionado = null;
    const selectDescuento = document.getElementById('selectDescuento');
    if (selectDescuento) selectDescuento.value = '';
    const descuentoInfo = document.getElementById('descuentoInfo');
    if (descuentoInfo) descuentoInfo.textContent = '';
    
    actualizarCarrito();
    notify.success(`${cantidad} asiento(s) deseleccionado(s)`);
}

// Función para seleccionar rango de asientos (Ctrl + Click)
function seleccionarRango(asientoActual) {
    if (!ultimoAsientoSeleccionado) return;
    
    const todosAsientos = Array.from(document.querySelectorAll('.seat'));
    const indexUltimo = todosAsientos.findIndex(s => s.dataset.asientoId === ultimoAsientoSeleccionado);
    const indexActual = todosAsientos.findIndex(s => s.dataset.asientoId === asientoActual);
    
    if (indexUltimo === -1 || indexActual === -1) return;
    
    const inicio = Math.min(indexUltimo, indexActual);
    const fin = Math.max(indexUltimo, indexActual);
    
    let seleccionados = 0;
    for (let i = inicio; i <= fin; i++) {
        const seat = todosAsientos[i];
        if (!seat.classList.contains('vendido') && !seat.classList.contains('selected')) {
            const asientoId = seat.dataset.asientoId;
            const categoriaId = seat.dataset.categoriaId;
            if (agregarAlCarrito(asientoId, categoriaId)) {
                seat.classList.add('selected');
                seleccionados++;
            }
        }
    }
    
    if (seleccionados > 0) {
        notify.success(`${seleccionados} asiento(s) seleccionado(s)`);
    }
}

// Función para seleccionar toda una fila
function seleccionarFila(filaLabel) {
    const asientosFila = document.querySelectorAll(`.seat[data-asiento-id^="${filaLabel}"]`);
    let seleccionados = 0;
    
    asientosFila.forEach(seat => {
        if (!seat.classList.contains('vendido') && !seat.classList.contains('selected')) {
            const asientoId = seat.dataset.asientoId;
            const categoriaId = seat.dataset.categoriaId;
            if (agregarAlCarrito(asientoId, categoriaId)) {
                seat.classList.add('selected');
                seleccionados++;
            }
        }
    });
    
    if (seleccionados > 0) {
        notify.success(`${seleccionados} asiento(s) de la fila ${filaLabel} seleccionado(s)`);
    } else {
        notify.info('No hay asientos disponibles en esta fila');
    }
}

// Función para deseleccionar toda una fila
function deseleccionarFila(filaLabel) {
    const asientosFila = document.querySelectorAll(`.seat[data-asiento-id^="${filaLabel}"].selected`);
    let deseleccionados = 0;
    
    asientosFila.forEach(seat => {
        const asientoId = seat.dataset.asientoId;
        removerDelCarrito(asientoId);
        seat.classList.remove('selected');
        deseleccionados++;
    });
    
    if (deseleccionados > 0) {
        notify.info(`${deseleccionados} asiento(s) de la fila ${filaLabel} deseleccionado(s)`);
    }
}

// Función para alternar modo de selección múltiple
function toggleModoSeleccionMultiple() {
    modoSeleccionMultiple = !modoSeleccionMultiple;
    const btn = document.getElementById('btnModoMultiple');
    
    if (modoSeleccionMultiple) {
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-primary');
        btn.innerHTML = '<i class="bi bi-check-square-fill"></i> Modo Múltiple: ON';
        notify.info('Modo selección múltiple activado');
    } else {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-primary');
        btn.innerHTML = '<i class="bi bi-check-square"></i> Modo Múltiple';
        notify.info('Modo selección múltiple desactivado');
    }
}

// Función removida por preferencia del usuario
// function seleccionarNAsientos() { ... }

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', () => {
    cargarAsientosVendidos();
    cargarDescuentos();

    // Modificar el comportamiento de click en los asientos
    document.querySelectorAll('.seat').forEach(seat => {
        seat.addEventListener('click', (e) => {
            const asientoId = seat.dataset.asientoId;
            const categoriaId = seat.dataset.categoriaId;

            // Selección por rango con Ctrl
            if (e.ctrlKey && ultimoAsientoSeleccionado) {
                seleccionarRango(asientoId);
                e.stopPropagation();
                return;
            }

            // Si ya está seleccionado, remover
            if (seat.classList.contains('selected')) {
                removerDelCarrito(asientoId);
                seat.classList.remove('selected');
                ultimoAsientoSeleccionado = null;
            } else if (!seat.classList.contains('vendido')) {
                // Si no está vendido, agregar
                if (agregarAlCarrito(asientoId, categoriaId)) {
                    seat.classList.add('selected');
                    ultimoAsientoSeleccionado = asientoId;
                }
            }

            // Prevenir que se abra el modal de información
            e.stopPropagation();
        });
        
        // Agregar doble click para seleccionar fila completa
        seat.addEventListener('dblclick', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const asientoId = seat.dataset.asientoId;
            // Extraer la fila del código de asiento
            const filaMatch = asientoId.match(/^([A-Z]+\d*)/);
            if (filaMatch) {
                const fila = filaMatch[1];
                if (seat.classList.contains('selected')) {
                    deseleccionarFila(fila);
                } else {
                    seleccionarFila(fila);
                }
            }
        });
    });
    
    // Agregar botones de selección rápida a las etiquetas de fila
    document.querySelectorAll('.row-label').forEach(label => {
        const fila = label.textContent.trim();
        if (fila) {
            label.style.cursor = 'pointer';
            label.title = `Doble click para seleccionar toda la fila ${fila}`;
            
            label.addEventListener('dblclick', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // Verificar si hay asientos seleccionados en esta fila
                const asientosFila = document.querySelectorAll(`.seat[data-asiento-id^="${fila}"].selected`);
                if (asientosFila.length > 0) {
                    deseleccionarFila(fila);
                } else {
                    seleccionarFila(fila);
                }
            });
        }
    });
    
    // Inicializar estadísticas
    actualizarEstadisticas();
});

// Función para mostrar/ocultar botones de acciones según el estado del carrito
function mostrarBotonesAcciones(mostrar) {
    // Seleccionar los botones que queremos ocultar cuando hay asientos en el carrito
    const btnEscanerQR = document.querySelector('.acciones-rapidas .btn-primary');
    const btnCancelarBoleto = document.querySelector('.acciones-rapidas .btn-danger');
    const btnCategorias = document.querySelector('.acciones-rapidas .btn-warning');
    
    if (mostrar) {
        // Mostrar los botones
        if (btnEscanerQR) btnEscanerQR.style.display = '';
        if (btnCancelarBoleto) btnCancelarBoleto.style.display = '';
        if (btnCategorias) btnCategorias.style.display = '';
    } else {
        // Ocultar los botones
        if (btnEscanerQR) btnEscanerQR.style.display = 'none';
        if (btnCancelarBoleto) btnCancelarBoleto.style.display = 'none';
        if (btnCategorias) btnCategorias.style.display = 'none';
    }
}
