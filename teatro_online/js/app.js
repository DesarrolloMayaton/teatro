/**
 * Teatro Online - JavaScript Principal
 * ====================================
 * Interfaz intuitiva con progress steps, zoom y sincronización en tiempo real
 */

// ============================================
// ESTADO GLOBAL
// ============================================
const estado = {
    eventoSeleccionado: null,
    eventoTipo: 1,
    eventoTitulo: '',
    funcionSeleccionada: null,
    funcionTexto: '',
    asientosSeleccionados: [],
    categorias: [],
    asientosVendidos: [],
    lastChangeId: 0,
    eventSource: null,
    pasoActual: 1,
    zoom: 1.0,
};

// ============================================
// INICIALIZACIÓN
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    cargarEventos();
    document.getElementById('form-datos').addEventListener('submit', procesarCompra);
});

// ============================================
// PROGRESS STEPS
// ============================================
function actualizarProgreso(paso) {
    estado.pasoActual = paso;
    const steps = document.querySelectorAll('.progress-step');
    steps.forEach((s) => {
        const n = parseInt(s.dataset.step);
        s.classList.remove('active', 'completed');
        if (n < paso) s.classList.add('completed');
        else if (n === paso) s.classList.add('active');
    });
    const line = document.getElementById('progressLine');
    const total = steps.length - 1;
    const pct = ((paso - 1) / total) * 100;
    line.style.width = pct + '%';
}

function irAPaso(numero) {
    const mapa = {
        1: 'paso-evento',
        2: 'paso-funcion',
        3: 'paso-asientos',
        4: 'paso-datos',
        5: 'paso-confirmacion',
    };
    document.querySelectorAll('.paso').forEach((p) => p.classList.remove('activo'));
    document.getElementById(mapa[numero]).classList.add('activo');
    actualizarProgreso(numero);
    window.scrollTo({ top: 0, behavior: 'smooth' });

    if (numero < 3 && estado.eventSource) {
        estado.eventSource.close();
        estado.eventSource = null;
    }
}

// ============================================
// PASO 1: CARGAR EVENTOS
// ============================================
async function cargarEventos() {
    try {
        const res = await fetch('api/eventos.php');
        const data = await res.json();
        if (data.success) renderizarEventos(data.eventos);
        else mostrarError('Error al cargar eventos');
    } catch (err) {
        mostrarError('No pudimos cargar los eventos. Verifica tu conexión.');
    }
}

function renderizarEventos(eventos) {
    const grid = document.getElementById('eventos-grid');
    if (!eventos || eventos.length === 0) {
        grid.innerHTML = `
            <div class="loading-container" style="grid-column:1/-1;">
                <i class="bi bi-calendar-x" style="font-size:4rem;color:#3a3a3c;"></i>
                <p>No hay eventos disponibles en este momento.<br>Vuelve pronto.</p>
            </div>`;
        return;
    }

    grid.innerHTML = eventos
        .map(
            (e) => `
        <article class="evento-card" onclick="seleccionarEvento(${e.id_evento}, '${escapeHtml(e.titulo)}', ${e.tipo})">
            <div class="evento-imagen-wrapper">
                ${
                    e.imagen
                        ? `<img src="${e.imagen}" alt="${escapeHtml(e.titulo)}" loading="lazy">`
                        : `<div class="evento-imagen-placeholder"><i class="bi bi-image"></i></div>`
                }
                <span class="evento-tipo-badge">${e.tipo_texto || 'Teatro'}</span>
            </div>
            <div class="evento-body">
                <h3 class="evento-titulo">${escapeHtml(e.titulo)}</h3>
                <p class="evento-desc">${escapeHtml(e.descripcion || 'Una experiencia única te espera.')}</p>
                <div class="evento-funciones">
                    <i class="bi bi-calendar-event-fill"></i>
                    ${e.funciones_disponibles} ${e.funciones_disponibles === 1 ? 'función disponible' : 'funciones disponibles'}
                </div>
                <button class="evento-btn-comprar">
                    Ver funciones <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </article>
        `
        )
        .join('');
}

