<?php
// Capturar todos los errores y warnings
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Función para manejar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Error fatal: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']
        ]);
    }
});

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/vendor/autoload.php';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error al cargar dependencias: ' . $e->getMessage()]);
    exit;
}

$conexion_path = __DIR__ . '/../conexion.php';
if (!file_exists($conexion_path)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Archivo de conexión no encontrado en: ' . $conexion_path]);
    exit;
}

try {
    include $conexion_path;
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error al incluir conexión: ' . $e->getMessage()]);
    exit;
}

if (!isset($conn) || !$conn) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: No se pudo establecer conexión a la base de datos']);
    exit;
}

// Importar clases de QR Code para v6.x
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

// Leer datos JSON del request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['id_evento']) || !isset($data['asientos'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$id_evento = (int)$data['id_evento'];
$asientos = $data['asientos'];

if (empty($asientos)) {
    echo json_encode(['success' => false, 'message' => 'No hay asientos seleccionados']);
    exit;
}

// Crear directorio para QR si no existe
$qr_dir = __DIR__ . '/../boletos_qr';
if (!file_exists($qr_dir)) {
    mkdir($qr_dir, 0777, true);
}

$conn->begin_transaction();

try {
    $boletos_generados = [];
    
    foreach ($asientos as $asiento_data) {
        $codigo_asiento = $asiento_data['asiento'];
        $categoria_id = (int)$asiento_data['categoriaId'];
        $precio = (float)$asiento_data['precio'];
        $descuento_aplicado = isset($asiento_data['descuento_aplicado']) ? (float)$asiento_data['descuento_aplicado'] : 0;
        $precio_final = isset($asiento_data['precio_final']) ? (float)$asiento_data['precio_final'] : $precio;
        $id_promocion = isset($asiento_data['id_promocion']) ? (int)$asiento_data['id_promocion'] : null;
        
        // Obtener o crear id_asiento de la tabla asientos
        $stmt = $conn->prepare("SELECT id_asiento FROM asientos WHERE codigo_asiento = ?");
        $stmt->bind_param("s", $codigo_asiento);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Si el asiento no existe, crearlo
            $stmt->close();
            
            // Extraer fila y número del código de asiento
            preg_match('/^([A-Z]+\d*)[-]?(\d+)$/', $codigo_asiento, $matches);
            $fila = isset($matches[1]) ? $matches[1] : substr($codigo_asiento, 0, 1);
            $numero = isset($matches[2]) ? (int)$matches[2] : (int)filter_var($codigo_asiento, FILTER_SANITIZE_NUMBER_INT);
            
            $stmt = $conn->prepare("INSERT INTO asientos (codigo_asiento, fila, numero) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $codigo_asiento, $fila, $numero);
            if (!$stmt->execute()) {
                throw new Exception("Error al crear asiento $codigo_asiento: " . $stmt->error);
            }
            $id_asiento = $conn->insert_id;
            $stmt->close();
        } else {
            $row = $result->fetch_assoc();
            $id_asiento = $row['id_asiento'];
            $stmt->close();
        }
        
        // Verificar si existe un boleto para este asiento
        $stmt = $conn->prepare("SELECT id_boleto, estatus FROM boletos WHERE id_evento = ? AND id_asiento = ?");
        $stmt->bind_param("ii", $id_evento, $id_asiento);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $boleto_existente = null;
        if ($result->num_rows > 0) {
            $boleto_existente = $result->fetch_assoc();
        }
        $stmt->close();
        
        // Si existe un boleto activo (estatus = 1), no se puede vender
        if ($boleto_existente && $boleto_existente['estatus'] == 1) {
            throw new Exception("El asiento $codigo_asiento ya está vendido");
        }
        
        // Generar código único alfanumérico
        $codigo_unico = strtoupper(bin2hex(random_bytes(8)));
        
        // Si existe un boleto cancelado (estatus = 2) o usado (estatus = 0), reutilizarlo
        if ($boleto_existente) {
            // Actualizar el boleto existente
            if ($id_promocion) {
                $stmt = $conn->prepare("
                    UPDATE boletos SET
                        id_categoria = ?,
                        id_promocion = ?,
                        codigo_unico = ?,
                        precio_base = ?,
                        descuento_aplicado = ?,
                        precio_final = ?,
                        fecha_compra = NOW(),
                        estatus = 1
                    WHERE id_boleto = ?
                ");
                
                $stmt->bind_param("iisdddi", 
                    $categoria_id,
                    $id_promocion,
                    $codigo_unico,
                    $precio,
                    $descuento_aplicado,
                    $precio_final,
                    $boleto_existente['id_boleto']
                );
            } else {
                $stmt = $conn->prepare("
                    UPDATE boletos SET
                        id_categoria = ?,
                        id_promocion = NULL,
                        codigo_unico = ?,
                        precio_base = ?,
                        descuento_aplicado = ?,
                        precio_final = ?,
                        fecha_compra = NOW(),
                        estatus = 1
                    WHERE id_boleto = ?
                ");
                
                $stmt->bind_param("isdddi", 
                    $categoria_id,
                    $codigo_unico,
                    $precio,
                    $descuento_aplicado,
                    $precio_final,
                    $boleto_existente['id_boleto']
                );
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar boleto: " . $stmt->error);
            }
            $stmt->close();
        } else {
            // Insertar nuevo boleto si no existe ninguno
            if ($id_promocion) {
                $stmt = $conn->prepare("
                    INSERT INTO boletos (
                        id_evento, 
                        id_asiento, 
                        id_categoria, 
                        id_promocion,
                        codigo_unico, 
                        precio_base, 
                        descuento_aplicado, 
                        precio_final, 
                        estatus
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                
                $stmt->bind_param("iiiisddd", 
                    $id_evento, 
                    $id_asiento, 
                    $categoria_id,
                    $id_promocion, 
                    $codigo_unico, 
                    $precio,
                    $descuento_aplicado, 
                    $precio_final
                );
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO boletos (
                        id_evento, 
                        id_asiento, 
                        id_categoria, 
                        codigo_unico, 
                        precio_base, 
                        descuento_aplicado, 
                        precio_final, 
                        estatus
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                
                $stmt->bind_param("iiisddd", 
                    $id_evento, 
                    $id_asiento, 
                    $categoria_id, 
                    $codigo_unico, 
                    $precio,
                    $descuento_aplicado, 
                    $precio_final
                );
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error al crear boleto: " . $stmt->error);
            }
            $stmt->close();
        }
        
        // Generar código QR - VERSIÓN 6.x
        try {
            $builder = new Builder(
                writer: new PngWriter(),
                data: $codigo_unico,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 300,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin
            );
            
            $result = $builder->build();
            
            // Guardar imagen QR
            $qr_path = $qr_dir . '/' . $codigo_unico . '.png';
            $result->saveToFile($qr_path);
            
        } catch (Exception $qr_error) {
            throw new Exception("Error al generar QR: " . $qr_error->getMessage());
        }
        
        $boletos_generados[] = [
            'asiento' => $codigo_asiento,
            'codigo_unico' => $codigo_unico,
            'precio' => $precio_final
        ];
    }
    
    $conn->commit();
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Compra procesada exitosamente',
        'boletos' => $boletos_generados
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
ob_end_flush();
?>