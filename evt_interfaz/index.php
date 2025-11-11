<?php
include "../conexion.php";

// ==================================================================
// == FUNCIONES DE AYUDA ==
// ==================================================================

function limpiar_datos_asociados($id_evento, $conn) {
    // 1. BORRAR ARCHIVOS QR FISICOS
    $sql_qrs = "SELECT qr_path FROM boletos WHERE id_evento = ?";
    if ($stmt = $conn->prepare($sql_qrs)) {
        $stmt->bind_param("i", $id_evento);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['qr_path'])) {
                // Intentar borrar en varias rutas posibles por si acaso
                $rutas_posibles = [
                    __DIR__ . '/' . $row['qr_path'],
                    __DIR__ . '/../' . $row['qr_path']
                ];
                foreach($rutas_posibles as $ruta_fisica) {
                    if (file_exists($ruta_fisica)) { @unlink($ruta_fisica); break; }
                }
            }
        }
        $stmt->close();
    }

    // 2. BORRAR REGISTROS DE LA DB
    $conn->query("DELETE FROM boletos WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM categorias WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM promociones WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM funciones WHERE id_evento = $id_evento");
}

// ==================================================================
// == PROCESADOR DE ACCIONES (AJAX / POST) ==
// ==================================================================

if (isset($_POST['accion']) && $_POST['accion'] == 'finalizar') {
    $id = $_POST['id_evento'];
    limpiar_datos_asociados($id, $conn);
    if ($stmt = $conn->prepare("UPDATE evento SET finalizado=1 WHERE id_evento = ?")) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'accion' => 'finalizado']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error DB']);
    }
    exit;
}

