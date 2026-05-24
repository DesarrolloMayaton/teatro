<?php
/**
 * API de Categorías - Teatro Online
 * ==================================
 * Retorna categorías y precios de un evento
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$id_evento = isset($_GET['id_evento']) ? (int)$_GET['id_evento'] : 0;

if ($id_evento <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de evento inválido'
    ]);
    exit;
}

try {
    $stmt = $conn_online->prepare("
        SELECT id_categoria, nombre_categoria, precio, color 
        FROM categorias 
        WHERE id_evento = ? 
        ORDER BY precio ASC
    ");
    
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categorias = [];
    while ($row = $result->fetch_assoc()) {
        $categorias[] = [
            'id_categoria' => (int)$row['id_categoria'],
            'nombre' => $row['nombre_categoria'],
            'precio' => (float)$row['precio'],
            'color' => $row['color']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'categorias' => $categorias
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn_online->close();
?>
