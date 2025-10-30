<?php
require_once 'conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id_boleto'])) {
    die('ID de boleto no proporcionado');
}

$id_boleto = (int)$_GET['id_boleto'];

// Obtener información del boleto
$stmt = $conn->prepare("
    SELECT b.*, e.titulo as evento_titulo, e.descripcion, a.codigo_asiento, a.fila, a.numero,
           (SELECT MIN(f.fecha_hora) FROM funciones f WHERE f.id_evento = e.id_evento AND f.fecha_hora >= NOW()) as fecha_funcion
    FROM boletos b
    INNER JOIN evento e ON b.id_evento = e.id_evento
    INNER JOIN asientos a ON b.id_asiento = a.id_asiento
    WHERE b.id_boleto = ?
");
$stmt->bind_param("i", $id_boleto);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Boleto no encontrado');
}

$boleto = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Convertir imagen QR a base64
$qr_path = __DIR__ . '/../' . $boleto['qr_path'];
$qr_base64 = '';
if (file_exists($qr_path)) {
    $qr_data = file_get_contents($qr_path);
    $qr_base64 = 'data:image/png;base64,' . base64_encode($qr_data);
}

$fecha_funcion = $boleto['fecha_funcion'] ? date('d/m/Y H:i', strtotime($boleto['fecha_funcion'])) : 'Por confirmar';
$fecha_compra = date('d/m/Y H:i', strtotime($boleto['fecha_compra']));

// HTML del PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .ticket-container { border: 3px solid #2c3e50; border-radius: 15px; padding: 30px; max-width: 600px; margin: 0 auto; }
        .header { text-align: center; border-bottom: 2px dashed #ccc; padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { color: #2c3e50; margin: 0 0 10px 0; font-size: 32px; }
        .header h2 { color: #e74c3c; margin: 0; font-size: 24px; }
        .info-section { margin: 20px 0; }
        .info-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px; }
        .info-label { font-weight: bold; color: #555; }
        .info-value { color: #2c3e50; }
        .qr-section { text-align: center; margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 10px; }
        .qr-section img { max-width: 250px; }
        .codigo-unico { text-align: center; font-size: 14px; color: #666; margin-top: 10px; word-break: break-all; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px dashed #ccc; color: #666; font-size: 12px; }
        .precio { font-size: 24px; font-weight: bold; color: #27ae60; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="ticket-container">
        <div class="header">
            <h1>🎭 MI TEATRO</h1>
            <h2>' . htmlspecialchars($boleto['evento_titulo']) . '</h2>
        </div>
        
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Asiento:</span>
                <span class="info-value">' . htmlspecialchars($boleto['codigo_asiento']) . ' (Fila ' . htmlspecialchars($boleto['fila']) . ', Número ' . $boleto['numero'] . ')</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha de Función:</span>
                <span class="info-value">' . $fecha_funcion . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha de Compra:</span>
                <span class="info-value">' . $fecha_compra . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Estado:</span>
                <span class="info-value">' . ($boleto['estatus'] == 1 ? '✓ Válido' : '✗ Usado') . '</span>
            </div>
        </div>
        
        <div class="precio">
            $' . number_format($boleto['precio_final'], 2) . ' MXN
        </div>
        
        <div class="qr-section">
            <p style="margin: 0 0 15px 0; font-weight: bold; color: #2c3e50;">Código QR de Acceso</p>
            <img src="' . $qr_base64 . '" alt="QR Code">
            <div class="codigo-unico">
                <strong>Código:</strong> ' . htmlspecialchars($boleto['codigo_unico']) . '
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Instrucciones:</strong></p>
            <p>Presenta este boleto en la entrada del teatro. El código QR será escaneado para validar tu acceso.</p>
            <p>Una vez escaneado, el boleto quedará marcado como usado y no podrá ser utilizado nuevamente.</p>
            <p style="margin-top: 15px;">¡Disfruta el espectáculo!</p>
        </div>
    </div>
</body>
</html>
';

// Generar PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Enviar PDF al navegador
$dompdf->stream('boleto_' . $id_boleto . '.pdf', ['Attachment' => true]);
?>
