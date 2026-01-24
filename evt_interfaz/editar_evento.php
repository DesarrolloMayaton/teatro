<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include "../conexion.php";
if(file_exists("../transacciones_helper.php")) { require_once "../transacciones_helper.php"; }
require_once __DIR__ . "/../api/registrar_cambio.php";

// Verificar permisos
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="display:flex;height:100vh;align-items:center;justify-content:center;font-family:sans-serif;color:#ef4444;"><h1>Acceso Denegado</h1></div>');
}

// Variables de control
$errores_php = [];
$modo_reactivacion = isset($_GET['modo_reactivacion']) && $_GET['modo_reactivacion'] == 1;
$id_evento = $_GET['id'] ?? 0;

// ==================================================================
// 2. PROCESADOR (POST)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. GUARDAR / ACTUALIZAR
    if (isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
        $conn->begin_transaction();
        try {
            $titulo = trim($_POST['titulo']);
            $desc = trim($_POST['descripcion']);
            $tipo = $_POST['tipo'];
            $ini = $_POST['inicio_venta'];
            $fin = $_POST['cierre_venta'];
            $img = $_POST['imagen_actual'];

            // Subida de imagen
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
                 $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                 if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                     if (!is_dir("imagenes")) mkdir("imagenes", 0755, true);
                     $ruta = "imagenes/evt_" . time() . "." . $ext;
                     if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
                         $img = $ruta;
                         if ($_POST['imagen_actual'] && file_exists($_POST['imagen_actual'])) unlink($_POST['imagen_actual']);
                     }
                 }
            }

            // --- LÓGICA DUAL: REACTIVAR vs ACTUALIZAR ---
            if ($modo_reactivacion) {
                // 1. Traer mapa del histórico
                $res_old = $conn->query("SELECT mapa_json FROM trt_historico_evento.evento WHERE id_evento = $id_evento");
                $mapa_json = $res_old->fetch_object()->mapa_json;

                // 2. Insertar en Activos
                $stmt = $conn->prepare("INSERT INTO evento (titulo, descripcion, imagen, tipo, inicio_venta, cierre_venta, finalizado, mapa_json) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
                $stmt->bind_param("sssisss", $titulo, $desc, $img, $tipo, $ini, $fin, $mapa_json);
                $stmt->execute();
                $new_id = $conn->insert_id;
                $stmt->close();

                // 3. Copiar dependencias
                $conn->query("INSERT INTO categorias (id_evento, nombre_categoria, precio, color) SELECT $new_id, nombre_categoria, precio, color FROM trt_historico_evento.categorias WHERE id_evento = $id_evento");
                
                // 4. Funciones nuevas
                $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora) VALUES (?, ?)");
                foreach ($_POST['funciones'] as $fh) { $stmt_f->bind_param("is", $new_id, $fh); $stmt_f->execute(); }

                // 5. Borrar del histórico
                $conn->query("DELETE FROM trt_historico_evento.boletos WHERE id_evento = $id_evento");
                $conn->query("DELETE FROM trt_historico_evento.promociones WHERE id_evento = $id_evento");
                $conn->query("DELETE FROM trt_historico_evento.categorias WHERE id_evento = $id_evento");
                $conn->query("DELETE FROM trt_historico_evento.funciones WHERE id_evento = $id_evento");
                $conn->query("DELETE FROM trt_historico_evento.evento WHERE id_evento = $id_evento");

            } else {
                // Edición Normal
                $stmt = $conn->prepare("UPDATE evento SET titulo=?, inicio_venta=?, cierre_venta=?, descripcion=?, imagen=?, tipo=? WHERE id_evento=?");
                $stmt->bind_param("sssssii", $titulo, $ini, $fin, $desc, $img, $tipo, $id_evento);
                $stmt->execute();
                
                $conn->query("DELETE FROM funciones WHERE id_evento = $id_evento");
                $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora) VALUES (?, ?)");
                foreach ($_POST['funciones'] as $fh) { $stmt_f->bind_param("is", $id_evento, $fh); $stmt_f->execute(); }
            }

            $conn->commit();
            if(function_exists('registrar_transaccion')) registrar_transaccion('evento_guardar', "Guardó evento: $titulo");
            
            // Notificar cambio para auto-actualización en tiempo real
            $evt_id = $modo_reactivacion ? $new_id : $id_evento;
            registrar_cambio('evento', $evt_id, null, ['accion' => $modo_reactivacion ? 'reactivar' : 'editar']);
            registrar_cambio('funcion', $evt_id, null, ['accion' => 'modificar', 'cantidad' => count($_POST['funciones'])]);

            // PANTALLA DE ÉXITO ANIMADA
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
            <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <style>
                body { background-color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: system-ui, sans-serif; overflow: hidden; }
                .success-card { background: white; border-radius: 24px; padding: 40px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.08); max-width: 380px; width: 90%; animation: popIn 0.5s cubic-bezier(0.17, 0.67, 0.33, 1.15) forwards; }
                .icon-circle { width: 80px; height: 80px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 20px; }
                .progress-track { height: 6px; background: #f1f5f9; border-radius: 3px; margin-top: 30px; overflow: hidden; }
                .progress-fill { height: 100%; background: #16a34a; width: 0; transition: width 1.2s linear; }
                @keyframes popIn { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
                @keyframes fadeOut { to { opacity: 0; transform: scale(0.95); } }
                body.exiting { animation: fadeOut 0.4s ease-in forwards; }
            </style>
            </head>
            <body>
                <div class="success-card">
                    <div class="icon-circle"><i class="bi bi-check-lg"></i></div>
                    <h4 class="fw-bold mb-2">¡Operación Exitosa!</h4>
                    <p class="text-muted small mb-0">Los datos se han guardado correctamente.</p>
                    <div class="progress-track"><div class="progress-fill" id="pBar"></div></div>
                    <p class="text-muted mt-2" style="font-size: 0.75rem; font-weight: 700;">REDIRIGIENDO...</p>
                </div>
                <script>
                    setTimeout(() => document.getElementById('pBar').style.width = '100%', 50);
                    localStorage.setItem("evt_upd", Date.now());
                    setTimeout(() => { 
                        document.body.classList.add('exiting'); 
                        // Al guardar con éxito, siempre vamos a Activos
                        setTimeout(() => { window.parent.location.href = "index.php?tab=activos"; }, 350); 
                    }, 1300); 
                </script>
            </body>
            </html>
            <?php exit;

        } catch (Exception $e) { $conn->rollback(); $errores_php[] = "Error DB: " . $e->getMessage(); }
    }
}

