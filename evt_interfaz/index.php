<?php
include "../conexion.php";

// ==================================================================
// == INICIO: PROCESADOR DE ACCIONES (AJAX / POST) ==
// ==================================================================

// --- ACCIÓN 1: FINALIZAR UN EVENTO ---
if (isset($_POST['accion']) && $_POST['accion'] == 'finalizar') {
    $id = $_POST['id_evento'];
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

// --- ACCIÓN 2: BORRAR UN EVENTO ---
if (isset($_POST['accion']) && $_POST['accion'] == 'borrar') {
    $id = $_POST['id_evento'];
    // Borrar imagen si existe
    if ($stmt_img = $conn->prepare("SELECT imagen FROM evento WHERE id_evento = ?")) {
        $stmt_img->bind_param("i", $id);
        $stmt_img->execute();
        $res = $stmt_img->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['imagen']) && file_exists($row['imagen'])) {
                unlink($row['imagen']);
            }
        }
        $stmt_img->close();
    }
    // Borrar evento (y funciones asociadas por ON DELETE CASCADE)
    if ($stmt = $conn->prepare("DELETE FROM evento WHERE id_evento = ?")) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'accion' => 'borrado']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al preparar la consulta de borrado.']);
    }
    exit;
}

// ==================================================================
// == FIN: PROCESADOR DE ACCIONES ==
// ==================================================================


// --- LÓGICA NORMAL DE CARGA DE PÁGINA ---
// $conn->query("UPDATE evento SET finalizado=1 WHERE inicio_show <= NOW() AND finalizado=0"); // <-- LÍNEA ELIMINADA

// Consulta para eventos activos (finalizado = 0)
$eventos_activos = $conn->query("SELECT * FROM evento WHERE finalizado=0 ORDER BY inicio_venta DESC");

// Consulta para eventos finalizados (finalizado = 1)
$sql_finalizados = "SELECT * FROM evento WHERE finalizado=1 ORDER BY cierre_venta DESC"; // Ordenamos por cierre_venta ahora
$eventos_finalizados = $conn->query($sql_finalizados);

// Verificamos si la consulta de finalizados falló
if ($eventos_finalizados === false) {
    echo "<h1>Error en la consulta de eventos finalizados:</h1>";
    echo "<p>Error: " . $conn->error . "</p>";
    echo "<p>Consulta: " . $sql_finalizados . "</p>";
    die(); // Detenemos la ejecución para ver el error
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
        background-color: #495057;
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
                                <?php if($e['imagen']) echo "<img src='{$e['imagen']}' class='card-img-top'>"; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($e['titulo']) ?></h5>
                                    <p class="card-text">
                                        <strong>Funciones:</strong><br>
                                        <?php 
                                            if(empty($funciones_evento)){
                                                echo "<small>No hay funciones asignadas.</small><br>";
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
                                <?php if($e['imagen']) echo "<img src='{$e['imagen']}' class='card-img-top' style='opacity: 0.6;'>"; ?>
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

<div class="modal fade" id="modalBorrar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Borrado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que quieres eliminar permanentemente el evento?</p>
                <p class="text-danger fw-bold" id="nombreEventoBorrar"></p>
                <p class="small text-muted">Esta acción no se puede deshacer y borrará todas las funciones asociadas.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmarBorrado" class="btn btn-danger">Sí, Borrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFinalizar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Finalización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que quieres finalizar manualmente el evento?</p>
                <p class="text-success fw-bold" id="nombreEventoFinalizar"></p>
                <p class="small text-muted">El evento se moverá al historial.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmarFinalizar" class="btn btn-success">Sí, Finalizar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Instancias de los Modales
const modalBorrar = new bootstrap.Modal(document.getElementById('modalBorrar'));
const modalFinalizar = new bootstrap.Modal(document.getElementById('modalFinalizar'));

// Función para FINALIZAR
function prepararFinalizar(id, nombre) {
    document.getElementById('nombreEventoFinalizar').textContent = `"${nombre}"`;
    document.getElementById('btnConfirmarFinalizar').onclick = function() {
        const formData = new FormData();
        formData.append('accion', 'finalizar');
        formData.append('id_evento', id);

        fetch('index.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                modalFinalizar.hide();
                // Opcional: Recargar la página para ver el cambio
                 window.location.reload(); 
                // O mover la tarjeta al historial manualmente con JS (más complejo)
                // const card = document.getElementById(`evento-card-${id}`);
                // if(card) card.remove(); 
            } else { alert('Error al finalizar: ' + (data.message || 'Error desconocido')); }
        })
        .catch(error => {
             console.error('Error en fetch:', error);
             alert('Ocurrió un error de red al intentar finalizar.');
        });
    }
    modalFinalizar.show();
}

// Función para BORRAR
function prepararBorrado(id, nombre) {
    document.getElementById('nombreEventoBorrar').textContent = `"${nombre}"`;
    document.getElementById('btnConfirmarBorrado').onclick = function() {
        const formData = new FormData();
        formData.append('accion', 'borrar');
        formData.append('id_evento', id);

        fetch('index.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                modalBorrar.hide();
                const card = document.getElementById(`evento-card-${id}`);
                if(card) card.remove();
            } else { alert('Error al borrar: ' + (data.message || 'Error desconocido')); }
        })
         .catch(error => {
             console.error('Error en fetch:', error);
             alert('Ocurrió un error de red al intentar borrar.');
        });
    }
    modalBorrar.show();
}
</script>

</body>
</html>