<?php
// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "../conexion.php";

if(file_exists("../transacciones_helper.php")) {
    require_once "../transacciones_helper.php";
}

// ==================================================================
// VERIFICACIÓN DE SESIÓN
// ==================================================================
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px; color: red;"><h1>Acceso Denegado</h1><p>No tiene permiso para ver esta página.</p></div>');
}

// ==================================================================
// PROCESADOR AJAX (SOLO PARA BORRAR)
// ==================================================================
if (isset($_POST['accion']) && $_POST['accion'] === 'borrar_permanente') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id_evento'];
    
    $conn->begin_transaction();
    try {
        // Borrar todo de las tablas HISTÓRICAS
        $conn->query("DELETE FROM trt_historico_evento.boletos WHERE id_evento = $id");
        $conn->query("DELETE FROM trt_historico_evento.promociones WHERE id_evento = $id");
        $conn->query("DELETE FROM trt_historico_evento.categorias WHERE id_evento = $id");
        $conn->query("DELETE FROM trt_historico_evento.funciones WHERE id_evento = $id");
        $conn->query("DELETE FROM trt_historico_evento.evento WHERE id_evento = $id");
        
        if(function_exists('registrar_transaccion')) {
            registrar_transaccion('evento_borrar_historico', "Borró permanentemente evento histórico ID $id");
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// CARGA DE DATOS (HISTORIAL)
$historial = $conn->query("SELECT * FROM trt_historico_evento.evento ORDER BY cierre_venta DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    :root {
        --primary-color: #1561f0; 
        --bg-primary: #131313;
        --bg-card: #1c1c1e;
        --text-primary: #ffffff;
        --text-secondary: #86868b;
        --border-color: #3a3a3c;
        --radius-md: 12px;
    }
    body { 
        background-color: var(--bg-primary); 
        color: var(--text-primary);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
        opacity: 0; transition: opacity 0.4s;
    }
    body.loaded { opacity: 1; }
    
    .content-wrapper { padding: 20px; }
    
    /* --- TARJETA --- */
    .card { 
        border: 1px solid var(--border-color); 
        border-radius: var(--radius-md); 
        box-shadow: 0 4px 8px rgba(0,0,0,0.3); 
        background: var(--bg-card); 
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: cardEntry 0.4s ease forwards;
        opacity: 0; transform: translateY(15px);
    }
    @keyframes cardEntry { to { opacity: 1; transform: translateY(0); } }
    
    .card:hover { 
        transform: translateY(-3px); 
        box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        border-color: var(--primary-color);
    }
    
    /* Estilo para histórico */
    .card-img-container {
        width: 100%;
        aspect-ratio: 3 / 4; 
        background-color: #2b2b2b; 
        position: relative;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
    }
    
    .card-img-top { 
        width: 100%; height: 100%; object-fit: cover; 
        transition: transform 0.4s, filter 0.4s;
        filter: grayscale(100%) contrast(0.9);
    }
    .card:hover .card-img-top { 
        transform: scale(1.08); 
        filter: grayscale(0%) contrast(1);
    }

    .card-body { padding: 0.8rem; display: flex; flex-direction: column; gap: 5px; } 
    .card-title { 
        font-size: 0.95rem; 
        margin-bottom: 0.2rem; 
        line-height: 1.2; 
        color: var(--text-secondary) !important;
    }
    
    /* --- CONTENEDOR DE FUNCIONES --- */
    .funcs-container {
        display: flex; flex-wrap: wrap; gap: 4px;
        max-height: 65px; overflow-y: auto;
        padding-right: 2px;
    }
    .funcs-container::-webkit-scrollbar { width: 3px; }
    .funcs-container::-webkit-scrollbar-thumb { background: #3a3a3c; border-radius: 3px; }

    .func-badge {
        font-size: 0.65rem; 
        background: #2b2b2b; 
        color: var(--text-secondary);
        padding: 2px 6px; border-radius: 4px;
        border: 1px solid var(--border-color);
        white-space: nowrap; font-weight: 600;
    }
    
    .card-footer { 
        padding: 0.6rem 0.8rem; 
        background: var(--bg-card) !important;
        border-top: 1px solid var(--border-color) !important;
    }
    .btn-sm-custom { padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 6px; }
    .badge-archived { font-size: 0.65rem; background: #2b2b2b; color: var(--text-secondary); padding: 2px 6px; border-radius: 4px; font-weight: bold; width: fit-content; }

    /* Título y badge */
    .text-secondary { color: var(--text-secondary) !important; }
    .badge { background: #2b2b2b !important; color: var(--text-secondary) !important; }

    /* Botones */
    .btn-outline-primary {
        border-color: var(--primary-color) !important;
        color: var(--primary-color) !important;
    }
    .btn-outline-primary:hover {
        background: var(--primary-color) !important;
        color: white !important;
    }
    .btn-light {
        background: #2b2b2b !important;
        border-color: var(--border-color) !important;
    }
    .text-danger { color: #ff453a !important; }

    /* Alerta vacía */
    .alert-light {
        background: var(--bg-card) !important;
        border-color: var(--border-color) !important;
        color: var(--text-secondary) !important;
    }

    /* Modal */
    .modal-content { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); }
    .modal-header, .modal-footer { border-color: var(--border-color); }
    .form-control { background: #2b2b2b; border-color: var(--border-color); color: var(--text-primary); }
    .form-control:focus { background: #2b2b2b; border-color: var(--primary-color); color: var(--text-primary); }
    .bg-light { background: #2b2b2b !important; }
    .alert-warning { background: rgba(255, 159, 10, 0.15) !important; border-color: rgba(255, 159, 10, 0.3) !important; color: #ff9f0a !important; }
</style>
</head>
<body>

<div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-secondary m-0">Historial</h4>
        <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $historial->num_rows ?> archivados</span>
    </div>

    <div class="row g-3">
        <?php if($historial && $historial->num_rows > 0): 
            $delay = 0;
            while($e = $historial->fetch_assoc()): 
                $delay += 30;
                
                // 1. IMAGEN
                $img = '';
                if (!empty($e['imagen'])) {
                    // Intentar buscar en interfaz o local
                    $rutas = ["../evt_interfaz/" . $e['imagen'], $e['imagen']];
                    foreach($rutas as $r) { if(file_exists($r)) { $img = $r; break; } }
                }

                // 2. OBTENER FUNCIONES HISTÓRICAS
                $id_evt = $e['id_evento'];
                // Nota: Consultamos a trt_historico_evento
                $sql_func = "SELECT fecha_hora FROM trt_historico_evento.funciones WHERE id_evento = $id_evt ORDER BY fecha_hora ASC";
                $res_func = $conn->query($sql_func);
                $funciones = [];
                if($res_func){
                    while($f = $res_func->fetch_assoc()){
                        $funciones[] = $f['fecha_hora'];
                    }
                }
        ?>
        <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-4 col-6">
            <div class="card h-100" style="animation-delay: <?= $delay ?>ms">
                <div class="card-img-container">
                    <?php if($img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" class="card-img-top" loading="lazy">
                    <?php else: ?>
                        <div class="text-center text-muted"><i class="bi bi-archive fs-4 opacity-50"></i></div>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                    <h6 class="card-title fw-bold text-truncate" title="<?= htmlspecialchars($e['titulo']) ?>">
                        <?= htmlspecialchars($e['titulo']) ?>
                    </h6>
                    <div class="badge-archived mb-2">Cerró: <?= date('d/m/y', strtotime($e['cierre_venta'])) ?></div>

                    <div class="funcs-container">
                        <?php if(count($funciones) > 0): ?>
                            <?php foreach($funciones as $fh): ?>
                                <span class="func-badge">
                                    <i class="bi bi-clock me-1" style="font-size:9px"></i><?= date('d/m H:i', strtotime($fh)) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="func-badge">Sin datos</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
                    <button onclick="irAReactivar(<?= $e['id_evento'] ?>)" class="btn btn-outline-primary btn-sm-custom w-100 me-1">
                        <i class="bi bi-arrow-counterclockwise"></i> Reactivar
                    </button>
                    
                    <button class="btn btn-light text-danger btn-sm-custom" onclick="conf('borrar_permanente', <?= $e['id_evento'] ?>, <?= htmlspecialchars(json_encode($e['titulo']), ENT_QUOTES, 'UTF-8') ?>)" title="Borrar Definitivamente">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="col-12">
            <div class="alert alert-light border text-center p-5">
                <i class="bi bi-archive fs-1 text-muted mb-3"></i>
                <p class="mb-0 text-muted">El historial está vacío.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="mConf" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" id="mContent">
            <div class="modal-header border-0" id="mHeader">
                <h5 class="modal-title fw-bold" id="mTitle">Confirmar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="mClose"></button>
            </div>
            <div class="modal-body">
                <p id="mMsg" class="fs-5 mb-3"></p>
                <div id="mAuth" class="d-none bg-light p-3 rounded-3 mb-3 border">
                    <label class="small fw-bold text-secondary mb-2">Credenciales Admin:</label>
                    <input type="text" id="mUser" class="form-control mb-2" placeholder="Usuario">
                    <input type="password" id="mPin" class="form-control" placeholder="Contraseña">
                </div>
                <div id="mWarn" class="alert alert-warning border-0 small d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i><span id="mTxt"></span>
                </div>
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
document.addEventListener('DOMContentLoaded', () => document.body.classList.add('loaded'));

// REDIRECCIÓN DIRECTA PARA REACTIVAR (Sin AJAX)
function irAReactivar(id) {
    // Pasamos el ID histórico y la bandera
    window.location.href = `editar_evento.php?id=${id}&modo_reactivacion=1`;
}

// LOGICA DE BORRADO (Modal)
const m = new bootstrap.Modal('#mConf');
const els = { cont:document.getElementById('mContent'), head:document.getElementById('mHeader'), title:document.getElementById('mTitle'), msg:document.getElementById('mMsg'), auth:document.getElementById('mAuth'), warn:document.getElementById('mWarn'), txt:document.getElementById('mTxt'), btn:document.getElementById('mBtn'), user:document.getElementById('mUser'), pin:document.getElementById('mPin'), close:document.getElementById('mClose'), cancel:document.getElementById('mCancel') };

function resetModal() {
    els.cont.parentNode.classList.remove('modal-danger-mode');
    els.head.classList.remove('bg-danger', 'text-white');
    els.btn.className = 'btn px-4'; 
    els.auth.classList.add('d-none'); els.user.value = ''; els.pin.value = '';
    els.close.classList.remove('btn-close-white');
}

function conf(act, id, nom) {
    resetModal();
    
    if (act === 'borrar_permanente') {
        t = 'Borrado Definitivo';
        els.msg.innerHTML = `¿Eliminar <strong>${nom}</strong> para siempre?`;
        w = 'Esta acción NO se puede deshacer. Requiere credenciales.';
        c = 'btn-danger fw-bold w-100 py-2';
        
        els.auth.classList.remove('d-none');
        els.cont.parentNode.classList.add('modal-danger-mode');
        els.head.classList.add('bg-danger', 'text-white');
        els.close.classList.add('btn-close-white');
    }

    els.title.textContent = t; 
    els.txt.textContent = w; 
    els.btn.className = `btn ${c}`;

    els.btn.onclick = () => ejecutar(act, id);
    m.show();
}

function ejecutar(act, id) {
    if((!els.user.value || !els.pin.value)) { alert('Credenciales requeridas.'); return; }

    let fd = new FormData(); 
    fd.append('accion', act); 
    fd.append('id_evento', id);
    fd.append('auth_user', els.user.value); 
    fd.append('auth_pass', els.pin.value);
    
    els.btn.disabled = true; 
    els.btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    fetch('', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if(d.status === 'success') {
            location.reload();
        } else {
            alert(d.message || 'Error');
            els.btn.disabled = false; els.btn.innerHTML = 'Confirmar';
        }
    })
    .catch(() => { alert('Error de conexión'); els.btn.disabled = false; els.btn.innerHTML = 'Confirmar'; });
}
</script>
</body>
</html>