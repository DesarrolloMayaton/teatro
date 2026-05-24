<?php
/**
 * Panel de Control de Sincronización
 * ===================================
 * Interfaz visual para gestionar la sincronización entre servidores.
 */

session_start();
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronización de Bases de Datos - Teatro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-card: #1a1a24;
            --accent-primary: #6366f1;
            --accent-secondary: #818cf8;
            --accent-success: #22c55e;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: rgba(99, 102, 241, 0.2);
            --gradient-primary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
            --shadow-glow: 0 0 40px rgba(99, 102, 241, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        /* Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .card:hover::before {
            opacity: 1;
        }

        .card:hover {
            box-shadow: var(--shadow-glow);
            transform: translateY(-2px);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title .icon {
            width: 24px;
            height: 24px;
        }

        /* Status Indicators */
        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .status-row:last-child {
            border-bottom: none;
        }

        .status-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .status-value {
            font-weight: 500;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-badge.connected {
            background: rgba(34, 197, 94, 0.15);
            color: var(--accent-success);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .status-badge.disconnected {
            background: rgba(239, 68, 68, 0.15);
            color: var(--accent-danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-badge.syncing {
            background: rgba(245, 158, 11, 0.15);
            color: var(--accent-warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        /* Server Cards */
        .server-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .server-info {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 12px;
        }

        .server-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .server-host {
            color: var(--text-muted);
            font-family: monospace;
            font-size: 0.85rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-card);
            border-color: var(--accent-primary);
        }

        .btn-success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Table Sync Status */
        .table-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .table-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: var(--bg-secondary);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }

        .table-item:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        .table-name {
            font-family: monospace;
            font-weight: 500;
        }

        .table-counts {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .count-badge {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .count-badge.local {
            color: var(--accent-primary);
        }

        .count-badge.remote {
            color: var(--accent-secondary);
        }

        .sync-indicator {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sync-indicator.synced {
            color: var(--accent-success);
        }

        .sync-indicator.not-synced {
            color: var(--accent-warning);
        }

        /* Log Console */
        .log-console {
            background: #0d0d12;
            border-radius: 12px;
            padding: 1rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
        }

        .log-entry {
            padding: 0.35rem 0;
            display: flex;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }

        .log-time {
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .log-message {
            color: var(--text-secondary);
        }

        .log-entry.error .log-message {
            color: var(--accent-danger);
        }

        .log-entry.success .log-message {
            color: var(--accent-success);
        }

        .log-entry.warning .log-message {
            color: var(--accent-warning);
        }

        /* Sync Actions */
        .sync-actions {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .sync-actions h2 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-card {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .action-card:hover {
            border-color: var(--accent-primary);
            background: rgba(99, 102, 241, 0.1);
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .action-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Loading Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: var(--accent-primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header h1 {
                font-size: 1.75rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../admin_interfaz/" class="back-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Volver al Panel Admin
        </a>

        <header class="header">
            <h1>🔄 Sincronización de Bases de Datos</h1>
            <p>Gestiona la sincronización entre servidor local y remoto</p>
        </header>

        <!-- Server Status -->
        <div class="dashboard-grid">
            <div class="card server-card">
                <div class="card-title">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2"/>
                        <path d="M8 21h8M12 17v4"/>
                    </svg>
                    Servidor Local (XAMPP)
                </div>
                <div class="server-info">
                    <div class="server-name">Base de Datos Local</div>
                    <div class="server-host"><?= DB_LOCAL_HOST ?> / <?= DB_LOCAL_NAME ?></div>
                </div>
                <div class="status-row">
                    <span class="status-label">Estado</span>
                    <span id="local-status" class="status-badge disconnected">
                        <span class="status-dot"></span>
                        Verificando...
                    </span>
                </div>
                <div class="status-row">
                    <span class="status-label">Latencia</span>
                    <span id="local-latency" class="status-value">--</span>
                </div>
                <button class="btn btn-secondary" onclick="testConnection('local')">
                    Probar Conexión
                </button>
            </div>

            <div class="card server-card">
                <div class="card-title">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    Servidor Remoto
                </div>
                <div class="server-info">
                    <div class="server-name">Base de Datos Remota</div>
                    <div class="server-host"><?= DB_REMOTE_HOST ?> / <?= DB_REMOTE_NAME ?></div>
                </div>
                <div class="status-row">
                    <span class="status-label">Estado</span>
                    <span id="remote-status" class="status-badge disconnected">
                        <span class="status-dot"></span>
                        Verificando...
                    </span>
                </div>
                <div class="status-row">
                    <span class="status-label">Latencia</span>
                    <span id="remote-latency" class="status-value">--</span>
                </div>
                <button class="btn btn-secondary" onclick="testConnection('remote')">
                    Probar Conexión
                </button>
            </div>
        </div>

        <!-- Sync Actions -->
        <div class="sync-actions">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 0 1-9 9m0 0a9 9 0 0 1-9-9m9 9V3m0 0L7 7m5-4 5 4"/>
                </svg>
                Acciones de Sincronización
            </h2>
            <div class="action-buttons">
                <div class="action-card" onclick="syncAll('both')">
                    <div class="action-icon">🔄</div>
                    <div class="action-title">Sincronización Completa</div>
                    <div class="action-desc">Sincronizar ambas direcciones</div>
                </div>
                <div class="action-card" onclick="syncAll('local_to_remote')">
                    <div class="action-icon">📤</div>
                    <div class="action-title">Local → Remoto</div>
                    <div class="action-desc">Enviar datos al servidor remoto</div>
                </div>
                <div class="action-card" onclick="syncAll('remote_to_local')">
                    <div class="action-icon">📥</div>
                    <div class="action-title">Remoto → Local</div>
                    <div class="action-desc">Descargar datos del servidor remoto</div>
                </div>
                <div class="action-card" onclick="prepareTables()">
                    <div class="action-icon">⚙️</div>
                    <div class="action-title">Preparar Tablas</div>
                    <div class="action-desc">Agregar campos de tracking</div>
                </div>
            </div>
        </div>

        <!-- Table Status -->
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-title">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 3h18v18H3zM3 9h18M9 21V9"/>
                    </svg>
                    Estado de Tablas
                </div>
                <div class="btn-group" style="margin-bottom: 1rem;">
                    <button class="btn btn-secondary" onclick="refreshDifferences()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12a9 9 0 1 1-9-9"/>
                            <path d="M21 3v6h-6"/>
                        </svg>
                        Actualizar
                    </button>
                </div>
                <div id="table-list" class="table-list">
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        Cargando tablas...
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                    </svg>
                    Log de Sincronización
                </div>
                <div id="log-console" class="log-console">
                    <div class="log-entry">
                        <span class="log-time"><?= date('H:i:s') ?></span>
                        <span class="log-message">Panel de sincronización iniciado</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_URL = 'sync_api.php';
        
        // Inicializar al cargar
        document.addEventListener('DOMContentLoaded', () => {
            checkStatus();
            refreshDifferences();
        });
        
        // Añadir log
        function addLog(message, type = 'info') {
            const console = document.getElementById('log-console');
            const time = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.innerHTML = `
                <span class="log-time">${time}</span>
                <span class="log-message">${message}</span>
            `;
            console.insertBefore(entry, console.firstChild);
        }
        
        // Verificar estado de conexiones
        async function checkStatus() {
            try {
                const response = await fetch(`${API_URL}?action=check`);
                const data = await response.json();
                
                if (data.success) {
                    updateServerStatus('local', data.data.local);
                    updateServerStatus('remote', data.data.remote);
                }
            } catch (error) {
                addLog('Error verificando conexiones: ' + error.message, 'error');
            }
        }
        
        // Actualizar estado visual de servidor
        function updateServerStatus(server, status) {
            const statusEl = document.getElementById(`${server}-status`);
            const latencyEl = document.getElementById(`${server}-latency`);
            
            if (status.connected) {
                statusEl.className = 'status-badge connected';
                statusEl.innerHTML = '<span class="status-dot"></span> Conectado';
                latencyEl.textContent = status.latency + ' ms';
                addLog(`Servidor ${server} conectado (${status.latency}ms)`, 'success');
            } else {
                statusEl.className = 'status-badge disconnected';
                statusEl.innerHTML = '<span class="status-dot"></span> Desconectado';
                latencyEl.textContent = '--';
                addLog(`Servidor ${server} desconectado: ${status.error}`, 'error');
            }
        }
        
        // Probar conexión individual
        async function testConnection(server) {
            addLog(`Probando conexión ${server}...`);
            
            try {
                const response = await fetch(`${API_URL}?action=test_${server}`);
                const data = await response.json();
                
                if (data.success) {
                    addLog(`Conexión ${server} exitosa: ${data.server_info}`, 'success');
                } else {
                    addLog(`Error ${server}: ${data.message}`, 'error');
                }
                
                checkStatus();
            } catch (error) {
                addLog('Error: ' + error.message, 'error');
            }
        }
        
        // Obtener diferencias entre tablas
        async function refreshDifferences() {
            try {
                const response = await fetch(`${API_URL}?action=differences`);
                const data = await response.json();
                
                if (data.success) {
                    renderTableList(data.data);
                } else {
                    addLog('Error obteniendo diferencias: ' + data.message, 'error');
                }
            } catch (error) {
                addLog('Error: ' + error.message, 'error');
            }
        }
        
        // Renderizar lista de tablas
        function renderTableList(tables) {
            const container = document.getElementById('table-list');
            
            if (!tables || Object.keys(tables).length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-muted);">No se encontraron tablas</div>';
                return;
            }
            
            let html = '';
            for (const [table, info] of Object.entries(tables)) {
                const syncIcon = info.in_sync 
                    ? '<svg class="sync-indicator synced" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>'
                    : '<svg class="sync-indicator not-synced" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>';
                
                html += `
                    <div class="table-item" onclick="syncTable('${table}')">
                        <span class="table-name">${table}</span>
                        <div class="table-counts">
                            <span class="count-badge local">Local: ${info.local_count}</span>
                            <span class="count-badge remote">Remoto: ${info.remote_count}</span>
                            ${syncIcon}
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
        
        // Sincronización completa
        async function syncAll(direction) {
            addLog(`Iniciando sincronización (${direction})...`, 'warning');
            
            try {
                const response = await fetch(`${API_URL}?action=sync_all&direction=${direction}`);
                const data = await response.json();
                
                if (data.success) {
                    addLog('Sincronización completada exitosamente', 'success');
                    
                    // Mostrar detalles del log
                    if (data.data && data.data.log) {
                        data.data.log.forEach(entry => {
                            addLog(entry.message, entry.type);
                        });
                    }
                } else {
                    addLog('Error en sincronización: ' + data.message, 'error');
                    
                    if (data.data && data.data.errors) {
                        data.data.errors.forEach(error => {
                            addLog(error.message, 'error');
                        });
                    }
                }
                
                refreshDifferences();
            } catch (error) {
                addLog('Error: ' + error.message, 'error');
            }
        }
        
        // Sincronizar tabla individual
        async function syncTable(table) {
            addLog(`Sincronizando tabla ${table}...`);
            
            try {
                const response = await fetch(`${API_URL}?action=sync_table&table=${table}&direction=both`);
                const data = await response.json();
                
                if (data.success) {
                    addLog(`Tabla ${table} sincronizada`, 'success');
                } else {
                    addLog(`Error sincronizando ${table}: ${data.message}`, 'error');
                }
                
                refreshDifferences();
            } catch (error) {
                addLog('Error: ' + error.message, 'error');
            }
        }
        
        // Preparar tablas
        async function prepareTables() {
            addLog('Preparando tablas para sincronización...', 'warning');
            
            try {
                const response = await fetch(`${API_URL}?action=prepare`);
                const data = await response.json();
                
                if (data.success) {
                    addLog('Tablas preparadas correctamente', 'success');
                    
                    if (data.log) {
                        data.log.forEach(entry => {
                            addLog(entry.message, entry.type);
                        });
                    }
                } else {
                    addLog('Error preparando tablas: ' + data.message, 'error');
                }
            } catch (error) {
                addLog('Error: ' + error.message, 'error');
            }
        }
        
        // Auto-refresh cada 30 segundos
        setInterval(() => {
            checkStatus();
        }, 30000);
    </script>
</body>
</html>
