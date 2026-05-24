<?php
/**
 * SyncManager - Gestor de Sincronización de Bases de Datos
 * =========================================================
 * Maneja la sincronización bidireccional entre servidor local y remoto.
 */

require_once __DIR__ . '/../config/database.php';

class SyncManager {
    private $localConn;
    private $remoteConn;
    private $syncLog = [];
    private $errors = [];
    private $tables;
    
    public function __construct() {
        $this->tables = unserialize(SYNC_TABLES);
    }
    
    /**
     * Inicializar conexiones
     */
    public function initConnections() {
        $this->localConn = getLocalConnection();
        $this->remoteConn = getRemoteConnection();
        
        return [
            'local' => $this->localConn !== null,
            'remote' => $this->remoteConn !== null
        ];
    }
    
    /**
     * Cerrar conexiones
     */
    public function closeConnections() {
        if ($this->localConn) {
            $this->localConn->close();
        }
        if ($this->remoteConn) {
            $this->remoteConn->close();
        }
    }
    
    /**
     * Obtener estado de sincronización
     */
    public function getStatus() {
        $status = checkConnectionsStatus();
        $status['sync_mode'] = SYNC_MODE;
        $status['primary_server'] = PRIMARY_SERVER;
        $status['last_sync'] = $this->getLastSyncTime();
        $status['tables'] = $this->tables;
        return $status;
    }
    
    /**
     * Obtener última hora de sincronización
     */
    private function getLastSyncTime() {
        $logFile = __DIR__ . '/sync_log.json';
        if (file_exists($logFile)) {
            $log = json_decode(file_get_contents($logFile), true);
            return $log['last_sync'] ?? null;
        }
        return null;
    }
    
    /**
     * Guardar log de sincronización
     */
    private function saveLogToFile() {
        $logFile = __DIR__ . '/sync_log.json';
        $logData = [
            'last_sync' => date('Y-m-d H:i:s'),
            'entries' => $this->syncLog,
            'errors' => $this->errors
        ];
        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
    }
    
