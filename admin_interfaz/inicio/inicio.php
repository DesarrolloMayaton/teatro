<?php
// inicio.php (raíz)
// Dashboard de estadísticas para eventos, categorías y (opcional) promociones

// === Conexión ===
include "../../evt_interfaz/conexion.php"; 


// Helper para verificar existencia de tabla
function table_exists(mysqli $conn, string $table): bool {
    $dbResult = $conn->query("SELECT DATABASE() AS dbn");
    $dbName = $dbResult ? ($dbResult->fetch_assoc()['dbn'] ?? null) : null;
    if (!$dbName) return false;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    $stmt->bind_param('ss', $dbName, $table);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($res['c']);
}

$has_promos = table_exists($conn, 'promociones');

// === Métricas de eventos ===
$eventos = [];
$tot_eventos = 0;
$tot_eventos_activos = 0;
$tot_eventos_finalizados = 0;

$resE = $conn->query("SELECT id_evento, titulo, finalizado FROM evento ORDER BY titulo ASC");
if ($resE) {
    while ($r = $resE->fetch_assoc()) {
        $eventos[(int)$r['id_evento']] = [
            'id_evento' => (int)$r['id_evento'],
            'titulo' => $r['titulo'],
            'finalizado' => (int)$r['finalizado'],
            'categorias' => 0,
            'precio_prom' => null,
            'precio_min' => null,
            'precio_max' => null,
            'promos' => 0
        ];
        $tot_eventos++;
        if ((int)$r['finalizado'] === 0) $tot_eventos_activos++; else $tot_eventos_finalizados++;
    }
}

// === Métricas de categorías (por evento) ===
$tot_categorias = 0;
$resC = $conn->query("SELECT id_evento, COUNT(*) AS ncat, AVG(precio) AS pavg, MIN(precio) AS pmin, MAX(precio) AS pmax FROM categorias GROUP BY id_evento");
if ($resC) {
    while ($r = $resC->fetch_assoc()) {
        $id = (int)$r['id_evento'];
        if (isset($eventos[$id])) {
            $eventos[$id]['categorias'] = (int)$r['ncat'];
            $eventos[$id]['precio_prom'] = is_null($r['pavg']) ? null : (float)$r['pavg'];
            $eventos[$id]['precio_min']  = is_null($r['pmin']) ? null : (float)$r['pmin'];
            $eventos[$id]['precio_max']  = is_null($r['pmax']) ? null : (float)$r['pmax'];
            $tot_categorias += (int)$r['ncat'];
        }
    }
}

// === Métricas de promociones (si existe tabla) ===
$tot_promos = 0;
$tot_promos_activas_hoy = 0;
$tot_promos_globales = 0;
$promos_proximas = []; // próximas a vencer (7 días)
if ($has_promos) {
    $hoy = date('Y-m-d');

    // total promos
    $resP = $conn->query("SELECT COUNT(*) AS n FROM promociones");
    if ($resP) { $tot_promos = (int)$resP->fetch_assoc()['n']; }

    // activas hoy
    $sqlAct = "
        SELECT COUNT(*) AS n 
        FROM promociones 
        WHERE (activo = 1)
          AND (fecha_desde IS NULL OR DATE(fecha_desde) <= ?)
          AND (fecha_hasta IS NULL OR DATE(fecha_hasta) >= ?)
    ";
    $stmt = $conn->prepare($sqlAct);
    $stmt->bind_param('ss', $hoy, $hoy);
    $stmt->execute();
    $tot_promos_activas_hoy = (int)$stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    // globales (id_evento NULL)
    $resG = $conn->query("SELECT COUNT(*) AS n FROM promociones WHERE id_evento IS NULL");
    if ($resG) { $tot_promos_globales = (int)$resG->fetch_assoc()['n']; }

    // promos por evento (conteo)
    $resPE = $conn->query("SELECT id_evento, COUNT(*) AS n FROM promociones WHERE id_evento IS NOT NULL GROUP BY id_evento");
    if ($resPE) {
        while ($r = $resPE->fetch_assoc()) {
            $id = (int)$r['id_evento'];
            if (isset($eventos[$id])) $eventos[$id]['promos'] = (int)$r['n'];
        }
    }

    // próximas a vencer en 7 días
    $sqlSoon = "
        SELECT id_promocion, nombre, id_evento, fecha_hasta, modo_calculo, valor
        FROM promociones
        WHERE fecha_hasta IS NOT NULL 
          AND DATE(fecha_hasta) >= ?
          AND DATE(fecha_hasta) <= DATE_ADD(?, INTERVAL 7 DAY)
        ORDER BY fecha_hasta ASC
        LIMIT 12
    ";
    $stmt = $conn->prepare($sqlSoon);
    $stmt->bind_param('ss', $hoy, $hoy);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($r = $rs->fetch_assoc()) {
        $evTitle = 'Global (Todos)';
        if (!empty($r['id_evento']) && isset($eventos[(int)$r['id_evento']])) {
            $evTitle = $eventos[(int)$r['id_evento']]['titulo'];
        }
        $promos_proximas[] = [
            'id_promocion' => (int)$r['id_promocion'],
            'nombre' => $r['nombre'],
            'evento' => $evTitle,
            'fecha_hasta' => substr($r['fecha_hasta'], 0, 10),
            'modo' => $r['modo_calculo'],
            'valor' => is_null($r['valor']) ? null : (float)$r['valor'],
        ];
    }
    $stmt->close();
}

