<?php
session_start();
include "../conexion.php"; // Incluir conexión para verificar usuarios

// Verificar que hay sesión activa
if (!isset($_SESSION['usuario_id'])) {
    die('<html><head><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></head><body style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial; background: #f4f7f6; color: #e74c3c; font-size: 1.2em;">
    <div style="text-align: center;">
        <i class="bi bi-lock-fill" style="font-size: 3em;"></i>
        <p>Acceso denegado. Debe iniciar sesión.</p>
    </div>
    </body></html>');
}

// Verificar acceso al panel de administración
if ($_SESSION['usuario_rol'] !== 'admin') {
    if (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']) {
        die('<html><head><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></head><body style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial; background: #f4f7f6; color: #e74c3c; font-size: 1.2em;">
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
    <title></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        nav.menu-admin {
            width: 230px;
            background-color: #1e293b; /* Color del menu.php */
            color: white;
            display: flex;
            flex-direction: column;
            padding-top: 10px;
            box-shadow: 4px 0 12px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }
        .menu-header {
            padding: 10px 20px 20px 20px;
            border-bottom: 1px solid #34495e;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .menu-header h4 {
            color: white;
            font-weight: 600;
            margin: 0;
            font-size: 1.2em;
        }
        .menu-header .btn-reload {
            background: none;
            border: 1px solid #566573;
            color: #bdc3c7;
            border-radius: 5px;
            cursor: pointer;
        }
        nav.menu-admin a.menu-item {
            display: flex;
            align-items: center;
            color: #bdc3c7; /* Color de texto más suave */
            padding: 16px 20px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        nav.menu-admin a.menu-item i {
            font-size: 1.2em;
            min-width: 30px;
            margin-right: 12px;
            opacity: 0.8;
        }
        nav.menu-admin a.menu-item:hover {
            background-color: #2c3e50;
            color: #fff;
        }
        nav.menu-admin a.menu-item.active {
            background-color: #2563eb; /* Azul primario */
            color: #fff;
            border-left: 4px solid #fff;
            font-weight: 600;
        }
        .menu-footer {
            margin-top: auto;
            padding: 20px;
        }
        .menu-footer .btn-success {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background-color: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
        }
        header {
            background-color: #ffffff;
            color: #2c3e50;
            padding: 12px 20px;
            font-size: 18px;
            font-weight: 600;
            flex-shrink: 0;
            width: 100%;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .contenido-admin {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        iframe.content-frame {
            flex-grow: 1;
            width: 100%;
            height: 100%;
            border: none;
            background-color: #f8fafc; /* Color de fondo del content */
        }
    </style>
</head>
<body>

    <nav class="menu-admin" id="menuAdmin">
        <div class="menu-header">
             <h4 class="m-0"><i class="bi bi-grid-fill me-2"></i>Dashboard</h4>
             <button onclick="reloadFrame()" class="btn-reload" title="Actualizar Datos"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
        
        <a class="menu-item" href="act_evento.php" target="contentFrame">
            <i class="bi bi-activity"></i> Activos
        </a>
    
        <a class="menu-item" href="htr_eventos.php" target="contentFrame">
            <i class="bi bi-archive"></i> Historial
        </a>
        
        <div style="height: 1px; background: #34495e; margin: 10px 20px;"></div>

        <a class="menu-item" href="crear_evento.php" target="contentFrame">
            <i class="bi bi-plus-lg"></i> Nuevo Evento
        </a>
    </nav>

    <div class="contenido-admin">
        <header></header>

        <iframe class="content-frame" name="contentFrame" id="contentFrame" src="act_evento.php">
            Tu navegador no soporta iframes.
        </iframe>
    </div>

    <script>
        // Script para manejar la clase 'active'
        document.querySelectorAll('nav.menu-admin a.menu-item').forEach(link => {
            link.addEventListener('click', function(e) {
                document.querySelectorAll('nav.menu-admin a.menu-item').forEach(item => {
                    item.classList.remove('active');
                });
                this.classList.add('active');
            });
        });

        // Activar el link de "Activos" por defecto
        document.querySelector('nav.menu-admin a.menu-item[href="act_evento.php"]').classList.add('active');
        
        // Función para recargar el iframe
        function reloadFrame() {
            document.getElementById('contentFrame').src = document.getElementById('contentFrame').src;
        }
    </script>

</body>
</html>