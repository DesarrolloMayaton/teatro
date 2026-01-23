<?php
// ════════════════════════════════════════════════════════════════════════════
// CREAR EVENTO - VERSIÓN RENOVADA CON VALIDACIONES COMPLETAS
// ════════════════════════════════════════════════════════════════════════════
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include "../conexion.php";
if(file_exists("../transacciones_helper.php")) { require_once "../transacciones_helper.php"; }

// Verificar permisos
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="display:flex;height:100vh;align-items:center;justify-content:center;font-family:sans-serif;color:#ef4444;"><h1>Acceso Denegado</h1></div>');
}

$errores_php = [];

// ════════════════════════════════════════════════════════════════════════════
// PROCESAR FORMULARIO (POST)
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sanitizar y validar todos los campos
    $titulo = trim(strip_tags($_POST['titulo'] ?? ''));
    $titulo = preg_replace('/[<>"\'\/\\\\;]/', '', $titulo); // Eliminar caracteres peligrosos
    $titulo = mb_substr($titulo, 0, 200); // Limitar longitud
    
    $desc = trim(strip_tags($_POST['descripcion'] ?? ''));
    $desc = preg_replace('/[<>]/', '', $desc);
    $desc = mb_substr($desc, 0, 2000);
    
    $tipo = $_POST['tipo'] ?? '';
    if (!in_array($tipo, ['1', '2'])) $tipo = ''; // Solo valores válidos
    
    $ini = $_POST['inicio_venta'] ?? '';
    $fin = $_POST['cierre_venta'] ?? '';
    
    // Validar formato de fechas
    function validarFecha($fecha) {
        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $fecha);
    }
    
    // Validaciones del servidor
    if (empty($titulo)) $errores_php[] = "El título es obligatorio.";
    if (strlen($titulo) < 3) $errores_php[] = "El título debe tener al menos 3 caracteres.";
    if (strlen($titulo) > 200) $errores_php[] = "El título no puede exceder 200 caracteres.";
    
    if (empty($desc)) $errores_php[] = "La descripción es obligatoria.";
    if (strlen($desc) < 10) $errores_php[] = "La descripción debe tener al menos 10 caracteres.";
    
    if (empty($tipo)) $errores_php[] = "Selecciona el tipo de escenario.";
    if (empty($_POST['funciones'])) $errores_php[] = "Debe agregar al menos una función.";
    if (empty($ini)) $errores_php[] = "Define la fecha de inicio de venta.";
    if (!empty($ini) && !validarFecha($ini)) $errores_php[] = "Formato de fecha de inicio inválido.";
    if (!empty($fin) && !validarFecha($fin)) $errores_php[] = "Formato de fecha de cierre inválido.";

    // Validar que las funciones sean futuras y tengan formato correcto
    if (!empty($_POST['funciones'])) {
        $ahora = new DateTime();
        foreach ($_POST['funciones'] as $fh) {
            if (!validarFecha($fh)) {
                $errores_php[] = "Formato de función inválido: $fh";
                break;
            }
            $fechaFunc = new DateTime($fh);
            if ($fechaFunc <= $ahora) {
                $errores_php[] = "Todas las funciones deben ser futuras.";
                break;
            }
            // Validar que la hora esté en rango válido (00:00 - 23:59)
            $hora = (int)$fechaFunc->format('H');
            $minuto = (int)$fechaFunc->format('i');
            if ($hora < 0 || $hora > 23 || $minuto < 0 || $minuto > 59) {
                $errores_php[] = "Hora de función inválida.";
                break;
            }
        }
    }
    
    // Validar que cierre sea después de inicio y después de todas las funciones
    if (!empty($ini) && !empty($fin) && validarFecha($ini) && validarFecha($fin)) {
        $fechaIni = new DateTime($ini);
        $fechaFin = new DateTime($fin);
        if ($fechaFin <= $fechaIni) {
            $errores_php[] = "El cierre de venta debe ser después del inicio.";
        }
    }

    // Imagen (Obligatoria al crear)
    $imagen_ruta = "";
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
         $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
         if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
             if (!is_dir("imagenes")) mkdir("imagenes", 0755, true);
             $ruta = "imagenes/evt_" . time() . "." . $ext;
             if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
                 $imagen_ruta = $ruta;
             } else $errores_php[] = "Error al guardar la imagen.";
         } else $errores_php[] = "Formato de imagen no válido (usa JPG, PNG, GIF o WEBP).";
    } else {
        $errores_php[] = "La imagen promocional es obligatoria.";
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

            // 3. INSERTAR 3 CATEGORÍAS POR DEFECTO
            $stmt_c = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
            
            // A) General
            $nom = 'General'; $prec = 80; $col = '#cbd5e1';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();
            $id_cat_gen = $conn->insert_id;

            // B) Discapacitado
            $nom = 'Discapacitado'; $prec = 80; $col = '#2563eb';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();

            // C) No Venta
            $nom = 'No Venta'; $prec = 0; $col = '#0f172a';
            $stmt_c->bind_param("isds", $id_nuevo, $nom, $prec, $col);
            $stmt_c->execute();
            $stmt_c->close();

            // 4. Generar Mapa de Asientos
            $mapa = [];
            if ($tipo == 2) { // Pasarela
                for ($f=1; $f<=10; $f++) {
                    for ($a=1; $a<=12; $a++) $mapa["PB$f-$a"] = $id_cat_gen;
                }
            }
            foreach (range('A','O') as $l) {
                for ($a=1; $a<=26; $a++) $mapa["$l$a"] = $id_cat_gen;
            }
            for ($a=1; $a<=30; $a++) $mapa["P$a"] = $id_cat_gen;

            $json = json_encode($mapa);
            $conn->query("UPDATE evento SET mapa_json = '$json' WHERE id_evento = $id_nuevo");

            $conn->commit();
            
            if(function_exists('registrar_transaccion')) registrar_transaccion('evento_crear', "Creó evento: $titulo");

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
                .success-card { background: linear-gradient(145deg, #1e293b, #334155); border-radius: 24px; padding: 48px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.05); max-width: 420px; width: 90%; opacity: 0; transform: scale(0.9) translateY(20px); animation: popIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
                .icon-circle { width: 88px; height: 88px; background: linear-gradient(135deg, #22c55e, #16a34a); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 44px; margin: 0 auto 24px; box-shadow: 0 0 0 8px rgba(34, 197, 94, 0.15), 0 10px 30px rgba(34, 197, 94, 0.3); color: white; }
                h4 { color: #f8fafc; font-weight: 700; margin-bottom: 8px; }
                p { color: #94a3b8; }
                .progress-track { height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; margin-top: 32px; overflow: hidden; }
                .progress-fill { height: 100%; background: linear-gradient(90deg, #22c55e, #4ade80); width: 0; border-radius: 3px; transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1); }
                @keyframes popIn { to { opacity: 1; transform: scale(1) translateY(0); } }
                body.exiting .success-card { animation: fadeOut 0.4s ease-in forwards; }
                @keyframes fadeOut { to { opacity: 0; transform: scale(0.95) translateY(-10px); } }
            </style>
            </head>
            <body>
                <div class="success-card">
                    <div class="icon-circle"><i class="bi bi-check-lg"></i></div>
                    <h4>¡Evento Creado Exitosamente!</h4>
                    <p class="small mb-0">El evento ya está disponible en cartelera.</p>
                    <div class="progress-track"><div class="progress-fill" id="pBar"></div></div>
                    <p class="mt-3 small" style="color: #64748b; font-weight: 600; letter-spacing: 0.05em;">VOLVIENDO A EVENTOS...</p>
                </div>
                <script>
                    setTimeout(() => document.getElementById('pBar').style.width = '100%', 100);
                    localStorage.setItem("evt_upd", Date.now());
                    setTimeout(() => { 
                        document.body.classList.add('exiting'); 
                        setTimeout(() => { window.location.href = "act_evento.php"; }, 350); 
                    }, 1400); 
                </script>
            </body>
            </html>
            <?php exit;

        } catch (Exception $e) { $conn->rollback(); $errores_php[] = "Error de base de datos: " . $e->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Evento · Teatro</title>
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
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.4);
        --shadow-lg: 0 12px 40px rgba(0,0,0,0.5);
        --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * { box-sizing: border-box; }
    
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: linear-gradient(135deg, var(--bg-primary) 0%, #1a1a2e 100%);
        color: var(--text-primary);
        padding: 32px 20px;
        min-height: 100vh;
        opacity: 0;
        transition: opacity 0.4s ease;
    }
    body.loaded { opacity: 1; }
    body.exiting { opacity: 0; transform: translateY(-10px); }

    .main-wrapper { max-width: 900px; margin: 0 auto; }

    /* Header del formulario */
    .form-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 32px;
    }
    
    .btn-back {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        cursor: pointer;
        transition: var(--transition);
    }
    .btn-back:hover {
        background: var(--bg-tertiary);
        color: var(--text-primary);
        transform: translateX(-3px);
    }

    .header-title {
        flex: 1;
    }
    .header-title h1 {
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0 0 4px 0;
        background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .header-title p {
        color: var(--text-muted);
        margin: 0;
        font-size: 0.9rem;
    }

    /* Card principal */
    .form-card {
        background: var(--bg-secondary);
        border-radius: var(--radius-xl);
        border: 1px solid var(--border);
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }

    .card-section {
        padding: 32px;
        border-bottom: 1px solid var(--border);
    }
    .card-section:last-child { border-bottom: none; }

    .section-title {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 24px;
    }
    .section-title i {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--accent), #6366f1);
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    /* Inputs */
    .form-group { margin-bottom: 20px; }
    .form-group:last-child { margin-bottom: 0; }

    .form-label {
        display: block;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }
    .form-label.required::after {
        content: ' *';
        color: var(--danger);
    }

    .form-control, .form-select {
        width: 100%;
        padding: 14px 16px;
        background: var(--bg-input);
        border: 2px solid var(--border);
        border-radius: var(--radius-md);
        color: var(--text-primary);
        font-size: 1rem;
        font-family: inherit;
        transition: var(--transition);
    }
    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--border-focus);
        box-shadow: 0 0 0 4px var(--accent-glow);
        background: var(--bg-tertiary);
    }
    .form-control::placeholder { color: var(--text-muted); }

    .form-control.is-invalid {
        border-color: var(--danger);
        background: rgba(239, 68, 68, 0.1);
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    /* Gestión de Funciones */
    .funciones-container {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        padding: 24px;
    }

    .funciones-input-group {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
    }
    .funciones-input-group .form-control {
        flex: 1;
    }

    .btn-add-funcion {
        padding: 14px 20px;
        background: linear-gradient(135deg, var(--success), #16a34a);
        border: none;
        border-radius: var(--radius-md);
        color: white;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: var(--transition);
        white-space: nowrap;
    }
    .btn-add-funcion:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
    }
    .btn-add-funcion:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .funciones-lista {
        min-height: 80px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 16px;
        background: var(--bg-secondary);
        border-radius: var(--radius-md);
        border: 2px dashed var(--border);
    }

    .funcion-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
        border: 1px solid var(--accent);
        border-radius: 50px;
        color: var(--accent);
        font-weight: 600;
        font-size: 0.9rem;
        animation: slideIn 0.3s ease;
    }
    @keyframes slideIn {
        from { opacity: 0; transform: scale(0.8); }
        to { opacity: 1; transform: scale(1); }
    }

    .funcion-item .btn-remove {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: rgba(239, 68, 68, 0.2);
        border: none;
        color: var(--danger);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
        font-size: 0.75rem;
    }
    .funcion-item .btn-remove:hover {
        background: var(--danger);
        color: white;
        transform: scale(1.1);
    }

    .funciones-empty {
        width: 100%;
        text-align: center;
        color: var(--text-muted);
        font-style: italic;
        padding: 20px;
    }

    .validation-msg {
        display: none;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--danger);
    }
    .validation-msg.show { display: flex; }

    /* Grid de fechas */
    .dates-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .btn-toggle-cierre {
        padding: 6px 12px;
        background: var(--bg-tertiary);
        border: 1px solid var(--border);
        border-radius: 20px;
        color: var(--text-muted);
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .btn-toggle-cierre:hover {
        background: var(--accent);
        border-color: var(--accent);
        color: white;
    }
    .btn-toggle-cierre.active {
        background: var(--success);
        border-color: var(--success);
        color: white;
    }

    .date-field-locked {
        position: relative;
    }
    .date-field-locked .form-control {
        background: var(--bg-primary);
        cursor: not-allowed;
        padding-right: 45px;
    }
    .date-field-locked.unlocked .form-control {
        background: var(--bg-input);
        cursor: pointer;
        border-color: var(--success);
    }
    .date-field-locked .lock-icon {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 1rem;
    }

    /* Upload de imagen */
    .image-upload {
        position: relative;
    }
    .image-upload-area {
        border: 2px dashed var(--border);
        border-radius: var(--radius-lg);
        padding: 40px 20px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--bg-primary);
    }
    .image-upload-area:hover {
        border-color: var(--accent);
        background: rgba(59, 130, 246, 0.05);
    }
    .image-upload-area.has-file {
        border-style: solid;
        border-color: var(--success);
        background: rgba(34, 197, 94, 0.05);
    }
    .image-upload-area i {
        font-size: 2.5rem;
        color: var(--text-muted);
        margin-bottom: 12px;
    }
    .image-upload-area.has-file i { color: var(--success); }
    .image-upload-area p {
        color: var(--text-secondary);
        margin: 0;
    }
    .image-upload-area .file-name {
        color: var(--success);
        font-weight: 600;
        margin-top: 8px;
    }
    .image-upload input[type="file"] {
        position: absolute;
        opacity: 0;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        cursor: pointer;
    }

    /* Tipo escenario */
    .escenario-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .escenario-option {
        position: relative;
    }
    .escenario-option input {
        position: absolute;
        opacity: 0;
    }
    .escenario-option label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding: 24px;
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: var(--radius-lg);
        cursor: pointer;
        transition: var(--transition);
        text-align: center;
    }
    .escenario-option label:hover {
        border-color: var(--accent);
        background: rgba(59, 130, 246, 0.05);
    }
    .escenario-option input:checked + label {
        border-color: var(--accent);
        background: rgba(59, 130, 246, 0.1);
        box-shadow: 0 0 0 4px var(--accent-glow);
    }
    .escenario-option .icon {
        font-size: 2rem;
    }
    .escenario-option .name {
        font-weight: 600;
        color: var(--text-primary);
    }
    .escenario-option .seats {
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    /* Botón submit */
    .submit-section {
        padding: 32px;
        background: linear-gradient(180deg, transparent, rgba(59, 130, 246, 0.05));
    }

    .btn-submit {
        width: 100%;
        padding: 18px 32px;
        background: linear-gradient(135deg, var(--accent), #6366f1);
        border: none;
        border-radius: var(--radius-md);
        color: white;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    .btn-submit:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(59, 130, 246, 0.4);
    }
    .btn-submit:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    /* Alertas de error */
    .alert-errors {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: var(--radius-md);
        padding: 16px 20px;
        margin-bottom: 24px;
    }
    .alert-errors ul {
        margin: 0;
        padding-left: 20px;
        color: var(--danger);
    }
    .alert-errors li { margin: 4px 0; }

    /* Modal */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .modal-overlay.show {
        display: flex;
        opacity: 1;
    }
    .modal-box {
        background: var(--bg-secondary);
        border-radius: var(--radius-xl);
        border: 1px solid var(--border);
        padding: 32px;
        max-width: 400px;
        width: 90%;
        text-align: center;
        transform: scale(0.9);
        transition: transform 0.3s ease;
    }
    .modal-overlay.show .modal-box {
        transform: scale(1);
    }
    .modal-box h3 {
        margin: 0 0 12px 0;
        color: var(--text-primary);
    }
    .modal-box p {
        color: var(--text-secondary);
        margin-bottom: 24px;
    }
    .modal-buttons {
        display: flex;
        gap: 12px;
    }
    .modal-buttons button {
        flex: 1;
        padding: 12px;
        border-radius: var(--radius-sm);
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }
    .btn-modal-cancel {
        background: var(--bg-tertiary);
        border: 1px solid var(--border);
        color: var(--text-primary);
    }
    .btn-modal-cancel:hover { background: var(--bg-primary); }
    .btn-modal-confirm {
        background: var(--danger);
        border: none;
        color: white;
    }
    .btn-modal-confirm:hover { filter: brightness(1.1); }

    @media (max-width: 768px) {
        .funciones-input-group { flex-direction: column; }
        .dates-grid { grid-template-columns: 1fr; }
        .escenario-options { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<div class="main-wrapper">
    <!-- Header -->
    <div class="form-header">
        <button type="button" class="btn-back" onclick="confirmarSalida()">
            <i class="bi bi-arrow-left"></i>
        </button>
        <div class="header-title">
            <h1><i class="bi bi-plus-circle-fill me-2"></i>Crear Nuevo Evento</h1>
            <p>Configura todos los detalles del evento</p>
        </div>
    </div>

    <!-- Card Principal -->
    <div class="form-card">
        <?php if($errores_php): ?>
            <div class="card-section">
                <div class="alert-errors">
                    <ul><?php foreach($errores_php as $e) echo "<li>$e</li>"; ?></ul>
                </div>
            </div>
        <?php endif; ?>

        <form id="formEvento" method="POST" enctype="multipart/form-data">
            <!-- Sección: Información Básica -->
            <div class="card-section">
                <div class="section-title">
                    <i class="bi bi-info-circle"></i>
                    <span>Información Básica</span>
                </div>

                <div class="form-group">
                    <label class="form-label required">Título del Evento</label>
                    <input type="text" id="titulo" name="titulo" class="form-control" placeholder="Ej: Concierto de Rock en Vivo" required>
                    <div class="validation-msg" id="val-titulo"><i class="bi bi-exclamation-circle"></i> <span></span></div>
                </div>

                <div class="form-group">
                    <label class="form-label required">Descripción</label>
                    <textarea id="descripcion" name="descripcion" class="form-control" placeholder="Describe el evento, artistas participantes, etc." required></textarea>
                    <div class="validation-msg" id="val-desc"><i class="bi bi-exclamation-circle"></i> <span></span></div>
                </div>
            </div>

            <!-- Sección: Funciones -->
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

            <!-- Sección: Fechas de Venta -->
            <div class="card-section">
                <div class="section-title">
                    <i class="bi bi-shop"></i>
                    <span>Periodo de Venta</span>
                </div>

                <div class="dates-grid">
                    <div class="form-group">
                        <label class="form-label required">Inicio de Venta</label>
                        <input type="text" id="inicioVenta" name="inicio_venta" class="form-control" placeholder="📅 Selecciona fecha y hora" readonly required>
                        <div class="validation-msg" id="val-inicio"><i class="bi bi-exclamation-circle"></i> <span></span></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                            <span>Cierre de Venta</span>
                            <button type="button" id="btnToggleCierre" class="btn-toggle-cierre" onclick="toggleCierreManual()" title="Activar modo manual">
                                <i class="bi bi-pencil-fill"></i> Personalizar
                            </button>
                        </label>
                        <div class="date-field-locked" id="cierreContainer">
                            <input type="text" id="cierreVenta" name="cierre_venta" class="form-control" readonly>
                            <i class="bi bi-lock-fill lock-icon" id="cierreLockIcon"></i>
                        </div>
                        <small id="cierreInfo" style="color: var(--text-muted); display: block; margin-top: 6px;">
                            <i class="bi bi-info-circle"></i> Automático: 2 horas después de la última función
                        </small>
                        <div class="validation-msg" id="val-cierre"><i class="bi bi-exclamation-circle"></i> <span></span></div>
                    </div>
                </div>
            </div>

            <!-- Sección: Imagen y Escenario -->
            <div class="card-section">
                <div class="section-title">
                    <i class="bi bi-image"></i>
                    <span>Imagen y Configuración</span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div class="form-group">
                        <label class="form-label required">Imagen Promocional</label>
                        <div class="image-upload">
                            <div class="image-upload-area" id="uploadArea">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <p>Arrastra o haz clic para subir</p>
                                <p class="file-name" id="fileName" style="display: none;"></p>
                            </div>
                            <input type="file" id="imagen" name="imagen" accept="image/*" required>
                        </div>
                        <div class="validation-msg" id="val-imagen"><i class="bi bi-exclamation-circle"></i> <span></span></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Tipo de Escenario</label>
                        <div class="escenario-options">
                            <div class="escenario-option">
                                <input type="radio" name="tipo" id="tipo1" value="1" required>
                                <label for="tipo1">
                                    <span class="icon">🎭</span>
                                    <span class="name">Teatro</span>
                                    <span class="seats">420 butacas</span>
                                </label>
                            </div>
                            <div class="escenario-option">
                                <input type="radio" name="tipo" id="tipo2" value="2">
                                <label for="tipo2">
                                    <span class="icon">🚶</span>
                                    <span class="name">Pasarela</span>
                                    <span class="seats">540 butacas</span>
                                </label>
                            </div>
                        </div>
                        <div class="validation-msg" id="val-tipo"><i class="bi bi-exclamation-circle"></i> <span></span></div>
                    </div>
                </div>
            </div>

            <!-- Botón Submit -->
            <div class="submit-section">
                <button type="submit" id="btnSubmit" class="btn-submit" disabled>
                    <i class="bi bi-check2-circle"></i>
                    Crear Evento
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de confirmación -->
<div class="modal-overlay" id="modalSalir">
    <div class="modal-box">
        <h3>¿Salir sin guardar?</h3>
        <p>Si sales ahora, perderás toda la información ingresada del nuevo evento.</p>
        <div class="modal-buttons">
            <button type="button" class="btn-modal-cancel" onclick="cerrarModal()">Seguir</button>
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
    // ESTADO Y ELEMENTOS
    // ═══════════════════════════════════════════════════════════════════
    let funciones = [];
    const ahora = new Date();
    
    const els = {
        titulo: document.getElementById('titulo'),
        desc: document.getElementById('descripcion'),
        imagen: document.getElementById('imagen'),
        uploadArea: document.getElementById('uploadArea'),
        fileName: document.getElementById('fileName'),
        listaFunciones: document.getElementById('listaFunciones'),
        funcionesEmpty: document.getElementById('funcionesEmpty'),
        funcionesHidden: document.getElementById('funcionesHidden'),
        btnAgregar: document.getElementById('btnAgregarFuncion'),
        btnSubmit: document.getElementById('btnSubmit'),
        inicioVenta: document.getElementById('inicioVenta'),
        cierreVenta: document.getElementById('cierreVenta')
    };

    // ═══════════════════════════════════════════════════════════════════
    // FLATPICKR CONFIGURATIONS
    // ═══════════════════════════════════════════════════════════════════
    const fpFecha = flatpickr("#inputFecha", {
        minDate: "today",
        dateFormat: "Y-m-d",
        onChange: (selectedDates, dateStr) => {
            // Si es hoy, limitar hora mínima
            const hoy = new Date().toISOString().split('T')[0];
            if (dateStr === hoy) {
                const minTime = new Date();
                minTime.setMinutes(minTime.getMinutes() + 30);
                fpHora.set('minTime', minTime.getHours() + ':' + String(minTime.getMinutes()).padStart(2, '0'));
            } else {
                fpHora.set('minTime', null);
            }
            checkAgregarBtn();
        }
    });

    const fpHora = flatpickr("#inputHora", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        minuteIncrement: 15,
        onChange: checkAgregarBtn
    });

    let cierreManual = false; // Estado del modo manual de cierre

    const fpInicio = flatpickr("#inicioVenta", {
        enableTime: true,
        minDate: ahora,
        dateFormat: "Y-m-d H:i",
        time_24hr: true,
        minuteIncrement: 15,
        onChange: validarFormulario
    });

    const fpCierre = flatpickr("#cierreVenta", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        clickOpens: false // Bloqueado 
    });

    // ═══════════════════════════════════════════════════════════════════
    // FUNCIONES DE FUNCIONES (valga la redundancia)
    // ═══════════════════════════════════════════════════════════════════
    function checkAgregarBtn() {
        els.btnAgregar.disabled = !(fpFecha.selectedDates.length && fpHora.selectedDates.length);
    }

    els.btnAgregar.addEventListener('click', () => {
        if (!fpFecha.selectedDates[0] || !fpHora.selectedDates[0]) return;

        // Construir fecha completa
        const fecha = new Date(fpFecha.selectedDates[0]);
        fecha.setHours(fpHora.selectedDates[0].getHours());
        fecha.setMinutes(fpHora.selectedDates[0].getMinutes());
        fecha.setSeconds(0);

        // Validaciones
        const ahora = new Date();
        
        // 1. No puede ser en el pasado o muy pronto
        if (fecha <= new Date(ahora.getTime() + 30 * 60 * 1000)) {
            mostrarError('val-func-add', 'La función debe ser al menos 30 minutos en el futuro.');
            return;
        }

        // 2. No duplicados
        if (funciones.some(f => f.getTime() === fecha.getTime())) {
            mostrarError('val-func-add', 'Esta función ya existe.');
            return;
        }

        // 3. Validar separación mínima de 2 horas entre funciones
        for (const f of funciones) {
            const diff = Math.abs(fecha.getTime() - f.getTime());
            if (diff < 2 * 60 * 60 * 1000) {
                mostrarError('val-func-add', 'Debe haber al menos 2 horas entre funciones.');
                return;
            }
        }

        ocultarError('val-func-add');
        funciones.push(fecha);
        funciones.sort((a, b) => a - b);
        
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

        funciones.forEach((fecha, index) => {
            const item = document.createElement('div');
            item.className = 'funcion-item';
            
            const fechaStr = fecha.toLocaleDateString('es-MX', {
                weekday: 'short',
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit'
            });

            item.innerHTML = `
                <i class="bi bi-calendar-event"></i>
                ${fechaStr}
                <button type="button" class="btn-remove" onclick="eliminarFuncion(${index})">
                    <i class="bi bi-x"></i>
                </button>
            `;
            els.listaFunciones.appendChild(item);

            // Hidden input para el form
            const sqlDate = formatoSQL(fecha);
            els.funcionesHidden.innerHTML += `<input type="hidden" name="funciones[]" value="${sqlDate}">`;
        });

        // Limitar inicio de venta a antes de la primera función
        const primeraFuncion = funciones[0];
        fpInicio.set('maxDate', new Date(primeraFuncion.getTime() - 60000));
    }

    window.eliminarFuncion = (index) => {
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
        // Solo actualizar automáticamente si NO está en modo manual
        if (!cierreManual) {
            const ultimaFuncion = funciones[funciones.length - 1];
            const cierre = new Date(ultimaFuncion.getTime() + 2 * 60 * 60 * 1000); // +2 horas
            fpCierre.setDate(cierre, true);
        }
        // Actualizar límites del cierre manual
        actualizarLimitesCierre();
    }

    function actualizarLimitesCierre() {
        if (funciones.length === 0) return;
        const ultimaFuncion = funciones[funciones.length - 1];
        // El cierre debe ser al menos 30 min después de la última función
        const minCierre = new Date(ultimaFuncion.getTime() + 30 * 60 * 1000);
        fpCierre.set('minDate', minCierre);
    }

    // Toggle para modo manual de cierre
    window.toggleCierreManual = function() {
        cierreManual = !cierreManual;
        const btn = document.getElementById('btnToggleCierre');
        const container = document.getElementById('cierreContainer');
        const icon = document.getElementById('cierreLockIcon');
        const info = document.getElementById('cierreInfo');
        
        if (cierreManual) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Manual activo';
            container.classList.add('unlocked');
            icon.className = 'bi bi-unlock-fill lock-icon';
            info.innerHTML = '<i class="bi bi-pencil"></i> Modo manual: selecciona la fecha de cierre';
            info.style.color = 'var(--success)';
            fpCierre.set('clickOpens', true);
            actualizarLimitesCierre();
        } else {
            btn.classList.remove('active');
            btn.innerHTML = '<i class="bi bi-pencil-fill"></i> Personalizar';
            container.classList.remove('unlocked');
            icon.className = 'bi bi-lock-fill lock-icon';
            info.innerHTML = '<i class="bi bi-info-circle"></i> Automático: 2 horas después de la última función';
            info.style.color = 'var(--text-muted)';
            fpCierre.set('clickOpens', false);
            // Recalcular automáticamente
            if (funciones.length > 0) {
                const ultimaFuncion = funciones[funciones.length - 1];
                const cierre = new Date(ultimaFuncion.getTime() + 2 * 60 * 60 * 1000);
                fpCierre.setDate(cierre, true);
            }
        }
        validarFormulario();
    };

    function formatoSQL(fecha) {
        return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')} ${String(fecha.getHours()).padStart(2, '0')}:${String(fecha.getMinutes()).padStart(2, '0')}:00`;
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPLOAD DE IMAGEN
    // ═══════════════════════════════════════════════════════════════════
    els.imagen.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            const file = e.target.files[0];
            els.uploadArea.classList.add('has-file');
            els.fileName.textContent = file.name;
            els.fileName.style.display = 'block';
            els.uploadArea.querySelector('p:first-of-type').textContent = '✓ Imagen seleccionada';
        } else {
            els.uploadArea.classList.remove('has-file');
            els.fileName.style.display = 'none';
            els.uploadArea.querySelector('p:first-of-type').textContent = 'Arrastra o haz clic para subir';
        }
        validarFormulario();
    });

    // ═══════════════════════════════════════════════════════════════════
    // VALIDACIÓN GENERAL
    // ═══════════════════════════════════════════════════════════════════
    function validarFormulario() {
        let valido = true;

        // Reset errores
        document.querySelectorAll('.validation-msg').forEach(el => el.classList.remove('show'));
        document.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-invalid'));

        // Título - validación robusta
        const titulo = els.titulo.value.trim();
        if (!titulo) {
            mostrarError('val-titulo', 'El título es obligatorio.');
            els.titulo.classList.add('is-invalid');
            valido = false;
        } else if (titulo.length < 3) {
            mostrarError('val-titulo', 'Mínimo 3 caracteres.');
            els.titulo.classList.add('is-invalid');
            valido = false;
        } else if (titulo.length > 200) {
            mostrarError('val-titulo', 'Máximo 200 caracteres.');
            els.titulo.classList.add('is-invalid');
            valido = false;
        } else if (/[<>"'\/\\;]/.test(titulo)) {
            mostrarError('val-titulo', 'Caracteres no permitidos: < > " \' / \\ ;');
            els.titulo.classList.add('is-invalid');
            valido = false;
        }

        // Descripción - validación robusta
        const desc = els.desc.value.trim();
        if (!desc) {
            mostrarError('val-desc', 'La descripción es obligatoria.');
            els.desc.classList.add('is-invalid');
            valido = false;
        } else if (desc.length < 10) {
            mostrarError('val-desc', 'Mínimo 10 caracteres.');
            els.desc.classList.add('is-invalid');
            valido = false;
        } else if (desc.length > 2000) {
            mostrarError('val-desc', 'Máximo 2000 caracteres.');
            els.desc.classList.add('is-invalid');
            valido = false;
        }

        // Funciones
        if (funciones.length === 0) {
            mostrarError('val-funciones', 'Agrega al menos una función.');
            valido = false;
        }

        // Inicio de venta
        if (!fpInicio.selectedDates.length) {
            if (funciones.length > 0) {
                mostrarError('val-inicio', 'Define cuándo inicia la venta.');
                valido = false;
            }
        } else if (funciones.length > 0) {
            const primeraFuncion = funciones[0];
            if (fpInicio.selectedDates[0] >= primeraFuncion) {
                mostrarError('val-inicio', 'Debe ser antes de la primera función.');
                valido = false;
            }
            // Validar que no sea en el pasado
            const ahora = new Date();
            if (fpInicio.selectedDates[0] < ahora) {
                mostrarError('val-inicio', 'No puede ser en el pasado.');
                valido = false;
            }
        }

        // Cierre de venta (si está en modo manual)
        if (cierreManual && fpCierre.selectedDates.length) {
            const cierre = fpCierre.selectedDates[0];
            const ultimaFuncion = funciones.length > 0 ? funciones[funciones.length - 1] : null;
            
            if (ultimaFuncion) {
                // Cierre debe ser al menos 30 min después de última función
                const minCierre = new Date(ultimaFuncion.getTime() + 30 * 60 * 1000);
                if (cierre < minCierre) {
                    mostrarError('val-cierre', 'Debe ser al menos 30 min después de la última función.');
                    valido = false;
                }
            }
            
            // Cierre debe ser después del inicio
            if (fpInicio.selectedDates.length && cierre <= fpInicio.selectedDates[0]) {
                mostrarError('val-cierre', 'Debe ser después del inicio de venta.');
                valido = false;
            }
        }

        // Imagen - validación de tipo y tamaño
        if (!els.imagen.files.length) {
            valido = false;
        } else {
            const file = els.imagen.files[0];
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            if (!allowedTypes.includes(file.type)) {
                mostrarError('val-imagen', 'Formato no válido. Usa JPG, PNG, GIF o WEBP.');
                valido = false;
            } else if (file.size > maxSize) {
                mostrarError('val-imagen', 'Imagen muy grande. Máximo 10MB.');
                valido = false;
            }
        }

        // Tipo escenario
        const tipoSeleccionado = document.querySelector('input[name="tipo"]:checked');
        if (!tipoSeleccionado) {
            mostrarError('val-tipo', 'Selecciona un tipo de escenario.');
            valido = false;
        }

        els.btnSubmit.disabled = !valido;
        return valido;
    }

    function mostrarError(id, msg) {
        const el = document.getElementById(id);
        if (el) {
            el.querySelector('span').textContent = msg;
            el.classList.add('show');
        }
    }

    function ocultarError(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('show');
    }

    // Event listeners para validación en tiempo real
    els.titulo.addEventListener('input', function() {
        // Sanitizar en tiempo real
        this.value = this.value.replace(/[<>]/g, '');
        validarFormulario();
    });
    els.titulo.addEventListener('paste', function(e) {
        setTimeout(() => {
            this.value = this.value.replace(/[<>"'\/\\;]/g, '').substring(0, 200);
            validarFormulario();
        }, 0);
    });
    
    els.desc.addEventListener('input', function() {
        this.value = this.value.replace(/[<>]/g, '');
        validarFormulario();
    });
    
    document.querySelectorAll('input[name="tipo"]').forEach(r => r.addEventListener('change', validarFormulario));

    // Submit con validación final
    document.getElementById('formEvento').addEventListener('submit', (e) => {
        // Sanitización final antes de enviar
        els.titulo.value = els.titulo.value.trim().replace(/[<>"'\/\\;]/g, '').substring(0, 200);
        els.desc.value = els.desc.value.trim().replace(/[<>]/g, '').substring(0, 2000);
        
        if (!validarFormulario()) {
            e.preventDefault();
            // Scroll al primer error
            const primerError = document.querySelector('.validation-msg.show');
            if (primerError) {
                primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODAL DE SALIR
// ═══════════════════════════════════════════════════════════════════
function confirmarSalida() {
    document.getElementById('modalSalir').classList.add('show');
}

function cerrarModal() {
    document.getElementById('modalSalir').classList.remove('show');
}

function salir() {
    document.body.classList.add('exiting');
    setTimeout(() => window.location.href = 'act_evento.php', 350);
}
</script>
</body>
</html>