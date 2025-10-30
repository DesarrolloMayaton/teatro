<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <base href="/teatro/admin_interfaz/">

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
            background-color: #2c3e50;
            color: white;
            display: flex;
            flex-direction: column;
            padding-top: 10px;
            box-shadow: 4px 0 12px rgba(0, 0, 0, 0.1);
            flex-shrink: 0; /* Evita que el menú se encoja */
        }

        nav.menu-admin a.menu-item {
            display: flex;
            align-items: center;
            color: #ecf0f1;
            padding: 18px 20px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            border-bottom: 1px solid #34495e;
            transition: all 0.3s ease;
            cursor: pointer;
            border-left: 4px solid transparent; /* Borde de inactividad */
        }

        nav.menu-admin a.menu-item i {
            font-size: 1.3em;
            min-width: 30px;
            margin-right: 12px;
        }

        nav.menu-admin a.menu-item:hover {
            background-color: #3498db;
            color: #fff;
            border-left: 4px solid #fff;
        }

        nav.menu-admin a.menu-item.active {
            background-color: #f4f7f6;
            color: #2c3e50;
            font-weight: 600;
            border-left: 4px solid #3498db;
        }

        .contenido-admin {
            flex-grow: 1;
            height: 100vh; /* Ocupa el 100% de la altura de la vista */
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Evita doble scrollbar */
        }

        header {
            background-color: #34495e;
            color: white;
            padding: 12px 20px;
            font-size: 18px;
            font-weight: 500;
            flex-shrink: 0; /* El header no se encoge */
        }

        /* --- ARREGLO 2: CSS para IFRAMES MÚLTIPLES --- */
        .iframe-container {
            flex-grow: 1; /* Ocupa todo el espacio restante */
            position: relative; /* Contenedor para los iframes */
        }

        iframe.content-frame {
            width: 100%;
            height: 100%;
            border: none;
            
            /* Posición absoluta para apilarlos */
            position: absolute;
            top: 0;
            left: 0;
            
            /* Ocultos por defecto */
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        /* El iframe activo SÍ se muestra */
        iframe.content-frame.active {
            visibility: visible;
            opacity: 1;
        }
        /* --- FIN ARREGLO 2 --- */
    </style>
</head>
<body>

    <nav class="menu-admin" id="menuAdmin">
        <a class="menu-item active" data-target="frame-descuentos"><i class="bi bi-percent"></i> Descuentos</a>
        <a class="menu-item" data-target="frame-reportes"><i class="bi bi-graph-up"></i> Reportes</a>
        <a class="menu-item" data-target="frame-categorias"><i class="bi bi-tags"></i> Categorías</a>
    </nav>

    <div class="contenido-admin">
        <header>Panel de Administración</header>
        
        <div class="iframe-container">
            <iframe id="frame-descuentos" class="content-frame active" src="dsc_boletos/index.php"></iframe>
            <iframe id="frame-reportes" class="content-frame" src="rpt_reportes/index.php"></iframe>
            <iframe id="frame-categorias" class="content-frame" src="ctg_boletos/index.php"></iframe>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuItems = document.querySelectorAll('.menu-item');
            
            // Seleccionamos TODOS los iframes
            const iframes = document.querySelectorAll('.content-frame');

            menuItems.forEach(item => {
                // --- ARREGLO 1: Capturamos el 'event' (e) ---
                item.addEventListener('click', function(e) {
                    
                    // --- ARREGLO 1 (Continuación): Evitamos el refresco ---
                    e.preventDefault(); 
                    
                    // --- ARREGLO 2: Lógica de mostrar/ocultar ---
                    
                    // Obtenemos el ID del iframe al que se le hizo clic (ej: "frame-descuentos")
                    const targetId = this.dataset.target;

                    // 1. Ocultamos TODOS los iframes
                    iframes.forEach(frame => {
                        frame.classList.remove('active');
                    });
                    
                    // 2. Quitamos 'active' de TODOS los botones del menú
                    menuItems.forEach(i => i.classList.remove('active'));

                    // 3. Mostramos el iframe correcto
                    const targetFrame = document.getElementById(targetId);
                    if (targetFrame) {
                        targetFrame.classList.add('active');
                    }
                    
                    // 4. Marcamos el botón del menú como activo
                    this.classList.add('active');
                });
            });
        });
    </script>

</body>
</html>