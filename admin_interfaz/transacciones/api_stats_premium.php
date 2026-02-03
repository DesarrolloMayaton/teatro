<?php
/**
 * API ESTADÍSTICAS PREMIUM
 * Proporciona métricas completas para el dashboard:
 * - Ocupación del teatro
 * - Ingresos detallados
 * - Asistencia de público
 * - Rendimiento de obras
 * - Tendencias temporales
 * - Ventas de boletos
 */

header('Content-Type: application/json');
require_once '../../evt_interfaz/conexion.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Parámetros
    $db_mode = $_GET['db'] ?? 'actual';
    $id_evento = isset($_GET['id_evento']) && $_GET['id_evento'] !== '' ? (int)$_GET['id_evento'] : null;
    $fecha_desde = $_GET['fecha_desde'] ?? null;
    $fecha_hasta = $_GET['fecha_hasta'] ?? null;

    $db_actual = 'trt_25';
    $db_historico = 'trt_historico_evento';

    // ============================================
    // HELPER: Construir WHERE clause para boletos
    // ============================================
    $buildWhere = function($prefix = 'b', $statusField = 'estatus', $statusValue = "1") use ($id_evento, $fecha_desde, $fecha_hasta) {
        $where = ["{$prefix}.{$statusField} = {$statusValue}"];
        if ($id_evento) $where[] = "{$prefix}.id_evento = {$id_evento}";
        if ($fecha_desde) $where[] = "{$prefix}.fecha_compra >= '{$fecha_desde} 00:00:00'";
        if ($fecha_hasta) $where[] = "{$prefix}.fecha_compra <= '{$fecha_hasta} 23:59:59'";
        return implode(' AND ', $where);
    };

    // ============================================
    // 1. RESUMEN GENERAL (KPIs principales)
    // ============================================
    $queries_resumen = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where = $buildWhere('b');
        $queries_resumen[] = "SELECT id_boleto, precio_final, fecha_compra, id_evento, id_funcion, id_categoria FROM {$db}.boletos b WHERE {$where}";
    }
    $union_resumen = implode(" UNION ALL ", $queries_resumen);

    $sql_resumen = "SELECT 
        COUNT(*) as total_boletos,
        COALESCE(SUM(precio_final), 0) as total_ingresos,
        COALESCE(AVG(precio_final), 0) as ticket_promedio,
        COUNT(DISTINCT id_evento) as total_eventos,
        COUNT(DISTINCT id_funcion) as total_funciones
    FROM ({$union_resumen}) as main";
    
    $resumen = $conn->query($sql_resumen)->fetch_assoc();

    // ============================================
    // 2. OCUPACIÓN DEL TEATRO
    // ============================================
    // 2a. Ocupación por función
    $queries_ocupacion = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where_vendido = $buildWhere('b');
        $queries_ocupacion[] = "
            SELECT 
                f.id_funcion,
                e.titulo as evento,
                DATE_FORMAT(f.fecha_hora, '%d/%m/%Y') as fecha,
                TIME_FORMAT(f.fecha_hora, '%H:%i') as hora,
                (SELECT COUNT(*) FROM {$db}.boletos b WHERE b.id_funcion = f.id_funcion AND b.estatus = 1) as vendidos,
                150 as capacidad
            FROM {$db}.funciones f
            JOIN {$db}.evento e ON f.id_evento = e.id_evento
            WHERE f.estado = 1
            " . ($id_evento ? "AND f.id_evento = {$id_evento}" : "") . "
        ";
    }
    $ocupacion_funcion = [];
    foreach ($queries_ocupacion as $q) {
        $res = $conn->query($q);
        if ($res) while ($row = $res->fetch_assoc()) {
            $row['porcentaje'] = $row['capacidad'] > 0 ? round(($row['vendidos'] / $row['capacidad']) * 100, 1) : 0;
            $ocupacion_funcion[] = $row;
        }
    }
    usort($ocupacion_funcion, fn($a, $b) => $b['porcentaje'] <=> $a['porcentaje']);

    // 2b. Ocupación por día de la semana
    $queries_dia = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where = $buildWhere('b');
        $queries_dia[] = "SELECT DAYOFWEEK(fecha_compra) as dia_num, precio_final FROM {$db}.boletos b WHERE {$where}";
    }
    $sql_dia = "SELECT 
        dia_num,
        CASE dia_num 
            WHEN 1 THEN 'Domingo' WHEN 2 THEN 'Lunes' WHEN 3 THEN 'Martes' 
            WHEN 4 THEN 'Miércoles' WHEN 5 THEN 'Jueves' WHEN 6 THEN 'Viernes' WHEN 7 THEN 'Sábado'
        END as dia,
        COUNT(*) as cantidad,
        SUM(precio_final) as ingresos
    FROM (" . implode(" UNION ALL ", $queries_dia) . ") as d
    GROUP BY dia_num ORDER BY dia_num";
    
    $por_dia = [];
    $res = $conn->query($sql_dia);
    while ($row = $res->fetch_assoc()) $por_dia[] = $row;

    // 2c. Ocupación por horario (matiné, tarde, noche)
    $queries_horario = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where = $buildWhere('b');
        $queries_horario[] = "SELECT HOUR(fecha_compra) as hora, precio_final FROM {$db}.boletos b WHERE {$where}";
    }
    $sql_horario = "SELECT 
        CASE 
            WHEN hora >= 6 AND hora < 12 THEN 'Matiné (6-12h)'
            WHEN hora >= 12 AND hora < 18 THEN 'Tarde (12-18h)'
            ELSE 'Noche (18-6h)'
        END as franja,
        COUNT(*) as cantidad,
        SUM(precio_final) as ingresos
    FROM (" . implode(" UNION ALL ", $queries_horario) . ") as h
    GROUP BY franja ORDER BY MIN(hora)";
    
    $por_horario = [];
    $res = $conn->query($sql_horario);
    while ($row = $res->fetch_assoc()) $por_horario[] = $row;

    // ============================================
    // 3. INGRESOS DETALLADOS
    // ============================================
    // 3a. Por período (día/semana/mes)
    $queries_periodo = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where = $buildWhere('b');
        $queries_periodo[] = "SELECT DATE(fecha_compra) as fecha, precio_final FROM {$db}.boletos b WHERE {$where}";
    }
    
    // Por día
    $sql_dia_ingresos = "SELECT fecha, SUM(precio_final) as ingresos, COUNT(*) as boletos
        FROM (" . implode(" UNION ALL ", $queries_periodo) . ") as p
        GROUP BY fecha ORDER BY fecha DESC LIMIT 30";
    $ingresos_diarios = [];
    $res = $conn->query($sql_dia_ingresos);
    while ($row = $res->fetch_assoc()) $ingresos_diarios[] = $row;

    // Por mes
    $sql_mes_ingresos = "SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(precio_final) as ingresos, COUNT(*) as boletos
        FROM (" . implode(" UNION ALL ", $queries_periodo) . ") as p
        GROUP BY mes ORDER BY mes DESC LIMIT 12";
    $ingresos_mensuales = [];
    $res = $conn->query($sql_mes_ingresos);
    while ($row = $res->fetch_assoc()) $ingresos_mensuales[] = $row;

    // 3b. Por tipo de boleto
    $queries_tipo = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where = $buildWhere('b');
        $queries_tipo[] = "SELECT COALESCE(tipo_boleto, 'Normal') as tipo, precio_final FROM {$db}.boletos b WHERE {$where}";
    }
    $sql_tipo = "SELECT tipo, COUNT(*) as cantidad, SUM(precio_final) as ingresos, AVG(precio_final) as promedio
        FROM (" . implode(" UNION ALL ", $queries_tipo) . ") as t
        GROUP BY tipo ORDER BY ingresos DESC";
    $por_tipo = [];
    $res = $conn->query($sql_tipo);
    while ($row = $res->fetch_assoc()) $por_tipo[] = $row;

    // 3c. Por categoría
    $queries_cat = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where = $buildWhere('b');
        $queries_cat[] = "SELECT c.nombre_categoria, b.precio_final FROM {$db}.boletos b JOIN {$db}.categorias c ON b.id_categoria = c.id_categoria WHERE {$where}";
    }
    $sql_cat = "SELECT nombre_categoria, COUNT(*) as cantidad, SUM(precio_final) as ingresos
        FROM (" . implode(" UNION ALL ", $queries_cat) . ") as c
        GROUP BY nombre_categoria ORDER BY ingresos DESC";
    $por_categoria = [];
    $res = $conn->query($sql_cat);
    while ($row = $res->fetch_assoc()) $por_categoria[] = $row;

    // ============================================
    // 4. RENDIMIENTO DE OBRAS (EVENTOS)
    // ============================================
    $queries_eventos = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where = $buildWhere('b');
        $queries_eventos[] = "SELECT e.id_evento, e.titulo, b.precio_final, b.id_funcion
            FROM {$db}.boletos b 
            JOIN {$db}.evento e ON b.id_evento = e.id_evento
            WHERE {$where}";
    }
    $sql_eventos = "SELECT titulo, COUNT(*) as boletos, SUM(precio_final) as ingresos, COUNT(DISTINCT id_funcion) as funciones
        FROM (" . implode(" UNION ALL ", $queries_eventos) . ") as ev
        GROUP BY titulo ORDER BY ingresos DESC";
    $ranking_eventos = [];
    $res = $conn->query($sql_eventos);
    $rank = 1;
    while ($row = $res->fetch_assoc()) {
        $row['rank'] = $rank++;
        $row['promedio_funcion'] = $row['funciones'] > 0 ? round($row['ingresos'] / $row['funciones'], 2) : 0;
        $ranking_eventos[] = $row;
    }

    // Alertas: eventos con baja asistencia (< 30% de capacidad promedio)
    $alertas_eventos = array_filter($ocupacion_funcion, fn($f) => $f['porcentaje'] < 30);

    // ============================================
    // 5. TENDENCIAS EN EL TIEMPO
    // ============================================
    // Ingresos por hora del día
    $queries_hora = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where = $buildWhere('b');
        $queries_hora[] = "SELECT HOUR(fecha_compra) as hora, precio_final FROM {$db}.boletos b WHERE {$where}";
    }
    $sql_hora = "SELECT hora, COUNT(*) as cantidad, SUM(precio_final) as ingresos
        FROM (" . implode(" UNION ALL ", $queries_hora) . ") as h
        GROUP BY hora ORDER BY hora";
    $por_hora = [];
    $res = $conn->query($sql_hora);
    while ($row = $res->fetch_assoc()) $por_hora[] = $row;

    // ============================================
    // 6. VENTAS DE BOLETOS
    // ============================================
    // Boletos vendidos vs disponibles (para eventos activos)
    $capacidad_total = 0;
    $vendidos_total = 0;
    
    $queries_capacidad = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        // Capacidad total
        $sql_cap = "SELECT COALESCE(SUM(asientos), 0) as cap FROM (
            SELECT COUNT(*) as asientos FROM {$db}.asiento_funcion GROUP BY id_funcion
        ) as af";
        $res_cap = $conn->query($sql_cap);
        if ($res_cap) $capacidad_total += (int)$res_cap->fetch_assoc()['cap'];
        
        // Vendidos
        $where = $buildWhere('b');
        $sql_v = "SELECT COUNT(*) as vendidos FROM {$db}.boletos b WHERE {$where}";
        $res_v = $conn->query($sql_v);
        if ($res_v) $vendidos_total += (int)$res_v->fetch_assoc()['vendidos'];
    }

    $ventas_resumen = [
        'vendidos' => $vendidos_total,
        'disponibles' => max(0, $capacidad_total - $vendidos_total),
        'capacidad' => $capacidad_total,
        'porcentaje_ocupacion' => $capacidad_total > 0 ? round(($vendidos_total / $capacidad_total) * 100, 1) : 0
    ];

    // Cancelaciones
    $queries_cancelados = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where_base = [];
        if ($id_evento) $where_base[] = "id_evento = {$id_evento}";
        if ($fecha_desde) $where_base[] = "fecha_compra >= '{$fecha_desde} 00:00:00'";
        if ($fecha_hasta) $where_base[] = "fecha_compra <= '{$fecha_hasta} 23:59:59'";
        $where_cancelado = "estatus = 0" . (count($where_base) > 0 ? " AND " . implode(" AND ", $where_base) : "");
        $queries_cancelados[] = "SELECT COUNT(*) as cancelados FROM {$db}.boletos WHERE {$where_cancelado}";
    }
    $cancelados_total = 0;
    foreach ($queries_cancelados as $q) {
        $res = $conn->query($q);
        if ($res) $cancelados_total += (int)$res->fetch_assoc()['cancelados'];
    }

    // ============================================
    // 7. TOP VENDEDORES
    // ============================================
    $queries_vendedores = [];
    foreach (($db_mode === 'ambas' ? [$db_actual, $db_historico] : [$db_mode === 'historico' ? $db_historico : $db_actual]) as $db) {
        $where = $buildWhere('b');
        $queries_vendedores[] = "SELECT CONCAT(u.nombre, ' ', u.apellido) as vendedor, b.precio_final
            FROM {$db}.boletos b 
            LEFT JOIN {$db}.usuarios u ON b.id_usuario = u.id_usuario
            WHERE {$where}";
    }
    $sql_vendedores = "SELECT COALESCE(vendedor, 'Sistema') as vendedor, COUNT(*) as boletos, SUM(precio_final) as ingresos
        FROM (" . implode(" UNION ALL ", $queries_vendedores) . ") as v
        GROUP BY vendedor ORDER BY ingresos DESC LIMIT 10";
    $top_vendedores = [];
    $res = $conn->query($sql_vendedores);
    while ($row = $res->fetch_assoc()) $top_vendedores[] = $row;

    // ============================================
    // RESPUESTA FINAL
    // ============================================
    echo json_encode([
        'success' => true,
        'data' => [
            // KPIs principales
            'resumen' => [
                'total_ingresos' => (float)$resumen['total_ingresos'],
                'total_boletos' => (int)$resumen['total_boletos'],
                'ticket_promedio' => (float)$resumen['ticket_promedio'],
                'total_eventos' => (int)$resumen['total_eventos'],
                'total_funciones' => (int)$resumen['total_funciones']
            ],
            
            // Ocupación
            'ocupacion' => [
                'por_funcion' => array_slice($ocupacion_funcion, 0, 10),
                'por_dia' => $por_dia,
                'por_horario' => $por_horario,
                'general' => $ventas_resumen
            ],
            
            // Ingresos
            'ingresos' => [
                'diarios' => array_reverse($ingresos_diarios),
                'mensuales' => array_reverse($ingresos_mensuales),
                'por_tipo' => $por_tipo,
                'por_categoria' => $por_categoria
            ],
            
            // Rendimiento de obras
            'rendimiento' => [
                'ranking' => $ranking_eventos,
                'alertas' => array_slice(array_values($alertas_eventos), 0, 5)
            ],
            
            // Tendencias
            'tendencias' => [
                'por_hora' => $por_hora
            ],
            
            // Ventas
            'ventas' => [
                'resumen' => $ventas_resumen,
                'cancelados' => $cancelados_total
            ],
            
            // Top vendedores
            'vendedores' => $top_vendedores
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