// ==================================================================
// 3. CARGA DE DATOS (GET)
// ==================================================================
if (!$id_evento || !is_numeric($id_evento)) { header('Location: act_evento.php'); exit; }

$tabla_evt = $modo_reactivacion ? "trt_historico_evento.evento" : "evento";
$tabla_fun = $modo_reactivacion ? "trt_historico_evento.funciones" : "funciones";

$res = $conn->query("SELECT * FROM $tabla_evt WHERE id_evento = $id_evento");
$evento = $res->fetch_assoc();
if (!$evento) { header('Location: act_evento.php'); exit; }

$funciones_existentes = [];
$res_f = $conn->query("SELECT fecha_hora FROM $tabla_fun WHERE id_evento = $id_evento ORDER BY fecha_hora ASC");
while($f = $res_f->fetch_assoc()) { $funciones_existentes[] = new DateTime($f['fecha_hora']); }

// Fechas: Si es reactivación, vacías para obligar a poner nuevas.
$defaultVenta = $modo_reactivacion ? '' : date('Y-m-d H:i', strtotime($evento['inicio_venta']));
$defaultCierre = $modo_reactivacion ? '' : date('Y-m-d H:i', strtotime($evento['cierre_venta']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editor</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    :root { --primary-color: #2563eb; --primary-dark: #1e40af; --bg-primary: #f8fafc; --text-primary: #0f172a; --border-color: #e2e8f0; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, var(--bg-primary), #e2e8f0); color: var(--text-primary); padding: 30px 20px; min-height: 100vh; opacity: 0; transition: opacity 0.4s ease; }
    body.loaded { opacity: 1; }
    body.exiting { opacity: 0; }

    .main-wrapper { max-width: 850px; margin: 0 auto; }
    .card { background: white; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); padding: 40px; border: 1px solid var(--border-color); }
    
    .form-control, .form-select { border-radius: 8px; padding: 12px; border: 1px solid var(--border-color); background: #fff; }
    .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(37,99,235,0.1); }
    
    .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary { background: var(--primary-color); color: white; } .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
    .btn-secondary { background: #fff; color: var(--text-primary); border: 1px solid var(--border-color); } .btn-secondary:hover { background: #f1f5f9; }
    .btn-danger { background: #ef4444; color: white; } .btn-danger:hover { background: #dc2626; }

    .input-error { border-color: #ef4444 !important; background: #fef2f2 !important; }
    .tooltip-error { color: #ef4444; font-size: 0.85em; margin-top: 5px; display: none; font-weight: 600; }
    
    #lista-funciones { background: #f8fafc; border: 2px dashed var(--border-color); border-radius: 8px; padding: 15px; min-height: 70px; display: flex; flex-wrap: wrap; gap: 10px; }
    .funcion-item { background: white; padding: 6px 12px; border-radius: 20px; border: 1px solid var(--primary-color); color: var(--primary-color); font-weight: 600; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .funcion-item button { background: none; border: none; color: #ef4444; font-size: 1.1em; padding: 0; cursor: pointer; line-height: 1; }
    .funcion-item button:hover { transform: scale(1.2); }

    .img-preview { width: 100px; height: 140px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color); margin-top: 10px; }
</style>
</head>
<body>

<div class="main-wrapper">
    <div class="mb-4">
        <button onclick="abrirModalCancelar()" class="btn btn-secondary shadow-sm">
            <i class="bi bi-arrow-left"></i> 
            <?= $modo_reactivacion ? 'Cancelar Reactivación' : 'Volver a Eventos' ?>
        </button>
    </div>

    <div class="card">
        <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
            <h2 class="m-0 text-primary">
                <i class="bi <?= $modo_reactivacion ? 'bi-arrow-counterclockwise' : 'bi-pencil-square' ?> me-3"></i>
                <?= $modo_reactivacion ? 'Reactivar Evento' : 'Editar Evento' ?>
            </h2>
        </div>

        <?php if($errores_php): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4">
                <ul class="m-0 ps-3"><?php foreach($errores_php as $e) echo "<li>$e</li>"; ?></ul>
            </div>
        <?php endif; ?>

        <form id="fEdit" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="actualizar">
            <input type="hidden" name="id_evento" value="<?= $id_evento ?>">
            <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($evento['imagen']) ?>">

            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label fw-bold">Título del Evento</label>
                    <input type="text" id="tit" name="titulo" class="form-control form-control-lg fw-bold" value="<?= htmlspecialchars($evento['titulo']) ?>" required>
                </div>

                <div class="col-12">
                    <div class="p-4 bg-light rounded-4 border">
                        <label class="fw-bold mb-3"><i class="bi bi-calendar-week me-2"></i>Gestión de Funciones</label>
                        <div class="input-group mb-3 shadow-sm">
                            <input type="text" id="fDate" class="form-control" placeholder="Selecciona Fecha" readonly>
                            <input type="text" id="fTime" class="form-control" placeholder="Hora" readonly style="max-width:130px">
                            <button type="button" id="fAdd" class="btn btn-success" disabled><i class="bi bi-plus-lg"></i> Agregar</button>
                        </div>
                        <div id="ttFunc" class="tooltip-error mb-2"></div>
                        <div id="lista-funciones">
                            <p id="noFunc" class="text-muted m-0 w-100 text-center fst-italic small">
                                <i class="bi bi-inbox me-2"></i>No hay funciones asignadas.
                            </p>
                        </div>
                        <div id="hidFunc"></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="fw-bold">Inicio Venta</label>
                    <input type="text" id="ini" name="inicio_venta" class="form-control" value="<?= $defaultVenta ?>" readonly required placeholder="Selecciona fecha">
                    <div id="ttIni" class="tooltip-error"></div>
                </div>
                
                <div class="col-md-6">
                    <label class="fw-bold" style="color: #94a3b8;">Cierre Venta (Automático)</label>
                    <input type="text" id="fin" name="cierre_venta" class="form-control" value="<?= $defaultCierre ?>" readonly style="background-color: #e9ecef; color: #1e293b; cursor: not-allowed;">
                    <div class="form-text small" style="color: #64748b;"><i class="bi bi-info-circle"></i> Se calcula 2 horas después de la última función.</div>
                </div>
                
                <div class="col-12">
                    <label class="fw-bold">Descripción</label>
                    <textarea id="desc" name="descripcion" class="form-control" rows="4" required><?= htmlspecialchars($evento['descripcion']) ?></textarea>
                    <div id="ttDesc" class="tooltip-error"></div>
                </div>
                
                <div class="col-md-7">
                    <label class="fw-bold">Imagen Promocional</label>
                    <input type="file" id="img" name="imagen" class="form-control" accept="image/*">
                    <?php if($evento['imagen']): ?>
                        <div class="mt-3 p-2 border rounded bg-light d-inline-block">
                            <div class="small fw-bold text-muted mb-1">Imagen Actual:</div>
                            <img src="../evt_interfaz/<?= htmlspecialchars($evento['imagen']) ?>" class="img-preview" onerror="this.style.display='none'">
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-5">
                    <label class="fw-bold">Tipo de Escenario</label>
                    <select id="tipo" name="tipo" class="form-select" required>
                        <option value="1" <?= $evento['tipo']==1?'selected':'' ?>>🎭 Teatro (420 Butacas)</option>
                        <option value="2" <?= $evento['tipo']==2?'selected':'' ?>>🚶 Pasarela (540 Butacas)</option>
                    </select>
                    <div id="ttTipo" class="tooltip-error"></div>
                </div>
            </div>

            <hr class="my-5">
            
            <button type="submit" id="bSub" class="btn btn-primary w-100 py-3 fs-5 fw-bold shadow-sm" disabled>
                <?= $modo_reactivacion ? 'Confirmar Reactivación' : 'Guardar Cambios' ?>
            </button>
        </form>
    </div>
</div>

<div class="modal fade" id="modalCancelar" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">¿Salir sin guardar?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted">Si sales ahora, perderás todos los cambios no guardados. 
        <?php if($modo_reactivacion): ?> El evento no se reactivará y permanecerá en el historial. <?php endif; ?></p>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Seguir Editando</button>
        <button type="button" class="btn btn-danger fw-bold px-4" onclick="confirmarSalida()">Salir sin Guardar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script><script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('loaded');
    flatpickr.localize(flatpickr.l10ns.es);
    const now = new Date();
    // Cargar funciones existentes desde PHP
    let funcs = [<?php if(!$modo_reactivacion) { foreach($funciones_existentes as $f) echo "new Date('".$f->format('c')."'),"; } ?>];

    const els={add:document.getElementById('fAdd'),sub:document.getElementById('bSub'),list:document.getElementById('lista-funciones'),hid:document.getElementById('hidFunc'),no:document.getElementById('noFunc'),ttF:document.getElementById('ttFunc'),ttI:document.getElementById('ttIni'),ini:document.getElementById('ini'),fin:document.getElementById('fin'),desc:document.getElementById('desc'),img:document.getElementById('img'),tipo:document.getElementById('tipo'),ttDesc:document.getElementById('ttDesc'),ttImg:document.getElementById('ttImg'),ttTipo:document.getElementById('ttTipo')};
    
    const fpD=flatpickr("#fDate",{minDate:"today",onChange:(s,d)=>{
        if(d===new Date().toISOString().split('T')[0]) fpT.set('minTime',new Date().setMinutes(new Date().getMinutes()+5));
        else fpT.set('minTime',null);
        check();
    }});
    const fpT=flatpickr("#fTime",{enableTime:true,noCalendar:true,dateFormat:"H:i",time_24hr:true,minuteIncrement:15,onChange:check});
    
    // CAMBIO 1: Eliminado minDate: new Date() para permitir cualquier fecha
    const fpI=flatpickr("#ini",{enableTime:true, onChange:val}); 
    
    const fpE=flatpickr("#fin",{enableTime:true, clickOpens:false}); 

    function check(){ els.add.disabled=!(fpD.selectedDates.length && fpT.selectedDates.length); }

    els.add.onclick=()=>{
        if (!fpD.selectedDates[0] || !fpT.selectedDates[0]) return;
        
        let dt = new Date(fpD.selectedDates[0].getTime());
        dt.setHours(fpT.selectedDates[0].getHours());
        dt.setMinutes(fpT.selectedDates[0].getMinutes());
        dt.setSeconds(0);

        if(dt <= new Date(Date.now() + 60000)) return alert("La función debe ser futura.");
        if(funcs.some(d=>d.getTime()===dt.getTime())) return alert("Ya existe esta función.");
        
        funcs.push(dt); funcs.sort((a,b)=>a-b); fpD.clear(); fpT.clear(); check(); upd();
    };

    function upd(){
        els.list.innerHTML=''; els.hid.innerHTML='';
        if(!funcs.length){ 
            els.list.appendChild(els.no); 
            fpI.set('maxDate',null); 
            fpE.setDate(null); 
        } else {
            funcs.forEach((d,i)=>{
                const fechaStr = d.toLocaleDateString('es-ES', {day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'});
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                const hours = String(d.getHours()).padStart(2, '0');
                const mins = String(d.getMinutes()).padStart(2, '0');
                const sqlDate = `${year}-${month}-${day} ${hours}:${mins}:00`;

                els.list.innerHTML+=`<div class="funcion-item"><i class="bi bi-calendar-event"></i> ${fechaStr}<button type="button" onclick="del(${i})" class="btn-close ms-2" style="font-size:0.6em"></button></div>`;
                els.hid.innerHTML+=`<input type="hidden" name="funciones[]" value="${sqlDate}">`;
            });
            
            // CAMBIO 2: Limitar visualmente a 2 horas antes de la primera funcion
            const limiteVenta = new Date(funcs[0].getTime() - 7200000); 
            fpI.set('maxDate', limiteVenta);
            
            const ultima = funcs[funcs.length-1];
            const cierre = new Date(ultima.getTime() + 7200000);
            fpE.setDate(cierre, true);
        }
        val();
    }
    window.del=i=>{funcs.splice(i,1);upd();};

    function val(){
        let ok=true; 
        [els.ttF, els.ttI, els.ttDesc, els.ttImg, els.ttTipo].forEach(e=>{ if(e) e.style.display='none'; });
        
        if(!document.getElementById('tit').value.trim()) ok=false;
        if(!funcs.length){ err(els.ttF,null,'Añade funciones.'); ok=false; }
        
        // CAMBIO 3: Validación manual de las 2 horas de anticipacion
        if(!fpI.selectedDates.length){ 
             if (funcs.length) { err(els.ttI,els.ini,'Requerido.'); ok=false; }
        } else if(funcs.length) {
             const limite = funcs[0].getTime() - 7200000;
             const seleccion = fpI.selectedDates[0].getTime();
             if(seleccion > limite){ 
                 err(els.ttI,els.ini,'Debe ser al menos 2 horas antes de la 1ª función.'); ok=false; 
             }
        }

        if(!els.desc.value.trim()){ err(els.ttDesc,els.desc,'Descripción obligatoria.'); ok=false; }
        
        els.sub.disabled = !ok;
        return ok;
    }

    function err(t,i,m){ t.textContent=m; t.style.display='flex'; if(i) i.classList.add('input-error'); }
    
    ['tit','desc','img','tipo'].forEach(id=>document.getElementById(id).addEventListener(id==='img'||id==='tipo'?'change':'input',val));
    document.getElementById('fEdit').addEventListener('submit',e=>{ if(!val()){ e.preventDefault(); alert("Faltan campos."); } });
    
    upd();
});

// Funciones del Modal de Cancelar
const modalCancel = new bootstrap.Modal(document.getElementById('modalCancelar'));
function abrirModalCancelar() {
    modalCancel.show();
}

function confirmarSalida() {
    modalCancel.hide();
    goBack();
}

function goBack() {
    document.body.classList.remove('loaded');
    document.body.classList.add('exiting');
    
    const esReactivacion = <?= $modo_reactivacion ? 'true' : 'false' ?>;
    const tab = esReactivacion ? 'historial' : 'activos';
    
    setTimeout(() => window.parent.location.href = 'index.php?tab=' + tab, 350);
}
</script>
</body>
</html>