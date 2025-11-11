<?php
include "conexion.php";

$errores = [];

// ... (Bloques 1, 2 y 3 de recepción de datos y validación permanecen igual) ...
// 1. Recibir datos principales
$titulo = trim($_POST['titulo']);
$inicio_venta = $_POST['inicio_venta'];
$cierre_venta = $_POST['cierre_venta'];
$descripcion = trim($_POST['descripcion']);
$tipo = $_POST['tipo'];

// 2. Lógica de imagen
$imagen_ruta = "";
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
    $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    if (!is_dir("imagenes")) { mkdir("imagenes", 0755, true); }
    $nombreArchivo = "evento_" . time() . "." . $ext;
    if (move_uploaded_file($_FILES['imagen']['tmp_name'], "imagenes/" . $nombreArchivo)) {
        $imagen_ruta = "imagenes/" . $nombreArchivo;
    } else {
        $errores[] = "Error al mover la imagen.";
    }
} else {
    $errores[] = "Debe subir una imagen.";
}

// 3. Validaciones
if (!isset($_POST['funciones']) || !is_array($_POST['funciones']) || empty($_POST['funciones'])) {
    $errores[] = "Debe añadir al menos una función.";
}
if (!isset($_POST['precios']) || !is_array($_POST['precios']) || empty($_POST['precios'])) {
    $errores[] = "No se recibieron los precios por defecto.";
}

if (!empty($errores)) {
    foreach ($errores as $e) echo "<p style='color:red;'>❌ $e</p>";
    echo "<a href='crear_evento.php'>Volver a intentar</a>";
    exit;
}

// 4. Insertar EVENTO
$stmt_evento = $conn->prepare("INSERT INTO evento (titulo, inicio_venta, cierre_venta, descripcion, imagen, tipo, finalizado) VALUES (?, ?, ?, ?, ?, ?, 0)");
$stmt_evento->bind_param("sssssi", $titulo, $inicio_venta, $cierre_venta, $descripcion, $imagen_ruta, $tipo);

if ($stmt_evento->execute()) {
    $id_evento_nuevo = $conn->insert_id;

    // 5. Insertar FUNCIONES
    $stmt_funcion = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora) VALUES (?, ?)");
    foreach ($_POST['funciones'] as $fecha_hora) {
        $stmt_funcion->bind_param("is", $id_evento_nuevo, $fecha_hora);
        $stmt_funcion->execute();
    }
    $stmt_funcion->close();

    // 6. Insertar CATEGORÍAS
    $precios_base = $_POST['precios'];
    $colores_default = ["General" => "#808080", "Discapacitado" => "#007BFF"];
    $stmt_cat = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
    
    $cat_error = '';
    if ($stmt_cat) {
        foreach ($precios_base as $nombre => $precio) {
            $color = $colores_default[$nombre] ?? '#000000';
            $stmt_cat->bind_param("isds", $id_evento_nuevo, $nombre, $precio, $color);
            if (!$stmt_cat->execute()) {
                $cat_error = $stmt_cat->error;
                break; 
            }
        }
        $stmt_cat->close();
    } else {
        $cat_error = $conn->error;
    }

    // 7. Pantalla de confirmación y redirección OFFLINE
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Procesando...</title>
        <style>
            body {
                font-family: sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background-color: #f4f4f4;
            }
            .confirm-box {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 400px;
                width: 90%;
            }
            .icon {
                font-size: 60px;
                margin-bottom: 20px;
            }
            .success { color: #28a745; }
            .warning { color: #ffc107; }
            h2 { margin: 0 0 15px; color: #333; }
            p { color: #666; margin-bottom: 25px; }
            .loader {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 30px;
                height: 30px;
                animation: spin 1s linear infinite;
                margin: 0 auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <div class="confirm-box">
            <?php if (empty($cat_error)): ?>
                <div class="icon success">✅</div>
                <h2>¡Evento Creado!</h2>
                <p>Todo se ha guardado correctamente.</p>
            <?php else: ?>
                <div class="icon warning">⚠️</div>
                <h2>Creado con Advertencias</h2>
                <p>Error en categorías: <?= htmlspecialchars($cat_error) ?></p>
            <?php endif; ?>
            
            <div class="loader"></div>
            <p style="margin-top: 15px; font-size: 0.9em;">Redirigiendo...</p>
        </div>

        <script>
            // Redirigir después de 2.5 segundos
            setTimeout(function() {
                window.location.href = 'index.php';
            }, 2500);
        </script>
    </body>
    </html>
    <?php

} else {
    echo "<h3>Error Fatal</h3>";
    echo "<p>No se pudo crear el evento: " . $stmt_evento->error . "</p>";
    echo "<a href='crear_evento.php'>Volver</a>";
}

$stmt_evento->close();
$conn->close();
?>