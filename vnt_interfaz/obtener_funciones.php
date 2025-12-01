<?php
// Endpoint para obtener funciones disponibles en tiempo real
header('Content-Type: application/json');

include "../conexion.php";

$response = ['success' => false, 'funciones' => []];

if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    $id_evento = (int)$_GET['id_evento'];
    $fecha_actual = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("SELECT id_funcion, fecha_hora FROM funciones WHERE id_evento = ? AND fecha_hora > ? ORDER BY fecha_hora ASC");
    $stmt->bind_param("is", $id_evento, $fecha_actual);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        $funciones = [];
        while ($row = $result->fetch_assoc()) {
            $fecha_funcion = new DateTime($row['fecha_hora']);
            $funciones[] = [
                'id_funcion' => (int)$row['id_funcion'],
                'fecha_hora' => $row['fecha_hora'],
                'texto' => $fecha_funcion->format('d/m/Y \a\s H:i')
            ];
        }
        $response['success'] = true;
        $response['funciones'] = $funciones;
    }
    
    $stmt->close();
}

$conn->close();
echo json_encode($response);
