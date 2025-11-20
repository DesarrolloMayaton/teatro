<?php
session_start();
include "../conexion.php";
<<<<<<< HEAD
require_once "../transacciones_helper.php";
=======
>>>>>>> 4d92ed57add1e65b0a8c2a3a700b2b0cfd2e6268

// ==================================================================
// VERIFICACIÓN DE SESIÓN
// ==================================================================
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px; color: red;"><h1>Acceso Denegado</h1><p>No tiene permiso para ver esta página.</p></div>');
}

// ==================================================================
// FUNCIONES DE GESTIÓN
// ==================================================================
function finalizar_evento($id_evento, $conn) {
    // Esta función no se usa en esta página, pero la dejamos por coherencia
    $conn->query("UPDATE trt_25.evento SET finalizado = 1 WHERE id_evento = $id_evento");
}

/**
 * Mueve un evento del histórico (trt_historico_evento) a producción (trt_25) con un NUEVO ID.
 * Mapea los IDs de categorías y promociones para mantener la integridad referencial.
 * Luego borra el registro del histórico.
 *
 * @param int $id_evento_historico El ID del evento en las tablas 'trt_historico_'.
 * @param mysqli $conn La conexión a la base de datos.
 * @return int El NUEVO ID del evento creado en la tabla 'trt_25.evento'.
 * @throws Exception Si alguna de las operaciones SQL falla.
 */
