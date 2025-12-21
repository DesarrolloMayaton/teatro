<?php
session_start();

// Verificar que hay sesión activa
if (!isset($_SESSION['usuario_id'])) {
    die('<html><body style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial; background: #f4f7f6; color: #e74c3c; font-size: 1.2em;">
    <div style="text-align: center;">
        <i class="bi bi-lock-fill" style="font-size: 3em;"></i>
        <p>Acceso denegado. Debe iniciar sesión.</p>
    </div>
    </body></html>');
}
require_once '../transacciones_helper.php';
registrar_transaccion('admin_panel', 'Ingreso al panel de administración');

// Verificar acceso al panel de administración
// Si es admin de rol, acceso directo
// Si es empleado, debe haber verificado con contraseña del admin
if ($_SESSION['usuario_rol'] !== 'admin') {
    // Es empleado, verificar que haya ingresado contraseña del admin
    if (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']) {
        die('<html><body style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial; background: #f4f7f6; color: #e74c3c; font-size: 1.2em;">
        <div style="text-align: center;">
            <i class="bi bi-shield-lock-fill" style="font-size: 3em;"></i>
            <p>Acceso denegado. Requiere verificación de administrador.</p>
            <button onclick="window.parent.location.href=\'/teatro/index.php\'" style="margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 1em;">Volver al Sistema</button>
        </div>
        </body></html>');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border: #475569;
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-main);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        nav.menu-admin {
            width: 230px;
            background: linear-gradient(180deg, var(--bg-card) 0%, var(--bg-main) 100%);
            color: white;
            display: flex;
            flex-direction: column;
            padding-top: 10px;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
            flex-shrink: 0;
            border-right: 1px solid var(--border);
        }

        nav.menu-admin a.menu-item {
            display: flex;
            align-items: center;
            color: var(--text-secondary);
            padding: 16px 20px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 1px solid var(--border);
            transition: all 0.2s ease;
            cursor: pointer;
            border-left: 3px solid transparent;
        }

        nav.menu-admin a.menu-item i {
            font-size: 1.2em;
            min-width: 30px;
            margin-right: 12px;
            color: var(--text-secondary);
            transition: all 0.2s ease;
        }

        nav.menu-admin a.menu-item:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--text-primary);
            border-left-color: var(--primary);
        }

        nav.menu-admin a.menu-item:hover i {
            color: var(--primary);
        }

        nav.menu-admin a.menu-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.2) 0%, transparent 100%);
            color: var(--text-primary);
            border-left-color: var(--primary);
        }

        nav.menu-admin a.menu-item.active i {
            color: var(--primary);
        }

        header {
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-input) 100%);
            color: var(--text-primary);
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            flex-shrink: 0;
            width: 100%;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        header::before {
            content: '\F3D1';
            font-family: 'bootstrap-icons';
            font-size: 1.2em;
            color: var(--primary);
        }

        .contenido-admin {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: var(--bg-main);
        }

        iframe.content-frame {
            flex-grow: 1;
            width: 100%;
            height: 100%;
            border: none;
            background-color: var(--bg-main);
        }

        /* Selector de BD */
        .db-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 auto;
        }

        .db-toggle {
            display: flex;
            background: var(--bg-input);
            border-radius: 8px;
            padding: 3px;
            gap: 2px;
        }

        .db-toggle button {
            padding: 5px 10px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .db-toggle button:hover {
            color: var(--text-primary);
        }

        .db-toggle button.active {
            background: var(--primary);
            color: white;
        }

        .db-toggle button i {
            font-size: 0.9em;
        }

        /* Animación de cambio de BD */
        .content-frame {
            transition: opacity 0.3s ease;
        }

        .content-frame.loading {
            opacity: 0.4;
        }
    </style>
</head>
<body>

    <nav class="menu-admin" id="menuAdmin">
        <a class="menu-item" href="inicio/inicio.php" target="contentFrame">
            <i class="bi bi-house-door"></i> Inicio
        </a>
    
        <a class="menu-item" href="dsc_boletos/index.php" target="contentFrame">
            <i class="bi bi-percent"></i> Descuentos
        </a>

        <a class="menu-item" href="rpt_reportes/index.php" target="contentFrame">
            <i class="bi bi-graph-up"></i> Reportes
        </a>

        <a class="menu-item" href="ctg_boletos/index.php" target="contentFrame">
            <i class="bi bi-tags"></i> Categorías
        </a>
        <a class="menu-item" href="transacciones/index.php" target="contentFrame">
            <i class="bi bi-clock-history"></i> Transacciones
        </a>
    </nav>

    <div class="contenido-admin">
        <header>
            <span>Panel de Administración</span>
            <div class="db-selector">
                <span style="font-size: 0.8rem; color: var(--text-secondary);">Base de datos:</span>
                <div class="db-toggle">
                    <button id="btnAmbas" onclick="cambiarBD('ambas')">
                        <i class="bi bi-collection"></i> Ambas
                    </button>
                    <button id="btnActual" onclick="cambiarBD('actual')">
                        <i class="bi bi-database"></i> Actual
                    </button>
                    <button id="btnHistorico" onclick="cambiarBD('historico')">
                        <i class="bi bi-archive"></i> Histórico
                    </button>
                </div>
            </div>
        </header>

        <iframe class="content-frame" name="contentFrame" id="contentFrame" src="inicio/inicio.php">
            Tu navegador no soporta iframes.
        </iframe>
    </div>

    <script>
        // Estado de base de datos seleccionada
        let dbActual = sessionStorage.getItem('admin_db') || 'ambas';

        // Inicializar al cargar
        document.addEventListener('DOMContentLoaded', () => {
            // Restaurar estado de BD
            actualizarEstadoToggle();
            
            // Configurar menú
            document.querySelectorAll('nav.menu-admin a.menu-item').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('nav.menu-admin a.menu-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    // Cargar con parámetro de BD
                    const url = agregarParamDB(this.getAttribute('href'));
                    document.getElementById('contentFrame').src = url;
                });
            });

            // Activar inicio por defecto
            document.querySelector('nav.menu-admin a.menu-item[href="inicio/inicio.php"]').classList.add('active');
            
            // Cargar inicio con BD correcta
            document.getElementById('contentFrame').src = agregarParamDB('inicio/inicio.php');
        });

        // Cambiar base de datos
        function cambiarBD(db) {
            dbActual = db;
            sessionStorage.setItem('admin_db', db);
            actualizarEstadoToggle();
            
            // Recargar iframe actual con nueva BD
            const iframe = document.getElementById('contentFrame');
            const currentSrc = iframe.src.split('?')[0].split('/').pop() || 'inicio/inicio.php';
            const activeLink = document.querySelector('nav.menu-admin a.menu-item.active');
            const href = activeLink ? activeLink.getAttribute('href') : 'inicio/inicio.php';
            iframe.src = agregarParamDB(href);
        }

        // Actualizar estado visual del toggle
        function actualizarEstadoToggle() {
            document.getElementById('btnActual').classList.toggle('active', dbActual === 'actual');
            document.getElementById('btnHistorico').classList.toggle('active', dbActual === 'historico');
            document.getElementById('btnAmbas').classList.toggle('active', dbActual === 'ambas');
        }

        // Agregar parámetro de BD a URL
        function agregarParamDB(url) {
            const separator = url.includes('?') ? '&' : '?';
            return url + separator + 'db=' + dbActual;
        }
    </script>

</body>
</html>