// ============================================
// PASO 2: SELECCIONAR EVENTO Y CARGAR FUNCIONES
// ============================================
async function seleccionarEvento(idEvento, titulo, tipo) {
    estado.eventoSeleccionado = idEvento;
    estado.eventoTitulo = titulo;
    estado.eventoTipo = parseInt(tipo) || 1;
    document.getElementById('evento-titulo-funcion').textContent = titulo;

    mostrarCarga('Cargando funciones disponibles...');
    try {
        const [catRes, funcRes] = await Promise.all([
            fetch(`api/categorias.php?id_evento=${idEvento}`),
            fetch(`api/funciones.php?id_evento=${idEvento}`),
        ]);
        const catData = await catRes.json();
        const funcData = await funcRes.json();
        ocultarCarga();

        if (catData.success) estado.categorias = catData.categorias;
        if (funcData.success) {
            renderizarFunciones(funcData.funciones);
            irAPaso(2);
        } else {
            mostrarError('Error al cargar funciones');
        }
    } catch (err) {
        ocultarCarga();
        mostrarError('Error de conexión');
    }
}

function renderizarFunciones(funciones) {
    const grid = document.getElementById('funciones-grid');
    if (!funciones || funciones.length === 0) {
        grid.innerHTML = `
            <div class="loading-container" style="grid-column:1/-1;">
                <i class="bi bi-clock-history" style="font-size:4rem;color:#3a3a3c;"></i>
                <p>No hay funciones disponibles para este evento.</p>
            </div>`;
        return;
    }

    grid.innerHTML = funciones
        .map((f) => {
            const fecha = new Date(f.fecha_hora);
            const opciones = { weekday: 'long', day: 'numeric', month: 'long' };
            const fechaStr = fecha.toLocaleDateString('es-MX', opciones);
            const hora = fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            const vencida = f.vencida == 1 || f.estado == 1;

            return `
            <button class="funcion-card${vencida ? ' disabled' : ''}"
                ${vencida ? 'disabled' : `onclick="seleccionarFuncion(${f.id_funcion}, '${fechaStr} · ${hora}', this)"`}
                style="${vencida ? 'opacity:0.4;cursor:not-allowed;' : ''}">
                <div class="funcion-fecha">${capitalizar(fechaStr)}</div>
                <div class="funcion-hora">🕐 ${hora}</div>
                <span class="funcion-status ${vencida ? 'vencida' : 'disponible'}">
                    <i class="bi bi-${vencida ? 'x-circle-fill' : 'check-circle-fill'}"></i>
                    ${vencida ? 'No disponible' : 'Disponible'}
                </span>
            </button>
            `;
        })
        .join('');
}

// ============================================
// PASO 3: SELECCIONAR FUNCIÓN Y MAPA
// ============================================
async function seleccionarFuncion(idFuncion, texto, elemento) {
    estado.funcionSeleccionada = idFuncion;
    estado.funcionTexto = texto;
    estado.asientosSeleccionados = [];

    document.querySelectorAll('.funcion-card').forEach((c) => c.classList.remove('selected'));
    if (elemento) elemento.classList.add('selected');

    mostrarCarga('Cargando mapa de asientos...');
    try {
        const res = await fetch(
            `api/asientos_vendidos.php?id_evento=${estado.eventoSeleccionado}&id_funcion=${idFuncion}`
        );
        const data = await res.json();
        ocultarCarga();
        if (data.success) {
            estado.asientosVendidos = data.asientos || [];
            generarMapa();
            actualizarCarrito();
            irAPaso(3);
            iniciarSincronizacion();
        } else {
            mostrarError('Error al cargar asientos');
        }
    } catch (err) {
        ocultarCarga();
        mostrarError('Error de conexión');
    }
}

