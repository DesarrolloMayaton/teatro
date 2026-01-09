<?php
// Endpoint para obtener funciones disponibles en tiempo real
header('Content-Type: application/json');

include "../conexion.php";

$response = ['success' => false, 'funciones' => []];

if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    $id_evento = (int)$_GET['id_evento'];
    // Se permite vender hasta 2 horas después de iniciada la función O si es del día de hoy
    $fecha_limite = date('Y-m-d H:i:s', strtotime('-2 hours'));
    
    // Obtener todas las funciones disponibles (incluyendo las de hoy)
    $stmt = $conn->prepare("SELECT id_funcion, fecha_hora, estado FROM funciones WHERE id_evento = ? AND (fecha_hora > ? OR DATE(fecha_hora) = CURDATE()) ORDER BY fecha_hora ASC");
    $stmt->bind_param("is", $id_evento, $fecha_limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        $funciones = [];
        $dias_semana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        while ($row = $result->fetch_assoc()) {
            $fecha_funcion = new DateTime($row['fecha_hora']);
            $estado = (int)$row['estado'];
            $dia = $dias_semana[(int)$fecha_funcion->format('w')];
            $num = $fecha_funcion->format('d');
            $hora = $fecha_funcion->format('g:i A');
            
            $funciones[] = [
                'id_funcion' => (int)$row['id_funcion'],
                'fecha_hora' => $row['fecha_hora'],
                'texto' => "$dia $num - $hora",
                'estado' => $estado,
                'vencida' => $estado === 1
            ];
        }
        $response['success'] = true;
        $response['funciones'] = $funciones;
    }
    
    $stmt->close();
}

$conn->close();
echo json_encode($response);
