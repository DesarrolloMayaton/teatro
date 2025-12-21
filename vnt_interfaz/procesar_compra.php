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

// Iniciar sesión para obtener el usuario que está vendiendo
session_start();
require_once __DIR__ . '/../transacciones_helper.php';
require_once __DIR__ . '/../api/registrar_cambio.php';

// Obtener ID del usuario logueado
$id_usuario_vendedor = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;

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
$id_funcion = isset($data['id_funcion']) ? (int)$data['id_funcion'] : 0;
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
        $tipo_boleto = isset($asiento_data['tipo_boleto']) ? $asiento_data['tipo_boleto'] : 'adulto';
        
        // Validar que la categoría existe y pertenece al evento
        $stmt = $conn->prepare("SELECT id_categoria FROM categorias WHERE id_categoria = ? AND id_evento = ?");
        $stmt->bind_param("ii", $categoria_id, $id_evento);
        $stmt->execute();
        $result_cat = $stmt->get_result();
        
        if ($result_cat->num_rows === 0) {
            // La categoría no existe o no pertenece al evento, buscar una categoría por defecto
            $stmt->close();
            
            // Primero intentar encontrar "General"
            $stmt = $conn->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? AND LOWER(nombre_categoria) = 'general' LIMIT 1");
            $stmt->bind_param("i", $id_evento);
            $stmt->execute();
            $result_cat = $stmt->get_result();
            
            if ($result_cat->num_rows > 0) {
                $row_cat = $result_cat->fetch_assoc();
                $categoria_id = (int)$row_cat['id_categoria'];
                $stmt->close();
            } else {
                // Si no hay "General", tomar la primera categoría disponible del evento
                $stmt->close();
                $stmt = $conn->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? ORDER BY precio ASC LIMIT 1");
                $stmt->bind_param("i", $id_evento);
                $stmt->execute();
                $result_cat = $stmt->get_result();
                
                if ($result_cat->num_rows > 0) {
                    $row_cat = $result_cat->fetch_assoc();
                    $categoria_id = (int)$row_cat['id_categoria'];
                    $stmt->close();
                } else {
                    // No hay categorías para este evento
                    $stmt->close();
                    throw new Exception("El evento no tiene categorías configuradas. Por favor, configura las categorías antes de vender boletos.");
                }
            }
        } else {
            $stmt->close();
        }
        
        // Si es cortesía, el precio final es 0
        if ($tipo_boleto === 'cortesia') {
            $precio_final = 0.00;
            $descuento_aplicado = $precio; // El descuento es el precio completo
        }
        
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
        $sql = "SELECT id_boleto, estatus FROM boletos WHERE id_evento = ? AND id_asiento = ?";
        $params = [$id_evento, $id_asiento];
        $types = "ii";
        
        // Si se proporcionó id_funcion, incluirlo en la consulta
        if ($id_funcion > 0) {
            $sql .= " AND id_funcion = ?";
            $params[] = $id_funcion;
            $types .= "i";
        } else {
            // Si no se proporcionó id_funcion, buscar boletos con id_funcion NULL o 0
            $sql .= " AND (id_funcion IS NULL OR id_funcion = 0)";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
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
                        id_funcion = ?,
                        id_categoria = ?,
                        id_promocion = ?,
                        codigo_unico = ?,
                        precio_base = ?,
                        descuento_aplicado = ?,
                        precio_final = ?,
                        tipo_boleto = ?,
                        id_usuario = ?,
                        fecha_compra = NOW(),
                        estatus = 1
                    WHERE id_boleto = ?
                ");
                
                $stmt->bind_param("iiisdddsii", 
                    $id_funcion,
                    $categoria_id,
                    $id_promocion,
                    $codigo_unico,
                    $precio,
                    $descuento_aplicado,
                    $precio_final,
                    $tipo_boleto,
                    $id_usuario_vendedor,
                    $boleto_existente['id_boleto']
                );
            } else {
                $stmt = $conn->prepare("
                    UPDATE boletos SET
                        id_funcion = ?,
                        id_categoria = ?,
                        id_promocion = NULL,
                        codigo_unico = ?,
                        precio_base = ?,
                        descuento_aplicado = ?,
                        precio_final = ?,
                        tipo_boleto = ?,
                        id_usuario = ?,
                        fecha_compra = NOW(),
                        estatus = 1
                    WHERE id_boleto = ?
                ");
                
                $stmt->bind_param("iisdddsii", 
                    $id_funcion,
                    $categoria_id,
                    $codigo_unico,
                    $precio,
                    $descuento_aplicado,
                    $precio_final,
                    $tipo_boleto,
                    $id_usuario_vendedor,
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
                $sql = "
                    INSERT INTO boletos (
                        id_evento, 
                        id_funcion,
                        id_asiento, 
                        id_categoria, 
                        id_promocion,
                        codigo_unico, 
                        precio_base, 
                        descuento_aplicado, 
                        precio_final,
                        tipo_boleto,
                        id_usuario, 
                        estatus
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiiiisdddsi", 
                    $id_evento, 
                    $id_funcion,
                    $id_asiento, 
                    $categoria_id,
                    $id_promocion, 
                    $codigo_unico, 
                    $precio,
                    $descuento_aplicado, 
                    $precio_final,
                    $tipo_boleto,
                    $id_usuario_vendedor
                );
            } else {
                $sql = "
                    INSERT INTO boletos (
                        id_evento, 
                        id_funcion,
                        id_asiento, 
                        id_categoria, 
                        codigo_unico, 
                        precio_base, 
                        descuento_aplicado, 
                        precio_final,
                        tipo_boleto,
                        id_usuario
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiiisdddsi", 
                    $id_evento, 
                    $id_funcion,
                    $id_asiento, 
                    $categoria_id, 
                    $codigo_unico, 
                    $precio,
                    $descuento_aplicado, 
                    $precio_final,
                    $tipo_boleto,
                    $id_usuario_vendedor
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
    
    $cantidad_boletos = count($boletos_generados);
    registrar_transaccion('venta', 'Venta de ' . $cantidad_boletos . ' boleto(s) para evento ID ' . $id_evento);
    
    // Notificar cambio para auto-actualización en tiempo real
    registrar_cambio('venta', $id_evento, $id_funcion, [
        'asientos' => array_column($boletos_generados, 'asiento'),
        'cantidad' => $cantidad_boletos
    ]);
    
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