$conn->close();

// Preparar datos para JS
$eventos_list = array_values($eventos);
?>
<!DOCTYPE html>
<html lang="es">
<head>


    <meta charset="UTF-8">
    <title>Inicio • Estadísticas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 + Icons (consistente con tus otras vistas) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --primary-color: #2563eb; --primary-dark: #1e40af;
            --success-color: #10b981; --danger-color: #ef4444;
            --warning-color: #f59e0b; --info-color: #3b82f6;
            --bg-primary: #f8fafc; --bg-secondary: #ffffff;
            --text-primary: #0f172a; --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,.1);
            --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, var(--bg-primary), #e2e8f0);
            color: var(--text-primary);
            padding: 20px;
            min-height: 100vh;
        }
        .container-fluid { max-width: 1400px; margin: 0 auto; }
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }
        .card:hover { box-shadow: var(--shadow-lg); }
        h2, h3 { font-weight: 700; letter-spacing: -0.5px; margin-bottom: .5rem; }
        .muted { color: var(--text-secondary); font-size: .9rem; }
        .kpi {
            display: flex; align-items: center; gap: 14px;
        }
        .kpi .icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: grid; place-items: center; font-size: 1.25rem;
        }
        .table thead { background: var(--bg-primary); }
        .badge-soft { background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); }
        .list-group-item { border-color: var(--border-color); }
        .empty {
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px; text-align: center; color: var(--text-secondary);
        }
        .footnote { color: var(--text-secondary); font-size: .8rem; }

        /* === FIX DE GRÁFICAS: contenedor controla la altura === */
        .chart-box{
            position: relative;
            width: 100%;
            height: 320px;       /* Ajusta aquí el alto visual */
        }
        @media (max-width: 992px){
            .chart-box{ height: 280px; }
        }
        canvas { background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-md); }
    </style>
</head>
<body>

