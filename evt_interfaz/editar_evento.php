<?php
include "../conexion.php";
$errores_php = [];

// ==================================================================
// == PROCESADOR DE ACCIÓN (POST) ==
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
    $id_evento = $_POST['id_evento'];
    $titulo = trim($_POST['titulo']);
    $inicio_venta = $_POST['inicio_venta'];
    $cierre_venta = $_POST['cierre_venta'];
    $descripcion = trim($_POST['descripcion']);
    $tipo = $_POST['tipo'];
    $imagen_actual = $_POST['imagen_actual'];
    $imagen_ruta = $imagen_actual;

    if (empty($titulo)) $errores_php[] = "El título es obligatorio.";
    if (empty($descripcion)) $errores_php[] = "La descripción es obligatoria.";
    if (empty($tipo)) $errores_php[] = "Debe seleccionar un tipo de escenario.";
    if (empty($inicio_venta)) $errores_php[] = "Inicio de venta obligatorio.";
    if (empty($cierre_venta)) $errores_php[] = "Cierre de venta obligatorio.";
    if (!isset($_POST['funciones']) || !is_array($_POST['funciones']) || empty($_POST['funciones'])) {
        $errores_php[] = "Debe añadir al menos una función.";
    }

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $errores_php[] = "Formato de imagen no válido.";
        } else {
            if (!is_dir("imagenes")) mkdir("imagenes", 0755, true);
            $nombreArchivo = "evento_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], "imagenes/" . $nombreArchivo)) {
                $imagen_ruta = "imagenes/" . $nombreArchivo;
                if ($imagen_actual && $imagen_actual != $imagen_ruta && file_exists($imagen_actual)) {
                    unlink($imagen_actual);
                }
            } else {
                $errores_php[] = "Error al subir la nueva imagen.";
            }
        }
    } elseif (empty($imagen_actual) && (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] != 0) ) {
         $errores_php[] = "Debe subir una imagen para el evento.";
    }

    if (empty($errores_php)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE evento SET titulo=?, inicio_venta=?, cierre_venta=?, descripcion=?, imagen=?, tipo=?, finalizado=0 WHERE id_evento=?");
            $stmt->bind_param("sssssii", $titulo, $inicio_venta, $cierre_venta, $descripcion, $imagen_ruta, $tipo, $id_evento);
            $stmt->execute();
            $stmt->close();

            $conn->query("DELETE FROM funciones WHERE id_evento = $id_evento");
            $stmt_f = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora) VALUES (?, ?)");
            foreach ($_POST['funciones'] as $fh) {
                $stmt_f->bind_param("is", $id_evento, $fh);
                $stmt_f->execute();
            }
            $stmt_f->close();

            $conn->commit();
            // Redirección con parámetro para mostrar alerta de éxito en el index
            header('Location: index.php?status=success&msg=' . urlencode('Evento actualizado correctamente.'));
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $errores_php[] = "Error DB: " . $e->getMessage();
        }
    }
}

// ==================================================================
// == CARGA DE DATOS (GET) ==
// ==================================================================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { header('Location: index.php'); exit; }
$id_evento = $_GET['id'];
$res = $conn->query("SELECT * FROM evento WHERE id_evento = $id_evento");
$evento = $res->fetch_assoc();
if (!$evento) { header('Location: index.php'); exit; }

$funciones_existentes = [];
$res_f = $conn->query("SELECT fecha_hora FROM funciones WHERE id_evento = $id_evento ORDER BY fecha_hora ASC");
while($f = $res_f->fetch_assoc()) { $funciones_existentes[] = new DateTime($f['fecha_hora']); }

