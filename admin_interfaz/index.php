<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
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
            background-color: #2c3e50;
            color: white;
            display: flex;
            flex-direction: column;
            padding-top: 10px;
            box-shadow: 4px 0 12px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
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
            border-left: 4px solid transparent;
        }

        nav.menu-admin a.menu-item i {
            font-size: 1.3em;
            min-width: 30px;
            margin-right: 12px;
        }

        nav.menu-admin a.menu-item:hover,
        nav.menu-admin a.menu-item.active { /* MODIFICADO: Estilo para el item activo */
            background-color: #3498db;
            color: #fff;
            border-left: 4px solid #fff;
        }

        header {
            background-color: #34495e;
            color: white;
            padding: 12px 20px;
            font-size: 18px;
            font-weight: 500;
            flex-shrink: 0;
            width: 100%;
        }

        .contenido-admin {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        /* MODIFICADO: Estilo para el iframe */
        iframe.content-frame {
            flex-grow: 1; /* Hace que el iframe ocupe todo el espacio restante */
            width: 100%;
            height: 100%;
            border: none;
            background-color: #f4f7f6; /* Color de fondo mientras carga */
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
    </nav>

    <div class="contenido-admin">
        <header>Panel de Administración</header>

        <iframe class="content-frame" name="contentFrame" id="contentFrame" src="inicio/inicio.php">
            Tu navegador no soporta iframes.
        </iframe>
    </div>

    <script>
        document.querySelectorAll('nav.menu-admin a.menu-item').forEach(link => {
            link.addEventListener('click', function(e) {
                // Quitar 'active' de todos los links
                document.querySelectorAll('nav.menu-admin a.menu-item').forEach(item => {
                    item.classList.remove('active');
                });
                // Añadir 'active' solo al link clickeado
                this.classList.add('active');
            });
        });

        // Activar el link de "Inicio" por defecto al cargar la página
        document.querySelector('nav.menu-admin a.menu-item[href="inicio/inicio.php"]').classList.add('active');
    </script>

</body>
</html>