function reactivar_evento($id_evento_historico, $conn) {
    
    // 1. Rescatar datos del evento histórico
    $res_evt = $conn->query("SELECT * FROM trt_historico_evento.evento WHERE id_evento = $id_evento_historico");
    if ($res_evt->num_rows == 0) {
        throw new Exception("No se encontró el evento en el histórico.");
    }
    $evt = $res_evt->fetch_assoc();

    // 2. Insertar en la tabla 'trt_25.evento' (producción)
    $stmt_evt = $conn->prepare("INSERT INTO trt_25.evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado, mapa_json) 
                                VALUES (?, ?, ?, ?, NOW(), NOW() + INTERVAL 30 DAY, 0, ?)");
    $stmt_evt->bind_param("sssis", $evt['titulo'], $evt['descripcion'], $evt['imagen'], $evt['tipo'], $evt['mapa_json']);
    if (!$stmt_evt->execute()) {
        throw new Exception("Error al insertar nuevo evento: " . $stmt_evt->error);
    }
    $new_id = $conn->insert_id; // <-- Obtenemos el NUEVO ID
    $stmt_evt->close();

    // 3. Mover Categorías (Histórico -> Producción) y MAPEAR IDs
    $categoria_id_map = []; // Mapa para [old_id => new_id]
    $res_cat = $conn->query("SELECT * FROM trt_historico_evento.categorias WHERE id_evento = $id_evento_historico");
    $stmt_cat = $conn->prepare("INSERT INTO trt_25.categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
    while ($cat = $res_cat->fetch_assoc()) {
        $old_cat_id = $cat['id_categoria']; // ID Viejo
        $stmt_cat->bind_param("isds", $new_id, $cat['nombre_categoria'], $cat['precio'], $cat['color']);
        $stmt_cat->execute();
        $new_cat_id = $conn->insert_id; // ID Nuevo
        $categoria_id_map[$old_cat_id] = $new_cat_id; // Guardar mapeo
    }
    $stmt_cat->close();

    // 4. Mover Funciones (Histórico -> Producción)
    $res_func = $conn->query("SELECT * FROM trt_historico_evento.funciones WHERE id_evento = $id_evento_historico");
    $stmt_func = $conn->prepare("INSERT INTO trt_25.funciones (id_evento, fecha_hora) VALUES (?, ?)");
    while ($func = $res_func->fetch_assoc()) {
        $stmt_func->bind_param("is", $new_id, $func['fecha_hora']);
        $stmt_func->execute();
    }
    $stmt_func->close();

    // 5. Mover Promociones (Histórico -> Producción) y MAPEAR IDs
    $promocion_id_map = []; // Mapa para [old_id => new_id]
    $res_promo = $conn->query("SELECT * FROM trt_historico_evento.promociones WHERE id_evento = $id_evento_historico");
    $stmt_promo = $conn->prepare("INSERT INTO trt_25.promociones (id_evento, nombre, precio, id_categoria, fecha_desde, fecha_hasta, min_cantidad, tipo_regla, codigo, modo_calculo, valor, condiciones, activo) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    while ($p = $res_promo->fetch_assoc()) {
        $old_promo_id = $p['id_promocion'];
        $old_cat_id_for_promo = $p['id_categoria'];
        
        // Buscar el NUEVO id_categoria usando el mapa, o null si no aplica
        $new_cat_id_for_promo = $categoria_id_map[$old_cat_id_for_promo] ?? null;

        $stmt_promo->bind_param("isdisissssssi", $new_id, $p['nombre'], $p['precio'], $new_cat_id_for_promo, $p['fecha_desde'], $p['fecha_hasta'], $p['min_cantidad'], $p['tipo_regla'], $p['codigo'], $p['modo_calculo'], $p['valor'], $p['condiciones'], $p['activo']);
        $stmt_promo->execute();
        $new_promo_id = $conn->insert_id;
        $promocion_id_map[$old_promo_id] = $new_promo_id; // Guardar mapeo
    }
    $stmt_promo->close();

    // 6. Mover Boletos (Histórico -> Producción) USANDO MAPAS
    $res_bol = $conn->query("SELECT * FROM trt_historico_evento.boletos WHERE id_evento = $id_evento_historico");
    $stmt_bol = $conn->prepare("INSERT INTO trt_25.boletos (id_evento, id_asiento, id_categoria, id_promocion, codigo_unico, precio_base, descuento_aplicado, precio_final, tipo_boleto, id_usuario, fecha_compra, estatus, qr_path)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    while ($b = $res_bol->fetch_assoc()) {
        // Buscar NUEVOS IDs usando los mapas
        $new_cat_id_for_boleto = $categoria_id_map[$b['id_categoria']] ?? null;
        $new_promo_id_for_boleto = $promocion_id_map[$b['id_promocion']] ?? null;

        $stmt_bol->bind_param("iiiisdddssiss", $new_id, $b['id_asiento'], $new_cat_id_for_boleto, $new_promo_id_for_boleto, $b['codigo_unico'], $b['precio_base'], $b['descuento_aplicado'], $b['precio_final'], $b['tipo_boleto'], $b['id_usuario'], $b['fecha_compra'], $b['estatus'], $b['qr_path']);
        $stmt_bol->execute();
    }
    $stmt_bol->close();

    // 7. Borrar del histórico (hacerlo al final, por si algo falla)
    $conn->query("DELETE FROM trt_historico_evento.boletos WHERE id_evento = $id_evento_historico");
    $conn->query("DELETE FROM trt_historico_evento.promociones WHERE id_evento = $id_evento_historico");
    $conn->query("DELETE FROM trt_historico_evento.categorias WHERE id_evento = $id_evento_historico");
    $conn->query("DELETE FROM trt_historico_evento.funciones WHERE id_evento = $id_evento_historico");
    $conn->query("DELETE FROM trt_historico_evento.evento WHERE id_evento = $id_evento_historico");
    
    return $new_id;
}

/**
 * Borra permanentemente un evento de las tablas de histórico.
 */
function borrar_de_historico($id_evento_historico, $conn) {
    // Borra permanentemente de la base de datos 'trt_historico_evento'
    $conn->query("DELETE FROM trt_historico_evento.boletos WHERE id_evento = $id_evento_historico");
    $conn->query("DELETE FROM trt_historico_evento.promociones WHERE id_evento = $id_evento_historico");
    $conn->query("DELETE FROM trt_historico_evento.categorias WHERE id_evento = $id_evento_historico");
    $conn->query("DELETE FROM trt_historico_evento.funciones WHERE id_evento = $id_evento_historico");
    $conn->query("DELETE FROM trt_historico_evento.evento WHERE id_evento = $id_evento_historico");
}

// Esta función es para archivar (Activos -> Histórico), no se usa aquí.
function borrar_evento($id_evento, $conn) {
    if (!$conn->query("INSERT INTO trt_historico_evento.evento SELECT * FROM trt_25.evento WHERE id_evento = $id_evento")) { throw new Exception("Error al archivar evento: " . $conn->error); }
    if (!$conn->query("INSERT INTO trt_historico_evento.funciones SELECT * FROM trt_25.funciones WHERE id_evento = $id_evento")) { throw new Exception("Error al archivar funciones: " . $conn->error); }
    if (!$conn->query("INSERT INTO trt_historico_evento.categorias SELECT * FROM trt_25.categorias WHERE id_evento = $id_evento")) { throw new Exception("Error al archivar categorías: " . $conn->error); }
    if (!$conn->query("INSERT INTO trt_historico_evento.promociones SELECT * FROM trt_25.promociones WHERE id_evento = $id_evento")) { throw new Exception("Error al archivar promociones: " . $conn->error); }
    if (!$conn->query("INSERT INTO trt_historico_evento.boletos SELECT * FROM trt_25.boletos WHERE id_evento = $id_evento")) { throw new Exception("Error al archivar boletos: " . $conn->error); }
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
        $response = ['status' => 'success']; // Respuesta base

        switch($_POST['accion']) {
            case 'reactivar':
                $new_id = reactivar_evento($id, $conn);
                $response['new_id'] = $new_id; // Añadir el new_id a la respuesta
<<<<<<< HEAD
                registrar_transaccion('evento_reactivar', 'Reactivó evento histórico ID ' . $id . ' como nuevo evento ID ' . $new_id);
=======
>>>>>>> 4d92ed57add1e65b0a8c2a3a700b2b0cfd2e6268
                break;
            
            case 'borrar_permanente':
                // Esta acción ahora borra del histórico
                borrar_de_historico($id, $conn);
<<<<<<< HEAD
                registrar_transaccion('evento_borrar_historico', 'Borró permanentemente evento histórico ID ' . $id);
=======
>>>>>>> 4d92ed57add1e65b0a8c2a3a700b2b0cfd2e6268
                break;
            
            case 'finalizar':
            case 'borrar':
                // Estas acciones no deben ocurrir aquí, pero si ocurren, no hacen nada.
                break;
        }
        $conn->commit();
        echo json_encode($response);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
    }
    exit;
}

// ==================================================================
// CARGA DE DATOS (SOLO HISTORIAL)
// ==================================================================
// Cargamos de la base de datos 'trt_historico_evento', tabla 'evento'
$historial = $conn->query("SELECT * FROM trt_historico_evento.evento ORDER BY cierre_venta DESC");
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    /* ... (Tu CSS va aquí) ... */
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
    <h2 class="fw-bold mb-4 text-secondary">Historial de Eventos</h2>
    <div class="row g-4">
        <?php if($historial && $historial->num_rows > 0): while($e = $historial->fetch_assoc()): 
            $img = ''; 
            if (!empty($e['imagen'])) {
                // Comprueba la ruta de la imagen en la carpeta de interfaz
                $img_path_real = "../evt_interfaz/" . $e['imagen'];
                if(file_exists($img_path_real)) {
                    $img = $img_path_real;
                }
            }
        ?>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 bg-light">
                <?php if($img): ?><img src="<?= htmlspecialchars($img) ?>" class="card-img-top" style="filter:grayscale(1);opacity:0.7"><?php else: ?><div class="card-img-top bg-secondary" style="opacity:0.7"></div><?php endif; ?>
                <div class="card-body">
                    <h6 class="card-title text-muted"><?= htmlspecialchars($e['titulo']) ?> <span class="badge-finalizado">ARCHIVADO</span></h6>
                    <p class="small text-muted mb-0">Cerró: <?= date('d/m/y', strtotime($e['cierre_venta'])) ?></p>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 text-end">
                    <button class="btn btn-primary btn-sm" onclick="conf('reactivar', <?= $e['id_evento'] ?>, '<?= addslashes($e['titulo']) ?>')"><i class="bi bi-copy"></i> Reactivar</button>
                    <button class="btn btn-dark btn-sm" onclick="conf('borrar_permanente', <?= $e['id_evento'] ?>, '<?= addslashes($e['titulo']) ?>')"><i class="bi bi-x-lg"></i> Borrar</button>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="col-12"><p class="text-muted">Historial vacío.</p></div>
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
        case 'reactivar': 
            t='Reactivar Evento'; 
            els.msg.innerHTML=`¿Reactivar <strong>${nom}</strong>?`; 
            w='El evento se moverá a "Activos" para que edites las nuevas fechas. Se eliminará del historial.'; 
            c='btn-primary';
            isCritical = true;
            break;
        case 'borrar_permanente': 
            t='Borrado Definitivo'; 
            els.msg.innerHTML=`¿Borrar <strong>${nom}</strong> permanentemente?`; 
            w='Se eliminará del histórico. ESTA ACCIÓN NO SE PUEDE DESHACER.'; 
            c='btn-dark'; 
            isCritical=true; 
            break;
    }
    els.title.textContent=t; els.txt.textContent=w; els.auth.classList.toggle('d-none',!req); els.btn.className=`btn fw-bold ${c}`;
    
    els.btn.onclick = () => {
        if(isCritical) {
            if(req && (!els.user.value || !els.pin.value)) return alert('Faltan credenciales');
            
            els.cont.parentNode.classList.add('modal-danger-mode');
            els.head.classList.add('bg-danger', 'text-white');
            els.close.classList.add('btn-close-white');
            els.title.innerHTML = '<i class="bi bi-exclamation-octagon-fill me-2"></i>¡ADVERTENCIA FINAL!';

            if (act === 'reactivar') {
                 els.msg.innerHTML = `<h4 class="text-primary fw-bold text-center my-3">¿CONFIRMAS LA REACTIVACIÓN?</h4><p class="text-center mb-0">El evento <strong>${nom}</strong> se moverá a producción.</p>`;
                els.warn.className = 'alert alert-primary border-0 fw-bold text-center d-block';
                els.txt.textContent = 'ACCIÓN DE REACTIVACIÓN.';
                els.btn.className = 'btn btn-primary fw-bold w-100 py-3 fs-5';
                els.btn.innerHTML = '<i class="bi bi-copy me-2"></i>REACTIVAR AHORA';
            } else if (act === 'borrar_permanente') {
                els.msg.innerHTML = `<h4 class="text-danger fw-bold text-center my-3">¿BORRADO PERMANENTE?</h4><p class="text-center mb-0">Se eliminarán todos los datos de <strong>${nom}</strong> del histórico.</p><p class="text-center fw-bold text-danger mt-2">¡DATOS IRRECUPERABLES!</p>`;
                els.warn.className = 'alert alert-danger border-0 fw-bold text-center d-block';
                els.txt.textContent = 'ACCIÓN DESTRUCTIVA.';
                els.btn.className = 'btn btn-danger fw-bold w-100 py-3 fs-5';
                els.btn.innerHTML = '<i class="bi bi-trash3-fill me-2"></i>BORRAR DEFINITIVAMENTE';
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
                if(d.new_id) {
                    // Redirige el iframe a editar_evento.php
                    window.location.href = `editar_evento.php?id=${d.new_id}&es_nuevo=1`;
                } else {
                    alert('Error: No se recibió el nuevo ID del evento.');
                }
            } else {
                // Para 'borrar_permanente', solo recarga la página
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