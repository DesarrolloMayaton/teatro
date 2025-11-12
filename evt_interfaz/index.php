<?php
include "../conexion.php";

// ==================================================================
// FUNCIONES DE GESTIÓN
// ==================================================================

function finalizar_evento($id_evento, $conn) {
    // Marcar evento como finalizado
    $conn->query("UPDATE evento SET finalizado = 1 WHERE id_evento = $id_evento");
}

function reactivar_evento($id_evento, $conn) {
    // Reactivar evento (cambiar finalizado a 0)
    $conn->query("UPDATE evento SET finalizado = 0, inicio_venta = NOW(), cierre_venta = NOW() + INTERVAL 30 DAY WHERE id_evento = $id_evento");
}

function borrar_evento($id_evento, $conn) {
    // Borrar QR físicos
    $res_qrs = $conn->query("SELECT qr_path FROM boletos WHERE id_evento = $id_evento");
    if ($res_qrs) {
        while ($row = $res_qrs->fetch_assoc()) {
            if (!empty($row['qr_path'])) {
                $rutas = [__DIR__ . '/' . $row['qr_path'], __DIR__ . '/../' . $row['qr_path']];
                foreach ($rutas as $r) { if (file_exists($r)) { @unlink($r); break; } }
            }
        }
    }

    // Eliminar registros relacionados
    $conn->query("DELETE FROM boletos WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM promociones WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM categorias WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM funciones WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM evento WHERE id_evento = $id_evento");
}

