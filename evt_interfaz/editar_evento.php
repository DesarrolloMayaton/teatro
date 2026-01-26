<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Mexico_City'); // Zona horaria local

<<<<<<< HEAD
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
=======
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('America/Mexico_City'); // Sincronizar con hora local del usuario (-06:00)
>>>>>>> 9ad8a0c7aac8bcdad612d9182d5320658bd19a15
include "../conexion.php";
if (file_exists("../transacciones_helper.php")) {
    require_once "../transacciones_helper.php";
}
require_once __DIR__ . "/../api/registrar_cambio.php";

// Verificar permisos
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="display:flex;height:100vh;align-items:center;justify-content:center;font-family:sans-serif;color:#ef4444;"><h1>Acceso Denegado</h1></div>');
}

// Variables de control
$errores_php = [];
$modo_reactivacion = isset($_GET['modo_reactivacion']) && $_GET['modo_reactivacion'] == 1;
$id_evento = $_GET['id'] ?? 0;

// ==================================================================
// 2. PROCESADOR (POST)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {

        $titulo = trim($_POST['titulo']);
        $desc = trim($_POST['descripcion']);
        $tipo = $_POST['tipo'];
        $ini = $_POST['inicio_venta'];
        $fin = $_POST['cierre_venta'];
        $img = $_POST['imagen_actual'];

        // Validaciones del lado servidor
        if (empty($titulo))
            $errores_php[] = "Falta el título.";
        if (empty($_POST['funciones']))
            $errores_php[] = "Debe agregar al menos una función.";
        if (empty($ini))
            $errores_php[] = "Falta inicio de venta.";

        // Validar que inicio de venta no sea en el pasado
        if (!empty($ini) && strtotime($ini) < time()) {
            $errores_php[] = "La fecha/hora de inicio de venta no puede ser anterior a ahora.";
        }

        // Validar que TODAS las funciones sean futuras
        if (!empty($_POST['funciones'])) {
            foreach ($_POST['funciones'] as $func) {
                if (strtotime($func) <= time()) {
                    $errores_php[] = "No se puede guardar con funciones en el pasado. Elimina las funciones vencidas primero.";
                    break;
                }
            }

            // Auto-ajustar cierre: 2 horas después de la función con hora mayor
            $ultimaFuncion = max(array_map('strtotime', $_POST['funciones']));
            $fin = date('Y-m-d H:i:s', $ultimaFuncion + 7200); // +2 horas automáticamente
        }

        if (empty($errores_php)) {
            $conn->begin_transaction();
            try {
                // Subida de imagen
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        if (!is_dir("imagenes"))
                            mkdir("imagenes", 0755, true);
                        $ruta = "imagenes/evt_" . time() . "." . $ext;
                        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
                            $img = $ruta;
                            if ($_POST['imagen_actual'] && file_exists($_POST['imagen_actual']))
                                unlink($_POST['imagen_actual']);
                        }
                    }
                }

                if ($modo_reactivacion) {
                    // --- REACTIVACIÓN: Crear nuevo evento desde histórico ---

                    // 1. Traer mapa del histórico
                    $res_old = $conn->query("SELECT mapa_json FROM trt_historico_evento.evento WHERE id_evento = $id_evento");
                    $mapa_json = $res_old->fetch_object()->mapa_json;

                    // 2. Insertar en Activos
                    $stmt = $conn->prepare("INSERT INTO evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado, mapa_json) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
                    $stmt->bind_param("sssisss", $titulo, $desc, $img, $tipo, $ini, $fin, $mapa_json);
                    $stmt->execute();
                    $new_id = $conn->insert_id;
                    $stmt->close();

                    // 3. Copiar categorías
                    $conn->query("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) SELECT $new_id, nombre_categoria, precio, color FROM trt_historico_evento.categorias WHERE id_evento = $id_evento");

                    // 4. Insertar NUEVAS funciones (las viejas se ignoraron)
                    $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora, estado) VALUES (?, ?, 0)");
                    foreach ($_POST['funciones'] as $fh) {
                        $stmt_f->bind_param("is", $new_id, $fh);
                        $stmt_f->execute();
                    }
                    $stmt_f->close();

                    // 5. Borrar del histórico (limpieza completa)
                    $conn->query("DELETE FROM trt_historico_evento.boletos WHERE id_evento = $id_evento");
                    $conn->query("DELETE FROM trt_historico_evento.promociones WHERE id_evento = $id_evento");
                    $conn->query("DELETE FROM trt_historico_evento.categorias WHERE id_evento = $id_evento");
                    $conn->query("DELETE FROM trt_historico_evento.funciones WHERE id_evento = $id_evento");
                    $conn->query("DELETE FROM trt_historico_evento.evento WHERE id_evento = $id_evento");

                    $evt_id = $new_id;

                } else {
                    // --- EDICIÓN NORMAL ---
                    $stmt = $conn->prepare("UPDATE evento SET titulo=?, inicio_venta=?, cierre_venta=?, descripcion=?, imagen=?, tipo=? WHERE id_evento=?");
                    $stmt->bind_param("sssssii", $titulo, $ini, $fin, $desc, $img, $tipo, $id_evento);
                    $stmt->execute();

                    // Eliminar funciones antiguas y agregar las nuevas
                    $conn->query("DELETE FROM funciones WHERE id_evento = $id_evento");
                    $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora, estado) VALUES (?, ?, 0)");
                    foreach ($_POST['funciones'] as $fh) {
                        $stmt_f->bind_param("is", $id_evento, $fh);
                        $stmt_f->execute();
                    }
                    $stmt_f->close();

                    $evt_id = $id_evento;
                }

                $conn->commit();
                if (function_exists('registrar_transaccion'))
                    registrar_transaccion('evento_guardar', "Guardó evento: $titulo");

                // Notificar cambio para auto-actualización en tiempo real
                registrar_cambio('evento', $evt_id, null, ['accion' => $modo_reactivacion ? 'reactivar' : 'editar']);
                registrar_cambio('funcion', $evt_id, null, ['accion' => 'modificar', 'cantidad' => count($_POST['funciones'])]);

                // PANTALLA DE ÉXITO ANIMADA
                ?>
                <!DOCTYPE html>
                <html lang="es">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
                    <link rel="stylesheet" href="../assets/css/teatro-style.css">
                    <style>
                        body {
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            height: 100vh;
                            margin: 0;
                            overflow: hidden;
                        }

                        .success-card {
                            background: var(--bg-secondary);
                            border-radius: 24px;
                            padding: 40px;
                            text-align: center;
                            box-shadow: var(--shadow-xl);
                            max-width: 380px;
                            width: 90%;
                            border: 1px solid var(--border-color);
                            animation: popIn 0.5s cubic-bezier(0.17, 0.67, 0.33, 1.15) forwards;
                        }

                        .icon-circle {
                            width: 80px;
                            height: 80px;
                            background: var(--success-bg);
                            color: var(--success);
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 40px;
                            margin: 0 auto 20px;
                        }

                        .progress-track {
                            height: 6px;
                            background: var(--bg-tertiary);
                            border-radius: 3px;
                            margin-top: 30px;
                            overflow: hidden;
                        }

                        .progress-fill {
                            height: 100%;
                            background: var(--success);
                            width: 0;
                            transition: width 1.2s linear;
                        }

                        @keyframes popIn {
                            from {
                                opacity: 0;
                                transform: scale(0.8);
                            }

                            to {
                                opacity: 1;
                                transform: scale(1);
                            }
                        }

                        @keyframes fadeOut {
                            to {
                                opacity: 0;
                                transform: scale(0.95);
                            }
                        }

                        body.exiting {
                            animation: fadeOut 0.4s ease-in forwards;
                        }

                        h4 {
                            color: var(--text-primary);
                        }

                        p {
                            color: var(--text-muted);
                        }
                    </style>
                </head>

                <body>
                    <div class="success-card">
                        <div class="icon-circle"><i class="bi bi-check-lg"></i></div>
                        <h4 class="fw-bold mb-2">¡Operación Exitosa!</h4>
                        <p class="small mb-0">Los datos se han guardado correctamente.</p>
                        <div class="progress-track">
                            <div class="progress-fill" id="pBar"></div>
                        </div>
                        <p class="mt-2" style="font-size: 0.75rem; font-weight: 700;">REDIRIGIENDO...</p>
                    </div>
                    <script>
                        setTimeout(() => document.getElementById('pBar').style.width = '100%', 50);
                        localStorage.setItem("evt_upd", Date.now());
                        setTimeout(() => {
                            document.body.classList.add('exiting');
                            setTimeout(() => { window.parent.location.href = "index.php?tab=activos"; }, 350);
                        }, 1300); 
                    </script>
                </body>

                </html>
                <?php exit;

            } catch (Exception $e) {
                $conn->rollback();
                $errores_php[] = "Error DB: " . $e->getMessage();
            }
        }
    }
}

