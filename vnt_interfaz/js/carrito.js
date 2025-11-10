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
    if (!descuentoSeleccionado) return 0;

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
        return;
    }

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
                    <strong>${item.asiento}</strong><br>
                    <small>${item.categoria}</small><br>
                    <span class="text-success">$${item.precio.toFixed(2)}</span>
                    ${descuentoItem > 0 ? `<br><small class="text-danger">-$${descuentoItem.toFixed(2)}</small>` : ''}
                    ${descuentoItem > 0 ? `<br><strong class="text-primary">$${precioFinal.toFixed(2)}</strong>` : ''}
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

// Procesar pago
async function procesarPago() {
    if (carrito.length === 0) {
        notify.warning('No hay asientos en el carrito');
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');

    if (!idEvento) {
        notify.error('Error: No se ha seleccionado un evento');
        return;
    }

    const btnPagar = document.getElementById('btnPagar');
    btnPagar.disabled = true;
    btnPagar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

    // Preparar datos con descuentos aplicados
    const asientosConDescuento = carrito.map(item => {
        const descuentoItem = calcularDescuentoItem(item);
        return {
            ...item,
            descuento_aplicado: descuentoItem,
            precio_final: item.precio - descuentoItem,
            id_promocion: descuentoSeleccionado ? descuentoSeleccionado.id_promocion : null
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
        btnPagar.innerHTML = '<i class="bi bi-credit-card"></i> Pagar';
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
