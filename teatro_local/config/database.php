<?php
/**
 * Configuración de Base de Datos Multi-Servidor
 * =============================================
 * Este archivo centraliza la configuración para la sincronización
 * entre el servidor local y el servidor remoto.
 */

// Protección contra inclusión múltiple
if (defined('DATABASE_CONFIG_INCLUDED')) {
    return;
}
define('DATABASE_CONFIG_INCLUDED', true);

// Configuración del servidor LOCAL (XAMPP)
define('DB_LOCAL_HOST', 'localhost');
define('DB_LOCAL_USER', 'root');
define('DB_LOCAL_PASS', '');
define('DB_LOCAL_NAME', 'trt_25');

// Configuración del servidor REMOTO (IP: 10.20.40.160)
define('DB_REMOTE_HOST', '10.20.40.160');
define('DB_REMOTE_USER', 'admin');
define('DB_REMOTE_PASS', 'informatico');
define('DB_REMOTE_NAME', 'trt_25');

// Servidor primario para operaciones de escritura
// Cambiar a 'remote' si el servidor remoto es el principal
define('PRIMARY_SERVER', 'local');

// Tiempo de espera para conexiones (en segundos)
define('DB_TIMEOUT', 5);

// Modo de sincronización: 'auto' | 'manual' | 'realtime'
define('SYNC_MODE', 'auto');

// Intervalo de sincronización automática (en segundos)
define('SYNC_INTERVAL', 30);

// Tablas a sincronizar (en orden de dependencia)
define('SYNC_TABLES', serialize([
    'usuarios',
    'evento',
    'funciones',
    'categorias',
    'promociones',
    'asientos',
    'boletos',
    'transacciones',
    'precios_tipo_boleto'
]));

/**
 * Obtener conexión a la base de datos local
 */
function getLocalConnection() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    try {
        $conn = new mysqli(DB_LOCAL_HOST, DB_LOCAL_USER, DB_LOCAL_PASS, DB_LOCAL_NAME);
        $conn->set_charset("utf8mb4");
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, DB_TIMEOUT);
        return $conn;
    } catch (Exception $e) {
        error_log("Error conexión LOCAL: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtener conexión a la base de datos remota
 */
function getRemoteConnection() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    try {
        $conn = new mysqli(DB_REMOTE_HOST, DB_REMOTE_USER, DB_REMOTE_PASS, DB_REMOTE_NAME);
        $conn->set_charset("utf8mb4");
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, DB_TIMEOUT);
        return $conn;
    } catch (Exception $e) {
        error_log("Error conexión REMOTO: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtener conexión al servidor primario
 */
function getPrimaryConnection() {
    if (PRIMARY_SERVER === 'remote') {
        return getRemoteConnection();
    }
    return getLocalConnection();
}

/**
 * Obtener conexión al servidor secundario
 */
function getSecondaryConnection() {
    if (PRIMARY_SERVER === 'remote') {
        return getLocalConnection();
    }
    return getRemoteConnection();
}

/**
 * Obtener ambas conexiones
 * @return array ['local' => mysqli|null, 'remote' => mysqli|null]
 */
function getBothConnections() {
    return [
        'local' => getLocalConnection(),
        'remote' => getRemoteConnection()
    ];
}

/**
 * Verificar estado de conexiones
 * @return array Estado de cada servidor
 */
function checkConnectionsStatus() {
    $status = [
        'local' => ['connected' => false, 'latency' => 0, 'error' => null],
        'remote' => ['connected' => false, 'latency' => 0, 'error' => null]
    ];
    
    // Verificar local
    $start = microtime(true);
    $localConn = getLocalConnection();
    if ($localConn) {
        $status['local']['connected'] = true;
        $status['local']['latency'] = round((microtime(true) - $start) * 1000, 2);
        $localConn->close();
    } else {
        $status['local']['error'] = 'No se pudo conectar al servidor local';
    }
    
    // Verificar remoto
    $start = microtime(true);
    $remoteConn = getRemoteConnection();
    if ($remoteConn) {
        $status['remote']['connected'] = true;
        $status['remote']['latency'] = round((microtime(true) - $start) * 1000, 2);
        $remoteConn->close();
    } else {
        $status['remote']['error'] = 'No se pudo conectar al servidor remoto';
    }
    
    return $status;
}
?>
