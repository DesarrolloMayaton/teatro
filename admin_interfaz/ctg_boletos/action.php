<?php
// 1. CONEXIÓN
// Subimos dos niveles (action.php -> ctg_boletos -> admin_menu -> teatro/conexion.php)
include "../../evt_interfaz/conexion.php"; 

// 2. DETERMINAR LA ACCIÓN Y EL MÉTODO
$accion = null;
$id_evento_redirect = null; // Para saber a dónde volver

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ===================================
    // === VIENE DEL FORMULARIO (CREAR O ACTUALIZAR)
    // ===================================
    $accion = $_POST['accion'] ?? null;
    $id_evento_redirect = $_POST['id_evento'] ?? null;
    
    // Recoger todos los datos del formulario
    $id_categoria = $_POST['id_categoria'] ?? null;
    $nombre_categoria = $_POST['nombre_categoria'] ?? '';
    $precio = $_POST['precio'] ?? 0.00;
    $color = $_POST['color'] ?? '#E0E0E0';
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ===================================
    // === VIENE DE UN ENLACE (BORRAR)
    // ===================================
    $accion = $_GET['accion'] ?? null;
    $id_evento_redirect = $_GET['id_evento'] ?? null;
    
    // Recoger el ID para borrar
    $id_categoria = $_GET['id_categoria'] ?? null;
}

// 3. VALIDACIÓN BÁSICA
// Si no hay acción o no sabemos a qué evento volver, detenemos.
if ($accion === null || $id_evento_redirect === null) {
    die("Error: Acción no válida o ID de evento faltante.");
}


// 4. EJECUTAR ACCIÓN CON PREPARED STATEMENTS (Para seguridad)
try {
    
    switch ($accion) {
        
        // --- CREAR ---
        case 'crear':
            // Validar datos (simple)
            if (empty($nombre_categoria) || $precio < 0) {
                throw new Exception("El nombre y el precio son obligatorios.");
            }
            
            $stmt = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
            // 'isds' significa: Integer, String, Double (decimal), String
            $stmt->bind_param("isds", $id_evento_redirect, $nombre_categoria, $precio, $color);
            $stmt->execute();
            $stmt->close();
            break;

        // --- ACTUALIZAR ---
        case 'actualizar':
            // Validar datos
            if (empty($id_categoria) || empty($nombre_categoria) || $precio < 0) {
                throw new Exception("Datos incompletos para actualizar.");
            }
            
            $stmt = $conn->prepare("UPDATE categorias SET nombre_categoria = ?, precio = ?, color = ? WHERE id_categoria = ? AND id_evento = ?");
            // 'sdsii' -> String, Double, String, Integer, Integer
            $stmt->bind_param("sdsii", $nombre_categoria, $precio, $color, $id_categoria, $id_evento_redirect);
            $stmt->execute();
            $stmt->close();
            break;
            
        // --- BORRAR ---
        case 'borrar':
            // Validar datos
            if (empty($id_categoria)) {
                throw new Exception("ID de categoría faltante para borrar.");
            }
            
            $stmt = $conn->prepare("DELETE FROM categorias WHERE id_categoria = ? AND id_evento = ?");
            // 'ii' -> Integer, Integer
            $stmt->bind_param("ii", $id_categoria, $id_evento_redirect);
            $stmt->execute();
            $stmt->close();
            break;

        default:
            throw new Exception("Acción desconocida.");
    }

    // 5. REDIRECCIÓN DE ÉXITO
    // Si todo salió bien, regresamos al usuario a la página de categorías,
    // cargando el evento que estaba editando.
    header("Location: index.php?id_evento=" . $id_evento_redirect);
    exit;

} catch (Exception $e) {
    // 6. MANEJO DE ERRORES
    // (En un sistema real, esto sería una página de error más amigable)
    echo "<h1>Error en la operación</h1>";
    echo "<p>No se pudo completar la acción en la base de datos.</p>";
    echo "<p><strong>Mensaje del error:</strong> " . $e->getMessage() . "</p>";
    echo "<a href='index.php?id_evento=" . $id_evento_redirect . "'>Volver a la página anterior</a>";
}

$conn->close();
?>