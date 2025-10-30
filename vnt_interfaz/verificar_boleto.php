<?php
require_once 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['codigo_unico'])) {
    echo json_encode(['success' => false, 'message' => 'Código no proporcionado']);
    exit;
}

$codigo_unico = $data['codigo_unico'];

$stmt = $conn->prepare("
    SELECT b.*, e.titulo as evento_titulo, a.codigo_asiento, a.fila, a.numero
    FROM boletos b
    INNER JOIN evento e ON b.id_evento = e.id_evento
    INNER JOIN asientos a ON b.id_asiento = a.id_asiento
    WHERE b.codigo_unico = ?
");
$stmt->bind_param("s", $codigo_unico);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Boleto no encontrado']);
    exit;
}

$boleto = $result->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'boleto' => $boleto
]);
?>
