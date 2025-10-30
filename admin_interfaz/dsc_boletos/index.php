<?php
/* =========================
   ADMIN - DESCUENTOS SOLO
   ========================= */

$TABLE_EVENTS   = 'evento'; // tabla de tus eventos
$IMG_CANDIDATES = ['imagen','foto','ruta_imagen','img','thumbnail','miniatura'];
$COLS_REQUIRED  = ['id_evento','titulo','inicio_venta','cierre_venta','tipo','finalizado'];

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
$selectCols = $COLS_REQUIRED;
$selectColsSql = implode(',', array_map(fn($c)=>"`$c`", $selectCols));

$EVENTOS = [];
$EVENTOS_ERROR = null;

try {
    $EVENTOS = exec_query("SELECT $selectColsSql FROM `$TABLE_EVENTS` ORDER BY `inicio_venta` ASC, `id_evento` ASC");
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
<title>Admin — Descuentos</title>
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
  }

  .actions{display:flex;gap:10px;flex-wrap:wrap}
  .btn{
    border:none;
    border-radius:10px;
    padding:10px 14px;
    cursor:pointer;
    font-weight:600;
  }
  .btn.brand{background:var(--brand);color:#fff}
  .btn.ok{background:#27ae60;color:#fff}
  .btn.muted{background:#ecf0f1;color:#2c3e50}
  .btn.warn{background:#f39c12;color:#fff}
  .btn.danger{background:#e74c3c;color:#fff}

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

  @media(max-width:900px){
    .row{grid-template-columns:repeat(6,1fr)}
    .col-6{grid-column:span 6}
  }

  label{
    font-weight:600;
    font-size:13px;
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

  .muted{color:#6c7a89}
</style>
</head>
<body>

<header>
  <div>
    <strong>Descuentos y Promociones</strong>
    <span class="muted">· Admin</span>
  </div>

  <div class="actions">
    <button class="btn muted" id="btn-refresh"><i class="bi bi-arrow-repeat"></i> Refrescar</button>
    <button class="btn warn" id="btn-export"><i class="bi bi-download"></i> Descargar historial</button>
  </div>
</header>

<div class="wrap">

  <!-- FORM -->
  <div class="card">
    <h2 style="margin:0 0 12px">Crear / editar promoción</h2>

    <div class="row">
      <div class="col-6">
        <form id="form-descuento" class="row" autocomplete="off">
          <div class="col-12">
            <label>Evento</label>
            <select id="ev"></select>
          </div>

          <div class="col-6">
            <label>Tipo boleto</label>
            <select id="tipo">
              <option>General</option>
              <option>VIP</option>
              <option>Estudiante</option>
            </select>
          </div>

          <div class="col-6">
            <label>Precio base (MXN)</label>
            <input type="number" id="precio" min="0" step="0.01" placeholder="Ej. 350" />
          </div>

          <div class="col-6">
            <label>Tipo de descuento</label>
            <select id="modo">
              <option value="porcentaje">% Porcentaje</option>
              <option value="fijo">$ Monto fijo</option>
            </select>
          </div>

          <div class="col-6">
            <label>Valor del descuento</label>
            <input type="number" id="valor" min="0" step="0.01" placeholder="Ej. 10" />
          </div>

          <div class="col-6">
            <label>Mínimo de boletos</label>
            <input type="number" id="minq" min="1" step="1" value="1" />
          </div>

          <div class="col-6">
            <label>Desde</label>
            <input type="date" id="desde" />
          </div>

          <div class="col-6">
            <label>Hasta</label>
            <input type="date" id="hasta" />
          </div>

          <div class="col-12">
            <label>Condiciones (opcional)</label>
            <input type="text" id="cond" placeholder="Ej. válido martes, no acumulable" />
          </div>

          <div class="col-12 actions" style="margin-top:6px">
            <button class="btn brand" type="button" id="btn-preview">
              <i class="bi bi-eye"></i> Vista previa
            </button>
            <button class="btn ok" type="submit">
              <i class="bi bi-check2-circle"></i> Guardar
            </button>
            <button class="btn muted" type="reset" id="btn-clear">
              <i class="bi bi-eraser"></i> Limpiar
            </button>
          </div>
        </form>
      </div>

      <div class="col-6">
        <div class="card" style="height:100%">
          <h3 style="margin-top:0">Vista previa</h3>
          <div id="preview" class="muted">
            Completa el formulario y haz clic en “Vista previa”.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- TABLA -->
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px">
      <h3 style="margin:0">Promociones registradas</h3>
    </div>

    <table id="tabla-promos">
      <thead>
        <tr>
          <th>Evento</th>
          <th>Nombre promo</th>
          <th>Desc.</th>
          <th>Vigencia</th>
          <th>Mín.</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

  <?php if($EVENTOS_ERROR): ?>
    <p class="muted" style="margin-top:12px;color:#e74c3c">
      ⚠️ Error al leer eventos: <?php echo htmlspecialchars($EVENTOS_ERROR); ?>
    </p>
  <?php endif; ?>

</div>

<!-- MODAL -->
<div id="modalOverlay" style="
  position:fixed;inset:0;display:none;align-items:center;justify-content:center;
  background:rgba(0,0,0,.4);z-index:9999;
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">
  
  <div id="modalCard" style="
    background:#fff;min-width:320px;max-width:90vw;
    border-radius:16px;border:1px solid #e6ebf0;
    box-shadow:0 30px 80px rgba(0,0,0,.25);
    padding:20px;position:relative;">
    
    <h3 id="modalTitle" style="margin:0 0 8px;font-size:16px;color:#2c3e50;">Título</h3>
    <div id="modalBody" style="font-size:14px;color:#4a5568;line-height:1.4;">
      contenido...
    </div>

    <div id="modalActions" style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;flex-wrap:wrap;"></div>

    <button id="modalCloseX" style="
      position:absolute;right:12px;top:12px;
      background:transparent;border:0;color:#94a3b8;
      font-size:18px;line-height:1;cursor:pointer;">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
</div>

<script>
// ====== DATA DEL PHP ======
const PHP_EVENTOS = <?php echo $EVENTOS_JSON ?: '{"items":[],"error":"sin_datos"}'; ?>;

// ====== HELPERS ======
const $  = s => document.querySelector(s);
const fmtMoney = n => Number(n||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
const todayISO = () => new Date().toISOString().slice(0,10);

function calcularFinal(precioBase, modo, valor){
  const base = parseFloat(precioBase || 0);
  const v    = parseFloat(valor || 0);

  if (modo === 'porcentaje') {
    return Math.max(0, base * (1 - v/100));
  } else {
    return Math.max(0, base - v);
  }
}

// ====== MODAL ======
const modalOverlay = $("#modalOverlay");
const modalTitle   = $("#modalTitle");
const modalBody    = $("#modalBody");
const modalActions = $("#modalActions");
const modalCloseX  = $("#modalCloseX");

function openModal({title, bodyNode, actions=[]}) {
  modalTitle.textContent = title || "";
  modalBody.innerHTML = "";
  if (bodyNode instanceof Node) {
    modalBody.appendChild(bodyNode);
  } else {
    modalBody.textContent = bodyNode || "";
  }
  modalActions.innerHTML = "";
  actions.forEach(btnConf=>{
    const b = document.createElement("button");
    b.textContent = btnConf.label;
    b.className = "btn " + (btnConf.className||"muted");
    b.style.minWidth = "90px";
    b.addEventListener("click", btnConf.onClick);
    modalActions.appendChild(b);
  });
  modalOverlay.style.display = "flex";
}
function closeModal() {
  modalOverlay.style.display = "none";
}
modalCloseX.addEventListener("click", closeModal);
modalOverlay.addEventListener("click", e=>{
  if(e.target === modalOverlay) closeModal();
});

// ====== ESTADO ======
const state = {
  eventos: [],
  promos: [],
  editingId: null
};

// ====== API ======
const API = 'promos_api.php';

async function apiListPromos(){
  const r = await fetch(API + '?action=list', { cache:'no-store' });
  const j = await r.json();
  if(!j.ok) throw new Error(j.error||'Error API');
  return j.items||[];
}

async function apiCreatePromo(p){
  const r = await fetch(API + '?action=create', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(p)
  });
  const j = await r.json();
  if(!j.ok) throw new Error(j.error||'No se pudo crear');
  return j.id_promocion;
}

async function apiUpdatePromo(id, p){
  const r = await fetch(API + '?action=update&id='+encodeURIComponent(id), {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(p)
  });
  const j = await r.json();
  if(!j.ok) throw new Error(j.error||'No se pudo actualizar');
}

async function apiDeletePromo(id){
  const r = await fetch(API + '?action=delete&id='+encodeURIComponent(id), {
    method:'POST'
  });
  const j = await r.json();
  if(!j.ok) throw new Error(j.error||'No se pudo eliminar');
}

function exportCSV(){
  window.open(API+'?action=export','_blank');
}

// ====== LOGICA ======
function previewPromo(){
  const evSeleccionadoId = $('#ev').value ?? '';
  const ev = state.eventos.find(e => (e.id ?? '') == evSeleccionadoId);

  const tipo   = $('#tipo').value;
  const precio = $('#precio').value;
  const modo   = $('#modo').value;
  const valor  = $('#valor').value;
  const desde  = $('#desde').value || todayISO();
  const hasta  = $('#hasta').value || todayISO();
  const minq   = $('#minq').value || 1;
  const cond   = $('#cond').value.trim();

  if(!precio || !valor){
    alert('Pon precio base y valor de descuento.');
    return;
  }

  const nombreEvento = ev ? ev.nombre : '(sin evento)';
  const fechaEvento  = ev ? ev.fecha  : '—';
  const final        = calcularFinal(precio, modo, valor);

  $('#preview').innerHTML = `
    <div>
      <strong>${nombreEvento}</strong>
      — <span class="muted">${fechaEvento} · ${tipo}</span>
    </div>

    <div style="margin:6px 0">
      <span class="pill info">Base: ${fmtMoney(precio)}</span>
      <span class="pill info">Desc.: ${
        modo==='porcentaje' ? (valor+'%') : fmtMoney(valor)
      }</span>
      <span class="pill ok">Final: ${fmtMoney(final)}</span>
    </div>

    <div class="muted">
      Vigencia: ${desde} a ${hasta} · Mín. ${minq}${
        cond ? ' · '+cond : ''
      }
    </div>
  `;
}

// cargar promos actuales desde API y mapear al estado
async function loadPromos(){
  const list = await apiListPromos();

  state.promos = list.map(p => ({
    id_promocion : Number(p.id_promocion),
    evId         : p.id_evento ? Number(p.id_evento) : null,
    evNombre     : p.evento_titulo || '(sin evento)',
    nombrePromo  : p.nombre || 'Promo',

    modo         : p.modo_calculo,          // "porcentaje" | "fijo"
    valor        : Number(p.valor || 0),

    desde        : (p.fecha_desde || '').slice(0,10),
    hasta        : (p.fecha_hasta || '').slice(0,10),

    minq         : Number(p.min_cantidad || 1),
    activo       : Number(p.activo || 0),

    precio       : p.precio ? Number(p.precio) : 0,

    condiciones  : p.condiciones || ''
  }));
}

// guardar desde el formulario principal (crear o actualizar existente)
async function savePromo(e){
  e.preventDefault();

  const evObj = state.eventos.find(e=> (e.id ?? '') == ($('#ev').value ?? ''));
  if(!evObj){
    alert('Selecciona un evento válido.');
    return;
  }

  const payload = {
    id_evento:     evObj.id,
    tipo_boleto:   $('#tipo').value, // solo se usa en create para generar el nombre
    precio_base:   +($('#precio').value||0),
    modo:          $('#modo').value,
    valor:         +($('#valor').value||0),
    desde:         $('#desde').value || todayISO(),
    hasta:         $('#hasta').value || todayISO(),
    min_cantidad:  +($('#minq').value||1),
    condiciones:   $('#cond').value.trim()
  };

  if(!payload.precio_base || !payload.valor){
    alert('Precio base y valor de descuento son obligatorios.');
    return;
  }

  try{
    if(state.editingId){
      // UPDATE
      // importante: en update ya no regeneramos el nombre,
      // usamos el nombre existente como nombre_fijo
      const promoActual = state.promos.find(x => x.id_promocion === state.editingId);
      payload.nombre_fijo = promoActual ? promoActual.nombrePromo : 'Promoción';

      await apiUpdatePromo(state.editingId, payload);
    } else {
      // CREATE
      await apiCreatePromo(payload);
    }

    state.editingId = null;
    $('#form-descuento').reset();
    $('#preview').textContent = 'Descuento guardado.';

    await loadPromos();
    renderPromosTable();
  }catch(err){
    alert('Error al guardar: '+err.message);
  }
}

function vigentePromo(p){
  const hoy = todayISO();
  if(!p.desde || !p.hasta) return true;
  return (p.desde <= hoy && p.hasta >= hoy);
}

// pinta tabla con datos correctos
function renderPromosTable(){
  const tb = document.querySelector('#tabla-promos tbody');
  tb.innerHTML = '';

  if(!state.promos.length){
    tb.innerHTML = `<tr><td colspan="7" class="muted">Sin promociones registradas.</td></tr>`;
    return;
  }

  state.promos
    .slice()
    .sort((a,b)=> (a.desde||'').localeCompare(b.desde||'')) // orden cronológico
    .forEach(p=>{
      const descTxt = (p.modo === 'porcentaje')
        ? (p.valor + '%')
        : fmtMoney(p.valor);

      const precioFinal = calcularFinal(p.precio, p.modo, p.valor);

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${p.evNombre}</td>

        <td>
          <div><strong>${p.nombrePromo}</strong></div>

          <div class="muted" style="font-size:12px">
            Base: ${fmtMoney(p.precio || 0)}
          </div>

          <div class="muted" style="font-size:12px">
            Final: ${fmtMoney(precioFinal)}
          </div>

          <div class="muted" style="font-size:12px">
            ${p.condiciones ? 'Condiciones: ' + p.condiciones : ''}
          </div>
        </td>

        <td>${descTxt}</td>

        <td>${p.desde || '—'} → ${p.hasta || '—'}</td>

        <td>${p.minq}</td>

        <td>${
          (p.activo && vigentePromo(p))
            ? '<span class="pill ok">Vigente</span>'
            : '—'
        }</td>

        <td>
          <button type="button" class="btn muted" data-act="edit" data-id="${p.id_promocion}">
            <i class="bi bi-pencil-square"></i>
          </button>
          <button type="button" class="btn danger" data-act="del" data-id="${p.id_promocion}">
            <i class="bi bi-trash3"></i>
          </button>
        </td>
      `;
      tb.appendChild(tr);
    });
}


// ====== MODAL EDITAR ======
function openEditModal(promo){
  const wrap = document.createElement('div');
  wrap.innerHTML = `
    <div class="row" style="gap:8px;grid-template-columns:repeat(12,1fr);font-size:14px;">

      <div class="col-12">
        <label style="font-size:12px;font-weight:600;">Evento</label>
        <input id="m_ev_show"
               disabled
               style="width:100%;padding:8px 10px;border:1px solid #e6ebf0;border-radius:8px;background:#f1f5f9;color:#475569;" />
      </div>

      <div class="col-12">
        <label style="font-size:12px;font-weight:600;">Nombre / tipo boleto</label>
        <input id="m_tipo"
               disabled
               style="width:100%;padding:8px 10px;border:1px solid #e6ebf0;border-radius:8px;background:#f1f5f9;color:#475569;" />
      </div>

      <div class="col-6">
        <label style="font-size:12px;font-weight:600;">Precio base (MXN)</label>
        <input id="m_precio"
               type="number"
               step="0.01"
               style="width:100%;padding:8px 10px;border:1px solid #e6ebf0;border-radius:8px;" />
      </div>

      <div class="col-6">
        <label style="font-size:12px;font-weight:600;">Modo</label>
        <select id="m_modo"
                style="width:100%;padding:8px 10px;border:1px solid #e6ebf0;border-radius:8px;">
          <option value="porcentaje">% Porcentaje</option>
          <option value="fijo">$ Monto fijo</option>
        </select>
      </div>

      <div class="col-6">
        <label style="font-size:12px;font-weight:600;">Valor desc.</label>
        <input id="m_valor"
               type="number"
               step="0.01"
               style="width:100%;padding:8px 10px;border:1px solid #e6ebf0;border-radius:8px;" />
      </div>

      <div class="col-6">
        <label style="font-size:12px;font-weight:600;">Mínimos</label>
        <input id="m_minq"
               type="number"
               step="1"
               min="1"
               style="width:100%;padding:8px 10px;border:1px solid #e6ebf0;border-radius:8px;" />
      </div>

      <div class="col-6">
        <label style="font-size:12px;font-weight:600;">Desde</label>
        <input id="m_desde"
               type="date"
               style="width:100%;padding:8px 10px;border:1px solid #e6ebf0;border-radius:8px;" />
      </div>

      <div class="col-6">
        <label style="font-size:12px;font-weight:600;">Hasta</label>
        <input id="m_hasta"
               type="date"
               style="width:100%;padding:8px 10px;border:1px solid #e6ebf0;border-radius:8px;" />
      </div>

      <div class="col-12">
        <label style="font-size:12px;font-weight:600;">Condiciones</label>
        <input id="m_cond"
               style="width:100%;padding:8px 10px;border:1px solid #e6ebf0;border-radius:8px;" />
      </div>
    </div>
  `;

  // precargar datos en los inputs
  wrap.querySelector('#m_ev_show').value = promo.evNombre || '(sin evento)';
  wrap.querySelector('#m_tipo').value    = promo.nombrePromo || '';
  wrap.querySelector('#m_precio').value  = promo.precio ?? 0;
  wrap.querySelector('#m_modo').value    = promo.modo || 'porcentaje';
  wrap.querySelector('#m_valor').value   = promo.valor ?? 0;
  wrap.querySelector('#m_minq').value    = promo.minq ?? 1;
  wrap.querySelector('#m_desde').value   = promo.desde || '';
  wrap.querySelector('#m_hasta').value   = promo.hasta || '';
  wrap.querySelector('#m_cond').value    = promo.condiciones || '';

  openModal({
    title: "Editar promoción",
    bodyNode: wrap,
    actions: [
      {
        label: "Cancelar",
        className: "muted",
        onClick: closeModal
      },
      {
        label: "Guardar",
        className: "ok",
        onClick: ()=>{
          const payload = {
            id_evento:    promo.evId,                 // fijo, no cambia
            precio_base:  +wrap.querySelector('#m_precio').value || 0,
            modo:         wrap.querySelector('#m_modo').value,
            valor:        +wrap.querySelector('#m_valor').value,
            desde:        wrap.querySelector('#m_desde').value || todayISO(),
            hasta:        wrap.querySelector('#m_hasta').value || todayISO(),
            min_cantidad: +wrap.querySelector('#m_minq').value,
            condiciones:  wrap.querySelector('#m_cond').value.trim(),

            // nombre_fijo evita que se vuelva a generar "25% 25% 25%"
            nombre_fijo:  promo.nombrePromo
          };

          openModal({
            title: "Confirmar cambios",
            bodyNode: document.createTextNode("¿Seguro que quieres guardar esta promoción?"),
            actions: [
              { label:"No", className:"muted", onClick: closeModal },
              {
                label:"Sí",
                className:"ok",
                onClick: async ()=>{
                  try{
                    await apiUpdatePromo(promo.id_promocion, payload);

                    // reflejar cambios locales de precio/condiciones para no esperar refetch visual
                    promo.precio       = payload.precio_base;
                    promo.modo         = payload.modo;
                    promo.valor        = payload.valor;
                    promo.minq         = payload.min_cantidad;
                    promo.desde        = payload.desde;
                    promo.hasta        = payload.hasta;
                    promo.condiciones  = payload.condiciones;

                    await loadPromos();
                    renderPromosTable();
                    closeModal();
                  }catch(err){
                    closeModal();
                    openModal({
                      title:"Error",
                      bodyNode:document.createTextNode("No se pudo guardar: "+err.message),
                      actions:[{label:"OK",className:"danger",onClick:closeModal}]
                    });
                  }
                }
              }
            ]
          });
        }
      }
    ]
  });
}

// ====== MODAL ELIMINAR ======
function openDeleteModal(promo){
  openModal({
    title: "Eliminar promoción",
    bodyNode: document.createTextNode(
      `¿Seguro que quieres eliminar "${promo.nombrePromo}" del evento "${promo.evNombre}"? Esta acción no se puede deshacer.`
    ),
    actions: [
      {
        label: "No",
        className: "muted",
        onClick: closeModal
      },
      {
        label: "Sí, eliminar",
        className: "danger",
        onClick: async ()=>{
          try{
            await apiDeletePromo(promo.id_promocion);
            await loadPromos();
            renderPromosTable();
            closeModal();
          }catch(err){
            closeModal();
            openModal({
              title:"Error",
              bodyNode:document.createTextNode("No se pudo eliminar: "+err.message),
              actions:[{label:"OK",className:"danger",onClick:closeModal}]
            });
          }
        }
      }
    ]
  });
}

// ====== CLICK EN TABLA ======
function onPromosTBodyClick(ev){
  const btn = ev.target.closest('button[data-act]');
  if (!btn) return;
  const id  = +btn.dataset.id;
  const act = btn.dataset.act;
  const p   = state.promos.find(x => x.id_promocion === id);
  if (!p) return;

  if (act === 'del') {
    openDeleteModal(p);
  } else if (act === 'edit') {
    state.editingId = p.id_promocion;
    openEditModal(p);
  }
}

// ====== INIT ======
(async function init(){
  // eventos desde PHP
  try {
    state.eventos = (PHP_EVENTOS.items || []).map(e => {
      const inicio = (e.inicio_venta || '').toString();
      return {
        id:     e.id_evento ?? null,
        nombre: e.titulo || 'Evento',
        fecha:  inicio.slice(0,10)
      };
    });
  } catch {
    state.eventos = [];
  }

  // llenar <select> Evento del form principal
  const sel = $('#ev');
  sel.innerHTML = '';
  state.eventos.forEach(e=>{
    const opt = document.createElement('option');
    opt.value = e.id ?? '';
    opt.textContent = `${e.nombre} — ${e.fecha||'s/f'}`;
    sel.appendChild(opt);
  });
  if (state.eventos.length > 0) {
    sel.value = state.eventos[0].id ?? '';
  }

  // cargar promos actuales
  await loadPromos();

  // listeners
  $('#btn-preview').addEventListener('click', previewPromo);
  $('#form-descuento').addEventListener('submit', savePromo);
  $('#btn-clear').addEventListener('click', ()=> {
    $('#preview').textContent='Completa el formulario y haz clic en “Vista previa”.';
    state.editingId = null;
  });
  $('#btn-export').addEventListener('click', exportCSV);
  $('#btn-refresh').addEventListener('click', async ()=>{
    await loadPromos();
    renderPromosTable();
    openModal({
      title:"Listo",
      bodyNode:document.createTextNode("Promociones recargadas."),
      actions:[{label:"OK",className:"brand",onClick:closeModal}]
    });
  });

  document
    .querySelector('#tabla-promos tbody')
    .addEventListener('click', onPromosTBodyClick);

  // render inicial
  renderPromosTable();
})();
</script>
</body>
</html>