// ============================================
// MAPA DE ASIENTOS
// ============================================
function generarMapa() {
    const grid = document.getElementById('asientos-grid');
    grid.innerHTML = '';
    document.getElementById('escenario-label').textContent =
        estado.eventoTipo == 2 ? 'PASARELA / ESCENARIO' : 'ESCENARIO';

    // Filas A-O del teatro principal
    const filas = 'ABCDEFGHIJKLMNO'.split('');
    filas.forEach((letra) => grid.appendChild(crearFila(letra, 26, true)));

    // Fila P (palco)
    const filaP = document.createElement('div');
    filaP.className = 'fila';
    const lblP = document.createElement('div');
    lblP.className = 'fila-label';
    lblP.textContent = 'P';
    filaP.appendChild(lblP);
    const blockP = document.createElement('div');
    blockP.className = 'asientos-block';
    for (let i = 1; i <= 30; i++) {
        blockP.appendChild(crearAsiento(`P${i}`, i));
    }
    filaP.appendChild(blockP);
    const lblP2 = document.createElement('div');
    lblP2.className = 'fila-label';
    lblP2.textContent = 'P';
    filaP.appendChild(lblP2);
    grid.appendChild(filaP);

    // Pasarela (PB1-PB10)
    if (estado.eventoTipo == 2) {
        const sep = document.createElement('div');
        sep.style.cssText = 'height:24px;';
        grid.appendChild(sep);
        for (let f = 1; f <= 10; f++) {
            grid.appendChild(crearFilaPasarela(f));
        }
    }
}

function crearFila(letra, numAsientos, conPasillos) {
    const fila = document.createElement('div');
    fila.className = 'fila';

    const lblIzq = document.createElement('div');
    lblIzq.className = 'fila-label';
    lblIzq.textContent = letra;
    fila.appendChild(lblIzq);

    const block = document.createElement('div');
    block.className = 'asientos-block';

    if (conPasillos) {
        // 6 + pasillo + 14 + pasillo + 6 = 26
        for (let i = 1; i <= 6; i++) block.appendChild(crearAsiento(`${letra}${i}`, i));
        const p1 = document.createElement('div');
        p1.className = 'pasillo';
        block.appendChild(p1);
        for (let i = 7; i <= 20; i++) block.appendChild(crearAsiento(`${letra}${i}`, i));
        const p2 = document.createElement('div');
        p2.className = 'pasillo';
        block.appendChild(p2);
        for (let i = 21; i <= 26; i++) block.appendChild(crearAsiento(`${letra}${i}`, i));
    } else {
        for (let i = 1; i <= numAsientos; i++) block.appendChild(crearAsiento(`${letra}${i}`, i));
    }

    fila.appendChild(block);

    const lblDer = document.createElement('div');
    lblDer.className = 'fila-label';
    lblDer.textContent = letra;
    fila.appendChild(lblDer);

    return fila;
}

function crearFilaPasarela(numFila) {
    const fila = document.createElement('div');
    fila.className = 'fila';

    const lblIzq = document.createElement('div');
    lblIzq.className = 'fila-label';
    lblIzq.textContent = 'PB' + numFila;
    fila.appendChild(lblIzq);

    const block = document.createElement('div');
    block.className = 'asientos-block';
    for (let i = 1; i <= 12; i++) {
        block.appendChild(crearAsiento(`PB${numFila}-${i}`, i));
    }
    fila.appendChild(block);

    const lblDer = document.createElement('div');
    lblDer.className = 'fila-label';
    lblDer.textContent = 'PB' + numFila;
    fila.appendChild(lblDer);

    return fila;
}

function crearAsiento(codigo, numero) {
    const btn = document.createElement('button');
    btn.className = 'asiento';
    btn.dataset.asiento = codigo;
    btn.textContent = numero;
    btn.title = `Asiento ${codigo}`;

    if (estado.asientosVendidos.includes(codigo)) {
        btn.classList.add('vendido');
        btn.disabled = true;
    } else {
        btn.onclick = () => toggleAsiento(codigo, btn);
    }
    return btn;
}

function toggleAsiento(codigo, elemento) {
    const idx = estado.asientosSeleccionados.findIndex((a) => a.asiento === codigo);
    if (idx > -1) {
        estado.asientosSeleccionados.splice(idx, 1);
        elemento.classList.remove('selected');
    } else {
        const cat = estado.categorias[0] || { id_categoria: 0, nombre_categoria: 'General', precio: 80 };
        estado.asientosSeleccionados.push({
            asiento: codigo,
            categoriaId: cat.id_categoria,
            categoriaNombre: cat.nombre_categoria,
            precio: parseFloat(cat.precio),
            precio_final: parseFloat(cat.precio),
            tipo_boleto: 'adulto',
        });
        elemento.classList.add('selected');
    }
    actualizarCarrito();
}

