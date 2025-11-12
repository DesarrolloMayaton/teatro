<?php
session_start();

// Verificar que hay sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuario_apellido = $_SESSION['usuario_apellido'] ?? '';
$usuario_rol = $_SESSION['usuario_rol'] ?? 'empleado';
$nombre_completo = $usuario_nombre . ' ' . $usuario_apellido;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrador del Teatro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Estilos generales */ 
        :root {
            --sidebar-width-expanded: 230px;
            --sidebar-width-collapsed: 70px;
            --transition-speed: 0.3s;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
            overflow: hidden;
            transition: margin-left var(--transition-speed) ease;
        }

        /* --- Menú Lateral --- */
        nav.menu-lateral {
            width: var(--sidebar-width-expanded);
            height: 100vh;
            background-color: #2c3e50;
            box-shadow: 4px 0 12px rgba(0, 0, 0, 0.1);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            transition: width var(--transition-speed) ease;
        }

        .menu-links {
            flex-grow: 1;
            padding-top: 10px;
        }

        nav.menu-lateral a.menu-item {
            display: flex;
            align-items: center;
            color: #ecf0f1;
            padding: 18px 25px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            white-space: nowrap;
            border-bottom: 1px solid #34495e;
            transition: all var(--transition-speed) ease;
            border-left: 5px solid transparent;
            cursor: pointer;
            overflow: hidden;
        }

        nav.menu-lateral a.menu-item i {
            font-size: 1.3em;
            min-width: 30px;
            margin-right: 15px;
            transition: margin var(--transition-speed) ease;
        }

        nav.menu-lateral a.menu-item span {
            opacity: 1;
            transition: opacity 0.1s ease;
        }

        nav.menu-lateral a.menu-item:hover:not(.active) {
            background-color: #3498db;
            color: #ffffff;
            border-left-color: #ffffff;
        }

        nav.menu-lateral a.menu-item.active {
            background-color: #f4f7f6;
            color: #2c3e50;
            font-weight: 600;
            border-left-color: #3498db;
        }

        /* --- Estado colapsado --- */
        nav.menu-lateral.collapsed {
            width: var(--sidebar-width-collapsed);
        }

        nav.menu-lateral.collapsed a.menu-item {
            padding-left: 0;
            padding-right: 0;
            justify-content: center;
        }

        nav.menu-lateral.collapsed a.menu-item i {
            margin-right: 0;
        }

        nav.menu-lateral.collapsed a.menu-item span {
            opacity: 0;
            width: 0;
        }

        /* --- Botón de colapsar --- */
        .sidebar-toggle {
            padding: 15px 25px;
            color: #ecf0f1;
            border: none;
            cursor: pointer;
            text-align: left;
            display: flex;
            align-items: center;
            white-space: nowrap;
            overflow: hidden;
            border-bottom: 1px solid #34495e;
            transition: background-color var(--transition-speed) ease;
            background-color: transparent;
        }

        .sidebar-toggle:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-toggle i {
            font-size: 1.3em;
            min-width: 30px;
            margin-right: 15px;
            transition: margin var(--transition-speed) ease, transform var(--transition-speed) ease;
        }

        .sidebar-toggle span {
            opacity: 1;
            transition: opacity 0.1s ease;
        }

        nav.menu-lateral.collapsed .sidebar-toggle {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }

        nav.menu-lateral.collapsed .sidebar-toggle i {
            margin-right: 0;
            transform: rotate(180deg);
        }

        nav.menu-lateral.collapsed .sidebar-toggle span {
            opacity: 0;
            width: 0;
        }

        /* --- Contenedor principal --- */
        .content-container {
            margin-left: var(--sidebar-width-expanded);
            width: calc(100% - var(--sidebar-width-expanded));
            height: 100vh;
            transition: margin-left var(--transition-speed) ease, width var(--transition-speed) ease;
        }

        body.sidebar-collapsed .content-container {
            margin-left: var(--sidebar-width-collapsed);
            width: calc(100% - var(--sidebar-width-collapsed));
        }

        .content-frame {
            width: 100%;
            height: 100%;
            border: none;
            display: none;
        }

        .content-frame.active {
            display: block;
        }

        /* --- Información de usuario --- */
        .user-info {
            padding: 20px 25px;
            color: #ecf0f1;
            border-bottom: 1px solid #34495e;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .user-info .user-name {
            font-weight: 600;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info .user-role {
            font-size: 0.85em;
            opacity: 0.8;
            padding: 4px 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: inline-block;
            width: fit-content;
        }

        .user-info .btn-logout {
            margin-top: 10px;
            padding: 10px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.95em;
            transition: all 0.3s;
        }

        .user-info .btn-logout:hover {
            background: #c0392b;
        }

        nav.menu-lateral.collapsed .user-info {
            padding: 15px 5px;
            align-items: center;
        }

        nav.menu-lateral.collapsed .user-info .user-name span,
        nav.menu-lateral.collapsed .user-info .user-role {
            display: none;
        }

        nav.menu-lateral.collapsed .user-info .btn-logout span {
            display: none;
        }

        /* --- Modal para verificar admin --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: modalSlide 0.3s ease;
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-content p {
            color: #666;
            margin-bottom: 20px;
        }

        .modal-content input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            margin-bottom: 20px;
        }

        .modal-content input:focus {
            outline: none;
            border-color: #667eea;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
        }

        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-modal-confirm {
            background: #667eea;
            color: white;
        }

        .btn-modal-confirm:hover {
            background: #5568d3;
        }

        .btn-modal-cancel {
            background: #e0e0e0;
            color: #333;
        }

        .btn-modal-cancel:hover {
            background: #d0d0d0;
        }

        .modal-error {
            color: #e74c3c;
            font-size: 0.9em;
            margin-top: -15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <nav class="menu-lateral" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle" title="Minimizar Menú">
            <i class="bi bi-arrow-left-square-fill"></i> <span>Minimizar</span>
        </button>

        <div class="user-info">
            <div class="user-name">
                <i class="bi bi-person-circle"></i>
                <span><?php echo htmlspecialchars($nombre_completo); ?></span>
            </div>
            <div class="user-role">
                <?php echo $usuario_rol === 'admin' ? '👑 Administrador' : '👤 Empleado'; ?>
            </div>
            <button class="btn-logout" onclick="location.href='logout.php'">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar Sesión</span>
            </button>
        </div>

        <div class="menu-links">
            <a class="menu-item" data-target="frame-inicio"><i class="bi bi-house-door-fill"></i> <span>Inicio</span></a>
            <a class="menu-item" data-target="frame-evento"><i class="bi bi-calendar-event-fill"></i> <span>Evento</span></a>
            <a class="menu-item" data-target="frame-venta"><i class="bi bi-ticket-perforated-fill"></i> <span>Venta</span></a>
            <a class="menu-item" data-target="frame-mapa"><i class="bi bi-map-fill"></i> <span>Ajuste escenario</span></a> 
            <a class="menu-item" data-target="frame-cartelera"><i class="bi bi-film"></i> <span>Cartelera</span></a>
            <a class="menu-item" id="admin-link" data-target="frame-admin"><i class="bi bi-gear-fill"></i> <span>Administración</span></a>
            <?php if ($usuario_rol === 'admin'): ?>
            <a class="menu-item" data-target="frame-registro"><i class="bi bi-person-plus-fill"></i> <span>Registrar Empleado</span></a>
            <?php endif; ?>
        </div>
        </div>
    </nav>

    <div class="content-container" id="contentContainer">
        <iframe id="frame-inicio" src="ind_menu/inicio.php" name="frame-inicio" class="content-frame active"></iframe>
        <iframe id="frame-evento" src="evt_interfaz/index.php" name="frame-evento" class="content-frame"></iframe>
        <iframe id="frame-venta" src="vnt_interfaz/index.php" name="frame-venta" class="content-frame"></iframe>
        <iframe id="frame-cartelera" src="crt_interfaz/index.php" name="frame-cartelera" class="content-frame"></iframe>
        <iframe id="frame-mapa" src="mp_interfaz/index.php" name="frame-mapa" class="content-frame"></iframe>
        <iframe id="frame-admin" src="admin_interfaz/index.php" name="frame-admin" class="content-frame"></iframe>
        <?php if ($usuario_rol === 'admin'): ?>
        <iframe id="frame-registro" src="auth/registrar_empleado.php" name="frame-registro" class="content-frame"></iframe>
        <?php endif; ?>
    </div>

    <!-- Modal para verificar contraseña de admin -->
    <div class="modal-overlay" id="adminModal">
        <div class="modal-content">
            <h3><i class="bi bi-shield-lock-fill"></i> Verificación de Administrador</h3>
            <p>Para acceder al panel de administración, ingrese la contraseña del administrador:</p>
            <input type="password" id="adminPassword" placeholder="Contraseña de admin" maxlength="6">
            <div class="modal-error" id="modalError" style="display: none;"></div>
            <div class="modal-buttons">
                <button class="btn-modal-cancel" onclick="cancelarVerificacion()">Cancelar</button>
                <button class="btn-modal-confirm" onclick="verificarAdmin()">Verificar</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const menuLinks = document.querySelectorAll('nav.menu-lateral a.menu-item');
            const iframes = document.querySelectorAll('.content-frame');

            const defaultFrameId = 'frame-inicio';
            const tabStorageKey = 'ultimaPestanaActiva';
            const sidebarStateKey = 'sidebarEstado';

            function cambiarPestana(targetId) {
                // Protección adicional: No permitir cambiar a frame-admin si es empleado sin verificación
                if (targetId === 'frame-admin') {
                    <?php if ($usuario_rol === 'empleado' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado'])): ?>
                        console.log('Acceso denegado a administración sin verificación (empleado)');
                        mostrarModalAdmin('frame-admin');
                        return false;
                    <?php endif; ?>
                }
                
                let frameEncontrado = false;
                iframes.forEach(frame => {
                    const isActive = frame.id === targetId;
                    frame.classList.toggle('active', isActive);
                    if (isActive) frameEncontrado = true;
                });
                menuLinks.forEach(link => {
                    link.classList.toggle('active', link.dataset.target === targetId);
                });
                if (frameEncontrado) {
                    localStorage.setItem(tabStorageKey, targetId);
                }
                return frameEncontrado;
            }

            function toggleSidebar() {
                const isCollapsed = sidebar.classList.toggle('collapsed');
                document.body.classList.toggle('sidebar-collapsed', isCollapsed);
                localStorage.setItem(sidebarStateKey, isCollapsed ? 'collapsed' : 'expanded');
                const toggleButtonText = sidebarToggle.querySelector('span');
                const toggleButtonIcon = sidebarToggle.querySelector('i');
                if (toggleButtonText) {
                    toggleButtonText.textContent = isCollapsed ? 'Expandir' : 'Minimizar';
                }
                sidebarToggle.title = isCollapsed ? 'Expandir Menú' : 'Minimizar Menú';
                toggleButtonIcon.className = isCollapsed ? 'bi bi-arrow-right-square-fill' : 'bi bi-arrow-left-square-fill';
            }

            const savedSidebarState = localStorage.getItem(sidebarStateKey);
            let initialCollapsedState = false;

            if (savedSidebarState === 'collapsed') {
                initialCollapsedState = true;
                sidebar.style.transition = 'none';
                document.body.style.transition = 'none';
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }

            const toggleButtonText = sidebarToggle.querySelector('span');
            const toggleButtonIcon = sidebarToggle.querySelector('i');
            if (toggleButtonText) toggleButtonText.textContent = initialCollapsedState ? 'Expandir' : 'Minimizar';
            sidebarToggle.title = initialCollapsedState ? 'Expandir Menú' : 'Minimizar Menú';
            toggleButtonIcon.className = initialCollapsedState ? 'bi bi-arrow-right-square-fill' : 'bi bi-arrow-left-square-fill';

            setTimeout(() => {
                sidebar.style.transition = '';
                document.body.style.transition = '';
            }, 50);

            let pestanaGuardada = localStorage.getItem(tabStorageKey);
            let pestanaValida = false;
            
            // Si la pestaña guardada es frame-admin, validar acceso
            if (pestanaGuardada === 'frame-admin') {
                <?php if ($usuario_rol === 'empleado' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado'])): ?>
                    console.log('Pestaña admin guardada pero empleado sin verificación, redirigiendo a inicio');
                    pestanaGuardada = null; // Forzar a inicio
                <?php endif; ?>
            }
            
            if (pestanaGuardada) {
                pestanaValida = cambiarPestana(pestanaGuardada);
            }
            if (!pestanaGuardada || !pestanaValida) {
                cambiarPestana(defaultFrameId);
            }

            // Manejo del botón de administración (debe ir ANTES del evento general)
            const adminLink = document.getElementById('admin-link');
            if (adminLink) {
                adminLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Si el usuario es admin, dar acceso directo
                    <?php if ($usuario_rol === 'admin'): ?>
                        cambiarPestana('frame-admin');
                    <?php elseif (isset($_SESSION['admin_verificado']) && $_SESSION['admin_verificado']): ?>
                        // Si es empleado pero ya verificó la contraseña del admin
                        cambiarPestana('frame-admin');
                    <?php else: ?>
                        // Si es empleado y no ha verificado, pedir contraseña
                        mostrarModalAdmin('frame-admin');
                    <?php endif; ?>
                }, true); // Usar capture phase para que se ejecute primero
            }

            menuLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Si es el link de admin, validar acceso
                    if (this.id === 'admin-link') {
                        <?php if ($usuario_rol === 'empleado' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado'])): ?>
                            // Solo bloquear si es empleado y no ha verificado
                            e.preventDefault();
                            e.stopPropagation();
                            return false;
                        <?php endif; ?>
                    }
                    
                    const targetId = this.dataset.target;
                    cambiarPestana(targetId);
                });
            });

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }
        });

        // Funciones para el modal de verificación de admin
        let targetFrame = '';

        function mostrarModalAdmin(frameTarget) {
            targetFrame = frameTarget;
            document.getElementById('adminModal').classList.add('active');
            document.getElementById('adminPassword').focus();
            document.getElementById('modalError').style.display = 'none';
        }

        function cancelarVerificacion() {
            document.getElementById('adminModal').classList.remove('active');
            document.getElementById('adminPassword').value = '';
            document.getElementById('modalError').style.display = 'none';
        }

        async function verificarAdmin() {
            const password = document.getElementById('adminPassword').value;
            const errorDiv = document.getElementById('modalError');
            
            if (!password) {
                errorDiv.textContent = 'Por favor ingrese la contraseña';
                errorDiv.style.display = 'block';
                return;
            }

            try {
                const response = await fetch('auth/verificar_admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ password: password })
                });

                const data = await response.json();

                if (data.success) {
                    // Verificación exitosa
                    cancelarVerificacion();
                    // Cambiar a la pestaña de admin
                    const iframes = document.querySelectorAll('.content-frame');
                    const menuLinks = document.querySelectorAll('nav.menu-lateral a.menu-item');
                    
                    iframes.forEach(frame => {
                        frame.classList.toggle('active', frame.id === targetFrame);
                    });
                    
                    menuLinks.forEach(link => {
                        link.classList.toggle('active', link.dataset.target === targetFrame);
                    });
                    
                    localStorage.setItem('ultimaPestanaActiva', targetFrame);
                    
                    // Recargar la página para actualizar la sesión
                    location.reload();
                } else {
                    errorDiv.textContent = data.message || 'Contraseña incorrecta';
                    errorDiv.style.display = 'block';
                    document.getElementById('adminPassword').value = '';
                    document.getElementById('adminPassword').focus();
                }
            } catch (error) {
                errorDiv.textContent = 'Error al verificar. Intente de nuevo.';
                errorDiv.style.display = 'block';
            }
        }

        // Permitir Enter en el campo de contraseña
        document.addEventListener('DOMContentLoaded', () => {
            const passwordInput = document.getElementById('adminPassword');
            if (passwordInput) {
                passwordInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        verificarAdmin();
                    }
                });
            }
        });
    </script>

</body>
</html>