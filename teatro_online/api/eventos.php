<?php
/**
 * API de Eventos - Teatro Online
 * =================================
 * Retorna eventos disponibles para compra en línea
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

if (!$conn_online || $conn_online->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos'
    ]);
    exit;
}

try {
    // Obtener eventos activos con funciones disponibles
    $fecha_limite = date('Y-m-d H:i:s', strtotime('-2 hours'));
    
    $stmt = $conn_online->prepare("
        SELECT DISTINCT 
            e.id_evento,
            e.titulo,
            e.tipo,
            e.descripcion,
            e.imagen,
            e.mapa_json,
            (SELECT COUNT(*) FROM funciones f 
             WHERE f.id_evento = e.id_evento 
             AND f.fecha_hora > ? AND f.estado = 0) as funciones_disponibles
        FROM evento e
        INNER JOIN funciones f ON e.id_evento = f.id_evento
        WHERE e.finalizado = 0 
        AND f.fecha_hora > ?
        AND f.estado = 0
        ORDER BY e.titulo ASC
    ");
    
    $stmt->bind_param("ss", $fecha_limite, $fecha_limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $eventos = [];
    while ($row = $result->fetch_assoc()) {
        $eventos[] = [
            'id_evento' => (int)$row['id_evento'],
            'titulo' => $row['titulo'],
            'tipo' => (int)$row['tipo'],
            'tipo_texto' => $row['tipo'] == 1 ? 'Teatro 420' : 'Pasarela 540',
            'descripcion' => $row['descripcion'],
            'imagen' => $row['imagen'],
            'funciones_disponibles' => (int)$row['funciones_disponibles']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'eventos' => $eventos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn_online->close();
?>
