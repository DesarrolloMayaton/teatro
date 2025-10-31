// Sistema de carrito de compras
let carrito = [];
let asientosVendidos = new Set();

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
        alert('Este asiento ya está vendido');
        return false;
    }

    // Verificar si ya está en el carrito
    if (carrito.find(item => item.asiento === asientoId)) {
        alert('Este asiento ya está en tu carrito');
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
    let total = 0;

    carrito.forEach(item => {
        total += item.precio;
        html += `
            <div class="carrito-item">
                <div class="asiento-info">
                    <strong>${item.asiento}</strong><br>
                    <small>${item.categoria}</small><br>
                    <span class="text-success">$${item.precio.toFixed(2)}</span>
                </div>
                <button class="btn-remove" onclick="removerDelCarrito('${item.asiento}')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
    });

    carritoContainer.innerHTML = html;
    totalElement.textContent = `$${total.toFixed(2)}`;
    btnPagar.disabled = false;
}

// Procesar pago
async function procesarPago() {
    if (carrito.length === 0) {
        alert('No hay asientos en el carrito');
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');

    if (!idEvento) {
        alert('Error: No se ha seleccionado un evento');
        return;
    }

    const btnPagar = document.getElementById('btnPagar');
    btnPagar.disabled = true;
    btnPagar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

    try {
        const response = await fetch('procesar_compra.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_evento: idEvento,
                asientos: carrito
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
            alert(`¡Compra exitosa! Se generaron ${data.boletos.length} boleto(s)`);

            // Agregar asientos vendidos al set
            carrito.forEach(item => asientosVendidos.add(item.asiento));

            // Limpiar carrito
            document.querySelectorAll('.seat.selected').forEach(s => s.classList.remove('selected'));
            carrito = [];
            actualizarCarrito();
            marcarAsientosVendidos();

            // Mostrar códigos QR
            mostrarBoletosGenerados(data.boletos);
        } else {
            alert('Error al procesar la compra: ' + data.message);
        }
    } catch (error) {
        console.error('Error completo:', error);
        alert('Error al procesar la compra: ' + error.message);
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

// Descargar todos los boletos
function descargarTodosBoletos() {
    if (!window.boletosActuales || window.boletosActuales.length === 0) return;

    window.boletosActuales.forEach((boleto, index) => {
        setTimeout(() => {
            window.open(`descargar_boleto.php?codigo=${boleto.codigo_unico}`, '_blank');
        }, index * 500); // Delay de 500ms entre cada descarga
    });
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', () => {
    cargarAsientosVendidos();

    // Modificar el comportamiento de click en los asientos
    document.querySelectorAll('.seat').forEach(seat => {
        seat.addEventListener('click', (e) => {
            const asientoId = seat.dataset.asientoId;
            const categoriaId = seat.dataset.categoriaId;

            // Si ya está seleccionado, remover
            if (seat.classList.contains('selected')) {
                removerDelCarrito(asientoId);
                seat.classList.remove('selected');
            } else if (!seat.classList.contains('vendido')) {
                // Si no está vendido, agregar
                if (agregarAlCarrito(asientoId, categoriaId)) {
                    seat.classList.add('selected');
                }
            }

            // Prevenir que se abra el modal de información
            e.stopPropagation();
        });
    });
});
