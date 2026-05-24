/**
 * Teatro Online - JavaScript Principal
 * ====================================
 * Con sincronización en tiempo real con vnt_interfaz
 */

// Estado global
const estado = {
    eventoSeleccionado: null,
    funcionSeleccionada: null,
    asientosSeleccionados: [],
    categorias: [],
    asientosVendidos: [],
    lastChangeId: 0,
    eventSource: null
};

// Elementos DOM
const elementos = {
    eventosContainer: document.getElementById('eventos-container'),
    funcionesContainer: document.getElementById('funciones-container'),
    asientosGrid: document.getElementById('asientos-grid'),
    carritoItems: document.getElementById('carrito-items'),
    totalPrecio: document.getElementById('total-precio'),
    btnContinuarDatos: document.getElementById('btn-continuar-datos'),
    resumenCompra: document.getElementById('resumen-compra'),
    totalPagar: document.getElementById('total-pagar'),
    modalCarga: new bootstrap.Modal(document.getElementById('modal-carga')),
    mensajeCarga: document.getElementById('mensaje-carga')
};

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    cargarEventos();
    configurarFormularioDatos();
});

// Cargar eventos
async function cargarEventos() {
    try {
        const response = await fetch('api/eventos.php');
        const data = await response.json();
        
        if (data.success) {
            renderizarEventos(data.eventos);
        } else {
            mostrarError('Error al cargar eventos');
        }
    } catch (error) {
        mostrarError('Error de conexión');
    }
}

// Renderizar eventos
function renderizarEventos(eventos) {
    if (eventos.length === 0) {
        elementos.eventosContainer.innerHTML = '<p class="text-center text-muted">No hay eventos disponibles</p>';
        return;
    }
    
    elementos.eventosContainer.innerHTML = eventos.map(evento => `
        <div class="col-md-6 col-lg-4">
            <div class="teatro-card evento-card" onclick="seleccionarEvento(${evento.id_evento})" style="cursor: pointer; transition: var(--transition-normal);">
                <div class="position-relative">
                    ${evento.imagen ? 
                        `<img src="${evento.imagen}" class="w-100 rounded" style="height: 180px; object-fit: cover;" alt="${evento.titulo}">` :
                        `<div class="w-100 rounded d-flex align-items-center justify-content-center bg-secondary text-white" style="height: 180px;">
                            <i class="bi bi-image display-4"></i>
                        </div>`
                    }
                    <span class="badge bg-primary position-absolute top-0 end-0 m-2">${evento.tipo_texto}</span>
                </div>
                <div class="card-body pt-3">
                    <h5 class="card-title text-primary">${evento.titulo}</h5>
                    <p class="card-text text-muted small">${evento.descripcion || 'Sin descripción'}</p>
                    <p class="card-text text-muted">
                        <i class="bi bi-calendar-check me-1"></i>
                        ${evento.funciones_disponibles} función(es) disponible(s)
                    </p>
                </div>
            </div>
        </div>
    `).join('');
}

// Seleccionar evento
async function seleccionarEvento(idEvento) {
    estado.eventoSeleccionado = idEvento;
    mostrarCarga('Cargando funciones...');
    
    try {
        // Cargar categorías
        const catResponse = await fetch(`api/categorias.php?id_evento=${idEvento}`);
        const catData = await catResponse.json();
        
        if (catData.success) {
            estado.categorias = catData.categorias;
        }
        
        // Cargar funciones
        const funcResponse = await fetch(`api/funciones.php?id_evento=${idEvento}`);
        const funcData = await funcResponse.json();
        
        elementos.modalCarga.hide();
        
        if (funcData.success) {
            renderizarFunciones(funcData.funciones);
            cambiarPaso('paso-funcion');
        } else {
            mostrarError('Error al cargar funciones');
        }
    } catch (error) {
        elementos.modalCarga.hide();
        mostrarError('Error de conexión');
    }
}

