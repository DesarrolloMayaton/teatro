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

// *** NUEVA VALIDACIÓN: Checar si los precios por defecto llegaron ***
if (!isset($_POST['precios']) || !is_array($_POST['precios']) || empty($_POST['precios'])) {
    // Esto no debería pasar si el HTML es correcto, pero es buena idea validarlo.
    $errores[] = "Error: No se recibieron los precios por defecto (General, Discapacitado).";
}
// ******************************************************************


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

<<<<<<< HEAD
    // --- 7. (NUEVO) Procesar y guardar las CATEGORÍAS POR DEFECTO ---
    // -----------------------------------------------------------------
    
    // Recuperar el array de precios que enviamos desde el HTML
    $precios_base = $_POST['precios']; // Esto es ['General' => '80', 'Discapacitado' => '80']

    // Definir los colores para tus categorías (Gris y Azul)
    $colores_default = [
        "General"       => "#808080", 
        "Discapacitado" => "#007BFF"
    ];

    // Preparar la consulta para insertar en 'categorias'
    // (id_evento, nombre_categoria, precio, color)
    $sql_categoria = "INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)";
    $stmt_categoria = $conn->prepare($sql_categoria);

    $categorias_exito = true; // Bandera para saber si todo salió bien

    if (!$stmt_categoria) {
        echo "<p style='color:red;'>❌ Error al preparar la consulta de categorías: " . $conn->error . "</p>";
        $categorias_exito = false;
    } else {
        // Recorremos el array de precios e insertamos cada categoría
        foreach ($precios_base as $nombre => $precio) {
            
            // Obtenemos el color correspondiente
            $color = isset($colores_default[$nombre]) ? $colores_default[$nombre] : '#000000';
            
            // Asignamos las variables a la consulta preparada (i: int, s: string, d: double/decimal, s: string)
            $stmt_categoria->bind_param("isds", $id_evento_nuevo, $nombre, $precio, $color);
            
            // Ejecutar la inserción
            if (!$stmt_categoria->execute()) {
                echo "<p style='color:red;'>❌ Error al insertar categoría '" . htmlspecialchars($nombre) . "': " . $stmt_categoria->error . "</p>";
                $categorias_exito = false;
            }
        }
        // Cerrar la sentencia preparada
        $stmt_categoria->close();
    }
    // --- FIN DE LA SECCIÓN DE CATEGORÍAS ---


    // 8. Mensaje de éxito (modificado para incluir categorías)
    if ($categorias_exito) {
        echo "<div style='padding:20px; background:#d4edda; color:#155724; border-radius:8px; margin:20px;'>✅ Evento, funciones y categorías (General, Discapacitado) creados con éxito. ID: " . $id_evento_nuevo . "</div>";
    } else {
         echo "<div style='padding:20px; background:#f8d7da; color:#721c24; border-radius:8px; margin:20px;'>⚠️ Evento y funciones creados (ID: $id_evento_nuevo), PERO hubo un error al guardar las categorías por defecto. Revise la base de datos.</div>";
    }
    
    echo "<a href='index.php'>Volver al listado de eventos</a>";
=======
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
>>>>>>> moises-avila

} else {
    echo "<p style='color:red;'>❌ Error al guardar el evento principal: " . $stmt_evento->error . "</p>";
    echo "<a href='crear_evento.php'>Volver</a>";
}

$stmt_evento->close();
$conn->close();

// 9. Redirigir al listado (Opcional, puedes quitar esto si quieres ver el mensaje de éxito)
// header("Location: index.php");
// exit;

?>