<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrador del Teatro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Estilos generales holas */ 
        
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

        /* --- El Menú Lateral (Sidebar) --- */
        nav.menu-lateral {
            width: var(--sidebar-width-expanded);
            height: 100vh;
            background-color: #2c3e50;
            /* REMOVED padding-top: 20px; */
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

        /* Contenedor de los enlaces */
        .menu-links {
             flex-grow: 1;
             padding-top: 10px; /* Add some space above links */
        }

        /* Estilo de los enlaces */
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

        /* REMOVED: first-child border-top rule was here */

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

        /* --- Estilos para el estado COLAPSADO --- */
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
             opacity: 0; width: 0;
         }
        /* --- Fin Estilos Colapsado --- */


        /* --- Botón para Colapsar/Expandir --- */
        .sidebar-toggle {
            padding: 15px 25px; /* Padding vertical y horizontal */
            /* REMOVED background-color */
            color: #ecf0f1;
            border: none;
            cursor: pointer;
            text-align: left;
            /* REMOVED margin-top: auto; */
            display: flex;
            align-items: center;
            white-space: nowrap;
            overflow: hidden;
            border-bottom: 1px solid #34495e; /* Separator line */
            transition: background-color var(--transition-speed) ease;
             background-color: transparent; /* Make it transparent initially */
        }
        .sidebar-toggle:hover {
            background-color: rgba(255, 255, 255, 0.1); /* Darken slightly on hover */
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

         /* Estilos botón colapsado */
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
             opacity: 0; width: 0;
         }
         /* --- Fin Botón Colapsar --- */


        /* --- El contenedor del contenido principal --- */
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


        /* Estilos para Múltiples IFrames */
        .content-frame {
            width: 100%; height: 100%; border: none; display: none;
        }
        .content-frame.active { display: block; }

    </style>
</head>
<body>

    <nav class="menu-lateral" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle" title="Minimizar Menú">
             <i class="bi bi-arrow-left-square-fill"></i> <span>Minimizar</span>
         </button>

        <div class="menu-links">
            <a class="menu-item" data-target="frame-inicio"><i class="bi bi-house-door-fill"></i> <span>Inicio</span></a>
            <a class="menu-item" data-target="frame-evento"><i class="bi bi-calendar-event-fill"></i> <span>Evento</span></a>
            <a class="menu-item" data-target="frame-venta"><i class="bi bi-ticket-perforated-fill"></i> <span>Venta</span></a>
            <a class="menu-item" data-target="frame-cartelera"><i class="bi bi-film"></i> <span>Cartelera</span></a>
        </div>
    </nav>
     <div class="content-container" id="contentContainer">
        <iframe id="frame-inicio" src="ind_menu/inicio.php" name="frame-inicio" class="content-frame active"></iframe>
        <iframe id="frame-evento" src="evt_interfaz/index.php" name="frame-evento" class="content-frame"></iframe>
        <iframe id="frame-venta" src="vnt_interfaz/index.php" name="frame-venta" class="content-frame"></iframe>
        <iframe id="frame-cartelera" src="crt_interfaz/index.php" name="frame-cartelera" class="content-frame"></iframe>
    </div>

    <script>
        // --- JAVASCRIPT REMAINS THE SAME as the previous version ---
        document.addEventListener('DOMContentLoaded', () => {

            const sidebar = document.getElementById('sidebar');
            const contentContainer = document.getElementById('contentContainer');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const menuLinks = document.querySelectorAll('nav.menu-lateral a.menu-item');
            const iframes = document.querySelectorAll('.content-frame');

            const defaultFrameId = 'frame-inicio';
            const tabStorageKey = 'ultimaPestanaActiva';
            const sidebarStateKey = 'sidebarEstado';

            function cambiarPestana(targetId) {
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
                 // Optionally change icon based on state
                 toggleButtonIcon.className = isCollapsed ? 'bi bi-arrow-right-square-fill' : 'bi bi-arrow-left-square-fill';
            }

            // --- LÓGICA DE CARGA ---
            const savedSidebarState = localStorage.getItem(sidebarStateKey);
            let initialCollapsedState = false; // Assume expanded initially

            if (savedSidebarState === 'collapsed') {
                 initialCollapsedState = true;
                 // Apply collapsed state immediately without transition
                 sidebar.style.transition = 'none';
                 document.body.style.transition = 'none';
                 sidebar.classList.add('collapsed');
                 document.body.classList.add('sidebar-collapsed');
            }

            // Update button based on initial state
             const toggleButtonText = sidebarToggle.querySelector('span');
             const toggleButtonIcon = sidebarToggle.querySelector('i');
             if (toggleButtonText) toggleButtonText.textContent = initialCollapsedState ? 'Expandir' : 'Minimizar';
             sidebarToggle.title = initialCollapsedState ? 'Expandir Menú' : 'Minimizar Menú';
             toggleButtonIcon.className = initialCollapsedState ? 'bi bi-arrow-right-square-fill' : 'bi bi-arrow-left-square-fill';

            // Re-enable transitions after initial state is set
            setTimeout(() => {
                sidebar.style.transition = '';
                document.body.style.transition = '';
            }, 50);


            // Restore last active tab
            let pestanaGuardada = localStorage.getItem(tabStorageKey);
            let pestanaValida = false;
            if (pestanaGuardada) {
                pestanaValida = cambiarPestana(pestanaGuardada);
            }
            if (!pestanaGuardada || !pestanaValida) {
                cambiarPestana(defaultFrameId);
            }

            // --- EVENT LISTENERS ---
            menuLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const targetId = this.dataset.target;
                    cambiarPestana(targetId);
                });
            });

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }
        });
    </script>

</body>
</html>