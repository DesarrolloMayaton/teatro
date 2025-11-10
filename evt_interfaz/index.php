<?php
include "../conexion.php";

// ==================================================================
// == FUNCIONES DE AYUDA (HELPER FUNCTIONS) ==
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
                $ruta_fisica = __DIR__ . '/../' . $row['qr_path'];
                if (file_exists($ruta_fisica)) {
                    unlink($ruta_fisica); // Borra el archivo QR
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
// == INICIO: PROCESADOR DE ACCIONES (AJAX / POST) ==
// ==================================================================

// --- ACCIÓN 1: FINALIZAR UN EVENTO (MANUAL) ---
if (isset($_POST['accion']) && $_POST['accion'] == 'finalizar') {
    $id = $_POST['id_evento'];
    
    // Limpiamos datos asociados al finalizar manualmente
    limpiar_datos_asociados($id, $conn);

    if ($stmt = $conn->prepare("UPDATE evento SET finalizado=1 WHERE id_evento = ?")) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'accion' => 'finalizado']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al preparar la consulta.']);
    }
    exit;
}

// --- ACCIÓN 2: BORRAR UN EVENTO (TOTAL Y SEGURO) ---
if (isset($_POST['accion']) && $_POST['accion'] == 'borrar') {
    $id = $_POST['id_evento'];
    $usuario_nombre = $_POST['auth_user'] ?? '';
    $pin_ingresado = $_POST['auth_pin'] ?? '';

    // 1. VERIFICACIÓN DE SEGURIDAD
    $auth_ok = false;
    $sql_auth = "SELECT id_usuario FROM usuario WHERE nombre = ? AND pin = ?";
    if ($stmt_auth = $conn->prepare($sql_auth)) {
        $stmt_auth->bind_param("ss", $usuario_nombre, $pin_ingresado);
        $stmt_auth->execute();
        $stmt_auth->store_result();
        if ($stmt_auth->num_rows > 0) {
            $auth_ok = true;
        }
        $stmt_auth->close();
    }

    if (!$auth_ok) {
        echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas.']);
        exit;
    }

    // 2. BORRADO TOTAL AUTORIZADO
    $conn->begin_transaction();
    try {
        // Borrar imagen de cartelera
        $stmt_img = $conn->prepare("SELECT imagen FROM evento WHERE id_evento = ?");
        $stmt_img->bind_param("i", $id);
        $stmt_img->execute();
        $res_img = $stmt_img->get_result();
        if ($row_img = $res_img->fetch_assoc()) {
            if (!empty($row_img['imagen'])) {
                $ruta_imagen = __DIR__ . '/../' . $row_img['imagen'];
                if (file_exists($ruta_imagen)) {
                    unlink($ruta_imagen);
                }
            }
        }
        $stmt_img->close();

        // Limpieza profunda
        limpiar_datos_asociados($id, $conn);
        
        // Borrar evento principal
        $conn->query("DELETE FROM evento WHERE id_evento = $id");

        $conn->commit();
        echo json_encode(['status' => 'success', 'accion' => 'borrado']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Error al borrar: ' . $e->getMessage()]);
    }
    exit;
}

// ==================================================================
// == AUTOMATIZACIÓN: FINALIZAR Y LIMPIAR ==
// ==================================================================
$sql_vencidos = "
SELECT e.id_evento 
FROM evento e
LEFT JOIN (
    SELECT id_evento, MAX(fecha_hora) as ultima_funcion
    FROM funciones
    GROUP BY id_evento
) lf ON e.id_evento = lf.id_evento
WHERE 
    e.finalizado = 0 
    AND (
        (lf.ultima_funcion IS NOT NULL AND lf.ultima_funcion < NOW()) 
        OR 
        (lf.ultima_funcion IS NULL AND e.cierre_venta < NOW())
    )
";
$res_vencidos = $conn->query($sql_vencidos);
if ($res_vencidos && $res_vencidos->num_rows > 0) {
    while ($row = $res_vencidos->fetch_assoc()) {
        limpiar_datos_asociados($row['id_evento'], $conn);
        $conn->query("UPDATE evento SET finalizado = 1 WHERE id_evento = " . $row['id_evento']);
    }
}

// --- CARGA DE DATOS PARA LA INTERFAZ ---
$eventos_activos = $conn->query("SELECT * FROM evento WHERE finalizado=0 ORDER BY inicio_venta DESC");
$sql_finalizados = "SELECT * FROM evento WHERE finalizado=1 ORDER BY cierre_venta DESC";
$eventos_finalizados = $conn->query($sql_finalizados);

if ($eventos_finalizados === false) {
    die("Error en la consulta de eventos finalizados: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Dashboard de Eventos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    body, html { height: 100%; margin: 0; background-color: #f8f9fa; }
    .main-container { display: flex; min-height: 100vh; }
    .sidebar { 
        width: 280px; 
        background-color: #2c3e50; 
        padding: 20px; 
        color: #fff; 
        flex-shrink: 0; 
    }
    .sidebar .nav-link { 
        color: #ced4da; 
        font-size: 1.05em; 
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        padding: 12px 18px;
        border-radius: 8px; 
        transition: all 0.2s ease-in-out;
    }
    .sidebar .nav-link i {
        margin-right: 12px; 
        font-size: 1.1em;
        width: 25px;
    }
    .sidebar .nav-link:hover { 
        background-color: #34495e;
        color: #ffffff;
    }
    .sidebar .nav-link.active { 
        background-color: #f8f9fa; 
        color: #2c3e50; 
        font-weight: 600; 
    }
    .btn-agregar {
        display: block;
        width: 100%;
        padding: 12px;
        background-color: #198754; 
        color: #fff;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        text-align: center;
        transition: background-color 0.2s ease;
    }
    .btn-agregar:hover {
        background-color: #157347;
        color: #fff;
        text-decoration: none;
    }
    
    .content { flex-grow: 1; padding: 30px; height: 100vh; overflow-y: auto; }
    .card-img-top { height: 200px; object-fit: cover; }
    .card { margin-bottom: 20px; height: 100%; }
    .card-body { display: flex; flex-direction: column; }
    .card-text { flex-grow: 1; line-height: 1.6; }
    .card-text strong { color: #333; }
    .card-footer { background: #fff; border-top: none; padding-top: 0; }
</style>
</head>
<body>

<div class="main-container">

    <div class="sidebar">
        
        <ul class="nav nav-pills flex-column mb-auto" style="padding-top: 10px;">
            <li class="nav-item">
                <a href="#eventos-activos" class="nav-link active" data-bs-toggle="tab">
                    <i class="bi bi-calendar-check"></i> Eventos Activos
                </a>
            </li>
            <li class="nav-item">
                <a href="#historial" class="nav-link" data-bs-toggle="tab">
                    <i class="bi bi-archive"></i> Historial
                </a>
            </li>
            <li class="nav-item mt-3 pt-3 border-top border-secondary">
                <a href="crear_evento.php" class="btn-agregar">
                    <i class="bi bi-plus-circle-fill"></i> Agregar Nuevo Evento
                </a>
            </li>
        </ul>
    </div>

    <div class="content">
        
        <h2>Gestión de Eventos</h2>

        <div class="tab-content mt-3" id="eventosTabContent">

            <div class="tab-pane fade show active" id="eventos-activos" role="tabpanel">
                <div class="row" id="lista-eventos-activos">
                    <?php if($eventos_activos && $eventos_activos->num_rows > 0) {
                        while($e = $eventos_activos->fetch_assoc()) { 
                        // Formatear fechas de venta y cierre
                        $fecha_venta = date('M d, Y - h:i A', strtotime($e['inicio_venta']));
                        $fecha_cierre = date('M d, Y - h:i A', strtotime($e['cierre_venta']));
                        $tipo_escenario = ($e['tipo']==1 ? 'Escenario completo (420)' : 'Escenario pasarela (540)');
                        
                        // Necesitamos buscar las funciones para este evento
                        $funciones_evento = [];
                        $stmt_func = $conn->prepare("SELECT fecha_hora FROM funciones WHERE id_evento = ? ORDER BY fecha_hora ASC");
                        $stmt_func->bind_param("i", $e['id_evento']);
                        $stmt_func->execute();
                        $res_func = $stmt_func->get_result();
                        while($f = $res_func->fetch_assoc()){
                            $funciones_evento[] = date('M d, Y - h:i A', strtotime($f['fecha_hora']));
                        }
                        $stmt_func->close();

                        ?>
                        
                        <div class="col-lg-4 col-md-6 mb-4" id="evento-card-<?= $e['id_evento'] ?>">
                            <div class="card shadow-sm">
                                <?php 
                                    $imgMostrar = '';
                                    if (!empty($e['imagen'])) {
                                        // Ajustar ruta relativa para mostrar desde esta carpeta
                                        $ruta_relativa = '../' . $e['imagen'];
                                        if (file_exists(__DIR__ . '/' . $ruta_relativa)) {
                                            $imgMostrar = $ruta_relativa;
                                        }
                                    }
                                    if ($imgMostrar) echo "<img src='" . htmlspecialchars($imgMostrar, ENT_QUOTES, 'UTF-8') . "' class='card-img-top'>";
                                    else echo "<div class='card-img-top bg-secondary d-flex align-items-center justify-content-center text-white'><i class='bi bi-image fs-1'></i></div>";
                                ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($e['titulo']) ?></h5>
                                    <p class="card-text">
                                        <strong>Funciones:</strong><br>
                                        <?php 
                                            if(empty($funciones_evento)){
                                                echo "<small class='text-danger'>No hay funciones asignadas.</small><br>";
                                            } else {
                                                foreach($funciones_evento as $fecha_func){
                                                    echo "<small>• {$fecha_func}</small><br>";
                                                }
                                            }
                                        ?>
                                        <strong>Venta:</strong> <?= $fecha_venta ?><br>
                                        <strong>Cierre:</strong> <?= $fecha_cierre ?><br>
                                        <strong>Tipo:</strong> <?= $tipo_escenario ?> asientos
                                    </p>
                                </div>
                                <div class="card-footer d-flex justify-content-between">
                                    <a href="editar_evento.php?id=<?= $e['id_evento'] ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <button type="button" class="btn btn-success btn-sm" onclick="prepararFinalizar(<?= $e['id_evento'] ?>, '<?= htmlspecialchars(addslashes($e['titulo'])) ?>')">Finalizar</button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="prepararBorrado(<?= $e['id_evento'] ?>, '<?= htmlspecialchars(addslashes($e['titulo'])) ?>')">Borrar</button>
                                </div>
                            </div>
                        </div>
                    <?php } } else { echo "<p class='text-muted'>No hay eventos activos programados.</p>"; } ?>
                </div>
            </div>

            <div class="tab-pane fade" id="historial" role="tabpanel">
                <div class="row mt-3">
                    <?php if($eventos_finalizados && $eventos_finalizados->num_rows > 0) {
                        while($e = $eventos_finalizados->fetch_assoc()) { 
                        // Formatear fechas
                        $fecha_cierre_hist = date('M d, Y - h:i A', strtotime($e['cierre_venta']));
                        $tipo_escenario_hist = ($e['tipo']==1 ? 'Escenario completo (420)' : 'Escenario pasarela (540)');
                        
                        // Buscar la última función para mostrarla (opcional)
                        $ultima_funcion_hist = "N/A";
                        $stmt_uf = $conn->prepare("SELECT MAX(fecha_hora) as ultima FROM funciones WHERE id_evento = ?");
                        $stmt_uf->bind_param("i", $e['id_evento']);
                        $stmt_uf->execute();
                        $res_uf = $stmt_uf->get_result();
                        if($row_uf = $res_uf->fetch_assoc()){
                            if($row_uf['ultima']){
                                $ultima_funcion_hist = date('M d, Y - h:i A', strtotime($row_uf['ultima']));
                            }
                        }
                        $stmt_uf->close();
                        
                        ?>
                        
                        <div class="col-lg-4 col-md-6 mb-4" id="evento-card-<?= $e['id_evento'] ?>">
                            <div class="card shadow-sm bg-light">
                                <?php 
                                    $imgMostrar = '';
                                    if (!empty($e['imagen'])) {
                                        $ruta_relativa = '../' . $e['imagen'];
                                        if (file_exists(__DIR__ . '/' . $ruta_relativa)) {
                                            $imgMostrar = $ruta_relativa;
                                        }
                                    }
                                    if ($imgMostrar) echo "<img src='" . htmlspecialchars($imgMostrar, ENT_QUOTES, 'UTF-8') . "' class='card-img-top' style='opacity: 0.6;'>";
                                    else echo "<div class='card-img-top bg-secondary d-flex align-items-center justify-content-center text-white' style='opacity: 0.6;'><i class='bi bi-image fs-1'></i></div>";
                                ?>
                                <div class="card-body">
                                    <h5 class="card-title text-muted"><?= htmlspecialchars($e['titulo']) ?></h5>
                                    <p class="card-text">
                                        <strong>Última función:</strong> <?= $ultima_funcion_hist ?><br>
                                        <strong>Cierre Venta:</strong> <?= $fecha_cierre_hist ?><br>
                                        <strong>Tipo:</strong> <?= $tipo_escenario_hist ?> asientos
                                    </p>
                                </div>
                                <div class="card-footer d-flex justify-content-between">
                                    <a href="editar_evento.php?id=<?= $e['id_evento'] ?>" class="btn btn-primary btn-sm">Reactivar</a>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="prepararBorrado(<?= $e['id_evento'] ?>, '<?= htmlspecialchars(addslashes($e['titulo'])) ?>')">Borrar</button>
                                </div>
                            </div>
                        </div>
                    <?php } } else { echo "<p class='text-muted'>Aún no hay eventos en el historial.</p>"; } ?>
                </div>
            </div>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalFinalizar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Finalizar Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Finalizar <strong><span id="nombreEventoFinalizar"></span></strong>?</p>
                <div class="alert alert-warning small">
                    <i class="bi bi-info-circle-fill"></i> Esto moverá el evento al historial y <strong>eliminará permanentemente</strong> todos los boletos generados y sus códigos QR para liberar espacio.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmarFinalizar" class="btn btn-warning">Sí, Finalizar y Limpiar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBorrar" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-shield-lock-fill me-2"></i>Borrado Seguro Requerido</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger fw-bold">ESTA ACCIÓN ES DESTRUCTIVA E IRREVERSIBLE.</p>
                <p>Para eliminar el evento <strong><span id="nombreEventoBorrar"></span></strong>, ingrese sus credenciales.</p>
                <div class="form-floating mb-2">
                    <input type="text" class="form-control" id="auth_user" placeholder="Usuario" autocomplete="off">
                    <label>Usuario Administrador</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="auth_pin" placeholder="PIN" autocomplete="new-password">
                    <label>PIN de Seguridad</label>
                </div>
                <div id="borrar-error-msg" class="text-danger small fw-bold"></div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmarBorrado" class="btn btn-danger px-4"><i class="bi bi-trash-fill me-2"></i>AUTORIZAR BORRADO</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modalBorrar = new bootstrap.Modal(document.getElementById('modalBorrar'));
const modalFinalizar = new bootstrap.Modal(document.getElementById('modalFinalizar'));

function prepararFinalizar(id, nombre) {
    document.getElementById('nombreEventoFinalizar').textContent = nombre;
    document.getElementById('btnConfirmarFinalizar').onclick = () => ejecutarAccion('finalizar', id, null, modalFinalizar);
    modalFinalizar.show();
}

function prepararBorrado(id, nombre) {
    document.getElementById('nombreEventoBorrar').textContent = nombre;
    document.getElementById('auth_user').value = '';
    document.getElementById('auth_pin').value = '';
    document.getElementById('borrar-error-msg').textContent = '';
    
    document.getElementById('btnConfirmarBorrado').onclick = () => {
        const user = document.getElementById('auth_user').value.trim();
        const pin = document.getElementById('auth_pin').value.trim();
        if(!user || !pin) {
            document.getElementById('borrar-error-msg').textContent = 'Ingrese Usuario y PIN.';
            return;
        }
        ejecutarAccion('borrar', id, { auth_user: user, auth_pin: pin }, modalBorrar, 'borrar-error-msg');
    };
    modalBorrar.show();
}

function ejecutarAccion(accion, id, extras, modal, errorDivId = null) {
    const modalEl = modal._element;
    const btn = modalEl.querySelector('.modal-footer button:last-child');
    const txtOriginal = btn.innerHTML;
    
    btn.disabled = true; 
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';
    if(errorDivId) document.getElementById(errorDivId).textContent = '';

    const formData = new FormData();
    formData.append('accion', accion);
    formData.append('id_evento', id);
    if(extras) { for(const key in extras) formData.append(key, extras[key]); }

    fetch('index.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            // Sincronizar otras pestañas si están abiertas
            localStorage.setItem('evento_actualizado', accion + '_' + id + '_' + Date.now());
            window.location.reload();
        } else {
            if(errorDivId) document.getElementById(errorDivId).textContent = data.message;
            else alert(data.message);
            btn.disabled = false; 
            btn.innerHTML = txtOriginal;
        }
    })
    .catch(e => {
        console.error(e);
        alert('Error de conexión con el servidor.');
        btn.disabled = false; 
        btn.innerHTML = txtOriginal;
    });
}

// Listener para recargar si otra pestaña hizo cambios
window.addEventListener('storage', function(e) {
    if (e.key === 'evento_actualizado') {
        console.log('Cambio detectado externamente, recargando...');
        window.location.reload();
    }
});
</script>
</body>
</html>