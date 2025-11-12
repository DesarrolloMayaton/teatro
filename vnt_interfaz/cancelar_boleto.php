<?php
// Cancelar boleto y liberar asiento
header('Content-Type: application/json');

include "../conexion.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_boleto = $_POST['id_boleto'] ?? 0;

if (empty($id_boleto)) {
    echo json_encode(['success' => false, 'message' => 'ID de boleto requerido']);
    exit;
}

// Iniciar transacción
$conn->begin_transaction();

try {
    // Verificar que el boleto existe y está activo (estatus = 1)
    $stmt = $conn->prepare("SELECT id_boleto, estatus FROM boletos WHERE id_boleto = ?");
    $stmt->bind_param("i", $id_boleto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Boleto no encontrado');
    }
    
    $boleto = $result->fetch_assoc();
    
    if ($boleto['estatus'] == 0) {
        throw new Exception('El boleto ya fue usado y no puede ser cancelado');
    } elseif ($boleto['estatus'] == 2) {
        throw new Exception('El boleto ya fue cancelado previamente');
    } elseif ($boleto['estatus'] != 1) {
        throw new Exception('El boleto no está en estado válido para cancelación');
    }
    
    $stmt->close();
    
    // Actualizar el estado del boleto a 2 (cancelado)
    $stmt = $conn->prepare("UPDATE boletos SET estatus = 2 WHERE id_boleto = ?");
    $stmt->bind_param("i", $id_boleto);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al cancelar el boleto');
    }
    
    $stmt->close();
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Boleto cancelado exitosamente'
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
