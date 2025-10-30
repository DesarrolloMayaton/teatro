<?php
// 1. CONEXIÓN
// Subimos dos niveles (action.php -> ctg_boletos -> admin_menu -> teatro/conexion.php)
include "../../evt_interfaz/conexion.php"; 

// 2. DETERMINAR LA ACCIÓN Y EL MÉTODO
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$id_evento_redirect = $_POST['id_evento'] ?? $_GET['id_evento'] ?? null;


// 3. VALIDACIÓN BÁSICA
if (empty($accion)) {
    die("Error: Acción no válida.");
}


// ==================================================================
// --- CASO 1: LÓGICA DE ACTUALIZACIÓN RÁPIDA DE PRECIOS ---
// ==================================================================
if ($accion === 'actualizar_todos' || $accion === 'actualizar_seleccionado') {
    
    // 1. Obtener los precios (si se escribieron)
    $precio_general = (!empty($_POST['precio_general']) && is_numeric($_POST['precio_general'])) ? (float)$_POST['precio_general'] : null;
    $precio_discapacitado = (!empty($_POST['precio_discapacitado']) && is_numeric($_POST['precio_discapacitado'])) ? (float)$_POST['precio_discapacitado'] : null;
    
    // 2. Determinar si es para un solo evento
    $id_evento_especifico = ($accion === 'actualizar_seleccionado' && !empty($_POST['id_evento'])) ? (int)$_POST['id_evento'] : null;
    
    try {
        // --- Actualizar "General" ---
        if ($precio_general !== null) {
            $sql_gen = "UPDATE categorias SET precio = ? WHERE nombre_categoria = 'General'";
            
            if ($id_evento_especifico) {
                // Filtro para UN solo evento
                $sql_gen .= " AND id_evento = ?"; 
                $stmt_gen = $conn->prepare($sql_gen);
                $stmt_gen->bind_param("di", $precio_general, $id_evento_especifico);
            } else {
                // Actualiza TODOS los eventos
                $stmt_gen = $conn->prepare($sql_gen);
                $stmt_gen->bind_param("d", $precio_general);
            }
            $stmt_gen->execute();
            $stmt_gen->close();
        }
        
        // --- Actualizar "Discapacitado" ---
        if ($precio_discapacitado !== null) {
            $sql_dis = "UPDATE categorias SET precio = ? WHERE nombre_categoria = 'Discapacitado'";
            
            if ($id_evento_especifico) {
                // Filtro para UN solo evento
                $sql_dis .= " AND id_evento = ?";
                $stmt_dis = $conn->prepare($sql_dis);
                $stmt_dis->bind_param("di", $precio_discapacitado, $id_evento_especifico);
            } else {
                // Actualiza TODOS los eventos
                $stmt_dis = $conn->prepare($sql_dis);
                $stmt_dis->bind_param("d", $precio_discapacitado);
            }
            $stmt_dis->execute();
            $stmt_dis->close();
        }

    } catch (Exception $e) {
        die("Error al actualizar precios: " . $e->getMessage());
    }

    // Redirección de éxito
    $redirect_url = "index.php";
    if ($id_evento_redirect) {
        $redirect_url .= "?id_evento=" . $id_evento_redirect;
    }
    header("Location: " . $redirect_url);
    exit;

} 

// ==================================================================
// --- CASO 2: LÓGICA CRUD (CREAR, ACTUALIZAR, BORRAR) ---
// ==================================================================
else {
    
    // Validación para CRUD: necesita un id_evento
    if ($id_evento_redirect === null) {
        die("Error: ID de evento faltante para la acción CRUD.");
    }
    
    try {
        switch ($accion) {
            
            // --- CREAR ---
            case 'crear':
                $nombre_categoria = $_POST['nombre_categoria'] ?? '';
                $precio = $_POST['precio'] ?? 0.00;
                $color = $_POST['color'] ?? '#E0E0E0';
            
                if (empty($nombre_categoria) || $precio < 0) {
                    throw new Exception("El nombre y el precio son obligatorios.");
                }
                
                $stmt = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isds", $id_evento_redirect, $nombre_categoria, $precio, $color);
                $stmt->execute();
                $stmt->close();
                break;

            // --- ACTUALIZAR ---
            case 'actualizar':
                $id_categoria = $_POST['id_categoria'] ?? null;
                $nombre_categoria = $_POST['nombre_categoria'] ?? '';
                $precio = $_POST['precio'] ?? 0.00;
                $color = $_POST['color'] ?? '#E0E0E0';
            
                if (empty($id_categoria) || empty($nombre_categoria) || $precio < 0) {
                    throw new Exception("Datos incompletos para actualizar.");
                }
                
                $stmt = $conn->prepare("UPDATE categorias SET nombre_categoria = ?, precio = ?, color = ? WHERE id_categoria = ? AND id_evento = ?");
                $stmt->bind_param("sdsii", $nombre_categoria, $precio, $color, $id_categoria, $id_evento_redirect);
                $stmt->execute();
                $stmt->close();
                break;
                
            // --- BORRAR ---
            case 'borrar':
                $id_categoria = $_GET['id_categoria'] ?? null;
            
                if (empty($id_categoria)) {
                    throw new Exception("ID de categoría faltante para borrar.");
                }
                
                $stmt = $conn->prepare("DELETE FROM categorias WHERE id_categoria = ? AND id_evento = ?");
                $stmt->bind_param("ii", $id_categoria, $id_evento_redirect);
                $stmt->execute();
                $stmt->close();
                break;

            default:
                throw new Exception("Acción desconocida: " . htmlspecialchars($accion));
        }

        // 5. REDIRECCIÓN DE ÉXITO (para CRUD)
        header("Location: index.php?id_evento=" . $id_evento_redirect);
        exit;

    } catch (Exception $e) {
        // 6. MANEJO DE ERRORES (para CRUD)
        echo "<h1>Error en la operación</h1>";
        echo "<p>No se pudo completar la acción en la base de datos.</p>";
        echo "<p><strong>Mensaje del error:</strong> " . $e->getMessage() . "</p>";
        echo "<a href='index.php?id_evento=" . $id_evento_redirect . "'>Volver a la página anterior</a>";
    }
}

$conn->close();
?>