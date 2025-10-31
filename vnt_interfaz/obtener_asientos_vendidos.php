<?php
header('Content-Type: application/json');
include "../conexion.php";

$id_evento = isset($_GET['id_evento']) ? (int)$_GET['id_evento'] : 0;

if ($id_evento <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de evento inválido']);
    exit;
}

// Obtener todos los asientos vendidos para este evento (estatus 1 = activo, 0 = usado)
$stmt = $conn->prepare("
    SELECT a.codigo_asiento 
    FROM boletos b
    INNER JOIN asientos a ON b.id_asiento = a.id_asiento
    WHERE b.id_evento = ?
");

$stmt->bind_param("i", $id_evento);
$stmt->execute();
$result = $stmt->get_result();

$asientos = [];
while ($row = $result->fetch_assoc()) {
    $asientos[] = $row['codigo_asiento'];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'asientos' => $asientos
]);
?>
