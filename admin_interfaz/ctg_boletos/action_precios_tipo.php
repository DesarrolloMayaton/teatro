<?php
// Acción para manejar precios por tipo de boleto
include "../../evt_interfaz/conexion.php";

// Obtener datos del formulario
$id_evento = isset($_POST['id_evento']) && $_POST['id_evento'] !== '' ? (int)$_POST['id_evento'] : null;
$accion = $_POST['accion'] ?? '';

$precios = [
    'adulto' => isset($_POST['precio_adulto']) ? (float)$_POST['precio_adulto'] : 0,
    'nino' => isset($_POST['precio_nino']) ? (float)$_POST['precio_nino'] : 0,
    'adulto_mayor' => isset($_POST['precio_adulto_mayor']) ? (float)$_POST['precio_adulto_mayor'] : 0,
    'discapacitado' => isset($_POST['precio_discapacitado']) ? (float)$_POST['precio_discapacitado'] : 0,
    'cortesia' => 0 // Siempre gratis
];

// Verificar que la tabla existe
$conn->query("
    CREATE TABLE IF NOT EXISTS precios_tipo_boleto (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_evento INT NULL COMMENT 'NULL = precio global para todos los eventos',
        tipo_boleto VARCHAR(50) NOT NULL,
        precio DECIMAL(10,2) NOT NULL DEFAULT 0,
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_evento_tipo (id_evento, tipo_boleto)
    )
");

try {
    switch ($accion) {
        case 'guardar':
            // Guardar precios (globales o para un evento específico)
            foreach ($precios as $tipo => $precio) {
                if ($id_evento === null) {
                    // Precio global
                    $stmt = $conn->prepare("
                        INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio) 
                        VALUES (NULL, ?, ?)
                        ON DUPLICATE KEY UPDATE precio = VALUES(precio)
                    ");
                    $stmt->bind_param("sd", $tipo, $precio);
                } else {
                    // Precio específico del evento
                    $stmt = $conn->prepare("
                        INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio) 
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE precio = VALUES(precio)
                    ");
                    $stmt->bind_param("isd", $id_evento, $tipo, $precio);
                }
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
                    INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio) 
                    VALUES (NULL, ?, ?)
                    ON DUPLICATE KEY UPDATE precio = VALUES(precio)
                ");
                $stmt->bind_param("sd", $tipo, $precio);
                $stmt->execute();
                $stmt->close();
            }
            
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
