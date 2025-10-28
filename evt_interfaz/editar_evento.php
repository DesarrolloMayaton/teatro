<?php
include "conexion.php";
$errores_php = []; // Array para errores del lado del servidor

// ==================================================================
// == INICIO: PROCESADOR DE ACCIÓN (POST) ==
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
    $id_evento = $_POST['id_evento'];
    $titulo = trim($_POST['titulo']);
    $inicio_venta = $_POST['inicio_venta']; // Ej: "2025-10-20 10:00"
    $cierre_venta = $_POST['cierre_venta']; // Ej: "2025-10-22 22:00"
    $descripcion = trim($_POST['descripcion']);
    $tipo = $_POST['tipo'];
    $imagen_actual = $_POST['imagen_actual'];
    $imagen_ruta = $imagen_actual; // Por defecto mantenemos la actual

    // --- Validación básica de campos ---
    if (empty($titulo)) $errores_php[] = "El título es obligatorio.";
    if (empty($descripcion)) $errores_php[] = "La descripción es obligatoria.";
    if (empty($tipo)) $errores_php[] = "Debe seleccionar un tipo de escenario.";
    if (empty($inicio_venta)) $errores_php[] = "La fecha de inicio de venta es obligatoria.";
    if (empty($cierre_venta)) $errores_php[] = "La fecha de cierre de venta es obligatoria.";
    if (!isset($_POST['funciones']) || !is_array($_POST['funciones']) || empty($_POST['funciones'])) {
        $errores_php[] = "Debe añadir al menos una función para el evento.";
    }

    // --- Validación de Imagen (Si se subió una nueva) ---
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed_ext)) {
            $errores_php[] = "Formato de imagen no válido (permitidos: jpg, jpeg, png, gif).";
        } else {
            if (!is_dir("imagenes")) mkdir("imagenes", 0755, true);
            $nombreArchivo = "evento_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], "imagenes/" . $nombreArchivo)) {
                $imagen_ruta = "imagenes/" . $nombreArchivo;
                // Borrar imagen anterior si existía y es diferente
                if ($imagen_actual && $imagen_actual != $imagen_ruta && file_exists($imagen_actual)) {
                    unlink($imagen_actual);
                }
            } else {
                $errores_php[] = "Error al subir la nueva imagen.";
            }
        }
    } elseif (empty($imagen_actual) && (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] != 0) ) {
         // Si NO había imagen antes Y NO se subió una nueva ahora
         $errores_php[] = "Debe subir una imagen para el evento.";
    }


    // --- Si no hay errores hasta ahora, procedemos a actualizar ---
    if (empty($errores_php)) {
        $conn->begin_transaction(); // Iniciar transacción para seguridad

        try {
            // 1. Actualizar datos principales del evento
            $stmt_evento = $conn->prepare(
                "UPDATE evento SET titulo=?, inicio_venta=?, cierre_venta=?, descripcion=?, imagen=?, tipo=?, finalizado=0 
                 WHERE id_evento=?" // Siempre reactivamos al editar
            );
            $stmt_evento->bind_param("sssssii", $titulo, $inicio_venta, $cierre_venta, $descripcion, $imagen_ruta, $tipo, $id_evento);
            $stmt_evento->execute();
            $stmt_evento->close();

            // 2. Borrar TODAS las funciones antiguas asociadas a este evento
            $stmt_delete_func = $conn->prepare("DELETE FROM funciones WHERE id_evento = ?");
            $stmt_delete_func->bind_param("i", $id_evento);
            $stmt_delete_func->execute();
            $stmt_delete_func->close();

            // 3. Insertar las NUEVAS funciones recibidas del formulario
            $stmt_insert_func = $conn->prepare("INSERT INTO funciones (id_evento, fecha_hora) VALUES (?, ?)");
            foreach ($_POST['funciones'] as $fecha_hora) {
                // $fecha_hora ya viene en formato "YYYY-MM-DD HH:MM:SS"
                $stmt_insert_func->bind_param("is", $id_evento, $fecha_hora);
                $stmt_insert_func->execute();
            }
            $stmt_insert_func->close();

            // Si todo fue bien, confirmar los cambios
            $conn->commit();
            header('Location: index.php?status=success'); // Redirigir con mensaje (opcional)
            exit;

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback(); // Revertir cambios si algo falló
            $errores_php[] = "Error en la base de datos: " . $exception->getMessage();
        }
    }
    // Si hubo errores PHP, NO redirigimos, se mostrarán en el formulario abajo
}

