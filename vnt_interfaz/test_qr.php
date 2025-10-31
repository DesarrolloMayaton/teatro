<?php
/**
 * Script de prueba para verificar la generación de códigos QR
 * Acceder a: http://localhost/teatro/vnt_interfaz/test_qr.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Crear directorio de prueba si no existe
$test_dir = __DIR__ . '/../boletos_qr';
if (!file_exists($test_dir)) {
    mkdir($test_dir, 0777, true);
}

// Generar código de prueba
$codigo_prueba = 'TEST' . strtoupper(bin2hex(random_bytes(6)));

try {
    // Crear QR
    $qr = QrCode::create($codigo_prueba)
        ->setSize(300)
        ->setMargin(10);
    
    $writer = new PngWriter();
    $result = $writer->write($qr);
    
    // Guardar imagen
    $qr_path = $test_dir . '/' . $codigo_prueba . '.png';
    $result->saveToFile($qr_path);
    
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Test QR</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='card'>
                <div class='card-header bg-success text-white'>
                    <h3>✓ Generación de QR Exitosa</h3>
                </div>
                <div class='card-body text-center'>
                    <h4>Código generado: <code>{$codigo_prueba}</code></h4>
                    <img src='../boletos_qr/{$codigo_prueba}.png' alt='QR Test' class='img-fluid mt-3' style='max-width: 300px;'>
                    <hr>
                    <p class='text-success'><strong>El sistema está funcionando correctamente</strong></p>
                    <a href='index.php' class='btn btn-primary'>Ir al Punto de Venta</a>
                    <a href='escanear_qr.php?codigo={$codigo_prueba}' class='btn btn-secondary'>Probar Escaneo</a>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
} catch (Exception $e) {
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Error Test QR</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='card'>
                <div class='card-header bg-danger text-white'>
                    <h3>✗ Error en la Generación de QR</h3>
                </div>
                <div class='card-body'>
                    <div class='alert alert-danger'>
                        <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
                    </div>
                    <p>Verifica que:</p>
                    <ul>
                        <li>Composer esté instalado correctamente</li>
                        <li>Las dependencias estén instaladas (composer install)</li>
                        <li>La carpeta boletos_qr/ tenga permisos de escritura</li>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    </html>";
}
?>
