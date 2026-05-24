<?php
/**
 * API de Cambios en Tiempo Real - Teatro Online
 * ==============================================
 * SSE para notificar cambios desde vnt_interfaz
 */

while (ob_get_level()) ob_end_clean();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('X-Accel-Buffering: no');

set_time_limit(0);

require_once __DIR__ . '/../config/database.php';

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$eventoId = isset($_GET['id_evento']) ? (int)$_GET['id_evento'] : null;
$funcionId = isset($_GET['id_funcion']) ? (int)$_GET['id_funcion'] : null;

echo "event: connected\n";
echo "data: " . json_encode(['status' => 'ok', 'time' => date('Y-m-d H:i:s')]) . "\n\n";
flush();

// Verificar tabla cambios_log
$tableCheck = $conn_online->query("SHOW TABLES LIKE 'cambios_log'");
if ($tableCheck->num_rows === 0) {
    $conn_online->query("
        CREATE TABLE IF NOT EXISTS cambios_log (
            id_cambio INT AUTO_INCREMENT PRIMARY KEY,
            tipo_cambio ENUM('venta', 'cancelacion', 'evento', 'categoria', 'descuento', 'mapa', 'funcion', 'precio') NOT NULL,
            id_evento INT NULL,
            id_funcion INT NULL,
            datos JSON NULL,
            fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            procesado TINYINT(1) DEFAULT 0,
            INDEX idx_fecha (fecha_cambio),
            INDEX idx_tipo (tipo_cambio),
            INDEX idx_evento (id_evento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

$maxTime = 30;
$startTime = time();
$checkInterval = 2;

while ((time() - $startTime) < $maxTime) {
    if (connection_aborted()) {
        break;
    }
    
    $sql = "SELECT * FROM cambios_log WHERE id_cambio > ?";
    $params = [$lastId];
    $types = "i";
    
    if ($eventoId !== null) {
        $sql .= " AND (id_evento = ? OR id_evento IS NULL)";
        $params[] = $eventoId;
        $types .= "i";
    }
    
    $sql .= " ORDER BY id_cambio ASC LIMIT 50";
    
    $stmt = $conn_online->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cambios = [];
    while ($row = $result->fetch_assoc()) {
        $cambios[] = [
            'id' => (int)$row['id_cambio'],
            'tipo' => $row['tipo_cambio'],
            'id_evento' => $row['id_evento'] ? (int)$row['id_evento'] : null,
            'id_funcion' => $row['id_funcion'] ? (int)$row['id_funcion'] : null,
            'datos' => json_decode($row['datos'], true),
            'fecha' => $row['fecha_cambio']
        ];
        $lastId = max($lastId, (int)$row['id_cambio']);
    }
    $stmt->close();
    
    if (!empty($cambios)) {
        foreach ($cambios as $cambio) {
            echo "event: cambio\n";
            echo "data: " . json_encode($cambio) . "\n\n";
        }
        flush();
    } else {
        echo ": keepalive " . time() . "\n\n";
        flush();
    }
    
    sleep($checkInterval);
}

echo "event: reconnect\n";
echo "data: " . json_encode(['last_id' => $lastId]) . "\n\n";
flush();

$conn_online->close();
?>
