<?php
require_once 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_boleto'])) {
    echo json_encode(['success' => false, 'message' => 'ID de boleto no proporcionado']);
    exit;
}

$id_boleto = (int)$data['id_boleto'];

// Verificar que el boleto existe y está activo
$stmt_check = $conn->prepare("SELECT estatus FROM boletos WHERE id_boleto = ?");
$stmt_check->bind_param("i", $id_boleto);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Boleto no encontrado']);
    exit;
}

$boleto = $result->fetch_assoc();
$stmt_check->close();

if ($boleto['estatus'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Este boleto ya fue usado anteriormente']);
    exit;
}

// Actualizar estatus a 0 (usado)
$stmt_update = $conn->prepare("UPDATE boletos SET estatus = 0 WHERE id_boleto = ?");
$stmt_update->bind_param("i", $id_boleto);

if ($stmt_update->execute()) {
    echo json_encode(['success' => true, 'message' => 'Acceso confirmado exitosamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al confirmar acceso']);
}

$stmt_update->close();
$conn->close();
?>
