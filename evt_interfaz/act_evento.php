<?php
// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "../conexion.php";

if(file_exists("../transacciones_helper.php")) {
    require_once "../transacciones_helper.php";
}

require_once __DIR__ . "/../api/registrar_cambio.php";

// ==================================================================
// VERIFICACIÓN DE SESIÓN
// ==================================================================
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px; color: red;"><h1>Acceso Denegado</h1><p>No tiene permiso para ver esta página.</p></div>');
}

// ==================================================================
// ACTUALIZACIÓN AUTOMÁTICA DE ESTADOS DE FUNCIONES
// ==================================================================
// Marca como '1' (Finalizada) las funciones que ya pasaron de fecha/hora
$conn->query("UPDATE funciones SET estado = 1 WHERE fecha_hora < NOW() AND estado = 0");


// ==================================================================
// FUNCIÓN PARA ARCHIVAR (MOVER A HISTÓRICO)
// Copia TODA la información a trt_historico_evento y borra de producción
// ==================================================================
function archivar_evento_completo($id, $conn) {
    // Nombre de la base de datos histórica
    $db_historico = 'trt_historico_evento';
    $db_principal = 'trt_25';
    
    // 1. COPIAR TODO A HISTÓRICO (en orden de dependencias)
    // Primero el evento
    $result = $conn->query("INSERT IGNORE INTO {$db_historico}.evento SELECT * FROM {$db_principal}.evento WHERE id_evento = $id");
    if (!$result) {
        throw new Exception("Error copiando evento: " . $conn->error);
    }
    
    // Funciones del evento
    $result = $conn->query("INSERT IGNORE INTO {$db_historico}.funciones SELECT * FROM {$db_principal}.funciones WHERE id_evento = $id");
    if (!$result) {
        throw new Exception("Error copiando funciones: " . $conn->error);
    }
    
    // Categorías
    $result = $conn->query("INSERT IGNORE INTO {$db_historico}.categorias SELECT * FROM {$db_principal}.categorias WHERE id_evento = $id");
    if (!$result) {
        throw new Exception("Error copiando categorías: " . $conn->error);
    }
    
    // Promociones/Descuentos
    $result = $conn->query("INSERT IGNORE INTO {$db_historico}.promociones SELECT * FROM {$db_principal}.promociones WHERE id_evento = $id");
    if (!$result) {
        throw new Exception("Error copiando promociones: " . $conn->error);
    }
    
    // Boletos (todos los vendidos)
    $result = $conn->query("INSERT IGNORE INTO {$db_historico}.boletos SELECT * FROM {$db_principal}.boletos WHERE id_evento = $id");
    if (!$result) {
        throw new Exception("Error copiando boletos: " . $conn->error);
    }

    // 2. BORRAR DE PRODUCCIÓN (en orden inverso de dependencias)
    $conn->query("DELETE FROM {$db_principal}.boletos WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.promociones WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.categorias WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.funciones WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.evento WHERE id_evento = $id");
    
    return true;
}

// ==================================================================
// PROCESADOR AJAX
// ==================================================================
if (isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id_evento'] ?? 0);
    
    $conn->begin_transaction();
    try {
        if($_POST['accion'] === 'finalizar') {
            $user = $_POST['auth_user'] ?? '';
            $pass = $_POST['auth_pass'] ?? '';

            // Buscar admin por nombre (sin comparar contraseña en SQL)
            $stmt = $conn->prepare("SELECT id_usuario, password FROM usuarios WHERE nombre = ? AND rol = 'admin' AND activo = 1");
            $stmt->bind_param("s", $user);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Usuario no encontrado o no es administrador.");
            }
            
            $admin = $result->fetch_assoc();
            
            // Verificar contraseña con hash seguro
            if (!password_verify($pass, $admin['password'])) {
                throw new Exception("Contraseña incorrecta.");
            }
            $stmt->close();

            archivar_evento_completo($id, $conn);
            
            if(function_exists('registrar_transaccion')) {
                registrar_transaccion('evento_archivar', 'Archivó evento ID ' . $id . ' (Autorizado por: '.$user.')');
            }
            
            // Notificar cambio para auto-actualización en tiempo real
            registrar_cambio('evento', $id, null, ['accion' => 'archivar']);
        }
        $conn->commit();
        echo json_encode(['status'=>'success']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
    }
    exit;
}

