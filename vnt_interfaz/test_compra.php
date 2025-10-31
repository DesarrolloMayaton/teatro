<?php
// Script de prueba para verificar la configuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Configuración de Compra</h2>";

// 1. Verificar conexión
include "../conexion.php";
if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}
echo "✅ Conexión a base de datos exitosa<br>";

// 2. Verificar tabla asientos
$result = $conn->query("SHOW TABLES LIKE 'asientos'");
if ($result->num_rows > 0) {
    echo "✅ Tabla 'asientos' existe<br>";
    
    // Mostrar estructura
    $result = $conn->query("DESCRIBE asientos");
    echo "<strong>Estructura de tabla asientos:</strong><br>";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})<br>";
    }
} else {
    echo "❌ Tabla 'asientos' no existe<br>";
}

// 3. Verificar tabla boletos
$result = $conn->query("SHOW TABLES LIKE 'boletos'");
if ($result->num_rows > 0) {
    echo "✅ Tabla 'boletos' existe<br>";
    
    // Mostrar estructura
    $result = $conn->query("DESCRIBE boletos");
    echo "<strong>Estructura de tabla boletos:</strong><br>";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})<br>";
    }
} else {
    echo "❌ Tabla 'boletos' no existe<br>";
}

// 4. Verificar directorio de QR
$qr_dir = __DIR__ . '/../boletos_qr';
if (file_exists($qr_dir)) {
    echo "✅ Directorio 'boletos_qr' existe<br>";
    if (is_writable($qr_dir)) {
        echo "✅ Directorio 'boletos_qr' tiene permisos de escritura<br>";
    } else {
        echo "❌ Directorio 'boletos_qr' NO tiene permisos de escritura<br>";
    }
} else {
    echo "❌ Directorio 'boletos_qr' no existe<br>";
}

// 5. Verificar vendor/autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✅ Composer autoload existe<br>";
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Verificar librería QR
    if (class_exists('Endroid\QrCode\QrCode')) {
        echo "✅ Librería QR Code instalada correctamente<br>";
    } else {
        echo "❌ Librería QR Code no encontrada<br>";
    }
} else {
    echo "❌ Composer autoload no existe. Ejecuta 'composer install' en vnt_interfaz/<br>";
}

$conn->close();
?>
