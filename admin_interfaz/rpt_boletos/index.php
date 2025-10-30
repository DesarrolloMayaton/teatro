<?php
/* =========================
   ADMIN INDEX (todo en uno)
   ========================= */

// ---------- CONFIG EDITABLE ----------
$TABLE_EVENTS   = 'evento'; // nombre según tu evento.sql
$IMG_CANDIDATES = ['imagen','foto','ruta_imagen','img','thumbnail','miniatura'];
$COLS_REQUIRED  = ['id_evento','titulo','descripcion','imagen','tipo','inicio_venta','cierre_venta','finalizado'];

// ---------- CONEXIÓN ----------
// AJUSTA ESTA RUTA si tu conexion.php vive en otro lado:
// include_once $_SERVER['DOCUMENT_ROOT'] . '/TEATRO/teatro/evt_interfaz/conexion.php';
include_once __DIR__ . '/../evt_interfaz/conexion.php';

/** Ejecuta SELECT y regresa array assoc (mysqli o PDO) */
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

/** Detecta columna de imagen si se llama distinto */
function detect_image_col($table, $candidates) {
    try {
        $cols = exec_query("DESCRIBE `$table`");
        $names = array_map(fn($c)=> strtolower($c['Field'] ?? $c['COLUMN_NAME'] ?? ''), $cols);
        foreach ($candidates as $c) {
            if (in_array(strtolower($c), $names, true)) return $c;
        }
    } catch (Exception $e) {}
    return null;
}

$imgCol = detect_image_col($TABLE_EVENTS, $IMG_CANDIDATES);

// SELECT seguro (evita duplicar imagen si ya está en COLS_REQUIRED)
$selectCols = $COLS_REQUIRED;
if ($imgCol && !in_array($imgCol, $selectCols, true)) $selectCols[] = $imgCol;
$select = implode(',', array_map(fn($c)=>"`$c`", $selectCols));

// Carga eventos
$EVENTOS = [];
$EVENTOS_ERROR = null;
try {
    $EVENTOS = exec_query("SELECT $select FROM `$TABLE_EVENTS` ORDER BY `inicio_venta` ASC, `id_evento` ASC");
} catch (Exception $e) {
    $EVENTOS_ERROR = $e->getMessage();
}

