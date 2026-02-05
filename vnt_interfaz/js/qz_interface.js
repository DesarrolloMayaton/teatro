/// <reference path="qz-tray.js" />
// qz_interface.js
// Manejo de impresión cliente con QZ Tray
// Versión 2.0 - Con reconexión automática y verificación de estado real

const QZ_CONFIG = {
    host: 'localhost',
    connected: false,
    reconnectAttempts: 0,
    maxReconnectAttempts: 5,
    reconnectDelay: 1000, // ms inicial
    isReconnecting: false,
    lastError: null
};

// Configuración de Seguridad (Firma de mensajes) global
qz.security.setCertificatePromise(function (resolve, reject) {
    console.log("QZ: Solicitando certificado...");
    // Intentar múltiples rutas para el certificado
    const paths = ['utils/qz_cert.pem', '../vnt_interfaz/utils/qz_cert.pem', '../../vnt_interfaz/utils/qz_cert.pem'];

    const tryPath = (index) => {
        if (index >= paths.length) {
            reject(new Error('No se pudo cargar el certificado QZ'));
            return;
        }

        fetch(paths[index])
            .then(response => {
                if (!response.ok) throw new Error('Not found');
                return response.text();
            })
            .then(text => {
                console.log("QZ: Certificado encontrado en", paths[index]);
                resolve(text);
            })
            .catch(() => tryPath(index + 1));
    };

    tryPath(0);
});

qz.security.setSignaturePromise(function (toSign) {
    return function (resolve, reject) {
        console.log("QZ: Solicitando firma...");
        // Intentar múltiples rutas para el endpoint de firma
        const paths = ['sign_message.php', '../vnt_interfaz/sign_message.php', '../../vnt_interfaz/sign_message.php'];

        const tryPath = (index) => {
            if (index >= paths.length) {
                reject(new Error('No se pudo obtener la firma QZ'));
                return;
            }

            fetch(paths[index] + '?request=' + encodeURIComponent(toSign))
                .then(response => {
                    if (!response.ok) throw new Error('Not found');
                    return response.text();
                })
                .then(signed => {
                    console.log("QZ: Firma obtenida correctamente");
                    resolve(signed);
                })
                .catch(() => tryPath(index + 1));
        };

        tryPath(0);
    };
});

// Verificar si la conexión WebSocket está REALMENTE activa
function isQZReallyConnected() {
    try {
        return qz.websocket.isActive();
    } catch (e) {
        return false;
    }
}

// Configurar listener de desconexión
function setupDisconnectListener() {
    try {
        qz.websocket.setClosedCallbacks(function (event) {
            console.warn("QZ Tray: Conexión cerrada", event);
            QZ_CONFIG.connected = false;
            QZ_CONFIG.lastError = "Conexión cerrada";

            // Intentar reconectar automáticamente si no estamos ya reconectando
            if (!QZ_CONFIG.isReconnecting) {
                console.log("QZ Tray: Iniciando reconexión automática...");
                autoReconnect();
            }
        });

        qz.websocket.setErrorCallbacks(function (error) {
            console.error("QZ Tray: Error de WebSocket", error);
            QZ_CONFIG.lastError = error.message || "Error de conexión";
        });
    } catch (e) {
        console.warn("QZ: No se pudieron configurar callbacks de desconexión", e);
    }
}

// Reconexión automática con backoff exponencial
async function autoReconnect() {
    if (QZ_CONFIG.isReconnecting) return;
    if (QZ_CONFIG.reconnectAttempts >= QZ_CONFIG.maxReconnectAttempts) {
        console.error("QZ Tray: Máximo de intentos de reconexión alcanzado");
        QZ_CONFIG.isReconnecting = false;
        return;
    }

    QZ_CONFIG.isReconnecting = true;
    QZ_CONFIG.reconnectAttempts++;

    const delay = QZ_CONFIG.reconnectDelay * Math.pow(2, QZ_CONFIG.reconnectAttempts - 1);
    console.log(`QZ Tray: Reintentando conexión en ${delay}ms (intento ${QZ_CONFIG.reconnectAttempts}/${QZ_CONFIG.maxReconnectAttempts})`);

    await new Promise(resolve => setTimeout(resolve, delay));

    try {
        // Forzar desconexión primero si hay conexión zombie
        try {
            if (qz.websocket.isActive()) {
                await qz.websocket.disconnect();
            }
        } catch (e) { /* ignorar */ }

        await qz.websocket.connect();
        QZ_CONFIG.connected = true;
        QZ_CONFIG.reconnectAttempts = 0;
        QZ_CONFIG.isReconnecting = false;
        QZ_CONFIG.lastError = null;
        console.log("QZ Tray: Reconexión exitosa!");
        setupDisconnectListener();
    } catch (err) {
        console.error("QZ Tray: Fallo en reconexión", err);
        QZ_CONFIG.connected = false;
        QZ_CONFIG.isReconnecting = false;

        // Intentar de nuevo si aún hay intentos disponibles
        if (QZ_CONFIG.reconnectAttempts < QZ_CONFIG.maxReconnectAttempts) {
            autoReconnect();
        }
    }
}