// ==================================================================
// == INICIO: CARGA DE DATOS PARA EL FORMULARIO (GET) ==
// ==================================================================

// 1. Validar que se recibió un ID (esto debe estar ANTES del HTML)
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}
$id_evento = $_GET['id'];
$evento = null;
$funciones_existentes = []; // Array para guardar las funciones actuales

// 2. Buscar los datos del evento principal
if ($stmt = $conn->prepare("SELECT * FROM evento WHERE id_evento = ?")) {
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $evento = $resultado->fetch_assoc();
    $stmt->close();
}

// 3. Si el evento no existe, regresa al index
if (!$evento) {
    header('Location: index.php');
    exit;
}

// 4. Buscar las funciones existentes para este evento
if ($stmt_func = $conn->prepare("SELECT fecha_hora FROM funciones WHERE id_evento = ? ORDER BY fecha_hora ASC")) {
    $stmt_func->bind_param("i", $id_evento);
    $stmt_func->execute();
    $res_func = $stmt_func->get_result();
    while ($f = $res_func->fetch_assoc()) {
        // Guardamos como objetos Date para pasarlos a JS
        $funciones_existentes[] = new DateTime($f['fecha_hora']);
    }
    $stmt_func->close();
}

// Convertimos las fechas de venta a formato Y-m-d H:i
$ahora = new DateTime();
$fecha_venta_obj = new DateTime($evento['inicio_venta']);
$fecha_cierre_obj = new DateTime($evento['cierre_venta']);

