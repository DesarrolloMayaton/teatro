<?php
/**
 * Panel de Sincronización y Respaldos
 * ====================================
 * Monitorea el estado de las 3 bases de datos:
 * - trt_25 (Local)
 * - trt_25_online (Online)
 * - trt_25_backup (Respaldo)
 * Muestra log de sincronización, integridad y estado.
 */

session_start();
require_once __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function chequeoBd($host, $user, $pass, $db) {
    $c = @new mysqli($host, $user, $pass, $db);
    if ($c->connect_error) return ['ok' => false, 'error' => $c->connect_error];
    $info = ['ok' => true];
    foreach (['evento','funciones','boletos','categorias'] as $t) {
        $r = @$c->query("SELECT COUNT(*) as n FROM $t");
        if ($r) { $row = $r->fetch_assoc(); $info[$t] = (int)$row['n']; }
        else $info[$t] = 0;
    }
    $c->close();
    return $info;
}
function chequeoBackup() {
    $c = @new mysqli('localhost', 'root', '', 'trt_25_backup');
    if ($c->connect_error) return ['ok' => false, 'error' => $c->connect_error];
    $info = ['ok' => true];
    foreach (['evento_backup','boleto_backup','venta_detallada','sync_log','proteccion_borrado'] as $t) {
        $r = @$c->query("SELECT COUNT(*) as n FROM $t");
        if ($r) { $row = $r->fetch_assoc(); $info[$t] = (int)$row['n']; }
        else $info[$t] = 0;
    }
    $c->close();
    return $info;
}

$bd_local   = chequeoBd('localhost', 'root', '', 'trt_25');
$bd_online  = chequeoBd('localhost', 'root', '', 'trt_25_online');
$bd_backup  = chequeoBackup();

// Log reciente
$log_recientes = [];
$conn_b = @new mysqli('localhost', 'root', '', 'trt_25_backup');
if (!$conn_b->connect_error) {
    $r = @$conn_b->query("SELECT * FROM sync_log ORDER BY fecha DESC LIMIT 50");
    if ($r) while ($row = $r->fetch_assoc()) $log_recientes[] = $row;
    $conn_b->close();
}

