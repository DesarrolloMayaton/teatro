<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Mexico_City'); // Zona horaria local

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../conexion.php";
if (file_exists("../transacciones_helper.php")) {
    require_once "../transacciones_helper.php";
}

// Verificar permisos
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="display:flex;height:100vh;align-items:center;justify-content:center;font-family:sans-serif;color:#ef4444;"><h1>Acceso Denegado</h1></div>');
}

$errores_php = [];

// ==================================================================
// 2. PROCESAR FORMULARIO (POST)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitización de datos de entrada
    $titulo = htmlspecialchars(trim($_POST['titulo']), ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars(trim($_POST['descripcion']), ENT_QUOTES, 'UTF-8');
    $tipo = (int) $_POST['tipo'];
    $ini = $_POST['inicio_venta'];
    $fin = $_POST['cierre_venta'];

    // Validaciones básicas
    if (empty($titulo))
        $errores_php[] = "Falta el título.";
    if (strlen($titulo) > 200)
        $errores_php[] = "El título es demasiado largo (máximo 200 caracteres).";
    if (strlen($desc) > 5000)
        $errores_php[] = "La descripción es demasiado larga (máximo 5000 caracteres).";
    if (empty($_POST['funciones']))
        $errores_php[] = "Debe agregar al menos una función.";
    if (empty($ini))
        $errores_php[] = "Falta inicio de venta.";
    if ($tipo < 1 || $tipo > 2)
        $errores_php[] = "Tipo de escenario inválido.";

    // Validación: hora de inicio no menor a ahora
    if (!empty($ini) && strtotime($ini) < time()) {
        $errores_php[] = "La fecha/hora de inicio de venta no puede ser anterior a ahora.";
    }

    // Validación: funciones futuras
    if (!empty($_POST['funciones'])) {
        foreach ($_POST['funciones'] as $func) {
            if (strtotime($func) <= time()) {
                $errores_php[] = "Todas las funciones deben ser en el futuro.";
                break;
            }
        }

        // Auto-ajustar cierre: 2 horas después de la función con hora mayor
        $ultimaFuncion = max(array_map('strtotime', $_POST['funciones']));
        $fin = date('Y-m-d H:i:s', $ultimaFuncion + 7200); // +2 horas automáticamente
    }

    // Imagen (Obligatoria al crear)
    $imagen_ruta = "";
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            if (!is_dir("imagenes"))
                mkdir("imagenes", 0755, true);
            $ruta = "imagenes/evt_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
                $imagen_ruta = $ruta;
            } else
                $errores_php[] = "Error al guardar imagen.";
        } else
            $errores_php[] = "Formato de imagen no válido.";
    } else {
        $errores_php[] = "La imagen es obligatoria.";
    }

    if (empty($errores_php)) {
        $conn->begin_transaction();
        try {
            // 1. Insertar Evento
            $stmt = $conn->prepare("INSERT INTO evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sssiss", $titulo, $desc, $imagen_ruta, $tipo, $ini, $fin);
            $stmt->execute();
            $id_nuevo = $conn->insert_id;
            $stmt->close();

            // 2. Insertar Funciones
            $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora, estado) VALUES (?, ?, 0)");
            foreach ($_POST['funciones'] as $fh) {
                $stmt_f->bind_param("is", $id_nuevo, $fh);
                $stmt_f->execute();
            }
            $stmt_f->close();

            // =========================================================
            // 3. INSERTAR 3 CATEGORÍAS POR DEFECTO
            // =========================================================
            $stmt_c = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");

            $nom = 'General';
            $prec = 80;
            $col = '#cbd5e1';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();
            $id_cat_gen = $conn->insert_id;

            $nom = 'Discapacitado';
            $prec = 80;
            $col = '#2563eb';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();

            $nom = 'No Venta';
            $prec = 0;
            $col = '#0f172a';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();

            $stmt_c->close();

            // 4. Generar Mapa de Asientos
            $mapa = [];
            if ($tipo == 2) {
                for ($f = 1; $f <= 10; $f++) {
                    for ($a = 1; $a <= 12; $a++)
                        $mapa["PB$f-$a"] = $id_cat_gen;
                }
            }
            foreach (range('A', 'O') as $l) {
                for ($a = 1; $a <= 26; $a++)
                    $mapa["$l$a"] = $id_cat_gen;
            }
            for ($a = 1; $a <= 30; $a++)
                $mapa["P$a"] = $id_cat_gen;

            $json = json_encode($mapa);
            $conn->query("UPDATE evento SET mapa_json = '$json' WHERE id_evento = $id_nuevo");

            $conn->commit();

            if (function_exists('registrar_transaccion'))
                registrar_transaccion('evento_crear', "Creó evento: $titulo");

            // PANTALLA DE ÉXITO
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
                        opacity: 0;
                        transform: scale(0.9);
                        animation: popIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
                        box-shadow: 0 0 0 8px rgba(50, 215, 75, 0.1);
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
                        border-radius: 3px;
                        transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
                    }

                    @keyframes popIn {
                        to {
                            opacity: 1;
                            transform: scale(1);
                        }
                    }

                    @keyframes fadeOut {
                        to {
                            opacity: 0;
                            transform: translateY(10px);
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
                    <h4 class="fw-bold mb-2">¡Evento Creado!</h4>
                    <p class="small mb-0">El evento ya está disponible en cartelera.</p>
                    <div class="progress-track">
                        <div class="progress-fill" id="pBar"></div>
                    </div>
                    <p class="mt-2" style="font-size: 0.75rem; font-weight: 600;">VOLVIENDO A ACTIVOS...</p>
                </div>
                <script>
                    setTimeout(() => document.getElementById('pBar').style.width = '100%', 100);
                    localStorage.setItem("evt_upd", Date.now());
                    setTimeout(() => {
                        document.body.classList.add('exiting');
                        setTimeout(() => { window.location.href = "act_evento.php"; }, 350);
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
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Evento</title>
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
            background: var(--bg-secondary);
            padding: 8px 14px;
            border-radius: 20px;
            border: 1px solid var(--accent-blue);
            color: var(--accent-blue);
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
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

        /* Modal Ultra Premium */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(160deg, #1e1e22 0%, #141416 50%, #1a1a1e 100%);
            border-radius: 24px;
            padding: 0;
            max-width: 440px;
            width: 92%;
            border: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.05),
                0 25px 80px rgba(0, 0, 0, 0.7),
                0 0 100px rgba(255, 159, 10, 0.15);
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
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        }

        @keyframes modalPop {
            from {
                opacity: 0;
                transform: scale(0.85) translateY(-30px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
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
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.2) 0%, transparent 50%);
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
            filter: drop-shadow(0 5px 15px rgba(255, 69, 58, 0.5));
        }

        .modal-icon {
            font-size: 4rem;
            color: white;
            position: relative;
            z-index: 1;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: iconPulse 2s ease-in-out infinite;
        }

        @keyframes iconPulse {

            0%,
            100% {
                transform: scale(1) rotate(0deg);
            }

            25% {
                transform: scale(1.05) rotate(-3deg);
            }

            75% {
                transform: scale(1.05) rotate(3deg);
            }
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
            background: linear-gradient(90deg, transparent, rgba(255, 159, 10, 0.3), transparent);
        }

        .modal-content h5 {
            color: #ffffff;
            margin-bottom: 16px;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .modal-content p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0;
            font-size: 1rem;
            line-height: 1.7;
        }

        .modal-content p strong {
            color: var(--warning);
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
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.1), transparent);
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
            box-shadow: 0 8px 25px rgba(21, 97, 240, 0.4);
        }

        .btn-stay:hover {
            box-shadow: 0 12px 35px rgba(21, 97, 240, 0.5);
        }

        .btn-leave {
            background: rgba(255, 69, 58, 0.1);
            color: var(--danger);
            border: 2px solid rgba(255, 69, 58, 0.5);
        }

        .btn-leave:hover {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
            box-shadow: 0 8px 25px rgba(255, 69, 58, 0.4);
        }
    </style>
</head>

<body>

    <div class="main-wrapper">
        <div style="margin-bottom: 20px;">
            <button onclick="confirmarSalida()" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Eventos
            </button>
        </div>

        <div class="card">
            <div class="page-header">
                <h2>
                    <i class="bi bi-plus-circle-fill"></i>Crear Nuevo Evento
                </h2>
            </div>

            <?php if ($errores_php): ?>
                <div class="alert-danger">
                    <ul>
                        <?php foreach ($errores_php as $e)
                            echo "<li>$e</li>"; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="fCreate" method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-12">
                        <label class="form-label">Título del Evento</label>
                        <input type="text" id="tit" name="titulo" class="form-control"
                            style="font-size: 1.1rem; font-weight: 600;" required placeholder="Nombre del evento">
                    </div>

                    <div class="col-12">
                        <div class="funciones-section">
                            <label class="form-label"><i class="bi bi-calendar-week me-2"></i>Gestión de
                                Funciones</label>
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
                        <input type="text" id="ini" name="inicio_venta" class="form-control" readonly required
                            placeholder="Selecciona fecha y hora">
                        <div id="ttIni" class="tooltip-error"></div>
                        <div class="form-text"><i class="bi bi-info-circle"></i> No puede ser anterior a ahora</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" style="color: var(--text-muted);">Cierre Venta (Automático)</label>
                        <div class="cierre-container">
                            <input type="text" id="fin" name="cierre_venta" class="form-control" readonly required
                                style="padding-right: 50px;">
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
                        <textarea id="desc" name="descripcion" class="form-control" rows="4" required
                            placeholder="Describe el evento..."></textarea>
                        <div id="ttDesc" class="tooltip-error"></div>
                    </div>

                    <div class="col-md-7">
                        <label class="form-label">Imagen Promocional</label>
                        <input type="file" id="img" name="imagen" class="form-control" accept="image/*" required>
                        <div id="ttImg" class="tooltip-error"></div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Tipo de Escenario</label>
                        <select id="tipo" name="tipo" class="form-control" required>
                            <option value="">-- Selecciona --</option>
                            <option value="1">🎭 Teatro (420 Butacas)</option>
                            <option value="2">🚶 Pasarela (540 Butacas)</option>
                        </select>
                        <div id="ttTipo" class="tooltip-error"></div>
                    </div>
                </div>

                <hr>
                <button type="submit" id="bSub" class="btn btn-primary"
                    style="width: 100%; padding: 16px; font-size: 1.1rem;" disabled>
                    <i class="bi bi-check2-circle"></i> Crear Evento
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
                <p>Tienes cambios sin guardar. Si sales ahora, perderás toda la información del nuevo evento.</p>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-leave" onclick="goBack()">
                    <i class="bi bi-box-arrow-left"></i> Salir
                </button>
                <button class="btn btn-stay" onclick="cerrarModal()">
                    <i class="bi bi-pencil-fill"></i> Seguir Editando
                </button>
            </div>
        </div>
    </div>

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
            let funcs = [];
            let cierreBloqueado = true;
            let formModificado = false; // Tracker para beforeunload

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
                ttImg: document.getElementById('ttImg'),
                ttTipo: document.getElementById('ttTipo'),
                lockBtn: document.getElementById('lockBtn')
            };

            // Flatpickr para fecha de función (solo futuro)
            const fpD = flatpickr("#fDate", {
                minDate: "today",
                dateFormat: "Y-m-d",
                onChange: (s, d) => {
                    if (d === new Date().toISOString().split('T')[0]) {
                        // Si es hoy, mínimo hora actual + 5 min
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

            // Inicio venta: mínimo ahora
            const fpI = flatpickr("#ini", {
                enableTime: true,
                minDate: now,
                dateFormat: "Y-m-d H:i",
                onChange: val
            });

            // Cierre venta: bloqueado por defecto
            const fpE = flatpickr("#fin", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                clickOpens: false
            });

            // Botón de candado para cierre
            els.lockBtn.onclick = () => {
                cierreBloqueado = !cierreBloqueado;
                if (cierreBloqueado) {
                    els.lockBtn.innerHTML = '<i class="bi bi-lock-fill"></i>';
                    els.lockBtn.classList.remove('unlocked');
                    fpE.set('clickOpens', false);
                    // Recalcular cierre automático
                    recalcularCierre();
                } else {
                    els.lockBtn.innerHTML = '<i class="bi bi-unlock-fill"></i>';
                    els.lockBtn.classList.add('unlocked');
                    fpE.set('clickOpens', true);
                }
            };

            // Función para recalcular cierre: 2 horas después de la función con fecha mayor
            function recalcularCierre() {
                if (funcs.length) {
                    const funcionMayor = funcs.reduce((max, f) => f > max ? f : max, funcs[0]);
                    const cierre = new Date(funcionMayor.getTime() + 7200000);
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

                // Validar que la función sea futura (al menos 1 min)
                if (dt <= new Date(Date.now() + 60000)) {
                    showModal("La función debe ser en el futuro.", 'warning');
                    return;
                }

                if (funcs.some(d => d.getTime() === dt.getTime())) {
                    showModal("Ya existe esta función.", 'warning');
                    return;
                }

                funcs.push(dt);
                funcs.sort((a, b) => a - b);
                formModificado = true; // Marcar como modificado
                fpD.clear();
                fpT.clear();
                check();
                upd();
            };

            function upd() {
                els.list.innerHTML = '';
                els.hid.innerHTML = '';

                if (!funcs.length) {
                    els.list.appendChild(els.no);
                    fpI.set('maxDate', null);
                    fpE.setDate(null);
                } else {
                    funcs.forEach((d, i) => {
                        const fechaStr = d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
                        const year = d.getFullYear();
                        const month = String(d.getMonth() + 1).padStart(2, '0');
                        const day = String(d.getDate()).padStart(2, '0');
                        const hours = String(d.getHours()).padStart(2, '0');
                        const mins = String(d.getMinutes()).padStart(2, '0');
                        const sqlDate = `${year}-${month}-${day} ${hours}:${mins}:00`;

                        els.list.innerHTML += `<div class="funcion-item"><i class="bi bi-calendar-event"></i> ${fechaStr}<button type="button" onclick="del(${i})" title="Eliminar">×</button></div>`;
                        els.hid.innerHTML += `<input type="hidden" name="funciones[]" value="${sqlDate}">`;
                    });

                    // Límite de inicio: antes de la primera función
                    fpI.set('maxDate', new Date(funcs[0].getTime() - 60000));

                    // Cierre automático: 2 horas después de la función con fecha mayor
                    recalcularCierre();
                }
                val();
            }

            window.del = i => {
                funcs.splice(i, 1);
                formModificado = true; // Marcar como modificado
                upd();
            };

            function val() {
                let ok = true;
                [els.ttF, els.ttI, els.ttDesc, els.ttImg, els.ttTipo].forEach(e => { if (e) e.style.display = 'none'; });
                document.querySelectorAll('.input-error').forEach(e => e.classList.remove('input-error'));

                if (!document.getElementById('tit').value.trim()) ok = false;
                if (!funcs.length) { err(els.ttF, null, 'Añade al menos una función.'); ok = false; }

                if (!fpI.selectedDates.length) {
                    if (funcs.length) { err(els.ttI, els.ini, 'Requerido.'); ok = false; }
                } else {
                    // Validar que inicio sea >= ahora
                    if (fpI.selectedDates[0] < new Date()) {
                        err(els.ttI, els.ini, 'No puede ser anterior a ahora.');
                        ok = false;
                    }
                    // Validar que inicio sea antes de primera función
                    if (funcs.length && fpI.selectedDates[0] >= funcs[0]) {
                        err(els.ttI, els.ini, 'Debe ser anterior a la primera función.');
                        ok = false;
                    }
                }

                // El cierre se ajusta automáticamente - no es necesario validar

                if (!els.desc.value.trim()) { err(els.ttDesc, els.desc, 'Descripción obligatoria.'); ok = false; }
                if (!els.img.files.length) { err(els.ttImg, els.img, 'Imagen obligatoria.'); ok = false; }
                if (!els.tipo.value) { err(els.ttTipo, els.tipo, 'Selecciona escenario.'); ok = false; }

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

            document.getElementById('fCreate').addEventListener('submit', e => {
                if (!val()) {
                    e.preventDefault();
                    showModal('Por favor, completa todos los campos requeridos y corrige los errores antes de continuar.', 'error');
                } else {
                    // Desactivar advertencia al enviar correctamente
                    formModificado = false;
                }
            });
        });

        // Variable global para saber si confirmarSalida viene de clic en botón
        let urlDestino = null;

        function confirmarSalida(destino = null) {
            urlDestino = destino;

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
            urlDestino = null;
        }

        function goBack() {
            // Desactivar beforeunload y tracking
            window.onbeforeunload = null;
            formModificado = false;

            document.body.classList.remove('loaded');
            document.body.classList.add('exiting');

            const destino = urlDestino || 'act_evento.php';
            setTimeout(() => window.location.href = destino, 350);
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
</body>

</html>