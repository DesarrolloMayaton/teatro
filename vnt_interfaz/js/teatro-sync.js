/**
 * TeatroSync v3.0 - Sistema de Auto-Actualización en Tiempo Real
 * ===============================================================
 * Sistema centralizado para sincronizar todas las interfaces del teatro.
 * Usa Server-Sent Events (SSE) como método principal con fallback a polling.
 * 
 * USO: Incluir este script y llamar TeatroSync.init()
 * <script src="/teatro/vnt_interfaz/js/teatro-sync.js"></script>
 * 
 * @author Sistema Teatro
 * @version 3.0
 */

(function () {
    'use strict';

    // ============================================
    // CONFIGURACIÓN
    // ============================================
    const CONFIG = {
        // URL del endpoint SSE
        SSE_URL: '/teatro-main/api/cambios_api.php',

        // URL del endpoint de polling (fallback)
        POLL_URL: '/teatro-main/api/cambios_poll.php',

        // Intervalo de polling si SSE no está disponible (ms)
        POLL_INTERVAL: 3000,

        // Intervalo de verificación de asientos vendidos (ms)
        SEATS_POLL_INTERVAL: 5000,

        // Delay antes de recargar (para mostrar notificación)
        RELOAD_DELAY: 1500,

        // Tiempo máximo de reconexión SSE (ms)
        SSE_RECONNECT_DELAY: 3000,

        // Máximo de reintentos de reconexión
        MAX_RECONNECT_ATTEMPTS: 5,

        // Tipos de cambios que se monitorean
        CHANGE_TYPES: {
            VENTA: 'venta',
            CANCELACION: 'cancelacion',
            EVENTO: 'evento',
            CATEGORIA: 'categoria',
            MAPA: 'mapa',
            DESCUENTO: 'descuento',
            FUNCION: 'funcion',
            PRECIO: 'precio'
        }
    };

    // ============================================
    // ESTADO GLOBAL
    // ============================================
    let state = {
        initialized: false,
        sseSupported: typeof EventSource !== 'undefined',
        sseConnection: null,
        polling: false,
        pollTimer: null,
        seatsPollTimer: null,
        lastChangeId: parseInt(sessionStorage.getItem('teatro_sync_lastId') || '0'),
        currentEventId: null,
        currentFuncionId: null,
        reconnectAttempts: 0,
        listeners: {},
        soldSeatsCache: new Set(),
        autoReload: true, // Si true, recarga automáticamente en cambios
        reloadCooldown: false, // Evitar recargas en cascada
        lastNotification: 0, // Timestamp de la última notificación (para debounce)
        pendingReload: false, // Si ya estamos esperando para recargar
        saleInProgress: false, // Si acabamos de hacer una venta propia (no recargar)
        ventaModalAbierto: false // Si el modal de venta exitosa está abierto (BLOQUEO TOTAL)
    };

    // Variable global accesible desde fuera para bloquear actualizaciones
    window.TEATRO_VENTA_MODAL_ABIERTO = false;

    // ============================================
    // BROADCAST CHANNEL (comunicación entre pestañas)
    // ============================================
    let broadcastChannel = null;
    try {
        broadcastChannel = new BroadcastChannel('teatro_sync_v3');
        broadcastChannel.onmessage = handleBroadcastMessage;
    } catch (e) {
        console.warn('[TeatroSync] BroadcastChannel no disponible');
    }

    // ============================================
    // FUNCIONES DE NOTIFICACIÓN
    // ============================================
    function showNotification(message, type = 'info') {
        // Intentar usar el sistema de notificaciones existente
        if (typeof notify !== 'undefined') {
            notify[type] ? notify[type](message) : notify.info(message);
            return;
        }

        // Fallback: crear notificación toast
        const toast = document.createElement('div');
        toast.className = 'teatro-sync-toast';
        toast.setAttribute('role', 'alert');

        const colors = {
            info: '#3b82f6',
            success: '#10b981',
            warning: '#f59e0b',
            error: '#ef4444'
        };

        const icons = {
            info: '🔄',
            success: '✅',
            warning: '⚠️',
            error: '❌'
        };

        toast.innerHTML = `
            <span style="font-size: 18px; margin-right: 8px;">${icons[type] || icons.info}</span>
            <span>${message}</span>
        `;

        Object.assign(toast.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: colors[type] || colors.info,
            color: 'white',
            padding: '14px 24px',
            borderRadius: '12px',
            boxShadow: '0 8px 32px rgba(0,0,0,0.2)',
            display: 'flex',
            alignItems: 'center',
            gap: '10px',
            zIndex: '10000',
            fontFamily: 'system-ui, -apple-system, sans-serif',
            fontWeight: '500',
            fontSize: '14px',
            animation: 'teatroSyncSlideIn 0.3s ease',
            backdropFilter: 'blur(10px)'
        });

        // Agregar estilos de animación si no existen
        if (!document.getElementById('teatro-sync-styles')) {
            const style = document.createElement('style');
            style.id = 'teatro-sync-styles';
            style.textContent = `
                @keyframes teatroSyncSlideIn {
                    from { transform: translateY(-100%); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                @keyframes teatroSyncSlideOut {
                    from { transform: translateY(0); opacity: 1; }
                    to { transform: translateY(-100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'teatroSyncSlideOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // ============================================
    // SERVER-SENT EVENTS (SSE)
    // ============================================

    /**
     * Iniciar conexión SSE
     */
    function startSSE() {
        if (!state.sseSupported) {
            console.log('[TeatroSync] SSE no soportado, usando polling');
            startPolling();
            return;
        }

        if (state.sseConnection) {
            state.sseConnection.close();
        }

        // Construir URL con parámetros
        let url = CONFIG.SSE_URL + `?last_id=${state.lastChangeId}`;
        if (state.currentEventId) {
            url += `&id_evento=${state.currentEventId}`;
        }
        if (state.currentFuncionId) {
            url += `&id_funcion=${state.currentFuncionId}`;
        }

        console.log('[TeatroSync] Conectando SSE:', url);

        try {
            state.sseConnection = new EventSource(url);

            state.sseConnection.onopen = () => {
                console.log('[TeatroSync] SSE conectado');
                state.reconnectAttempts = 0;
            };

            // Evento de conexión exitosa
            state.sseConnection.addEventListener('connected', (e) => {
                const data = JSON.parse(e.data);
                console.log('[TeatroSync] Conexión confirmada:', data);
            });

            // Evento de cambio
            state.sseConnection.addEventListener('cambio', (e) => {
                const cambio = JSON.parse(e.data);
                console.log('[TeatroSync] Cambio recibido:', cambio);

                // Actualizar último ID y persistir
                if (cambio.id > state.lastChangeId) {
                    state.lastChangeId = cambio.id;
                    sessionStorage.setItem('teatro_sync_lastId', cambio.id.toString());
                }

                // Procesar cambio
                handleChange(cambio.tipo, cambio);

                // Propagar a otras pestañas
                if (broadcastChannel) {
                    broadcastChannel.postMessage(cambio);
                }
            });

            // Evento de reconexión requerida
            state.sseConnection.addEventListener('reconnect', (e) => {
                const data = JSON.parse(e.data);
                state.lastChangeId = data.last_id;

                // Reconectar después de delay
                setTimeout(() => {
                    startSSE();
                }, CONFIG.SSE_RECONNECT_DELAY);
            });

            state.sseConnection.onerror = (e) => {
                console.warn('[TeatroSync] Error SSE, reintentando...', e);
                state.sseConnection.close();

                state.reconnectAttempts++;

                if (state.reconnectAttempts >= CONFIG.MAX_RECONNECT_ATTEMPTS) {
                    console.log('[TeatroSync] Máximo de reintentos SSE, cambiando a polling');
                    startPolling();
                } else {
                    setTimeout(() => {
                        startSSE();
                    }, CONFIG.SSE_RECONNECT_DELAY * state.reconnectAttempts);
                }
            };

        } catch (e) {
            console.error('[TeatroSync] Error creando SSE:', e);
            startPolling();
        }
    }

    /**
     * Detener conexión SSE
     */
    function stopSSE() {
        if (state.sseConnection) {
            state.sseConnection.close();
            state.sseConnection = null;
        }
    }

    // ============================================
    // POLLING (FALLBACK)
    // ============================================

    /**
     * Iniciar polling como fallback
     */
    function startPolling() {
        if (state.polling) return;

        state.polling = true;
        console.log('[TeatroSync] Iniciando polling cada', CONFIG.POLL_INTERVAL, 'ms');

        // Ejecutar inmediatamente
        pollChanges();

        // Configurar intervalo
        state.pollTimer = setInterval(pollChanges, CONFIG.POLL_INTERVAL);
    }

    /**
     * Detener polling
     */
    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
        state.polling = false;
    }

    /**
     * Consultar cambios via polling
     */
    async function pollChanges() {
        try {
            let url = CONFIG.POLL_URL + `?last_id=${state.lastChangeId}`;
            if (state.currentEventId) {
                url += `&id_evento=${state.currentEventId}`;
            }

            const response = await fetch(url);
            const data = await response.json();

            if (data.success && data.cambios && data.cambios.length > 0) {
                data.cambios.forEach(cambio => {
                    handleChange(cambio.tipo, cambio);
                });
                state.lastChangeId = data.last_id;
                sessionStorage.setItem('teatro_sync_lastId', data.last_id.toString());
            }
        } catch (e) {
            console.error('[TeatroSync] Error en polling:', e);
        }
    }

    // ============================================
    // HANDLERS DE CAMBIOS
    // ============================================

    /**
     * Manejar mensajes del BroadcastChannel
     */
    function handleBroadcastMessage(event) {
        const cambio = event.data;
        console.log('[TeatroSync] Mensaje de otra pestaña:', cambio);
        handleChange(cambio.tipo, cambio, true); // true = fromBroadcast
    }

    /**
     * Manejar un cambio detectado
     */
    function handleChange(tipo, data, fromBroadcast = false) {
        const eventId = data?.id_evento;
        const funcionId = data?.id_funcion;

        console.log('[TeatroSync] Procesando cambio:', tipo, data);

        // Verificar si afecta al evento actual
        if (eventId && state.currentEventId && eventId != state.currentEventId) {
            console.log('[TeatroSync] Cambio en otro evento, ignorando');
            return;
        }

        // Ejecutar listeners registrados
        if (state.listeners[tipo]) {
            state.listeners[tipo].forEach(callback => {
                try {
                    callback(data);
                } catch (e) {
                    console.error('[TeatroSync] Error en listener:', e);
                }
            });
        }

        // Si autoReload está activo, manejar recarga automática
        if (state.autoReload) {
            handleAutoReload(tipo, data);
        }
    }

    /**
     * Manejar recarga automática según tipo de cambio
     */
    function handleAutoReload(tipo, data) {
        // BLOQUEO TOTAL: Si el modal de venta exitosa está abierto, NO hacer nada
        if (window.TEATRO_VENTA_MODAL_ABIERTO === true || state.ventaModalAbierto === true) {
            console.log('[TeatroSync] Modal de venta exitosa abierto - BLOQUEANDO recarga');
            return;
        }

        // Evitar recargas en cascada
        if (state.reloadCooldown) {
            console.log('[TeatroSync] En cooldown, ignorando recarga');
            return;
        }

        // NO recargar si acabamos de hacer una venta propia (para poder imprimir)
        if (state.saleInProgress) {
            console.log('[TeatroSync] Venta propia en progreso, ignorando recarga');
            return;
        }

        // NO recargar si hay un modal de boletos abierto (verificación adicional por DOM)
        const modalBoletos = document.getElementById('modalBoletosNuevo');
        const modalTipoBoleto = document.getElementById('modalTipoBoleto');
        if (modalBoletos || modalTipoBoleto) {
            console.log('[TeatroSync] Modal de venta detectado en DOM, ignorando recarga');
            return;
        }

        // Evitar múltiples notificaciones/recargas simultáneas
        if (state.pendingReload) {
            console.log('[TeatroSync] Ya hay recarga pendiente, ignorando');
            return;
        }

        // Debounce: ignorar si ya mostramos notificación hace menos de 3 segundos
        const now = Date.now();
        if (now - state.lastNotification < 3000) {
            console.log('[TeatroSync] Debounce activo, ignorando notificación');
            return;
        }
        state.lastNotification = now;
        state.pendingReload = true;

        const messages = {
            [CONFIG.CHANGE_TYPES.VENTA]: 'Nueva venta registrada',
            [CONFIG.CHANGE_TYPES.CANCELACION]: 'Boleto cancelado',
            [CONFIG.CHANGE_TYPES.EVENTO]: 'Evento actualizado',
            [CONFIG.CHANGE_TYPES.CATEGORIA]: 'Categorías actualizadas',
            [CONFIG.CHANGE_TYPES.MAPA]: 'Mapa de asientos actualizado',
            [CONFIG.CHANGE_TYPES.DESCUENTO]: 'Descuentos actualizados',
            [CONFIG.CHANGE_TYPES.FUNCION]: 'Función actualizada',
            [CONFIG.CHANGE_TYPES.PRECIO]: 'Precios actualizados'
        };

        const message = messages[tipo] || 'Cambios detectados';

        // Marcar que estamos por recargar
        sessionStorage.setItem('teatro_sync_reloading', Date.now().toString());

        showNotification(`${message}. Actualizando...`, 'warning');

        setTimeout(() => {
            window.location.reload();
        }, CONFIG.RELOAD_DELAY);
    }

    /**
     * Marcar asientos como vendidos en la UI sin recargar
     */
    function markSeatsAsSold(seatIds) {
        if (!Array.isArray(seatIds)) return;

        seatIds.forEach(id => {
            const seatEl = document.querySelector(`.seat[data-id="${id}"]`);
            if (seatEl && !seatEl.classList.contains('vendido')) {
                seatEl.classList.add('vendido');
                seatEl.style.pointerEvents = 'none';
                state.soldSeatsCache.add(id);
            }
        });
    }

    // ============================================
    // COMPATIBILIDAD CON SISTEMA ANTERIOR
    // ============================================

    // Escuchar las keys antiguas de localStorage
    window.addEventListener('storage', (e) => {
        const keyMappings = {
            'evt_upd': CONFIG.CHANGE_TYPES.EVENTO,
            'evento_actualizado': CONFIG.CHANGE_TYPES.EVENTO,
            'categorias_actualizadas': CONFIG.CHANGE_TYPES.CATEGORIA,
            'descuentos_actualizados': CONFIG.CHANGE_TYPES.DESCUENTO,
            'mapa_actualizado': CONFIG.CHANGE_TYPES.MAPA,
            'precios_actualizados': CONFIG.CHANGE_TYPES.PRECIO
        };

        // Compatibilidad con keys antiguas
        if (keyMappings[e.key] && e.newValue) {
            try {
                const data = JSON.parse(e.newValue);
                handleChange(keyMappings[e.key], data);
            } catch (err) {
                // Ignorar errores de parsing
            }
        }

        // Nuevas keys con prefijo teatro_sync_
        if (e.key?.startsWith('teatro_sync_') && e.newValue) {
            try {
                const payload = JSON.parse(e.newValue);
                handleChange(payload.type, payload);
            } catch (e) {
                // Ignorar
            }
        }
    });

    // ============================================
    // API PÚBLICA
    // ============================================

    const TeatroSync = {
        /**
         * Inicializar el sistema de sincronización
         * @param {Object} options - Opciones de configuración
         * @param {number} options.eventoId - ID del evento actual
         * @param {number} options.funcionId - ID de la función actual
         * @param {boolean} options.autoReload - Si recargar automáticamente (default: true)
         */
        init(options = {}) {
            if (state.initialized) {
                console.log('[TeatroSync] Ya inicializado');
                return this;
            }

            // Detectar evento/función actual
            state.currentEventId = options.eventoId ||
                document.querySelector('select[name="id_evento"]')?.value ||
                document.getElementById('current_event_id')?.value ||
                null;

            state.currentFuncionId = options.funcionId ||
                document.querySelector('[data-funcion-id]')?.dataset.funcionId ||
                null;

            state.autoReload = options.autoReload !== false;

            // Verificar si acabamos de recargar (cooldown de 5 segundos)
            const lastReloadTime = parseInt(sessionStorage.getItem('teatro_sync_reloading') || '0');
            if (Date.now() - lastReloadTime < 5000) {
                state.reloadCooldown = true;
                console.log('[TeatroSync] Cooldown activo (recarga reciente)');
                setTimeout(() => {
                    state.reloadCooldown = false;
                    sessionStorage.removeItem('teatro_sync_reloading');
                    console.log('[TeatroSync] Cooldown terminado');
                }, 5000 - (Date.now() - lastReloadTime));
            }

            // Iniciar SSE (con fallback a polling)
            startSSE();

            state.initialized = true;
            console.log('[TeatroSync] Sistema inicializado v3.0', {
                eventoId: state.currentEventId,
                funcionId: state.currentFuncionId,
                sseSupported: state.sseSupported,
                autoReload: state.autoReload
            });

            return this;
        },

        /**
         * Emitir un cambio manualmente (para notificar otras pestañas)
         */
        emit(type, data) {
            const payload = {
                tipo: type,
                id_evento: data?.id_evento || state.currentEventId,
                id_funcion: data?.id_funcion || state.currentFuncionId,
                datos: data,
                timestamp: Date.now()
            };

            // Emitir via BroadcastChannel
            if (broadcastChannel) {
                broadcastChannel.postMessage(payload);
            }

            // También via localStorage para compatibilidad
            localStorage.setItem(`teatro_sync_${type}`, JSON.stringify(payload));
            setTimeout(() => {
                localStorage.removeItem(`teatro_sync_${type}`);
            }, 1000);

            console.log('[TeatroSync] Cambio emitido:', payload);
            return this;
        },

        /**
         * Registrar listener para un tipo de cambio
         */
        on(type, callback) {
            if (!state.listeners[type]) {
                state.listeners[type] = [];
            }
            state.listeners[type].push(callback);
            return this;
        },

        /**
         * Remover listener
         */
        off(type, callback) {
            if (state.listeners[type]) {
                state.listeners[type] = state.listeners[type].filter(cb => cb !== callback);
            }
            return this;
        },

        /**
         * Establecer evento actual
         */
        setEvento(eventoId) {
            state.currentEventId = eventoId;
            // Reconectar SSE con nuevo evento
            if (state.sseConnection) {
                stopSSE();
                startSSE();
            }
            return this;
        },

        /**
         * Establecer función actual
         */
        setFuncion(funcionId) {
            state.currentFuncionId = funcionId;
            return this;
        },

        /**
         * Habilitar/deshabilitar auto-recarga
         */
        setAutoReload(enabled) {
            state.autoReload = enabled;
            return this;
        },

        /**
         * Forzar recarga inmediata
         */
        forceReload() {
            showNotification('Recargando página...', 'info');
            setTimeout(() => window.location.reload(), 500);
        },

        /**
         * Pausar recargas automáticas (para después de ventas propias)
         * @param {number} duration - Duración en ms (default: 2 minutos)
         */
        pauseReloads(duration = 120000) {
            state.saleInProgress = true;
            console.log('[TeatroSync] Recargas pausadas por', duration / 1000, 'segundos');
            setTimeout(() => {
                state.saleInProgress = false;
                console.log('[TeatroSync] Recargas reactivadas');
            }, duration);
            return this;
        },

        /**
         * Reanudar recargas automáticas
         */
        resumeReloads() {
            state.saleInProgress = false;
            return this;
        },

        /**
         * Mostrar notificación
         */
        notify: showNotification,

        /**
         * Detener todo el sistema
         */
        destroy() {
            stopSSE();
            stopPolling();
            if (broadcastChannel) {
                broadcastChannel.close();
            }
            state.initialized = false;
            console.log('[TeatroSync] Sistema detenido');
        },

        // Constantes de tipos de cambio
        TYPES: CONFIG.CHANGE_TYPES,

        // Estado actual (solo lectura)
        get state() {
            return {
                initialized: state.initialized,
                sseSupported: state.sseSupported,
                sseConnected: state.sseConnection !== null,
                polling: state.polling,
                currentEventId: state.currentEventId,
                currentFuncionId: state.currentFuncionId,
                autoReload: state.autoReload
            };
        }
    };

    // ============================================
    // INICIALIZACIÓN AUTOMÁTICA
    // ============================================

    // Auto-inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => TeatroSync.init());
    } else {
        // DOM ya cargado, inicializar en siguiente tick
        setTimeout(() => TeatroSync.init(), 100);
    }

    // Limpiar al cerrar la página
    window.addEventListener('beforeunload', () => TeatroSync.destroy());

    // Exponer globalmente
    window.TeatroSync = TeatroSync;

})();