// ==================================================================
// 3. CARGA DE DATOS (GET)
// ==================================================================
if (!$id_evento || !is_numeric($id_evento)) {
    header('Location: act_evento.php');
    exit;
}

$tabla_evt = $modo_reactivacion ? "trt_historico_evento.evento" : "evento";
$tabla_fun = $modo_reactivacion ? "trt_historico_evento.funciones" : "funciones";

$res = $conn->query("SELECT * FROM $tabla_evt WHERE id_evento = $id_evento");
$evento = $res->fetch_assoc();
if (!$evento) {
    header('Location: act_evento.php');
    exit;
}

// Cargar funciones existentes - En modo reactivación NO cargamos funciones (se deben crear nuevas)
$funciones_existentes = [];
if (!$modo_reactivacion) {
    // Incluir id_funcion y conteo de boletos vendidos por función
    $tabla_boletos = $modo_reactivacion ? "trt_historico_evento.boletos" : "boletos";
    $res_f = $conn->query("
        SELECT f.id_funcion, f.fecha_hora, f.estado,
               (SELECT COUNT(*) FROM $tabla_boletos b WHERE b.id_funcion = f.id_funcion AND b.estatus = 1) as boletos_vendidos
        FROM $tabla_fun f
        WHERE f.id_evento = $id_evento 
        ORDER BY f.fecha_hora ASC
    ");
    while ($f = $res_f->fetch_assoc()) {
        $funciones_existentes[] = [
            'id_funcion' => (int) $f['id_funcion'],
            'fecha' => new DateTime($f['fecha_hora']),
            'estado' => $f['estado'],
            'boletos' => (int) $f['boletos_vendidos']
        ];
    }
}

// Fechas: Si es reactivación, vacías para obligar a poner nuevas
$defaultVenta = $modo_reactivacion ? '' : date('Y-m-d H:i', strtotime($evento['inicio_venta']));
$defaultCierre = $modo_reactivacion ? '' : date('Y-m-d H:i', strtotime($evento['cierre_venta']));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $modo_reactivacion ? 'Reactivar' : 'Editar' ?> Evento</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/teatro-style.css">
    <style>
        body {
            padding: 30px 20px;
            min-height: 100vh;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        body.loaded {
            opacity: 1;
        }

        body.exiting {
            opacity: 0;
        }

        .main-wrapper {
            max-width: 850px;
            margin: 0 auto;
        }

        .card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 40px;
            border: 1px solid var(--border-color);
        }

        .form-control,
        .form-select {
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: var(--transition-fast);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(21, 97, 240, 0.2);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-text {
            color: var(--text-muted) !important;
            font-size: 0.8rem;
        }

        .btn {
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            border: none;
            transition: var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

<<<<<<< HEAD
        .btn-primary {
            background: var(--accent-blue);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--accent-blue-hover);
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-hover);
        }

        .btn-success {
            background: var(--success);
            color: #000;
        }

        .btn-success:hover:not(:disabled) {
            filter: brightness(1.1);
        }

        .btn-success:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-warning {
            background: var(--warning);
            color: #000;
        }

        .input-error {
            border-color: var(--danger) !important;
            background: var(--danger-bg) !important;
        }

        .tooltip-error {
            color: var(--danger);
            font-size: 0.85em;
            margin-top: 5px;
            display: none;
            font-weight: 600;
        }

        .funciones-section {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
        }

        #lista-funciones {
            background: var(--bg-primary);
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-sm);
            padding: 15px;
            min-height: 70px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .funcion-item {
            padding: 8px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }

        .funcion-item.future {
            background: var(--bg-secondary);
            border: 1px solid var(--accent-blue);
            color: var(--accent-blue);
        }

        .funcion-item.past {
            background: var(--danger-bg);
            border: 1px solid var(--danger);
            color: var(--danger);
            text-decoration: line-through;
            opacity: 0.7;
        }

        .funcion-item button {
            background: none;
            border: none;
            color: var(--danger);
            font-size: 1.1em;
            padding: 0;
            cursor: pointer;
            line-height: 1;
            transition: var(--transition-fast);
        }

        .funcion-item button:hover {
            transform: scale(1.2);
        }

        /* Funciones protegidas (con boletos vendidos) */
        .funcion-item.protected {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, rgba(21, 97, 240, 0.1) 100%);
            border: 2px solid var(--accent-blue);
            position: relative;
        }

        .funcion-item .func-protected {
            color: var(--warning);
            font-size: 0.9em;
            cursor: help;
        }

        .funcion-item .boletos-count {
            background: var(--success-bg);
            color: var(--success);
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .funcion-item .boletos-count i {
            font-size: 0.65rem;
        }

        .input-group {
            display: flex;
            gap: 8px;
        }

        .input-group .form-control {
            flex: 1;
        }

        .cierre-container {
            position: relative;
        }

        .cierre-lock-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 6px 10px;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .cierre-lock-btn:hover {
            background: var(--warning-bg);
            color: var(--warning);
            border-color: var(--warning);
        }

        .cierre-lock-btn.unlocked {
            background: var(--warning-bg);
            color: var(--warning);
            border-color: var(--warning);
        }

        .alert-danger {
            background: var(--danger-bg) !important;
            border: 1px solid rgba(255, 69, 58, 0.3) !important;
            color: var(--danger) !important;
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            margin-bottom: 20px;
        }

        .alert-danger ul {
            margin: 0;
            padding-left: 20px;
        }

        .alert-warning {
            background: var(--warning-bg);
            border: 1px solid rgba(255, 159, 10, 0.3);
            color: var(--warning);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-warning i {
            font-size: 1.5rem;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .page-header h2 {
            margin: 0;
            color: var(--accent-blue);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }

        .col-12 {
            width: 100%;
            padding: 10px;
        }

        .col-md-5 {
            width: 41.666%;
            padding: 10px;
        }

        .col-md-6 {
            width: 50%;
            padding: 10px;
        }

        .col-md-7 {
            width: 58.333%;
            padding: 10px;
        }

        @media (max-width: 768px) {

            .col-md-5,
            .col-md-6,
            .col-md-7 {
                width: 100%;
            }
        }

        hr {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 30px 0;
        }

        .img-preview {
            width: 100px;
            height: 140px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            margin-top: 10px;
        }

        /* Modal Premium */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(12px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(145deg, var(--bg-secondary) 0%, #1a1a1c 100%);
            border-radius: 20px;
            padding: 0;
            max-width: 420px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
            animation: modalPop 0.3s ease;
            overflow: hidden;
        }

        @keyframes modalPop {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-icon-container {
            background: linear-gradient(135deg, var(--warning) 0%, #ff6b35 100%);
            padding: 30px;
            position: relative;
        }

        .modal-icon-container::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 25px solid transparent;
            border-right: 25px solid transparent;
            border-top: 20px solid #ff6b35;
        }

        .modal-icon {
            font-size: 3.5rem;
            color: white;
            animation: iconBounce 1s ease infinite;
        }

        @keyframes iconBounce {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .modal-body-content {
            padding: 40px 30px 30px;
        }

        .modal-content h5 {
            color: var(--text-primary);
            margin-bottom: 12px;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .modal-content p {
            color: var(--text-muted);
            margin-bottom: 0;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            padding: 20px 30px 30px;
            justify-content: center;
        }

        .modal-buttons .btn {
            flex: 1;
            padding: 14px 20px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .modal-buttons .btn:hover {
            transform: translateY(-2px);
        }

        .btn-stay {
            background: var(--accent-blue);
            color: white;
            border: none;
        }

        .btn-stay:hover {
            background: var(--accent-blue-hover);
            box-shadow: 0 8px 20px rgba(21, 97, 240, 0.4);
        }

        .btn-leave {
            background: transparent;
            color: var(--danger);
            border: 2px solid var(--danger);
        }

        .btn-leave:hover {
            background: var(--danger);
            color: white;
        }
    </style>
=======
    /* Modal Ultra Premium */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.9);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    
    .modal-content {
        background: linear-gradient(160deg, #1e1e22 0%, #141416 50%, #1a1a1e 100%);
        border-radius: 24px;
        padding: 0;
        max-width: 440px;
        width: 92%;
        border: 1px solid rgba(255,255,255,0.08);
        text-align: center;
        box-shadow: 
            0 0 0 1px rgba(255,255,255,0.05),
            0 25px 80px rgba(0,0,0,0.7),
            0 0 100px rgba(255,159,10,0.15);
        animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        overflow: hidden;
        position: relative;
    }
    .modal-content::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    }
    @keyframes modalPop {
        from { opacity: 0; transform: scale(0.85) translateY(-30px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    
    .modal-icon-container {
        background: linear-gradient(135deg, #ff9f0a 0%, #ff6b35 50%, #ff453a 100%);
        padding: 40px 30px;
        position: relative;
        overflow: hidden;
    }
    .modal-icon-container::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.2) 0%, transparent 50%);
    }
    .modal-icon-container::after {
        content: '';
        position: absolute;
        bottom: -25px;
        left: 50%;
        transform: translateX(-50%);
        border-left: 30px solid transparent;
        border-right: 30px solid transparent;
        border-top: 25px solid #ff453a;
        filter: drop-shadow(0 5px 15px rgba(255,69,58,0.5));
    }
    
    .modal-icon {
        font-size: 4rem;
        color: white;
        position: relative;
        z-index: 1;
        text-shadow: 0 4px 20px rgba(0,0,0,0.3);
        animation: iconPulse 2s ease-in-out infinite;
    }
    @keyframes iconPulse {
        0%, 100% { transform: scale(1) rotate(0deg); }
        25% { transform: scale(1.05) rotate(-3deg); }
        75% { transform: scale(1.05) rotate(3deg); }
    }
    
    .modal-body-content {
        padding: 50px 35px 30px;
        position: relative;
    }
    .modal-body-content::before {
        content: '';
        position: absolute;
        top: 0;
        left: 10%;
        right: 10%;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,159,10,0.3), transparent);
    }
    
    .modal-content h5 { 
        color: #ffffff; 
        margin-bottom: 16px; 
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
    .modal-content p { 
        color: rgba(255,255,255,0.7); 
        margin-bottom: 0;
        font-size: 1rem;
        line-height: 1.7;
    }
    .modal-content p strong {
        color: var(--warning);
    }
    
    .warning-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,159,10,0.15);
        border: 1px solid rgba(255,159,10,0.3);
        color: var(--warning);
        padding: 10px 20px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-top: 20px;
    }
    .warning-badge i {
        font-size: 1.1rem;
    }
    
    .modal-buttons { 
        display: flex; 
        gap: 14px; 
        padding: 25px 35px 35px;
        justify-content: center;
    }
    .modal-buttons .btn {
        flex: 1;
        padding: 16px 24px;
        font-weight: 700;
        font-size: 0.95rem;
        border-radius: 14px;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: hidden;
    }
    .modal-buttons .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 50%;
        background: linear-gradient(180deg, rgba(255,255,255,0.1), transparent);
        pointer-events: none;
    }
    .modal-buttons .btn:hover {
        transform: translateY(-3px) scale(1.02);
    }
    .modal-buttons .btn:active {
        transform: translateY(0) scale(0.98);
    }
    
    .btn-stay {
        background: linear-gradient(135deg, var(--accent-blue) 0%, #4f46e5 100%);
        color: white;
        border: none;
        box-shadow: 0 8px 25px rgba(21,97,240,0.4);
    }
    .btn-stay:hover {
        box-shadow: 0 12px 35px rgba(21,97,240,0.5);
    }
    
    .btn-leave {
        background: rgba(255,69,58,0.1);
        color: var(--danger);
        border: 2px solid rgba(255,69,58,0.5);
    }
    .btn-leave:hover {
        background: var(--danger);
        color: white;
        border-color: var(--danger);
        box-shadow: 0 8px 25px rgba(255,69,58,0.4);
    }
</style>
>>>>>>> 9ad8a0c7aac8bcdad612d9182d5320658bd19a15
</head>

<body>

    <div class="main-wrapper">
        <div style="margin-bottom: 20px;">
            <button onclick="abrirModalCancelar()" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i>
                <?= $modo_reactivacion ? 'Cancelar Reactivación' : 'Volver a Eventos' ?>
            </button>
        </div>

        <div class="card">
            <div class="page-header">
                <h2>
                    <i class="bi <?= $modo_reactivacion ? 'bi-arrow-counterclockwise' : 'bi-pencil-square' ?>"></i>
                    <?= $modo_reactivacion ? 'Reactivar Evento' : 'Editar Evento' ?>
                </h2>
            </div>

            <?php if ($modo_reactivacion): ?>
                <div class="alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <strong>Modo Reactivación:</strong> Las funciones y fechas anteriores se han borrado.
                        Debes agregar nuevas funciones y configurar las fechas de venta.
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($errores_php): ?>
                <div class="alert-danger">
                    <ul><?php foreach ($errores_php as $e)
                        echo "<li>$e</li>"; ?></ul>
                </div>
            <?php endif; ?>

            <form id="fEdit" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id_evento" value="<?= $id_evento ?>">
                <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($evento['imagen']) ?>">

                <div class="row">
                    <div class="col-12">
                        <label class="form-label">Título del Evento</label>
                        <input type="text" id="tit" name="titulo" class="form-control"
                            style="font-size: 1.1rem; font-weight: 600;"
                            value="<?= htmlspecialchars($evento['titulo']) ?>" required>
                    </div>

                    <div class="col-12">
                        <div class="funciones-section">
                            <label class="form-label"><i class="bi bi-calendar-week"
                                    style="margin-right: 8px;"></i>Gestión de Funciones</label>
                            <div class="input-group">
                                <input type="text" id="fDate" class="form-control" placeholder="Selecciona Fecha"
                                    readonly>
                                <input type="text" id="fTime" class="form-control" placeholder="Hora" readonly
                                    style="max-width:130px">
                                <button type="button" id="fAdd" class="btn btn-success" disabled><i
                                        class="bi bi-plus-lg"></i> Agregar</button>
                            </div>
                            <div id="ttFunc" class="tooltip-error"></div>
                            <div id="lista-funciones">
                                <p id="noFunc"
                                    style="color: var(--text-muted); margin: 0; width: 100%; text-align: center; font-style: italic; font-size: 0.9rem;">
                                    <i class="bi bi-inbox" style="margin-right: 8px;"></i>No hay funciones asignadas.
                                </p>
                            </div>
                            <div id="hidFunc"></div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Inicio Venta</label>
                        <input type="text" id="ini" name="inicio_venta" class="form-control"
                            value="<?= $defaultVenta ?>" readonly required placeholder="Selecciona fecha y hora">
                        <div id="ttIni" class="tooltip-error"></div>
                        <div class="form-text"><i class="bi bi-info-circle"></i> No puede ser anterior a ahora</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" style="color: var(--text-muted);">Cierre Venta (Automático)</label>
                        <div class="cierre-container">
                            <input type="text" id="fin" name="cierre_venta" class="form-control"
                                value="<?= $defaultCierre ?>" readonly style="padding-right: 50px;">
                            <button type="button" class="cierre-lock-btn" id="lockBtn"
                                title="Desbloquear edición manual">
                                <i class="bi bi-lock-fill"></i>
                            </button>
                        </div>
                        <div class="form-text"><i class="bi bi-info-circle"></i> Se calcula 2 horas después de la última
                            función.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea id="desc" name="descripcion" class="form-control" rows="4"
                            required><?= htmlspecialchars($evento['descripcion']) ?></textarea>
                        <div id="ttDesc" class="tooltip-error"></div>
                    </div>

                    <div class="col-md-7">
                        <label class="form-label">Imagen Promocional</label>
                        <input type="file" id="img" name="imagen" class="form-control" accept="image/*">
                        <?php if ($evento['imagen']): ?>
                            <div
                                style="margin-top: 12px; padding: 10px; background: var(--bg-tertiary); border-radius: var(--radius-sm); display: inline-block;">
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">Imagen Actual:
                                </div>
                                <img src="../evt_interfaz/<?= htmlspecialchars($evento['imagen']) ?>" class="img-preview"
                                    onerror="this.style.display='none'">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Tipo de Escenario</label>
                        <select id="tipo" name="tipo" class="form-control" required>
                            <option value="1" <?= $evento['tipo'] == 1 ? 'selected' : '' ?>>🎭 Teatro (420 Butacas)
                            </option>
                            <option value="2" <?= $evento['tipo'] == 2 ? 'selected' : '' ?>>🚶 Pasarela (540 Butacas)
                            </option>
                        </select>
                        <div id="ttTipo" class="tooltip-error"></div>
                    </div>
                </div>

                <hr>

                <button type="submit" id="bSub" class="btn btn-primary"
                    style="width: 100%; padding: 16px; font-size: 1.1rem;" disabled>
                    <?= $modo_reactivacion ? '<i class="bi bi-arrow-counterclockwise"></i> Confirmar Reactivación' : '<i class="bi bi-check2-circle"></i> Guardar Cambios' ?>
                </button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="modalCancelar">
        <div class="modal-content">
            <div class="modal-icon-container">
                <i class="bi bi-exclamation-triangle-fill modal-icon"></i>
            </div>
            <div class="modal-body-content">
                <h5>¿Salir sin guardar?</h5>
                <p>Tienes cambios sin guardar. Si sales ahora, perderás todas las modificaciones.
                    <?php if ($modo_reactivacion): ?><br><strong>El evento no se reactivará y permanecerá en el
                            historial.</strong><?php endif; ?>
                </p>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-leave" onclick="confirmarSalida()">
                    <i class="bi bi-box-arrow-left"></i> Salir
                </button>
                <button class="btn btn-stay" onclick="cerrarModal()">
                    <i class="bi bi-pencil-fill"></i> Seguir Editando
                </button>
            </div>
        </div>
    </div>

<<<<<<< HEAD
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('loaded');
            flatpickr.localize(flatpickr.l10ns.es);
            const now = new Date();
            const esReactivacion = <?= $modo_reactivacion ? 'true' : 'false' ?>;
            let cierreBloqueado = true;
=======
<!-- Modal de Alertas Premium -->
<div class="modal-overlay" id="modalAlerta">
    <div class="modal-content">
        <div class="modal-icon-container" id="modalAlertaIconContainer">
            <i class="bi bi-exclamation-triangle-fill modal-icon" id="modalAlertaIcon"></i>
        </div>
        <div class="modal-body-content">
            <h5 id="modalAlertaTitulo">Atención</h5>
            <p id="modalAlertaMensaje"></p>
        </div>
        <div class="modal-buttons">
            <button class="btn btn-stay" onclick="cerrarModalAlerta()">
                <i class="bi bi-check-lg"></i> Entendido
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('loaded');
    flatpickr.localize(flatpickr.l10ns.es);
    const now = new Date();
    const esReactivacion = <?= $modo_reactivacion ? 'true' : 'false' ?>;
    let cierreBloqueado = true;
>>>>>>> 9ad8a0c7aac8bcdad612d9182d5320658bd19a15

            // Cargar funciones existentes desde PHP (solo en modo edición normal)
            // En reactivación, empezamos vacío para obligar a crear nuevas
            let funcs = [];
            let formModificado = false; // Tracker para beforeunload

            <?php if (!$modo_reactivacion): ?>
                <?php foreach ($funciones_existentes as $f): ?>
                    <?php
                    $fechaObj = $f['fecha'];
                    $esPasada = $f['estado'] == 1 || $fechaObj < new DateTime();
                    ?>
                    funcs.push({
                        id: <?= $f['id_funcion'] ?>,
                        date: new Date('<?= $fechaObj->format('c') ?>'),
                        past: <?= $esPasada ? 'true' : 'false' ?>,
                        boletos: <?= $f['boletos'] ?>
                    });
                <?php endforeach; ?>
            <?php endif; ?>

            const els = {
                add: document.getElementById('fAdd'),
                sub: document.getElementById('bSub'),
                list: document.getElementById('lista-funciones'),
                hid: document.getElementById('hidFunc'),
                no: document.getElementById('noFunc'),
                ttF: document.getElementById('ttFunc'),
                ttI: document.getElementById('ttIni'),
                ini: document.getElementById('ini'),
                fin: document.getElementById('fin'),
                desc: document.getElementById('desc'),
                img: document.getElementById('img'),
                tipo: document.getElementById('tipo'),
                ttDesc: document.getElementById('ttDesc'),
                ttTipo: document.getElementById('ttTipo'),
                lockBtn: document.getElementById('lockBtn')
            };

            const fpD = flatpickr("#fDate", {
                minDate: "today",
                dateFormat: "Y-m-d",
                onChange: (s, d) => {
                    if (d === new Date().toISOString().split('T')[0]) {
                        const minTime = new Date();
                        minTime.setMinutes(minTime.getMinutes() + 5);
                        fpT.set('minTime', minTime.getHours() + ':' + minTime.getMinutes());
                    } else {
                        fpT.set('minTime', null);
                    }
                    check();
                }
            });

            const fpT = flatpickr("#fTime", {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
                time_24hr: true,
                minuteIncrement: 15,
                onChange: check
            });

            // Inicio: mínimo ahora
            const fpI = flatpickr("#ini", {
                enableTime: true,
                minDate: now,
                dateFormat: "Y-m-d H:i",
                onChange: val
            });

<<<<<<< HEAD
            const fpE = flatpickr("#fin", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                clickOpens: false
            });

            // Botón de candado (visual, el backend siempre ajusta automáticamente)
            els.lockBtn.onclick = () => {
                cierreBloqueado = !cierreBloqueado;
                if (cierreBloqueado) {
                    els.lockBtn.innerHTML = '<i class="bi bi-lock-fill"></i>';
                    els.lockBtn.classList.remove('unlocked');
                    fpE.set('clickOpens', false);
=======
    function recalcularCierre() {
        const futuras = funcs.filter(f => !f.past);
        if (futuras.length && cierreBloqueado) {
            const ultima = futuras[futuras.length - 1].date;
            // Cierre automático: 2 horas después de última función
            const cierre = new Date(ultima.getTime() + 7200000); // +2 horas
            fpE.setDate(cierre, true);
        }
    }

    function check() { 
        els.add.disabled = !(fpD.selectedDates.length && fpT.selectedDates.length); 
    }

    els.add.onclick = () => {
        if (!fpD.selectedDates[0] || !fpT.selectedDates[0]) return;
        
        // Construcción robusta de fecha función (sin segundos ni ms)
        const dPart = fpD.selectedDates[0];
        const tPart = fpT.selectedDates[0];
        const dt = new Date(dPart.getFullYear(), dPart.getMonth(), dPart.getDate(), tPart.getHours(), tPart.getMinutes(), 0, 0);

        // Validar que sea futura con respecto a AHORA mismo (tiempo real)
        const now = new Date();
        // Margen de 1 minuto hacia el futuro
        if (dt <= new Date(now.getTime() + 60000)) {
            showModal('La función debe ser en el futuro (al menos 1 minuto adelante de ahora).', 'warning');
            return;
        }
        
        // Validar margen con Inicio de Venta
        if (fpI.selectedDates.length) {
            const iPart = fpI.selectedDates[0];
            const inicioDate = new Date(iPart.getFullYear(), iPart.getMonth(), iPart.getDate(), iPart.getHours(), iPart.getMinutes(), 0, 0);
            
            const minInicio = new Date(inicioDate.getTime() + 60000);
            if (dt < minInicio) {
                const horaFunc = dt.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                const horaMin = minInicio.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                showModal(`Conflicto de horario: La función (${horaFunc}) debe ser posterior al inicio de venta + 1min (${horaMin}).`, 'warning');
                return;
            }
        }
        
        // Validar margen con Cierre de Venta (si es manual)
        if (cierreBloqueado && fpE.selectedDates.length) {
            const ePart = fpE.selectedDates[0];
            const cierreDate = new Date(ePart.getFullYear(), ePart.getMonth(), ePart.getDate(), ePart.getHours(), ePart.getMinutes(), 0, 0);
            
            const maxCierre = new Date(cierreDate.getTime() - 60000);
            if (dt > maxCierre) {
                const horaFunc = dt.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                const horaMax = maxCierre.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                showModal(`Conflicto de horario: La función (${horaFunc}) debe ser anterior al cierre de venta - 1min (${horaMax}).`, 'warning');
                return;
            }
        }
        
        if (funcs.some(f => f.date.getTime() === dt.getTime())) {
            showModal('Ya existe una función programada para esta fecha y hora.', 'warning');
            return;
        }
        
        funcs.push({ id: 0, date: dt, past: false, boletos: 0 });
        funcs.sort((a, b) => a.date - b.date);
        formModificado = true; // Marcar como modificado
        fpD.clear();
        fpT.clear();
        check();
        upd();
    };

    function upd() {
        els.list.innerHTML = '';
        els.hid.innerHTML = '';
        
        const futuras = funcs.filter(f => !f.past);
        
        if (!funcs.length) { 
            els.list.appendChild(els.no); 
            fpI.set('maxDate', null); 
            fpE.setDate(null); 
        } else {
            funcs.forEach((f, i) => {
                const d = f.date;
                const fechaStr = d.toLocaleDateString('es-ES', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'});
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                const hours = String(d.getHours()).padStart(2, '0');
                const mins = String(d.getMinutes()).padStart(2, '0');
                const sqlDate = `${year}-${month}-${day} ${hours}:${mins}:00`;

                const clase = f.past ? 'past' : 'future';
                const icono = f.past ? 'bi-hourglass-bottom' : 'bi-calendar-event';
                
                // Protección de funciones con boletos vendidos
                const tieneBoletos = (f.boletos || 0) > 0;
                let btnDel = '';
                let boletosInfo = '';
                
                if (tieneBoletos) {
                    // Función protegida: mostrar candado y cantidad de boletos
                    btnDel = `<span class="func-protected" title="${f.boletos} boletos vendidos - No se puede eliminar"><i class="bi bi-lock-fill"></i></span>`;
                    boletosInfo = `<span class="boletos-count">${f.boletos} <i class="bi bi-ticket-fill"></i></span>`;
>>>>>>> 9ad8a0c7aac8bcdad612d9182d5320658bd19a15
                } else {
                    els.lockBtn.innerHTML = '<i class="bi bi-unlock-fill"></i>';
                    els.lockBtn.classList.add('unlocked');
                    fpE.set('clickOpens', true);
                }
                // Siempre recalcular automáticamente
                recalcularCierre();
            };

            function recalcularCierre() {
                const futuras = funcs.filter(f => !f.past);
                if (futuras.length) {
                    // Encontrar la función con la fecha mayor (máxima)
                    const funcionMayor = futuras.reduce((max, f) => f.date > max.date ? f : max, futuras[0]);
                    const cierre = new Date(funcionMayor.date.getTime() + 7200000);
                    fpE.setDate(cierre, true);
                }
            }

            function check() {
                els.add.disabled = !(fpD.selectedDates.length && fpT.selectedDates.length);
            }

            els.add.onclick = () => {
                if (!fpD.selectedDates[0] || !fpT.selectedDates[0]) return;

                let dt = new Date(fpD.selectedDates[0].getTime());
                dt.setHours(fpT.selectedDates[0].getHours());
                dt.setMinutes(fpT.selectedDates[0].getMinutes());
                dt.setSeconds(0);

                if (dt <= new Date(Date.now() + 60000)) {
                    alert("La función debe ser en el futuro.");
                    return;
                }

                if (funcs.some(f => f.date.getTime() === dt.getTime())) {
                    alert("Ya existe esta función.");
                    return;
                }

                funcs.push({ id: 0, date: dt, past: false, boletos: 0 });
                funcs.sort((a, b) => a.date - b.date);
                formModificado = true; // Marcar como modificado
                fpD.clear();
                fpT.clear();
                check();
                upd();
            };

            function upd() {
                els.list.innerHTML = '';
                els.hid.innerHTML = '';

                const futuras = funcs.filter(f => !f.past);

                if (!funcs.length) {
                    els.list.appendChild(els.no);
                    fpI.set('maxDate', null);
                    fpE.setDate(null);
                } else {
                    funcs.forEach((f, i) => {
                        const d = f.date;
                        const fechaStr = d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
                        const year = d.getFullYear();
                        const month = String(d.getMonth() + 1).padStart(2, '0');
                        const day = String(d.getDate()).padStart(2, '0');
                        const hours = String(d.getHours()).padStart(2, '0');
                        const mins = String(d.getMinutes()).padStart(2, '0');
                        const sqlDate = `${year}-${month}-${day} ${hours}:${mins}:00`;

                        const clase = f.past ? 'past' : 'future';
                        const icono = f.past ? 'bi-hourglass-bottom' : 'bi-calendar-event';

                        // Protección de funciones con boletos vendidos
                        const tieneBoletos = (f.boletos || 0) > 0;
                        let btnDel = '';
                        let boletosInfo = '';

                        if (tieneBoletos) {
                            // Función protegida: mostrar candado y cantidad de boletos
                            btnDel = `<span class="func-protected" title="${f.boletos} boletos vendidos - No se puede eliminar"><i class="bi bi-lock-fill"></i></span>`;
                            boletosInfo = `<span class="boletos-count">${f.boletos} <i class="bi bi-ticket-fill"></i></span>`;
                        } else {
                            btnDel = `<button type="button" onclick="del(${i})" title="Eliminar">×</button>`;
                        }

                        els.list.innerHTML += `<div class="funcion-item ${clase}${tieneBoletos ? ' protected' : ''}"><i class="bi ${icono}"></i> ${fechaStr}${boletosInfo}${btnDel}</div>`;

                        // Solo agregar al formulario si NO es pasada
                        if (!f.past) {
                            els.hid.innerHTML += `<input type="hidden" name="funciones[]" value="${sqlDate}">`;
                        }
                    });

                    // Limitar inicio venta
                    if (futuras.length) {
                        fpI.set('maxDate', new Date(futuras[0].date.getTime() - 60000));
                    }

                    recalcularCierre();
                }
                val();
            }

            window.del = i => {
                // Verificar si tiene boletos antes de eliminar
                if (funcs[i].boletos > 0) {
                    alert(`No se puede eliminar esta función porque tiene ${funcs[i].boletos} boletos vendidos.`);
                    return;
                }
                funcs.splice(i, 1);
                formModificado = true;
                upd();
            };

            function val() {
                let ok = true;
                [els.ttF, els.ttI, els.ttDesc, els.ttTipo].forEach(e => { if (e) e.style.display = 'none'; });
                document.querySelectorAll('.input-error').forEach(e => e.classList.remove('input-error'));

                if (!document.getElementById('tit').value.trim()) ok = false;

                const futuras = funcs.filter(f => !f.past);
                if (!futuras.length) {
                    err(els.ttF, null, 'Añade al menos una función futura.');
                    ok = false;
                }

                if (!fpI.selectedDates.length) {
                    if (futuras.length) { err(els.ttI, els.ini, 'Requerido.'); ok = false; }
                } else {
                    // Validar que inicio sea >= ahora
                    if (fpI.selectedDates[0] < new Date()) {
                        err(els.ttI, els.ini, 'No puede ser anterior a ahora.');
                        ok = false;
                    }
                    // Validar que inicio sea antes de primera función futura
                    if (futuras.length && fpI.selectedDates[0] >= futuras[0].date) {
                        err(els.ttI, els.ini, 'Debe ser antes de la primera función.');
                        ok = false;
                    }
                }

                if (!els.desc.value.trim()) { err(els.ttDesc, els.desc, 'Descripción obligatoria.'); ok = false; }

                els.sub.disabled = !ok;
                return ok;
            }

            function err(t, i, m) {
                t.textContent = m;
                t.style.display = 'flex';
                if (i) i.classList.add('input-error');
            }

            ['tit', 'desc', 'img', 'tipo'].forEach(id => {
                const el = document.getElementById(id);
                el.addEventListener(id === 'img' || id === 'tipo' ? 'change' : 'input', () => {
                    formModificado = true;
                    val();
                });
            });

            // Tracker para fechas
            document.getElementById('ini').addEventListener('change', () => formModificado = true);
            document.getElementById('fin').addEventListener('change', () => formModificado = true);

            // Interceptar navegación del navegador (tecla atrás, cerrar pestaña)
            window.addEventListener('beforeunload', function (e) {
                if (formModificado) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });
<<<<<<< HEAD

            document.getElementById('fEdit').addEventListener('submit', e => {
                // Verificación final: no permitir enviar con funciones pasadas
                const futuras = funcs.filter(f => !f.past);
                if (!futuras.length) {
                    e.preventDefault();
                    alert("No puedes guardar sin funciones futuras. Elimina las vencidas y agrega nuevas.");
                    return;
                }
                if (!val()) {
                    e.preventDefault();
                    alert("Faltan campos o hay errores.");
                } else {
                    // Desactivar advertencia de beforeunload al enviar correctamente
                    formModificado = false;
                }
            });
=======
            
            // Limitar inicio venta: eliminado para dar flexibilidad (se valida en val())
            // if (futuras.length) {
            //    fpI.set('maxDate', new Date(futuras[0].date.getTime() - 60000));
            // }
            
            recalcularCierre();
        }
        val();
    }
    
    window.del = i => { 
        // Verificar si tiene boletos antes de eliminar
        if (funcs[i].boletos > 0) {
            showModal(`No se puede eliminar esta función porque tiene ${funcs[i].boletos} boletos vendidos.`, 'error');
            return;
        }
        funcs.splice(i, 1); 
        formModificado = true;
        upd(); 
    };

    function val() {
        let ok = true; 
        [els.ttF, els.ttI, els.ttDesc, els.ttTipo].forEach(e => { if(e) e.style.display = 'none'; });
        document.querySelectorAll('.input-error').forEach(e => e.classList.remove('input-error'));
        
        if (!document.getElementById('tit').value.trim()) ok = false;
        
        const futuras = funcs.filter(f => !f.past);
        if (!futuras.length) { 
            err(els.ttF, null, 'Añade al menos una función futura.'); 
            ok = false; 
        }
        
        if (!fpI.selectedDates.length) { 
            if (futuras.length) { err(els.ttI, els.ini, 'Requerido.'); ok = false; }
        } else {
            // Validar que inicio sea >= ahora + 1 minuto
            if (fpI.selectedDates[0] <= new Date(Date.now() + 60000)) {
                err(els.ttI, els.ini, 'Debe ser al menos 1 minuto posterior a ahora.'); 
                ok = false;
            }
            // Validar que inicio sea al menos 1 minuto antes de primera función futura
            // Nota: Se elimina restricción visual maxDate para evitar bloqueos
            if (futuras.length && new Date(fpI.selectedDates[0].getTime() + 60000) > futuras[0].date) { 
                err(els.ttI, els.ini, 'Debe ser al menos 1 minuto antes de la primera función.'); 
                ok = false; 
            }
        }
        
        // Validar que cierre sea mayor que la última función (margen 1 min)
        if (fpE.selectedDates.length && futuras.length && cierreBloqueado) {
            const ultimaFuncion = futuras[futuras.length - 1]; // Usar futuras
            if (new Date(ultimaFuncion.date.getTime() + 60000) > fpE.selectedDates[0]) {
                showModal('El cierre de venta debe ser al menos 1 minuto después de la última función.', 'warning');
                ok = false;
            }
        }
>>>>>>> 9ad8a0c7aac8bcdad612d9182d5320658bd19a15

            upd();
        });

        function abrirModalCancelar() {
            // Si hay cambios, mostrar modal
            if (typeof formModificado !== 'undefined' && formModificado) {
                document.getElementById('modalCancelar').classList.add('active');
            } else {
                // No hay cambios, salir directamente
                goBack();
            }
        }
<<<<<<< HEAD

        function cerrarModal() {
            document.getElementById('modalCancelar').classList.remove('active');
=======
    });
    
    // Interceptar TODOS los clics en enlaces para mostrar modal premium
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        if (link && formModificado) {
            e.preventDefault();
            urlDestino = link.href;
            document.getElementById('modalCancelar').classList.add('active');
        }
    });
    
    // Interceptar navegación con botón atrás del navegador (history)
    window.addEventListener('popstate', function(e) {
        if (formModificado) {
            history.pushState(null, '', location.href);
            document.getElementById('modalCancelar').classList.add('active');
        }
    });
    // Agregar estado inicial para poder detectar popstate
    history.pushState(null, '', location.href);
    
    document.getElementById('fEdit').addEventListener('submit', e => { 
        // Verificación final: no permitir enviar con funciones pasadas
        const futuras = funcs.filter(f => !f.past);
        if (!futuras.length) {
            e.preventDefault();
            alert("No puedes guardar sin funciones futuras. Elimina las vencidas y agrega nuevas.");
            return;
>>>>>>> 9ad8a0c7aac8bcdad612d9182d5320658bd19a15
        }

        function confirmarSalida() {
            cerrarModal();
            goBack();
        }

<<<<<<< HEAD
        function goBack() {
            // Desactivar beforeunload
            window.onbeforeunload = null;

            document.body.classList.remove('loaded');
            document.body.classList.add('exiting');
=======
// Variable global para destino de navegación
let urlDestino = null;

function abrirModalCancelar() {
    // Si hay cambios, mostrar modal
    if (typeof formModificado !== 'undefined' && formModificado) {
        document.getElementById('modalCancelar').classList.add('active');
    } else {
        // No hay cambios, salir directamente
        goBack();
    }
}

function cerrarModal() {
    document.getElementById('modalCancelar').classList.remove('active');
    urlDestino = null; // Limpiar destino al cerrar
}
>>>>>>> 9ad8a0c7aac8bcdad612d9182d5320658bd19a15

            const esReactivacion = <?= $modo_reactivacion ? 'true' : 'false' ?>;
            const tab = esReactivacion ? 'historial' : 'activos';

<<<<<<< HEAD
            setTimeout(() => window.parent.location.href = 'index.php?tab=' + tab, 350);
        }
    </script>
=======
function goBack() {
    // Desactivar beforeunload
    window.onbeforeunload = null;
    formModificado = false;
    
    document.body.classList.remove('loaded');
    document.body.classList.add('exiting');
    
    // Si hay URL de destino específica, usarla
    if (urlDestino) {
        setTimeout(() => window.location.href = urlDestino, 350);
    } else {
        const esReactivacion = <?= $modo_reactivacion ? 'true' : 'false' ?>;
        const tab = esReactivacion ? 'historial' : 'activos';
        setTimeout(() => window.parent.location.href = 'index.php?tab=' + tab, 350);
    }
}

// === MODAL DE ALERTAS PREMIUM ===
function showModal(mensaje, tipo = 'warning') {
    const modal = document.getElementById('modalAlerta');
    const iconContainer = document.getElementById('modalAlertaIconContainer');
    const icon = document.getElementById('modalAlertaIcon');
    const titulo = document.getElementById('modalAlertaTitulo');
    const mensajeEl = document.getElementById('modalAlertaMensaje');
    
    mensajeEl.textContent = mensaje;
    
    // Configurar según tipo
    if (tipo === 'error') {
        iconContainer.style.background = 'linear-gradient(135deg, #ff453a 0%, #d70015 100%)';
        icon.className = 'bi bi-x-circle-fill modal-icon';
        titulo.textContent = 'Error';
    } else if (tipo === 'success') {
        iconContainer.style.background = 'linear-gradient(135deg, #30d158 0%, #00b341 100%)';
        icon.className = 'bi bi-check-circle-fill modal-icon';
        titulo.textContent = '¡Éxito!';
    } else {
        iconContainer.style.background = 'linear-gradient(135deg, #ff9f0a 0%, #ff6b35 50%, #ff453a 100%)';
        icon.className = 'bi bi-exclamation-triangle-fill modal-icon';
        titulo.textContent = 'Atención';
    }
    
    modal.classList.add('active');
}

function cerrarModalAlerta() {
    document.getElementById('modalAlerta').classList.remove('active');
}
</script>
>>>>>>> 9ad8a0c7aac8bcdad612d9182d5320658bd19a15
</body>

</html>