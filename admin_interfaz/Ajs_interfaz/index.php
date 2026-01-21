<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['usuario_rol'] !== 'admin') {
    if (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']) {
        die('<html><head><link rel="stylesheet" href="../../assets/css/teatro-style.css"></head>
        <body style="display:flex;justify-content:center;align-items:center;height:100vh;">
        <div style="text-align:center;color:var(--danger);"><p>Acceso denegado</p></div></body></html>');
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/teatro-style.css">
    <style>
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 8px 0;
        }

        .page-header h1 i {
            color: var(--accent-blue);
        }

        .page-header p {
            color: var(--text-muted);
            margin: 0;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            max-width: 1200px;
        }

        .settings-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px;
            text-decoration: none;
            color: inherit;
            transition: var(--transition-fast);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .settings-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(21, 97, 240, 0.15);
        }

        .settings-card-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .settings-card-icon.blue {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }

        .settings-card-icon.green {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .settings-card-icon.purple {
            background: rgba(139, 92, 246, 0.15);
            color: #8b5cf6;
        }

        .settings-card-content h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 6px 0;
        }

        .settings-card-content p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.5;
        }

        .settings-card-arrow {
            margin-left: auto;
            color: var(--text-muted);
            font-size: 1.2rem;
            transition: var(--transition-fast);
        }

        .settings-card:hover .settings-card-arrow {
            color: var(--accent-blue);
            transform: translateX(4px);
        }

        .settings-card-header {
            display: flex;
            align-items: center;
            gap: 16px;
        }
    </style>
</head>

<body>
    <div class="page-header">
        <h1><i class="bi bi-sliders"></i> Ajustes</h1>
        <p>Configura descuentos, categorías de boletos y mapeo de asientos</p>
    </div>

    <div class="settings-grid">
        <a href="../dsc_boletos/index.php" class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon green">
                    <i class="bi bi-percent"></i>
                </div>
                <div class="settings-card-content">
                    <h3>Descuentos</h3>
                    <p>Gestiona los descuentos disponibles para niños, tercera edad y cortesías</p>
                </div>
                <i class="bi bi-chevron-right settings-card-arrow"></i>
            </div>
        </a>

        <a href="../ctg_boletos/index.php" class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon blue">
                    <i class="bi bi-tags-fill"></i>
                </div>
                <div class="settings-card-content">
                    <h3>Categorías de Boletos</h3>
                    <p>Configura las categorías y precios de boletos por evento</p>
                </div>
                <i class="bi bi-chevron-right settings-card-arrow"></i>
            </div>
        </a>

        <a href="../../mp_interfaz/index.php" class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon purple">
                    <i class="bi bi-grid-3x3"></i>
                </div>
                <div class="settings-card-content">
                    <h3>Mapeo de Asientos</h3>
                    <p>Define la distribución de asientos y zonas del teatro</p>
                </div>
                <i class="bi bi-chevron-right settings-card-arrow"></i>
            </div>
        </a>
    </div>
</body>

</html>