<div class="container-fluid">

    <!-- TÍTULO -->
    <div class="mb-4">
        <h2 class="text-primary d-flex align-items-center gap-2">
            <i class="bi bi-house-door-fill"></i>
            Inicio · Estadísticas
        </h2>
        <div class="muted">Resumen general de tus eventos, categorías y promociones.</div>
    </div>

    <!-- KPIs -->
    <div class="row g-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3">
                <div class="kpi">
                    <div class="icon" style="background:#dbeafe;color:#1d4ed8"><i class="bi bi-collection"></i></div>
                    <div>
                        <div class="muted text-uppercase">Eventos (Total)</div>
                        <div class="h4 mb-0"><?php echo number_format($tot_eventos); ?></div>
                        <span class="badge badge-soft mt-1"><?php echo number_format($tot_eventos_activos); ?> activos · <?php echo number_format($tot_eventos_finalizados); ?> finalizados</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3">
                <div class="kpi">
                    <div class="icon" style="background:#dcfce7;color:#166534"><i class="bi bi-tags-fill"></i></div>
                    <div>
                        <div class="muted text-uppercase">Categorías (Total)</div>
                        <div class="h4 mb-0"><?php echo number_format($tot_categorias); ?></div>
                        <span class="badge badge-soft mt-1">Prom · Min · Max por evento</span>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($has_promos): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3">
                <div class="kpi">
                    <div class="icon" style="background:#fee2e2;color:#991b1b"><i class="bi bi-percent"></i></div>
                    <div>
                        <div class="muted text-uppercase">Promociones (Total)</div>
                        <div class="h4 mb-0"><?php echo number_format($tot_promos); ?></div>
                        <span class="badge badge-soft mt-1"><?php echo number_format($tot_promos_activas_hoy); ?> activas hoy · <?php echo number_format($tot_promos_globales); ?> globales</span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3">
                <div class="kpi">
                    <div class="icon" style="background:#fef9c3;color:#854d0e"><i class="bi bi-percent"></i></div>
                    <div>
                        <div class="muted text-uppercase">Promociones</div>
                        <div class="h6 mb-0">No se encontró la tabla <code>promociones</code></div>
                        <span class="badge badge-soft mt-1">Se omitieron KPIs de promos</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3">
                <div class="kpi">
                    <div class="icon" style="background:#ede9fe;color:#5b21b6"><i class="bi bi-graph-up"></i></div>
                    <div>
                        <div class="muted text-uppercase">Promedio (MXN)</div>
                        <div class="h4 mb-0">
                            <?php
                            // promedio global de promedios (solo eventos con categorías)
                            $promedios = array_values(array_filter(array_map(fn($e)=>$e['precio_prom'], $eventos_list), fn($v)=>$v!==null));
                            echo count($promedios) ? number_format(array_sum($promedios)/count($promedios), 2) : '—';
                            ?>
                        </div>
                        <span class="badge badge-soft mt-1">Precios de categorías</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILA: Gráficos -->
    <div class="row g-3 mt-1">
        <div class="col-lg-7">
            <div class="card p-3">
                <h3 class="mb-2"><i class="bi bi-bar-chart"></i> Precio promedio por evento</h3>
                <div class="muted mb-3">Comparativa del precio promedio de categorías por evento.</div>
                <div class="chart-box"><canvas id="chartPromedios"></canvas></div>
                <div class="footnote mt-2">Solo se consideran eventos con al menos una categoría.</div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card p-3">
                <h3 class="mb-2"><i class="bi bi-pie-chart"></i> Estado de eventos</h3>
                <div class="muted mb-3">Distribución de eventos activos vs finalizados.</div>
                <div class="chart-box"><canvas id="chartEventos"></canvas></div>
            </div>
        </div>
    </div>

    <!-- FILA: Tabla resumen + Próximas a vencer -->
    <div class="row g-3 mt-1">
        <div class="col-lg-8">
            <div class="card p-3">
                <h3 class="mb-2"><i class="bi bi-table"></i> Resumen por evento</h3>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Evento</th>
                                <th class="text-center">Categorías</th>
                                <th class="text-end">Promedio</th>
                                <th class="text-end">Mínimo</th>
                                <th class="text-end">Máximo</th>
                                <?php if ($has_promos): ?><th class="text-center">Promos</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $tieneFilas = false;
                        foreach ($eventos_list as $e):
                            $tieneFilas = true;
                            $prom = $e['precio_prom']; $min = $e['precio_min']; $max = $e['precio_max'];
                        ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?php echo htmlspecialchars($e['titulo']); ?>
                                    <?php if ((int)$e['finalizado'] === 1): ?>
                                        <span class="badge bg-secondary ms-2">Finalizado</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success ms-2">Activo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo number_format((int)$e['categorias']); ?></td>
                                <td class="text-end"><?php echo is_null($prom) ? '—' : '$'.number_format($prom, 2); ?></td>
                                <td class="text-end"><?php echo is_null($min)  ? '—' : '$'.number_format($min, 2); ?></td>
                                <td class="text-end"><?php echo is_null($max)  ? '—' : '$'.number_format($max, 2); ?></td>
                                <?php if ($has_promos): ?><td class="text-center"><?php echo number_format((int)$e['promos']); ?></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$tieneFilas): ?>
                            <tr><td colspan="<?php echo $has_promos ? 6 : 5; ?>"><div class="empty">Aún no hay eventos.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="footnote">Consejo: usa <em>Categorías</em> para definir precios base, y <em>Descuentos</em> para reglas dinámicas.</div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-3">
                <h3 class="mb-2"><i class="bi bi-clock-history"></i> Próximas a vencer</h3>
                <?php if ($has_promos): ?>
                    <?php if (count($promos_proximas)): ?>
                        <ul class="list-group">
                        <?php foreach ($promos_proximas as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="me-2">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($p['nombre']); ?></div>
                                    <div class="muted small">
                                        <?php echo htmlspecialchars($p['evento']); ?> · Vence: 
                                        <strong><?php echo htmlspecialchars($p['fecha_hasta']); ?></strong>
                                        <?php if ($p['valor'] !== null): ?>
                                            · <?php echo ($p['modo']==='porcentaje' ? number_format($p['valor']).'%' : '$'.number_format($p['valor'],2)); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge bg-warning text-dark">¡Atención!</span>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty">No hay promociones próximas a vencer en los próximos 7 días.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty">La tabla <code>promociones</code> no está disponible.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Nota inferior -->
    <div class="mt-3 footnote">
        * Si agregas o cambias datos en <em>Categorías</em> o <em>Descuentos</em>, vuelve a esta vista para ver las métricas actualizadas.
    </div>

</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Datos desde PHP
const EVENTOS = <?php echo json_encode($eventos_list, JSON_UNESCAPED_UNICODE); ?>;
const KPI = {
    tot_eventos: <?php echo (int)$tot_eventos; ?>,
    activos: <?php echo (int)$tot_eventos_activos; ?>,
    finalizados: <?php echo (int)$tot_eventos_finalizados; ?>
};

// Utilidad: destruir instancia previa si recargas dentro del iframe
function ensureDestroy(canvas) {
  const inst = window.Chart && Chart.getChart ? Chart.getChart(canvas) : null;
  if (inst) inst.destroy();
}

// === Chart: Precio promedio por evento (responsive sin loop) ===
(() => {
    const canvas = document.getElementById('chartPromedios');
    if (!canvas) return;

    const rows = (Array.isArray(EVENTOS) ? EVENTOS : [])
      .filter(e => e && e.precio_prom !== null && e.precio_prom !== undefined && !isNaN(parseFloat(e.precio_prom)));

    if (!rows.length) return;

    ensureDestroy(canvas);
    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: rows.map(e => String(e.titulo)),
            datasets: [{
                label: 'Precio promedio (MXN)',
                data: rows.map(e => Math.round(parseFloat(e.precio_prom) * 100) / 100),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // el alto lo define .chart-box
            animation: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { position: 'bottom' } }
        }
    });
})();

// === Chart: Activos vs Finalizados (responsive sin loop) ===
(() => {
    const canvas = document.getElementById('chartEventos');
    if (!canvas) return;

    const act = Number(KPI.activos || 0);
    const fin = Number(KPI.finalizados || 0);
    if (act + fin === 0) return;

    ensureDestroy(canvas);
    new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Activos', 'Finalizados'],
            datasets: [{ data: [act, fin] }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // el alto lo define .chart-box
            animation: false,
            cutout: '60%',
            plugins: { legend: { position: 'bottom' } }
        }
    });
})();
</script>
</body>
</html>
