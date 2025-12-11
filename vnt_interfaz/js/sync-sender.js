// js/sync-sender.js
const canalSender = new BroadcastChannel('pos_sync_channel');

// 1. Enviar Evento Inicial
function enviarEventoInicial() {
    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');
    if (idEvento) {
        canalSender.postMessage({ accion: 'INIT', id_evento: idEvento });
    }
}

// 2. Enviar Función Seleccionada
function enviarFuncion() {
    const select = document.getElementById('selectFuncion');
    if (select && select.value && select.selectedIndex >= 0) {
        const texto = select.options[select.selectedIndex].text;
        canalSender.postMessage({ accion: 'UPDATE_FUNCION', texto: texto });
    }
}

// 3. Enviar Carrito (Esta se llama desde carrito.js)
function enviarCarrito(carritoArray) {
    let total = 0;
    const arraySimple = carritoArray.map(item => {
        const precioFinal = item.precio;
        total += parseFloat(precioFinal);
        return {
            id: item.asiento,
            precio: precioFinal,
            categoria: item.categoria,
            color: item.color || '#2563eb'
        };
    });

    canalSender.postMessage({
        accion: 'UPDATE_CARRITO',
        carrito: arraySimple,
        total: total
    });
}

// 4. Enviar Vendidos
function enviarVendidos(listaIds) {
    canalSender.postMessage({
        accion: 'UPDATE_VENDIDOS',
        asientos: listaIds
    });
}

// 5. Enviar compra exitosa (muestra animación de gracias)
function enviarCompraExitosa(total, cantidadBoletos) {
    canalSender.postMessage({
        accion: 'COMPRA_EXITOSA',
        total: total,
        cantidad: cantidadBoletos
    });
}

// 6. Enviar a cliente de regreso a cartelera
function enviarRegresarCartelera() {
    canalSender.postMessage({
        accion: 'REGRESAR_CARTELERA'
    });
}

// Inicializadores
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const idEvento = urlParams.get('id_evento');

    // Si hay evento, enviar INIT; si no hay, enviar al cliente a cartelera
    if (idEvento) {
        enviarEventoInicial();
    } else {
        // Vendedor está en cartelera, cliente también debe ir a cartelera
        enviarRegresarCartelera();
    }

    const selectFun = document.getElementById('selectFuncion');
    if (selectFun) {
        selectFun.addEventListener('change', enviarFuncion);
    }
});