<?php
/* =========================
   ADMIN - TRANSACCIONES (Consolidado & Mejorado)
   - Búsqueda en Vivo
   - Alto Contraste
   - Detalle Completo (Cliente, Evento, Asientos)
   ========================= */

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}
$es_admin = ($_SESSION['usuario_rol'] === 'admin' || (isset($_SESSION['admin_verificado']) && $_SESSION['admin_verificado']));
require_once '../../evt_interfaz/conexion.php';

// Carga inicial de filtros para estadísticas
$eventos_filtro = [];
$res_ev = $conn->query("SELECT id_evento, titulo FROM evento ORDER BY id_evento DESC");
while ($r = $res_ev->fetch_assoc()) $eventos_filtro[] = $r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Transacciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --bs-body-bg: #131313;
            --bs-body-color: #e0e0e0;
            --card-bg: #1e1e1e;
            --input-bg: #2b2b2b;
            --border-color: #444;
            --primary: #6366f1; /* Indigo más brillante */
            --accent: #22d3ee; /* Cian brillante */
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; padding-top: 1rem; }
        
        /* High Contrast Card */
        .card { 
            background-color: var(--card-bg); 
            border: 1px solid var(--border-color); 
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }

        /* Inputs Search */
        .form-control, .form-select { 
            background-color: #000; 
            border: 1px solid #555; 
            color: #fff; 
            font-size: 0.95rem;
        }
        .form-control:focus { 
            background-color: #111; 
            border-color: var(--primary); 
            color: #fff; 
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3); 
        }

        /* Table Enhanced */
        .table-custom {
            width: 100%;
            border-collapse: separate; 
            border-spacing: 0;
            margin-bottom: 0;
        }
        .table-custom thead th {
            background-color: #2d2d2d;
            color: #fff;
            padding: 12px 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--primary);
        }
        .table-custom tbody tr {
            background-color: #1a1a1a;
            color: #ddd;
            transition: all 0.2s;
        }
        .table-custom tbody tr:nth-child(even) {
            background-color: #222;
        }
        .table-custom tbody tr:hover {
            background-color: #333;
            cursor: pointer;
            color: #fff;
        }
        .table-custom td {
            padding: 12px 15px;
            border-bottom: 1px solid #333;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        /* Badges */
        .badge-client {
            background: rgba(34, 211, 238, 0.1);
            color: #22d3ee;
            border: 1px solid rgba(34, 211, 238, 0.3);
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        .badge-event {
            background: rgba(168, 85, 247, 0.1);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 0.8rem;
        }

        /* Modal Detail */
        .detail-row {
            padding: 10px 0;
            border-bottom: 1px solid #333;
            display: flex;
            justify-content: space-between;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #888; }
        .detail-val { color: #fff; font-weight: 600; text-align: right; }

        /* Pagination Links */
        .pagination-link {
            color: #888;
            padding: 5px 10px;
            border: 1px solid #444;
            margin: 0 2px;
            text-decoration: none;
            border-radius: 4px;
        }
        /* Premium Animations & Glassmorphism */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 5px rgba(99, 102, 241, 0.2); }
            50% { box-shadow: 0 0 20px rgba(99, 102, 241, 0.4); }
            100% { box-shadow: 0 0 5px rgba(99, 102, 241, 0.2); }
        }

        .animate-enter {
            animation: fadeSlideUp 0.5s ease-out forwards;
            opacity: 0; /* Init hidden */
        }

        /* Stagger delays */
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }

        .glass-panel {
            background: rgba(33, 37, 41, 0.85); /* bg-dark with opacity */
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .premium-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.4);
            transition: all 0.3s ease;
            cursor: pointer;
            border-color: var(--primary) !important;
        }

        /* Improved Table */
        .table-custom tr { transition: all 0.2s ease; }
        .table-custom tr:hover td {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            box-shadow: inset 4px 0 0 var(--primary);
        }
        
        /* Stats Cards */
        .kpi-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .kpi-card:hover { transform: scale(1.03); box-shadow: 0 15px 30px rgba(0,0,0,0.5); }

        .pagination-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 0 10px var(--primary);
        }
    </style>
