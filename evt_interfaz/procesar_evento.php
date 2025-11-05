<?php
include "conexion.php";

$errores = [];

// 1. Recibir los datos principales del evento
$titulo = trim($_POST['titulo']);
$inicio_venta = $_POST['inicio_venta']; // Ej: "2025-10-20 10:00"
$cierre_venta = $_POST['cierre_venta']; // Ej: "2025-10-22 22:00"
$descripcion = trim($_POST['descripcion']);
$tipo = $_POST['tipo'];

// 2. Lógica para subir la imagen (Usando tu lógica de validación)
$imagen_ruta = ""; // Placeholder
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
    $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    if (!is_dir("imagenes")) {
        mkdir("imagenes", 0755, true);
    }
    $nombreArchivo = "evento_" . time() . "." . $ext;
    if (move_uploaded_file($_FILES['imagen']['tmp_name'], "imagenes/" . $nombreArchivo)) {
        $imagen_ruta = "imagenes/" . $nombreArchivo;
    } else {
        $errores[] = "Error al mover el archivo de imagen.";
    }
} else {
    // Si la imagen es obligatoria
    $errores[] = "Debe subir una imagen para el evento.";
}

// 3. Validar que se recibieron funciones
if (!isset($_POST['funciones']) || !is_array($_POST['funciones']) || empty($_POST['funciones'])) {
    $errores[] = "Debe añadir al menos una función para el evento.";
}

// Si hay errores, detenerse aquí
if (!empty($errores)) {
    foreach ($errores as $e) echo "<p style='color:red;'>❌ $e</p>";
    echo "<a href='crear_evento.php'>Volver</a>";
    exit;
}


// 4. Insertar el EVENTO principal (en la tabla 'evento')
$stmt_evento = $conn->prepare(
    "INSERT INTO evento (titulo, inicio_venta, cierre_venta, descripcion, imagen, tipo, finalizado) 
     VALUES (?, ?, ?, ?, ?, ?, 0)"
);
$stmt_evento->bind_param("sssssi", $titulo, $inicio_venta, $cierre_venta, $descripcion, $imagen_ruta, $tipo);

if ($stmt_evento->execute()) {
    // 5. Obtener el ID del evento que acabamos de crear
    $id_evento_nuevo = $conn->insert_id;

    // 6. Procesar y guardar las FUNCIONES (en la tabla 'funciones')
    
    // Preparamos la consulta para insertar en la NUEVA tabla 'funciones'
    $stmt_funcion = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora) VALUES (?, ?)");
    
    // Recorremos el array de funciones que envió el formulario
    foreach ($_POST['funciones'] as $fecha_hora) {
        // $fecha_hora ya viene en formato "YYYY-MM-DD HH:MM:SS"
        $stmt_funcion->bind_param("is", $id_evento_nuevo, $fecha_hora);
        $stmt_funcion->execute();
    }
    
    $stmt_funcion->close();

    // Mensaje de éxito y redirección a Cartelera en el marco principal
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Evento creado</title>
    </head>
    <body>
    <script>
    (function(){
        try {
            if (window.top && window.top !== window) {
                const d = window.top.document;
                // Guardar la pestaña activa como Cartelera
                window.top.localStorage.setItem('ultimaPestanaActiva', 'frame-cartelera');
                // Activar iframe de Cartelera
                d.querySelectorAll('.content-frame').forEach(f => f.classList.remove('active'));
                const target = d.getElementById('frame-cartelera');
                if (target) {
                    target.classList.add('active');
                    // Forzar recarga para reflejar el nuevo evento
                    const src = target.getAttribute('src');
                    target.setAttribute('src', src);
                }
                // Marcar elemento del menú como activo
                d.querySelectorAll('nav.menu-lateral a.menu-item').forEach(a => {
                    a.classList.toggle('active', a.dataset.target === 'frame-cartelera');
                });
            }
        } catch(e) {}
        // Volver al listado de eventos en este iframe
        window.location.href = 'index.php';
    })();
    </script>
    </body>
    </html>
    <?php

} else {
    echo "<p style='color:red;'>❌ Error al guardar el evento principal: " . $stmt_evento->error . "</p>";
    echo "<a href='crear_evento.php'>Volver</a>";
}

$stmt_evento->close();
$conn->close();

// 7. Redirigir al listado (Opcional, puedes quitar esto si quieres ver el mensaje de éxito)
// header("Location: index.php");
// exit;

?>