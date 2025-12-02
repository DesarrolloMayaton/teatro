<?php
header('Content-Type: application/json');

include "../conexion.php";

if (!isset($_GET['id_evento'])) {
    echo json_encode(['success' => false, 'message' => 'ID de evento no proporcionado']);
    exit;
}

$id_evento = (int)$_GET['id_evento'];

// Obtener promociones/descuentos del evento activas
$stmt = $conn->prepare("
    SELECT 
        p.id_promocion,
        p.nombre,
        p.modo_calculo,
        p.valor,
        p.codigo,
        p.id_categoria,
        p.tipo_regla,
        p.min_cantidad,
        c.nombre_categoria
    FROM promociones p
    LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
    WHERE (p.id_evento = ? OR p.id_evento IS NULL)
    AND p.activo = 1
    AND (p.fecha_desde IS NULL OR p.fecha_desde <= NOW())
    AND (p.fecha_hasta IS NULL OR p.fecha_hasta >= NOW())
    ORDER BY p.nombre ASC
");

$stmt->bind_param("i", $id_evento);
$stmt->execute();
$result = $stmt->get_result();

$descuentos = [];
while ($row = $result->fetch_assoc()) {
    $descuentos[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'descuentos' => $descuentos
]);
?>