// Renderizar funciones
function renderizarFunciones(funciones) {
    if (funciones.length === 0) {
        elementos.funcionesContainer.innerHTML = '<p class="text-center text-muted">No hay funciones disponibles</p>';
        return;
    }
    
    elementos.funcionesContainer.innerHTML = funciones.map(funcion => `
        <div class="col-md-4">
            <div class="teatro-card funcion-card" onclick="seleccionarFuncion(${funcion.id_funcion}, this)" style="cursor: pointer; border: 2px solid var(--border-color); transition: var(--transition-normal);">
                <div class="card-body text-center">
                    <h5 class="card-title">${funcion.texto}</h5>
                    ${funcion.vencida ? 
                        '<span class="badge bg-danger">Vencida</span>' :
                        '<span class="badge bg-success">Disponible</span>'
                    }
                </div>
            </div>
        </div>
    `).join('');
}

// Seleccionar función
async function seleccionarFuncion(idFuncion, elemento) {
    if (elemento.classList.contains('selected')) return;
    
    // Remover selección anterior
    document.querySelectorAll('.funcion-card').forEach(el => {
        el.classList.remove('selected');
        el.style.borderColor = 'var(--border-color)';
        el.style.background = 'var(--bg-card)';
    });
    elemento.classList.add('selected');
    elemento.style.borderColor = 'var(--accent-blue)';
    elemento.style.background = 'var(--bg-tertiary)';
    
    estado.funcionSeleccionada = idFuncion;
    mostrarCarga('Cargando mapa de asientos...');
    
    try {
        // Cargar asientos vendidos
        const response = await fetch(`api/asientos_vendidos.php?id_evento=${estado.eventoSeleccionado}&id_funcion=${idFuncion}`);
        const data = await response.json();
        
        elementos.modalCarga.hide();
        
        if (data.success) {
            estado.asientosVendidos = data.asientos;
            generarMapaAsientos();
            cambiarPaso('paso-asientos');
            iniciarSincronizacion();
        } else {
            mostrarError('Error al cargar asientos');
        }
    } catch (error) {
        elementos.modalCarga.hide();
        mostrarError('Error de conexión');
    }
}

// Generar mapa de asientos
function generarMapaAsientos() {
    const grid = elementos.asientosGrid;
    grid.innerHTML = '';
    
    // Generar mapa Teatro 420: filas A-O
    const letras = range('A', 'O');
    
    letras.forEach(fila => {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'seat-row-wrapper';
        
        // Etiqueta de fila
        const labelDiv = document.createElement('div');
        labelDiv.className = 'row-label';
        labelDiv.textContent = fila;
        rowDiv.appendChild(labelDiv);
        
        // Bloque de asientos
        const blockDiv = document.createElement('div');
        blockDiv.className = 'seats-block';
        
        // 6 asientos izquierda
        for (let i = 0; i < 6; i++) {
            const numero = i + 1;
            const codigo = `${fila}${numero}`;
            blockDiv.appendChild(crearAsiento(codigo));
        }
        
        // Pasillo
        const pasilloDiv = document.createElement('div');
        pasilloDiv.className = 'pasillo';
        blockDiv.appendChild(pasilloDiv);
        
        // 14 asientos centro
        for (let i = 0; i < 14; i++) {
            const numero = i + 7;
            const codigo = `${fila}${numero}`;
            blockDiv.appendChild(crearAsiento(codigo));
        }
        
        // Pasillo
        const pasilloDiv2 = document.createElement('div');
        pasilloDiv2.className = 'pasillo';
        blockDiv.appendChild(pasilloDiv2);
        
        // 6 asientos derecha
        for (let i = 0; i < 6; i++) {
            const numero = i + 21;
            const codigo = `${fila}${numero}`;
            blockDiv.appendChild(crearAsiento(codigo));
        }
        
        rowDiv.appendChild(blockDiv);
        
        // Etiqueta de fila derecha
        const labelDiv2 = document.createElement('div');
        labelDiv2.className = 'row-label';
        labelDiv2.textContent = fila;
        rowDiv.appendChild(labelDiv2);
        
        grid.appendChild(rowDiv);
    });
    
    // Fila P (palco)
    const rowP = document.createElement('div');
    rowP.className = 'seat-row-wrapper';
    
    const labelP = document.createElement('div');
    labelP.className = 'row-label';
    labelP.textContent = 'P';
    rowP.appendChild(labelP);
    
    const blockP = document.createElement('div');
    blockP.className = 'seats-block';
    
    for (let i = 1; i <= 30; i++) {
        const codigo = `P${i}`;
        blockP.appendChild(crearAsiento(codigo));
    }
    
    rowP.appendChild(blockP);
    grid.appendChild(rowP);
}

