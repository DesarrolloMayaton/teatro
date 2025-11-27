<?php
session_start();
include "../conexion.php"; 

// 1. VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['usuario_id'])) {
    die('<html><head><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></head><body style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial; background: #f4f7f6; color: #e74c3c; font-size: 1.2em;"><div style="text-align: center;"><i class="bi bi-lock-fill" style="font-size: 3em;"></i><p>Acceso denegado.</p></div></body></html>');
}

if ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado'])) {
    die('<html><head><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></head><body style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial; background: #f4f7f6; color: #e74c3c; font-size: 1.2em;"><div style="text-align: center;"><i class="bi bi-shield-lock-fill" style="font-size: 3em;"></i><p>Requiere permisos de Administrador.</p><button onclick="window.parent.location.href=\'/teatro/index.php\'" style="margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer;">Volver</button></div></body></html>');
}

// 2. DETERMINAR VISTA INICIAL
$sql_check = "SELECT COUNT(*) as total FROM evento WHERE finalizado = 0";
$res_check = $conn->query($sql_check);
$row_check = $res_check->fetch_assoc();
$hay_eventos = $row_check['total'] > 0;

$tab = $_GET['tab'] ?? 'activos';
$src_inicial = '';
$class_activos = ''; 
$class_historial = '';

if ($tab === 'historial') {
    $src_inicial = 'htr_eventos.php';
    $class_historial = 'active';
} else {
    if ($hay_eventos) {
        $src_inicial = "act_evento.php";
    } else {
        $html_vacio = '<!DOCTYPE html>
    <html lang="es">
    <head>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            body { height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8fafc; color: #64748b; font-family: sans-serif; text-align: center; }
            .empty-state { max-width: 400px; padding: 40px; }
            .icon-box { font-size: 4rem; color: #cbd5e1; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="empty-state">
            <div class="icon-box"><i class="bi bi-calendar-x"></i></div>
            <h3 class="fw-bold text-dark">No hay eventos activos</h3>
            <p class="mb-0">Actualmente no existen eventos disponibles en la plataforma.</p>
        </div>
    </body>
    </html>';
    
    $src_inicial = "data:text/html;base64," . base64_encode($html_vacio);
    }
    
    // Solo marcamos activo si no se especificó historial explícitamente
    if ($tab !== 'historial') $class_activos = 'active';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --sidebar-bg: #1e293b; --sidebar-text: #94a3b8; --active-color: #3b82f6;
            --hover-bg: rgba(255, 255, 255, 0.05); --body-bg: #f1f5f9;
        }
        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            margin: 0; padding: 0; background-color: var(--body-bg);
            display: flex; height: 100vh; overflow: hidden;
        }
        
        nav.menu-admin {
            width: 200px; background-color: var(--sidebar-bg); color: white;
            display: flex; flex-direction: column; padding: 20px 10px;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.15); flex-shrink: 0; z-index: 10;
            animation: slideIn 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        @keyframes slideIn { from { transform: translateX(-100%); } to { transform: translateX(0); } }

        .menu-header {
            padding: 0 10px 25px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 15px; display: flex; align-items: center; gap: 10px;
        }
        .menu-header h4 { color: white; font-weight: 700; margin: 0; font-size: 1.1em; letter-spacing: 0.5px; }
        
        nav.menu-admin a.menu-item {
            display: flex; align-items: center; color: var(--sidebar-text);
            padding: 12px 15px; margin-bottom: 6px; text-decoration: none;
            font-size: 14px; font-weight: 500; border-radius: 10px;
            transition: all 0.2s ease; border: 1px solid transparent;
        }
        nav.menu-admin a.menu-item i { font-size: 1.2em; min-width: 28px; transition: transform 0.2s; }
        nav.menu-admin a.menu-item:hover { background-color: var(--hover-bg); color: white; transform: translateX(4px); }
        
        nav.menu-admin a.menu-item.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); font-weight: 600;
        }
        
        .menu-separator { height: 1px; background: rgba(255,255,255,0.1); margin: 15px 5px; }
        .contenido-admin { flex-grow: 1; display: flex; flex-direction: column; height: 100vh; background-color: var(--body-bg); animation: fadeIn 0.6s ease-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        iframe.content-frame { width: 100%; height: 100%; border: none; transition: opacity 0.3s ease; }
        .iframe-loading { opacity: 0.5; filter: blur(2px); }
    </style>
</head>
<body>

    <nav class="menu-admin">
        <div class="menu-header">
             <h4 class="m-0"><i class="bi bi-grid-fill text-primary me-2"></i> Admin</h4>
        </div>
        
        <a class="menu-item <?= $class_activos ?>" href="act_evento.php" target="contentFrame" onclick="manualLoad()">
            <i class="bi bi-activity"></i> Activos
        </a>
    
        <a class="menu-item <?= $class_historial ?>" href="htr_eventos.php" target="contentFrame" onclick="manualLoad()">
            <i class="bi bi-archive"></i> Historial
        </a>
        
        <div class="menu-separator"></div>

        <a class="menu-item" href="crear_evento.php" target="contentFrame" onclick="manualLoad()">
            <i class="bi bi-plus-lg"></i> Nuevo
        </a>
    </nav>

    <div class="contenido-admin">
        <iframe class="content-frame" name="contentFrame" id="contentFrame" src="<?php echo $src_inicial; ?>"></iframe>
    </div>

    <script>
        const iframe = document.getElementById('contentFrame');
        const menuItems = document.querySelectorAll('nav.menu-admin a.menu-item');

        function manualLoad() {
            iframe.classList.add('iframe-loading');
        }

        // --- LÓGICA AUTOMÁTICA DEL MENÚ ---
        iframe.onload = function() {
            iframe.classList.remove('iframe-loading');

            try {
                // 1. Obtener nombre del archivo cargado
                const path = iframe.contentWindow.location.pathname;
                const filename = path.substring(path.lastIndexOf('/') + 1);
                
                // 2. Obtener parámetros URL (ej: ?modo_reactivacion=1)
                const searchParams = iframe.contentWindow.location.search;

                menuItems.forEach(link => {
                    link.classList.remove('active'); // Limpiar todos
                    const href = link.getAttribute('href');

                    // CASO 1: Coincidencia Exacta (act_evento.php, htr_eventos.php, crear_evento.php)
                    if (href === filename) {
                        link.classList.add('active');
                    } 
                    
                    // CASO 2: Estamos en el EDITOR
                    else if (filename.includes('editar_evento.php')) {
                        
                        // Si estamos REACTIVANDO (viene del historial) -> Iluminar Historial
                        if (searchParams.includes('modo_reactivacion=1') && href === 'htr_eventos.php') {
                            link.classList.add('active');
                        }
                        
                        // Si es EDICIÓN NORMAL (activo) -> Iluminar Activos
                        else if (!searchParams.includes('modo_reactivacion=1') && href === 'act_evento.php') {
                            link.classList.add('active');
                        }
                    }
                });
            } catch (e) {
                // Silencioso
            }
        };
    </script>

</body>
</html>