if (isset($_POST['accion']) && $_POST['accion'] == 'borrar') {
    $id = $_POST['id_evento'];
    $usuario = $_POST['auth_user'] ?? '';
    $pin = $_POST['auth_pin'] ?? '';

    $stmt_auth = $conn->prepare("SELECT id_usuario FROM usuario WHERE nombre = ? AND pin = ?");
    $stmt_auth->bind_param("ss", $usuario, $pin);
    $stmt_auth->execute();
    if ($stmt_auth->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas.']);
        exit;
    }
    $stmt_auth->close();

    $conn->begin_transaction();
    try {
        $res_img = $conn->query("SELECT imagen FROM evento WHERE id_evento = $id");
        if ($row_img = $res_img->fetch_assoc()) {
            if (!empty($row_img['imagen'])) {
                $rutas = [
                    $row_img['imagen'],
                    '../' . $row_img['imagen'],
                    'evt_interfaz/' . $row_img['imagen']
                ];
                foreach ($rutas as $r) {
                    if (file_exists(__DIR__ . '/' . $r)) { @unlink(__DIR__ . '/' . $r); break; }
                }
            }
        }
        limpiar_datos_asociados($id, $conn);
        $conn->query("DELETE FROM evento WHERE id_evento = $id");
        $conn->commit();
        echo json_encode(['status' => 'success', 'accion' => 'borrado']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==================================================================
// == AUTOMATIZACIÓN Y CARGA ==
// ==================================================================

$sql_v = "SELECT e.id_evento FROM evento e LEFT JOIN (SELECT id_evento, MAX(fecha_hora) as ult FROM funciones GROUP BY id_evento) lf ON e.id_evento = lf.id_evento WHERE e.finalizado = 0 AND ((lf.ult IS NOT NULL AND lf.ult < NOW()) OR (lf.ult IS NULL AND e.cierre_venta < NOW()))";
$res_v = $conn->query($sql_v);
if ($res_v) { while ($r = $res_v->fetch_assoc()) { limpiar_datos_asociados($r['id_evento'], $conn); } }
$conn->query("UPDATE evento e LEFT JOIN (SELECT id_evento, MAX(fecha_hora) as ult FROM funciones GROUP BY id_evento) lf ON e.id_evento = lf.id_evento SET e.finalizado = 1 WHERE e.finalizado = 0 AND ((lf.ult IS NOT NULL AND lf.ult < NOW()) OR (lf.ult IS NULL AND e.cierre_venta < NOW()))");

$eventos_activos = $conn->query("SELECT * FROM evento WHERE finalizado=0 ORDER BY inicio_venta DESC");
$eventos_finalizados = $conn->query("SELECT * FROM evento WHERE finalizado=1 ORDER BY cierre_venta DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Dashboard de Eventos</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    :root {
        --primary-color: #2563eb; --primary-dark: #1e40af;
        --success-color: #10b981; --danger-color: #ef4444;
        --warning-color: #f59e0b; --info-color: #3b82f6;
        --bg-primary: #f8fafc; --bg-secondary: #ffffff;
        --text-primary: #0f172a; --text-secondary: #64748b;
        --border-color: #e2e8f0;
        --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
        --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
    }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        color: var(--text-primary);
        margin: 0; padding: 0; height: 100vh; display: flex;
    }
    .main-container { display: flex; width: 100%; height: 100vh; }
    .sidebar {
        width: 280px; background-color: #1e293b; padding: 24px; color: #fff; flex-shrink: 0;
        display: flex; flex-direction: column;
    }
    .sidebar .nav-link {
        color: #94a3b8; font-weight: 500; padding: 12px 16px; border-radius: var(--radius-sm);
        margin-bottom: 8px; transition: all 0.2s; display: flex; align-items: center;
    }
    .sidebar .nav-link:hover, .sidebar .nav-link.active {
        background-color: #334155; color: #fff;
    }
    .sidebar .nav-link i { margin-right: 12px; font-size: 1.25rem; }
    .btn-agregar {
        background: var(--success-color); color: white; padding: 12px; border-radius: var(--radius-sm);
        text-align: center; text-decoration: none; font-weight: 600; display: block; transition: all 0.2s;
    }
    .btn-agregar:hover { background: #059669; color: white; transform: translateY(-2px); box-shadow: var(--shadow-md); }
    
    .content { flex-grow: 1; padding: 32px; overflow-y: auto; }
    h2 { font-weight: 800; color: var(--text-primary); margin-bottom: 24px; font-size: 2rem; }
    
    .card {
        border: none; border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
        transition: all 0.3s ease; background: var(--bg-secondary); overflow: hidden; height: 100%;
    }
    .card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .card-img-top { height: 220px; object-fit: cover; }
    .card-body { padding: 20px; display: flex; flex-direction: column; }
    .card-title { font-weight: 700; color: var(--text-primary); font-size: 1.25rem; margin-bottom: 16px; }
    .card-text { color: var(--text-secondary); font-size: 0.95rem; flex-grow: 1; }
    .card-text strong { color: var(--text-primary); font-weight: 600; }
    .card-footer {
        background: transparent; border-top: 1px solid var(--border-color);
        padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;
    }
    
    .btn { border-radius: var(--radius-sm); font-weight: 600; padding: 8px 16px; transition: all 0.2s; border: none; }
    .btn-warning { background: var(--warning-color); color: white; }
    .btn-warning:hover { background: #d97706; transform: translateY(-2px); }
    .btn-success { background: var(--success-color); color: white; }
    .btn-success:hover { background: #059669; transform: translateY(-2px); }
    .btn-danger { background: var(--danger-color); color: white; }
    .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
    .btn-primary { background: var(--primary-color); color: white; }
    .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }

    .modal-content { border-radius: var(--radius-lg); border: none; box-shadow: var(--shadow-lg); }
    .modal-header { border-bottom: 1px solid var(--border-color); padding: 20px 24px; }
    .modal-footer { border-top: 1px solid var(--border-color); padding: 16px 24px; }
    .form-control {
        border-radius: var(--radius-sm); border: 1px solid var(--border-color);
        padding: 12px 16px; font-size: 1rem;
    }
    .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
</style>
</head>
<body>

<div class="main-container">
    <div class="sidebar">
        <h4 class="mb-4 fw-bold px-3" style="color: #fff;"><i class="bi bi-grid-fill me-2"></i>Dashboard</h4>
        <ul class="nav flex-column mb-auto">
            <li class="nav-item"><a href="#activos" class="nav-link active" data-bs-toggle="tab"><i class="bi bi-calendar-event"></i> Activos</a></li>
            <li class="nav-item"><a href="#historial" class="nav-link" data-bs-toggle="tab"><i class="bi bi-clock-history"></i> Historial</a></li>
        </ul>
        <div class="mt-4 pt-4 border-top border-secondary">
            <a href="crear_evento.php" class="btn-agregar"><i class="bi bi-plus-lg me-2"></i>Nuevo Evento</a>
        </div>
    </div>

    <div class="content">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="activos">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Eventos Activos</h2>
                </div>
                <div class="row g-4">
                    <?php if($eventos_activos && $eventos_activos->num_rows > 0):
                        while($e = $eventos_activos->fetch_assoc()):
                            $img = '';
                            if(!empty($e['imagen'])) {
                                $rutas = [$e['imagen'], '../'.$e['imagen'], 'evt_interfaz/'.$e['imagen']];
                                foreach($rutas as $r) { if(file_exists(__DIR__.'/'.$r)) { $img = $r; break; } }
                            }
                    ?>
                        <div class="col-xl-4 col-lg-6">
                            <div class="card h-100">
                                <?php if($img): ?><img src="<?= htmlspecialchars($img) ?>" class="card-img-top" alt="Portada">
                                <?php else: ?><div class="card-img-top bg-secondary d-flex align-items-center justify-content-center text-white"><i class="bi bi-image fs-1"></i></div><?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($e['titulo']) ?></h5>
                                    <div class="card-text">
                                        <p class="mb-2"><i class="bi bi-calendar-check me-2 text-muted"></i><strong>Inicio:</strong> <?= date('d M, Y - h:i A', strtotime($e['inicio_venta'])) ?></p>
                                        <p class="mb-3"><i class="bi bi-calendar-x me-2 text-muted"></i><strong>Cierre:</strong> <?= date('d M, Y - h:i A', strtotime($e['cierre_venta'])) ?></p>
                                        <?php
                                        $sf = $conn->prepare("SELECT fecha_hora FROM funciones WHERE id_evento = ? ORDER BY fecha_hora ASC LIMIT 3");
                                        $sf->bind_param("i", $e['id_evento']); $sf->execute(); $rf = $sf->get_result();
                                        if($rf->num_rows > 0) {
                                            echo "<small class='text-muted d-block mb-1 fw-bold'>Próximas funciones:</small><ul class='mb-0 ps-3 small text-secondary'>";
                                            while($f = $rf->fetch_assoc()) echo "<li>".date('d/m/Y H:i', strtotime($f['fecha_hora']))."</li>";
                                            echo "</ul>";
                                        }
                                        $sf->close();
                                        ?>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <a href="editar_evento.php?id=<?= $e['id_evento'] ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil-square me-1"></i>Editar</a>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-success btn-sm" onclick="prepararFinalizar(<?= $e['id_evento'] ?>, '<?= htmlspecialchars(addslashes($e['titulo'])) ?>')"><i class="bi bi-check-lg"></i></button>
                                        <button class="btn btn-danger btn-sm" onclick="prepararBorrado(<?= $e['id_evento'] ?>, '<?= htmlspecialchars(addslashes($e['titulo'])) ?>')"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="col-12"><div class="alert alert-info rounded-3 shadow-sm"><i class="bi bi-info-circle me-2"></i>No hay eventos activos en este momento.</div></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="historial">
                <h2 class="mb-4">Historial de Eventos</h2>
                <div class="row g-4">
                    <?php if($eventos_finalizados && $eventos_finalizados->num_rows > 0):
                        while($e = $eventos_finalizados->fetch_assoc()):
                            $img = '';
                            if(!empty($e['imagen'])) {
                                $rutas = [$e['imagen'], '../'.$e['imagen'], 'evt_interfaz/'.$e['imagen']];
                                foreach($rutas as $r) { if(file_exists(__DIR__.'/'.$r)) { $img = $r; break; } }
                            }
                    ?>
                        <div class="col-xl-4 col-lg-6">
                            <div class="card h-100 bg-light border">
                                <?php if($img): ?><img src="<?= htmlspecialchars($img) ?>" class="card-img-top" style="filter: grayscale(1); opacity: 0.7" alt="Portada">
                                <?php else: ?><div class="card-img-top bg-secondary d-flex align-items-center justify-content-center text-white" style="opacity:0.7"><i class="bi bi-image fs-1"></i></div><?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title text-muted"><?= htmlspecialchars($e['titulo']) ?> <span class="badge bg-secondary align-middle" style="font-size: 0.6em">FINALIZADO</span></h5>
                                    <p class="card-text text-muted small">
                                        Cerró venta el: <strong><?= date('d M, Y', strtotime($e['cierre_venta'])) ?></strong>
                                    </p>
                                </div>
                                <div class="card-footer justify-content-end gap-2">
                                    <a href="editar_evento.php?id=<?= $e['id_evento'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reactivar</a>
                                    <button class="btn btn-danger btn-sm" onclick="prepararBorrado(<?= $e['id_evento'] ?>, '<?= htmlspecialchars(addslashes($e['titulo'])) ?>')"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="col-12"><p class="text-muted">El historial está vacío.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFinalizar" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-warning-subtle"><h5 class="modal-title fw-bold text-warning-emphasis"><i class="bi bi-exclamation-triangle-fill me-2"></i>Finalizar Evento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="fs-5">¿Finalizar <strong><span id="nombreFin"></span></strong>?</p><div class="alert alert-warning d-flex align-items-center"><i class="bi bi-info-circle-fill fs-4 me-3"></i><div>Esto moverá el evento al historial y <strong>eliminará permanentemente</strong> los boletos y QRs generados.</div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="button" id="btnConfFin" class="btn btn-warning fw-bold px-4">Sí, Finalizar</button></div></div></div></div>
<div class="modal fade" id="modalBorrar" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-danger text-white"><h5 class="modal-title fw-bold"><i class="bi bi-shield-lock-fill me-2"></i>Borrado Seguro</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="text-danger fw-bold mb-3"><i class="bi bi-x-octagon-fill me-1"></i> ESTA ACCIÓN ES IRREVERSIBLE.</p><p>Para eliminar <strong><span id="nombreBorrar"></span></strong>, ingresa tus credenciales:</p><div class="form-floating mb-2"><input type="text" id="auth_user" class="form-control" placeholder="U"><label>Usuario</label></div><div class="form-floating mb-3"><input type="password" id="auth_pin" class="form-control" placeholder="P"><label>PIN</label></div><div id="borrarMsg" class="text-danger fw-bold small"></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="button" id="btnConfBorrar" class="btn btn-danger fw-bold px-4">AUTORIZAR BORRADO</button></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const mFin = new bootstrap.Modal('#modalFinalizar'), mBorrar = new bootstrap.Modal('#modalBorrar');
function prepararFinalizar(id, n) {
    document.getElementById('nombreFin').textContent = n;
    document.getElementById('btnConfFin').onclick = () => exec('finalizar', id, {}, mFin);
    mFin.show();
}
function prepararBorrado(id, n) {
    document.getElementById('nombreBorrar').textContent = n;
    document.getElementById('auth_user').value = ''; document.getElementById('auth_pin').value = '';
    document.getElementById('borrarMsg').textContent = '';
    document.getElementById('btnConfBorrar').onclick = () => {
        const u = document.getElementById('auth_user').value.trim(), p = document.getElementById('auth_pin').value.trim();
        if(!u || !p) { document.getElementById('borrarMsg').textContent = 'Ingresa usuario y PIN.'; return; }
        exec('borrar', id, {auth_user: u, auth_pin: p}, mBorrar, 'borrarMsg');
    };
    mBorrar.show();
}
function exec(act, id, data, modal, errId=null) {
    const btn = modal._element.querySelector('.modal-footer button:last-child'), txt = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    const fd = new FormData(); fd.append('accion', act); fd.append('id_evento', id);
    for(let k in data) fd.append(k, data[k]);
    fetch('', {method: 'POST', body: fd}).then(r=>r.json()).then(d => {
        if(d.status==='success') {
            localStorage.setItem('evt_upd', Date.now());
            window.location.reload();
        } else {
            if(errId) document.getElementById(errId).textContent = d.message; else alert(d.message);
            btn.disabled = false; btn.innerHTML = txt;
        }
    }).catch(() => { alert('Error de conexión'); btn.disabled = false; btn.innerHTML = txt; });
}
window.addEventListener('storage', e => { if(e.key==='evt_upd') window.location.reload(); });
</script>
</body>
</html>