    /**
     * Agregar campos de tracking a las tablas si no existen
     */
    public function ensureTrackingFields() {
        $connections = $this->initConnections();
        
        foreach ($this->tables as $table) {
            // Agregar campos de tracking en ambos servidores
            $alterSQL = "
                ALTER TABLE `$table` 
                ADD COLUMN IF NOT EXISTS `sync_updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                ADD COLUMN IF NOT EXISTS `sync_source` VARCHAR(50) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `sync_id` VARCHAR(100) DEFAULT NULL
            ";
            
            // Intentar en local
            if ($this->localConn) {
                try {
                    // MySQL no soporta IF NOT EXISTS para columnas, usar método alternativo
                    $this->addColumnIfNotExists($this->localConn, $table, 'sync_updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
                    $this->addColumnIfNotExists($this->localConn, $table, 'sync_source', 'VARCHAR(50) DEFAULT NULL');
                    $this->addColumnIfNotExists($this->localConn, $table, 'sync_id', 'VARCHAR(100) DEFAULT NULL');
                } catch (Exception $e) {
                    $this->addLog("Error agregando campos tracking a $table (local): " . $e->getMessage(), 'error');
                }
            }
            
            // Intentar en remoto
            if ($this->remoteConn) {
                try {
                    $this->addColumnIfNotExists($this->remoteConn, $table, 'sync_updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
                    $this->addColumnIfNotExists($this->remoteConn, $table, 'sync_source', 'VARCHAR(50) DEFAULT NULL');
                    $this->addColumnIfNotExists($this->remoteConn, $table, 'sync_id', 'VARCHAR(100) DEFAULT NULL');
                } catch (Exception $e) {
                    $this->addLog("Error agregando campos tracking a $table (remoto): " . $e->getMessage(), 'error');
                }
            }
        }
        
        return $this->syncLog;
    }
    
    /**
     * Agregar columna si no existe
     */
    private function addColumnIfNotExists($conn, $table, $column, $definition) {
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($result && $result->num_rows == 0) {
            $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            $this->addLog("Columna $column agregada a $table", 'info');
        }
    }
    
    /**
     * Agregar entrada al log
     */
    private function addLog($message, $type = 'info') {
        $entry = [
            'time' => date('Y-m-d H:i:s'),
            'type' => $type,
            'message' => $message
        ];
        $this->syncLog[] = $entry;
        
        if ($type === 'error') {
            $this->errors[] = $entry;
        }
    }
    
    /**
     * Sincronización completa bidireccional
     */
    public function syncAll($direction = 'both') {
        $this->syncLog = [];
        $this->errors = [];
        
        $connections = $this->initConnections();
        
        if (!$connections['local']) {
            $this->addLog('No se pudo conectar al servidor local', 'error');
            return ['success' => false, 'log' => $this->syncLog, 'errors' => $this->errors];
        }
        
        if (!$connections['remote']) {
            $this->addLog('No se pudo conectar al servidor remoto', 'error');
            return ['success' => false, 'log' => $this->syncLog, 'errors' => $this->errors];
        }
        
        $this->addLog('Iniciando sincronización completa...');
        
        // Primero asegurar campos de tracking
        $this->ensureTrackingFields();
        
        // Sincronizar cada tabla según dirección
        foreach ($this->tables as $table) {
            try {
                if ($direction === 'both' || $direction === 'local_to_remote') {
                    $this->syncTable($table, 'local_to_remote');
                }
                if ($direction === 'both' || $direction === 'remote_to_local') {
                    $this->syncTable($table, 'remote_to_local');
                }
            } catch (Exception $e) {
                $this->addLog("Error sincronizando tabla $table: " . $e->getMessage(), 'error');
            }
        }
        
        $this->addLog('Sincronización completada');
        $this->saveLogToFile();
        
        $this->closeConnections();
        
        return [
            'success' => count($this->errors) === 0,
            'log' => $this->syncLog,
            'errors' => $this->errors
        ];
    }
    
    /**
     * Sincronizar una tabla específica
     */
    public function syncTable($table, $direction = 'local_to_remote') {
        $sourceConn = ($direction === 'local_to_remote') ? $this->localConn : $this->remoteConn;
        $targetConn = ($direction === 'local_to_remote') ? $this->remoteConn : $this->localConn;
        $sourceLabel = ($direction === 'local_to_remote') ? 'local' : 'remote';
        $targetLabel = ($direction === 'local_to_remote') ? 'remote' : 'local';
        
        if (!$sourceConn || !$targetConn) {
            $this->addLog("Conexiones no disponibles para sincronizar $table", 'error');
            return false;
        }
        
        // Obtener la estructura de la tabla
        $columns = $this->getTableColumns($sourceConn, $table);
        if (empty($columns)) {
            $this->addLog("No se encontró la tabla $table en $sourceLabel", 'warning');
            return false;
        }
        
        // Obtener la clave primaria
        $primaryKey = $this->getPrimaryKey($sourceConn, $table);
        if (!$primaryKey) {
            $this->addLog("No se encontró clave primaria en $table, usando 'id'", 'info');
            $primaryKey = 'id';
        }
        
        // Obtener todos los registros del origen
        $sourceData = $this->getTableData($sourceConn, $table);
        $targetData = $this->getTableData($targetConn, $table);
        
        // Crear índice de datos destino por clave primaria
        $targetIndex = [];
        foreach ($targetData as $row) {
            if (isset($row[$primaryKey])) {
                $targetIndex[$row[$primaryKey]] = $row;
            }
        }
        
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        
        foreach ($sourceData as $row) {
            $pkValue = $row[$primaryKey] ?? null;
            
            if ($pkValue === null) {
                $skipped++;
                continue;
            }
            
            // Verificar si existe en destino
            if (isset($targetIndex[$pkValue])) {
                // Comparar timestamps para decidir si actualizar
                $sourceTime = strtotime($row['sync_updated_at'] ?? '2000-01-01');
                $targetTime = strtotime($targetIndex[$pkValue]['sync_updated_at'] ?? '2000-01-01');
                
                if ($sourceTime > $targetTime) {
                    // Actualizar en destino
                    $this->updateRecord($targetConn, $table, $row, $primaryKey, $sourceLabel);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // Insertar en destino
                $row['sync_source'] = $sourceLabel;
                $row['sync_id'] = uniqid($sourceLabel . '_');
                $this->insertRecord($targetConn, $table, $row);
                $inserted++;
            }
        }
        
        $this->addLog("$table ($direction): $inserted insertados, $updated actualizados, $skipped omitidos");
        return true;
    }
    
    /**
     * Obtener columnas de una tabla
     */
    private function getTableColumns($conn, $table) {
        $columns = [];
        $result = $conn->query("DESCRIBE `$table`");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }
        return $columns;
    }
    
    /**
     * Obtener clave primaria de una tabla
     */
    private function getPrimaryKey($conn, $table) {
        $result = $conn->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
        if ($result && $row = $result->fetch_assoc()) {
            return $row['Column_name'];
        }
        return null;
    }
    
    /**
     * Obtener todos los datos de una tabla
     */
    private function getTableData($conn, $table) {
        $data = [];
        $result = $conn->query("SELECT * FROM `$table`");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
    
    /**
     * Insertar registro en tabla
     */
    private function insertRecord($conn, $table, $row) {
        $columns = array_keys($row);
        $values = array_values($row);
        
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $columnList = implode(', ', array_map(function($c) { return "`$c`"; }, $columns));
        
        $types = str_repeat('s', count($values));
        
        $sql = "INSERT INTO `$table` ($columnList) VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Convertir nulls a tipos apropiados
            foreach ($values as $i => $v) {
                if ($v === null) {
                    $values[$i] = null;
                }
            }
            
            $stmt->bind_param($types, ...$values);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        
        return false;
    }
    
    /**
     * Actualizar registro en tabla
     */
    private function updateRecord($conn, $table, $row, $primaryKey, $source) {
        $pkValue = $row[$primaryKey];
        unset($row[$primaryKey]); // No actualizar la clave primaria
        
        $row['sync_source'] = $source;
        
        $sets = [];
        $values = [];
        foreach ($row as $column => $value) {
            $sets[] = "`$column` = ?";
            $values[] = $value;
        }
        
        $values[] = $pkValue;
        $types = str_repeat('s', count($values));
        
        $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$primaryKey` = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param($types, ...$values);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        
        return false;
    }
    
    /**
     * Obtener diferencias entre bases de datos
     */
    public function getDifferences() {
        $connections = $this->initConnections();
        $differences = [];
        
        if (!$connections['local'] || !$connections['remote']) {
            return ['error' => 'No se pudieron establecer las conexiones'];
        }
        
        foreach ($this->tables as $table) {
            $localCount = $this->getRecordCount($this->localConn, $table);
            $remoteCount = $this->getRecordCount($this->remoteConn, $table);
            
            $differences[$table] = [
                'local_count' => $localCount,
                'remote_count' => $remoteCount,
                'difference' => abs($localCount - $remoteCount),
                'in_sync' => $localCount === $remoteCount
            ];
        }
        
        $this->closeConnections();
        return $differences;
    }
    
    /**
     * Obtener cantidad de registros en una tabla
     */
    private function getRecordCount($conn, $table) {
        if (!$conn) return 0;
        
        try {
            $result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
            if ($result && $row = $result->fetch_assoc()) {
                return (int)$row['count'];
            }
        } catch (Exception $e) {
            return 0;
        }
        
        return 0;
    }
    
    /**
     * Ejecutar operación en ambos servidores
     */
    public function executeOnBoth($sql, $params = []) {
        $results = ['local' => false, 'remote' => false];
        
        $connections = $this->initConnections();
        
        if ($this->localConn) {
            try {
                if (empty($params)) {
                    $results['local'] = $this->localConn->query($sql);
                } else {
                    $stmt = $this->localConn->prepare($sql);
                    if ($stmt) {
                        $types = str_repeat('s', count($params));
                        $stmt->bind_param($types, ...$params);
                        $results['local'] = $stmt->execute();
                        $stmt->close();
                    }
                }
            } catch (Exception $e) {
                $this->addLog("Error ejecutando en local: " . $e->getMessage(), 'error');
            }
        }
        
        if ($this->remoteConn) {
            try {
                if (empty($params)) {
                    $results['remote'] = $this->remoteConn->query($sql);
                } else {
                    $stmt = $this->remoteConn->prepare($sql);
                    if ($stmt) {
                        $types = str_repeat('s', count($params));
                        $stmt->bind_param($types, ...$params);
                        $results['remote'] = $stmt->execute();
                        $stmt->close();
                    }
                }
            } catch (Exception $e) {
                $this->addLog("Error ejecutando en remoto: " . $e->getMessage(), 'error');
            }
        }
        
        $this->closeConnections();
        return $results;
    }
}
?>
