<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['usuario_rol'] !== 'admin') {
    if (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']) {
        die('Acceso denegado');
    }
}

require_once '../../transacciones_helper.php';

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$transacciones = [];

$sql_base = "SELECT t.id_transaccion, t.accion, t.descripcion, t.fecha_hora, u.nombre, u.apellido
             FROM transacciones t
             JOIN usuarios u ON t.id_usuario = u.id_usuario";

if ($fecha_desde && $fecha_hasta) {
    $sql = $sql_base . " WHERE t.fecha_hora >= ? AND t.fecha_hora <= ? ORDER BY t.fecha_hora DESC LIMIT 500";
    $stmt = $conn->prepare($sql);
    $desde = $fecha_desde . ' 00:00:00';
    $hasta = $fecha_hasta . ' 23:59:59';
    $stmt->bind_param('ss', $desde, $hasta);
} elseif ($fecha_desde) {
    $sql = $sql_base . " WHERE t.fecha_hora >= ? ORDER BY t.fecha_hora DESC LIMIT 500";
    $stmt = $conn->prepare($sql);
    $desde = $fecha_desde . ' 00:00:00';
    $stmt->bind_param('s', $desde);
} elseif ($fecha_hasta) {
    $sql = $sql_base . " WHERE t.fecha_hora <= ? ORDER BY t.fecha_hora DESC LIMIT 500";
    $stmt = $conn->prepare($sql);
    $hasta = $fecha_hasta . ' 23:59:59';
    $stmt->bind_param('s', $hasta);
} else {
    $sql = $sql_base . " ORDER BY t.fecha_hora DESC LIMIT 200";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $transacciones[] = $row;
}

$stmt->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transacciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius-lg: 16px;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--bg-primary), #e2e8f0);
            color: var(--text-primary);
            padding: 30px;
            min-height: 100vh;
        }
        .container-fluid {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: var(--shadow-lg);
        }
        h2, h3 {
            color: var(--text-primary);
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .table thead {
            background: var(--bg-primary);
        }
        .table th {
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        .badge-accion {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .descripcion {
            max-width: 320px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .filtros-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
            <div>
                <h2 class="m-0 d-flex align-items-center"><i class="bi bi-clock-history me-3"></i>Transacciones de Usuarios</h2>
                <p class="text-secondary mb-0">Historial de acciones realizadas por los usuarios logeados.</p>
            </div>
            <div class="text-end">
                <span class="text-secondary small">Registros mostrados:</span>
                <div class="fs-4 fw-bold"><?php echo count($transacciones); ?></div>
            </div>
        </div>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="fecha_desde" class="filtros-label">Desde</label>
                <input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde); ?>">
            </div>
            <div class="col-md-4">
                <label for="fecha_hasta" class="filtros-label">Hasta</label>
                <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
            </div>
        </form>
    </div>

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($transacciones)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-4">No hay transacciones para el criterio seleccionado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transacciones as $t): ?>
                        <tr>
                            <td class="text-nowrap"><?php echo date('d/m/Y H:i:s', strtotime($t['fecha_hora'])); ?></td>
                            <td><?php echo htmlspecialchars($t['nombre'] . ' ' . $t['apellido']); ?></td>
                            <td><span class="badge-accion"><?php echo htmlspecialchars($t['accion']); ?></span></td>
                            <td class="descripcion" title="<?php echo htmlspecialchars($t['descripcion'] ?? ''); ?>"><?php echo htmlspecialchars($t['descripcion'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
