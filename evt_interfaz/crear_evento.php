<?php
session_start();
include "../conexion.php"; // Incluir conexión para la verificación de sesión

// ==================================================================
// VERIFICACIÓN DE SESIÓN (para acceso directo)
// ==================================================================
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px; color: red;"><h1>Acceso Denegado</h1><p>No tiene permiso para ver esta página.</p></div>');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Evento</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    :root { --primary-color: #2563eb; --primary-dark: #1e40af; --success-color: #10b981; --danger-color: #ef4444; --bg-primary: #f8fafc; --bg-secondary: #ffffff; --text-primary: #0f172a; --text-secondary: #64748b; --border-color: #e2e8f0; --radius-sm: 8px; --radius-lg: 16px; }
    body { background: var(--bg-primary); color: var(--text-primary); font-family: 'Inter', sans-serif; padding: 30px; min-height: 100vh; }
    .main-wrapper { max-width: 850px; margin: 0 auto; }
    .card { background: var(--bg-secondary); border-radius: var(--radius-lg); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 40px; border: 1px solid var(--border-color); }
    .form-control, .form-select { border-radius: var(--radius-sm); padding: 12px; border: 1px solid var(--border-color); background: var(--bg-primary); transition: all 0.2s; }
    .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(37,99,235,0.1); }
    .btn { border-radius: var(--radius-sm); padding: 12px 24px; font-weight: 600; border: none; transition: all 0.2s; }
    .btn-primary { background: var(--primary-color); color: white; } .btn-primary:hover { background: var(--primary-dark); }
    .btn-secondary { background: #fff; color: var(--text-primary); border: 1px solid var(--border-color); }
    .input-error { border-color: var(--danger-color) !important; background: #fef2f2 !important; }
    .tooltip-error { color: var(--danger-color); font-size: 0.9em; margin-top: 5px; display: none; font-weight: 600; align-items: center; gap: 5px; }
    .tooltip-error::before { content: "\F659"; font-family: "bootstrap-icons"; }
    #lista-funciones { background: var(--bg-primary); border: 2px dashed var(--border-color); border-radius: var(--radius-sm); padding: 15px; min-height: 60px; display: flex; flex-wrap: wrap; gap: 10px; }
    .funcion-item { background: #fff; padding: 6px 12px; border-radius: 20px; border: 1px solid var(--primary-color); color: var(--primary-color); font-weight: 600; display: flex; align-items: center; gap: 8px; }
    .funcion-item button { background: none; border: none; color: var(--danger-color); font-size: 1.2em; line-height: 1; padding: 0; opacity: 0.6; cursor: pointer; }
    .funcion-item button:hover { opacity: 1; transform: scale(1.2); }
</style>
</head>
<body>
<div class="main-wrapper">
    <a href="act_evento.php" class="btn btn-secondary mb-4"><i class="bi bi-arrow-left me-2"></i>Volver a Eventos Activos</a>
    <div class="card">
        <h2 class="text-primary fw-bold mb-4 pb-3 border-bottom"><i class="bi bi-plus-circle-fill me-3"></i>Crear Evento</h2>
        <form id="fCreate" action="procesar_evento.php" method="POST" enctype="multipart/form-data">
            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label fw-bold">Título</label>
                    <input type="text" id="tit" name="titulo" class="form-control fw-bold" required>
                </div>
                <div class="col-12">
                    <div class="p-4 bg-light rounded-4 border">
                        <label class="fw-bold mb-3"><i class="bi bi-calendar-week me-2"></i>Funciones</label>
                        <div class="input-group mb-2">
                            <input type="text" id="fDate" class="form-control" placeholder="Fecha" readonly>
                            <input type="text" id="fTime" class="form-control" placeholder="Hora" readonly style="max-width:120px">
                            <button type="button" id="fAdd" class="btn btn-success" disabled><i class="bi bi-plus-lg"></i></button>
                        </div>
                        <div id="ttFunc" class="tooltip-error mb-3"></div>
                        <div id="lista-funciones"><p id="noFunc" class="text-muted m-0 fst-italic"><i class="bi bi-inbox me-2"></i>Sin funciones.</p></div>
                        <div id="hidFunc"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="fw-bold">Inicio Venta</label><input type="text" id="ini" name="inicio_venta" class="form-control" readonly required>
                    <div id="ttIni" class="tooltip-error"></div>
                </div>
                <div class="col-md-6">
                    <label class="fw-bold">Cierre Venta</label><input type="text" id="fin" name="cierre_venta" class="form-control" readonly required>
                    <div id="ttFin" class="tooltip-error"></div>
                </div>
                <div class="col-12">
                    <label class="fw-bold">Descripción</label>
                    <textarea id="desc" name="descripcion" class="form-control" rows="3" required></textarea>
                    <div id="ttDesc" class="tooltip-error"></div>
                </div>
                <div class="col-md-7">
                    <label class="fw-bold">Imagen</label>
                    <input type="file" id="img" name="imagen" class="form-control" accept="image/*" required>
                    <div id="ttImg" class="tooltip-error"></div>
                </div>
                <div class="col-md-5">
                    <label class="fw-bold">Escenario</label>
                    <select id="tipo" name="tipo" class="form-select" required>
                        <option value="">-- Selecciona --</option><option value="1">🎭 Teatro (420)</option><option value="2">🚶 Pasarela (540)</option>
                    </select>
                    <div id="ttTipo" class="tooltip-error"></div>
                </div>
            </div>
            <input type="hidden" name="precios[General]" value="80"><input type="hidden" name="precios[Discapacitado]" value="80">
            <hr class="my-4">
            <button type="submit" id="bSub" class="btn btn-primary w-100 py-3 fs-5" disabled><i class="bi bi-check-lg me-2"></i>Crear Evento</button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script><script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
flatpickr.localize(flatpickr.l10ns.es); const now=new Date(); let funcs=[];

const els={
    add:document.getElementById('fAdd'),sub:document.getElementById('bSub'),
    list:document.getElementById('lista-funciones'),hid:document.getElementById('hidFunc'),no:document.getElementById('noFunc'),
    ttF:document.getElementById('ttFunc'),ttI:document.getElementById('ttIni'),ttE:document.getElementById('ttFin'),
    ini:document.getElementById('ini'),fin:document.getElementById('fin'),
    desc: document.getElementById('desc'), img: document.getElementById('img'), tipo: document.getElementById('tipo'),
    ttDesc: document.getElementById('ttDesc'), ttImg: document.getElementById('ttImg'), ttTipo: document.getElementById('ttTipo')
};

const fpD=flatpickr("#fDate",{minDate:"today",onChange:function(s,d){
    if(d === new Date().toISOString().split('T')[0]) fpT.set('minTime', new Date().setMinutes(new Date().getMinutes()+5));
    else fpT.set('minTime', null);
    check();
}});
const fpT=flatpickr("#fTime",{enableTime:true,noCalendar:true,dateFormat:"H:i",time_24hr:true,minuteIncrement:15,onChange:check});
const fpI=flatpickr("#ini",{enableTime:true,minDate:now,onChange:val}), fpE=flatpickr("#fin",{enableTime:true,minDate:now,onChange:val});

function check(){els.add.disabled=!(fpD.selectedDates.length&&fpT.selectedDates.length);}
els.add.onclick=()=>{
    let dt=new Date(fpD.input.value+'T'+fpT.input.value);
    if(dt<=new Date(now.getTime()+6e4)) return alert("La fecha y hora deben ser futuras.");
    if(funcs.some(d=>d.getTime()===dt.getTime())) return alert("Esta función ya existe.");
    funcs.push(dt); funcs.sort((a,b)=>a-b); fpD.clear(); fpT.clear(); check(); upd();
};

function upd(){
    els.list.innerHTML=''; els.hid.innerHTML='';
    if(!funcs.length){ 
        els.list.appendChild(els.no); 
        fpI.set('maxDate',null); 
        fpE.set('minDate',now); fpE.clear();
    }
    else{
        funcs.forEach((d,i)=>{
            els.list.innerHTML+=`<div class="funcion-item">${d.toLocaleString('es-ES',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'})}<button type="button" onclick="del(${i})">×</button></div>`;
            els.hid.innerHTML+=`<input type="hidden" name="funciones[]" value="${d.getFullYear()}-${(d.getMonth()+1+'').padStart(2,'0')}-${(d.getDate()+'').padStart(2,'0')} ${(d.getHours()+'').padStart(2,'0')}:${(d.getMinutes()+'').padStart(2,'0')}:00">`;
        });
        fpI.set('maxDate', new Date(funcs[0].getTime() - 60000));
        const cierreAuto = new Date(funcs[funcs.length-1].getTime() + 7200000);
        fpE.set('minDate', cierreAuto);
        fpE.setDate(cierreAuto, true);
    }
    val();
}
window.del=i=>{funcs.splice(i,1);upd();};

function val(){
    let ok=true; 
    [els.ttF, els.ttI, els.ttE, els.ttDesc, els.ttImg, els.ttTipo].forEach(e=> { if(e) e.style.display='none'; });
    document.querySelectorAll('.input-error').forEach(e=>e.classList.remove('input-error'));
    
    if(!document.getElementById('tit').value.trim()) ok=false;
    
    if(!funcs.length){ err(els.ttF,null,'Añade al menos una función.'); ok=false; }
    
    if(!fpI.selectedDates.length){ if(funcs.length) err(els.ttI,els.ini,'Requerido.'); ok=false; }
    else if(funcs.length&&fpI.selectedDates[0]>=funcs[0]){ err(els.ttI,els.ini,'Debe ser antes de la 1ª función.'); ok=false; }
    if(!fpE.selectedDates.length){ if(funcs.length) err(els.ttE,els.fin,'Requerido.'); ok=false; }
    else if(fpI.selectedDates.length&&fpE.selectedDates[0]<=fpI.selectedDates[0]){ err(els.ttE,els.fin,'Debe ser posterior al inicio.'); ok=false; }
    
    if(!els.desc.value.trim()){ err(els.ttDesc, els.desc, 'La descripción es obligatoria.'); ok=false; }
    if(!els.img.files.length){ err(els.ttImg, els.img, 'La imagen es obligatoria.'); ok=false; }
    if(!els.tipo.value){ err(els.ttTipo, els.tipo, 'Selecciona un escenario.'); ok=false; }

    els.sub.disabled=!ok; return ok;
}

function err(t,i,m){ t.textContent=m; t.style.display='flex'; if(i) i.classList.add('input-error'); }
['tit','desc','img','tipo'].forEach(id=>document.getElementById(id).addEventListener(id==='img'||id==='tipo'?'change':'input',val));
</script>
</body>
</html>