// ============================================
// ZOOM DEL MAPA
// ============================================
function zoomMapa(delta) {
    if (delta === 0) estado.zoom = 1.0;
    else estado.zoom = Math.max(0.5, Math.min(1.8, estado.zoom + delta));
    document.getElementById('mapa-content').style.transform = `scale(${estado.zoom})`;
    document.getElementById('zoomLevel').textContent = Math.round(estado.zoom * 100) + '%';
}

// ============================================
// CARRITO
// ============================================
function actualizarCarrito() {
    const body = document.getElementById('carrito-body');
    const total = document.getElementById('carrito-total');
    const count = document.getElementById('carrito-count');
    const btn = document.getElementById('btn-continuar');

    if (estado.asientosSeleccionados.length === 0) {
        body.innerHTML = `
            <div class="carrito-vacio-msg">
                <i class="bi bi-cart"></i>
                <p>Selecciona asientos para verlos aquí</p>
            </div>`;
        total.textContent = '$0.00';
        count.textContent = '0 boletos';
        btn.disabled = true;
        return;
    }

    body.innerHTML = estado.asientosSeleccionados
        .map(
            (item, i) => `
        <div class="carrito-item-online">
            <div class="carrito-item-info">
                <strong>Asiento ${item.asiento}</strong>
                <small>$${item.precio_final.toFixed(2)} · ${item.categoriaNombre || 'General'}</small>
            </div>
            <button class="carrito-item-remove" onclick="quitarAsiento(${i})" title="Quitar">
                <i class="bi bi-trash-fill"></i>
            </button>
        </div>
        `
        )
        .join('');

    const sum = estado.asientosSeleccionados.reduce((s, x) => s + x.precio_final, 0);
    total.textContent = '$' + sum.toFixed(2);
    count.textContent = `${estado.asientosSeleccionados.length} ${estado.asientosSeleccionados.length === 1 ? 'boleto' : 'boletos'}`;
    btn.disabled = false;
}

function quitarAsiento(index) {
    const item = estado.asientosSeleccionados[index];
    estado.asientosSeleccionados.splice(index, 1);
    const el = document.querySelector(`[data-asiento="${item.asiento}"]`);
    if (el) el.classList.remove('selected');
    actualizarCarrito();
}

// ============================================
// PASO 4: DATOS Y RESUMEN
// ============================================
function continuarADatos() {
    if (estado.asientosSeleccionados.length === 0) return;

    const detalle = document.getElementById('resumen-detalle');
    detalle.innerHTML = `
        <div class="resumen-item">
            <span><i class="bi bi-mask"></i> Evento</span>
            <strong>${escapeHtml(estado.eventoTitulo)}</strong>
        </div>
        <div class="resumen-item">
            <span><i class="bi bi-calendar-event"></i> Función</span>
            <strong>${escapeHtml(estado.funcionTexto)}</strong>
        </div>
        <div class="resumen-item">
            <span><i class="bi bi-grid-3x3-gap-fill"></i> Asientos</span>
            <strong>${estado.asientosSeleccionados.map((a) => a.asiento).join(', ')}</strong>
        </div>
        <div class="resumen-item">
            <span><i class="bi bi-ticket-perforated-fill"></i> Cantidad</span>
            <strong>${estado.asientosSeleccionados.length} boletos</strong>
        </div>
    `;
    const total = estado.asientosSeleccionados.reduce((s, x) => s + x.precio_final, 0);
    document.getElementById('resumen-total').textContent = '$' + total.toFixed(2);

    irAPaso(4);
}

// ============================================
// PROCESAR COMPRA
// ============================================
async function procesarCompra(e) {
    e.preventDefault();
    const nombre = document.getElementById('nombre_cliente').value.trim();
    const email = document.getElementById('email_cliente').value.trim();
    const telefono = document.getElementById('telefono_cliente').value.trim();

    if (!nombre || !email) {
        mostrarError('Por favor completa los campos obligatorios');
        return;
    }

    mostrarCarga('Procesando tu compra...');
    try {
        const res = await fetch('api/comprar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_evento: estado.eventoSeleccionado,
                id_funcion: estado.funcionSeleccionada,
                asientos: estado.asientosSeleccionados,
                nombre_cliente: nombre,
                email_cliente: email,
                telefono_cliente: telefono,
            }),
        });
        const data = await res.json();
        ocultarCarga();

        if (data.success) {
            mostrarConfirmacion(data.boletos, nombre);
        } else {
            mostrarError(data.message || 'Error al procesar la compra');
        }
    } catch (err) {
        ocultarCarga();
        mostrarError('Error de conexión. Inténtalo de nuevo.');
    }
}

