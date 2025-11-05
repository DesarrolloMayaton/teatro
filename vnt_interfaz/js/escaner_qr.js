// Sistema de escaneo de códigos QR
let html5QrCode = null;
let modalEscaner = null;
let modalBoletoInfo = null;

// Abrir el escáner QR
function abrirEscanerQR() {
    modalEscaner = new bootstrap.Modal(document.getElementById('modalEscanerQR'));
    modalEscaner.show();
    
    // Iniciar escáner cuando el modal se muestre completamente
    document.getElementById('modalEscanerQR').addEventListener('shown.bs.modal', function () {
        iniciarEscaner();
    }, { once: true });
    
    // Detener escáner cuando se cierre el modal
    document.getElementById('modalEscanerQR').addEventListener('hidden.bs.modal', function () {
        detenerEscaner();
    }, { once: true });
}

// Iniciar el escáner
function iniciarEscaner() {
    html5QrCode = new Html5Qrcode("qr-reader");
    
    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };
    
    html5QrCode.start(
        { facingMode: "environment" }, // Usar cámara trasera
        config,
        onScanSuccess,
        onScanError
    ).catch(err => {
        console.error("Error al iniciar cámara:", err);
        document.getElementById('qr-reader-results').innerHTML = 
            '<div class="alert alert-danger">Error al acceder a la cámara. Por favor, verifique los permisos.</div>';
    });
}

// Detener el escáner
function detenerEscaner() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            html5QrCode = null;
        }).catch(err => {
            console.error("Error al detener escáner:", err);
        });
    }
}

// Cuando se escanea exitosamente
function onScanSuccess(decodedText, decodedResult) {
    console.log(`Código escaneado: ${decodedText}`);
    
    // Detener el escáner
    detenerEscaner();
    
    // Cerrar modal del escáner
    modalEscaner.hide();
    
    // Buscar información del boleto
    buscarBoleto(decodedText);
}

// Manejo de errores del escáner (se ejecuta constantemente, no mostrar)
function onScanError(errorMessage) {
    // No hacer nada, es normal que haya "errores" mientras busca el QR
}

// Buscar información del boleto
async function buscarBoleto(codigo) {
    try {
        const response = await fetch(`verificar_boleto.php?codigo=${encodeURIComponent(codigo)}`);
        
        // Verificar si la respuesta es OK
        if (!response.ok) {
            throw new Error(`Error del servidor: ${response.status}`);
        }
        
        // Obtener el texto de la respuesta
        const text = await response.text();
        
        // Intentar parsear como JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Respuesta del servidor:', text.substring(0, 500));
            throw new Error('El servidor no devolvió un JSON válido. Revise la consola para más detalles.');
        }
        
        if (data.success) {
            mostrarInfoBoleto(data.boleto);
        } else {
            mostrarError(data.message || 'Boleto no encontrado');
        }
    } catch (error) {
        console.error('Error al buscar boleto:', error);
        mostrarError(error.message || 'Error al verificar el boleto');
    }
}

// Mostrar información del boleto
function mostrarInfoBoleto(boleto) {
    const modal = document.getElementById('modalBoletoInfo');
    const header = document.getElementById('boletoInfoHeader');
    const title = document.getElementById('boletoInfoTitle');
    const body = document.getElementById('boletoInfoBody');
    const footer = document.getElementById('boletoInfoFooter');
    
    // Configurar header según el estado
    if (boleto.estatus == 1) {
        header.className = 'modal-header bg-success text-white';
        title.innerHTML = '<i class="bi bi-check-circle"></i> Boleto Válido';
    } else {
        header.className = 'modal-header bg-danger text-white';
        title.innerHTML = '<i class="bi bi-x-circle"></i> Boleto Usado';
    }
    
    // Construir el cuerpo
    body.innerHTML = `
        <div class="text-center mb-3">
            <img src="../boletos_qr/${boleto.codigo_unico}.png" 
                 alt="QR" 
                 class="img-fluid" 
                 style="max-width: 200px; border: 2px solid #dee2e6; border-radius: 10px;">
        </div>
        <table class="table table-bordered">
            <tr>
                <th>Código:</th>
                <td><strong>${boleto.codigo_unico}</strong></td>
            </tr>
            <tr>
                <th>Evento:</th>
                <td>${boleto.evento_titulo}</td>
            </tr>
            <tr>
                <th>Función:</th>
                <td>${boleto.fecha_hora ? formatearFecha(boleto.fecha_hora) : 'No especificada'}</td>
            </tr>
            <tr>
                <th>Asiento:</th>
                <td><strong>${boleto.codigo_asiento}</strong></td>
            </tr>
            <tr>
                <th>Categoría:</th>
                <td>${boleto.nombre_categoria || 'General'}</td>
            </tr>
            <tr>
                <th>Precio:</th>
                <td>$${parseFloat(boleto.precio_final).toFixed(2)}</td>
            </tr>
            <tr>
                <th>Estado:</th>
                <td>
                    ${boleto.estatus == 1 
                        ? '<span class="badge bg-success">Activo</span>' 
                        : '<span class="badge bg-secondary">Usado</span>'}
                </td>
            </tr>
        </table>
    `;
    
    // Configurar footer
    if (boleto.estatus == 1) {
        footer.innerHTML = `
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="confirmarEntrada('${boleto.codigo_unico}')">
                <i class="bi bi-check-circle"></i> Confirmar Entrada
            </button>
        `;
    } else {
        footer.innerHTML = `
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        `;
    }
    
    // Mostrar modal
    modalBoletoInfo = new bootstrap.Modal(modal);
    modalBoletoInfo.show();
}

// Mostrar error
function mostrarError(mensaje) {
    const modal = document.getElementById('modalBoletoInfo');
    const header = document.getElementById('boletoInfoHeader');
    const title = document.getElementById('boletoInfoTitle');
    const body = document.getElementById('boletoInfoBody');
    const footer = document.getElementById('boletoInfoFooter');
    
    header.className = 'modal-header bg-danger text-white';
    title.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error';
    
    body.innerHTML = `
        <div class="alert alert-danger text-center">
            <i class="bi bi-x-circle fs-1"></i>
            <p class="mt-3 mb-0">${mensaje}</p>
        </div>
    `;
    
    footer.innerHTML = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    `;
    
    modalBoletoInfo = new bootstrap.Modal(modal);
    modalBoletoInfo.show();
}

// Confirmar entrada
async function confirmarEntrada(codigoUnico) {
    if (!confirm('¿Confirmar entrada? Esta acción marcará el boleto como usado y no se puede deshacer.')) {
        return;
    }
    
    try {
        const response = await fetch('confirmar_entrada.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                codigo_unico: codigoUnico
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Cerrar modal actual
            modalBoletoInfo.hide();
            
            // Mostrar mensaje de éxito
            alert('✓ Entrada confirmada exitosamente');
        } else {
            alert('Error: ' + (data.message || 'No se pudo confirmar la entrada'));
        }
    } catch (error) {
        console.error('Error al confirmar entrada:', error);
        alert('Error al confirmar la entrada');
    }
}

// Formatear fecha
function formatearFecha(fechaStr) {
    const fecha = new Date(fechaStr);
    const opciones = { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    };
    return fecha.toLocaleString('es-MX', opciones);
}
