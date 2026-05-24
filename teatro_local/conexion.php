<?php
/**
 * Conexión con Sincronización Dual
 * =================================
 * Esta conexión ejecuta operaciones en ambos servidores cuando está habilitado.
 */

// Protección contra inclusión múltiple
if (defined('CONEXION_PHP_INCLUDED')) {
    return;
}
define('CONEXION_PHP_INCLUDED', true);

require_once __DIR__ . '/config/database.php';

// Conexión principal (servidor primario según configuración)
$conn = getPrimaryConnection();

// Verificar conexión principal
if ($conn === null || $conn->connect_error) {
    // Intentar con servidor alternativo si el primario falla
    $conn = getSecondaryConnection();
    
    if ($conn === null || $conn->connect_error) {
        die("Error de conexión: No se pudo conectar a ningún servidor de base de datos");
    }
}

// Configurar charset
$conn->set_charset("utf8mb4");

/**
 * Clase para ejecutar queries en ambos servidores
 */
if (!class_exists('DualDBConnection')) {
class DualDBConnection {
    private $primary;
    private $secondary;
    private $syncEnabled;
    
    public function __construct($primary, $syncEnabled = true) {
        $this->primary = $primary;
        $this->syncEnabled = $syncEnabled;
        $this->secondary = null;
        
        // Conectar secundario solo si sync está habilitado
        if ($this->syncEnabled && SYNC_MODE !== 'manual') {
            $this->secondary = getSecondaryConnection();
        }
    }
    
    /**
     * Ejecutar query en servidor primario
     */
    public function query($sql) {
        return $this->primary->query($sql);
    }
    
    /**
     * Ejecutar query que modifica datos en ambos servidores
     */
    public function queryBoth($sql) {
        $result = $this->primary->query($sql);
        
        // Ejecutar en secundario si está disponible
        if ($this->secondary && $this->isModifyingQuery($sql)) {
            try {
                $this->secondary->query($sql);
            } catch (Exception $e) {
                error_log("Error sync secundario: " . $e->getMessage());
            }
        }
        
        return $result;
    }
    
    /**
     * Preparar statement
     */
    public function prepare($sql) {
        return $this->primary->prepare($sql);
    }
    
    /**
     * Preparar y ejecutar en ambos servidores
     */
    public function prepareBoth($sql, $types, ...$params) {
        // Ejecutar en primario
        $stmt = $this->primary->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $result = $stmt->execute();
            $stmt->close();
        }
        
        // Ejecutar en secundario
        if ($this->secondary && $this->isModifyingQuery($sql)) {
            try {
                $stmt2 = $this->secondary->prepare($sql);
                if ($stmt2) {
                    $stmt2->bind_param($types, ...$params);
                    $stmt2->execute();
                    $stmt2->close();
                }
            } catch (Exception $e) {
                error_log("Error sync secundario: " . $e->getMessage());
            }
        }
        
        return $result ?? false;
    }
    
    /**
     * Determinar si es una query que modifica datos
     */
    private function isModifyingQuery($sql) {
        $sql = trim(strtoupper($sql));
        return (
            strpos($sql, 'INSERT') === 0 ||
            strpos($sql, 'UPDATE') === 0 ||
            strpos($sql, 'DELETE') === 0 ||
            strpos($sql, 'ALTER') === 0 ||
            strpos($sql, 'CREATE') === 0 ||
            strpos($sql, 'DROP') === 0
        );
    }
    
    /**
     * Obtener último ID insertado
     */
    public function insertId() {
        return $this->primary->insert_id;
    }
    
    /**
     * Obtener error
     */
    public function error() {
        return $this->primary->error;
    }
    
    /**
     * Cerrar conexiones
     */
    public function close() {
        if ($this->primary) $this->primary->close();
        if ($this->secondary) $this->secondary->close();
    }
}
} // fin class_exists DualDBConnection

// Crear instancia de conexión dual (opcional, disponible si se necesita)
$dualConn = null;
if (!function_exists('getDualConnection')) {
    function getDualConnection() {
        global $conn, $dualConn;
        if ($dualConn === null) {
            $dualConn = new DualDBConnection($conn, SYNC_MODE === 'realtime');
        }
        return $dualConn;
    }
}
?>
