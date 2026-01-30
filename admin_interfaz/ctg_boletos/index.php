<?php
// 1. CONEXIÓN
include "../../evt_interfaz/conexion.php";

$id_evento_seleccionado = null;
$nombre_evento = "";
$eventos = [];
$categorias_del_evento = [];

// Precios por tipo de boleto (globales por defecto)
$precios_tipo = [
    'general' => 0,
    'nino' => 0,
    'adulto_mayor' => 0,
    'discapacitado' => 0,
    'cortesia' => 0
];

// Variable para saber si usa precios diferenciados
$usa_precios_diferenciados = false;

// Verificar si existe la tabla de precios por tipo
$tabla_existe = false;
$check_table = $conn->query("SHOW TABLES LIKE 'precios_tipo_boleto'");
if ($check_table && $check_table->num_rows > 0) {
    $tabla_existe = true;
}

// Si no existe, crearla
if (!$tabla_existe) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS precios_tipo_boleto (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_evento INT NULL COMMENT 'NULL = precio global para todos los eventos',
            tipo_boleto VARCHAR(50) NOT NULL,
            precio DECIMAL(10,2) NOT NULL DEFAULT 0,
            activo TINYINT(1) DEFAULT 1,
            usa_diferenciados TINYINT(1) DEFAULT 0 COMMENT '1 = usa precios por tipo, 0 = solo precio general',
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_evento_tipo (id_evento, tipo_boleto)
        )
    ");

    // Insertar precios globales por defecto
    $tipos = ['general', 'nino', 'adulto_mayor', 'discapacitado', 'cortesia'];
    $precios_default = [80, 50, 60, 40, 0];

    foreach ($tipos as $i => $tipo) {
        $precio = $precios_default[$i];
        $conn->query("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio) VALUES (NULL, '$tipo', $precio)");
    }
} else {
    // Verificar si la columna usa_diferenciados existe
    $check_col = $conn->query("SHOW COLUMNS FROM precios_tipo_boleto LIKE 'usa_diferenciados'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE precios_tipo_boleto ADD COLUMN usa_diferenciados TINYINT(1) DEFAULT 0");
    }
    
    // Verificar si existe el tipo 'general' (migrar de 'adulto' si no existe)
    $check_general = $conn->query("SELECT id FROM precios_tipo_boleto WHERE tipo_boleto = 'general' AND id_evento IS NULL LIMIT 1");
    if ($check_general && $check_general->num_rows == 0) {
        // Copiar precio de adulto a general
        $res_adulto = $conn->query("SELECT precio FROM precios_tipo_boleto WHERE tipo_boleto = 'adulto' AND id_evento IS NULL LIMIT 1");
        $precio_adulto = 80;
        if ($res_adulto && $row = $res_adulto->fetch_assoc()) {
            $precio_adulto = $row['precio'];
        }
        $conn->query("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio) VALUES (NULL, 'general', $precio_adulto)");
    }
}

// Cargar precios globales actuales
$res_precios = $conn->query("SELECT tipo_boleto, precio, usa_diferenciados FROM precios_tipo_boleto WHERE id_evento IS NULL");
if ($res_precios) {
    while ($row = $res_precios->fetch_assoc()) {
        $precios_tipo[$row['tipo_boleto']] = $row['precio'];
        if ($row['usa_diferenciados']) {
            $usa_precios_diferenciados = true;
        }
    }
}

// 2. Cargar todos los eventos para el dropdown
$res_eventos = $conn->query("SELECT id_evento, titulo FROM evento WHERE finalizado = 0 ORDER BY titulo ASC");
if ($res_eventos) {
    while ($row = $res_eventos->fetch_assoc()) {
        $eventos[] = $row;
    }
}