// Crear elemento de asiento
function crearAsiento(codigo) {
    const div = document.createElement('div');
    div.className = 'seat';
    div.dataset.asiento = codigo;
    div.textContent = codigo;
    
    // Verificar si está vendido
    if (estado.asientosVendidos.includes(codigo)) {
        div.classList.add('vendido');
    } else {
        div.onclick = () => toggleAsiento(codigo, div);
    }
    
    return div;
}

// Toggle selección de asiento
function toggleAsiento(codigo, elemento) {
    const index = estado.asientosSeleccionados.findIndex(a => a.asiento === codigo);
    
    if (index > -1) {
        // Deseleccionar
        estado.asientosSeleccionados.splice(index, 1);
        elemento.classList.remove('selected');
    } else {
        // Seleccionar
        const categoria = estado.categorias[0] || { id_categoria: 0, nombre: 'General', precio: 0 };
        estado.asientosSeleccionados.push({
            asiento: codigo,
            categoriaId: categoria.id_categoria,
            precio: categoria.precio,
            precio_final: categoria.precio,
            tipo_boleto: 'adulto'
        });
        elemento.classList.add('selected');
    }
    
    actualizarCarrito();
}

// Actualizar carrito
function actualizarCarrito() {
    if (estado.asientosSeleccionados.length === 0) {
        elementos.carritoItems.innerHTML = '<div class="carrito-vacio">No hay asientos seleccionados</div>';
        elementos.totalPrecio.textContent = '$0.00';
        elementos.btnContinuarDatos.disabled = true;
        return;
    }
    
    elementos.carritoItems.innerHTML = estado.asientosSeleccionados.map((item, index) => `
        <div class="carrito-item">
            <div class="asiento-info">
                <strong>${item.asiento}</strong>
                <small>$${item.precio_final.toFixed(2)}</small>
            </div>
            <button class="btn-remove" onclick="removerAsiento(${index})">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `).join('');
    
    const total = estado.asientosSeleccionados.reduce((sum, item) => sum + item.precio_final, 0);
    elementos.totalPrecio.textContent = `$${total.toFixed(2)}`;
    elementos.btnContinuarDatos.disabled = false;
}

// Remover asiento del carrito
function removerAsiento(index) {
    const item = estado.asientosSeleccionados[index];
    estado.asientosSeleccionados.splice(index, 1);
    
    // Actualizar visualmente
    const elemento = document.querySelector(`[data-asiento="${item.asiento}"]`);
    if (elemento) {
        elemento.classList.remove('selected');
    }
    
    actualizarCarrito();
}

// Configurar formulario de datos
function configurarFormularioDatos() {
    const form = document.getElementById('form-datos-cliente');
    form.addEventListener('submit', procesarCompra);
}

