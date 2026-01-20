<?php
/**
 * API de Sincronización
 * =====================
 * Endpoints para controlar la sincronización desde la interfaz web.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/SyncManager.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

$syncManager = new SyncManager();
$response = ['success' => false, 'message' => 'Acción no reconocida'];

try {
    switch ($action) {
        case 'status':
            // Obtener estado de las conexiones
            $response = [
                'success' => true,
                'data' => $syncManager->getStatus()
            ];
            break;
            
        case 'check':
            // Verificar estado de conexiones
            $response = [
                'success' => true,
                'data' => checkConnectionsStatus()
            ];
            break;
            
        case 'differences':
            // Obtener diferencias entre bases de datos
            $response = [
                'success' => true,
                'data' => $syncManager->getDifferences()
            ];
            break;
            
        case 'sync_all':
            // Sincronización completa bidireccional
            $direction = $_GET['direction'] ?? $_POST['direction'] ?? 'both';
            $result = $syncManager->syncAll($direction);
            $response = [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Sincronización completada' : 'Sincronización con errores',
                'data' => $result
            ];
            break;
            
        case 'sync_table':
            // Sincronizar una tabla específica
            $table = $_GET['table'] ?? $_POST['table'] ?? '';
            $direction = $_GET['direction'] ?? $_POST['direction'] ?? 'local_to_remote';
            
            if (empty($table)) {
                $response = ['success' => false, 'message' => 'Tabla no especificada'];
            } else {
                $syncManager->initConnections();
                $result = $syncManager->syncTable($table, $direction);
                $syncManager->closeConnections();
                
                $response = [
                    'success' => $result,
                    'message' => $result ? "Tabla $table sincronizada" : "Error sincronizando $table"
                ];
            }
            break;
            
        case 'prepare':
            // Preparar tablas agregando campos de tracking
            $syncManager->initConnections();
            $log = $syncManager->ensureTrackingFields();
            $syncManager->closeConnections();
            
            $response = [
                'success' => true,
                'message' => 'Campos de tracking preparados',
                'log' => $log
            ];
            break;
            
        case 'test_local':
            // Probar conexión local
            $conn = getLocalConnection();
            if ($conn) {
                $response = [
                    'success' => true,
                    'message' => 'Conexión local exitosa',
                    'server_info' => $conn->server_info
                ];
                $conn->close();
            } else {
                $response = ['success' => false, 'message' => 'No se pudo conectar al servidor local'];
            }
            break;
            
        case 'test_remote':
            // Probar conexión remota
            $conn = getRemoteConnection();
            if ($conn) {
                $response = [
                    'success' => true,
                    'message' => 'Conexión remota exitosa',
                    'server_info' => $conn->server_info
                ];
                $conn->close();
            } else {
                $response = ['success' => false, 'message' => 'No se pudo conectar al servidor remoto (10.20.40.160)'];
            }
            break;
            
        default:
            $response = ['success' => false, 'message' => "Acción '$action' no reconocida"];
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