// ==================================================================
// PROCESADOR AJAX
// ==================================================================
if (isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $id = $_POST['id_evento'] ?? 0;
    $conn->begin_transaction();
    try {
        switch($_POST['accion']) {
            case 'finalizar':
                finalizar_evento($id, $conn);
                break;
            case 'borrar':
                // --- CORRECCIÓN DE LÓGICA DE MI RESPUESTA ANTERIOR ---
                // El usuario revirtió mi cambio anterior, así que lo respeto.
                // 'borrar' vuelve a ser una acción destructiva con credenciales.
                $u = $_POST['auth_user']??''; $p = $_POST['auth_pin']??'';
                // Verificar credenciales (ajusta según tu tabla usuarios)
                $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ? AND password = ?");
                $stmt->bind_param("ss", $u, $p);
                $stmt->execute();
                $stmt->store_result();
                if($stmt->num_rows === 0) throw new Exception("Credenciales incorrectas");
                $stmt->close();
                borrar_evento($id, $conn);
                break;
            case 'reactivar':
                reactivar_evento($id, $conn);
                break;
            case 'borrar_permanente':
                borrar_evento($id, $conn);
                break;
        }
        $conn->commit();
        echo json_encode(['status'=>'success']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
    }
    exit;
}

// ==================================================================
// CARGA DE DATOS
// ==================================================================
// Auto-finalizar eventos vencidos
$rv = $conn->query("SELECT e.id_evento FROM evento e LEFT JOIN (SELECT id_evento, MAX(fecha_hora) as ult FROM funciones GROUP BY id_evento) lf ON e.id_evento = lf.id_evento WHERE e.finalizado=0 AND ((lf.ult IS NOT NULL AND lf.ult < NOW()) OR (lf.ult IS NULL AND e.cierre_venta < NOW()))");
if($rv){ while($r=$rv->fetch_assoc()){ finalizar_evento($r['id_evento'], $conn); }}

$activos = $conn->query("SELECT * FROM evento WHERE finalizado = 0 ORDER BY inicio_venta DESC");
$historial = $conn->query("SELECT * FROM evento WHERE finalizado = 1 ORDER BY cierre_venta DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Dashboard de Eventos</title>
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
    body { background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); font-family: 'Inter', sans-serif; color: var(--text-primary); height: 100vh; overflow: hidden; }
    .main-container { display: flex; height: 100vh; }
    .sidebar { width: 280px; background: #1e293b; padding: 25px; color: #fff; display: flex; flex-direction: column; }
    .nav-link { color: #94a3b8; padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 5px; transition: all 0.2s; }
    .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: #fff; }
    .nav-link.active { background: var(--primary-color); }
    .nav-link i { margin-right: 10px; font-size: 1.1em; }
    .content { flex: 1; padding: 30px; overflow-y: auto; }
    .card { border: none; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); transition: all 0.3s ease; background: var(--bg-secondary); }
    .card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }
    .card-img-top { height: 180px; object-fit: cover; border-radius: var(--radius-md) var(--radius-md) 0 0; }
    .btn { border-radius: var(--radius-sm); font-weight: 600; padding: 8px 16px; border: none; transition: all 0.2s; }
    .btn-primary { background: var(--primary-color); color: white; } .btn-primary:hover { background: var(--primary-dark); }
    .btn-success { background: var(--success-color); color: white; } .btn-success:hover { background: #059669; }
    .btn-danger { background: var(--danger-color); color: white; } .btn-danger:hover { background: #dc2626; }
    .btn-warning { background: var(--warning-color); color: white; } .btn-warning:hover { background: #d97706; }
    .btn-dark { background: #334155; color: white; } .btn-dark:hover { background: #1e293b; }
    .badge-finalizado { background: #64748b; color: white; padding: 4px 8px; border-radius: 20px; font-size: 0.7em; vertical-align: middle; }
    /* Estilos Alerta Roja Modal */
    .modal-danger-mode .modal-header { background-color: var(--danger-color); color: white; }
    .modal-danger-mode .modal-content { border: 3px solid var(--danger-color); }
</style>
</head>
<body>
<div class="main-container">
    <div class="sidebar">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="text-white fw-bold m-0"><i class="bi bi-grid-fill me-2"></i>Dashboard</h4>
            <button onclick="window.location.reload()" class="btn btn-sm btn-outline-light" title="Actualizar Datos"><i class="bi bi-arrow-clockwise"></i></button>
        </div>

        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><button class="nav-link active w-100 text-start" data-bs-toggle="pill" data-bs-target="#activos"><i class="bi bi-activity"></i> Activos</button></li>
            <li class="nav-item"><button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#historial"><i class="bi bi-archive"></i> Historial</button></li>
        </ul>
        <a href="crear_evento.php" class="btn btn-success w-100 py-2 mt-4"><i class="bi bi-plus-lg me-2"></i>Nuevo Evento</a>
    </div>

    <div class="content">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="activos">
                <h2 class="fw-bold mb-4 text-primary">Eventos Activos</h2>
                <div class="row g-4">
                    <?php if($activos && $activos->num_rows>0): while($e=$activos->fetch_assoc()): 
                        $img=''; $rutas=[$e['imagen'], '../'.$e['imagen'], 'evt_interfaz/'.$e['imagen']];
                        foreach($rutas as $r){if(file_exists(__DIR__.'/'.$r)){$img=$r;break;}} ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card h-100">
                            <?php if($img):?><img src="<?=htmlspecialchars($img)?>" class="card-img-top"><?php else:?><div class="card-img-top bg-secondary"></div><?php endif;?>
                            <div class="card-body">
                                <h5 class="card-title fw-bold"><?=htmlspecialchars($e['titulo'])?></h5>
                                <p class="card-text small text-muted mb-0"><i class="bi bi-calendar2-event me-1"></i> Inicio: <?=date('d/m/y',strtotime($e['inicio_venta']))?></p>
                            </div>
                            <div class="card-footer bg-white border-0 pt-0 d-flex justify-content-between">
                                <a href="editar_evento.php?id=<?=$e['id_evento']?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i></a>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-warning btn-sm text-white" onclick="conf('finalizar',<?=$e['id_evento']?>,'<?=addslashes($e['titulo'])?>')"><i class="bi bi-archive"></i></button>
                                    <button class="btn btn-danger btn-sm" onclick="conf('borrar',<?=$e['id_evento']?>,'<?=addslashes($e['titulo'])?>')"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; else: ?><div class="col-12"><div class="alert alert-info shadow-sm border-0">No hay eventos activos.</div></div><?php endif; ?>
                </div>
            </div>
            <div class="tab-pane fade" id="historial">
                <h2 class="fw-bold mb-4 text-secondary">Historial de Eventos</h2>
                <div class="row g-4">
                    <?php if($historial && $historial->num_rows>0): while($e=$historial->fetch_assoc()): 
                          $img=''; $rutas=[$e['imagen'], '../'.$e['imagen'], 'evt_interfaz/'.$e['imagen']];
                          foreach($rutas as $r){if(file_exists(__DIR__.'/'.$r)){$img=$r;break;}} ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card h-100 bg-light">
                            <?php if($img):?><img src="<?=htmlspecialchars($img)?>" class="card-img-top" style="filter:grayscale(1);opacity:0.7"><?php else:?><div class="card-img-top bg-secondary" style="opacity:0.7"></div><?php endif;?>
                            <div class="card-body">
                                <h6 class="card-title text-muted"><?=htmlspecialchars($e['titulo'])?> <span class="badge-finalizado">ARCHIVADO</span></h6>
                                <p class="small text-muted mb-0">Cerró: <?=date('d/m/y',strtotime($e['cierre_venta']))?></p>
                            </div>
                            <div class="card-footer bg-transparent border-0 pt-0 text-end">
                                <button class="btn btn-primary btn-sm" onclick="conf('reactivar',<?=$e['id_evento']?>,'<?=addslashes($e['titulo'])?>')"><i class="bi bi-copy"></i> Reactivar</button>
                                <button class="btn btn-dark btn-sm" onclick="conf('borrar_permanente',<?=$e['id_evento']?>,'<?=addslashes($e['titulo'])?>')"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; else: ?><div class="col-12"><p class="text-muted">Historial vacío.</p></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mConf" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" id="mContent">
            <div class="modal-header border-0" id="mHeader">
                <h5 class="modal-title fw-bold" id="mTitle">Confirmar Acción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="mClose"></button>
            </div>
            <div class="modal-body">
                <p id="mMsg" class="fs-5 mb-3"></p>
                <div id="mAuth" class="d-none bg-light p-3 rounded-3 mb-3 border">
                    <label class="small fw-bold text-secondary mb-2">Credenciales de Administrador:</label>
                    <input type="text" id="mUser" class="form-control mb-2" placeholder="Usuario">
                    <input type="password" id="mPin" class="form-control" placeholder="PIN">
                </div>
                <div id="mWarn" class="alert alert-warning border-0 small d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i><span id="mTxt"></span></div>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-light" data-bs-dismiss="modal" id="mCancel">Cancelar</button>
                <button id="mBtn" class="btn px-4">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const m = new bootstrap.Modal('#mConf');
const els = { cont:document.getElementById('mContent'), head:document.getElementById('mHeader'), title:document.getElementById('mTitle'), msg:document.getElementById('mMsg'), auth:document.getElementById('mAuth'), warn:document.getElementById('mWarn'), txt:document.getElementById('mTxt'), btn:document.getElementById('mBtn'), user:document.getElementById('mUser'), pin:document.getElementById('mPin'), close:document.getElementById('mClose'), cancel:document.getElementById('mCancel') };

function resetModal() {
    els.cont.parentNode.classList.remove('modal-danger-mode');
    els.head.classList.remove('bg-danger', 'text-white');
    els.btn.classList.remove('btn-danger', 'w-100', 'py-3', 'fs-5');
    els.warn.className = 'alert alert-warning border-0 small d-flex align-items-center';
    els.auth.classList.add('d-none'); els.user.value = ''; els.pin.value = '';
    els.close.classList.remove('btn-close-white'); els.cancel.classList.remove('d-none');
}

function conf(act, id, nom) {
    resetModal();
    let t='', w='', c='btn-primary', req=false, isCritical=false;
    switch(act) {
        case 'finalizar': t='Finalizar Evento'; els.msg.innerHTML=`¿Mover <strong>${nom}</strong> al historial?`; w='El evento dejará de estar activo.'; c='btn-warning'; break;
        case 'borrar': t='Archivar Evento'; els.msg.innerHTML=`¿Eliminar <strong>${nom}</strong> de activos?`; w='Se moverá al historial. Requiere autorización.'; c='btn-primary'; req=true; isCritical=true; break;
        case 'reactivar': t='Reactivar Evento'; els.msg.innerHTML=`¿Crear copia de <strong>${nom}</strong>?`; w='Se creará un nuevo evento activo.'; c='btn-success'; break;
        case 'borrar_permanente': t='Eliminación Total'; els.msg.innerHTML=`¿Borrar <strong>${nom}</strong> para siempre?`; w='IRREVERSIBLE.'; c='btn-dark'; isCritical=true; break;
    }
    els.title.textContent=t; els.txt.textContent=w; els.auth.classList.toggle('d-none',!req); els.btn.className=`btn fw-bold ${c}`;

    els.btn.onclick = () => {
        if(isCritical) {
            if(req && (!els.user.value || !els.pin.value)) return alert('Faltan credenciales');
            els.cont.parentNode.classList.add('modal-danger-mode');
            els.head.classList.add('bg-danger', 'text-white');
            els.close.classList.add('btn-close-white');
            els.title.innerHTML = '<i class="bi bi-exclamation-octagon-fill me-2"></i>¡ADVERTENCIA FINAL!';
            els.msg.innerHTML = `<h4 class="text-danger fw-bold text-center my-3">¿ESTÁS SEGURO?</h4><p class="text-center mb-0">Se eliminará <strong>${nom}</strong>.</p><p class="text-center fw-bold text-danger mt-2">¡DATOS IRRECUPERABLES!</p>`;
            els.warn.className = 'alert alert-danger border-0 fw-bold text-center d-block';
            els.txt.textContent = 'ACCIÓN DESTRUCTIVA.';
            els.auth.classList.add('d-none'); els.cancel.classList.add('d-none');
            els.btn.className = 'btn btn-danger fw-bold w-100 py-3 fs-5';
            els.btn.innerHTML = '<i class="bi bi-trash3-fill me-2"></i>BORRAR DEFINITIVAMENTE';
            els.btn.onclick = () => ejecutar(act, id, req);
            isCritical = false; return;
        }
        ejecutar(act, id, req);
    };
    m.show();
}

// ==========================================================
// INICIO DE LA CORRECCIÓN
// ==========================================================
function ejecutar(act, id, req) {
    let fd = new FormData(); fd.append('accion',act); fd.append('id_evento',id);
    if(req) { fd.append('auth_user',els.user.value); fd.append('auth_pin',els.pin.value); }
    
    // Guardar el texto original del botón
    const originalButtonText = els.btn.innerHTML;
    els.btn.disabled=true; 
    els.btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
    
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.status==='success') {
            localStorage.setItem('evt_upd', Date.now());
            
            // --- LÓGICA DE REDIRECCIÓN ---
            if (act === 'reactivar') {
                // Si fue 'reactivar', redirigir a la página de edición
                window.location.href = `editar_evento.php?id=${id}&es_nuevo=1`;
            } else {
                // Para cualquier otra acción (finalizar, borrar, etc.), recargar
                window.location.reload();
            }
            
        } else { 
            alert(d.message||'Error'); 
            m.hide(); 
            // Restaurar botón si falla
            els.btn.disabled=false;
            els.btn.innerHTML = originalButtonText;
        }
    }).catch(()=>{ 
        alert('Error de red'); 
        m.hide(); 
        // Restaurar botón si falla
        els.btn.disabled=false;
        els.btn.innerHTML = originalButtonText;
    });
}
// ==========================================================
// FIN DE LA CORRECCIÓN
// ==========================================================

// NUEVO: Escuchar cambios en localStorage para recarga automática en otras pestañas
window.addEventListener('storage', (e) => {
    if (e.key === 'evt_upd') window.location.reload();
});
</script>
</body>
</html>