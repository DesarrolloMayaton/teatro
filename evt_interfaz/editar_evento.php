<?php
// ════════════════════════════════════════════════════════════════════════════
// EDITAR EVENTO - VERSIÓN RENOVADA CON VALIDACIONES Y BLOQUEO POR VENTAS
// ════════════════════════════════════════════════════════════════════════════
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include "../conexion.php";
if(file_exists("../transacciones_helper.php")) { require_once "../transacciones_helper.php"; }
if(file_exists(__DIR__ . "/../api/registrar_cambio.php")) { require_once __DIR__ . "/../api/registrar_cambio.php"; }

// Verificar permisos
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="display:flex;height:100vh;align-items:center;justify-content:center;font-family:sans-serif;color:#ef4444;"><h1>Acceso Denegado</h1></div>');
}

$errores_php = [];
$modo_reactivacion = isset($_GET['modo_reactivacion']) && $_GET['modo_reactivacion'] == 1;
$id_evento = $_GET['id'] ?? 0;

if (!$id_evento || !is_numeric($id_evento)) { 
    header('Location: act_evento.php'); 
    exit; 
}

// ════════════════════════════════════════════════════════════════════════════
// CARGAR DATOS DEL EVENTO
// ════════════════════════════════════════════════════════════════════════════
$tabla_evt = $modo_reactivacion ? "trt_historico_evento.evento" : "evento";
$tabla_fun = $modo_reactivacion ? "trt_historico_evento.funciones" : "funciones";
$tabla_bol = $modo_reactivacion ? "trt_historico_evento.boletos" : "boletos";

$res = $conn->query("SELECT * FROM $tabla_evt WHERE id_evento = $id_evento");
$evento = $res->fetch_assoc();
if (!$evento) { header('Location: act_evento.php'); exit; }

// Cargar funciones existentes
$funciones_existentes = [];
$funciones_con_ventas = []; // IDs de funciones que tienen boletos vendidos
$res_f = $conn->query("SELECT id_funcion, fecha_hora FROM $tabla_fun WHERE id_evento = $id_evento ORDER BY fecha_hora ASC");
while($f = $res_f->fetch_assoc()) { 
    $funciones_existentes[] = [
        'id' => $f['id_funcion'],
        'fecha' => new DateTime($f['fecha_hora'])
    ];
}

// VERIFICAR BOLETOS VENDIDOS POR FUNCIÓN
$total_boletos_vendidos = 0;
if (!$modo_reactivacion) {
    $res_bol = $conn->query("SELECT id_funcion, COUNT(*) as vendidos FROM $tabla_bol WHERE id_evento = $id_evento AND estatus = 1 GROUP BY id_funcion");
    while($b = $res_bol->fetch_assoc()) {
        if ($b['vendidos'] > 0) {
            $funciones_con_ventas[$b['id_funcion']] = $b['vendidos'];
            $total_boletos_vendidos += $b['vendidos'];
        }
    }
}

$tiene_ventas = $total_boletos_vendidos > 0;

// Fechas por defecto (vacías si es reactivación)
$defaultVenta = $modo_reactivacion ? '' : date('Y-m-d H:i', strtotime($evento['inicio_venta']));
$defaultCierre = $modo_reactivacion ? '' : date('Y-m-d H:i', strtotime($evento['cierre_venta']));

