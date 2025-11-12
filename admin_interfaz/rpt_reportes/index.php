<?php
/* =========================
   ADMIN - REPORTES
   ========================= */

$TABLE_EVENTS   = 'evento';
$TABLE_BOLETOS  = 'boletos';
$TABLE_CATEGORIAS = 'categorias';
$TABLE_PROMOCIONES = 'promociones';
$TABLE_ASIENTOS = 'asientos';

// ---------- CONEXIÓN ----------
include_once __DIR__ . '/../../evt_interfaz/conexion.php';

/** Ejecuta SELECT y devuelve array assoc (mysqli o PDO) */
function exec_query($sql) {
    // mysqli
    foreach (['conn','conexion','mysqli'] as $m) {
        if (isset($GLOBALS[$m]) && $GLOBALS[$m] instanceof mysqli) {
            $res = $GLOBALS[$m]->query($sql);
            if ($res === false) throw new Exception($GLOBALS[$m]->error);
            $out = [];
            while ($row = $res->fetch_assoc()) $out[] = $row;
            return $out;
        }
    }
    // PDO
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $st = $GLOBALS['pdo']->query($sql);
        if ($st === false) {
            $err = $GLOBALS['pdo']->errorInfo();
            throw new Exception($err[2] ?? 'PDO error');
        }
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    throw new Exception('No se detectó conexión ($conn | $conexion | $mysqli | $pdo)');
}

// ---- Cargar eventos para select ----
$EVENTOS = [];
$EVENTOS_ERROR = null;

try {
    $EVENTOS = exec_query("SELECT id_evento, titulo, inicio_venta, cierre_venta, finalizado FROM $TABLE_EVENTS ORDER BY inicio_venta DESC, id_evento DESC");
} catch (Exception $e) {
    $EVENTOS_ERROR = $e->getMessage();
}

