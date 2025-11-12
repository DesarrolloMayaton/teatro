<?php
header('Content-Type: application/json');

include "../conexion.php";

try {
    // Obtener todos los eventos activos
    $query = "SELECT id_evento, titulo, tipo FROM evento WHERE finalizado = 0 ORDER BY titulo ASC";
    $result = $conn->query($query);
    
    $eventos = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $eventos[] = [
                'id_evento' => (int)$row['id_evento'],
                'titulo' => $row['titulo'],
                'tipo' => (int)$row['tipo']
            ];
        }
    }
    
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

$conn->close();
?>
