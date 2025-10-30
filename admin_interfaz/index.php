<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Esto hace que las rutas relativas apunten a /teatro/admin_interfaz/ -->

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

        nav.menu-admin a.menu-item:hover {
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

        .bienvenida {
            padding: 30px;
            color: #2c3e50;
            font-size: 16px;
            line-height: 1.4;
        }
    </style>
</head>
<body>

    <nav class="menu-admin" id="menuAdmin">
        <!-- Cada botón ahora es un link REAL -->
        <a class="menu-item" href="dsc_boletos/index.php">
            <i class="bi bi-percent"></i> Descuentos
        </a>

        <a class="menu-item" href="rpt_reportes/index.php">
            <i class="bi bi-graph-up"></i> Reportes
        </a>

        <a class="menu-item" href="ctg_boletos/index.php">
            <i class="bi bi-tags"></i> Categorías
        </a>
    </nav>

    <div class="contenido-admin">
        <header>Panel de Administración</header>

        <div class="bienvenida">
            <h2 style="margin-top:0;">Bienvenido al panel 👋</h2>
            <p>Selecciona una opción del menú de la izquierda para administrar descuentos, ver reportes o manejar categorías de boletos.</p>
        </div>
    </div>

</body>
</html>
