<?php
session_start();
require_once 'conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_evento']) || !isset($data['asientos']) || empty($data['asientos'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$id_evento = (int)$data['id_evento'];
$asientos = $data['asientos'];

$conn->begin_transaction();

try {
    $boletos_creados = [];
    
    foreach ($asientos as $asiento_data) {
        // Soportar ambos formatos: array con id_asiento y precio, o solo el id
        if (is_array($asiento_data)) {
            $id_asiento = (int)$asiento_data['id_asiento'];
            $precio_asiento = (float)$asiento_data['precio'];
        } else {
            $id_asiento = (int)$asiento_data;
            $precio_asiento = 150.00; // Precio por defecto
        }
        
        // Verificar que el asiento no esté vendido
        $stmt_check = $conn->prepare("SELECT id_boleto FROM boletos WHERE id_evento = ? AND id_asiento = ?");
        $stmt_check->bind_param("ii", $id_evento, $id_asiento);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            throw new Exception("El asiento ya está vendido");
        }
        $stmt_check->close();
        
        // Obtener la categoría del asiento desde el mapa JSON del evento
        $stmt_cat = $conn->prepare("SELECT mapa_json FROM evento WHERE id_evento = ?");
        $stmt_cat->bind_param("i", $id_evento);
        $stmt_cat->execute();
        $res_cat = $stmt_cat->get_result();
        $evento_data = $res_cat->fetch_assoc();
        $stmt_cat->close();
        
        $id_categoria = null;
        if ($evento_data && !empty($evento_data['mapa_json'])) {
            $mapa_json = json_decode($evento_data['mapa_json'], true);
            // Obtener código del asiento
            $stmt_asiento = $conn->prepare("SELECT codigo_asiento FROM asientos WHERE id_asiento = ?");
            $stmt_asiento->bind_param("i", $id_asiento);
            $stmt_asiento->execute();
            $res_asiento = $stmt_asiento->get_result();
            $asiento_info = $res_asiento->fetch_assoc();
            $stmt_asiento->close();
            
            if ($asiento_info && isset($mapa_json[$asiento_info['codigo_asiento']])) {
                $id_categoria = $mapa_json[$asiento_info['codigo_asiento']];
            }
        }
        
        // Generar código único
        $codigo_unico = 'TRT-' . strtoupper(uniqid()) . '-' . time();
        
        // Insertar boleto
        $stmt_insert = $conn->prepare("
            INSERT INTO boletos (id_evento, id_asiento, id_categoria, codigo_unico, precio_base, descuento_aplicado, precio_final, estatus)
            VALUES (?, ?, ?, ?, ?, 0.00, ?, 1)
        ");
        $stmt_insert->bind_param("iiisdd", $id_evento, $id_asiento, $id_categoria, $codigo_unico, $precio_asiento, $precio_asiento);
        $stmt_insert->execute();
        $id_boleto = $conn->insert_id;
        $stmt_insert->close();
        
        // Generar QR
        $qr_dir = __DIR__ . '/qr_codes/';
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0777, true);
        }
        
        $qr_filename = 'qr_' . $id_boleto . '.png';
        $qr_path = $qr_dir . $qr_filename;
        
        $qrCode = QrCode::create($codigo_unico)
            ->setSize(300)
            ->setMargin(10);
        
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        $result->saveToFile($qr_path);
        
        // Actualizar ruta del QR en la base de datos
        $qr_path_db = 'vnt_interfaz/qr_codes/' . $qr_filename;
        $stmt_update = $conn->prepare("UPDATE boletos SET qr_path = ? WHERE id_boleto = ?");
        $stmt_update->bind_param("si", $qr_path_db, $id_boleto);
        $stmt_update->execute();
        $stmt_update->close();
        
        $boletos_creados[] = [
            'id_boleto' => $id_boleto,
            'codigo_unico' => $codigo_unico,
            'qr_path' => $qr_path_db
        ];
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Boletos creados exitosamente',
        'boletos' => $boletos_creados
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la compra: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