function mostrarConfirmacion(boletos, nombre) {
    irAPaso(5);
    document.getElementById('confirmacion-mensaje').textContent =
        `¡Gracias ${nombre}! Hemos enviado tus boletos a tu correo electrónico.`;
    const lista = document.getElementById('boletos-lista');
    lista.innerHTML = boletos
        .map(
            (b) => `
        <div class="boleto-item">
            <div class="boleto-row">
                <span><i class="bi bi-grid-3x3-gap-fill"></i> Asiento</span>
                <strong>${b.asiento}</strong>
            </div>
            <div class="boleto-row">
                <span><i class="bi bi-qr-code"></i> Código</span>
                <strong>${b.codigo_unico}</strong>
            </div>
            <div class="boleto-row">
                <span><i class="bi bi-cash-coin"></i> Precio</span>
                <strong>$${parseFloat(b.precio).toFixed(2)}</strong>
            </div>
        </div>`
        )
        .join('');

    if (estado.eventSource) {
        estado.eventSource.close();
        estado.eventSource = null;
    }
}

// ============================================
// SINCRONIZACIÓN EN TIEMPO REAL (SSE)
// ============================================
function iniciarSincronizacion() {
    if (estado.eventSource) estado.eventSource.close();
    const url = `api/cambios.php?last_id=${estado.lastChangeId}&id_evento=${estado.eventoSeleccionado}&id_funcion=${estado.funcionSeleccionada}`;
    try {
        estado.eventSource = new EventSource(url);
    } catch (e) {
        return;
    }

    estado.eventSource.addEventListener('cambio', (ev) => {
        const cambio = JSON.parse(ev.data);
        estado.lastChangeId = cambio.id;
        if (cambio.tipo === 'venta' && cambio.datos && cambio.datos.asientos) {
            cambio.datos.asientos.forEach((codigo) => {
                if (!estado.asientosVendidos.includes(codigo)) {
                    estado.asientosVendidos.push(codigo);
                    const el = document.querySelector(`[data-asiento="${codigo}"]`);
                    if (el) {
                        el.classList.remove('selected');
                        el.classList.add('vendido');
                        el.disabled = true;
                        el.onclick = null;
                    }
                    const idx = estado.asientosSeleccionados.findIndex((a) => a.asiento === codigo);
                    if (idx > -1) {
                        estado.asientosSeleccionados.splice(idx, 1);
                        actualizarCarrito();
                        notificacion(`El asiento ${codigo} acaba de venderse y fue retirado de tu carrito.`);
                    }
                }
            });
        }
    });

    estado.eventSource.onerror = () => {
        if (estado.eventSource) estado.eventSource.close();
        setTimeout(() => {
            if (estado.pasoActual === 3) iniciarSincronizacion();
        }, 5000);
    };
}

// ============================================
// UTILIDADES
// ============================================
function mostrarCarga(msg) {
    document.getElementById('mensaje-carga').textContent = msg || 'Procesando...';
    document.getElementById('modal-carga').classList.add('active');
}

function ocultarCarga() {
    document.getElementById('modal-carga').classList.remove('active');
}

function mostrarError(msg) {
    notificacion(msg, true);
}

function notificacion(mensaje, esError = false) {
    const div = document.createElement('div');
    div.style.cssText = `
        position: fixed; top: 24px; left: 50%; transform: translateX(-50%);
        background: ${esError ? 'linear-gradient(135deg, #ff453a, #c92a1f)' : 'linear-gradient(135deg, #1561f0, #0d4fc4)'};
        color: #fff; padding: 14px 24px; border-radius: 12px; z-index: 9999;
        box-shadow: 0 8px 24px rgba(0,0,0,0.4); font-weight: 600;
        animation: slideInRight 0.3s ease;
    `;
    div.innerHTML = `<i class="bi bi-${esError ? 'exclamation-triangle-fill' : 'info-circle-fill'}"></i> ${mensaje}`;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 4000);
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function capitalizar(s) {
    return s.charAt(0).toUpperCase() + s.slice(1);
}
