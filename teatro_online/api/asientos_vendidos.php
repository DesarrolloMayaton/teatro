<?php
/**
 * API de Asientos Vendidos - Teatro Online
 * =========================================
 * Retorna asientos ocupados para un evento/función
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$id_evento = isset($_GET['id_evento']) ? (int)$_GET['id_evento'] : 0;
$id_funcion = isset($_GET['id_funcion']) ? (int)$_GET['id_funcion'] : 0;

if ($id_evento <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de evento inválido'
    ]);
    exit;
}

try {
    // Verificar si existe id_funcion en boletos
    $check_column = $conn_online->query("SHOW COLUMNS FROM boletos LIKE 'id_funcion'");
    $has_id_funcion = ($check_column && $check_column->num_rows > 0);
    
    if ($has_id_funcion && $id_funcion > 0) {
        $stmt = $conn_online->prepare("
            SELECT a.codigo_asiento 
            FROM boletos b
            INNER JOIN asientos a ON b.id_asiento = a.id_asiento
            WHERE b.id_evento = ? AND b.id_funcion = ? AND b.estatus = 1
        ");
        $stmt->bind_param("ii", $id_evento, $id_funcion);
    } else {
        $stmt = $conn_online->prepare("
            SELECT a.codigo_asiento 
            FROM boletos b
            INNER JOIN asientos a ON b.id_asiento = a.id_asiento
            WHERE b.id_evento = ? AND b.estatus = 1
        ");
        $stmt->bind_param("i", $id_evento);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $asientos = [];
    while ($row = $result->fetch_assoc()) {
        $asientos[] = $row['codigo_asiento'];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'asientos' => $asientos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn_online->close();
?>
