<?php
/**
 * API de Funciones - Teatro Online
 * ================================
 * Retorna funciones disponibles para un evento
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
    // Permitir vender hasta 2 horas después de iniciada
    $fecha_limite = date('Y-m-d H:i:s', strtotime('-2 hours'));
    
    $stmt = $conn_online->prepare("
        SELECT id_funcion, fecha_hora, estado 
        FROM funciones 
        WHERE id_evento = ? AND fecha_hora > ? 
        ORDER BY fecha_hora ASC
    ");
    
    $stmt->bind_param("is", $id_evento, $fecha_limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $funciones = [];
    $dias_semana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    
    while ($row = $result->fetch_assoc()) {
        $fecha_funcion = new DateTime($row['fecha_hora']);
        $estado = (int)$row['estado'];
        
        $funciones[] = [
            'id_funcion' => (int)$row['id_funcion'],
            'fecha_hora' => $row['fecha_hora'],
            'texto' => $dias_semana[(int)$fecha_funcion->format('w')] . ' ' . 
                      $fecha_funcion->format('d') . ' - ' . 
                      $fecha_funcion->format('g:i A'),
            'estado' => $estado,
            'vencida' => $estado === 1
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'funciones' => $funciones
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn_online->close();
?>
