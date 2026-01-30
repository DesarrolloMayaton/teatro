<?php
// Generador de Reporte para Impresión/PDF
require_once '../../evt_interfaz/conexion.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado");
}

// Mismos parámetros que el dashboard
$db_mode = $_GET['db'] ?? 'actual';
$id_evento = isset($_GET['id_evento']) && $_GET['id_evento'] !== '' ? (int)$_GET['id_evento'] : null;
$fecha_desde = $_GET['fecha_desde'] ?? null;
$fecha_hasta = $_GET['fecha_hasta'] ?? null;

// Título del Reporte
$titulo_reporte = "Reporte General de Ventas";
if ($id_evento) {
    // Buscar nombre del evento
    $stmt = $conn->prepare("SELECT titulo FROM evento WHERE id_evento = ?");
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $titulo_reporte = "Reporte: " . $row['titulo'];
    }
}

// --- LOGICA DE DATOS (Duplicada simplificada de api_stats para renderizado server-side) ---
// En un sistema ideal refactorizaríamos esto a una clase Service, pero por ahora inline para velocidad.
$db_actual = 'trt_25';
$db_historico = 'trt_historico_evento';

$queries_cat = [];
$build_cat_query = function($db_name) use ($id_evento, $fecha_desde, $fecha_hasta) {
    $where = ["b.estatus = 'vendido'"];
    if ($id_evento) $where[] = "b.id_evento = $id_evento";
    if ($fecha_desde) $where[] = "b.fecha_compra >= '$fecha_desde 00:00:00'";
    if ($fecha_hasta) $where[] = "b.fecha_compra <= '$fecha_hasta 23:59:59'";
    $where_sql = implode(' AND ', $where);

    return "SELECT c.nombre_categoria, b.precio_final 
            FROM {$db_name}.boletos b 
            JOIN {$db_name}.categorias c ON b.id_categoria = c.id_categoria 
            WHERE $where_sql";
};

if ($db_mode === 'actual' || $db_mode === 'ambas') $queries_cat[] = $build_cat_query($db_actual);
if ($db_mode === 'historico' || $db_mode === 'ambas') $queries_cat[] = $build_cat_query($db_historico);

$sql_cat = "SELECT nombre_categoria, COUNT(*) as cantidad, SUM(precio_final) as total 
            FROM (" . implode(" UNION ALL ", $queries_cat) . ") as alias_cat 
            GROUP BY nombre_categoria 
            ORDER BY cantidad DESC";

$res_cat = $conn->query($sql_cat);
$categorias = [];
$total_gral = 0;
$boletos_gral = 0;
while($r = $res_cat->fetch_assoc()) {
    $categorias[] = $r;
    $total_gral += $r['total'];
    $boletos_gral += $r['cantidad'];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte PDF</title>
    <style>
        body { font-family: sans-serif; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .meta { font-size: 0.9em; color: #666; margin-bottom: 20px; }
        .stat-box { border: 1px solid #ddd; padding: 15px; display: inline-block; width: 30%; text-align: center; background: #f9f9f9; }
        .stat-label { font-size: 0.8em; text-transform: uppercase; color: #888; }
        .stat-value { font-size: 1.5em; font-weight: bold; color: #000; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body> <!-- onload="window.print()" -->

<div class="no-print" style="padding: 10px; background: #eee; border-bottom: 1px solid #ccc; text-align: right;">
    <button onclick="window.print()" style="padding: 10px 20px; font-weight: bold; cursor: pointer;">🖨️ Imprimir / Guardar como PDF</button>
</div>

<div class="header">
    <h1><?php echo htmlspecialchars($titulo_reporte); ?></h1>
    <p>Teatro Administration System</p>
</div>

<div class="meta">
    <strong>Fecha de Emisión:</strong> <?php echo date('d/m/Y H:i'); ?><br>
    <strong>Base de Datos:</strong> <?php echo ucfirst($db_mode); ?><br>
    <strong>Filtro Fecha:</strong> <?php echo $fecha_desde ? $fecha_desde : 'Inicio'; ?> al <?php echo $fecha_hasta ? $fecha_hasta : 'Actualidad'; ?>
</div>

<div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
    <div class="stat-box">
        <div class="stat-label">Ingresos Totales</div>
        <div class="stat-value">$<?php echo number_format($total_gral, 2); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Boletos Vendidos</div>
        <div class="stat-value"><?php echo number_format($boletos_gral); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Promedio Ticket</div>
        <div class="stat-value">$<?php echo $boletos_gral > 0 ? number_format($total_gral / $boletos_gral, 2) : '0.00'; ?></div>
    </div>
</div>

<h3>Desglose por Categoría</h3>
<table>
    <thead>
        <tr>
            <th>Categoría</th>
            <th class="text-right">Cantidad</th>
            <th class="text-right">Total Generado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($categorias as $cat): ?>
        <tr>
            <td><?php echo htmlspecialchars($cat['nombre_categoria']); ?></td>
            <td class="text-right"><?php echo number_format($cat['cantidad']); ?></td>
            <td class="text-right">$<?php echo number_format($cat['total'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr style="font-weight: bold; background: #f9f9f9;">
            <td>TOTAL</td>
            <td class="text-right"><?php echo number_format($boletos_gral); ?></td>
            <td class="text-right">$<?php echo number_format($total_gral, 2); ?></td>
        </tr>
    </tfoot>
</table>

<div style="margin-top: 50px; border-top: 1px solid #ccc; padding-top: 10px; font-size: 0.8em; text-align: center; color: #888;">
    Reporte generado automáticamente por el sistema.
</div>

</body>
</html>