</head>
<body class="p-3">
    <div class="container-fluid">
        
        <!-- Header & Tabs -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <h3 class="fw-bold mb-0 text-white"><i class="bi bi-clock-history me-2" style="color:var(--primary)"></i>Centro de Actividad</h3>
                <span id="headerTotalCount" class="badge bg-primary ms-3 fs-6">0 registros</span>
            </div>
            
            <ul class="nav nav-pills bg-dark rounded border border-secondary p-1" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active rounded-1 text-white" id="pills-historial-tab" data-bs-toggle="pill" data-bs-target="#pills-historial">
                        <i class="bi bi-list-ul"></i> Historial
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link rounded-1 text-secondary" id="pills-stats-tab" data-bs-toggle="pill" data-bs-target="#pills-stats">
                        <i class="bi bi-bar-chart-fill"></i> Estadísticas
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content" id="pills-tabContent">
            
            <!-- TAB HISTORIAL (MEJORADO) -->
            <div class="tab-pane fade show active" id="pills-historial">
                <div class="card p-0 overflow-hidden">
                    
                    <!-- Search Bar Toolbar -->
                    <div class="p-3 bg-dark border-bottom border-secondary d-flex flex-wrap gap-3 align-items-center">
                        <div class="flex-grow-1 position-relative" style="min-width: 250px;">
                            <i class="bi bi-search position-absolute text-muted" style="left: 15px; top: 10px;"></i>
                            <input type="text" id="searchInput" class="form-control ps-5" placeholder="Buscar por cliente, vendedor, evento o acción..." autocomplete="off">
                        </div>
                        <div class="d-flex gap-2">
                             <input type="date" id="filterDesde" class="form-control form-control-sm" style="width: 140px;" placeholder="Desde">
                             <input type="date" id="filterHasta" class="form-control form-control-sm" style="width: 140px;" placeholder="Hasta">
                        </div>
                        <button class="btn btn-primary" onclick="filtrarConFechas()" title="Aplicar Filtros"><i class="bi bi-funnel"></i> Filtrar</button>
                        <button class="btn btn-outline-secondary" onclick="limpiarBusqueda()" title="Limpiar"><i class="bi bi-arrow-clockwise"></i></button>
                    </div>

                    <!-- Enhanced Table -->
                    <div class="table-responsive">
                        <table class="table-custom" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th style="width: 200px;">Fecha/Hora</th>
                                    <th style="width: 150px;">Acción</th>
                                    <th>Descripción / Detalle</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyTransactions">
                                <tr><td colspan="3" class="text-center py-5 text-muted">Cargando transacciones...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Pagination -->
                    <div class="p-3 bg-dark border-top border-secondary d-flex justify-content-between align-items-center">
                        <small class="text-muted" id="resultsCount">Mostrando 0 resultados</small>
                        <div id="paginationContainer"></div>
                    </div>
                </div>
            </div>

            <!-- TAB ESTADISTICAS (PREMIUM) -->
            <div class="tab-pane fade" id="pills-stats">
                <div class="card bg-transparent border-0">
                    
                    <!-- Filters Toolbar -->
                    <div class="d-flex flex-wrap gap-3 mb-4 align-items-center bg-dark p-3 rounded border border-secondary shadow-sm">
                        <div class="d-flex align-items-center gap-2">
                             <label class="text-secondary small fw-bold text-uppercase">Base de Datos:</label>
                             <select id="statsFromDB" class="form-select form-select-sm bg-black text-white border-secondary" style="width: 140px;">
                                 <option value="actual">Actual</option>
                                 <option value="historico">Histórico</option>
                                 <option value="ambas">Ambas</option>
                             </select>
                        </div>
                        <div class="vr bg-secondary mx-2"></div>
                        <div class="d-flex gap-2">
                             <input type="date" id="statsDesde" class="form-control form-control-sm bg-black text-white border-secondary" placeholder="Desde">
                             <input type="date" id="statsHasta" class="form-control form-control-sm bg-black text-white border-secondary" placeholder="Hasta">
                        </div>
                        <div class="vr bg-secondary mx-2"></div>
                        <select id="statsEvento" class="form-select form-select-sm bg-black text-white border-secondary" style="max-width: 200px;">
                             <option value="">Todo los Eventos</option>
                             <?php foreach($eventos_filtro as $ev): ?>
                                <option value="<?= $ev['id_evento'] ?>"><?= $ev['titulo'] ?></option>
                             <?php endforeach; ?>
                        </select>
                        
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm px-3 fw-bold" onclick="cargarEstadisticas()"><i class="bi bi-lightning-charge-fill"></i> Actualizar</button>
                            <button class="btn btn-success btn-sm px-3 fw-bold" onclick="descargarPDF()"><i class="bi bi-file-earmark-pdf-fill"></i> PDF</button>
                        </div>
                    </div>

                    <!-- KPI Cards Row -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden kpi-card animate-enter delay-1" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);">
                                <div class="card-body p-4 text-center">
                                    <div class="text-white-50 text-uppercase small fw-bold ls-1 mb-2">Ingresos Totales</div>
                                    <h2 class="display-6 fw-bold text-white mb-0" id="kpi-ingresos">$0.00</h2>
                                    <i class="bi bi-currency-dollar position-absolute text-white opacity-10" style="bottom: -10px; right: -10px; font-size: 5rem;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden kpi-card animate-enter delay-2" style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);">
                                <div class="card-body p-4 text-center">
                                    <div class="text-white-50 text-uppercase small fw-bold ls-1 mb-2">Boletos Vendidos</div>
                                    <h2 class="display-6 fw-bold text-white mb-0" id="kpi-boletos">0</h2>
                                    <i class="bi bi-ticket-perforated position-absolute text-white opacity-10" style="bottom: -10px; right: -10px; font-size: 5rem;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden kpi-card animate-enter delay-3" style="background: linear-gradient(135deg, #701a75 0%, #581c87 100%);">
                                <div class="card-body p-4 text-center">
                                    <div class="text-white-50 text-uppercase small fw-bold ls-1 mb-2">Ticket Promedio</div>
                                    <h2 class="display-6 fw-bold text-white mb-0" id="kpi-promedio">$0.00</h2>
                                    <i class="bi bi-graph-up-arrow position-absolute text-white opacity-10" style="bottom: -10px; right: -10px; font-size: 5rem;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden kpi-card animate-enter delay-4" style="background: linear-gradient(135deg, #881337 0%, #4c0519 100%);">
                                <div class="card-body p-4 text-center">
                                    <div class="text-white-50 text-uppercase small fw-bold ls-1 mb-2">Eventos Activos</div>
                                    <h2 class="display-6 fw-bold text-white mb-0" id="kpi-eventos">0</h2>
                                    <i class="bi bi-calendar-check position-absolute text-white opacity-10" style="bottom: -10px; right: -10px; font-size: 5rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row g-4 mb-4">
                        <div class="col-lg-5">
                            <div class="card bg-dark border-secondary shadow h-100 glass-panel animate-enter delay-2">
                                <div class="card-header bg-transparent border-secondary text-white fw-bold text-uppercase small">
                                    <i class="bi bi-clock me-2 text-info"></i>Actividad de Ventas
                                </div>
                                <div class="card-body">
                                    <canvas id="chartHora" style="max-height: 250px;"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card bg-dark border-secondary shadow h-100 glass-panel animate-enter delay-3">
                                <div class="card-header bg-transparent border-secondary text-white fw-bold text-uppercase small">
                                    <i class="bi bi-pie-chart me-2 text-warning"></i>Por Categoría
                                </div>
                                <div class="card-body position-relative">
                                    <canvas id="chartCategoria" style="max-height: 250px;"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="card bg-dark border-secondary shadow h-100 glass-panel animate-enter delay-4">
                                <div class="card-header bg-transparent border-secondary text-white fw-bold text-uppercase small">
                                    <i class="bi bi-tag-fill me-2 text-danger"></i>Por Tipo
                                </div>
                                <div class="card-body position-relative">
                                    <canvas id="chartTipo" style="max-height: 250px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Events Table -->
                    <div class="card bg-dark border-secondary shadow">
                        <div class="card-header bg-transparent border-secondary text-white fw-bold text-uppercase small">
                            <i class="bi bi-trophy me-2 text-warning"></i>Eventos Más Vendidos
                        </div>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead>
                                    <tr class="text-secondary small text-uppercase">
                                        <th class="ps-4">Evento</th>
                                        <th class="text-center">Boletos</th>
                                        <th class="text-end pe-4">Ingresos Generados</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyTopEvents">
                                    <!-- Dynamic -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- Modal Detalle Rico -->
    <div class="modal fade" id="modalDetail" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-secondary shadow-lg">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2 text-primary"></i>Detalle de Transacción</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="modalDetailBody">
                    <!-- Dynamic Content -->
                </div>
                <div class="modal-footer border-top border-secondary bg-black bg-opacity-25">
                    <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let debounceTimer;
        let currentPage = 1;
        let chartHoraInstance = null;
        let chartCategoriaInstance = null;
        let chartTipoInstance = null;

        // Auto load
        document.addEventListener('DOMContentLoaded', () => {
            fetchTransactions();
            
            // Auto-load stats if tab is active, or wait until clicked
            const statsTab = document.getElementById('pills-stats-tab');
            statsTab.addEventListener('shown.bs.tab', () => {
                cargarEstadisticas();
            });
        });

        // Search Input Logic (from previous tasks) ...
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                fetchTransactions();
            }, 300);
        });

        function filtrarConFechas() {
            currentPage = 1;
            fetchTransactions();
        }

        function limpiarBusqueda() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterDesde').value = '';
            document.getElementById('filterHasta').value = '';
            currentPage = 1;
            fetchTransactions();
        }

        async function fetchTransactions() {
             // ... existing logic ...
            const query = document.getElementById('searchInput').value;
            const desde = document.getElementById('filterDesde').value;
            const hasta = document.getElementById('filterHasta').value;
            
            const tbody = document.getElementById('tbodyTransactions');
            const countLabel = document.getElementById('resultsCount');
            const pagContainer = document.getElementById('paginationContainer');

            if(query === '' && desde === '' && hasta === '' && currentPage === 1) tbody.innerHTML = '<tr><td colspan="3" class="text-center py-5 text-muted"><div class="spinner-border text-primary mb-2"></div><br>Cargando...</td></tr>';
            
            const params = new URLSearchParams({
                q: query,
                fecha_desde: desde,
                fecha_hasta: hasta,
                page: currentPage
            });

            try {
                const res = await fetch(`api_search_transactions.php?${params.toString()}`);
                const data = await res.json();
                
                if (!data.success) throw new Error(data.error);

                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-5 text-muted">No se encontraron transacciones.</td></tr>';
                    countLabel.innerText = '0 resultados';
                    pagContainer.innerHTML = '';
                    return;
                }

                tbody.innerHTML = data.data.map((t, index) => {
                    const extra = t.extra || {};
                    const dateObj = new Date(t.fecha_hora);
                    const dateStr = dateObj.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + 
                                  dateObj.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });

                    let badgeClass = 'bg-secondary';
                    if(t.accion === 'venta') badgeClass = 'bg-success';
                    if(t.accion === 'login') badgeClass = 'bg-primary';
                    if(t.accion === 'logout') badgeClass = 'bg-danger';

                    return `
                        <tr onclick='openDetail(${JSON.stringify(t)})' class="animate-enter premium-hover" style="animation-delay: ${index * 0.05}s">
                            <td class="text-white fw-medium">${dateStr}</td>
                            <td><span class="badge ${badgeClass} bg-opacity-75 text-white shadow-sm" style="font-weight:400; letter-spacing:0.5px;">${t.accion.toUpperCase()}</span></td>
                            <td>
                                <div class="text-light">${t.descripcion.length > 80 ? t.descripcion.substring(0,80)+'...' : t.descripcion}</div>
                            </td>
                        </tr>
                    `;
                }).join('');

                countLabel.innerText = `Total: ${data.pagination.total} registros | Pág ${data.pagination.page} de ${data.pagination.pages}`;
                document.getElementById('headerTotalCount').innerText = data.pagination.total + ' registros';
                renderPagination(data.pagination.page, data.pagination.pages);

            } catch (e) {
                console.error(e);
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-5">Error al cargar datos.</td></tr>';
            }
        }

        async function goToPage(p) {
            currentPage = p;
            fetchTransactions();
        }

        function renderPagination(curr, total) {
             // ... existing logic ...
            const container = document.getElementById('paginationContainer');
            let html = '';
            if (total > 1) {
                if (curr > 1) html += `<a href="#" class="pagination-link" onclick="goToPage(${curr-1})">&laquo;</a>`;
                let start = Math.max(1, curr - 2);
                let end = Math.min(total, curr + 2);
                for(let i=start; i<=end; i++) {
                     html += `<a href="#" class="pagination-link ${i===curr?'active':''}" onclick="goToPage(${i})">${i}</a>`;
                }
                if (curr < total) html += `<a href="#" class="pagination-link" onclick="goToPage(${curr+1})">&raquo;</a>`;
            }
            container.innerHTML = html;
        }

        function openDetail(t) {
             // ... existing logic ...
            const extra = t.extra || {};
            const modal = new bootstrap.Modal(document.getElementById('modalDetail'));
            const body = document.getElementById('modalDetailBody');

            let contenidoHTML = `
                <div class="detail-row"><span class="detail-label">ID Transacción</span><span class="detail-val">#${t.id_transaccion}</span></div>
                <div class="detail-row"><span class="detail-label">Fecha</span><span class="detail-val">${t.fecha_hora}</span></div>
                <div class="detail-row"><span class="detail-label">Vendedor</span><span class="detail-val">${t.nombre} ${t.apellido}</span></div>
                <div class="detail-row"><span class="detail-label">Acción</span><span class="detail-val text-uppercase text-primary">${t.accion}</span></div>
                <div class="mt-3 mb-2 border-top border-secondary pt-2 text-muted small">DESCRIPCIÓN</div>
                <p class="text-white small">${t.descripcion}</p>
            `;

            if (t.accion === 'venta' && extra.cantidad) {
                contenidoHTML += `
                    <div class="bg-black bg-opacity-50 p-3 rounded mt-3">
                        <div class="detail-row"><span class="detail-label">Evento</span><span class="detail-val text-warning">${extra.evento || 'N/A'}</span></div>
                        <div class="detail-row"><span class="detail-label">Cliente</span><span class="detail-val text-info">${extra.cliente || 'Anónimo'}</span></div>
                        <div class="detail-row"><span class="detail-label">Boletos</span><span class="detail-val">${extra.cantidad}</span></div>
                        <div class="detail-row mt-2 pt-2 border-top border-secondary"><span class="detail-label">TOTAL COBRADO</span><span class="detail-val text-success fs-5">$${parseFloat(extra.total||0).toFixed(2)}</span></div>
                    </div>
                `;
                
                if (extra.boletos_detalle) {
                    contenidoHTML += `<div class="mt-3"><small class="text-muted">Desglose de Asientos:</small><div class="d-flex flex-wrap gap-2 mt-1">`;
                    extra.boletos_detalle.forEach(b => {
                        contenidoHTML += `<span class="badge bg-secondary border border-secondary">${b.asiento} ($${b.precio})</span>`;
                    });
                    contenidoHTML += `</div></div>`;
                }
            }
            body.innerHTML = contenidoHTML;
            modal.show();
        }

        // --- PREMIUM STATISTICS LOGIC ---
        async function cargarEstadisticas() {
            const db = document.getElementById('statsFromDB').value;
            const id_evento = document.getElementById('statsEvento').value;
            const desde = document.getElementById('statsDesde').value;
            const hasta = document.getElementById('statsHasta').value;

            // params
            const params = new URLSearchParams({ db, id_evento, fecha_desde: desde, fecha_hasta: hasta });

            try {
                const res = await fetch(`api_stats.php?${params.toString()}`);
                const r = await res.json();
                
                if(!r.success) throw new Error(r.error);

                const d = r.data;

                // 1. Update KPIs with animation
                animateValue("kpi-ingresos", parseFloat(d.resumen.total_ingresos || 0), "$");
                animateValue("kpi-boletos", parseInt(d.resumen.total_boletos || 0));
                animateValue("kpi-promedio", parseFloat(d.resumen.ticket_promedio || 0), "$");
                animateValue("kpi-eventos", parseInt(d.resumen.eventos_activos || 0));

                // 2. Charts
                renderCharts(d);

                // 3. Top Events Table
                const tbodyEv = document.getElementById('tbodyTopEvents');
                if(d.por_evento.length === 0) {
                    tbodyEv.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-3">Sin datos registrados</td></tr>`;
                } else {
                    tbodyEv.innerHTML = d.por_evento.map((ev, i) => `
                        <tr class="animate-enter premium-hover" style="animation-delay: ${i * 0.1}s">
                            <td class="ps-4 fw-bold text-white">${ev.titulo}</td>
                            <td class="text-center"><span class="badge bg-secondary rounded-pill">${ev.cantidad}</span></td>
                            <td class="text-end pe-4 text-success fw-bold">$${parseFloat(ev.total).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                        </tr>
                    `).join('');
                }

            } catch (e) {
                console.error("Stats Error:", e);
                alert("Error al cargar estadísticas: " + e.message);
            }
        }

        function animateValue(id, value, prefix = "") {
            const obj = document.getElementById(id);
            obj.innerHTML = prefix + (value.toLocaleString(undefined, {minimumFractionDigits: prefix==="$"?2:0, maximumFractionDigits:2}));
            // Simple direct set for now. Can add counting animation later if requested.
        }

        function renderCharts(data) {
            // Colors
            const colorPrimary = '#6366f1';
            const colorAccent = '#22d3ee';
            const colorPurple = '#a855f7';
            const colorPink = '#ec4899';
            const colorGrid = '#334155';

            // --- Chart Hora (Bar) ---
            const ctxHora = document.getElementById('chartHora').getContext('2d');
            
            // Build Labels 00-23
            const labelsHora = Array.from({length: 24}, (_, i) => i + ':00');
            const dataHoraValues = new Array(24).fill(0);
            
            data.por_hora.forEach(item => {
                dataHoraValues[parseInt(item.hora)] = parseFloat(item.ingresos);
            });

            if(chartHoraInstance) chartHoraInstance.destroy();

            chartHoraInstance = new Chart(ctxHora, {
                type: 'bar',
                data: {
                    labels: labelsHora,
                    datasets: [{
                        label: 'Ingresos ($)',
                        data: dataHoraValues,
                        backgroundColor: colorPrimary,
                        borderRadius: 4,
                        hoverBackgroundColor: colorAccent
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: colorGrid, drawBorder: false },
                            ticks: { color: '#94a3b8' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#94a3b8', maxTicksLimit: 12 }
                        }
                    }
                }
            });

            // --- Chart Categoria (Doughnut) ---
            const ctxCat = document.getElementById('chartCategoria').getContext('2d');
            
            if(chartCategoriaInstance) chartCategoriaInstance.destroy();

            const labelsCat = data.por_categoria.map(c => c.nombre_categoria);
            const dataCatValues = data.por_categoria.map(c => c.cantidad);

            chartCategoriaInstance = new Chart(ctxCat, {
                type: 'doughnut',
                data: {
                    labels: labelsCat,
                    datasets: [{
                        data: dataCatValues,
                        backgroundColor: [colorAccent, colorPurple, colorPink, colorPrimary, '#f59e0b'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#cbd5e1', padding: 20, usePointStyle: true, boxWidth: 10 } }
                    },
                    cutout: '60%'
                }
            });

            // --- Chart Tipo (Pie) ---
            const ctxTipo = document.getElementById('chartTipo').getContext('2d');
            
            if(chartTipoInstance) chartTipoInstance.destroy();

            const labelsTipo = data.por_tipo.map(t => t.tipo_boleto.charAt(0).toUpperCase() + t.tipo_boleto.slice(1));
            const dataTipoValues = data.por_tipo.map(t => t.cantidad);

            chartTipoInstance = new Chart(ctxTipo, {
                type: 'pie',
                data: {
                    labels: labelsTipo,
                    datasets: [{
                        data: dataTipoValues,
                        backgroundColor: ['#10b981', '#f43f5e', '#3b82f6', '#f59e0b'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#cbd5e1', padding: 15, usePointStyle: true, boxWidth: 10 } }
                    }
                }
            });
        }

        function descargarPDF() {
            const db = document.getElementById('statsFromDB').value;
            const id_evento = document.getElementById('statsEvento').value;
            const desde = document.getElementById('statsDesde').value;
            const hasta = document.getElementById('statsHasta').value;
            
            const params = new URLSearchParams({ db, id_evento, fecha_desde: desde, fecha_hasta: hasta });
            window.open(`generar_pdf.php?${params.toString()}`, '_blank');
        }
    </script>
</body>
</html>