// Inicializar QZ con verificación robusta
async function initQZ(forceReconnect = false) {
    // Verificar el estado REAL de la conexión, no solo la variable
    const reallyConnected = isQZReallyConnected();

    // Si la variable dice conectado pero realmente no lo está, corregir
    if (QZ_CONFIG.connected && !reallyConnected) {
        console.warn("QZ Tray: Estado inconsistente detectado, reconectando...");
        QZ_CONFIG.connected = false;
    }

    // Si ya está conectado y no forzamos reconexión, retornar éxito
    if (reallyConnected && !forceReconnect) {
        QZ_CONFIG.connected = true;
        return true;
    }

    // Reiniciar contador de intentos si es una nueva conexión iniciada por el usuario
    if (forceReconnect) {
        QZ_CONFIG.reconnectAttempts = 0;
    }

    try {
        // Si hay una conexión zombie, desconectar primero
        try {
            if (qz.websocket.isActive()) {
                await qz.websocket.disconnect();
            }
        } catch (e) {
            console.warn("QZ: Error al desconectar conexión previa", e);
        }

        await qz.websocket.connect();
        QZ_CONFIG.connected = true;
        QZ_CONFIG.reconnectAttempts = 0;
        QZ_CONFIG.lastError = null;
        console.log("QZ Tray: Conectado exitosamente");

        // Configurar listener para detectar desconexiones futuras
        setupDisconnectListener();

        return true;
    } catch (err) {
        console.error("QZ Tray: Error conectando:", err);
        QZ_CONFIG.connected = false;
        QZ_CONFIG.lastError = err.message || "Error de conexión";
        return false;
    }
}

// Obtener lista de impresoras con manejo robusto
async function getPrinters(retryOnFail = true) {
    // Intentar inicializar/reconectar
    if (!await initQZ()) {
        // Si falló la primera vez, intentar una reconexión forzada
        if (retryOnFail) {
            console.log("QZ Tray: Reintentando conexión para obtener impresoras...");
            await new Promise(resolve => setTimeout(resolve, 500));
            return getPrinters(false); // Sin retry para evitar loop infinito
        }
        return [];
    }

    try {
        const printers = await qz.printers.find();
        console.log("QZ Tray: Impresoras encontradas:", printers);
        return printers;
    } catch (err) {
        console.error("QZ Tray: Error obteniendo impresoras:", err);

        // Si el error parece ser de conexión, intentar reconectar
        if (retryOnFail && (err.message?.includes('connection') || err.message?.includes('socket'))) {
            console.log("QZ Tray: Error de conexión detectado, reconectando...");
            QZ_CONFIG.connected = false;
            await new Promise(resolve => setTimeout(resolve, 500));
            return getPrinters(false);
        }

        return [];
    }
}

// Forzar reconexión manual (útil para botón de "Reconectar")
async function forceQZReconnect() {
    console.log("QZ Tray: Forzando reconexión...");
    QZ_CONFIG.reconnectAttempts = 0;
    QZ_CONFIG.isReconnecting = false;
    QZ_CONFIG.connected = false;

    return await initQZ(true);
}

// Obtener estado de conexión
function getQZStatus() {
    return {
        connected: QZ_CONFIG.connected,
        reallyConnected: isQZReallyConnected(),
        reconnectAttempts: QZ_CONFIG.reconnectAttempts,
        isReconnecting: QZ_CONFIG.isReconnecting,
        lastError: QZ_CONFIG.lastError
    };
}

