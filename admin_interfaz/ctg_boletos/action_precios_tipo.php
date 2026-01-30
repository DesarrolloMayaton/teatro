<?php
// Acción para manejar precios por tipo de boleto
include "../../evt_interfaz/conexion.php";

// Obtener datos del formulario
$id_evento = isset($_POST['id_evento']) && $_POST['id_evento'] !== '' ? (int)$_POST['id_evento'] : null;
$accion = $_POST['accion'] ?? '';
$usa_diferenciados = isset($_POST['usa_diferenciados']) ? 1 : 0;

$precios = [
    'general' => isset($_POST['precio_general']) ? (float)$_POST['precio_general'] : 0,
    'nino' => isset($_POST['precio_nino']) ? (float)$_POST['precio_nino'] : 0,
    'adulto_mayor' => isset($_POST['precio_adulto_mayor']) ? (float)$_POST['precio_adulto_mayor'] : 0,
    'discapacitado' => isset($_POST['precio_discapacitado']) ? (float)$_POST['precio_discapacitado'] : 0,
    'cortesia' => 0 // Siempre gratis
];

// Verificar que la tabla existe y tiene la columna usa_diferenciados
$conn->query("
    CREATE TABLE IF NOT EXISTS precios_tipo_boleto (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_evento INT NULL COMMENT 'NULL = precio global para todos los eventos',
        tipo_boleto VARCHAR(50) NOT NULL,
        precio DECIMAL(10,2) NOT NULL DEFAULT 0,
        activo TINYINT(1) DEFAULT 1,
        usa_diferenciados TINYINT(1) DEFAULT 0,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_evento_tipo (id_evento, tipo_boleto)
    )
");

// Verificar si la columna usa_diferenciados existe
$check_col = $conn->query("SHOW COLUMNS FROM precios_tipo_boleto LIKE 'usa_diferenciados'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE precios_tipo_boleto ADD COLUMN usa_diferenciados TINYINT(1) DEFAULT 0");
}

try {
    switch ($accion) {
        case 'guardar':
            // Guardar precios (globales o para un evento específico)
            foreach ($precios as $tipo => $precio) {
                if ($id_evento === null) {
                    // Precio global
                    $stmt = $conn->prepare("
                        INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                        VALUES (NULL, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
                    ");
                    $stmt->bind_param("sdi", $tipo, $precio, $usa_diferenciados);
                } else {
                    // Precio específico del evento
                    $stmt = $conn->prepare("
                        INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
                    ");
                    $stmt->bind_param("isdi", $id_evento, $tipo, $precio, $usa_diferenciados);
                }
                $stmt->execute();
                $stmt->close();
            }
            
            // También mantener compatibilidad con 'adulto' = 'general'
            if ($id_evento === null) {
                $stmt = $conn->prepare("
                    INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                    VALUES (NULL, 'adulto', ?, ?)
                    ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
                ");
                $stmt->bind_param("di", $precios['general'], $usa_diferenciados);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                    VALUES (?, 'adulto', ?, ?)
                    ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
                ");
                $stmt->bind_param("idi", $id_evento, $precios['general'], $usa_diferenciados);
                $stmt->execute();
                $stmt->close();
            }
            
            $msg = $id_evento ? 'Precios guardados para este evento' : 'Precios globales actualizados';
            $redirect = $id_evento ? "index.php?id_evento=$id_evento&status=success&msg=" . urlencode($msg) 
                                   : "index.php?status=success&msg=" . urlencode($msg);
            break;
            
        case 'usar_global':
            // Eliminar precios específicos del evento para que use los globales
            if ($id_evento) {
                $stmt = $conn->prepare("DELETE FROM precios_tipo_boleto WHERE id_evento = ?");
                $stmt->bind_param("i", $id_evento);
                $stmt->execute();
                $stmt->close();
            }
            
            $redirect = "index.php?id_evento=$id_evento&status=success&msg=" . urlencode('Ahora este evento usa precios globales');
            break;
            
        case 'aplicar_todos':
            // Aplicar precios globales a todos los eventos (eliminar precios específicos)
            $conn->query("DELETE FROM precios_tipo_boleto WHERE id_evento IS NOT NULL");
            
            // Actualizar globales
            foreach ($precios as $tipo => $precio) {
                $stmt = $conn->prepare("
                    INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                    VALUES (NULL, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
                ");
                $stmt->bind_param("sdi", $tipo, $precio, $usa_diferenciados);
                $stmt->execute();
                $stmt->close();
            }
            
            // Compatibilidad con adulto
            $stmt = $conn->prepare("
                INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio, usa_diferenciados) 
                VALUES (NULL, 'adulto', ?, ?)
                ON DUPLICATE KEY UPDATE precio = VALUES(precio), usa_diferenciados = VALUES(usa_diferenciados)
            ");
            $stmt->bind_param("di", $precios['general'], $usa_diferenciados);
            $stmt->execute();
            $stmt->close();
            
            $redirect = "index.php?status=success&msg=" . urlencode('Precios aplicados a todos los eventos');
            break;
            
        default:
            $redirect = "index.php?status=error&msg=" . urlencode('Acción no válida');
    }
    
} catch (Exception $e) {
    $redirect = "index.php?status=error&msg=" . urlencode($e->getMessage());
}

$conn->close();
header("Location: $redirect");
exit;
?>
