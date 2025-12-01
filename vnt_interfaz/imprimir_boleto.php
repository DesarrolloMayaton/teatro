<?php
// Habilitar todos los errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../conexion.php';

if (!isset($_GET['codigo']) || empty($_GET['codigo'])) {
    die('Código de boleto no proporcionado');
}

// Función helper para convertir UTF-8 a ISO-8859-1
function convertirTexto($texto) {
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

$codigo_unico = $_GET['codigo'];

// Obtener información del boleto y evento
$stmt = $conn->prepare("
    SELECT 
        b.codigo_unico,
        b.precio_final,
        b.fecha_compra,
        a.codigo_asiento,
        e.titulo as evento_nombre,
        c.nombre_categoria,
        f.fecha_hora as funcion_fecha,
        f.nombre as funcion_nombre,
        TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, ''))) AS vendedor_nombre
    FROM boletos b
    INNER JOIN asientos a ON b.id_asiento = a.id_asiento
    INNER JOIN evento e ON b.id_evento = e.id_evento
    INNER JOIN funciones f ON b.id_funcion = f.id_funcion
    INNER JOIN categorias c ON b.id_categoria = c.id_categoria
    LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario
    WHERE b.codigo_unico = ? AND b.estatus = 1
");

$stmt->bind_param("s", $codigo_unico);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Boleto no encontrado');
}

$boleto = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Crear PDF para impresora térmica (80mm de ancho)
// 80mm = aproximadamente 226 puntos en FPDF
$ancho = 80; // mm
$pdf = new FPDF('P', 'mm', array($ancho, 200)); // Alto variable
$pdf->AddPage();
$pdf->SetMargins(3, 5, 3); // Márgenes muy pequeños

// Línea separadora superior
$pdf->SetLineWidth(0.5);
$pdf->Line(3, $pdf->GetY(), $ancho - 3, $pdf->GetY());
$pdf->Ln(3);

// Título principal
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, 'BOLETO DE ENTRADA', 0, 1, 'C');
$pdf->Ln(2);

// Línea separadora
$pdf->Line(3, $pdf->GetY(), $ancho - 3, $pdf->GetY());
$pdf->Ln(3);

// Nombre del evento
$pdf->SetFont('Arial', 'B', 10);
$nombre_evento = convertirTexto($boleto['evento_nombre']);
// Ajustar texto largo
if (strlen($nombre_evento) > 35) {
    $pdf->SetFont('Arial', 'B', 8);
}
$pdf->MultiCell(0, 5, $nombre_evento, 0, 'C');
$pdf->Ln(2);

// Fecha y hora de la función
if ($boleto['funcion_fecha']) {
    $fecha_obj = new DateTime($boleto['funcion_fecha']);
    $fecha_str = $fecha_obj->format('d/m/Y');
    $hora_str = $fecha_obj->format('H:i');
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(0, 4, 'FUNCIÓN', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 4, 'Fecha: ' . $fecha_str, 0, 1, 'C');
    $pdf->Cell(0, 4, 'Hora: ' . $hora_str, 0, 1, 'C');
    
    if (!empty($boleto['funcion_nombre'])) {
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell(0, 4, 'Tipo:', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 7);
        $pdf->MultiCell(0, 4, convertirTexto($boleto['funcion_nombre']), 0, 'C');
    }
    
    $pdf->Ln(2);
}

// Línea separadora
$pdf->Line(3, $pdf->GetY(), $ancho - 3, $pdf->GetY());
$pdf->Ln(3);

// Información del boleto
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 5, 'Asiento:', 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, $boleto['codigo_asiento'], 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 5, convertirTexto('Categoría:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, convertirTexto($boleto['nombre_categoria']), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 5, 'Precio:', 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, '$' . number_format($boleto['precio_final'], 2), 0, 1);

$pdf->Ln(3);

// Código QR (más pequeño para ticket)
$qr_path = __DIR__ . '/../boletos_qr/' . $codigo_unico . '.png';
if (file_exists($qr_path)) {
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(0, 4, convertirTexto('Código de verificación'), 0, 1, 'C');
    $pdf->Ln(1);
    
    // QR de 40mm (centrado en 80mm)
    $qr_size = 40;
    $qr_x = ($ancho - $qr_size) / 2;
    $pdf->Image($qr_path, $qr_x, $pdf->GetY(), $qr_size, $qr_size);
    $pdf->Ln($qr_size + 2);
}

// Código único
$pdf->SetFont('Arial', '', 7);
$pdf->Cell(0, 4, 'Codigo: ' . $codigo_unico, 0, 1, 'C');
$pdf->Ln(2);

// Línea separadora
$pdf->Line(3, $pdf->GetY(), $ancho - 3, $pdf->GetY());
$pdf->Ln(2);

// Fecha de compra
$fecha_compra = new DateTime($boleto['fecha_compra']);
$pdf->SetFont('Arial', 'I', 6);
$pdf->Cell(0, 3, 'Comprado: ' . $fecha_compra->format('d/m/Y H:i'), 0, 1, 'C');
$pdf->Cell(0, 3, 'Vendido por: ' . convertirTexto(!empty($boleto['vendedor_nombre']) ? $boleto['vendedor_nombre'] : 'Sin asignar'), 0, 1, 'C');

// Nota al pie
$pdf->Ln(2);
$pdf->SetFont('Arial', 'I', 6);
$pdf->MultiCell(0, 3, convertirTexto('Este boleto es válido únicamente para la función indicada. Conserve este boleto para su ingreso al evento.'), 0, 'C');

$pdf->Ln(2);
$pdf->Line(3, $pdf->GetY(), $ancho - 3, $pdf->GetY());

// Salida del PDF
$pdf->Output('I', 'Ticket_' . $boleto['codigo_asiento'] . '.pdf');
?>