// Generar HTML del boleto
function formatTicketHTML(boleto, cliente) {
    // Estilos para impresora térmica (80mm)
    // Usamos medidas en px o % para ancho. 80mm es aprox 300-500px dependiendo dpi
    // QZ rasteriza, así que HTML simple es suficiente

    // Logo y QR vienen ya en boleto.logo_image y boleto.qr_image como Base64

    // Fallback si no hay imágenes
    const logoImg = boleto.logo_image ? `<img src="${boleto.logo_image}" style="width: 150px; margin-bottom: 5px;">` : '<h3>TEATRO</h3>';
    const qrImg = boleto.qr_image ? `<img src="${boleto.qr_image}" style="width: 130px; margin-top: 10px;">` : `<p>${boleto.codigo_unico}</p>`;

    // Determinar nombre a mostrar: Específico del boleto > Global > Vacío
    const nombreMostrar = boleto.nombre_cliente || cliente;

    // Formatear Fecha y Hora (Natural)
    // boleto.fecha viene dd/mm/yyyy. boleto.hora viene HH:mm hrs
    let fechaTexto = boleto.fecha;
    let horaTexto = boleto.hora;

    try {
        if (boleto.fecha && boleto.fecha.includes('/')) {
            const [d, m, y] = boleto.fecha.split('/');
            const dateObj = new Date(y, m - 1, d);
            // Opción: jueves 27 de agosto de 2026
            const opcionesFecha = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            fechaTexto = dateObj.toLocaleDateString('es-ES', opcionesFecha);
        }

        if (boleto.hora) {
            // Extraer HH:mm
            const match = boleto.hora.match(/(\d+):(\d+)/);
            if (match) {
                let h = parseInt(match[1]);
                const min = match[2];
                const ampm = h >= 12 ? 'pm' : 'am';
                h = h % 12;
                h = h ? h : 12; // el 0 es 12
                horaTexto = `${h}:${min} ${ampm}`;
            }
        }
    } catch (e) {
        console.error("Error formateando fecha", e);
    }

    return `
        <div style="font-family: Arial, Helvetica, sans-serif; text-align: center; width: 100%; max-width: 332px; margin: 0 auto; color: #000; font-weight: bold; transform: scale(0.95); transform-origin: top center;">
            <!-- Cabecera -->
            <div style="margin-bottom: 5px;">
                ${logoImg}
                <div style="font-size: 14px; font-weight: 900; text-transform: uppercase;">TEATRO CONSTITUCION</div>
            </div>
            
            <div style="border-bottom: 3px solid #000; margin: 5px 0;"></div>
            
            <!-- Evento -->
            <div style="text-align: center; margin: 5px 0;">
                <div style="font-size: 12px; text-transform: uppercase; margin-bottom: 2px;">Evento:</div>
                <div style="font-size: 18px; font-weight: 900; line-height: 1.1; margin-bottom: 5px;">${boleto.evento}</div>
                
                <div style="font-size: 14px; margin-top: 2px; font-weight: 900; text-transform: capitalize;">
                    ${fechaTexto}
                </div>
                <div style="font-size: 14px; font-weight: 900;">
                    a las ${horaTexto}
                </div>
            </div>
            
            <div style="border-bottom: 2px dashed #000; margin: 5px 0;"></div>
            
            <!-- Detalles -->
            <div style="margin: 5px 0;">
                <div style="font-size: 18px; font-weight: 900; border: 2px solid #000; padding: 5px 8px; display: inline-block; border-radius: 5px;">ASIENTO: ${boleto.asiento}</div>
                <div style="font-size: 14px; margin-top: 5px;">${boleto.categoria}</div>
                <div style="font-size: 18px; font-weight: 900; margin-top: 5px;">$${Number(boleto.precio).toFixed(2)}</div>
            </div>

            <!-- Cliente -->
            ${nombreMostrar ? `
            <div style="border-bottom: 2px dashed #000; margin: 5px 0;"></div>
            <div style="margin: 5px 0; text-align: left;">
                <div style="font-size: 12px;">Cliente:</div>
                <div style="font-size: 14px; font-weight: 900;">${nombreMostrar}</div>
            </div>
            ` : ''}
            
            <div style="border-bottom: 3px solid #000; margin: 5px 0;"></div>
            
            <!-- QR -->
            <div style="margin: 5px 0; text-align: center;">
                <img src="${boleto.qr_image}" style="width: 160px; margin-top: 5px;">
                <div style="font-size: 12px; margin-top: 2px; font-weight: 900;">${boleto.codigo_unico}</div>
            </div>
            
            <!-- Footer -->
            <div style="margin-top: 10px; font-size: 11px; font-weight: bold; padding: 0 5px;">
                ${boleto.vendedor ? `<div style="font-size: 10px; margin-bottom: 5px;">Le atendio: ${boleto.vendedor}</div>` : ''}
                NOTA: Solo se harán válidos cambios o aclaraciones si se conserva este boleto.
                <br><br>
                <span style="font-size: 13px;">¡Gracias por su compra!</span>
            </div>
            
            <!-- Espacio final para corte -->
            <div style="height: 30px;"></div>
        </div>
    `;
}