// Pasa datos a JS
$EVENTOS_JSON = json_encode(['items'=>$EVENTOS,'imgCol'=>$imgCol, 'error'=>$EVENTOS_ERROR], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Admin — Teatro (Descuentos / Eventos / Reportes)</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  :root{
    --aside:#2c3e50; --aside-2:#243445; --brand:#3498db; --bg:#f4f7f6; --paper:#fff; --bd:#e6ebf0; --ink:#2c3e50;
    --rad:14px; --shadow:0 10px 30px rgba(0,0,0,.08); --t:.25s ease;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font:400 15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;display:flex;min-height:100vh;overflow:hidden}
  /* Sidebar */
  aside{width:230px;background:var(--aside);color:#ecf0f1;display:flex;flex-direction:column;border-right:1px solid rgba(255,255,255,.08)}
  .brand{display:flex;gap:10px;align-items:center;padding:16px 18px;border-bottom:1px solid rgba(255,255,255,.08)}
  .brand i{font-size:22px}
  nav{padding:8px 0;overflow:auto}
  .link{display:flex;align-items:center;gap:12px;color:#ecf0f1;text-decoration:none;padding:14px 18px;border-left:4px solid transparent;cursor:pointer}
  .link:hover{background:#3498db22}
  .link.active{background:#f4f7f6;color:var(--aside);border-left-color:var(--brand);font-weight:600}
  .link i{min-width:22px;font-size:18px}
  /* Main */
  main{flex:1;display:flex;flex-direction:column;min-width:0}
  header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;background:var(--paper);box-shadow:var(--shadow)}
  .wrap{padding:18px;height:100%;overflow:auto}
  .card{background:var(--paper);border:1px solid var(--bd);border-radius:var(--rad);box-shadow:var(--shadow);padding:18px}
  .row{display:grid;gap:12px;grid-template-columns:repeat(12,1fr)}
  .col-12{grid-column:span 12}.col-6{grid-column:span 6}.col-4{grid-column:span 4}.col-3{grid-column:span 3}
  @media(max-width:900px){.row{grid-template-columns:repeat(6,1fr)}.col-6{grid-column:span 6}.col-4{grid-column:span 6}.col-3{grid-column:span 3}}
  label{font-weight:600;font-size:13px}
  input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--bd);border-radius:10px;background:#fff;outline:none}
  input:focus,select:focus,textarea:focus{border-color:var(--brand);box-shadow:0 0 0 3px #3498db22}
  .actions{display:flex;gap:10px;flex-wrap:wrap}
  .btn{border:none;border-radius:10px;padding:10px 14px;cursor:pointer;font-weight:600}
  .btn.brand{background:var(--brand);color:#fff}.btn.ok{background:#27ae60;color:#fff}.btn.muted{background:#ecf0f1;color:#2c3e50}.btn.warn{background:#f39c12;color:#fff}.btn.danger{background:#e74c3c;color:#fff}
  table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--bd);border-radius:12px;overflow:hidden}
  th,td{padding:10px 12px;border-bottom:1px solid var(--bd);text-align:left;font-size:14px}
  th{background:#f8fafc;font-weight:700}
  tr:hover td{background:#fafcff}
  .pill{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px}
  .pill.ok{background:#eaf7ef;color:#1e7b43}
  .pill.info{background:#eaf3fc;color:#0e5aa7}
  .muted{color:#6c7a89}
  section{display:none}
  section.active{display:block}
  /* Evento con miniatura en esquina */
  .evt-card{
    position:relative; background:#fff; border:1px solid var(--bd); border-radius:12px; padding:14px 14px 14px 74px;
    box-shadow:var(--shadow); min-height:68px;
  }
  .evt-thumb{
    position:absolute; left:10px; top:10px; width:52px; height:52px; border-radius:8px; object-fit:cover; border:1px solid #e8ecf2; background:#f1f3f7;
  }
  .evt-grid{display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}
  .evt-title{margin:0; font-size:16px}
  .evt-meta{margin:2px 0 0; font-size:13px; color:#667085}
</style>
</head>
<body>
  <aside>
    <div class="brand"><i class="bi bi-gear-fill"></i><strong>Administración</strong></div>
    <nav id="menu">
      <div class="link active" data-section="descuentos"><i class="bi bi-percent"></i>Descuentos</div>
      <div class="link" data-section="eventos"><i class="bi bi-easel2-fill"></i>Eventos</div>
      <div class="link" data-section="reportes"><i class="bi bi-graph-up"></i>Reportes</div>
    </nav>
  </aside>

  <main>
    <header>
      <div><strong id="title">Descuentos</strong> <span class="muted" id="subtitle">· Admin</span></div>
      <div class="actions"><button class="btn muted" id="btn-refresh"><i class="bi bi-arrow-repeat"></i> Refrescar</button></div>
    </header>

    <div class="wrap">

      <!-- ================= DESCUENTOS ================= -->
      <section id="descuentos" class="active">
        <div class="card">
          <h2 style="margin:0 0 12px">Descuentos y promociones</h2>
          <div class="row">
            <div class="col-6">
              <form id="form-descuento" class="row" autocomplete="off">
                <div class="col-12 col-6">
                  <label>Evento</label>
                  <select id="ev"></select>
                </div>
                <div class="col-6">
                  <label>Tipo boleto</label>
                  <select id="tipo">
                    <option>General</option><option>VIP</option><option>Estudiante</option>
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
                  <button class="btn brand" type="button" id="btn-preview"><i class="bi bi-eye"></i> Vista previa</button>
                  <button class="btn ok" type="submit"><i class="bi bi-check2-circle"></i> Guardar</button>
                  <button class="btn muted" type="reset" id="btn-clear"><i class="bi bi-eraser"></i> Limpiar</button>
                </div>
              </form>
            </div>
            <div class="col-6">
              <div class="card" style="height:100%">
                <h3 style="margin-top:0">Vista previa</h3>
                <div id="preview" class="muted">Completa el formulario y haz clic en “Vista previa”.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:18px">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px">
            <h3 style="margin:0">Promociones</h3>
            <div class="actions">
              <button class="btn warn" id="btn-export"><i class="bi bi-download"></i> Descargar historial</button>
            </div>
          </div>
          <table id="tabla-promos">
            <thead>
              <tr>
                <th>Evento</th><th>Tipo</th><th>Base</th><th>Desc.</th><th>Final</th>
                <th>Vigencia</th><th>Mín.</th><th>Estado</th><th>Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </section>

      <!-- ================= EVENTOS ================= -->
      <section id="eventos">
        <div class="card">
          <h2 style="margin:0 0 12px">Eventos registrados</h2>

          <!-- Grid de tarjetas con miniatura -->
          <div class="evt-grid" id="evt-grid"></div>

          <h3 style="margin:16px 0 8px">Tabla</h3>
          <table id="tabla-eventos">
            <thead>
              <tr>
                <th>Evento</th>
                <th>Inicio venta</th>
                <th>Cierre venta</th>
                <th>Tipo</th>
                <th>Aforo</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>

          <?php if($EVENTOS_ERROR): ?>
            <p class="muted" style="margin-top:12px;color:#e74c3c">⚠️ Error al leer eventos: <?php echo htmlspecialchars($EVENTOS_ERROR); ?></p>
          <?php endif; ?>
        </div>
      </section>

      <!-- ================= REPORTES ================= -->
      <section id="reportes">
        <div class="card">
          <h2 style="margin:0 0 12px">Reportes (resumen rápido)</h2>
          <div class="row">
            <div class="col-4"><div class="card"><h3 style="margin:0 0 6px">Promos vigentes</h3><div id="rep-vig" class="pill info">0</div></div></div>
            <div class="col-4"><div class="card"><h3 style="margin:0 0 6px">Promos totales</h3><div id="rep-tot" class="pill info">0</div></div></div>
            <div class="col-4"><div class="card"><h3 style="margin:0 0 6px">Precio final promedio</h3><div id="rep-avg" class="pill info">$0.00</div></div></div>
          </div>
          <p class="muted">*El promedio se calcula con el precio final de todas las promociones guardadas.</p>
        </div>
      </section>

    </div>
  </main>

<!-- Puedes ajustar esta base pública si tu URL es otra -->
<script>window.APP_BASE = '/TEATRO/teatro/';</script>

<script>window.APP_BASE = '/TEATRO/teatro/';</script>
<script>
  // ------- Datos de PHP (eventos reales) -------
  const PHP_EVENTOS = <?php echo $EVENTOS_JSON ?: '{"items":[], "imgCol": null, "error":"sin_datos"}'; ?>;

  // ------- Utilidades -------
  const $ = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);
  const fmtMoney = n => Number(n||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
  const todayISO = () => new Date().toISOString().slice(0,10);

  // Estado
  const state = { eventos: [], promos: [], editingId: null };

  // ===== Helpers imágenes (miniatura) =====
  function unique(a){ return [...new Set(a.filter(Boolean))]; }
  function testImage(url, timeout=2000){
    return new Promise(resolve=>{
      const img = new Image();
      const t = setTimeout(()=>{ img.src=''; resolve(false); }, timeout);
      img.onload = ()=>{ clearTimeout(t); resolve(true); };
      img.onerror = ()=>{ clearTimeout(t); resolve(false); };
      img.src = url;
    });
  }
  function buildImageCandidates(src){
    if(!src) return [];
    if(/^https?:\/\//i.test(src) || src.startsWith('data:')) return [src];
    const APP_BASE = (window.APP_BASE || '/').replace(/\/+$/,'') + '/';
    const asAbsolute = src.startsWith('/') ? [src] : [];
    const dirs = ['', 'imagenes/', 'img/', 'uploads/','evt_interfaz/','evt_interfaz/imagenes/','evt_interfaz/uploads/'];
    const rel = dirs.map(d=> d+src);
    const fromBase = dirs.map(d => APP_BASE + d + src);
    const parent = dirs.map(d => '../' + d + src);
    const grand = dirs.map(d => '../../' + d + src);
    return unique([...asAbsolute, ...rel, ...parent, ...grand, ...fromBase]);
  }
  async function resolveThumbURL(src){ for(const u of buildImageCandidates(src)){ if(await testImage(u)) return u; } return ''; }

  // ===== API Promos =====
  const API = 'promos_api.php';
  async function apiListPromos(){
    const r = await fetch(API);
    const j = await r.json();
    if(!j.ok) throw new Error(j.error||'Error API');
    return j.items||[];
  }
  async function apiCreatePromo(p){
    const r = await fetch(API, {
      method:'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(p)
    });
    const j = await r.json();
    if(!j.ok) throw new Error(j.error||'No se pudo crear');
    return j.id_promocion;
  }
  async function apiUpdatePromo(id, p){
    const r = await fetch(API + '?id='+encodeURIComponent(id), {
      method:'PUT',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(p)
    });
    const j = await r.json();
    if(!j.ok) throw new Error(j.error||'No se pudo actualizar');
  }
  async function apiDeletePromo(id){
    const r = await fetch(API + '?id='+encodeURIComponent(id), { method:'DELETE' });
    const j = await r.json();
    if(!j.ok) throw new Error(j.error||'No se pudo eliminar');
  }
  function exportCSV(){ window.open(API+'?export=1','_blank'); }

  // ===== Navegación =====
  function go(section){
    $$('#menu .link').forEach(l=>l.classList.toggle('active', l.dataset.section===section));
    $$('section').forEach(s=>s.classList.toggle('active', s.id===section));
    $('#title').textContent = section.charAt(0).toUpperCase()+section.slice(1);
    if(section==='eventos'){ renderEventosCards(); renderEventosTable(); }
    if(section==='descuentos'){ renderPromosTable(); }
    if(section==='reportes'){ renderReportes(); }
  }

  // ===== Inicial =====
  (async function init(){
    // Eventos desde PHP
    try {
      const imgCol = PHP_EVENTOS.imgCol;
      state.eventos = (PHP_EVENTOS.items || []).map(e => {
        const tipoNum = Number(e.tipo ?? 0);
        const aforo = (tipoNum === 1) ? 420 : (tipoNum === 2 ? 540 : 0);
        const inicio = (e.inicio_venta || '').toString();
        const cierre = (e.cierre_venta || '').toString();
        return {
          id: e.id_evento ?? null,
          nombre: e.titulo || 'Evento',
          inicio, cierre,
          fecha: inicio.slice(0,10),
          tipo: tipoNum,
          aforo,
          finalizado: Number(e.finalizado) === 1,
          imagen: imgCol ? (e[imgCol] || '') : (e.imagen || e.foto || e.ruta_imagen || e.img || e.thumbnail || '')
        };
      });
    } catch { state.eventos = []; }

    // Llenar select de eventos
    const sel = $('#ev'); sel.innerHTML = '';
    state.eventos.forEach(e=>{
      const opt = document.createElement('option');
      opt.value = e.id ?? '';
      opt.textContent = `${e.nombre} — ${e.fecha||'s/f'}`;
      sel.appendChild(opt);
    });

    // Cargar promociones desde BD
    await loadPromos();

    // Listeners
    $$('#menu .link').forEach(l=>l.addEventListener('click', ()=> go(l.dataset.section)));
    $('#btn-preview').addEventListener('click', previewPromo);
    $('#form-descuento').addEventListener('submit', savePromo);
    $('#btn-clear').addEventListener('click', ()=> {
      $('#preview').textContent='Completa el formulario y haz clic en “Vista previa”.';
      state.editingId = null;
    });
    $('#btn-export').addEventListener('click', exportCSV);
    $('#btn-refresh').addEventListener('click', async ()=>{ await loadPromos(); renderReportes(); alert('Reportes recalculados'); });

    // Primer render
    await renderEventosCards();
    renderEventosTable();
    renderPromosTable();
    renderReportes();
    go('descuentos');
  })();

  // ===== Descuentos (UI) =====
  function calcularFinal(precio, modo, valor){
    precio = parseFloat(precio||0); valor = parseFloat(valor||0);
    return Math.max(0, modo==='porcentaje' ? precio*(1-valor/100) : precio-valor);
  }
  function previewPromo(){
    const ev = state.eventos.find(e=> (e.id ?? '') == ($('#ev').value ?? ''));
    const tipo = $('#tipo').value;
    const precio = $('#precio').value;
    const modo = $('#modo').value;
    const valor = $('#valor').value;
    const desde = $('#desde').value || todayISO();
    const hasta = $('#hasta').value || todayISO();
    const minq = $('#minq').value || 1;
    const cond = $('#cond').value.trim();
    if(!ev || !precio || !valor){ alert('Selecciona evento, precio base y valor de descuento.'); return; }
    const final = calcularFinal(precio, modo, valor);
    $('#preview').innerHTML = `
      <div><strong>${ev.nombre}</strong> — <span class="muted">${ev.fecha || '—'} · ${tipo}</span></div>
      <div style="margin:6px 0">
        <span class="pill info">Base: ${fmtMoney(precio)}</span>
        <span class="pill info">Desc.: ${modo==='porcentaje'? valor+'%': fmtMoney(valor)}</span>
        <span class="pill ok">Final: ${fmtMoney(final)}</span>
      </div>
      <div class="muted">Vigencia: ${desde} a ${hasta} · Mín. ${minq}${cond? ' · '+cond:''}</div>
    `;
  }

  async function loadPromos(){
    const list = await apiListPromos();
    // Normalizar hacia el front
    state.promos = list.map(p => ({
      id_promocion: p.id_promocion,
      evId: p.id_evento,
      evNombre: p.evento || (state.eventos.find(e=>e.id==p.id_evento)?.nombre || 'Evento'),
      evFecha: (state.eventos.find(e=>e.id==p.id_evento)?.fecha || ''),
      tipo: p.tipo_boleto,
      precio: Number(p.precio_base),
      modo: p.modo,
      valor: Number(p.valor),
      desde: p.desde,
      hasta: p.hasta,
      minq: Number(p.min_cantidad),
      cond: p.condiciones || ''
    }));
  }

  async function savePromo(e){
    e.preventDefault();
    const ev = state.eventos.find(e=> (e.id ?? '') == ($('#ev').value ?? ''));
    if(!ev){ alert('Selecciona un evento válido.'); return; }

    const payload = {
      id_evento: ev.id,
      tipo_boleto: $('#tipo').value,
      precio_base: +($('#precio').value||0),
      modo: $('#modo').value, // 'porcentaje' | 'fijo'
      valor: +($('#valor').value||0),
      desde: $('#desde').value || todayISO(),
      hasta: $('#hasta').value || todayISO(),
      min_cantidad: +($('#minq').value||1),
      condiciones: $('#cond').value.trim()
    };
    if(!payload.precio_base || !payload.valor){ alert('Precio base y valor de descuento son obligatorios.'); return; }

    try{
      if(state.editingId){ // UPDATE
        await apiUpdatePromo(state.editingId, payload);
      } else {            // CREATE
        await apiCreatePromo(payload);
      }
      state.editingId = null;
      $('#form-descuento').reset();
      $('#preview').textContent = 'Descuento guardado.';
      await loadPromos();
      renderPromosTable(); renderReportes();
    }catch(err){
      alert('Error al guardar: '+err.message);
    }
  }

  function vigente(p){
    const hoy = todayISO();
    return (p.desde<=hoy && p.hasta>=hoy);
  }

  function renderPromosTable(){
    const tb = document.querySelector('#tabla-promos tbody');
    tb.innerHTML = '';
    if(state.promos.length===0){
      tb.innerHTML = `<tr><td colspan="9" class="muted">Sin promociones registradas.</td></tr>`;
      return;
    }
    state.promos.slice().sort((a,b)=>a.desde.localeCompare(b.desde)).forEach(p=>{
      const final = calcularFinal(p.precio, p.modo, p.valor);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${p.evNombre}</td>
        <td>${p.tipo}</td>
        <td>${fmtMoney(p.precio)}</td>
        <td>${p.modo==='porcentaje'? p.valor+'%': fmtMoney(p.valor)}</td>
        <td><strong>${fmtMoney(final)}</strong></td>
        <td>${p.desde} → ${p.hasta}</td>
        <td>${p.minq}</td>
        <td>${vigente(p)?'<span class="pill ok">Vigente</span>':'—'}</td>
        <td>
          <button class="btn muted" data-act="edit" data-id="${p.id_promocion}"><i class="bi bi-pencil-square"></i></button>
          <button class="btn danger" data-act="del" data-id="${p.id_promocion}"><i class="bi bi-trash3"></i></button>
        </td>
      `;
      tb.appendChild(tr);
    });
    tb.querySelectorAll('button[data-act]').forEach(btn=>{
      btn.onclick = async ()=>{
        const id = +btn.dataset.id;
        const act = btn.dataset.act;
        const p = state.promos.find(x=>x.id_promocion===id);
        if(!p) return;
        if(act==='del'){
          if(confirm('¿Eliminar esta promoción?')){
            try{
              await apiDeletePromo(id);
              await loadPromos(); renderPromosTable(); renderReportes();
            }catch(err){ alert('No se pudo eliminar: '+err.message); }
          }
        }else if(act==='edit'){
          // Cargar datos al formulario para editar
          $('#ev').value = p.evId ?? '';
          $('#tipo').value = p.tipo;
          $('#precio').value = p.precio;
          $('#modo').value = p.modo;
          $('#valor').value = p.valor;
          $('#desde').value = p.desde;
          $('#hasta').value = p.hasta;
          $('#minq').value = p.minq;
          $('#cond').value = p.cond;
          state.editingId = id;
          go('descuentos');
          previewPromo();
        }
      };
    });
  }

  // ===== Export CSV desde servidor =====
  document.querySelector('#btn-export')?.addEventListener('click', exportCSV);

  // ===== Eventos (cards + tabla) =====
  async function renderEventosCards(){
    const grid = document.querySelector('#evt-grid'); grid.innerHTML = '';
    if(!state.eventos.length){
      grid.innerHTML = `<div class="muted">No se encontraron eventos.</div>`;
      return;
    }
    for(const e of state.eventos){
      const div = document.createElement('div');
      div.className = 'evt-card';
      div.innerHTML = `
        <div class="evt-thumb" style="display:flex;align-items:center;justify-content:center;font-size:11px;color:#98a2b3">Cargando</div>
        <h4 class="evt-title">${e.nombre}</h4>
        <p class="evt-meta">${(e.inicio || '').slice(0,16)} ${e.cierre ? '→ '+(e.cierre.slice(0,16)) : ''}</p>
        <p class="evt-meta">Tipo: ${e.tipo || '—'} · Aforo: ${e.aforo} ${e.finalizado ? '<span class="pill" style="background:#ffe8e6;color:#a23b2a; margin-left:6px">Finalizado</span>' : '<span class="pill ok" style="margin-left:6px">Activo</span>'}</p>
      `;
      const url = await resolveThumbURL(e.imagen || '');
      const ph = div.querySelector('.evt-thumb');
      if(url){
        const img = document.createElement('img');
        img.className = 'evt-thumb';
        img.alt = 'thumb';
        img.src = url;
        ph.replaceWith(img);
      }else{
        ph.textContent = 'Sin\nfoto';
      }
      grid.appendChild(div);
    }
  }
  function renderEventosTable(){
    const tb = document.querySelector('#tabla-eventos tbody'); tb.innerHTML='';
    state.eventos.forEach(e=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${e.nombre}</td>
        <td>${(e.inicio||'').toString().slice(0,16)}</td>
        <td>${(e.cierre||'').toString().slice(0,16)}</td>
        <td>${e.tipo || '—'}</td>
        <td>${e.aforo}</td>
        <td>${e.finalizado ? 'Finalizado' : 'Activo'}</td>
      `;
      tb.appendChild(tr);
    });
  }

  // ===== Reportes =====
  function renderReportes(){
    const finals = state.promos.map(p => calcularFinal(p.precio, p.modo, p.valor));
    const avg = finals.length ? finals.reduce((a,b)=>a+b,0)/finals.length : 0;
    const vig = state.promos.filter(p=> (p.desde<=todayISO() && p.hasta>=todayISO())).length;
    $('#rep-tot').textContent = state.promos.length;
    $('#rep-vig').textContent = vig;
    $('#rep-avg').textContent = fmtMoney(avg);
  }
</script>

</body>
</html>