$EVENTOS_JSON = json_encode([
    'items' => $EVENTOS,
    'error' => $EVENTOS_ERROR
], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Admin — Reportes</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
  :root{
    --brand:#3498db;
    --bg:#f4f7f6;
    --paper:#fff;
    --bd:#e6ebf0;
    --ink:#2c3e50;
    --rad:14px;
    --shadow:0 10px 30px rgba(0,0,0,.08);
    --success:#27ae60;
    --warning:#f39c12;
    --danger:#e74c3c;
    --info:#3498db;
  }

  *{box-sizing:border-box;}

  body{
    margin:0;
    background:var(--bg);
    color:var(--ink);
    font:400 15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
    min-height:100vh;
    display:flex;
    flex-direction:column;
  }

  header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:14px 18px;
    background:var(--paper);
    box-shadow:var(--shadow);
    flex-wrap:wrap;
  }

  .actions{display:flex;gap:10px;flex-wrap:wrap}
  .btn{
    border:none;
    border-radius:10px;
    padding:10px 14px;
    cursor:pointer;
    font-weight:600;
    transition:all 0.2s;
  }
  .btn:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15);}
  .btn:disabled{opacity:0.6;cursor:not-allowed;}
  .btn.brand{background:var(--brand);color:#fff}
  .btn.ok{background:var(--success);color:#fff}
  .btn.muted{background:#ecf0f1;color:#2c3e50}
  .btn.warn{background:var(--warning);color:#fff}
  .btn.danger{background:var(--danger);color:#fff}
  .btn.info{background:var(--info);color:#fff}

  .wrap{
    flex:1;
    padding:18px;
    min-height:0;
    overflow:auto;
  }

  .card{
    background:var(--paper);
    border:1px solid var(--bd);
    border-radius:var(--rad);
    box-shadow:var(--shadow);
    padding:18px;
    margin-bottom:18px;
  }

  .row{
    display:grid;
    gap:12px;
    grid-template-columns:repeat(12,1fr);
  }
  .col-12{grid-column:span 12}
  .col-6{grid-column:span 6}
  .col-4{grid-column:span 4}
  .col-3{grid-column:span 3}

  @media(max-width:900px){
    .row{grid-template-columns:repeat(6,1fr)}
    .col-6,.col-4,.col-3{grid-column:span 6}
  }

  label{
    font-weight:600;
    font-size:13px;
    display:block;
    margin-bottom:6px;
  }

  input,select{
    width:100%;
    padding:10px 12px;
    border:1px solid var(--bd);
    border-radius:10px;
    background:#fff;
    outline:none;
  }
  input:focus,select:focus{
    border-color:var(--brand);
    box-shadow:0 0 0 3px #3498db22;
  }

  table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    border:1px solid var(--bd);
    border-radius:12px;
    overflow:hidden;
  }
  th,td{
    padding:10px 12px;
    border-bottom:1px solid var(--bd);
    text-align:left;
    font-size:14px;
  }
  th{
    background:#f8fafc;
    font-weight:700;
  }
  tr:hover td{
    background:#fafcff;
  }
  tr:last-child td{border-bottom:none;}

  .stats-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
    gap:12px;
    margin-bottom:18px;
  }

  .stat-card{
    background:linear-gradient(135deg, var(--paper) 0%, #f8fafc 100%);
    border:1px solid var(--bd);
    border-radius:var(--rad);
    padding:16px;
    text-align:center;
  }
  .stat-value{
    font-size:28px;
    font-weight:700;
    color:var(--brand);
    margin:8px 0;
  }
  .stat-label{
    font-size:12px;
    color:#6c7a89;
    text-transform:uppercase;
    letter-spacing:0.5px;
  }

  .pill{
    display:inline-block;
    padding:4px 8px;
    border-radius:999px;
    font-size:12px;
  }
  .pill.ok{
    background:#eaf7ef;
    color:#1e7b43;
  }
  .pill.info{
    background:#eaf3fc;
    color:#0e5aa7;
  }
  .pill.warn{
    background:#fef3e2;
    color:#b45309;
  }
  .pill.danger{
    background:#fee2e2;
    color:#991b1b;
  }

  .muted{color:#6c7a89}
  .loading{opacity:0.6;pointer-events:none;}

  .tabs{
    display:flex;
    gap:8px;
    border-bottom:2px solid var(--bd);
    margin-bottom:18px;
    flex-wrap:wrap;
  }
  .tab{
    padding:10px 16px;
    background:transparent;
    border:none;
    border-bottom:3px solid transparent;
    cursor:pointer;
    font-weight:600;
    color:var(--ink);
    transition:all 0.2s;
  }
  .tab:hover{color:var(--brand);}
  .tab.active{
    color:var(--brand);
    border-bottom-color:var(--brand);
  }

  .tab-content{display:none;}
  .tab-content.active{display:block;}

  .empty-state{
    text-align:center;
    padding:40px 20px;
    color:#6c7a89;
  }
  .empty-state i{font-size:48px;margin-bottom:12px;opacity:0.3;}
</style>
</head>
<body>

<header>
  <div>
    <strong>Reportes y Estadísticas</strong>
    <span class="muted">· Admin</span>
  </div>

  <div class="actions">
    <button class="btn muted" id="btn-refresh"><i class="bi bi-arrow-repeat"></i> Refrescar</button>
  </div>
</header>

<div class="wrap">

  <!-- FILTROS -->
  <div class="card">
    <h2 style="margin:0 0 12px">Filtros de Reporte</h2>
    
    <div class="row">
      <div class="col-6">
        <label>Evento</label>
        <select id="filtro-evento">
          <option value="">Todos los eventos</option>
        </select>
      </div>

      <div class="col-3">
        <label>Fecha desde</label>
        <input type="date" id="filtro-desde" />
      </div>

      <div class="col-3">
        <label>Fecha hasta</label>
        <input type="date" id="filtro-hasta" />
      </div>
    </div>

    <div class="actions" style="margin-top:12px">
      <button class="btn brand" id="btn-generar">
        <i class="bi bi-search"></i> Generar Reporte
      </button>
      <button class="btn ok" id="btn-export">
        <i class="bi bi-file-pdf"></i> Exportar PDF
      </button>
    </div>
  </div>

  <!-- TABS DE REPORTES -->
  <div class="card">
    <div class="tabs">
      <button class="tab active" data-tab="resumen">Resumen General</button>
      <button class="tab" data-tab="ventas">Ventas por Evento</button>
      <button class="tab" data-tab="categorias">Ventas por Categoría</button>
      <button class="tab" data-tab="descuentos">Descuentos Aplicados</button>
      <button class="tab" data-tab="asientos">Ocupación de Asientos</button>
    </div>

    <!-- TAB: RESUMEN -->
    <div class="tab-content active" id="tab-resumen">
      <div id="stats-resumen" class="stats-grid"></div>
      <div id="table-resumen"></div>
    </div>

    <!-- TAB: VENTAS -->
    <div class="tab-content" id="tab-ventas">
      <div id="table-ventas"></div>
    </div>

    <!-- TAB: CATEGORIAS -->
    <div class="tab-content" id="tab-categorias">
      <div id="table-categorias"></div>
    </div>

    <!-- TAB: DESCUENTOS -->
    <div class="tab-content" id="tab-descuentos">
      <div id="table-descuentos"></div>
    </div>

    <!-- TAB: ASIENTOS -->
    <div class="tab-content" id="tab-asientos">
      <div id="table-asientos"></div>
    </div>
  </div>

</div>

<script>
// ====== DATA DEL PHP ======
const PHP_EVENTOS = <?php echo $EVENTOS_JSON ?: '{"items":[],"error":"sin_datos"}'; ?>;

// ====== HELPERS ======
const $  = s => document.querySelector(s);
const $$ = s => document.querySelectorAll(s);
const fmtMoney = n => Number(n||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
const fmtDate = d => d ? new Date(d).toLocaleDateString('es-MX') : '—';

// ====== ESTADO ======
const state = {
  eventos: [],
  reporteActual: null,
  tabActiva: 'resumen'
};

// ====== API ======
const API = 'reportes_api.php';

async function apiGenerarReporte(filtros){
  const params = new URLSearchParams();
  if(filtros.evento) params.append('evento', filtros.evento);
  if(filtros.desde) params.append('desde', filtros.desde);
  if(filtros.hasta) params.append('hasta', filtros.hasta);
  
  try {
    const r = await fetch(API + '?action=generar&' + params.toString(), { cache:'no-store' });
    
    // Verificar si la respuesta es JSON
    const contentType = r.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await r.text();
      console.error('Respuesta no es JSON:', text.substring(0, 500));
      throw new Error('El servidor no devolvió JSON. Respuesta: ' + text.substring(0, 200));
    }
    
    const j = await r.json();
    if(!j.ok) throw new Error(j.error||'Error API');
    return j.data;
  } catch (error) {
    if (error instanceof SyntaxError) {
      // Error al parsear JSON
      console.error('Error al parsear JSON:', error);
      throw new Error('Error al procesar la respuesta del servidor. Ver consola para más detalles.');
    }
    throw error;
  }
}

async function apiExportarPDF(filtros){
  const params = new URLSearchParams();
  if(filtros.evento) params.append('evento', filtros.evento);
  if(filtros.desde) params.append('desde', filtros.desde);
  if(filtros.hasta) params.append('hasta', filtros.hasta);
  
  window.open(API + '?action=export&' + params.toString(), '_blank');
}

// ====== TABS ======
function initTabs(){
  $$('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const tabId = tab.dataset.tab;
      state.tabActiva = tabId;
      
      $$('.tab').forEach(t => t.classList.remove('active'));
      $$('.tab-content').forEach(c => c.classList.remove('active'));
      
      tab.classList.add('active');
      $(#tab-${tabId}).classList.add('active');
      
      if(state.reporteActual){
        renderTab(tabId);
      }
    });
  });
}

// ====== RENDERIZADO ======
function renderResumen(data){
  const stats = data.resumen || {};
  
  $('#stats-resumen').innerHTML = `
    <div class="stat-card">
      <div class="stat-label">Total Vendido</div>
      <div class="stat-value">${fmtMoney(stats.total_vendido || 0)}</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Boletos Vendidos</div>
      <div class="stat-value">${stats.total_boletos || 0}</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Descuentos Aplicados</div>
      <div class="stat-value">${fmtMoney(stats.total_descuentos || 0)}</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Promedio por Boleto</div>
      <div class="stat-value">${fmtMoney(stats.promedio_por_boleto || 0)}</div>
    </div>
  `;

  if(data.eventos && data.eventos.length > 0){
    let html = '<table><thead><tr><th>Evento</th><th>Boletos</th><th>Total</th><th>Promedio</th></tr></thead><tbody>';
    data.eventos.forEach(ev => {
      html += `
        <tr>
          <td><strong>${ev.titulo || 'Sin título'}</strong></td>
          <td>${ev.total_boletos || 0}</td>
          <td>${fmtMoney(ev.total_vendido || 0)}</td>
          <td>${fmtMoney(ev.promedio || 0)}</td>
        </tr>
      `;
    });
    html += '</tbody></table>';
    $('#table-resumen').innerHTML = html;
  } else {
    $('#table-resumen').innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i><p>No hay datos para mostrar</p></div>';
  }
}

function renderVentas(data){
  if(!data.ventas || data.ventas.length === 0){
    $('#table-ventas').innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i><p>No hay datos de ventas</p></div>';
    return;
  }

  let html = '<table><thead><tr><th>Evento</th><th>Fecha</th><th>Boletos</th><th>Total</th><th>Descuentos</th></tr></thead><tbody>';
  data.ventas.forEach(v => {
    html += `
      <tr>
        <td><strong>${v.titulo || '—'}</strong></td>
        <td>${fmtDate(v.fecha)}</td>
        <td>${v.cantidad || 0}</td>
        <td>${fmtMoney(v.total || 0)}</td>
        <td>${fmtMoney(v.descuentos || 0)}</td>
      </tr>
    `;
  });
  html += '</tbody></table>';
  $('#table-ventas').innerHTML = html;
}

function renderCategorias(data){
  if(!data.categorias || data.categorias.length === 0){
    $('#table-categorias').innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i><p>No hay datos de categorías</p></div>';
    return;
  }

  let html = '<table><thead><tr><th>Categoría</th><th>Evento</th><th>Boletos</th><th>Total</th><th>% del Total</th></tr></thead><tbody>';
  const totalGeneral = data.resumen?.total_vendido || 1;
  
  data.categorias.forEach(cat => {
    const porcentaje = ((cat.total || 0) / totalGeneral * 100).toFixed(1);
    html += `
      <tr>
        <td><strong>${cat.nombre_categoria || '—'}</strong></td>
        <td>${cat.titulo_evento || '—'}</td>
        <td>${cat.cantidad || 0}</td>
        <td>${fmtMoney(cat.total || 0)}</td>
        <td>${porcentaje}%</td>
      </tr>
    `;
  });
  html += '</tbody></table>';
  $('#table-categorias').innerHTML = html;
}

function renderDescuentos(data){
  if(!data.descuentos || data.descuentos.length === 0){
    $('#table-descuentos').innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i><p>No hay descuentos aplicados</p></div>';
    return;
  }

  let html = '<table><thead><tr><th>Promoción</th><th>Evento</th><th>Boletos</th><th>Descuento Total</th><th>Promedio</th></tr></thead><tbody>';
  data.descuentos.forEach(desc => {
    html += `
      <tr>
        <td><strong>${desc.nombre_promocion || '—'}</strong></td>
        <td>${desc.titulo_evento || '—'}</td>
        <td>${desc.cantidad || 0}</td>
        <td>${fmtMoney(desc.total_descuento || 0)}</td>
        <td>${fmtMoney(desc.promedio || 0)}</td>
      </tr>
    `;
  });
  html += '</tbody></table>';
  $('#table-descuentos').innerHTML = html;
}

function renderAsientos(data){
  if(!data.asientos || data.asientos.length === 0){
    $('#table-asientos').innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i><p>No hay datos de asientos</p></div>';
    return;
  }

  let html = '<table><thead><tr><th>Evento</th><th>Total Asientos</th><th>Vendidos</th><th>Disponibles</th><th>% Ocupación</th></tr></thead><tbody>';
  data.asientos.forEach(asi => {
    const total = (asi.total_asientos || 0);
    const vendidos = (asi.vendidos || 0);
    const disponibles = total - vendidos;
    const ocupacion = total > 0 ? ((vendidos / total) * 100).toFixed(1) : 0;
    
    html += `
      <tr>
        <td><strong>${asi.titulo || '—'}</strong></td>
        <td>${total}</td>
        <td><span class="pill ok">${vendidos}</span></td>
        <td><span class="pill info">${disponibles}</span></td>
        <td><strong>${ocupacion}%</strong></td>
      </tr>
    `;
  });
  html += '</tbody></table>';
  $('#table-asientos').innerHTML = html;
}

function renderTab(tabId){
  if(!state.reporteActual) return;
  
  switch(tabId){
    case 'resumen':
      renderResumen(state.reporteActual);
      break;
    case 'ventas':
      renderVentas(state.reporteActual);
      break;
    case 'categorias':
      renderCategorias(state.reporteActual);
      break;
    case 'descuentos':
      renderDescuentos(state.reporteActual);
      break;
    case 'asientos':
      renderAsientos(state.reporteActual);
      break;
  }
}

// ====== GENERAR REPORTE ======
async function generarReporte(){
  const filtros = {
    evento: $('#filtro-evento').value || null,
    desde: $('#filtro-desde').value || null,
    hasta: $('#filtro-hasta').value || null
  };

  try{
    $('.wrap').classList.add('loading');
    const data = await apiGenerarReporte(filtros);
    state.reporteActual = data;
    renderTab(state.tabActiva);
  }catch(err){
    alert('Error al generar reporte: ' + err.message);
  }finally{
    $('.wrap').classList.remove('loading');
  }
}

// ====== EXPORTAR ======
function exportarPDF(){
  const filtros = {
    evento: $('#filtro-evento').value || null,
    desde: $('#filtro-desde').value || null,
    hasta: $('#filtro-hasta').value || null
  };
  apiExportarPDF(filtros);
}

// ====== INIT ======
(async function init(){
  // Cargar eventos
  try {
    state.eventos = (PHP_EVENTOS.items || []).map(e => ({
      id: e.id_evento ?? null,
      nombre: e.titulo || 'Evento',
      fecha: (e.inicio_venta || '').slice(0,10)
    }));
  } catch {
    state.eventos = [];
  }

  // Llenar select de eventos
  const sel = $('#filtro-evento');
  state.eventos.forEach(e => {
    const opt = document.createElement('option');
    opt.value = e.id ?? '';
    opt.textContent = ${e.nombre} — ${e.fecha||'s/f'};
    sel.appendChild(opt);
  });

  // Establecer fechas por defecto (último mes)
  const hoy = new Date();
  const haceUnMes = new Date();
  haceUnMes.setMonth(haceUnMes.getMonth() - 1);
  
  $('#filtro-hasta').value = hoy.toISOString().slice(0,10);
  $('#filtro-desde').value = haceUnMes.toISOString().slice(0,10);

  // Listeners
  initTabs();
  $('#btn-generar').addEventListener('click', generarReporte);
  $('#btn-export').addEventListener('click', exportarPDF);
  $('#btn-refresh').addEventListener('click', generarReporte);

  // Generar reporte inicial
  await generarReporte();
})();
</script>
</body>
</html>