// Procesar compra
async function procesarCompra(e) {
    e.preventDefault();
    
    const nombre = document.getElementById('nombre_cliente').value;
    const email = document.getElementById('email_cliente').value;
    const telefono = document.getElementById('telefono_cliente').value;
    
    if (!nombre || !email) {
        alert('Por favor completa los campos obligatorios');
        return;
    }
    
    mostrarCarga('Procesando compra...');
    
    const datos = {
        id_evento: estado.eventoSeleccionado,
        id_funcion: estado.funcionSeleccionada,
        asientos: estado.asientosSeleccionados,
        nombre_cliente: nombre,
        email_cliente: email
    };
    
    try {
        const response = await fetch('api/comprar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });
        
        const data = await response.json();
        
        elementos.modalCarga.hide();
        
        if (data.success) {
            mostrarConfirmacion(data.boletos, nombre);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        elementos.modalCarga.hide();
        alert('Error de conexión');
    }
}

// Mostrar confirmación
function mostrarConfirmacion(boletos, nombre) {
    cambiarPaso('paso-confirmacion');
    
    document.getElementById('confirmacion-mensaje').textContent = 
        `Gracias ${nombre}, tus boletos han sido generados exitosamente.`;
    
    document.getElementById('boletos-generados').innerHTML = `
        <div class="teatro-card">
            <div class="card-body">
                <h6 class="mb-3">Tus Boletos:</h6>
                ${boletos.map(b => `
                    <div class="d-flex justify-content-between py-2 border-bottom border-subtle">
                        <span><strong>Asiento:</strong> ${b.asiento}</span>
                        <span><strong>Código:</strong> ${b.codigo_unico}</span>
                        <span><strong>Precio:</strong> $${b.precio.toFixed(2)}</span>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    // Detener sincronización
    if (estado.eventSource) {
        estado.eventSource.close();
    }
}

// Iniciar sincronización en tiempo real
function iniciarSincronizacion() {
    if (estado.eventSource) {
        estado.eventSource.close();
    }
    
    const url = `api/cambios.php?last_id=${estado.lastChangeId}&id_evento=${estado.eventoSeleccionado}&id_funcion=${estado.funcionSeleccionada}`;
    
    estado.eventSource = new EventSource(url);
    
    estado.eventSource.addEventListener('connected', (e) => {
        console.log('SSE conectado');
    });
    
    estado.eventSource.addEventListener('cambio', (e) => {
        const cambio = JSON.parse(e.data);
        estado.lastChangeId = cambio.id;
        
        if (cambio.tipo === 'venta' && cambio.datos && cambio.datos.asientos) {
            // Actualizar asientos vendidos
            cambio.datos.asientos.forEach(asiento => {
                if (!estado.asientosVendidos.includes(asiento)) {
                    estado.asientosVendidos.push(asiento);
                    
                    // Actualizar visualmente
                    const elemento = document.querySelector(`[data-asiento="${asiento}"]`);
                    if (elemento) {
                        elemento.classList.remove('selected');
                        elemento.classList.add('vendido');
                        elemento.onclick = null;
                        
                        // Remover del carrito si estaba seleccionado
                        const index = estado.asientosSeleccionados.findIndex(a => a.asiento === asiento);
                        if (index > -1) {
                            estado.asientosSeleccionados.splice(index, 1);
                            actualizarCarrito();
                        }
                    }
                }
            });
        }
    });
    
    estado.eventSource.addEventListener('reconnect', (e) => {
        const data = JSON.parse(e.data);
        estado.lastChangeId = data.last_id;
        estado.eventSource.close();
        iniciarSincronizacion();
    });
    
    estado.eventSource.onerror = () => {
        estado.eventSource.close();
        // Reintentar después de 5 segundos
        setTimeout(iniciarSincronizacion, 5000);
    };
}

// Utilidades
function cambiarPaso(pasoId) {
    document.querySelectorAll('[id^="paso-"]').forEach(el => {
        el.classList.remove('paso-activo');
        el.classList.add('paso-inactivo');
    });
    
    document.getElementById(pasoId).classList.remove('paso-inactivo');
    document.getElementById(pasoId).classList.add('paso-activo');
}

function mostrarCarga(mensaje) {
    elementos.mensajeCarga.textContent = mensaje;
    elementos.modalCarga.show();
}

function mostrarError(mensaje) {
    alert(mensaje);
}

function volverEventos() {
    cambiarPaso('paso-evento');
    estado.eventoSeleccionado = null;
    estado.funcionSeleccionada = null;
}

function volverFunciones() {
    cambiarPaso('paso-funcion');
    estado.funcionSeleccionada = null;
    if (estado.eventSource) {
        estado.eventSource.close();
    }
}

function volverAsientos() {
    cambiarPaso('paso-asientos');
}

function range(start, end) {
    const result = [];
    for (let i = start.charCodeAt(0); i <= end.charCodeAt(0); i++) {
        result.push(String.fromCharCode(i));
    }
    return result;
}

// Actualizar resumen cuando se va a paso de datos
elementos.btnContinuarDatos.addEventListener('click', () => {
    const resumen = estado.asientosSeleccionados.map(item => `
        <div class="d-flex justify-content-between py-1">
            <span>${item.asiento}</span>
            <span>$${item.precio_final.toFixed(2)}</span>
        </div>
    `).join('');
    
    elementos.resumenCompra.innerHTML = resumen;
    const total = estado.asientosSeleccionados.reduce((sum, item) => sum + item.precio_final, 0);
    elementos.totalPagar.textContent = `$${total.toFixed(2)}`;
    
    cambiarPaso('paso-datos');
});