// Usamos la fecha guardada si es futura, si no, no ponemos default (forzamos selección)
$defaultVenta = ($fecha_venta_obj > $ahora) ? $fecha_venta_obj->format('Y-m-d H:i') : '';
$defaultCierre = ($fecha_cierre_obj > $ahora) ? $fecha_cierre_obj->format('Y-m-d H:i') : '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar Evento</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    body { background: #f8f9fa; font-family: Arial, sans-serif; }
    .container { max-width: 700px; margin-top: 30px; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
    h2 { text-align: center; margin-bottom: 25px; }
    .help-text { font-size: 0.9em; color: #6c757d; margin-bottom: 10px; }
    .input-error { border-color: #dc3545 !important; }
    .tooltip-error {
        background-color: #dc3545; color: #fff; padding: 6px 10px; border-radius: 5px;
        font-size: 0.85em; margin-top: 5px; display: none;
        z-index: 10; width: 100%;
    }
    .form-group { position: relative; }
    #lista-funciones-container { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; min-height: 100px; max-height: 200px; overflow-y: auto; }
    .funcion-item { display: inline-flex; align-items: center; background-color: #e0e7ff; color: #4338ca; padding: 5px 10px; border-radius: 15px; font-weight: 500; margin: 4px; font-size: 0.9em; }
    .funcion-item button { background: none; border: none; color: #4338ca; opacity: 0.7; margin-left: 8px; font-weight: bold; padding: 0; line-height: 1; }
    .funcion-item button:hover { opacity: 1; }
</style>
</head>
<body>

<div class="container">

    <a href="index.php" class="btn btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Volver al Listado
    </a>
    <h2>Editar / Reactivar Evento</h2>

    <?php if (!empty($errores_php)): ?>
        <div class="alert alert-danger">
            <strong>Error al guardar:</strong>
            <ul>
                <?php foreach ($errores_php as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form id="editEventoForm" action="editar_evento.php?id=<?= $id_evento ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="actualizar">
        <input type="hidden" name="id_evento" value="<?= $id_evento ?>">
        <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($evento['imagen']) ?>">

        <div class="mb-3 form-group">
            <label class="form-label">Título del evento</label>
            <input type="text" name="titulo" id="titulo" class="form-control" value="<?= htmlspecialchars($evento['titulo']) ?>" required>
            <div class="help-text">Ingrese un título descriptivo para el evento.</div>
        </div>

        <div class="mb-3 form-group">
            <label class="form-label">Funciones</label>
            <div class="input-group">
                <input type="text" id="funcion_fecha" class="form-control" placeholder="Selecciona un día..." readonly style="cursor:pointer;">
                <input type="text" id="funcion_hora" class="form-control" placeholder="Selecciona una hora..." readonly style="cursor:pointer;">
                <button class="btn btn-success" type="button" id="btn-add-funcion" disabled>
                    <i class="bi bi-plus-circle"></i> Añadir
                </button>
            </div>
            <div id="tooltip_funciones" class="tooltip-error"></div>
            <div class="help-text">Añade o elimina funciones para el evento.</div>
        </div>
        
        <label>Funciones Actuales:</label>
        <div id="lista-funciones-container" class="mb-3">
            <p id="no-funciones-msg" class="text-muted text-center m-0">Cargando funciones...</p>
        </div>
        
        <div id="hidden-funciones-container"></div>
        <div class="row">
            <div class="col-md-6 mb-3 form-group">
                <label class="form-label">Inicio de venta</label>
                <input type="text" name="inicio_venta" id="inicio_venta" class="form-control" value="<?= htmlspecialchars($defaultVenta) ?>" required readonly style="cursor:pointer;">
                <div id="tooltip_inicio_venta" class="tooltip-error"></div>
                <div class="help-text">Debe ser anterior a la primera función.</div>
            </div>
            <div class="col-md-6 mb-3 form-group">
                <label class="form-label">Cierre de venta</label>
                <input type="text" name="cierre_venta" id="cierre_venta" class="form-control" value="<?= htmlspecialchars($defaultCierre) ?>" required readonly style="cursor:pointer;">
                <div id="tooltip_cierre_venta" class="tooltip-error"></div>
                <div class="help-text">Se ajusta 2h después de la última función.</div>
            </div>
        </div>

        <div class="mb-3 form-group">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" id="descripcion" class="form-control" rows="4" required><?= htmlspecialchars($evento['descripcion']) ?></textarea>
            <div class="help-text">Información adicional sobre el evento.</div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3 form-group">
                <label class="form-label">Imagen de cartelera</label>
                <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*">
                <div class="help-text">Suba una nueva imagen si desea cambiarla.</div>
                 <?php if ($evento['imagen'] && file_exists($evento['imagen'])): ?>
                    <div class="mt-2">
                        <small>Imagen actual:</small><br>
                        <img src="<?= $evento['imagen'] ?>" alt="Imagen actual" style="max-width: 100px; border-radius: 5px;">
                    </div>
                <?php endif; ?>
            </div>
             <div class="col-md-6 mb-3 form-group">
                <label class="form-label">Tipo de escenario</label>
                <select name="tipo" id="tipo" class="form-select" required>
                    <option value="" <?= ($evento['tipo'] == '') ? 'selected' : '' ?>>Seleccione</option>
                    <option value="1" <?= ($evento['tipo'] == 1) ? 'selected' : '' ?>>Escenario completo (420 asientos)</option>
                    <option value="2" <?= ($evento['tipo'] == 2) ? 'selected' : '' ?>>Escenario pasarela (540 asientos)</option>
                </select>
                <div class="help-text">Seleccione el tipo de escenario.</div>
            </div>
        </div>

        <button type="submit" id="btn-submit" class="btn btn-primary w-100 btn-lg mt-3">Guardar Cambios</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const ahora = new Date();
    
    // --- 1. Almacén de datos y elementos del DOM ---
    // Convertimos las fechas PHP (objetos DateTime) a objetos Date de JS
    let listaDeFunciones = [
      <?php 
        foreach ($funciones_existentes as $fecha_obj) {
            // Formato ISO 8601 que JS entiende bien
            echo "new Date('" . $fecha_obj->format('c') . "'),"; 
        } 
      ?>
    ]; 
    const btnAddFuncion = document.getElementById('btn-add-funcion');
    const btnSubmit = document.getElementById('btn-submit');
    const listaContainer = document.getElementById('lista-funciones-container');
    const hiddenContainer = document.getElementById('hidden-funciones-container');
    const noFuncionesMsg = document.getElementById('no-funciones-msg');
    const tooltipFunciones = document.getElementById('tooltip_funciones');
    const tooltipInicioVenta = document.getElementById('tooltip_inicio_venta');
    const tooltipCierreVenta = document.getElementById('tooltip_cierre_venta');

    // --- 2. Configuración de Calendarios (Flatpickr) ---
    const configComun = {
        dateFormat: "Y-m-d H:i", 
        time_24hr: true,
        minuteIncrement: 1, 
        minDate: ahora 
    };

    const fpFecha = flatpickr("#funcion_fecha", {
        minDate: ahora,
        dateFormat: "Y-m-d",
        onChange: checkAddButton
    });
    
    const fpHora = flatpickr("#funcion_hora", {
        enableTime: true,
        noCalendar: true, 
        dateFormat: "H:i", 
        time_24hr: true,
        minuteIncrement: 15, 
        onChange: checkAddButton
    });

    const fpInicioVenta = flatpickr("#inicio_venta", { 
        ...configComun, 
        enableTime: true, 
        // defaultDate: '<?= $defaultVenta ?>', // Default se pone en el input value
        onChange: validarFormulario 
    });
    const fpCierreVenta = flatpickr("#cierre_venta", { 
        ...configComun, 
        enableTime: true, 
        // defaultDate: '<?= $defaultCierre ?>',
        onChange: validarFormulario 
    });
    
    // --- Lógica checkAddButton, addEventListener, actualizarUIFunciones, removeEventListener ---
    // (Estas funciones son idénticas a las de crear_evento.php)
    function checkAddButton() {
        let horaValida = true;
        if (fpFecha.selectedDates.length > 0 && fpHora.selectedDates.length > 0) {
            const fechaSeleccionada = fpFecha.selectedDates[0];
            const horaSeleccionada = fpHora.selectedDates[0];
            const fechaHoraSeleccionada = new Date(
                fechaSeleccionada.getFullYear(), fechaSeleccionada.getMonth(), fechaSeleccionada.getDate(),
                horaSeleccionada.getHours(), horaSeleccionada.getMinutes()
            );
            // Comprobar si la fecha-hora seleccionada es al menos 1 minuto en el futuro
            if (fechaHoraSeleccionada.getTime() <= ahora.getTime() + 60000) { 
                horaValida = false;
            }
        }
        btnAddFuncion.disabled = !(fpFecha.selectedDates.length > 0 && fpHora.selectedDates.length > 0 && horaValida);
    }

    btnAddFuncion.addEventListener('click', function() {
        const fechaStr = fpFecha.input.value;
        const horaStr = fpHora.input.value;
        const fechaHoraCompletaStr = `${fechaStr} ${horaStr}`;
        const nuevaFecha = new Date(fechaHoraCompletaStr.replace(/-/g, '/')); 

        if (nuevaFecha.getTime() <= ahora.getTime() + 60000) {
             alert("No puedes añadir una función en una fecha u hora pasada o muy próxima.");
             return;
        }
        const esDuplicado = listaDeFunciones.some(f => f.getTime() === nuevaFecha.getTime());
        if (esDuplicado) {
            alert("Esa función (día y hora) ya ha sido añadida.");
            return;
        }
        listaDeFunciones.push(nuevaFecha);
        listaDeFunciones.sort((a, b) => a.getTime() - b.getTime());
        fpFecha.clear();
        fpHora.clear();
        btnAddFuncion.disabled = true;
        actualizarUIFunciones();
    });

    function actualizarUIFunciones() {
        listaContainer.innerHTML = '';
        hiddenContainer.innerHTML = '';

        if (listaDeFunciones.length === 0) {
            listaContainer.appendChild(noFuncionesMsg); 
            fpInicioVenta.set('maxDate', null);
            fpInicioVenta.clear();
            fpCierreVenta.set('minDate', ahora);
            fpCierreVenta.clear();
            tooltipFunciones.style.display = 'block'; 
            tooltipFunciones.textContent = 'Debe añadir al menos una función.';
        } else {
            if (noFuncionesMsg.parentNode) noFuncionesMsg.remove();
            tooltipFunciones.style.display = 'none';

            listaDeFunciones.forEach((fecha, index) => {
                const fechaLegible = fecha.toLocaleString('es-ES', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });
                const y = fecha.getFullYear();
                const m = String(fecha.getMonth() + 1).padStart(2, '0');
                const d = String(fecha.getDate()).padStart(2, '0');
                const h = String(fecha.getHours()).padStart(2, '0');
                const i = String(fecha.getMinutes()).padStart(2, '0');
                const fechaMySQL = `${y}-${m}-${d} ${h}:${i}:00`;

                const item = document.createElement('span');
                item.className = 'funcion-item';
                item.innerHTML = `${fechaLegible} <button type="button" data-index="${index}" title="Eliminar función">&times;</button>`;
                listaContainer.appendChild(item);

                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'funciones[]';
                hiddenInput.value = fechaMySQL;
                hiddenContainer.appendChild(hiddenInput);
            });

            const primeraFuncion = listaDeFunciones[0];
            const ultimaFuncion = listaDeFunciones[listaDeFunciones.length - 1];

            fpInicioVenta.set('maxDate', primeraFuncion);
            if (fpInicioVenta.selectedDates.length > 0 && fpInicioVenta.selectedDates[0] >= primeraFuncion) {
                fpInicioVenta.clear();
            }
            
            const minCierre = new Date(ultimaFuncion.getTime() + 2 * 60 * 60 * 1000); 
            fpCierreVenta.set('minDate', ultimaFuncion);
             if (fpCierreVenta.selectedDates.length === 0 || fpCierreVenta.selectedDates[0] < minCierre) {
                fpCierreVenta.setDate(minCierre, true); 
             } else {
                 // No cambiamos la fecha si ya cumple, solo validamos
             }
        }
         validarFormulario(); // Validar siempre al final
    }

    listaContainer.addEventListener('click', function(e) {
        if (e.target.tagName === 'BUTTON') {
            const index = parseInt(e.target.dataset.index, 10);
            listaDeFunciones.splice(index, 1); 
            actualizarUIFunciones(); 
        }
    });

    // --- Validación General del Formulario (idéntica a crear_evento) ---
    function validarFormulario() {
        let esValido = true;
        let primerError = null; 

        const tituloInput = document.getElementById('titulo');
        if (tituloInput.value.trim() === '') { esValido = false; if(!primerError) primerError = tituloInput; }

        if (listaDeFunciones.length === 0) {
            tooltipFunciones.textContent = 'Debe añadir al menos una función.';
            tooltipFunciones.style.display = 'block'; esValido = false; if(!primerError) primerError = fpFecha.input;
        } else { tooltipFunciones.style.display = 'none'; }

        const inicioVentaInput = document.getElementById('inicio_venta');
        if (fpInicioVenta.selectedDates.length === 0) {
             tooltipInicioVenta.textContent = 'Seleccione inicio de venta.'; tooltipInicioVenta.style.display = 'block'; inicioVentaInput.classList.add('input-error'); esValido = false; if(!primerError) primerError = inicioVentaInput;
        } else {
             const startSale = fpInicioVenta.selectedDates[0];
             const primeraFuncion = listaDeFunciones.length > 0 ? listaDeFunciones[0] : null;
             if (primeraFuncion && startSale >= primeraFuncion) {
                  tooltipInicioVenta.textContent = 'Debe ser ANTES de la primera función.'; tooltipInicioVenta.style.display = 'block'; inicioVentaInput.classList.add('input-error'); esValido = false; if(!primerError) primerError = inicioVentaInput;
             } else if (startSale.getTime() <= ahora.getTime() + 60000) { // Margen de 1 min
                  tooltipInicioVenta.textContent = 'No puede ser en el pasado o muy próximo.'; tooltipInicioVenta.style.display = 'block'; inicioVentaInput.classList.add('input-error'); esValido = false; if(!primerError) primerError = inicioVentaInput;
             } else { tooltipInicioVenta.style.display = 'none'; inicioVentaInput.classList.remove('input-error'); }
        }

        const cierreVentaInput = document.getElementById('cierre_venta');
         if (fpCierreVenta.selectedDates.length === 0) {
             tooltipCierreVenta.textContent = 'Seleccione cierre de venta.'; tooltipCierreVenta.style.display = 'block'; cierreVentaInput.classList.add('input-error'); esValido = false; if(!primerError) primerError = cierreVentaInput;
        } else {
            const endSale = fpCierreVenta.selectedDates[0];
            const ultimaFuncion = listaDeFunciones.length > 0 ? listaDeFunciones[listaDeFunciones.length - 1] : null;
            const startSale = fpInicioVenta.selectedDates[0]; 
            const minCierreReq = ultimaFuncion ? new Date(ultimaFuncion.getTime() + 2 * 60 * 60 * 1000 - 1000) : ahora; 

            if (ultimaFuncion && endSale < minCierreReq) {
                 tooltipCierreVenta.textContent = 'Debe ser al menos 2h después de la última función.'; tooltipCierreVenta.style.display = 'block'; cierreVentaInput.classList.add('input-error'); esValido = false; if(!primerError) primerError = cierreVentaInput;
            } else if (startSale && endSale <= startSale) {
                 tooltipCierreVenta.textContent = 'Debe ser POSTERIOR al inicio de venta.'; tooltipCierreVenta.style.display = 'block'; cierreVentaInput.classList.add('input-error'); esValido = false; if(!primerError) primerError = cierreVentaInput;
            } else { tooltipCierreVenta.style.display = 'none'; cierreVentaInput.classList.remove('input-error'); }
        }

        const descInput = document.getElementById('descripcion');
        if (descInput.value.trim() === '') { esValido = false; if(!primerError) primerError = descInput; }

        // Imagen: No es obligatoria al editar si ya existe una
        // const imgInput = document.getElementById('imagen');
        // if (imgInput.files.length === 0 && !document.querySelector('input[name="imagen_actual"]').value) { esValido = false; if(!primerError) primerError = imgInput; }

        const tipoSelect = document.getElementById('tipo');
        if (tipoSelect.value === '') { esValido = false; if(!primerError) primerError = tipoSelect; }

        btnSubmit.disabled = !esValido;
        return esValido;
    }

    // Inicializar la UI con las funciones existentes
    actualizarUIFunciones();

    // Validar al cambiar cualquier campo relevante
    ['titulo', 'descripcion', 'imagen', 'tipo'].forEach(id => {
        const element = document.getElementById(id);
        element.addEventListener('input', validarFormulario); 
        element.addEventListener('change', validarFormulario); 
    });

    // Listener para el SUBMIT
    document.getElementById('editEventoForm').addEventListener('submit', function(e){
        if(!validarFormulario()){ 
            e.preventDefault();
            alert("Por favor, corrija los errores marcados en el formulario antes de continuar.");
            // Enfocar el primer campo con error visible
             const primerInputConError = document.querySelector('.input-error');
             const primerTooltipVisible = document.querySelector('.tooltip-error[style*="display: block"]');
             
             if(primerTooltipVisible && primerTooltipVisible.id === 'tooltip_funciones'){
                 document.getElementById('funcion_fecha').focus(); // Enfocar selector de fecha
             } else if (primerInputConError) {
                 primerInputConError.focus();
             } else if (primerTooltipVisible) {
                 // Si solo hay error de tooltip (debería haber input asociado)
                 const inputAsociadoId = primerTooltipVisible.id.replace('tooltip_', 'edit_'); // Asume convención
                 const inputAsociado = document.getElementById(inputAsociadoId);
                 if(inputAsociado) inputAsociado.focus();
             }
        }
    });
});
</script>
</body>
</html>