// ════════════════════════════════════════════════════════════════════════════
// PROCESAR FORMULARIO (POST)
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
        
        // Si hay ventas en modo edición normal, verificar que se mantengan las funciones con ventas
        if ($tiene_ventas && !$modo_reactivacion) {
            $ids_funciones_enviados = $_POST['funciones_ids'] ?? [];
            foreach ($funciones_con_ventas as $id_func => $cantidad) {
                if (!in_array($id_func, $ids_funciones_enviados)) {
                    $errores_php[] = "No puedes eliminar la función con ID $id_func que tiene $cantidad boleto(s) vendido(s).";
                }
            }
        }
        
        $titulo = trim($_POST['titulo'] ?? '');
        $desc = trim($_POST['descripcion'] ?? '');
        $tipo = $_POST['tipo'] ?? '';
        $ini = $_POST['inicio_venta'] ?? '';
        $fin = $_POST['cierre_venta'] ?? '';
        $img = $_POST['imagen_actual'] ?? '';

        // Validaciones básicas
        if (empty($titulo)) $errores_php[] = "El título es obligatorio.";
        if (empty($desc)) $errores_php[] = "La descripción es obligatoria.";
        
        // Verificar que haya al menos una función (existente o nueva)
        $tiene_funciones = !empty($_POST['funciones_ids']) || !empty($_POST['funciones_nuevas']);
        if (!$tiene_funciones) $errores_php[] = "Debe agregar al menos una función.";
        
        if (empty($ini)) $errores_php[] = "Define la fecha de inicio de venta.";

        // Subida de imagen (opcional en edición)
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
             $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
             if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                 if (!is_dir("imagenes")) mkdir("imagenes", 0755, true);
                 $ruta = "imagenes/evt_" . time() . "." . $ext;
                 if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
                     if ($_POST['imagen_actual'] && file_exists($_POST['imagen_actual'])) unlink($_POST['imagen_actual']);
                     $img = $ruta;
                 }
             }
        }

        if (empty($errores_php)) {
            $conn->begin_transaction();
            try {
                if ($modo_reactivacion) {
                    // ═══════════════════════════════════════════════════════════
                    // MODO REACTIVACIÓN: Crear nuevo evento desde histórico
                    // ═══════════════════════════════════════════════════════════
                    $res_old = $conn->query("SELECT mapa_json FROM trt_historico_evento.evento WHERE id_evento = $id_evento");
                    $mapa_json = $res_old->fetch_object()->mapa_json;

                    $stmt = $conn->prepare("INSERT INTO evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado, mapa_json) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
                    $stmt->bind_param("sssisss", $titulo, $desc, $img, $tipo, $ini, $fin, $mapa_json);
                    $stmt->execute();
                    $new_id = $conn->insert_id;
                    $stmt->close();

                    // Copiar categorías
                    $conn->query("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) SELECT $new_id, nombre_categoria, precio, color FROM trt_historico_evento.categorias WHERE id_evento = $id_evento");
                    
                    // Insertar nuevas funciones
                    $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora) VALUES (?, ?)");
                    foreach ($_POST['funciones'] as $fh) { 
                        $stmt_f->bind_param("is", $new_id, $fh); 
                        $stmt_f->execute(); 
                    }

                    // Limpiar histórico
                    $conn->query("DELETE FROM trt_historico_evento.boletos WHERE id_evento = $id_evento");
                    $conn->query("DELETE FROM trt_historico_evento.promociones WHERE id_evento = $id_evento");
                    $conn->query("DELETE FROM trt_historico_evento.categorias WHERE id_evento = $id_evento");
                    $conn->query("DELETE FROM trt_historico_evento.funciones WHERE id_evento = $id_evento");
                    $conn->query("DELETE FROM trt_historico_evento.evento WHERE id_evento = $id_evento");

                    $evt_id = $new_id;
                    
                } else {
                    // ═══════════════════════════════════════════════════════════
                    // MODO EDICIÓN NORMAL
                    // ═══════════════════════════════════════════════════════════
                    $stmt = $conn->prepare("UPDATE evento SET titulo=?, inicio_venta=?, cierre_venta=?, descripcion=?, imagen=?, tipo=? WHERE id_evento=?");
                    $stmt->bind_param("sssssii", $titulo, $ini, $fin, $desc, $img, $tipo, $id_evento);
                    $stmt->execute();
                    
                    // Obtener IDs de funciones existentes enviadas y las nuevas fechas
                    $ids_a_mantener = $_POST['funciones_ids'] ?? [];
                    $fechas_nuevas = $_POST['funciones_nuevas'] ?? [];
                    
                    // Eliminar funciones que no están en la lista (excepto las con ventas)
                    if (!empty($ids_a_mantener)) {
                        $ids_str = implode(',', array_map('intval', $ids_a_mantener));
                        $conn->query("DELETE FROM funciones WHERE id_evento = $id_evento AND id_funcion NOT IN ($ids_str)");
                    } else {
                        // Si no hay IDs a mantener, eliminar todas (pero esto no debería pasar si hay ventas)
                        if (!$tiene_ventas) {
                            $conn->query("DELETE FROM funciones WHERE id_evento = $id_evento");
                        }
                    }
                    
                    // Insertar funciones nuevas
                    if (!empty($fechas_nuevas)) {
                        $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora) VALUES (?, ?)");
                        foreach ($fechas_nuevas as $fh) {
                            $stmt_f->bind_param("is", $id_evento, $fh); 
                            $stmt_f->execute();
                        }
                        $stmt_f->close();
                    }
                    
                    $evt_id = $id_evento;
                }

                $conn->commit();
                if(function_exists('registrar_transaccion')) {
                    registrar_transaccion('evento_guardar', ($modo_reactivacion ? "Reactivó" : "Editó") . " evento: $titulo");
                }
                if(function_exists('registrar_cambio')) {
                    registrar_cambio('evento', $evt_id, null, ['accion' => $modo_reactivacion ? 'reactivar' : 'editar']);
                }

                // PANTALLA DE ÉXITO
                ?>
                <!DOCTYPE html>
                <html lang="es">
                <head>
                <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
                <style>
                    body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: 'Inter', system-ui, sans-serif; overflow: hidden; }
                    .success-card { background: linear-gradient(145deg, #1e293b, #334155); border-radius: 24px; padding: 48px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); max-width: 420px; width: 90%; animation: popIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
                    .icon-circle { width: 88px; height: 88px; background: linear-gradient(135deg, #22c55e, #16a34a); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 44px; margin: 0 auto 24px; box-shadow: 0 0 0 8px rgba(34, 197, 94, 0.15); color: white; }
                    h4 { color: #f8fafc; font-weight: 700; }
                    p { color: #94a3b8; }
                    .progress-track { height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; margin-top: 32px; overflow: hidden; }
                    .progress-fill { height: 100%; background: linear-gradient(90deg, #22c55e, #4ade80); width: 0; transition: width 1.2s ease; }
                    @keyframes popIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
                </style>
                </head>
                <body>
                    <div class="success-card">
                        <div class="icon-circle"><i class="bi bi-check-lg"></i></div>
                        <h4><?= $modo_reactivacion ? '¡Evento Reactivado!' : '¡Cambios Guardados!' ?></h4>
                        <p class="small mb-0">Los datos se han actualizado correctamente.</p>
                        <div class="progress-track"><div class="progress-fill" id="pBar"></div></div>
                    </div>
                    <script>
                        setTimeout(() => document.getElementById('pBar').style.width = '100%', 50);
                        localStorage.setItem("evt_upd", Date.now());
                        setTimeout(() => window.parent.location.href = "index.php?tab=activos", 1500);
                    </script>
                </body>
                </html>
                <?php exit;

            } catch (Exception $e) { 
                $conn->rollback(); 
                $errores_php[] = "Error: " . $e->getMessage(); 
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $modo_reactivacion ? 'Reactivar' : 'Editar' ?> Evento</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-primary: #0f172a;
        --bg-secondary: #1e293b;
        --bg-tertiary: #334155;
        --bg-input: #1e293b;
        --accent: #3b82f6;
        --accent-hover: #2563eb;
        --accent-glow: rgba(59, 130, 246, 0.25);
        --success: #22c55e;
        --danger: #ef4444;
        --warning: #f59e0b;
        --text-primary: #f8fafc;
        --text-secondary: #94a3b8;
        --text-muted: #64748b;
        --border: #334155;
        --border-focus: #3b82f6;
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 16px;
        --radius-xl: 20px;
        --shadow-lg: 0 12px 40px rgba(0,0,0,0.5);
        --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * { box-sizing: border-box; }
    
    body {
        font-family: 'Inter', -apple-system, sans-serif;
        background: linear-gradient(135deg, var(--bg-primary) 0%, #1a1a2e 100%);
        color: var(--text-primary);
        padding: 32px 20px;
        min-height: 100vh;
        opacity: 0;
        transition: opacity 0.4s ease;
    }
    body.loaded { opacity: 1; }

    .main-wrapper { max-width: 900px; margin: 0 auto; }

    .form-header { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
    
    .btn-back {
        width: 48px; height: 48px;
        border-radius: var(--radius-md);
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        color: var(--text-secondary);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; cursor: pointer;
        transition: var(--transition);
    }
    .btn-back:hover { background: var(--bg-tertiary); color: var(--text-primary); transform: translateX(-3px); }

    .header-title { flex: 1; }
    .header-title h1 {
        font-size: 1.75rem; font-weight: 700; margin: 0 0 4px 0;
        background: linear-gradient(135deg, #fff, #94a3b8);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .header-title p { color: var(--text-muted); margin: 0; font-size: 0.9rem; }

    /* Alerta de ventas */
    .alert-ventas {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(239, 68, 68, 0.1));
        border: 1px solid rgba(245, 158, 11, 0.4);
        border-radius: var(--radius-lg);
        padding: 20px 24px;
        margin-bottom: 24px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }
    .alert-ventas i {
        font-size: 1.8rem;
        color: var(--warning);
    }
    .alert-ventas-content h4 {
        color: var(--warning);
        margin: 0 0 6px 0;
        font-size: 1rem;
    }
    .alert-ventas-content p {
        color: var(--text-secondary);
        margin: 0;
        font-size: 0.9rem;
    }

    .form-card {
        background: var(--bg-secondary);
        border-radius: var(--radius-xl);
        border: 1px solid var(--border);
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }

    .card-section { padding: 32px; border-bottom: 1px solid var(--border); }
    .card-section:last-child { border-bottom: none; }

    .section-title {
        display: flex; align-items: center; gap: 12px;
        font-size: 1.1rem; font-weight: 600;
        color: var(--text-primary); margin-bottom: 24px;
    }
    .section-title i {
        width: 36px; height: 36px;
        background: linear-gradient(135deg, var(--accent), #6366f1);
        border-radius: var(--radius-sm);
        display: flex; align-items: center; justify-content: center;
    }

    .form-group { margin-bottom: 20px; }
    .form-group:last-child { margin-bottom: 0; }

    .form-label {
        display: block; font-weight: 600; font-size: 0.9rem;
        color: var(--text-secondary); margin-bottom: 8px;
    }
    .form-label.required::after { content: ' *'; color: var(--danger); }

    .form-control, .form-select {
        width: 100%; padding: 14px 16px;
        background: var(--bg-input);
        border: 2px solid var(--border);
        border-radius: var(--radius-md);
        color: var(--text-primary);
        font-size: 1rem; font-family: inherit;
        transition: var(--transition);
    }
    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--border-focus);
        box-shadow: 0 0 0 4px var(--accent-glow);
    }
    .form-control::placeholder { color: var(--text-muted); }

    textarea.form-control { min-height: 120px; resize: vertical; }

    .funciones-container {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        padding: 24px;
    }

    .funciones-input-group { display: flex; gap: 12px; margin-bottom: 16px; }
    .funciones-input-group .form-control { flex: 1; }

    .btn-add-funcion {
        padding: 14px 20px;
        background: linear-gradient(135deg, var(--success), #16a34a);
        border: none; border-radius: var(--radius-md);
        color: white; font-weight: 600;
        display: flex; align-items: center; gap: 8px;
        cursor: pointer; transition: var(--transition);
    }
    .btn-add-funcion:disabled { opacity: 0.5; cursor: not-allowed; }

    .funciones-lista {
        min-height: 80px;
        display: flex; flex-wrap: wrap; gap: 10px;
        padding: 16px;
        background: var(--bg-secondary);
        border-radius: var(--radius-md);
        border: 2px dashed var(--border);
    }

    .funcion-item {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 16px;
        background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
        border: 1px solid var(--accent);
        border-radius: 50px;
        color: var(--accent); font-weight: 600; font-size: 0.9rem;
    }

    .funcion-item.locked {
        border-color: var(--warning);
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), transparent);
    }
    .funcion-item.locked .lock-badge {
        background: var(--warning);
        color: #000;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .funcion-item .btn-remove {
        width: 22px; height: 22px;
        border-radius: 50%;
        background: rgba(239, 68, 68, 0.2);
        border: none; color: var(--danger);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: var(--transition);
        font-size: 0.75rem;
    }
    .funcion-item .btn-remove:hover { background: var(--danger); color: white; }
    .funcion-item .btn-remove:disabled { opacity: 0.3; cursor: not-allowed; }
    .funcion-item .btn-remove:disabled:hover { background: rgba(239, 68, 68, 0.2); color: var(--danger); }

    .funciones-empty {
        width: 100%; text-align: center;
        color: var(--text-muted); font-style: italic; padding: 20px;
    }

    .validation-msg {
        display: none; align-items: center; gap: 6px;
        margin-top: 8px; font-size: 0.85rem; font-weight: 500;
        color: var(--danger);
    }
    .validation-msg.show { display: flex; }

    .dates-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

    .date-field-locked { position: relative; }
    .date-field-locked .form-control {
        background: var(--bg-primary);
        cursor: not-allowed;
        padding-right: 45px;
    }
    .date-field-locked .lock-icon {
        position: absolute; right: 14px; top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }

    .image-upload { position: relative; }
    .image-upload-area {
        border: 2px dashed var(--border);
        border-radius: var(--radius-lg);
        padding: 30px 20px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--bg-primary);
    }
    .image-upload-area:hover { border-color: var(--accent); }
    .image-upload-area i { font-size: 2rem; color: var(--text-muted); margin-bottom: 8px; }
    .image-upload-area p { color: var(--text-secondary); margin: 0; }
    .image-upload input[type="file"] {
        position: absolute; opacity: 0;
        width: 100%; height: 100%; top: 0; left: 0; cursor: pointer;
    }

    .image-preview {
        margin-top: 16px;
        display: flex; align-items: center; gap: 16px;
        padding: 12px;
        background: var(--bg-primary);
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
    }
    .image-preview img {
        width: 80px; height: 100px;
        object-fit: cover;
        border-radius: var(--radius-sm);
    }
    .image-preview span { color: var(--text-secondary); font-size: 0.9rem; }

    .escenario-options { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .escenario-option { position: relative; }
    .escenario-option input { position: absolute; opacity: 0; }
    .escenario-option label {
        display: flex; flex-direction: column; align-items: center; gap: 8px;
        padding: 20px;
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: var(--radius-lg);
        cursor: pointer; transition: var(--transition);
    }
    .escenario-option label:hover { border-color: var(--accent); }
    .escenario-option input:checked + label {
        border-color: var(--accent);
        background: rgba(59, 130, 246, 0.1);
    }
    .escenario-option .icon { font-size: 1.8rem; }
    .escenario-option .name { font-weight: 600; color: var(--text-primary); }
    .escenario-option .seats { font-size: 0.8rem; color: var(--text-muted); }

    .submit-section { padding: 32px; }
    .btn-submit {
        width: 100%; padding: 18px 32px;
        background: linear-gradient(135deg, var(--accent), #6366f1);
        border: none; border-radius: var(--radius-md);
        color: white; font-size: 1.1rem; font-weight: 700;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 10px;
    }
    .btn-submit:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(59, 130, 246, 0.4);
    }
    .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

    .alert-errors {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: var(--radius-md);
        padding: 16px 20px; margin-bottom: 24px;
    }
    .alert-errors ul { margin: 0; padding-left: 20px; color: var(--danger); }

    .modal-overlay {
        position: fixed; inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        display: none; align-items: center; justify-content: center;
        z-index: 1000; opacity: 0;
        transition: opacity 0.3s ease;
    }
    .modal-overlay.show { display: flex; opacity: 1; }
    .modal-box {
        background: var(--bg-secondary);
        border-radius: var(--radius-xl);
        border: 1px solid var(--border);
        padding: 32px; max-width: 400px; width: 90%;
        text-align: center;
    }
    .modal-box h3 { margin: 0 0 12px 0; color: var(--text-primary); }
    .modal-box p { color: var(--text-secondary); margin-bottom: 24px; }
    .modal-buttons { display: flex; gap: 12px; }
    .modal-buttons button {
        flex: 1; padding: 12px;
        border-radius: var(--radius-sm);
        font-weight: 600; cursor: pointer;
    }
    .btn-modal-cancel { background: var(--bg-tertiary); border: 1px solid var(--border); color: var(--text-primary); }
    .btn-modal-confirm { background: var(--danger); border: none; color: white; }

    @media (max-width: 768px) {
        .funciones-input-group { flex-direction: column; }
        .dates-grid { grid-template-columns: 1fr; }
        .escenario-options { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<div class="main-wrapper">
    <div class="form-header">
        <button type="button" class="btn-back" onclick="confirmarSalida()">
            <i class="bi bi-arrow-left"></i>
        </button>
        <div class="header-title">
            <h1>
                <i class="bi bi-<?= $modo_reactivacion ? 'arrow-counterclockwise' : 'pencil-square' ?> me-2"></i>
                <?= $modo_reactivacion ? 'Reactivar Evento' : 'Editar Evento' ?>
            </h1>
            <p><?= htmlspecialchars($evento['titulo']) ?></p>
        </div>
    </div>

    <?php if($tiene_ventas && !$modo_reactivacion): ?>
    <div class="alert-ventas">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div class="alert-ventas-content">
            <h4><i class="bi bi-lock-fill me-1"></i> Edición Limitada</h4>
            <p>Este evento tiene <strong><?= $total_boletos_vendidos ?> boleto(s) vendido(s)</strong>. 
               Las funciones con ventas no pueden eliminarse ni modificarse. 
               Puedes agregar nuevas funciones y editar información general.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-card">
        <?php if($errores_php): ?>
            <div class="card-section">
                <div class="alert-errors">
                    <ul><?php foreach($errores_php as $e) echo "<li>$e</li>"; ?></ul>
                </div>
            </div>
        <?php endif; ?>

        <form id="formEvento" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="actualizar">
            <input type="hidden" name="id_evento" value="<?= $id_evento ?>">
            <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($evento['imagen']) ?>">

            <!-- Información Básica -->
            <div class="card-section">
                <div class="section-title">
                    <i class="bi bi-info-circle"></i>
                    <span>Información Básica</span>
                </div>

                <div class="form-group">
                    <label class="form-label required">Título del Evento</label>
                    <input type="text" id="titulo" name="titulo" class="form-control" 
                           value="<?= htmlspecialchars($evento['titulo']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label required">Descripción</label>
                    <textarea id="descripcion" name="descripcion" class="form-control" required><?= htmlspecialchars($evento['descripcion']) ?></textarea>
                </div>
            </div>

            <!-- Funciones -->
            <div class="card-section">
                <div class="section-title">
                    <i class="bi bi-calendar-event"></i>
                    <span>Programación de Funciones</span>
                </div>

                <div class="funciones-container">
                    <div class="funciones-input-group">
                        <input type="text" id="inputFecha" class="form-control" placeholder="📅 Selecciona fecha" readonly>
                        <input type="text" id="inputHora" class="form-control" placeholder="🕐 Hora" readonly style="max-width: 150px;">
                        <button type="button" id="btnAgregarFuncion" class="btn-add-funcion" disabled>
                            <i class="bi bi-plus-lg"></i> Agregar
                        </button>
                    </div>
                    <div class="validation-msg" id="val-func-add"><i class="bi bi-exclamation-circle"></i> <span></span></div>
                    
                    <div class="funciones-lista" id="listaFunciones">
                        <div class="funciones-empty" id="funcionesEmpty">
                            <i class="bi bi-inbox"></i> No hay funciones programadas
                        </div>
                    </div>
                    <div id="funcionesHidden"></div>
                </div>
                <div class="validation-msg" id="val-funciones"><i class="bi bi-exclamation-circle"></i> <span></span></div>
            </div>

            <!-- Fechas de Venta -->
            <div class="card-section">
                <div class="section-title">
                    <i class="bi bi-shop"></i>
                    <span>Periodo de Venta</span>
                </div>

                <div class="dates-grid">
                    <div class="form-group">
                        <label class="form-label required">Inicio de Venta</label>
                        <input type="text" id="inicioVenta" name="inicio_venta" class="form-control" 
                               value="<?= $defaultVenta ?>" readonly required>
                        <div class="validation-msg" id="val-inicio"><i class="bi bi-exclamation-circle"></i> <span></span></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cierre de Venta</label>
                        <div class="date-field-locked">
                            <input type="text" id="cierreVenta" name="cierre_venta" class="form-control" 
                                   value="<?= $defaultCierre ?>" readonly>
                            <i class="bi bi-lock-fill lock-icon"></i>
                        </div>
                        <small style="color: var(--text-muted); display: block; margin-top: 6px;">
                            <i class="bi bi-info-circle"></i> 2 horas después de la última función
                        </small>
                    </div>
                </div>
            </div>

            <!-- Imagen y Tipo -->
            <div class="card-section">
                <div class="section-title">
                    <i class="bi bi-image"></i>
                    <span>Imagen y Configuración</span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div class="form-group">
                        <label class="form-label">Imagen Promocional</label>
                        <div class="image-upload">
                            <div class="image-upload-area">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <p>Cambiar imagen (opcional)</p>
                            </div>
                            <input type="file" id="imagen" name="imagen" accept="image/*">
                        </div>
                        <?php if($evento['imagen']): ?>
                        <div class="image-preview">
                            <img src="../evt_interfaz/<?= htmlspecialchars($evento['imagen']) ?>" 
                                 onerror="this.style.display='none'">
                            <span>Imagen actual</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Tipo de Escenario</label>
                        <div class="escenario-options">
                            <div class="escenario-option">
                                <input type="radio" name="tipo" id="tipo1" value="1" <?= $evento['tipo']==1?'checked':'' ?> required>
                                <label for="tipo1">
                                    <span class="icon">🎭</span>
                                    <span class="name">Teatro</span>
                                    <span class="seats">420 butacas</span>
                                </label>
                            </div>
                            <div class="escenario-option">
                                <input type="radio" name="tipo" id="tipo2" value="2" <?= $evento['tipo']==2?'checked':'' ?>>
                                <label for="tipo2">
                                    <span class="icon">🚶</span>
                                    <span class="name">Pasarela</span>
                                    <span class="seats">540 butacas</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="submit-section">
                <button type="submit" id="btnSubmit" class="btn-submit">
                    <i class="bi bi-<?= $modo_reactivacion ? 'arrow-counterclockwise' : 'save' ?>"></i>
                    <?= $modo_reactivacion ? 'Confirmar Reactivación' : 'Guardar Cambios' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalSalir">
    <div class="modal-box">
        <h3>¿Salir sin guardar?</h3>
        <p>Perderás los cambios no guardados.</p>
        <div class="modal-buttons">
            <button type="button" class="btn-modal-cancel" onclick="cerrarModal()">Continuar</button>
            <button type="button" class="btn-modal-confirm" onclick="salir()">Salir</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('loaded');
    flatpickr.localize(flatpickr.l10ns.es);

    // ═══════════════════════════════════════════════════════════════════
    // DATOS DESDE PHP
    // ═══════════════════════════════════════════════════════════════════
    const modoReactivacion = <?= $modo_reactivacion ? 'true' : 'false' ?>;
    const tieneVentas = <?= $tiene_ventas ? 'true' : 'false' ?>;
    const funcionesConVentas = <?= json_encode(array_keys($funciones_con_ventas)) ?>;
    
    // Cargar funciones existentes (vacías si reactivación)
    let funciones = [
        <?php if(!$modo_reactivacion): ?>
            <?php foreach($funciones_existentes as $f): ?>
            { 
                id: <?= $f['id'] ?>, 
                fecha: new Date('<?= $f['fecha']->format('c') ?>'),
                locked: <?= isset($funciones_con_ventas[$f['id']]) ? 'true' : 'false' ?>
            },
            <?php endforeach; ?>
        <?php endif; ?>
    ];

    const els = {
        titulo: document.getElementById('titulo'),
        desc: document.getElementById('descripcion'),
        listaFunciones: document.getElementById('listaFunciones'),
        funcionesEmpty: document.getElementById('funcionesEmpty'),
        funcionesHidden: document.getElementById('funcionesHidden'),
        btnAgregar: document.getElementById('btnAgregarFuncion'),
        btnSubmit: document.getElementById('btnSubmit'),
        inicioVenta: document.getElementById('inicioVenta'),
        cierreVenta: document.getElementById('cierreVenta')
    };

    // ═══════════════════════════════════════════════════════════════════
    // FLATPICKR
    // ═══════════════════════════════════════════════════════════════════
    const fpFecha = flatpickr("#inputFecha", {
        minDate: "today",
        dateFormat: "Y-m-d",
        onChange: checkAgregarBtn
    });

    const fpHora = flatpickr("#inputHora", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        minuteIncrement: 15,
        onChange: checkAgregarBtn
    });

    const fpInicio = flatpickr("#inicioVenta", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        onChange: validarFormulario
    });

    const fpCierre = flatpickr("#cierreVenta", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        clickOpens: false
    });

    // ═══════════════════════════════════════════════════════════════════
    // FUNCIONES
    // ═══════════════════════════════════════════════════════════════════
    function checkAgregarBtn() {
        els.btnAgregar.disabled = !(fpFecha.selectedDates.length && fpHora.selectedDates.length);
    }

    els.btnAgregar.addEventListener('click', () => {
        if (!fpFecha.selectedDates[0] || !fpHora.selectedDates[0]) return;

        const fecha = new Date(fpFecha.selectedDates[0]);
        fecha.setHours(fpHora.selectedDates[0].getHours());
        fecha.setMinutes(fpHora.selectedDates[0].getMinutes());
        fecha.setSeconds(0);

        const ahora = new Date();
        
        // Validación: debe ser futura
        if (fecha <= new Date(ahora.getTime() + 30 * 60 * 1000)) {
            mostrarError('val-func-add', 'La función debe ser al menos 30 minutos en el futuro.');
            return;
        }

        // No duplicados
        if (funciones.some(f => f.fecha.getTime() === fecha.getTime())) {
            mostrarError('val-func-add', 'Esta función ya existe.');
            return;
        }

        // Mínimo 2 horas entre funciones
        for (const f of funciones) {
            const diff = Math.abs(fecha.getTime() - f.fecha.getTime());
            if (diff < 2 * 60 * 60 * 1000) {
                mostrarError('val-func-add', 'Debe haber al menos 2 horas entre funciones.');
                return;
            }
        }

        ocultarError('val-func-add');
        funciones.push({ id: null, fecha: fecha, locked: false });
        funciones.sort((a, b) => a.fecha - b.fecha);
        
        fpFecha.clear();
        fpHora.clear();
        checkAgregarBtn();
        renderizarFunciones();
        actualizarCierreVenta();
        validarFormulario();
    });

    function renderizarFunciones() {
        els.listaFunciones.innerHTML = '';
        els.funcionesHidden.innerHTML = '';

        if (funciones.length === 0) {
            els.listaFunciones.appendChild(els.funcionesEmpty);
            fpInicio.set('maxDate', null);
            return;
        }

        funciones.forEach((func, index) => {
            const item = document.createElement('div');
            item.className = 'funcion-item' + (func.locked ? ' locked' : '');
            
            const fechaStr = func.fecha.toLocaleDateString('es-MX', {
                weekday: 'short', day: '2-digit', month: 'short',
                hour: '2-digit', minute: '2-digit'
            });

            const lockBadge = func.locked ? '<span class="lock-badge"><i class="bi bi-lock-fill"></i> Vendido</span>' : '';
            
            item.innerHTML = `
                <i class="bi bi-calendar-event"></i>
                ${fechaStr}
                ${lockBadge}
                <button type="button" class="btn-remove" 
                        onclick="eliminarFuncion(${index})" 
                        ${func.locked ? 'disabled title="No se puede eliminar - tiene boletos vendidos"' : ''}>
                    <i class="bi bi-x"></i>
                </button>
            `;
            els.listaFunciones.appendChild(item);

            // Para funciones existentes (con ID), guardamos el ID
            // Para funciones nuevas, guardamos la fecha en otro campo
            if (func.id) {
                els.funcionesHidden.innerHTML += `<input type="hidden" name="funciones_ids[]" value="${func.id}">`;
            } else {
                const sqlDate = formatoSQL(func.fecha);
                els.funcionesHidden.innerHTML += `<input type="hidden" name="funciones_nuevas[]" value="${sqlDate}">`;
            }
        });

        const primeraFuncion = funciones[0].fecha;
        fpInicio.set('maxDate', new Date(primeraFuncion.getTime() - 60000));
    }

    window.eliminarFuncion = (index) => {
        if (funciones[index].locked) {
            alert('No puedes eliminar una función que ya tiene boletos vendidos.');
            return;
        }
        funciones.splice(index, 1);
        renderizarFunciones();
        actualizarCierreVenta();
        validarFormulario();
    };

    function actualizarCierreVenta() {
        if (funciones.length === 0) {
            fpCierre.clear();
            return;
        }
        const ultimaFuncion = funciones[funciones.length - 1].fecha;
        const cierre = new Date(ultimaFuncion.getTime() + 2 * 60 * 60 * 1000);
        fpCierre.setDate(cierre, true);
    }

    function formatoSQL(fecha) {
        return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')} ${String(fecha.getHours()).padStart(2, '0')}:${String(fecha.getMinutes()).padStart(2, '0')}:00`;
    }

    function validarFormulario() {
        let valido = true;

        document.querySelectorAll('.validation-msg').forEach(el => el.classList.remove('show'));

        if (!els.titulo.value.trim()) valido = false;
        if (!els.desc.value.trim()) valido = false;
        if (funciones.length === 0) {
            mostrarError('val-funciones', 'Agrega al menos una función.');
            valido = false;
        }

        if (!fpInicio.selectedDates.length && funciones.length > 0) {
            mostrarError('val-inicio', 'Define cuándo inicia la venta.');
            valido = false;
        }

        const tipoSeleccionado = document.querySelector('input[name="tipo"]:checked');
        if (!tipoSeleccionado) valido = false;

        els.btnSubmit.disabled = !valido;
        return valido;
    }

    function mostrarError(id, msg) {
        const el = document.getElementById(id);
        if (el) { el.querySelector('span').textContent = msg; el.classList.add('show'); }
    }

    function ocultarError(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('show');
    }

    els.titulo.addEventListener('input', validarFormulario);
    els.desc.addEventListener('input', validarFormulario);
    document.querySelectorAll('input[name="tipo"]').forEach(r => r.addEventListener('change', validarFormulario));

    document.getElementById('formEvento').addEventListener('submit', (e) => {
        if (!validarFormulario()) {
            e.preventDefault();
            alert('Completa todos los campos requeridos.');
        }
    });

    // Inicializar
    renderizarFunciones();
    if (funciones.length > 0) actualizarCierreVenta();
    validarFormulario();
});

function confirmarSalida() { document.getElementById('modalSalir').classList.add('show'); }
function cerrarModal() { document.getElementById('modalSalir').classList.remove('show'); }
function salir() {
    const tab = <?= $modo_reactivacion ? "'historial'" : "'activos'" ?>;
    window.parent.location.href = 'index.php?tab=' + tab;
}
</script>
</body>
</html>