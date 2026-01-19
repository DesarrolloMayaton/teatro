<?php
/**
 * AUTO-ARCHIVO DE EVENTOS
 * Este script archiva automáticamente los eventos cuya última función
 * terminó hace más de 4 horas.
 * 
 * Se ejecuta automáticamente al cargar la página de eventos activos.
 */

// Evitar ejecución directa
if (!isset($conn)) {
    die('Este archivo debe ser incluido, no ejecutado directamente.');
}

// Configuración
define('HORAS_PARA_ARCHIVAR', 4); // Horas después de la última función para archivar

/**
 * Función para archivar evento (copiada de act_evento.php para independencia)
 */
function archivar_evento_auto($id, $conn) {
    $db_historico = 'trt_historico_evento';
    $db_principal = 'trt_25';
    
    // 1. COPIAR TODO A HISTÓRICO
    $conn->query("INSERT IGNORE INTO {$db_historico}.evento SELECT * FROM {$db_principal}.evento WHERE id_evento = $id");
    $conn->query("INSERT IGNORE INTO {$db_historico}.funciones SELECT * FROM {$db_principal}.funciones WHERE id_evento = $id");
    $conn->query("INSERT IGNORE INTO {$db_historico}.categorias SELECT * FROM {$db_principal}.categorias WHERE id_evento = $id");
    $conn->query("INSERT IGNORE INTO {$db_historico}.promociones SELECT * FROM {$db_principal}.promociones WHERE id_evento = $id");
    $conn->query("INSERT IGNORE INTO {$db_historico}.boletos SELECT * FROM {$db_principal}.boletos WHERE id_evento = $id");

    // 2. BORRAR DE PRODUCCIÓN
    $conn->query("DELETE FROM {$db_principal}.boletos WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.promociones WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.categorias WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.funciones WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.evento WHERE id_evento = $id");
    
    return true;
}

/**
 * Ejecutar auto-archivado de eventos caducados
 */
function ejecutar_auto_archivado($conn) {
    $horas = HORAS_PARA_ARCHIVAR;
    $eventos_archivados = [];
    
    // Buscar eventos activos cuya ÚLTIMA función terminó hace más de X horas
    // Un evento se archiva cuando TODAS sus funciones ya pasaron hace más de 4 horas
    $sql = "
        SELECT e.id_evento, e.titulo,
               MAX(f.fecha_hora) as ultima_funcion,
               TIMESTAMPDIFF(HOUR, MAX(f.fecha_hora), NOW()) as horas_desde_ultima
        FROM evento e
        INNER JOIN funciones f ON e.id_evento = f.id_evento
        WHERE e.finalizado = 0
        GROUP BY e.id_evento, e.titulo
        HAVING horas_desde_ultima >= ?
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Error preparando consulta de auto-archivado: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("i", $horas);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($evento = $result->fetch_assoc()) {
        $id_evento = $evento['id_evento'];
        $titulo = $evento['titulo'];
        
        $conn->begin_transaction();
        try {
            archivar_evento_auto($id_evento, $conn);
            $conn->commit();
            
            $eventos_archivados[] = [
                'id' => $id_evento,
                'titulo' => $titulo,
                'ultima_funcion' => $evento['ultima_funcion'],
                'horas_desde' => $evento['horas_desde_ultima']
            ];
            
            // Registrar en transacciones si está disponible
            if (function_exists('registrar_transaccion')) {
                registrar_transaccion('evento_auto_archivar', 
                    "Auto-archivado: \"$titulo\" (última función hace {$evento['horas_desde_ultima']} horas)");
            }
            
            error_log("Auto-archivado evento ID $id_evento: $titulo");
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error auto-archivando evento $id_evento: " . $e->getMessage());
        }
    }
    
    $stmt->close();
    
    return $eventos_archivados;
}

// Ejecutar el auto-archivado
$eventos_auto_archivados = ejecutar_auto_archivado($conn);

// Si se archivaron eventos, guardar en variable de sesión para notificar
if (!empty($eventos_auto_archivados)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['eventos_auto_archivados'] = $eventos_auto_archivados;
}
