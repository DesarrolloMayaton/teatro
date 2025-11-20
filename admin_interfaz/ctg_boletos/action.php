<?php
session_start();
// 1. CONEXIÓN
// (Ajusta la ruta si es necesario, p.ej., ../../evt_interfaz/conexion.php)
include "../../evt_interfaz/conexion.php"; 
require_once '../../transacciones_helper.php';

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$id_evento_redirect = $_POST['id_evento'] ?? $_GET['id_evento'] ?? null;

// Construir la URL base para redireccionar
// (Asumiendo que tu archivo de gestión se llama index.php en esta carpeta)
$redirect_url = "index.php"; 
if ($id_evento_redirect) {
    $redirect_url .= "?id_evento=" . $id_evento_redirect;
}

// ==================================================================
// --- FUNCIÓN DE AYUDA PARA REDIRIGIR CON MENSAJES ---
// ==================================================================
function redirigir($base_url, $status, $mensaje, $id_evento = null) {
    $conector = strpos($base_url, '?') === false ? '?' : '&';
    
    // Notificar cambio en categorías para actualización en tiempo real
    if ($status === 'success' && $id_evento) {
        echo "<!DOCTYPE html><html><head><title>Procesando...</title></head><body>";
        echo "<script>
            // Notificar cambio en categorías
            localStorage.setItem('categorias_actualizadas', JSON.stringify({
                id_evento: " . intval($id_evento) . ",
                timestamp: Date.now()
            }));
            
            // Redirigir
            setTimeout(function() {
                window.location.href = '" . $base_url . $conector . "status=$status&msg=" . urlencode($mensaje) . "';
            }, 100);
        </script>";
        echo "<p>Procesando cambios...</p></body></html>";
        exit;
    }
    
    header("Location: " . $base_url . $conector . "status=$status&msg=" . urlencode($mensaje));
    exit;
}

// 3. VALIDACIÓN BÁSICA
if (empty($accion)) {
    redirigir($redirect_url, 'error', 'Acción no válida.');
}

// ==================================================================
// --- CASO 1: LÓGICA DE ACTUALIZACIÓN RÁPIDA DE PRECIOS ---
// ==================================================================
if ($accion === 'actualizar_todos' || $accion === 'actualizar_seleccionado') {
    
    $precio_general = (!empty($_POST['precio_general'])) ? (float)$_POST['precio_general'] : null;
    $precio_discapacitado = (!empty($_POST['precio_discapacitado'])) ? (float)$_POST['precio_discapacitado'] : null;
    $id_evento_especifico = ($accion === 'actualizar_seleccionado' && !empty($_POST['id_evento'])) ? (int)$_POST['id_evento'] : null;
    
    // (Validación de precios vacíos eliminada según tu solicitud)

    try {
        if ($precio_general !== null) {
            $sql_gen = "UPDATE categorias SET precio = ? WHERE nombre_categoria = 'General'";
            if ($id_evento_especifico) {
                $sql_gen .= " AND id_evento = ?"; $stmt_gen = $conn->prepare($sql_gen); $stmt_gen->bind_param("di", $precio_general, $id_evento_especifico);
            } else { $stmt_gen = $conn->prepare($sql_gen); $stmt_gen->bind_param("d", $precio_general); }
            $stmt_gen->execute(); $stmt_gen->close();
        }
        if ($precio_discapacitado !== null) {
            $sql_dis = "UPDATE categorias SET precio = ? WHERE nombre_categoria = 'Discapacitado'";
            if ($id_evento_especifico) {
                $sql_dis .= " AND id_evento = ?"; $stmt_dis = $conn->prepare($sql_dis); $stmt_dis->bind_param("di", $precio_discapacitado, $id_evento_especifico);
            } else { $stmt_dis = $conn->prepare($sql_dis); $stmt_dis->bind_param("d", $precio_discapacitado); }
            $stmt_dis->execute(); $stmt_dis->close();
        }
    } catch (Exception $e) {
        redirigir($redirect_url, 'error', $e->getMessage());
    }
    
    registrar_transaccion('categorias_actualizacion_masiva', 'Actualización rápida de precios en categorías');
    redirigir($redirect_url, 'success', 'Precios actualizados masivamente.');
} 

// ==================================================================
// --- CASO 2: LÓGICA CRUD (CREAR, ACTUALIZAR, BORRAR) ---
// ==================================================================
else {
    
    if ($id_evento_redirect === null) {
        redirigir('index.php', 'error', 'ID de evento faltante.');
    }
    
    try {
        switch ($accion) {
            
            case 'crear':
                $nombre_categoria = $_POST['nombre_categoria'] ?? '';
                if (empty($nombre_categoria)) throw new Exception("El nombre es obligatorio.");
                $precio = $_POST['precio'] ?? 0.00; $color = $_POST['color'] ?? '#E0E0E0';
                
                $check = $conn->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? AND nombre_categoria = ?");
                $check->bind_param("is", $id_evento_redirect, $nombre_categoria);
                $check->execute(); $check->store_result();
                if ($check->num_rows > 0) throw new Exception("El nombre '$nombre_categoria' ya existe.");
                $check->close();
                
                $stmt = $conn->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isds", $id_evento_redirect, $nombre_categoria, $precio, $color);
                $stmt->execute(); $stmt->close();
                registrar_transaccion('categoria_crear', 'Creó categoría: ' . $nombre_categoria);
                redirigir($redirect_url, 'success', 'Categoría creada con éxito.', $id_evento_redirect);
                break;

            case 'actualizar':
                $id_categoria = $_POST['id_categoria'] ?? null;
                $nombre_categoria = $_POST['nombre_categoria'] ?? '';
                if (empty($id_categoria)) throw new Exception("Datos incompletos.");
                $precio = $_POST['precio'] ?? 0.00; $color = $_POST['color'] ?? '#E0E0E0';
                
                $check = $conn->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? AND nombre_categoria = ? AND id_categoria != ?");
                $check->bind_param("isi", $id_evento_redirect, $nombre_categoria, $id_categoria);
                $check->execute(); $check->store_result();
                if ($check->num_rows > 0) throw new Exception("El nombre '$nombre_categoria' ya existe.");
                $check->close();
                
                $stmt = $conn->prepare("UPDATE categorias SET nombre_categoria = ?, precio = ?, color = ? WHERE id_categoria = ? AND id_evento = ?");
                $stmt->bind_param("sdsii", $nombre_categoria, $precio, $color, $id_categoria, $id_evento_redirect);
                $stmt->execute(); $stmt->close();
                registrar_transaccion('categoria_actualizar', 'Actualizó categoría: ' . $nombre_categoria);
                redirigir($redirect_url, 'success', 'Categoría actualizada.', $id_evento_redirect);
                break;
                
            case 'borrar':
                $id_categoria = $_GET['id_categoria'] ?? null;
                if (empty($id_categoria)) throw new Exception("ID faltante.");
                
                $stmt = $conn->prepare("DELETE FROM categorias WHERE id_categoria = ? AND id_evento = ?");
                $stmt->bind_param("ii", $id_categoria, $id_evento_redirect);
                $stmt->execute(); $stmt->close();
                registrar_transaccion('categoria_borrar', 'Eliminó categoría ID ' . $id_categoria);
                redirigir($redirect_url, 'success', 'Categoría eliminada.', $id_evento_redirect);
                break;

            default:
                throw new Exception("Acción desconocida.");
        }
    } catch (Exception $e) {
        redirigir($redirect_url, 'error', $e->getMessage());
    }
}

$conn->close();
?>