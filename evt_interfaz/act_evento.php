<?php
session_start();
include "../conexion.php";

// ==================================================================
// VERIFICACIÓN DE SESIÓN (para acceso directo)
// ==================================================================
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px; color: red;"><h1>Acceso Denegado</h1><p>No tiene permiso para ver esta página.</p></div>');
}

// ==================================================================
// FUNCIONES DE GESTIÓN (Finalizar, Reactivar, Borrar...)
// ==================================================================

// reactivar_evento() no es necesaria aquí, ya que solo se usa en htr_eventos.php
// La dejamos por si copias y pegas el bloque
function reactivar_evento($id_evento, $conn) {
    // Esta función simple ya no se usa, la real está en htr_eventos.php
}

/**
 * Mueve un evento de producción (trt_25) al histórico (trt_historico_evento).
 *
 * @param int $id_evento El ID del evento en 'trt_25.evento'.
 * @param mysqli $conn La conexión a la base de datos.
 * @throws Exception Si alguna de las operaciones SQL falla.
 */
function borrar_evento($id_evento, $conn) {
    // 1. COPIA de 'trt_25' a 'trt_historico_evento'
    if (!$conn->query("INSERT INTO trt_historico_evento.evento SELECT * FROM trt_25.evento WHERE id_evento = $id_evento")) { throw new Exception("Error al archivar evento: " . $conn->error); }
    if (!$conn->query("INSERT INTO trt_historico_evento.funciones SELECT * FROM trt_25.funciones WHERE id_evento = $id_evento")) { throw new Exception("Error al archivar funciones: " . $conn->error); }
    if (!$conn->query("INSERT INTO trt_historico_evento.categorias SELECT * FROM trt_25.categorias WHERE id_evento = $id_evento")) { throw new Exception("Error al archivar categorías: " . $conn->error); }
    if (!$conn->query("INSERT INTO trt_historico_evento.promociones SELECT * FROM trt_25.promociones WHERE id_evento = $id_evento")) { throw new Exception("Error al archivar promociones: " . $conn->error); }
    if (!$conn->query("INSERT INTO trt_historico_evento.boletos SELECT * FROM trt_25.boletos WHERE id_evento = $id_evento")) { throw new Exception("Error al archivar boletos: " . $conn->error); }
    
    // 2. BORRA de 'trt_25' (producción)
    $conn->query("DELETE FROM trt_25.boletos WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM trt_25.promociones WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM trt_25.categorias WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM trt_25.funciones WHERE id_evento = $id_evento");
    $conn->query("DELETE FROM trt_25.evento WHERE id_evento = $id_evento");
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
                // 'finalizar' (botón naranja) archiva, sin pedir clave
                borrar_evento($id, $conn);
                break;
            
            case 'borrar':
                // 'borrar' (botón rojo) archiva, pidiendo clave
                $u = $_POST['auth_user']??''; $p = $_POST['auth_pin']??'';
                $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ? AND password = ?");
                $stmt->bind_param("ss", $u, $p); $stmt->execute(); $stmt->store_result();
                if($stmt->num_rows === 0) throw new Exception("Credenciales incorrectas");
                $stmt->close();
                borrar_evento($id, $conn);
                break;
            
            // Los casos 'reactivar' y 'borrar_permanente' no se pueden llamar
            // desde esta página (act_evento.php), pero no causan daño.
            case 'reactivar':
                // No hacer nada
                break;
            case 'borrar_permanente':
                // No hacer nada
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
// CARGA DE DATOS (SOLO ACTIVOS)
// ==================================================================

// --- MODIFICADO ---
// Auto-archivar eventos vencidos (en lugar de solo finalizarlos)
$rv = $conn->query("SELECT e.id_evento FROM trt_25.evento e 
                   LEFT JOIN trt_25.funciones lf ON e.id_evento = lf.id_evento 
                   WHERE e.finalizado = 0 
                   GROUP BY e.id_evento, e.cierre_venta
                   HAVING ((MAX(lf.fecha_hora) IS NOT NULL AND MAX(lf.fecha_hora) < NOW()) 
                        OR (MAX(lf.fecha_hora) IS NULL AND e.cierre_venta < NOW()))");
if($rv){ 
    while($r=$rv->fetch_assoc()){ 
        // Llama a la función de archivado completo
        borrar_evento($r['id_evento'], $conn); 
    }
}

// Carga los eventos activos de trt_25.evento
$activos = $conn->query("SELECT * FROM trt_25.evento WHERE finalizado = 0 ORDER BY inicio_venta DESC");
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    /* ... (Tu CSS) ... */
    :root {
        --primary-color: #2563eb; --primary-dark: #1e40af;
        --success-color: #10b981; --danger-color: #ef4444;
        --warning-color: #f59e0b; --info-color: #3b82f6;
        --bg-primary: #f8fafc; --bg-secondary: #ffffff;
        --text-primary: #0f172a; --text-secondary: #64748b;
        --border-color: #e2e8f0;
        --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
    }
    body { background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .content-wrapper { padding: 30px; }
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
    .modal-danger-mode .modal-header { background-color: var(--danger-color); color: white; }
    .modal-danger-mode .modal-content { border: 3px solid var(--danger-color); }
</style>

<div class="content-wrapper">
    <h2 class="fw-bold mb-4 text-primary">Eventos Activos</h2>
    <div class="row g-4">
        <?php if($activos && $activos->num_rows > 0): while($e = $activos->fetch_assoc()): 
            // --- LÓGICA DE IMAGEN CORREGIDA ---
            $img = '';
            if (!empty($e['imagen'])) {
                $img_path_real = "../evt_interfaz/" . $e['imagen']; // Asume que DB guarda "imagenes/evt_123.jpg"
                if(file_exists($img_path_real)) {
                    $img = $img_path_real;
                }
            }
        ?>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100">
                <?php if($img): ?><img src="<?= htmlspecialchars($img) ?>" class="card-img-top"><?php else: ?><div class="card-img-top bg-secondary"></div><?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title fw-bold"><?= htmlspecialchars($e['titulo']) ?></h5>
                    <p class="card-text small text-muted mb-0"><i class="bi bi-calendar2-event me-1"></i> Inicio: <?= date('d/m/y', strtotime($e['inicio_venta'])) ?></p>
                </div>
                <div class="card-footer bg-white border-0 pt-0 d-flex justify-content-between">
                    <a href="editar_evento.php?id=<?= $e['id_evento'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i></a>
                    <div class="d-flex gap-1">
                        <button class="btn btn-warning btn-sm text-white" onclick="conf('finalizar', <?= $e['id_evento'] ?>, '<?= addslashes($e['titulo']) ?>')"><i class="bi bi-archive"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="conf('borrar', <?= $e['id_evento'] ?>, '<?= addslashes($e['titulo']) ?>')"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="col-12"><div class="alert alert-info shadow-sm border-0">No hay eventos activos.</div></div>
        <?php endif; ?>
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
        case 'finalizar': 
            t='Archivar Evento'; 
            els.msg.innerHTML=`¿Archivar <strong>${nom}</strong>?`; 
            w='El evento se moverá al historial y ya no estará activo.'; 
            c='btn-warning'; 
            isCritical = true;
            req = false;
            break;
        case 'borrar': 
            t='Archivar (con Clave)'; 
            els.msg.innerHTML=`¿Archivar <strong>${nom}</strong>?`; 
            w='Se moverá al historial. Requiere autorización.'; 
            c='btn-danger';
            req=true; 
            isCritical=true; 
            break;
        case 'reactivar': t='Reactivar Evento'; els.msg.innerHTML=`¿Crear copia de <strong>${nom}</strong>?`; w='Se creará un nuevo evento activo.'; c='btn-success'; break;
        case 'borrar_permanente': t='Archivado Permanente'; els.msg.innerHTML=`¿Archivar <strong>${nom}</strong> permanentemente?`; w='Se moverá a la base de datos histórica.'; c='btn-dark'; isCritical=true; break;
    }
    els.title.textContent=t; els.txt.textContent=w; els.auth.classList.toggle('d-none',!req); els.btn.className=`btn fw-bold ${c}`;
    
    els.btn.onclick = () => {
        if(isCritical) {
            if(req && (!els.user.value || !els.pin.value)) return alert('Faltan credenciales');
            
            els.cont.parentNode.classList.add('modal-danger-mode');
            els.head.classList.add('bg-danger', 'text-white');
            els.close.classList.add('btn-close-white');
            els.title.innerHTML = '<i class="bi bi-exclamation-octagon-fill me-2"></i>¡ADVERTENCIA FINAL!';
            
            if (act === 'finalizar') {
                els.msg.innerHTML = `<h4 class="text-warning fw-bold text-center my-3">¿CONFIRMAS EL ARCHIVADO?</h4><p class="text-center mb-0">El evento <strong>${nom}</strong> se moverá al histórico.</p>`;
                els.warn.className = 'alert alert-warning border-0 fw-bold text-center d-block';
                els.txt.textContent = 'ACCIÓN DE ARCHIVADO.';
                els.btn.className = 'btn btn-warning fw-bold w-100 py-3 fs-5';
                els.btn.innerHTML = '<i class="bi bi-archive-fill me-2"></i>ARCHIVAR AHORA';
            } 
            else if (act === 'borrar') {
                els.msg.innerHTML = `<h4 class="text-danger fw-bold text-center my-3">¿ESTÁS SEGURO?</h4><p class="text-center mb-0">Se archivará <strong>${nom}</strong>.</p><p class="text-center fw-bold text-danger mt-2">Esta acción requiere clave de admin.</p>`;
                els.warn.className = 'alert alert-danger border-0 fw-bold text-center d-block';
                els.txt.textContent = 'ACCIÓN DESTRUCTIVA.';
                els.btn.className = 'btn btn-danger fw-bold w-100 py-3 fs-5';
                els.btn.innerHTML = '<i class="bi bi-trash3-fill me-2"></i>ARCHIVAR DEFINITIVAMENTE';
            }
            else if (act === 'borrar_permanente') { 
                els.msg.innerHTML = `<h4 class="text-warning fw-bold text-center my-3">¿CONFIRMAS EL ARCHIVADO?</h4><p class="text-center mb-0">Se moverán todos los datos de <strong>${nom}</strong> al histórico.</p><p class="text-center fw-bold text-danger mt-2">Esta acción es irreversible.</p>`;
                els.warn.className = 'alert alert-warning border-0 fw-bold text-center d-block';
                els.txt.textContent = 'ACCIÓN DE ARCHIVADO.';
                els.btn.className = 'btn btn-dark fw-bold w-100 py-3 fs-5';
                els.btn.innerHTML = '<i class="bi bi-archive-fill me-2"></i>ARCHIVAR AHORA';
            }

            els.auth.classList.add('d-none');
            els.cancel.classList.add('d-none');
            els.btn.onclick = () => ejecutar(act, id, req);
            isCritical = false; return;
        }
        ejecutar(act, id, req);
    };
    m.show();
}

function ejecutar(act, id, req) {
    let fd = new FormData(); fd.append('accion',act); fd.append('id_evento',id);
    if(req) { fd.append('auth_user',els.user.value); fd.append('auth_pin',els.pin.value); }
    const originalButtonText = els.btn.innerHTML;
    els.btn.disabled=true; 
    els.btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
    
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.status==='success') {
            localStorage.setItem('evt_upd', Date.now());
            if (act === 'reactivar') {
                // 'reactivar' no debería estar aquí, pero si lo está, lo redirige
                window.location.href = `editar_evento.php?id=${id}&es_nuevo=1`;
            } else {
                window.location.reload();
            }
        } else { 
            alert(d.message||'Error'); 
            m.hide(); 
            els.btn.disabled=false;
            els.btn.innerHTML = originalButtonText;
        }
    }).catch(()=>{ 
        alert('Error de red'); 
        m.hide(); 
        els.btn.disabled=false;
        els.btn.innerHTML = originalButtonText;
    });
}
window.addEventListener('storage', (e) => {
    if (e.key === 'evt_upd') window.location.reload();
});
</script>