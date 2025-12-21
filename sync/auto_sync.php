<?php
/**
 * Script de Sincronización Automática
 * ====================================
 * Este script se puede ejecutar como cron job o tarea programada
 * para mantener los servidores sincronizados automáticamente.
 * 
 * Uso:
 *   php auto_sync.php              - Sincronización completa bidireccional
 *   php auto_sync.php local        - Solo local a remoto
 *   php auto_sync.php remote       - Solo remoto a local
 *   php auto_sync.php status       - Ver estado sin sincronizar
 */

require_once __DIR__ . '/SyncManager.php';

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     SINCRONIZACIÓN AUTOMÁTICA DE BASES DE DATOS - TEATRO    ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  Fecha: " . date('Y-m-d H:i:s') . str_repeat(' ', 35) . "║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Obtener argumento de línea de comandos
$direction = $argv[1] ?? 'both';

$syncManager = new SyncManager();

// Verificar conexiones primero
echo "Verificando conexiones...\n";
$status = checkConnectionsStatus();

echo "\n📍 Servidor Local (localhost):\n";
if ($status['local']['connected']) {
    echo "   ✅ Conectado - Latencia: {$status['local']['latency']}ms\n";
} else {
    echo "   ❌ Error: {$status['local']['error']}\n";
}

echo "\n🌐 Servidor Remoto (10.20.40.160):\n";
if ($status['remote']['connected']) {
    echo "   ✅ Conectado - Latencia: {$status['remote']['latency']}ms\n";
} else {
    echo "   ❌ Error: {$status['remote']['error']}\n";
}

if (!$status['local']['connected'] || !$status['remote']['connected']) {
    echo "\n⚠️  No se puede proceder: Ambos servidores deben estar conectados.\n\n";
    exit(1);
}

// Si solo se pide estado, mostrar y salir
if ($direction === 'status') {
    echo "\n📊 Estado de tablas:\n";
    echo str_repeat('-', 60) . "\n";
    
    $differences = $syncManager->getDifferences();
    
    foreach ($differences as $table => $info) {
        $syncStatus = $info['in_sync'] ? '✅' : '⚠️';
        $diff = $info['difference'] > 0 ? " (diff: {$info['difference']})" : "";
        printf("   %s %-30s Local: %5d | Remoto: %5d%s\n", 
            $syncStatus, $table, $info['local_count'], $info['remote_count'], $diff);
    }
    
    echo "\n";
    exit(0);
}

// Ejecutar sincronización
echo "\n🔄 Iniciando sincronización ({$direction})...\n";
echo str_repeat('-', 60) . "\n";

$result = $syncManager->syncAll($direction);

// Mostrar log
foreach ($result['log'] as $entry) {
    $icon = match($entry['type']) {
        'error' => '❌',
        'warning' => '⚠️',
        'success' => '✅',
        default => 'ℹ️'
    };
    echo "   {$icon} [{$entry['time']}] {$entry['message']}\n";
}

// Resumen final
echo "\n" . str_repeat('=', 60) . "\n";
if ($result['success']) {
    echo "✅ SINCRONIZACIÓN COMPLETADA EXITOSAMENTE\n";
} else {
    echo "❌ SINCRONIZACIÓN CON ERRORES\n";
    echo "   Errores encontrados: " . count($result['errors']) . "\n";
    foreach ($result['errors'] as $error) {
        echo "   - {$error['message']}\n";
    }
}
echo str_repeat('=', 60) . "\n\n";

exit($result['success'] ? 0 : 1);
?>
