<?php
/**
 * Configuración de Base de Datos - Teatro Online
 * =============================================
 * Comparte la misma base de datos que vnt_interfaz para sincronización
 */

if (defined('ONLINE_DB_INCLUDED')) {
    return;
}
define('ONLINE_DB_INCLUDED', true);

// Usar la misma base de datos que vnt_interfaz
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'trt_25');

/**
 * Obtener conexión a la base de datos
 */
function getOnlineConnection() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log("Error conexión teatro_online: " . $e->getMessage());
        return null;
    }
}

// Crear conexión global
$conn_online = getOnlineConnection();
?>