// 3. Verificar si se seleccionó un evento
$tiene_precios_propios = false;
if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    $id_evento_seleccionado = (int) $_GET['id_evento'];

    // 4. Cargar las categorías SÓLO para ESE evento (excluyendo los tipos estándar que se editan arriba)
    $tipos_excluidos = ['General', 'Discapacitado', 'Niño', 'Nino', '3ra Edad', 'Adulto Mayor', 'Cortesía', 'Cortesia', 'Adulto', 'No Venta', 'NoVenta', 'No venta'];
    $placeholders = implode(',', array_fill(0, count($tipos_excluidos), '?'));
    $sql = "SELECT * FROM categorias WHERE id_evento = ? AND nombre_categoria NOT IN ($placeholders) ORDER BY precio ASC";
    $stmt = $conn->prepare($sql);
    
    // Bind parameters: primero id_evento (i), luego todos los tipos excluidos (s)
    $types = 'i' . str_repeat('s', count($tipos_excluidos));
    $params = array_merge([$id_evento_seleccionado], $tipos_excluidos);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res_categorias = $stmt->get_result();
    if ($res_categorias) {
        while ($row = $res_categorias->fetch_assoc()) {
            $categorias_del_evento[] = $row;
        }
    }
    $stmt->close();

    // Cargar precios específicos del evento (si existen)
    $stmt2 = $conn->prepare("SELECT tipo_boleto, precio, usa_diferenciados FROM precios_tipo_boleto WHERE id_evento = ?");
    $stmt2->bind_param("i", $id_evento_seleccionado);
    $stmt2->execute();
    $res_precios_evento = $stmt2->get_result();
    if ($res_precios_evento && $res_precios_evento->num_rows > 0) {
        $tiene_precios_propios = true;
        $usa_precios_diferenciados = false; // Reset
        while ($row = $res_precios_evento->fetch_assoc()) {
            $precios_tipo[$row['tipo_boleto']] = $row['precio'];
            if ($row['usa_diferenciados']) {
                $usa_precios_diferenciados = true;
            }
        }
    }
    $stmt2->close();

    // Obtener el nombre del evento para el título
    foreach ($eventos as $evento) {
        if ($evento['id_evento'] == $id_evento_seleccionado) {
            $nombre_evento = $evento['titulo'];
            break;
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Precios y Categorías</title>
    <link rel="icon" href="../../crt_interfaz/imagenes_teatro/nat.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #1561f0;
            --primary-dark: #0d4fc4;
            --success: #32d74b;
            --danger: #ff453a;
            --warning: #ff9f0a;
            --info: #64d2ff;
            --bg-main: #131313;
            --bg-card: #1c1c1e;
            --bg-input: #2b2b2b;
            --text-primary: #ffffff;
            --text-secondary: #86868b;
            --border: #3a3a3c;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 20px;
        }

        .container-main {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }

        .card-title i {
            font-size: 1.2rem;
            color: var(--primary);
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .form-control,
        .form-select {
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            font-size: 0.9rem;
        }

        .form-control:focus,
        .form-select:focus {
            background: var(--bg-input);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            color: var(--text-primary);
        }

        .btn {
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: #1e293b;
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-secondary {
            background: var(--bg-input);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-sm {
            padding: 6px 10px;
            font-size: 0.8rem;
        }

        /* Precio GENERAL card - destacado */
        .precio-general-card {
            background: linear-gradient(135deg, var(--primary), #6366f1);
            border-radius: var(--radius-lg);
            padding: 24px;
            text-align: center;
            margin-bottom: 20px;
        }

        .precio-general-card .icon {
            font-size: 3rem;
            margin-bottom: 12px;
        }

        .precio-general-card .titulo {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .precio-general-card .input-group {
            max-width: 200px;
            margin: 0 auto;
        }

        .precio-general-card .input-precio {
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
        }

        .precio-general-card .input-precio:focus {
            background: rgba(255,255,255,0.2);
            border-color: white;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
        }

        .precio-general-card .input-group-text {
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
        }

        /* Switch para precios diferenciados */
        .switch-container {
            background: var(--bg-input);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 20px 0;
            border: 1px solid var(--border);
        }

        .switch-label {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .switch-label i {
            font-size: 1.3rem;
            color: var(--info);
        }

        .switch-text {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .switch-desc {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .form-switch .form-check-input {
            width: 50px;
            height: 26px;
            cursor: pointer;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--success);
            border-color: var(--success);
        }

        /* Tipo de boleto cards */
        .tipos-container {
            display: none;
            animation: slideDown 0.3s ease;
        }

        .tipos-container.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tipo-boleto-card {
            background: var(--bg-input);
            border-radius: var(--radius-md);
            padding: 16px;
            text-align: center;
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .tipo-boleto-card:hover {
            border-color: var(--primary);
        }

        .tipo-boleto-card .icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }

        .tipo-boleto-card .nombre {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: #e2e8f0;
        }

        .tipo-boleto-card .input-precio {
            text-align: center;
            font-size: 1.2rem;
            font-weight: 700;
            background: var(--bg-card);
        }

        .tipo-boleto-card.disabled {
            opacity: 0.5;
        }

        .event-selector {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-input));
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .event-selector select {
            max-width: 450px;
            margin: 0 auto;
            font-size: 1rem;
            font-weight: 600;
            padding: 12px 16px;
        }

        .table {
            color: var(--text-primary);
            background: var(--bg-card);
            --bs-table-bg: var(--bg-card);
            --bs-table-color: var(--text-primary);
            --bs-table-border-color: var(--border);
            --bs-table-striped-bg: var(--bg-input);
            --bs-table-hover-bg: var(--bg-input);
        }

        .table th {
            background: var(--bg-input);
            border-color: var(--border);
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .table td {
            background: var(--bg-card);
            border-color: var(--border);
            vertical-align: middle;
            color: var(--text-primary);
        }

        .table-responsive {
            background: var(--bg-card);
            border-radius: var(--radius-md);
        }

        .color-dot {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: inline-block;
            border: 2px solid var(--border);
        }

        .badge-global {
            background: var(--success);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-especifico {
            background: var(--info);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Navegación de regreso */
        .nav-back {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 100;
        }

        .nav-back a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .nav-back a:hover {
            background: var(--bg-input);
            color: var(--text-primary);
            transform: translateY(-2px);
        }
    </style>
</head>

<body>

    <div class="container-main">

        <div class="page-header">
            <h1><i class="bi bi-currency-dollar"></i> Precios y Categorías</h1>
            <p>Define el precio general y precios especiales por tipo de persona</p>
        </div>

        <!-- Selector de Evento -->
        <div class="event-selector">
            <form method="GET" action="">
                <select name="id_evento" class="form-select" onchange="this.form.submit()">
                    <option value="" <?= ($id_evento_seleccionado == null) ? 'selected' : '' ?>>
                        🌐 Precios Globales (todos los eventos)
                    </option>
                    <?php foreach ($eventos as $evento): ?>
                        <option value="<?= $evento['id_evento'] ?>" <?= ($id_evento_seleccionado == $evento['id_evento']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($evento['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Formulario de Precios -->
        <form id="form-precios-tipo" method="POST" action="action_precios_tipo.php">
            <input type="hidden" name="id_evento" value="<?= $id_evento_seleccionado ?? '' ?>">

            <!-- Precio GENERAL destacado -->
            <div class="precio-general-card">
                <div class="icon">💰</div>
                <div class="titulo">
                    PRECIO GENERAL
                    <?php if (!$id_evento_seleccionado): ?>
                        <span class="badge-global ms-2">GLOBAL</span>
                    <?php else: ?>
                        <span class="badge-especifico ms-2"><?= htmlspecialchars($nombre_evento) ?></span>
                    <?php endif; ?>
                </div>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="precio_general" id="precio_general" class="form-control input-precio"
                        value="<?= $precios_tipo['general'] ?? 0 ?>" min="0" step="0.01">
                </div>
                <small class="d-block mt-2 opacity-75">Este precio aplica para boletos de adultos</small>
            </div>

            <!-- Switch para precios diferenciados -->
            <div class="switch-container">
                <div class="switch-label">
                    <i class="bi bi-people-fill"></i>
                    <div>
                        <div class="switch-text">¿Usar precios diferenciados?</div>
                        <div class="switch-desc">Activa para definir precios especiales por Niño, 3ra Edad, Discapacitado</div>
                    </div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="switchDiferenciados" 
                        name="usa_diferenciados" value="1" <?= $usa_precios_diferenciados ? 'checked' : '' ?>>
                </div>
            </div>

            <!-- Precios por Tipo (ocultos por defecto) -->
            <div class="tipos-container <?= $usa_precios_diferenciados ? 'show' : '' ?>" id="tiposContainer">
                <div class="card">
                    <div class="card-title">
                        <i class="bi bi-people-fill"></i>
                        <span>Precios por Tipo de Persona</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="tipo-boleto-card">
                                <div class="icon">👶</div>
                                <div class="nombre">Niño</div>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-0 text-white">$</span>
                                    <input type="number" name="precio_nino" class="form-control input-precio"
                                        value="<?= $precios_tipo['nino'] ?>" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="tipo-boleto-card">
                                <div class="icon">👴</div>
                                <div class="nombre">3ra Edad</div>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-0 text-white">$</span>
                                    <input type="number" name="precio_adulto_mayor" class="form-control input-precio"
                                        value="<?= $precios_tipo['adulto_mayor'] ?>" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="tipo-boleto-card">
                                <div class="icon">♿</div>
                                <div class="nombre">Discapacitado</div>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-0 text-white">$</span>
                                    <input type="number" name="precio_discapacitado" class="form-control input-precio"
                                        value="<?= $precios_tipo['discapacitado'] ?>" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="tipo-boleto-card disabled">
                                <div class="icon">🎁</div>
                                <div class="nombre">Cortesía</div>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-0 text-white">$</span>
                                    <input type="number" class="form-control input-precio" value="0.00" disabled>
                                </div>
                                <small class="text-muted">Siempre gratis</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="d-flex gap-2 flex-wrap mt-3">
                <button type="submit" name="accion" value="guardar" class="btn btn-success flex-grow-1">
                    <i class="bi bi-check-circle"></i> Guardar Precios
                </button>
                <?php if ($id_evento_seleccionado): ?>
                    <?php if ($tiene_precios_propios): ?>
                        <button type="submit" name="accion" value="usar_global" class="btn btn-secondary">
                            <i class="bi bi-globe"></i> Usar Precios Globales
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <button type="submit" name="accion" value="aplicar_todos" class="btn btn-primary">
                        <i class="bi bi-broadcast"></i> Aplicar a Todos los Eventos
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($id_evento_seleccionado): ?>
            <!-- Categorías del Evento -->
            <div class="row g-3 mt-4">
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-title">
                            <i class="bi bi-plus-circle-fill"></i>
                            <span id="form-title-text">Nueva Categoría</span>
                        </div>
                        <p class="text-muted small mb-3">Añade zonas especiales como VIP, Palco, Preferencial, etc.</p>

                        <form id="formCRUD" action="action.php" method="POST">
                            <input type="hidden" name="id_categoria" id="id_categoria" value="">
                            <input type="hidden" name="id_evento" value="<?= $id_evento_seleccionado ?>">
                            <input type="hidden" name="accion" id="accion" value="crear">

                            <div class="mb-3">
                                <label class="form-label">Nombre Categoría</label>
                                <input type="text" name="nombre_categoria" id="nombre_categoria" class="form-control"
                                    placeholder="Ej: VIP, Preferencial, Palco" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Precio Base (MXN)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-0 text-white">$</span>
                                    <input type="number" name="precio" id="precio" class="form-control" step="0.01" min="0"
                                        placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Color</label>
                                <input type="color" name="color" id="color" class="form-control form-control-color w-100"
                                    value="#6366f1">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" id="btn-submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Guardar
                                </button>
                                <button type="button" class="btn btn-secondary d-none" id="btn-cancel"
                                    onclick="resetForm()">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-title">
                            <i class="bi bi-list-ul"></i>
                            <span>Categorías Personalizadas - "<?= htmlspecialchars($nombre_evento) ?>"</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Color</th>
                                        <th>Nombre</th>
                                        <th>Precio Base</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categorias_del_evento)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                                                No hay categorías personalizadas<br>
                                                <small>Usa el formulario para crear zonas como VIP, Palco, etc.</small>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($categorias_del_evento as $cat): ?>
                                            <tr>
                                                <td><span class="color-dot"
                                                        style="background-color: <?= htmlspecialchars($cat['color']) ?>"></span>
                                                </td>
                                                <td class="fw-bold"><?= htmlspecialchars($cat['nombre_categoria']) ?></td>
                                                <td class="text-success fw-bold">$<?= number_format($cat['precio'], 2) ?></td>
                                                <td class="text-end">
                                                    <button class="btn btn-warning btn-sm"
                                                        onclick='editCat(<?= json_encode($cat) ?>)'>
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm"
                                                        onclick="borrar(<?= $cat['id_categoria'] ?>, '<?= addslashes($cat['nombre_categoria']) ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Navegación de regreso -->


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle precios diferenciados
        document.getElementById('switchDiferenciados').addEventListener('change', function() {
            const container = document.getElementById('tiposContainer');
            if (this.checked) {
                container.classList.add('show');
            } else {
                container.classList.remove('show');
            }
        });

        function editCat(cat) {
            document.getElementById('id_categoria').value = cat.id_categoria;
            document.getElementById('nombre_categoria').value = cat.nombre_categoria;
            document.getElementById('precio').value = cat.precio;
            document.getElementById('color').value = cat.color;
            document.getElementById('accion').value = 'actualizar';
            document.getElementById('form-title-text').textContent = 'Editar Categoría';
            document.getElementById('btn-cancel').classList.remove('d-none');
            
            // Scroll al formulario
            document.getElementById('formCRUD').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function resetForm() {
            document.getElementById('formCRUD').reset();
            document.getElementById('id_categoria').value = '';
            document.getElementById('accion').value = 'crear';
            document.getElementById('form-title-text').textContent = 'Nueva Categoría';
            document.getElementById('btn-cancel').classList.add('d-none');
            document.getElementById('color').value = '#6366f1';
        }

        function borrar(id, nombre) {
            Swal.fire({
                title: `¿Eliminar "${nombre}"?`,
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `action.php?accion=borrar&id_categoria=${id}&id_evento=<?= $id_evento_seleccionado ?>`;
                }
            });
        }

        // Mostrar mensajes de éxito/error
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const msg = urlParams.get('msg');

            if (status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: msg || '¡Operación exitosa!',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            } else if (status === 'error') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: decodeURIComponent(msg) || 'Ocurrió un problema'
                });
            }

            if (status) {
                const newUrl = window.location.pathname + window.location.search.replace(/[?&]status=[^&]+|[?&]msg=[^&]+/g, '');
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    </script>

</body>

</html>