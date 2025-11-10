<?php
// Evitar cualquier output antes del PDF
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../conexion.php';

if (!isset($_GET['codigos']) || empty($_GET['codigos'])) {
    die('Códigos de boletos no proporcionados');
}

// Función helper para convertir UTF-8 a ISO-8859-1
function convertirTexto($texto) {
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

// Obtener códigos (separados por comas)
$codigos = explode(',', $_GET['codigos']);

if (empty($codigos)) {
    die('No se proporcionaron códigos válidos');
}

// Crear placeholders para la consulta
$placeholders = implode(',', array_fill(0, count($codigos), '?'));

// Obtener información de todos los boletos
$stmt = $conn->prepare("
    SELECT 
        b.codigo_unico,
        b.precio_final,
        a.codigo_asiento,
        e.titulo as evento_nombre,
        e.imagen,
        c.nombre_categoria
    FROM boletos b
    INNER JOIN asientos a ON b.id_asiento = a.id_asiento
    INNER JOIN evento e ON b.id_evento = e.id_evento
    INNER JOIN categorias c ON b.id_categoria = c.id_categoria
    WHERE b.codigo_unico IN ($placeholders)
    ORDER BY a.codigo_asiento
");

// Bind dinámico de parámetros
$types = str_repeat('s', count($codigos));
$stmt->bind_param($types, ...$codigos);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('No se encontraron boletos');
}

$boletos = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Crear PDF
$pdf = new FPDF('P', 'mm', 'A4');

foreach ($boletos as $index => $boleto) {
    $pdf->AddPage();
    $pdf->SetMargins(20, 20, 20);

    // Título
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->Cell(0, 15, 'BOLETO DE ENTRADA', 0, 1, 'C');
    $pdf->Ln(5);

    // Imagen del evento (si existe)
    $imagen_path = __DIR__ . '/../' . $boleto['imagen'];
    if (!empty($boleto['imagen']) && file_exists($imagen_path)) {
        $pdf->Image($imagen_path, 55, $pdf->GetY(), 100, 0);
        $pdf->Ln(60);
    }

    // Información del evento
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(0, 10, convertirTexto($boleto['evento_nombre']), 0, 1, 'C');
    $pdf->Ln(5);

    // Detalles del boleto
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, 'Asiento:', 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $boleto['codigo_asiento'], 0, 1);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, convertirTexto('Categoría:'), 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, convertirTexto($boleto['nombre_categoria']), 0, 1);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, 'Precio:', 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, '$' . number_format($boleto['precio_final'], 2), 0, 1);

    $pdf->Ln(10);

    // Código QR
    $qr_path = __DIR__ . '/../boletos_qr/' . $boleto['codigo_unico'] . '.png';
    if (file_exists($qr_path)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, convertirTexto('Código de verificación:'), 0, 1, 'C');
        $pdf->Image($qr_path, 65, $pdf->GetY(), 80, 80);
        $pdf->Ln(85);
    }

    // Código único
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Codigo: ' . $boleto['codigo_unico'], 0, 1, 'C');

    $pdf->Ln(10);

    // Nota al pie
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->MultiCell(0, 5, convertirTexto('Este boleto es válido únicamente para la función indicada. Conserve este boleto para su ingreso al evento.'), 0, 'C');

    // Indicador de página
    if ($index < count($boletos) - 1) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, 'Boleto ' . ($index + 1) . ' de ' . count($boletos), 0, 0, 'C');
    }
}

// Salida del PDF
$pdf->Output('D', 'Boletos_' . date('YmdHis') . '.pdf');
?>
