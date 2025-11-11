<?php
// --- ACTIVAR DEPURACIÓN (Eliminar en producción) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "../conexion.php"; 

// Verificar conexión
if ($conn->connect_error) { die("Error de conexión: " . $conn->connect_error); }

$errores = [];

// ==================================================================
// 1. RECIBIR Y VALIDAR DATOS BÁSICOS
// ==================================================================
$titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$inicio_venta_str = isset($_POST['inicio_venta']) ? $_POST['inicio_venta'] : '';
$cierre_venta_str = isset($_POST['cierre_venta']) ? $_POST['cierre_venta'] : '';

if (empty($titulo)) $errores[] = "El título es obligatorio.";
if (empty($tipo)) $errores[] = "Debe seleccionar un tipo de escenario.";

// Validar Funciones
if (!isset($_POST['funciones']) || !is_array($_POST['funciones']) || empty($_POST['funciones'])) {
    $errores[] = "Debe añadir al menos una función.";
} else {
    $funciones = $_POST['funciones'];
    sort($funciones); // Asegurar orden cronológico para validaciones
}

// Validar Precios
if (!isset($_POST['precios']) || !is_array($_POST['precios'])) {
    $errores[] = "Faltan los precios base.";
}

// ==================================================================
// 2. VALIDACIÓN RIGUROSA DE FECHAS Y HORAS
// ==================================================================
if (empty($errores)) {
    try {
        $inicio_venta = new DateTime($inicio_venta_str);
        $cierre_venta = new DateTime($cierre_venta_str);
        $primera_funcion = new DateTime($funciones[0]);
        $ultima_funcion = new DateTime($funciones[count($funciones) - 1]);

        if ($inicio_venta >= $primera_funcion) {
            $errores[] = "El inicio de venta debe ser ANTES de la primera función (" . $primera_funcion->format('d/m/Y H:i') . ").";
        }
        if ($cierre_venta <= $inicio_venta) {
            $errores[] = "El cierre de venta debe ser posterior al inicio de venta.";
        }
    } catch (Exception $e) {
        $errores[] = "Formato de fecha inválido.";
    }
}

// ==================================================================
// 3. PROCESAR IMAGEN
// ==================================================================
$imagen_ruta = "";
if (empty($errores)) {
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            if (!is_dir("../evt_interfaz/imagenes")) mkdir("../evt_interfaz/imagenes", 0755, true);
            $nombreArchivo = "evt_" . time() . "." . $ext;
            // Guardar en la carpeta de interfaz para que sea accesible desde el front
            $ruta_fisica = "../evt_interfaz/imagenes/" . $nombreArchivo;
            $ruta_bd = "imagenes/" . $nombreArchivo; // Ruta relativa para la BD
            
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_fisica)) {
                $imagen_ruta = $ruta_bd;
            } else {
                $errores[] = "Error al mover la imagen al servidor.";
            }
        } else {
            $errores[] = "Formato de imagen no válido (solo JPG, PNG, GIF).";
        }
    } else {
        $errores[] = "Debe seleccionar una imagen.";
    }
}

// ==================================================================
// 4. SI HAY ERRORES, MOSTRAR Y DETENER
// ==================================================================
if (!empty($errores)) {
    if (!empty($imagen_ruta) && file_exists("../evt_interfaz/" . $imagen_ruta)) { unlink("../evt_interfaz/" . $imagen_ruta); }
    
    echo "<div style='font-family:sans-serif; padding:20px; background:#fff0f0; border-left:5px solid red; max-width:600px; margin:20px auto; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1);'>";
    echo "<h3 style='color:#dc3545; margin-top:0;'>Error al crear evento</h3><ul style='color:#b02a37; padding-left:20px;'>";
    foreach ($errores as $e) { echo "<li style='margin-bottom:5px;'>" . htmlspecialchars($e) . "</li>"; }
    echo "</ul><button onclick='history.back()' style='padding:10px 20px; background:#6c757d; color:white; border:none; border-radius:5px; cursor:pointer;'>Volver</button></div>";
    exit;
}

// ==================================================================
// 5. INSERTAR EN BASE DE DATOS (TRANSACCIÓN)
// ==================================================================
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $ini_mysql = $inicio_venta->format('Y-m-d H:i:s');
    $fin_mysql = $cierre_venta->format('Y-m-d H:i:s');
    $stmt->bind_param("sssiss", $titulo, $descripcion, $imagen_ruta, $tipo, $ini_mysql, $fin_mysql);
    if (!$stmt->execute()) { throw new Exception("Error al guardar evento: " . $stmt->error); }
    $id_nuevo = $conn->insert_id;
    $stmt->close();

    $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora) VALUES (?, ?)");
    foreach ($funciones as $fh) {
        $stmt_f->bind_param("is", $id_nuevo, $fh);
        if (!$stmt_f->execute()) { throw new Exception("Error al guardar función."); }
    }
    $stmt_f->close();

    $colores = ['General' => '#808080', 'Discapacitado' => '#2563eb'];
    $stmt_c = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
    foreach ($_POST['precios'] as $nombre => $precio) {
        $color = $colores[$nombre] ?? '#000000'; $precio_float = floatval($precio);
        $stmt_c->bind_param("isds", $id_nuevo, $nombre, $precio_float, $color);
        if (!$stmt_c->execute()) { throw new Exception("Error al guardar categoría."); }
    }
    $stmt_c->close();

    $conn->commit();

    // ==================================================================
    // 6. REDIRECCIÓN CON ÉXITO (ESTILO NUEVO)
    // ==================================================================
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesando...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center vh-100">
        <div class="card shadow-lg p-5 text-center border-0" style="max-width:450px; border-radius:16px;">
            <div class="mb-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
            </div>
            <h2 class="mb-3 fw-bold text-success">¡Evento Creado!</h2>
            <p class="text-muted mb-4">El evento ha sido guardado correctamente.</p>
            <div class="d-flex justify-content-center align-items-center gap-2 text-primary">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span>Redirigiendo al dashboard...</span>
            </div>
        </div>
        <script>
            localStorage.setItem("evt_upd", Date.now()); // Sincronizar pestañas
            setTimeout(() => { window.location.href = "index.php"; }, 2000);
        </script>
    </body>
    </html>';
    exit;

} catch (Exception $e) {
    $conn->rollback();
    if (!empty($imagen_ruta) && file_exists("../evt_interfaz/" . $imagen_ruta)) { unlink("../evt_interfaz/" . $imagen_ruta); }
    die("<div style='color:red; padding:20px; text-align:center;'><h1>Error Fatal</h1><p>" . $e->getMessage() . "</p><a href='javascript:history.back()'>Volver</a></div>");
}
?>