// CARGA DE DATOS (SOLO ACTIVOS)
$activos = $conn->query("SELECT * FROM trt_25.evento WHERE finalizado = 0 ORDER BY inicio_venta DESC");
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
    
    .card-img-container {
        width: 100%;
        aspect-ratio: 3 / 4; 
        background-color: #2b2b2b; 
        position: relative;
        display: flex; align-items: center; justify-content: center;
    }
    
    .card-img-top { 
        width: 100%; height: 100%; object-fit: cover; 
        transition: transform 0.4s;
    }
    .card:hover .card-img-top { transform: scale(1.08); }

    .card-body { padding: 0.8rem; display: flex; flex-direction: column; gap: 5px; } 
    .card-title { 
        font-size: 0.95rem; 
        margin-bottom: 0.2rem; 
        line-height: 1.2; 
        color: var(--text-primary) !important;
    } 
    
    /* --- CONTENEDOR DE FUNCIONES --- */
    .funcs-container {
        display: flex; flex-wrap: wrap; gap: 4px;
        max-height: 65px; overflow-y: auto; 
        padding-right: 2px;
    }
    .funcs-container::-webkit-scrollbar { width: 3px; }
    .funcs-container::-webkit-scrollbar-thumb { background: #3a3a3c; border-radius: 3px; }

    /* Estilos dinámicos para badges de función */
    .func-badge {
        font-size: 0.65rem; 
        padding: 2px 6px; border-radius: 4px;
        white-space: nowrap; font-weight: 600;
        display: inline-flex; align-items: center; gap: 3px;
    }
    
    .func-badge.active {
        background: #2b2b2b; 
        color: #ffffff; 
        border: 1px solid #3a3a3c;
    }
    
    /* Estilo para funciones vencidas (estado 1) */
    .func-badge.expired {
        background: rgba(255, 69, 58, 0.2); 
        color: #ff453a; 
        border: 1px solid rgba(255, 69, 58, 0.4);
        text-decoration: line-through; 
        opacity: 0.7;
    }
    
    .card-footer { 
        padding: 0.6rem 0.8rem; 
        background: var(--bg-card) !important;
        border-top: 1px solid var(--border-color) !important;
    }
    .btn-sm-custom { padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 6px; }

    /* Título y badge */
    .text-primary { color: var(--primary-color) !important; }
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
    .btn-outline-danger {
        border-color: #ff453a !important;
        color: #ff453a !important;
    }
    .btn-outline-danger:hover {
        background: #ff453a !important;
        color: white !important;
    }

    /* Estilos Modal Seguridad */
    .modal-auth-header { background-color: #ff453a; color: white; }
    .modal-auth-icon { font-size: 3rem; color: #ff453a; margin-bottom: 15px; }
    .modal-content { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); }
    .modal-header, .modal-footer { border-color: var(--border-color); }
    .form-control { background: #2b2b2b; border-color: var(--border-color); color: var(--text-primary); }
    .form-control:focus { background: #2b2b2b; border-color: var(--primary-color); color: var(--text-primary); }
    .form-label { color: var(--text-secondary); }
</style>
</head>
<body>

<div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-primary m-0">Eventos Activos</h4>
        <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $activos->num_rows ?> eventos</span>
    </div>

    <div class="row g-3">
        <?php if($activos && $activos->num_rows > 0): 
            $delay = 0;
            while($e = $activos->fetch_assoc()): 
                $delay += 30;
                $img = '';
                if (!empty($e['imagen'])) {
                    $rutas = ["../evt_interfaz/" . $e['imagen'], $e['imagen']];
                    foreach($rutas as $r) { if(file_exists($r)) { $img = $r; break; } }
                }

                $id_evt = $e['id_evento'];
                // Seleccionamos también el estado
                $sql_func = "SELECT fecha_hora, estado FROM funciones WHERE id_evento = $id_evt ORDER BY fecha_hora ASC";
                $res_func = $conn->query($sql_func);
                $funciones = [];
                if($res_func){
                    while($f = $res_func->fetch_assoc()){ $funciones[] = $f; }
                }
        ?>
        <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-4 col-6">
            <div class="card h-100" style="animation-delay: <?= $delay ?>ms">
                <div class="card-img-container">
                    <?php if($img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" class="card-img-top" loading="lazy">
                    <?php else: ?>
                        <div class="text-center text-muted"><i class="bi bi-image fs-4 opacity-50"></i></div>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                    <h6 class="card-title fw-bold text-dark text-truncate" title="<?= htmlspecialchars($e['titulo']) ?>">
                        <?= htmlspecialchars($e['titulo']) ?>
                    </h6>
                    
                    <div class="funcs-container">
                        <?php if(count($funciones) > 0): ?>
                            <?php foreach($funciones as $f): 
                                $esVencida = ($f['estado'] == 1);
                                $clase = $esVencida ? 'expired' : 'active';
                                $icono = $esVencida ? 'bi-hourglass-bottom' : 'bi-clock';
                            ?>
                                <span class="func-badge <?= $clase ?>">
                                    <i class="bi <?= $icono ?>" style="font-size:9px"></i>
                                    <?= date('d/m H:i', strtotime($f['fecha_hora'])) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="func-badge text-danger border-danger bg-danger-subtle">Sin funciones</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
                    <button onclick="window.location.href='editar_evento.php?id=<?= $e['id_evento'] ?>'" class="btn btn-outline-primary btn-sm-custom w-100 me-1">
                        <i class="bi bi-pencil"></i>
                    </button>
                    
                    <button class="btn btn-light text-danger btn-sm-custom" onclick="prepararArchivado(<?= $e['id_evento'] ?>, '<?= addslashes($e['titulo']) ?>')" title="Mover al Historial">
                        <i class="bi bi-archive"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="col-12">
            <div class="alert alert-light border text-center p-5">
                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                <p class="mb-0 text-muted">No hay eventos activos.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalArchivar" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-lock-fill me-2"></i>Zona de Seguridad</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="pasoAdvertencia">
                    <div class="text-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-center fw-bold mb-3">¿Estás seguro?</h5>
                    <p class="text-center text-muted">
                        Estás a punto de archivar el evento: <br>
                        <strong id="nombreEventoArchivar" class="text-dark fs-5"></strong>
                    </p>
                    <div class="alert alert-warning small border-0">
                        <i class="bi bi-info-circle me-1"></i>
                        El evento dejará de ser visible. Se moverá al histórico.
                    </div>
                    <button class="btn btn-danger w-100 py-2 fw-bold" onclick="mostrarPasoAuth()">
                        Continuar y Autorizar <i class="bi bi-arrow-right"></i>
                    </button>
                </div>

                <div id="pasoAuth" style="display: none;">
                    <div class="text-center mb-3">
                        <i class="bi bi-person-lock text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="text-center fw-bold mb-3">Autorización de Administrador</h6>
                    <p class="text-center small text-muted mb-4">Para confirmar, ingresa tus credenciales.</p>
                    
                    <div class="mb-3">
                        <input type="text" id="auth_user" class="form-control form-control-lg" placeholder="Usuario Admin">
                    </div>
                    <div class="mb-4">
                        <input type="password" id="auth_pass" class="form-control form-control-lg" placeholder="Contraseña / PIN">
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button class="btn btn-light w-50" onclick="volverPasoAdvertencia()">Atrás</button>
                        <button id="btnConfirmarFinal" class="btn btn-danger w-50 fw-bold" onclick="ejecutarArchivado()">
                            Confirmar Archivo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => document.body.classList.add('loaded'));

let eventoIdSeleccionado = null;
const modalElement = document.getElementById('modalArchivar');
const modal = new bootstrap.Modal(modalElement);

function prepararArchivado(id, titulo) {
    eventoIdSeleccionado = id;
    document.getElementById('nombreEventoArchivar').textContent = titulo;
    document.getElementById('pasoAdvertencia').style.display = 'block';
    document.getElementById('pasoAuth').style.display = 'none';
    document.getElementById('auth_user').value = '';
    document.getElementById('auth_pass').value = '';
    document.getElementById('btnConfirmarFinal').disabled = false;
    document.getElementById('btnConfirmarFinal').innerHTML = 'Confirmar Archivo';
    modal.show();
}

function mostrarPasoAuth() {
    document.getElementById('pasoAdvertencia').style.display = 'none';
    document.getElementById('pasoAuth').style.display = 'block';
    setTimeout(() => document.getElementById('auth_user').focus(), 100);
}

function volverPasoAdvertencia() {
    document.getElementById('pasoAuth').style.display = 'none';
    document.getElementById('pasoAdvertencia').style.display = 'block';
}

function ejecutarArchivado() {
    const user = document.getElementById('auth_user').value.trim();
    const pass = document.getElementById('auth_pass').value.trim();

    if (!user || !pass) {
        alert("Por favor, ingresa usuario y contraseña.");
        return;
    }

    const btn = document.getElementById('btnConfirmarFinal');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

    let fd = new FormData();
    fd.append('accion', 'finalizar');
    fd.append('id_evento', eventoIdSeleccionado);
    fd.append('auth_user', user);
    fd.append('auth_pass', pass);
    
    fetch('', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            modal.hide();
            location.reload(); 
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = 'Confirmar Archivo';
        }
    })
    .catch(() => {
        alert('Error de conexión.');
        btn.disabled = false;
        btn.innerHTML = 'Confirmar Archivo';
    });
}
</script>
</body>
</html>