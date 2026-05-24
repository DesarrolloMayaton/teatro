<?php
/**
 * Registro Detallado de Ventas
 * =============================
 * Lista todas las ventas con metadatos completos:
 * fecha, hora, lugar, método de pago, tarjeta, cuenta, email, etc.
 * Se obtiene desde la base de datos de respaldo (trt_25_backup).
 */

session_start();
require_once __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$conn_b = @new mysqli('localhost', 'root', '', 'trt_25_backup');
$bd_disponible = !$conn_b->connect_error;
if ($bd_disponible) $conn_b->set_charset('utf8mb4');

// Filtros
$filtro_origen   = $_GET['origen']      ?? '';
$filtro_metodo   = $_GET['metodo']      ?? '';
$filtro_lugar    = $_GET['lugar']       ?? '';
$filtro_desde    = $_GET['desde']       ?? '';
$filtro_hasta    = $_GET['hasta']       ?? '';
$filtro_email    = $_GET['email']       ?? '';

$ventas = [];
$totales = ['cantidad' => 0, 'monto' => 0, 'boletos' => 0];

if ($bd_disponible) {
    $where = ['1=1'];
    $params = [];
    $types = '';

    if ($filtro_origen)   { $where[] = 'origen = ?';        $params[] = $filtro_origen; $types .= 's'; }
    if ($filtro_metodo)   { $where[] = 'metodo_pago = ?';   $params[] = $filtro_metodo; $types .= 's'; }
    if ($filtro_lugar)    { $where[] = 'lugar_venta = ?';   $params[] = $filtro_lugar;  $types .= 's'; }
    if ($filtro_desde)    { $where[] = 'fecha_venta >= ?';  $params[] = $filtro_desde . ' 00:00:00'; $types .= 's'; }
    if ($filtro_hasta)    { $where[] = 'fecha_venta <= ?';  $params[] = $filtro_hasta . ' 23:59:59'; $types .= 's'; }
    if ($filtro_email)    { $where[] = 'cliente_email LIKE ?'; $params[] = "%$filtro_email%"; $types .= 's'; }

    $sql = "SELECT * FROM venta_detallada WHERE " . implode(' AND ', $where) . " ORDER BY fecha_venta DESC LIMIT 500";
    $stmt = $conn_b->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $ventas[] = $r;
        $totales['cantidad']++;
        $totales['monto']   += (float)$r['total'];
        $totales['boletos'] += (int)$r['cantidad_boletos'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro Detallado de Ventas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    body { background:#f5f7fa; font-family:'Inter',system-ui,sans-serif; padding:20px; }
    .header-card { background: linear-gradient(135deg,#1561f0,#a855f7); color:#fff; border-radius:16px; padding:24px; margin-bottom:24px; }
    .stat-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid #1561f0; }
    .stat-card .num { font-size:2rem; font-weight:800; color:#1561f0; }
    .stat-card .lbl { color:#666; font-size:0.85rem; text-transform:uppercase; }
    .filtros-card { background:#fff; border-radius:12px; padding:20px; margin-bottom:24px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .table-card { background:#fff; border-radius:12px; padding:0; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .badge-origen-online { background:#a855f7; }
    .badge-origen-local  { background:#1561f0; }
    .badge-metodo-efectivo { background:#10b981; }
    .badge-metodo-tarjeta  { background:#f59e0b; }
    .badge-metodo-transferencia { background:#06b6d4; }
    .badge-metodo-online   { background:#a855f7; }
    .badge-metodo-cortesia { background:#64748b; }
    .badge-metodo-otro     { background:#9ca3af; }
    .badge-metodo-cuenta   { background:#06b6d4; }
    table th { background:#f1f5f9; font-size:0.85rem; text-transform:uppercase; color:#475569; }
    table td { font-size:0.9rem; vertical-align: middle; }
    .btn-export { background:#10b981; color:#fff; border:none; padding:10px 20px; border-radius:8px; font-weight:600; }
    .btn-export:hover { background:#059669; color:#fff; }
</style>
</head>
<body>

<div class="container-fluid" style="max-width:1500px;margin:0 auto;">

    <!-- HEADER -->
    <div class="header-card d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h2 class="m-0"><i class="bi bi-clipboard-data"></i> Registro Detallado de Ventas</h2>
            <p class="m-0 mt-1 opacity-75">Trazabilidad completa de cada venta · Lugar · Método pago · Cliente · Boletos</p>
        </div>
        <div class="d-flex gap-2">
            <a href="../index.php" class="btn btn-light fw-bold">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <button class="btn-export" onclick="exportarCSV()">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <?php if (!$bd_disponible): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>BD de Respaldo no disponible.</strong>
            Crea la BD ejecutando <code>sql/create_backup_db.sql</code>.
        </div>
    <?php else: ?>

    <!-- ESTADÍSTICAS -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="lbl">Total Ventas</div>
                <div class="num"><?= number_format($totales['cantidad']) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="border-left-color:#10b981;">
                <div class="lbl">Boletos Vendidos</div>
                <div class="num" style="color:#10b981;"><?= number_format($totales['boletos']) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="border-left-color:#a855f7;">
                <div class="lbl">Monto Total</div>
                <div class="num" style="color:#a855f7;">$<?= number_format($totales['monto'], 2) ?></div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filtros-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-funnel"></i> Filtros</h6>
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label small fw-bold">Origen</label>
                <select name="origen" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="local"  <?= $filtro_origen=='local'?'selected':'' ?>>Taquilla (Local)</option>
                    <option value="online" <?= $filtro_origen=='online'?'selected':'' ?>>Online</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Método Pago</label>
                <select name="metodo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="efectivo"      <?= $filtro_metodo=='efectivo'?'selected':'' ?>>Efectivo</option>
                    <option value="tarjeta"       <?= $filtro_metodo=='tarjeta'?'selected':'' ?>>Tarjeta</option>
                    <option value="transferencia" <?= $filtro_metodo=='transferencia'?'selected':'' ?>>Transferencia</option>
                    <option value="online"        <?= $filtro_metodo=='online'?'selected':'' ?>>Online</option>
                    <option value="cortesia"      <?= $filtro_metodo=='cortesia'?'selected':'' ?>>Cortesía</option>
                    <option value="otro"          <?= $filtro_metodo=='otro'?'selected':'' ?>>Otro</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Lugar</label>
                <input type="text" name="lugar" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_lugar) ?>" placeholder="Taquilla, Online…">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_hasta) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Email Cliente</label>
                <input type="text" name="email" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_email) ?>" placeholder="@correo">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i> Aplicar
                </button>
                <a href="registro_ventas.php" class="btn btn-secondary btn-sm">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- TABLA -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover m-0" id="tablaVentas">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Origen</th>
                        <th>Evento</th>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Lugar</th>
                        <th>Vendedor</th>
                        <th>Método</th>
                        <th>Tarjeta</th>
                        <th>Referencia</th>
                        <th>Cant.</th>
                        <th>Total</th>
                        <th>Asientos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$ventas): ?>
                    <tr><td colspan="13" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:3rem;opacity:0.4;"></i><br>
                        No hay ventas registradas con esos filtros.
                    </td></tr>
                    <?php else: foreach ($ventas as $v): ?>
                    <tr>
                        <td><small><?= date('d/m/Y H:i', strtotime($v['fecha_venta'])) ?></small></td>
                        <td>
                            <span class="badge badge-origen-<?= $v['origen'] ?>"><?= strtoupper($v['origen']) ?></span>
                        </td>
                        <td><small><strong><?= htmlspecialchars($v['titulo_evento'] ?? '—') ?></strong>
                            <?php if ($v['fecha_funcion']): ?><br><small class="text-muted"><?= date('d/m H:i', strtotime($v['fecha_funcion'])) ?></small><?php endif; ?></small></td>
                        <td><small><?= htmlspecialchars($v['cliente_nombre']) ?></small></td>
                        <td><small><?= htmlspecialchars($v['cliente_email'] ?? '—') ?></small></td>
                        <td><small><?= htmlspecialchars($v['lugar_venta']) ?></small></td>
                        <td><small><?= htmlspecialchars($v['nombre_vendedor'] ?? '—') ?></small></td>
                        <td><span class="badge badge-metodo-<?= $v['metodo_pago'] ?>"><?= strtoupper($v['metodo_pago']) ?></span></td>
                        <td><small><?= $v['tarjeta_terminacion'] ? '****'.htmlspecialchars($v['tarjeta_terminacion']) : '—' ?></small></td>
                        <td><small><?= htmlspecialchars($v['referencia_pago'] ?? '—') ?></small></td>
                        <td class="text-center"><strong><?= $v['cantidad_boletos'] ?></strong></td>
                        <td><strong style="color:#10b981;">$<?= number_format($v['total'], 2) ?></strong></td>
                        <td><small><?= htmlspecialchars(implode(', ', json_decode($v['asientos'], true) ?: [])) ?></small></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
function exportarCSV() {
    const filas = document.querySelectorAll('#tablaVentas tr');
    let csv = '';
    filas.forEach(fila => {
        const celdas = fila.querySelectorAll('th, td');
        const cols = Array.from(celdas).map(c => '"' + c.innerText.replace(/"/g, '""').replace(/\n/g, ' ') + '"');
        csv += cols.join(',') + '\n';
    });
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ventas_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>

</body>
</html>