// Imprimir lista de boletos con manejo robusto de errores
async function printTicketsQZ(boletos, cliente, impresoraName, isRetry = false) {
    // Verificar conexión real, no solo la variable
    if (!isQZReallyConnected()) {
        console.log("QZ Tray: Conexión no activa, intentando conectar...");
        const connected = await initQZ(true);
        if (!connected) {
            return { success: false, message: "No se pudo conectar con QZ Tray. Asegúrese de que QZ Tray esté ejecutándose." };
        }
    }

    try {
        // Configuración de impresora
        let config;

        // Determinar impresora
        if (!impresoraName || impresoraName === 'default') {
            // Fallback
            const printers = await qz.printers.find();
            // Preferir POS
            const posPrinter = printers.find(p => p.includes('POS') || p.includes('Epson') || p.includes('Thermal'));
            impresoraName = posPrinter || printers[0];

            if (!impresoraName) {
                return { success: false, message: "No se encontraron impresoras disponibles" };
            }
        }

        // Crear configuración para HTML (rasterización)
        config = qz.configs.create(impresoraName, {
            rasterize: true, // Importante para convertir HTML/Img a píxeles de impresora térmica
            altPrinting: true // A veces ayuda en Windows
            // encoding: 'ISO-8859-1' // Si fuera RAW, pero es Raster
        });

        const printData = [];

        // Generar una página HTML por boleto
        for (let boleto of boletos) {
            const htmlContent = formatTicketHTML(boleto, cliente);
            printData.push({
                type: 'pixel',
                format: 'html',
                flavor: 'plain', // o 'file' si fuera url
                data: htmlContent
            });
        }

        await qz.print(config, printData);
        return { success: true, message: `Enviado a ${impresoraName}` };
    } catch (err) {
        console.error("QZ Tray: Error al imprimir:", err);

        // Si el error parece ser de conexión y no es un reintento, intentar reconectar e imprimir de nuevo
        const isConnectionError = err.message?.includes('connection') ||
            err.message?.includes('socket') ||
            err.message?.includes('closed') ||
            err.message?.includes('WebSocket');

        if (isConnectionError && !isRetry) {
            console.log("QZ Tray: Error de conexión detectado, reintentando...");
            QZ_CONFIG.connected = false;
            await new Promise(resolve => setTimeout(resolve, 500));
            return printTicketsQZ(boletos, cliente, impresoraName, true);
        }

        return { success: false, message: err.message || err };
    }
}

// Exponer globalmente
window.initQZ = initQZ;
window.getPrinters = getPrinters;
window.printTicketsQZ = printTicketsQZ;
window.forceQZReconnect = forceQZReconnect;
window.getQZStatus = getQZStatus;
window.isQZReallyConnected = isQZReallyConnected;

// NOTA: Se eliminaron las conexiones automáticas para evitar que aparezca
// la ventana de permisos de QZ Tray repetidamente.
// La conexión ahora se realiza SOLO cuando el usuario solicita imprimir.

// Función para conectar manualmente (usar desde un botón si se desea)
async function connectQZManually() {
    console.log("QZ Tray: Conexión manual solicitada...");
    try {
        const connected = await initQZ();
        if (connected) {
            console.log("QZ Tray: Conexión manual exitosa");
            return true;
        } else {
            console.warn("QZ Tray: No se pudo conectar manualmente");
            return false;
        }
    } catch (e) {
        console.warn("QZ Tray: Error en conexión manual:", e);
        return false;
    }
}

// Exponer función de conexión manual
window.connectQZManually = connectQZManually;