$ahora = new DateTime();
$f_venta = new DateTime($evento['inicio_venta']);
$f_cierre = new DateTime($evento['cierre_venta']);
$defaultVenta = ($f_venta > $ahora) ? $f_venta->format('Y-m-d H:i') : '';
$defaultCierre = ($f_cierre > $ahora) ? $f_cierre->format('Y-m-d H:i') : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar Evento</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
        --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
        --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
    }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        color: var(--text-primary);
        line-height: 1.6;
        padding: 24px;
        min-height: 100vh;
    }
    .main-wrapper { max-width: 800px; margin: 0 auto; }
    .card {
        background: var(--bg-secondary); border: 1px solid var(--border-color);
        border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
    }
    .card:hover { box-shadow: var(--shadow-lg); }
    h2 { color: var(--text-primary); font-weight: 700; font-size: 1.75rem; margin-bottom: 1rem; }
    .form-label { font-weight: 600; color: var(--text-primary); font-size: 0.95rem; margin-bottom: 8px; }
    .form-control, .form-select {
        border: 1px solid var(--border-color); border-radius: var(--radius-sm);
        padding: 10px 14px; font-size: 0.95rem; transition: all 0.2s;
        background-color: var(--bg-primary);
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); outline: none; background-color: #fff;
    }
    .btn {
        border-radius: var(--radius-sm); padding: 10px 20px; font-weight: 600;
        font-size: 0.95rem; transition: all 0.2s; border: none;
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-primary { background: var(--primary-color); color: white; }
    .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: var(--shadow-md); }
    .btn-secondary { background: #64748b; color: white; }
    .btn-secondary:hover { background: #475569; transform: translateY(-1px); box-shadow: var(--shadow-md); }
    .btn-success { background: var(--success-color); color: white; }
    .btn-success:hover { background: #059669; transform: translateY(-1px); }

    .help-text { font-size: 0.85rem; color: var(--text-secondary); margin-top: 6px; }
    .input-error { border-color: var(--danger-color) !important; background-color: #fef2f2 !important; }
    .tooltip-error {
        background-color: var(--danger-color); color: #fff; padding: 6px 12px;
        border-radius: var(--radius-sm); font-size: 0.85em; margin-top: 6px; display: none;
        animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

    #lista-funciones-container {
        background-color: var(--bg-primary); border: 1px dashed var(--border-color);
        border-radius: var(--radius-sm); padding: 15px; min-height: 80px;
        display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
    }
    .funcion-item {
        background: #e0e7ff; color: var(--primary-dark); padding: 6px 12px;
        border-radius: 20px; font-weight: 600; font-size: 0.9rem;
        display: inline-flex; align-items: center; box-shadow: var(--shadow-sm);
    }
    .funcion-item button {
        background: none; border: none; color: var(--primary-color);
        margin-left: 8px; font-size: 1.2em; line-height: 1; padding: 0 4px;
        cursor: pointer; opacity: 0.6; transition: opacity 0.2s;
    }
    .funcion-item button:hover { opacity: 1; color: var(--danger-color); }
    .img-preview {
        width: 100px; height: 100px; object-fit: cover; border-radius: var(--radius-sm);
        border: 2px solid var(--border-color); margin-top: 10px;
    }
</style>
</head>
<body>

<div class="main-wrapper">
    <div class="mb-4">
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver al Dashboard
        </a>
    </div>

    <div class="card p-4 p-md-5">
        <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
            <h2 class="m-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Evento</h2>
        </div>

        <?php if (!empty($errores_php)): ?>
            <div class="alert alert-danger shadow-sm border-0 mb-4">
                <strong class="d-block mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Corrija los siguientes errores:</strong>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errores_php as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="editForm" action="editar_evento.php?id=<?= $id_evento ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="actualizar">
            <input type="hidden" name="id_evento" value="<?= $id_evento ?>">
            <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($evento['imagen']) ?>">

            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label" for="titulo">Título del Evento</label>
                    <input type="text" name="titulo" id="titulo" class="form-control form-control-lg fw-bold" value="<?= htmlspecialchars($evento['titulo']) ?>" placeholder="Ej: Concierto de Rock..." required>
                </div>

                <div class="col-12">
                    <div class="card p-3 bg-light border-0">
                        <label class="form-label mb-3"><i class="bi bi-calendar-week me-2"></i>Gestión de Funciones</label>
                        <div class="input-group mb-2 shadow-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-calendar-event"></i></span>
                            <input type="text" id="funcion_fecha" class="form-control border-start-0 ps-0" placeholder="Selecciona fecha" readonly style="cursor:pointer;">
                            <span class="input-group-text bg-white border-end-0 border-start-0"><i class="bi bi-clock"></i></span>
                            <input type="text" id="funcion_hora" class="form-control border-start-0 ps-0" placeholder="Hora" readonly style="cursor:pointer; max-width: 120px;">
                            <button class="btn btn-success" type="button" id="btn-add-funcion" disabled>
                                <i class="bi bi-plus-lg"></i> Añadir
                            </button>
                        </div>
                        <div id="tooltip_funciones" class="tooltip-error mb-2"></div>
                        
                        <div id="lista-funciones-container">
                            <p id="no-funciones-msg" class="text-muted m-0 w-100 text-center fst-italic">
                                <i class="bi bi-inbox me-1"></i> No hay funciones asignadas.
                            </p>
                        </div>
                        <div id="hidden-funciones-container"></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-shop me-1"></i>Inicio de Venta</label>
                    <input type="text" name="inicio_venta" id="inicio_venta" class="form-control" value="<?= $defaultVenta ?>" required readonly style="cursor:pointer;" placeholder="Selecciona fecha y hora...">
                    <div id="tooltip_inicio_venta" class="tooltip-error"></div>
                    <div class="help-text">Cuándo pueden empezar a comprar boletos.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-door-closed me-1"></i>Cierre de Venta</label>
                    <input type="text" name="cierre_venta" id="cierre_venta" class="form-control" value="<?= $defaultCierre ?>" required readonly style="cursor:pointer;" placeholder="Selecciona fecha y hora...">
                    <div id="tooltip_cierre_venta" class="tooltip-error"></div>
                    <div class="help-text">Normalmente 2h después de la última función.</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Descripción / Sinopsis</label>
                    <textarea name="descripcion" id="descripcion" class="form-control" rows="4" placeholder="Detalles del evento..." required><?= htmlspecialchars($evento['descripcion']) ?></textarea>
                </div>

                <div class="col-md-7">
                    <label class="form-label"><i class="bi bi-image me-1"></i>Imagen de Cartelera</label>
                    <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*">
                    <div class="d-flex align-items-start gap-3 mt-3">
                        <?php if ($evento['imagen'] && file_exists($evento['imagen'])): ?>
                            <div>
                                <div class="help-text mb-1">Imagen Actual:</div>
                                <img src="<?= $evento['imagen'] ?>" class="img-preview shadow-sm" alt="Actual">
                            </div>
                        <?php endif; ?>
                        <div class="help-text mt-3">
                            Formatos: JPG, PNG. Idealmente vertical (tipo poster).<br>
                            Si no subes una nueva, se mantiene la actual.
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <label class="form-label"><i class="bi bi-diagram-3 me-1"></i>Tipo de Escenario</label>
                    <select name="tipo" id="tipo" class="form-select form-select-lg" required>
                        <option value="">-- Selecciona --</option>
                        <option value="1" <?= ($evento['tipo'] == 1) ? 'selected' : '' ?>>🎭 Completo (420)</option>
                        <option value="2" <?= ($evento['tipo'] == 2) ? 'selected' : '' ?>>🚶 Pasarela (540)</option>
                    </select>
                </div>
            </div> <hr class="my-4" style="border-color: var(--border-color);">

            <button type="submit" id="btn-submit" class="btn btn-primary w-100 py-3 fs-5 shadow-sm">
                <i class="bi bi-floppy2-fill me-2"></i> Guardar Cambios y Reactivar
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    flatpickr.localize(flatpickr.l10ns.es);
    const ahora = new Date();
    let listaFunciones = [<?php foreach($funciones_existentes as $fo) echo "new Date('".$fo->format('c')."'),"; ?>];
    
    const btnAdd = document.getElementById('btn-add-funcion'), btnSub = document.getElementById('btn-submit');
    const listCont = document.getElementById('lista-funciones-container'), hidCont = document.getElementById('hidden-funciones-container');
    const msgNoFunc = document.getElementById('no-funciones-msg');
    const ttFunc = document.getElementById('tooltip_funciones'), ttIni = document.getElementById('tooltip_inicio_venta'), ttFin = document.getElementById('tooltip_cierre_venta');

    const fpConfig = { enableTime: true, time_24hr: true, minuteIncrement: 15, minDate: ahora, disableMobile: "true" };
    const fpFecha = flatpickr("#funcion_fecha", { minDate: ahora, dateFormat: "Y-m-d", onChange: checkAdd });
    const fpHora = flatpickr("#funcion_hora", { enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true, onChange: checkAdd });
    const fpIni = flatpickr("#inicio_venta", { ...fpConfig, onChange: validar });
    const fpFin = flatpickr("#cierre_venta", { ...fpConfig, onChange: validar });

    function checkAdd() {
        let ok = fpFecha.selectedDates.length && fpHora.selectedDates.length;
        if(ok) {
            let f = fpFecha.selectedDates[0], h = fpHora.selectedDates[0];
            let dt = new Date(f.getFullYear(), f.getMonth(), f.getDate(), h.getHours(), h.getMinutes());
            if(dt <= new Date(ahora.getTime() + 60000)) ok = false;
        }
        btnAdd.disabled = !ok;
    }

    btnAdd.addEventListener('click', () => {
        let fStr = fpFecha.input.value, hStr = fpHora.input.value;
        let dt = new Date(fStr + 'T' + hStr);
        if(listaFunciones.some(d => d.getTime() === dt.getTime())) { alert("Función duplicada."); return; }
        listaFunciones.push(dt); listaFunciones.sort((a,b) => a - b);
        fpFecha.clear(); fpHora.clear(); checkAdd(); updateUI();
    });

    function updateUI() {
        listCont.innerHTML = ''; hidCont.innerHTML = '';
        if(!listaFunciones.length) {
            listCont.appendChild(msgNoFunc);
            fpIni.set('maxDate', null); fpFin.set('minDate', ahora);
        } else {
            listaFunciones.forEach((dt, i) => {
                let item = document.createElement('span'); item.className = 'funcion-item';
                item.innerHTML = `${dt.toLocaleString('es-ES',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'})} <button type="button" data-i="${i}">×</button>`;
                listCont.appendChild(item);
                let inp = document.createElement('input'); inp.type='hidden'; inp.name='funciones[]';
                inp.value = `${dt.getFullYear()}-${(dt.getMonth()+1).toString().padStart(2,'0')}-${dt.getDate().toString().padStart(2,'0')} ${dt.getHours().toString().padStart(2,'0')}:${dt.getMinutes().toString().padStart(2,'0')}:00`;
                hidCont.appendChild(inp);
            });
            fpIni.set('maxDate', listaFunciones[0]);
            fpFin.set('minDate', new Date(listaFunciones[listaFunciones.length-1].getTime() + 7200000));
        }
        validar();
    }

    listCont.addEventListener('click', e => { if(e.target.tagName==='BUTTON'){ listaFunciones.splice(e.target.dataset.i,1); updateUI(); }});

    function validar() {
        let ok = true;
        // Reset errores
        [ttFunc, ttIni, ttFin].forEach(el => el.style.display='none');
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));

        if(!listaFunciones.length) { ttFunc.textContent='Añade al menos una función.'; ttFunc.style.display='block'; ok=false; }
        
        if(!fpIni.selectedDates.length) { setError('inicio_venta', ttIni, 'Requerido.'); ok=false; }
        else if(listaFunciones.length && fpIni.selectedDates[0] >= listaFunciones[0]) { setError('inicio_venta', ttIni, 'Debe ser antes de la 1ª función.'); ok=false; }

        if(!fpFin.selectedDates.length) { setError('cierre_venta', ttFin, 'Requerido.'); ok=false; }
        else if(fpIni.selectedDates.length && fpFin.selectedDates[0] <= fpIni.selectedDates[0]) { setError('cierre_venta', ttFin, 'Debe ser después del inicio.'); ok=false; }

        btnSub.disabled = !ok; return ok;
    }
    function setError(id, tt, msg) { document.getElementById(id).classList.add('input-error'); tt.textContent=msg; tt.style.display='block'; }

    updateUI();
    document.getElementById('editForm').addEventListener('submit', e => { if(!validar()) { e.preventDefault(); alert("Revisa los errores."); }});
});
</script>
</body>
</html>