// Detectar inconsistencias entre local y online
$inconsistencias = [];
if ($bd_local['ok'] && $bd_online['ok']) {
    if (abs($bd_local['evento'] - $bd_online['evento']) > 0) {
        $inconsistencias[] = "Eventos desincronizados: local=$bd_local[evento], online=$bd_online[evento]";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel de Sincronización</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    body { background:#f5f7fa; font-family:'Inter',system-ui,sans-serif; padding:20px; }
    .header-card { background:linear-gradient(135deg,#10b981,#0891b2); color:#fff; border-radius:16px; padding:24px; margin-bottom:24px; }
    .bd-card { background:#fff; border-radius:16px; padding:24px; box-shadow:0 4px 12px rgba(0,0,0,0.08); height:100%; transition:transform 0.2s; }
    .bd-card:hover { transform:translateY(-4px); }
    .bd-card.ok { border-top:5px solid #10b981; }
    .bd-card.error { border-top:5px solid #ef4444; }
    .bd-card h4 { color:#0f172a; }
    .bd-stat { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #e2e8f0; }
    .bd-stat:last-child { border:none; }
    .bd-stat strong { font-size:1.2rem; color:#1561f0; }
    .status-badge { display:inline-block; padding:4px 12px; border-radius:999px; font-size:0.75rem; font-weight:700; }
    .status-ok { background:#d1fae5; color:#065f46; }
    .status-error { background:#fee2e2; color:#991b1b; }
    .accion-badge { padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:700; text-transform:uppercase; }
    .accion-insert { background:#d1fae5; color:#065f46; }
    .accion-update { background:#dbeafe; color:#1e3a8a; }
    .accion-delete { background:#fee2e2; color:#991b1b; }
    .accion-error  { background:#fef3c7; color:#92400e; }
    .accion-skip   { background:#e5e7eb; color:#374151; }
</style>
</head>
<body>

<div class="container-fluid" style="max-width:1500px;margin:0 auto;">

    <div class="header-card d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h2 class="m-0"><i class="bi bi-arrow-repeat"></i> Panel de Sincronización</h2>
            <p class="m-0 mt-1 opacity-75">Estado de bases de datos LOCAL · ONLINE · BACKUP</p>
        </div>
        <div class="d-flex gap-2">
            <a href="../index.php" class="btn btn-light fw-bold"><i class="bi bi-arrow-left"></i> Volver</a>
            <a href="registro_ventas.php" class="btn btn-light fw-bold"><i class="bi bi-clipboard-data"></i> Ventas</a>
            <button class="btn btn-success fw-bold" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise"></i> Actualizar
            </button>
        </div>
    </div>

    <!-- ESTADO DE LAS 3 BASES DE DATOS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="bd-card <?= $bd_local['ok'] ? 'ok' : 'error' ?>">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="m-0"><i class="bi bi-hdd"></i> Local</h4>
                    <span class="status-badge <?= $bd_local['ok'] ? 'status-ok' : 'status-error' ?>">
                        <?= $bd_local['ok'] ? '✓ Conectada' : '✗ Error' ?>
                    </span>
                </div>
                <small class="text-muted d-block mb-3">trt_25 · Sistema de venta en taquilla</small>
                <?php if ($bd_local['ok']): ?>
                    <div class="bd-stat"><span>📅 Eventos</span><strong><?= $bd_local['evento'] ?></strong></div>
                    <div class="bd-stat"><span>🎬 Funciones</span><strong><?= $bd_local['funciones'] ?></strong></div>
                    <div class="bd-stat"><span>🎫 Boletos</span><strong><?= $bd_local['boletos'] ?></strong></div>
                    <div class="bd-stat"><span>🏷️ Categorías</span><strong><?= $bd_local['categorias'] ?></strong></div>
                <?php else: ?>
                    <div class="alert alert-danger small m-0"><?= htmlspecialchars($bd_local['error']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="bd-card <?= $bd_online['ok'] ? 'ok' : 'error' ?>">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="m-0"><i class="bi bi-globe"></i> Online</h4>
                    <span class="status-badge <?= $bd_online['ok'] ? 'status-ok' : 'status-error' ?>">
                        <?= $bd_online['ok'] ? '✓ Conectada' : '✗ Error' ?>
                    </span>
                </div>
                <small class="text-muted d-block mb-3">trt_25_online · Compras desde internet</small>
                <?php if ($bd_online['ok']): ?>
                    <div class="bd-stat"><span>📅 Eventos</span><strong><?= $bd_online['evento'] ?></strong></div>
                    <div class="bd-stat"><span>🎬 Funciones</span><strong><?= $bd_online['funciones'] ?></strong></div>
                    <div class="bd-stat"><span>🎫 Boletos</span><strong><?= $bd_online['boletos'] ?></strong></div>
                    <div class="bd-stat"><span>🏷️ Categorías</span><strong><?= $bd_online['categorias'] ?></strong></div>
                <?php else: ?>
                    <div class="alert alert-danger small m-0"><?= htmlspecialchars($bd_online['error']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="bd-card <?= $bd_backup['ok'] ? 'ok' : 'error' ?>">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="m-0"><i class="bi bi-shield-check"></i> Respaldo</h4>
                    <span class="status-badge <?= $bd_backup['ok'] ? 'status-ok' : 'status-error' ?>">
                        <?= $bd_backup['ok'] ? '✓ Conectada' : '✗ Error' ?>
                    </span>
                </div>
                <small class="text-muted d-block mb-3">trt_25_backup · Inmutable · Auditoría</small>
                <?php if ($bd_backup['ok']): ?>
                    <div class="bd-stat"><span>📅 Eventos respaldados</span><strong><?= $bd_backup['evento_backup'] ?></strong></div>
                    <div class="bd-stat"><span>🎫 Boletos respaldados</span><strong><?= $bd_backup['boleto_backup'] ?></strong></div>
                    <div class="bd-stat"><span>💰 Ventas detalladas</span><strong><?= $bd_backup['venta_detallada'] ?></strong></div>
                    <div class="bd-stat"><span>🔒 Protecciones</span><strong><?= $bd_backup['proteccion_borrado'] ?></strong></div>
                <?php else: ?>
                    <div class="alert alert-danger small m-0">
                        <?= htmlspecialchars($bd_backup['error']) ?><br>
                        <strong>Ejecuta:</strong> <code>sql/create_backup_db.sql</code>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($inconsistencias): ?>
    <div class="alert alert-warning">
        <h6 class="fw-bold"><i class="bi bi-exclamation-triangle"></i> Posibles inconsistencias detectadas:</h6>
        <ul class="m-0">
            <?php foreach ($inconsistencias as $i): ?>
            <li><?= htmlspecialchars($i) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- LOG DE SINCRONIZACIÓN -->
    <div class="bd-card">
        <h5 class="mb-3"><i class="bi bi-list-ul"></i> Log de Sincronización (últimas 50)</h5>
        <?php if (!$log_recientes): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox" style="font-size:3rem;opacity:0.3;"></i>
                <p class="mt-2">Sin actividad de sincronización todavía.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Dirección</th>
                        <th>Tipo</th>
                        <th>ID Origen</th>
                        <th>Acción</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log_recientes as $l): ?>
                    <tr>
                        <td><small><?= date('d/m/Y H:i:s', strtotime($l['fecha'])) ?></small></td>
                        <td><small><code><?= htmlspecialchars($l['direccion']) ?></code></small></td>
                        <td><small><strong><?= htmlspecialchars($l['tipo']) ?></strong></small></td>
                        <td><small><?= $l['id_origen'] ?: '—' ?></small></td>
                        <td><span class="accion-badge accion-<?= $l['accion'] ?>"><?= strtoupper($l['accion']) ?></span></td>
                        <td><small class="text-muted"><?= htmlspecialchars($l['mensaje']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
