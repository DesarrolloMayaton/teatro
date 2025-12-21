<?php
// Evitar cualquier output antes del PDF
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../conexion.php';

if (!isset($_GET['codigo']) || empty($_GET['codigo'])) {
    die('Código de boleto no proporcionado');
}

// Función helper para convertir UTF-8 a ISO-8859-1 (reemplazo de utf8_decode)
function convertirTexto($texto) {
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

$codigo_unico = $_GET['codigo'];

// Obtener información del boleto (solo boletos activos con estatus = 1)
$stmt = $conn->prepare("
    SELECT 
        b.codigo_unico,
        b.precio_final,
        a.codigo_asiento,
        e.titulo as evento_nombre,
        e.imagen,
        c.nombre_categoria,
        f.fecha_hora as funcion_fecha,
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

// Crear PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);

// Título con mejor diseño
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 12, 'BOLETO DE ENTRADA', 0, 1, 'C', true);
$pdf->Ln(8);

// Imagen del evento (si existe) - Dimensionada para que quepa en una sola hoja
// La imagen se guarda en evt_interfaz/imagenes/ y en la BD se guarda como "imagenes/evt_xxx.jpg"
$imagen_path = __DIR__ . '/../evt_interfaz/' . $boleto['imagen'];
if (!empty($boleto['imagen']) && file_exists($imagen_path)) {
    // Obtener dimensiones de la imagen
    $imagen_info = getimagesize($imagen_path);
    if ($imagen_info) {
        $ancho_imagen = $imagen_info[0];
        $alto_imagen = $imagen_info[1];
        
        // Ancho máximo disponible
        $ancho_maximo = 140;
        $alto_maximo = 70; // Altura máxima para la imagen
        
        // Calcular dimensiones manteniendo proporción
        $ratio = $ancho_imagen / $alto_imagen;
        
        if ($ancho_imagen > $alto_imagen) {
            // Imagen horizontal
            $ancho_final = min($ancho_maximo, $ancho_imagen);
            $alto_final = $ancho_final / $ratio;
            
            // Si la altura excede el máximo, ajustar
            if ($alto_final > $alto_maximo) {
                $alto_final = $alto_maximo;
                $ancho_final = $alto_final * $ratio;
            }
        } else {
            // Imagen vertical
            $alto_final = min($alto_maximo, $alto_imagen);
            $ancho_final = $alto_final * $ratio;
            
            // Si el ancho excede el máximo, ajustar
            if ($ancho_final > $ancho_maximo) {
                $ancho_final = $ancho_maximo;
                $alto_final = $ancho_final / $ratio;
            }
        }
        
        // Centrar la imagen
        $x_centrado = (210 - $ancho_final) / 2;
        $pdf->Image($imagen_path, $x_centrado, $pdf->GetY(), $ancho_final, $alto_final);
        $pdf->Ln($alto_final + 8);
    } else {
        // Si no se pueden obtener las dimensiones, usar tamaño fijo
        $pdf->Image($imagen_path, 35, $pdf->GetY(), 140, 70);
        $pdf->Ln(78);
    }
} else {
    $pdf->Ln(3);
}

// Información del evento
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 8, convertirTexto($boleto['evento_nombre']), 0, 1, 'C');
$pdf->Ln(8);

// Línea separadora
$pdf->SetLineWidth(0.5);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(8);

// Detalles del boleto en formato más compacto
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(45, 7, 'Asiento:', 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, $boleto['codigo_asiento'], 0, 1);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(45, 7, convertirTexto('Categoría:'), 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, convertirTexto($boleto['nombre_categoria']), 0, 1);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(45, 7, 'Precio:', 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 128, 0);
$pdf->Cell(0, 7, '$' . number_format($boleto['precio_final'], 2), 0, 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(45, 7, 'Función:', 0, 0);
$pdf->SetFont('Arial', '', 11);
$fecha_funcion = new DateTime($boleto['funcion_fecha']);
$pdf->Cell(0, 7, $fecha_funcion->format('d/m/Y H:i'), 0, 1);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(45, 7, convertirTexto('Vendido por:'), 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, convertirTexto(!empty(trim($boleto['vendedor_nombre'])) ? trim($boleto['vendedor_nombre']) : 'Sin asignar'), 0, 1);

$pdf->Ln(5);

// Código QR - Reducido de tamaño
$qr_path = __DIR__ . '/../boletos_qr/' . $codigo_unico . '.png';
if (file_exists($qr_path)) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, convertirTexto('Código de verificación:'), 0, 1, 'C');
    $pdf->Ln(3);
    
    // QR más pequeño (50mm en lugar de 80mm) y centrado
    $qr_size = 50;
    $qr_x = (210 - $qr_size) / 2;
    $pdf->Image($qr_path, $qr_x, $pdf->GetY(), $qr_size, $qr_size);
    $pdf->Ln($qr_size + 5);
}

// Código único
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, 'Codigo: ' . $codigo_unico, 0, 1, 'C');

$pdf->Ln(8);

// Línea separadora
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

// Nota al pie
$pdf->SetFont('Arial', 'I', 8);
$pdf->MultiCell(0, 4, convertirTexto('Este boleto es válido únicamente para la función indicada. Conserve este boleto para su ingreso al evento.'), 0, 'C');

// Salida del PDF
$pdf->Output('D', 'Boleto_' . $boleto['codigo_asiento'] . '.pdf');
?>
