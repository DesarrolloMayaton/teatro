<?php
include "../conexion.php";
$errores_php = [];

// ==================================================================
// PROCESADOR DE ACCIONES (POST)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CASO A: ACTUALIZAR EVENTO (Tu lógica existente) ---
    if (isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
        // ... (Toda tu lógica de validación y actualización que ya funciona bien) ...
        $id_evento = $_POST['id_evento'];
        $titulo = trim($_POST['titulo']);
        // ... (resto de variables) ...
        $inicio_venta = $_POST['inicio_venta'];
        $cierre_venta = $_POST['cierre_venta'];
        $descripcion = trim($_POST['descripcion']);
        $tipo = $_POST['tipo'];
        $imagen_actual = $_POST['imagen_actual'];
        $imagen_ruta = $imagen_actual;

        // Validaciones (resumidas para no repetir todo el bloque anterior, mantenlas igual)
        if (empty($titulo)) $errores_php[] = "Falta título.";
        if (empty($inicio_venta) || empty($cierre_venta)) $errores_php[] = "Faltan fechas.";
        if (empty($_POST['funciones'])) $errores_php[] = "Faltan funciones.";

        // Imagen
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
             $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
             if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                 if (!is_dir("../evt_interfaz/imagenes")) mkdir("../evt_interfaz/imagenes", 0755, true);
                 $ruta = "imagenes/evt_" . time() . "." . $ext;
                 if (move_uploaded_file($_FILES['imagen']['tmp_name'], "../evt_interfaz/" . $ruta)) {
                     $imagen_ruta = $ruta;
                     if ($imagen_actual && $imagen_actual != $ruta && file_exists("../evt_interfaz/" . $imagen_actual)) unlink("../evt_interfaz/" . $imagen_actual);
                 } else $errores_php[] = "Error subiendo imagen.";
             } else $errores_php[] = "Formato imagen incorrecto.";
        }

        if (empty($errores_php)) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE evento SET titulo=?, inicio_venta=?, cierre_venta=?, descripcion=?, imagen=?, tipo=?, finalizado=0 WHERE id_evento=?");
                $stmt->bind_param("sssssii", $titulo, $inicio_venta, $cierre_venta, $descripcion, $imagen_ruta, $tipo, $id_evento);
                $stmt->execute(); $stmt->close();

                $conn->query("DELETE FROM funciones WHERE id_evento = $id_evento");
                $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora) VALUES (?, ?)");
                foreach ($_POST['funciones'] as $fh) { $stmt_f->bind_param("is", $id_evento, $fh); $stmt_f->execute(); }
                $stmt_f->close();

                $conn->commit();
                header('Location: index.php?status=success'); exit;
            } catch (Exception $e) { $conn->rollback(); $errores_php[] = "Error DB: " . $e->getMessage(); }
        }
    }
    
    // --- CASO B: CANCELAR REACTIVACIÓN (NUEVO) ---
    // Esto borra el evento recién creado si el usuario se arrepiente
    if (isset($_POST['accion']) && $_POST['accion'] == 'cancelar_nuevo') {
        $id_borrar = $_POST['id_evento'];
        // Borrado simple porque es nuevo y no tiene ventas ni boletos aún
        $conn->query("DELETE FROM categorias WHERE id_evento = $id_borrar");
        $conn->query("DELETE FROM evento WHERE id_evento = $id_borrar");
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// ==================================================================
// CARGA INICIAL (GET)
// ==================================================================
$id_evento = $_GET['id'] ?? 0;
$es_nuevo = isset($_GET['es_nuevo']) && $_GET['es_nuevo'] == 1; // DETECTAR SI ES NUEVO

if (!$id_evento || !is_numeric($id_evento)) { header('Location: index.php'); exit; }

$res = $conn->query("SELECT * FROM evento WHERE id_evento = $id_evento");
$evento = $res->fetch_assoc();
if (!$evento) { header('Location: index.php'); exit; }

$funciones_existentes = [];
// Solo cargamos funciones si NO es una reactivación fresca
if (!$es_nuevo) {
    $res_f = $conn->query("SELECT fecha_hora FROM funciones WHERE id_evento = $id_evento ORDER BY fecha_hora ASC");
    while($f = $res_f->fetch_assoc()) { $funciones_existentes[] = new DateTime($f['fecha_hora']); }
}

// Si es nuevo, las fechas por defecto aparecen vacías para obligar a llenarlas
$defaultVenta = $es_nuevo ? '' : date('Y-m-d H:i', strtotime($evento['inicio_venta']));
$defaultCierre = $es_nuevo ? '' : date('Y-m-d H:i', strtotime($evento['cierre_venta']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $es_nuevo ? 'Completar Reactivación' : 'Editar Evento' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    /* ESTILOS UNIFICADOS */
    :root { --primary-color: #2563eb; --primary-dark: #1e40af; --success-color: #10b981; --danger-color: #ef4444; --warning-color: #f59e0b; --bg-primary: #f8fafc; --bg-secondary: #ffffff; --text-primary: #0f172a; --text-secondary: #64748b; --border-color: #e2e8f0; --radius-sm: 8px; --radius-lg: 16px; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, var(--bg-primary), #e2e8f0); color: var(--text-primary); padding: 30px 20px; min-height: 100vh; }
    .main-container { max-width: 850px; margin: 0 auto; }
    .card { background: var(--bg-secondary); border-radius: var(--radius-lg); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 40px; border: 1px solid var(--border-color); }
    .form-control, .form-select { border-radius: var(--radius-sm); padding: 12px; background: var(--bg-primary); border: 1px solid var(--border-color); }
    .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(37,99,235,0.1); }
    .btn { padding: 12px 24px; border-radius: var(--radius-sm); font-weight: 600; border: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .btn-primary { background: var(--primary-color); color: white; } .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
    .btn-secondary { background: #fff; color: var(--text-primary); border: 1px solid var(--border-color); } .btn-secondary:hover { background: var(--bg-primary); }
    .btn-danger { background: var(--danger-color); color: white; } .btn-danger:hover { background: #dc2626; }
    .input-error { border-color: var(--danger-color) !important; background: #fef2f2 !important; }
    .tooltip-error { color: var(--danger-color); font-size: 0.9em; margin-top: 5px; display: none; font-weight: 600; }
    #lista-funciones-container { background: var(--bg-primary); border: 2px dashed var(--border-color); border-radius: var(--radius-sm); padding: 15px; min-height: 80px; display: flex; flex-wrap: wrap; gap: 10px; }
    .funcion-item { background: #fff; padding: 6px 12px; border-radius: 20px; border: 1px solid var(--primary-color); color: var(--primary-color); font-weight: 600; display: flex; align-items: center; gap: 8px; box-shadow: var(--shadow-sm); }
    .funcion-item button { background: none; border: none; color: var(--danger-color); font-size: 1.1rem; line-height: 1; padding: 0; cursor: pointer; opacity: 0.6; }
    .funcion-item button:hover { opacity: 1; transform: scale(1.2); }
    .img-preview { width: 100px; height: 140px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-top: 10px; }
</style>
</head>
<body>

<div class="main-container">
    <div class="mb-4">
        <?php if ($es_nuevo): ?>
            <button type="button" id="btnCancelarReactivacion" class="btn btn-danger shadow-sm">
                <i class="bi bi-x-circle-fill"></i> Cancelar Reactivación
            </button>
        <?php else: ?>
            <a href="index.php" class="btn btn-secondary shadow-sm">
                <i class="bi bi-arrow-left"></i> Volver al Dashboard
            </a>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
            <h2 class="m-0 text-primary">
                <i class="bi <?= $es_nuevo ? 'bi-arrow-repeat' : 'bi-pencil-square' ?> me-3"></i>
                <?= $es_nuevo ? 'Completar Reactivación' : 'Editar Evento' ?>
            </h2>
        </div>

        <?php if($errores_php): ?><div class="alert alert-danger border-0 shadow-sm"><ul><?php foreach($errores_php as $e) echo "<li>$e</li>"; ?></ul></div><?php endif; ?>

        <form id="fEdit" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="actualizar">
            <input type="hidden" name="id_evento" id="hiddenIdEvento" value="<?= $id_evento ?>">
            <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($evento['imagen']) ?>">

            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label fw-bold">Título</label>
                    <input type="text" id="tit" name="titulo" class="form-control form-control-lg fw-bold" value="<?= htmlspecialchars($evento['titulo']) ?>" required>
                </div>

                <div class="col-12">
                    <div class="p-4 bg-light rounded-4 border">
                        <label class="fw-bold mb-3"><i class="bi bi-calendar-week me-2"></i>Gestión de Funciones</label>
                        <div class="input-group mb-2 shadow-sm">
                            <input type="text" id="fDate" class="form-control" placeholder="Fecha" readonly>
                            <input type="text" id="fTime" class="form-control" placeholder="Hora" readonly style="max-width:130px">
                            <button type="button" id="fAdd" class="btn btn-success" disabled><i class="bi bi-plus-lg"></i> Agregar</button>
                        </div>
                        <div id="ttFunc" class="tooltip-error mb-3"></div>
                        <div id="lista-funciones-container">
                            <p id="noFunc" class="text-muted m-0 w-100 text-center fst-italic">
                                <i class="bi bi-inbox me-2"></i><?= $es_nuevo ? 'Asigna las nuevas fechas para este evento.' : 'Sin funciones asignadas.' ?>
                            </p>
                        </div>
                        <div id="hidFunc"></div>
                    </div>
                </div>

                <div class="col-md-6"><label class="fw-bold">Inicio Venta</label><input type="text" id="ini" name="inicio_venta" class="form-control" value="<?= $defaultVenta ?>" readonly required><div id="ttIni" class="tooltip-error"></div></div>
                <div class="col-md-6"><label class="fw-bold">Cierre Venta</label><input type="text" id="fin" name="cierre_venta" class="form-control" value="<?= $defaultCierre ?>" readonly required><div id="ttFin" class="tooltip-error"></div></div>
                <div class="col-12"><label class="fw-bold">Descripción</label><textarea id="desc" name="descripcion" class="form-control" rows="3" required><?= htmlspecialchars($evento['descripcion']) ?></textarea></div>
                <div class="col-md-7">
                    <label class="fw-bold">Imagen</label><input type="file" id="img" name="imagen" class="form-control" accept="image/*">
                    <?php if($evento['imagen']): ?><div class="mt-2 small fw-bold text-muted">Actual:</div><img src="../evt_interfaz/<?= htmlspecialchars($evento['imagen']) ?>" class="img-preview"><?php endif; ?>
                </div>
                <div class="col-md-5">
                    <label class="fw-bold">Escenario</label>
                    <select id="tipo" name="tipo" class="form-select" required>
                        <option value="1" <?= $evento['tipo']==1?'selected':'' ?>>🎭 Teatro (420)</option>
                        <option value="2" <?= $evento['tipo']==2?'selected':'' ?>>🚶 Pasarela (540)</option>
                    </select>
                </div>
            </div>
            <hr class="my-5">
            <div class="d-flex gap-3">
                 <?php if ($es_nuevo): ?>
                    <button type="button" id="btnCancelarAbajo" class="btn btn-outline-danger py-3 fs-5 col-4">Cancelar</button>
                <?php endif; ?>
                <button type="submit" id="bSub" class="btn btn-primary py-3 fs-5 shadow-sm <?= $es_nuevo ? 'col-8' : 'w-100' ?>" disabled>
                    <i class="bi bi-check2-circle me-2"></i> Confirmar Reactivación
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script><script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    flatpickr.localize(flatpickr.l10ns.es); const now = new Date();
    // Si es nuevo, empezamos sin funciones. Si no, cargamos las existentes.
    let funcs = [<?php if(!$es_nuevo) { foreach($funciones_existentes as $f) echo "new Date('".$f->format('c')."'),"; } ?>];

    const els={add:document.getElementById('fAdd'),sub:document.getElementById('bSub'),list:document.getElementById('lista-funciones-container'),hid:document.getElementById('hidFunc'),no:document.getElementById('noFunc'),ttF:document.getElementById('ttFunc'),ttI:document.getElementById('ttIni'),ttE:document.getElementById('ttFin'),ini:document.getElementById('ini'),fin:document.getElementById('fin')};
    
    // Configuración Flatpickr (Forzamos fechas futuras si es reactivación)
    const minDateConfig = <?= $es_nuevo ? '"today"' : 'null' ?>;
    const fpD=flatpickr("#fDate",{minDate:minDateConfig, onChange:check}), fpT=flatpickr("#fTime",{enableTime:true,noCalendar:true,dateFormat:"H:i",time_24hr:true,minuteIncrement:15,onChange:check});
    const fpI=flatpickr("#ini",{enableTime:true,minDate:minDateConfig,onChange:val}), fpE=flatpickr("#fin",{enableTime:true,minDate:minDateConfig,onChange:val});

    function check(){els.add.disabled=!(fpD.selectedDates.length&&fpT.selectedDates.length);}
    els.add.onclick=()=>{
        let dt=new Date(fpD.input.value+'T'+fpT.input.value);
        if(funcs.some(d=>d.getTime()===dt.getTime())) return alert("Duplicada");
        funcs.push(dt); funcs.sort((a,b)=>a-b); fpD.clear(); fpT.clear(); check(); upd();
    };
    function upd(){
        els.list.innerHTML=''; els.hid.innerHTML='';
        if(!funcs.length){ els.list.appendChild(els.no); fpI.set('maxDate',null); if(<?= $es_nuevo?1:0 ?>) fpE.clear(); }
        else{
            funcs.forEach((d,i)=>{
                els.list.innerHTML+=`<div class="funcion-item"><i class="bi bi-calendar-event"></i> ${d.toLocaleString('es-ES',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'})}<button type="button" onclick="del(${i})">×</button></div>`;
                els.hid.innerHTML+=`<input type="hidden" name="funciones[]" value="${d.getFullYear()}-${(d.getMonth()+1+'').padStart(2,'0')}-${(d.getDate()+'').padStart(2,'0')} ${(d.getHours()+'').padStart(2,'0')}:${(d.getMinutes()+'').padStart(2,'0')}:00">`;
            });
            fpI.set('maxDate',new Date(funcs[0].getTime()-6e4));
            const minFin = new Date(funcs[funcs.length-1].getTime()+72e5);
            fpE.set('minDate',minFin);
            if(!fpE.selectedDates.length || fpE.selectedDates[0] < minFin) fpE.setDate(minFin,true);
        }
        val();
    }
    window.del=i=>{funcs.splice(i,1);upd();};
    function val(){
        let ok=true; [els.ttF,els.ttI,els.ttE].forEach(e=>e.style.display='none'); document.querySelectorAll('.input-error').forEach(e=>e.classList.remove('input-error'));
        if(!document.getElementById('tit').value.trim()) ok=false;
        if(!funcs.length){ err(els.ttF,null,'Falta función.'); ok=false; }
        if(!fpI.selectedDates.length){ if(funcs.length) {err(els.ttI,els.ini,'Requerido.'); ok=false;} }
        else if(funcs.length&&fpI.selectedDates[0]>=funcs[0]){ err(els.ttI,els.ini,'Debe ser antes de 1ª función.'); ok=false; }
        if(!fpE.selectedDates.length){ if(funcs.length) {err(els.ttE,els.fin,'Requerido.'); ok=false;} }
        else if(fpI.selectedDates.length&&fpE.selectedDates[0]<=fpI.selectedDates[0]){ err(els.ttE,els.fin,'Debe ser posterior al inicio.'); ok=false; }
        els.sub.disabled=!ok; return ok;
    }
    function err(t,i,m){ t.innerHTML='<i class="bi bi-exclamation-circle-fill me-1"></i> '+m; t.style.display='flex'; if(i) i.classList.add('input-error'); }
    
    ['tit','desc','img','tipo'].forEach(id=>document.getElementById(id).addEventListener(id==='img'||id==='tipo'?'change':'input',val));
    document.getElementById('fEdit').addEventListener('submit',e=>{if(!val()){e.preventDefault();alert("Corrige errores.");}});
    upd();

    // --- LÓGICA DE CANCELACIÓN (SOLO SI ES NUEVO) ---
    const btnCancelTop = document.getElementById('btnCancelarReactivacion');
    const btnCancelBot = document.getElementById('btnCancelarAbajo');
    
    function cancelarReactivacion() {
        if(confirm('¿Estás seguro de cancelar la reactivación? Se eliminará este borrador.')) {
            const id = document.getElementById('hiddenIdEvento').value;
            const fd = new FormData();
            fd.append('accion', 'cancelar_nuevo');
            fd.append('id_evento', id);
            fetch('', {method:'POST', body:fd})
                .then(r=>r.json())
                .then(d=>{ if(d.status==='success') window.location.href='index.php'; })
                .catch(()=>alert('Error de conexión'));
        }
    }
    if(btnCancelTop) btnCancelTop.onclick = cancelarReactivacion;
    if(btnCancelBot) btnCancelBot.onclick = cancelarReactivacion;
});
</script>
</body>
</html>