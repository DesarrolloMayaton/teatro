<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}
require_once '../../evt_interfaz/conexion.php';

$eventos_filtro = [];
$res_ev = $conn->query("SELECT id_evento, titulo FROM evento ORDER BY id_evento DESC");
while ($r = $res_ev->fetch_assoc()) $eventos_filtro[] = $r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Premium - Teatro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" integrity="sha512-dPXYcDub/aeb08c63jRq/k6GqJ6SZlWgIz2NNZZiP9RXXpR6+8E/gVBbBQs8rY7xMz5p5yUB78/5Q1xQHcGQ4g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="premium_dashboard.css">
</head>
<body class="p-4">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px;">
                    <i class="bi bi-graph-up-arrow me-2" style="color: var(--primary-light);"></i>
                    Centro de Estadísticas
                </h2>
                <p class="text-secondary mb-0">Análisis completo de rendimiento del teatro</p>
            </div>
            <div class="d-flex gap-3 align-items-center">
                <span class="live-badge" id="liveBadge">
                    <span class="live-dot"></span>
                    EN VIVO
                </span>
                <button class="btn-premium primary" onclick="cargarEstadisticas()">
                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                </button>
                <button class="btn-premium danger" onclick="descargarPDF()">
                    <i class="bi bi-file-pdf"></i> PDF
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="glass-card p-4 mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label text-secondary small fw-bold">
                        <i class="bi bi-database me-1"></i> Base de Datos
                    </label>
                    <select id="statsDB" class="form-select input-premium">
                        <option value="actual">📊 Actual</option>
                        <option value="historico">📁 Histórico</option>
                        <option value="ambas">📦 Ambas</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-secondary small fw-bold">
                        <i class="bi bi-calendar me-1"></i> Desde
                    </label>
                    <input type="date" id="statsDesde" class="form-control input-premium">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-secondary small fw-bold">
                        <i class="bi bi-calendar-check me-1"></i> Hasta
                    </label>
                    <input type="date" id="statsHasta" class="form-control input-premium">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-bold">
                        <i class="bi bi-film me-1"></i> Evento
                    </label>
                    <select id="statsEvento" class="form-select input-premium">
                        <option value="">🎭 Todos los Eventos</option>
                        <?php foreach($eventos_filtro as $ev): ?>
                            <option value="<?= $ev['id_evento'] ?>"><?= htmlspecialchars($ev['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <button class="btn-premium primary" onclick="cargarEstadisticas()">
                        <i class="bi bi-funnel-fill"></i> Aplicar Filtros
                    </button>
                </div>
            </div>
        </div>

        <!-- KPIs Row 1 -->
        <div class="row g-4 mb-4">
            <div class="col-md-2">
                <div class="kpi-card kpi-ingresos animate-in delay-1">
                    <div class="kpi-label">Ingresos Totales</div>
                    <div class="kpi-value text-white" id="kpiIngresos">$0</div>
                    <i class="bi bi-currency-dollar kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <div class="kpi-card kpi-boletos animate-in delay-2">
                    <div class="kpi-label">Boletos Vendidos</div>
                    <div class="kpi-value text-white" id="kpiBoletos">0</div>
                    <i class="bi bi-ticket-perforated kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <div class="kpi-card kpi-promedio animate-in delay-3">
                    <div class="kpi-label">Ticket Promedio</div>
                    <div class="kpi-value text-white" id="kpiPromedio">$0</div>
                    <i class="bi bi-graph-up kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <div class="kpi-card kpi-ocupacion animate-in delay-4">
                    <div class="kpi-label">Ocupación</div>
                    <div class="kpi-value text-white" id="kpiOcupacion">0%</div>
                    <i class="bi bi-people-fill kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <div class="kpi-card kpi-eventos animate-in delay-5">
                    <div class="kpi-label">Eventos Activos</div>
                    <div class="kpi-value text-white" id="kpiEventos">0</div>
                    <i class="bi bi-calendar-star kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <div class="kpi-card kpi-funciones animate-in delay-5">
                    <div class="kpi-label">Funciones</div>
                    <div class="kpi-value text-white" id="kpiFunciones">0</div>
                    <i class="bi bi-collection-play kpi-icon"></i>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <!-- Ventas por Hora -->
            <div class="col-lg-5">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-clock-history"></i>
                        <h6>Actividad por Hora</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartHora"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Por Día de Semana -->
            <div class="col-lg-4">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-calendar-week"></i>
                        <h6>Ventas por Día</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartDia"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Por Horario -->
            <div class="col-lg-3">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-sun"></i>
                        <h6>Por Franja Horaria</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartHorario"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div class="row g-4 mb-4">
            <!-- Categorías -->
            <div class="col-lg-3">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-pie-chart-fill"></i>
                        <h6>Por Categoría</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartCategoria"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tipo Boleto -->
            <div class="col-lg-3">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-tags-fill"></i>
                        <h6>Por Tipo de Boleto</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartTipo"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tendencia Mensual -->
            <div class="col-lg-6">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-graph-up-arrow"></i>
                        <h6>Tendencia de Ingresos</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartTendencia"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Third Row - Tables -->
        <div class="row g-4 mb-4">
            <!-- Ranking Eventos -->
            <div class="col-lg-6">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-trophy-fill" style="color: #fbbf24;"></i>
                        <h6>Ranking de Obras</h6>
                    </div>
                    <div class="p-0">
                        <table class="ranking-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Evento</th>
                                    <th class="text-center">Boletos</th>
                                    <th class="text-center">Funciones</th>
                                    <th class="text-end">Ingresos</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyRanking"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Top Vendedores -->
            <div class="col-lg-3">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-person-badge-fill" style="color: #10b981;"></i>
                        <h6>Top Vendedores</h6>
                    </div>
                    <div class="p-3" id="containerVendedores"></div>
                </div>
            </div>
            
            <!-- Alertas y Ocupación -->
            <div class="col-lg-3">
                <div class="glass-card h-100">
                    <div class="section-header">
                        <i class="bi bi-exclamation-triangle-fill" style="color: #f59e0b;"></i>
                        <h6>Alertas</h6>
                    </div>
                    <div class="p-3" id="containerAlertas">
                        <p class="text-muted text-center small">Sin alertas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ocupación por Función -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="glass-card">
                    <div class="section-header">
                        <i class="bi bi-person-lines-fill" style="color: #22d3ee;"></i>
                        <h6>Ocupación por Función</h6>
                    </div>
                    <div class="p-3">
                        <div class="row g-3" id="containerOcupacion"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart instances
        let charts = {};
        const colors = {
            primary: '#6366f1', accent: '#22d3ee', purple: '#a855f7',
            pink: '#ec4899', emerald: '#10b981', amber: '#f59e0b',
            rose: '#f43f5e', blue: '#3b82f6'
        };

        document.addEventListener('DOMContentLoaded', () => {
            cargarEstadisticas();
            setInterval(cargarEstadisticas, 30000);
        });

        async function cargarEstadisticas() {
            const params = new URLSearchParams({
                db: document.getElementById('statsDB').value,
                id_evento: document.getElementById('statsEvento').value,
                fecha_desde: document.getElementById('statsDesde').value,
                fecha_hasta: document.getElementById('statsHasta').value
            });

            try {
                const res = await fetch(`api_stats_premium.php?${params}`);
                const r = await res.json();
                if (!r.success) throw new Error(r.error);
                
                const d = r.data;
                
                // KPIs
                animateKPI('kpiIngresos', d.resumen.total_ingresos, '$');
                animateKPI('kpiBoletos', d.resumen.total_boletos);
                animateKPI('kpiPromedio', d.resumen.ticket_promedio, '$');
                animateKPI('kpiOcupacion', d.ocupacion.general.porcentaje_ocupacion, '', '%');
                animateKPI('kpiEventos', d.resumen.total_eventos);
                animateKPI('kpiFunciones', d.resumen.total_funciones);
                
                // Charts
                renderChartHora(d.tendencias.por_hora);
                renderChartDia(d.ocupacion.por_dia);
                renderChartHorario(d.ocupacion.por_horario);
                renderChartCategoria(d.ingresos.por_categoria);
                renderChartTipo(d.ingresos.por_tipo);
                renderChartTendencia(d.ingresos.mensuales);
                
                // Tables
                renderRanking(d.rendimiento.ranking);
                renderVendedores(d.vendedores);
                renderAlertas(d.rendimiento.alertas);
                renderOcupacion(d.ocupacion.por_funcion);
                
            } catch (e) {
                console.error('Error:', e);
            }
        }

        function animateKPI(id, value, prefix = '', suffix = '') {
            const el = document.getElementById(id);
            const formatted = typeof value === 'number' ? 
                (prefix === '$' ? value.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 
                value.toLocaleString('es-MX')) : value;
            el.textContent = prefix + formatted + suffix;
        }

        function renderChartHora(data) {
            const ctx = document.getElementById('chartHora');
            if (charts.hora) charts.hora.destroy();
            
            const labels = Array.from({length: 24}, (_, i) => `${i}:00`);
            const values = new Array(24).fill(0);
            data.forEach(d => values[parseInt(d.hora)] = parseFloat(d.ingresos));
            
            charts.hora = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Ingresos',
                        data: values,
                        backgroundColor: colors.primary,
                        borderRadius: 4
                    }]
                },
                options: chartOptions()
            });
        }

        function renderChartDia(data) {
            const ctx = document.getElementById('chartDia');
            if (charts.dia) charts.dia.destroy();
            
            charts.dia = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.dia),
                    datasets: [{
                        label: 'Ingresos',
                        data: data.map(d => parseFloat(d.ingresos)),
                        backgroundColor: [colors.rose, colors.amber, colors.emerald, colors.accent, colors.purple, colors.primary, colors.pink]
                    }]
                },
                options: chartOptions()
            });
        }

        function renderChartHorario(data) {
            const ctx = document.getElementById('chartHorario');
            if (charts.horario) charts.horario.destroy();
            
            charts.horario = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.franja),
                    datasets: [{
                        data: data.map(d => d.cantidad),
                        backgroundColor: [colors.amber, colors.accent, colors.purple]
                    }]
                },
                options: { ...chartOptions(), cutout: '60%' }
            });
        }

        function renderChartCategoria(data) {
            const ctx = document.getElementById('chartCategoria');
            if (charts.categoria) charts.categoria.destroy();
            
            charts.categoria = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.nombre_categoria),
                    datasets: [{
                        data: data.map(d => d.cantidad),
                        backgroundColor: [colors.accent, colors.purple, colors.pink, colors.emerald, colors.amber]
                    }]
                },
                options: { ...chartOptions(), cutout: '55%' }
            });
        }

        function renderChartTipo(data) {
            const ctx = document.getElementById('chartTipo');
            if (charts.tipo) charts.tipo.destroy();
            
            charts.tipo = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.map(d => d.tipo),
                    datasets: [{
                        data: data.map(d => d.cantidad),
                        backgroundColor: [colors.emerald, colors.rose, colors.blue, colors.amber]
                    }]
                },
                options: chartOptions()
            });
        }

        function renderChartTendencia(data) {
            const ctx = document.getElementById('chartTendencia');
            if (charts.tendencia) charts.tendencia.destroy();
            
            charts.tendencia = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.mes),
                    datasets: [{
                        label: 'Ingresos',
                        data: data.map(d => parseFloat(d.ingresos)),
                        borderColor: colors.primary,
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: chartOptions()
            });
        }

        function chartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { color: '#94a3b8', padding: 15, usePointStyle: true }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#64748b' }
                    },
                    x: { 
                        grid: { display: false },
                        ticks: { color: '#64748b', maxTicksLimit: 12 }
                    }
                }
            };
        }

        function renderRanking(data) {
            const tbody = document.getElementById('tbodyRanking');
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Sin datos</td></tr>';
                return;
            }
            tbody.innerHTML = data.slice(0, 10).map(ev => `
                <tr>
                    <td><span class="rank-badge ${ev.rank <= 3 ? 'rank-' + ev.rank : 'rank-default'}">${ev.rank}</span></td>
                    <td class="fw-semibold text-white">${ev.titulo}</td>
                    <td class="text-center"><span class="badge bg-secondary">${ev.boletos}</span></td>
                    <td class="text-center text-muted">${ev.funciones}</td>
                    <td class="text-end text-success fw-bold">$${parseFloat(ev.ingresos).toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
                </tr>
            `).join('');
        }

        function renderVendedores(data) {
            const container = document.getElementById('containerVendedores');
            if (!data.length) {
                container.innerHTML = '<p class="text-muted text-center small">Sin datos</p>';
                return;
            }
            container.innerHTML = data.slice(0, 5).map((v, i) => `
                <div class="vendedor-row">
                    <div class="vendedor-info">
                        <div class="vendedor-avatar">${v.vendedor.charAt(0)}</div>
                        <div>
                            <div class="fw-semibold text-white small">${v.vendedor}</div>
                            <div class="text-muted" style="font-size: 0.7rem;">${v.boletos} boletos</div>
                        </div>
                    </div>
                    <div class="text-success fw-bold">$${parseFloat(v.ingresos).toLocaleString('es-MX')}</div>
                </div>
            `).join('');
        }

        function renderAlertas(data) {
            const container = document.getElementById('containerAlertas');
            if (!data.length) {
                container.innerHTML = '<p class="text-muted text-center small py-3"><i class="bi bi-check-circle me-2"></i>Todo en orden</p>';
                return;
            }
            container.innerHTML = data.map(a => `
                <div class="alert-card ${a.porcentaje < 15 ? 'danger' : 'warning'}">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>
                        <div class="fw-semibold small">${a.evento}</div>
                        <div class="text-muted" style="font-size: 0.7rem;">${a.fecha} - Solo ${a.porcentaje}% ocupación</div>
                    </div>
                </div>
            `).join('');
        }

        function renderOcupacion(data) {
            const container = document.getElementById('containerOcupacion');
            if (!data.length) {
                container.innerHTML = '<p class="text-muted text-center col-12">Sin funciones activas</p>';
                return;
            }
            container.innerHTML = data.slice(0, 8).map(f => {
                const color = f.porcentaje >= 70 ? 'bg-success' : f.porcentaje >= 40 ? 'bg-warning' : 'bg-danger';
                return `
                    <div class="col-md-3">
                        <div class="p-3 rounded-3" style="background: rgba(0,0,0,0.3);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold text-white small text-truncate" style="max-width: 70%;">${f.evento}</span>
                                <span class="badge ${color}">${f.porcentaje}%</span>
                            </div>
                            <div class="text-muted mb-2" style="font-size: 0.7rem;">${f.fecha} - ${f.hora}</div>
                            <div class="progress-premium">
                                <div class="progress-bar ${color}" style="width: ${f.porcentaje}%"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-2" style="font-size: 0.7rem;">
                                <span class="text-muted">${f.vendidos} vendidos</span>
                                <span class="text-muted">${f.capacidad} total</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function descargarPDF() {
            const params = new URLSearchParams({
                db: document.getElementById('statsDB').value,
                id_evento: document.getElementById('statsEvento').value,
                fecha_desde: document.getElementById('statsDesde').value,
                fecha_hasta: document.getElementById('statsHasta').value
            });
            window.open(`generar_pdf.php?${params}`, '_blank');
        }
    </script>
</body>
</html>
