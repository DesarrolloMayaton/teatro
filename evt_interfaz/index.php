<?php
session_start();
include "../conexion.php"; 

if (!isset($_SESSION['usuario_id'])) {
    die('<html><head><link rel="stylesheet" href="../assets/css/teatro-style.css"></head>
    <body style="display:flex;justify-content:center;align-items:center;height:100vh;">
    <div style="text-align:center;color:var(--danger);"><p>Acceso denegado</p></div></body></html>');
}

if ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado'])) {
    die('<html><head><link rel="stylesheet" href="../assets/css/teatro-style.css"></head>
    <body style="display:flex;justify-content:center;align-items:center;height:100vh;">
    <div style="text-align:center;color:var(--danger);"><p>Solo administradores</p></div></body></html>');
}

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
        $src_inicial = "data:text/html;base64," . base64_encode('<html><head><link rel="stylesheet" href="../assets/css/teatro-style.css"></head>
        <body style="display:flex;justify-content:center;align-items:center;height:100vh;">
        <div style="text-align:center;"><i class="bi bi-calendar-x" style="font-size:3rem;color:var(--text-muted);"></i>
        <h3 style="color:var(--text-primary);margin-top:16px;">No hay eventos activos</h3>
        <p style="color:var(--text-muted);">Crea un nuevo evento para comenzar</p></div></body></html>');
    }
    if ($tab !== 'historial') $class_activos = 'active';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Eventos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/teatro-style.css">
    <style>
        body {
            background: var(--bg-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .event-sidebar {
            width: 200px;
            background: var(--bg-secondary);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .event-sidebar-header {
            padding: 20px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .event-sidebar-header i {
            color: var(--accent-blue);
            font-size: 1.2rem;
        }

        .event-sidebar-header h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .event-menu {
            flex: 1;
            padding: 12px 8px;
        }

        .event-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            margin-bottom: 4px;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition-fast);
        }

        .event-menu-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .event-menu-item.active {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }

        .event-menu-item i {
            font-size: 1.1rem;
        }

        .event-menu-divider {
            height: 1px;
            background: var(--border-color);
            margin: 10px 0;
        }

        .event-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .event-frame {
            flex: 1;
            width: 100%;
            border: none;
            background: var(--bg-primary);
        }
    </style>
</head>
<body>
    <aside class="event-sidebar">
        <div class="event-sidebar-header">
            <i class="bi bi-calendar-event-fill"></i>
            <h4>Eventos</h4>
        </div>

        <nav class="event-menu">
            <a class="event-menu-item <?= $class_activos ?>" href="act_evento.php" target="contentFrame">
                <i class="bi bi-lightning-fill"></i> Activos
            </a>
            <a class="event-menu-item <?= $class_historial ?>" href="htr_eventos.php" target="contentFrame">
                <i class="bi bi-archive-fill"></i> Historial
            </a>
            <div class="event-menu-divider"></div>
            <a class="event-menu-item" href="crear_evento.php" target="contentFrame">
                <i class="bi bi-plus-circle-fill"></i> Nuevo Evento
            </a>
        </nav>
    </aside>

    <main class="event-content">
        <iframe class="event-frame" name="contentFrame" id="contentFrame" src="<?php echo $src_inicial; ?>"></iframe>
    </main>

    <!-- Modal de confirmación -->
    <div id="modalConfirm" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(8px); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:var(--bg-secondary); border-radius:var(--radius-lg); border:1px solid var(--border-color); padding:32px; max-width:380px; width:90%; text-align:center;">
            <i class="bi bi-exclamation-triangle-fill" style="font-size:3rem; color:var(--warning); display:block; margin-bottom:16px;"></i>
            <h4 style="color:var(--text-primary); margin-bottom:12px;">¿Descartar cambios?</h4>
            <p style="color:var(--text-muted); margin-bottom:24px; font-size:0.95rem;">Tienes cambios sin guardar. Si continúas, se perderán.</p>
            <div style="display:flex; gap:12px;">
                <button id="btnCancelNav" style="flex:1; padding:12px; background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:var(--radius-sm); color:var(--text-primary); font-weight:600; cursor:pointer;">Cancelar</button>
                <button id="btnConfirmNav" style="flex:1; padding:12px; background:var(--danger); border:none; border-radius:var(--radius-sm); color:white; font-weight:600; cursor:pointer;">Descartar</button>
            </div>
        </div>
    </div>

    <script>
        const iframe = document.getElementById('contentFrame');
        const menuItems = document.querySelectorAll('.event-menu-item');
        const modal = document.getElementById('modalConfirm');
        let pendingUrl = null;
        let pendingElement = null;

        // Verificar si el iframe tiene un formulario con cambios
        function tieneFormularioConCambios() {
            try {
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                const path = iframe.contentWindow.location.pathname;
                
                // Solo verificar en páginas de crear/editar
                if (!path.includes('crear_evento.php') && !path.includes('editar_evento.php')) {
                    return false;
                }
                
                // Buscar campos de formulario con datos
                const titulo = iframeDoc.getElementById('titulo');
                const descripcion = iframeDoc.getElementById('descripcion');
                const listaFunciones = iframeDoc.getElementById('listaFunciones');
                
                // Verificar si hay datos ingresados
                if (titulo && titulo.value.trim() !== '') return true;
                if (descripcion && descripcion.value.trim() !== '') return true;
                if (listaFunciones) {
                    const funciones = listaFunciones.querySelectorAll('.funcion-item');
                    if (funciones.length > 0) return true;
                }
                
                // También verificar si el formulario fue modificado (para editar)
                const form = iframeDoc.getElementById('formEvento');
                if (form && form.dataset.modified === 'true') return true;
                
                return false;
            } catch (e) {
                return false; // Si no puede acceder, permitir navegación
            }
        }

        function navegarA(url, element) {
            menuItems.forEach(m => m.classList.remove('active'));
            if (element) element.classList.add('active');
            iframe.src = url;
        }

        function mostrarModal(url, element) {
            pendingUrl = url;
            pendingElement = element;
            modal.style.display = 'flex';
        }

        function cerrarModal() {
            modal.style.display = 'none';
            pendingUrl = null;
            pendingElement = null;
        }

        document.getElementById('btnCancelNav').addEventListener('click', cerrarModal);
        
        document.getElementById('btnConfirmNav').addEventListener('click', function() {
            if (pendingUrl) {
                navegarA(pendingUrl, pendingElement);
            }
            cerrarModal();
        });

        // Cerrar con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                cerrarModal();
            }
        });

        menuItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.getAttribute('href');
                
                // Si el iframe actual es crear/editar y tiene cambios, preguntar
                if (tieneFormularioConCambios()) {
                    mostrarModal(url, this);
                } else {
                    navegarA(url, this);
                }
            });
        });

        iframe.onload = function() {
            try {
                const path = iframe.contentWindow.location.pathname;
                const filename = path.substring(path.lastIndexOf('/') + 1);
                const search = iframe.contentWindow.location.search;

                menuItems.forEach(link => {
                    link.classList.remove('active');
                    const href = link.getAttribute('href');
                    
                    if (href === filename) {
                        link.classList.add('active');
                    } else if (filename.includes('editar_evento.php')) {
                        if (search.includes('modo_reactivacion=1') && href === 'htr_eventos.php') {
                            link.classList.add('active');
                        } else if (!search.includes('modo_reactivacion=1') && href === 'act_evento.php') {
                            link.classList.add('active');
                        }
                    }
                });
            } catch (e) {}
        };
    </script>
    <script src="../vnt_interfaz/js/teatro-sync.js"></script>
</body>
</html>