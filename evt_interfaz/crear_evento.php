<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Crear Evento</title>
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
        font-size: 0.85em; /*position: absolute;*/ margin-top: 5px; display: none; /* Changed position */
        z-index: 10;
        width: 100%; /* Make tooltip full width */
    }
    .form-group { position: relative; }
    /* Estilos para la lista de funciones */
    #lista-funciones-container {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        min-height: 100px;
        max-height: 200px; /* Added max-height */
        overflow-y: auto; /* Added scroll */
    }
    .funcion-item {
        display: inline-flex;
        align-items: center;
        background-color: #e0e7ff;
        color: #4338ca;
        padding: 5px 10px;
        border-radius: 15px;
        font-weight: 500;
        margin: 4px;
        font-size: 0.9em;
    }
    .funcion-item button {
        background: none;
        border: none;
        color: #4338ca;
        opacity: 0.7;
        margin-left: 8px;
        font-weight: bold;
        padding: 0; /* Adjust padding */
        line-height: 1; /* Adjust line-height */
    }
    .funcion-item button:hover { opacity: 1; }
</style>
</head>
<body>

<div class="container">

<a href="index.php" class="btn btn-outline-secondary mb-3">
    <i class="bi bi-arrow-left"></i> Volver al Listado
</a>
<h2>Crear Nuevo Evento</h2>
<form id="eventoForm" action="procesar_evento.php" method="POST" enctype="multipart/form-data">

    <div class="mb-3 form-group">
        <label class="form-label">Título del evento</label>
        <input type="text" name="titulo" id="titulo" class="form-control" placeholder="Ej: Concierto de Rock" required>
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
         <div id="tooltip_funciones" class="tooltip-error"></div> <div class="help-text">Añade una o varias funciones para el evento.</div>
    </div>
    
    <label>Funciones Añadidas:</label>
    <div id="lista-funciones-container" class="mb-3">
        <p id="no-funciones-msg" class="text-muted text-center m-0">Aún no hay funciones añadidas.</p>
    </div>
    
    <div id="hidden-funciones-container"></div>
    <div class="row">
        <div class="col-md-6 mb-3 form-group">
            <label class="form-label">Inicio de venta</label>
            <input type="text" name="inicio_venta" id="inicio_venta" class="form-control" required readonly style="cursor:pointer;">
            <div id="tooltip_inicio_venta" class="tooltip-error"></div>
            <div class="help-text">Debe ser anterior a la primera función.</div>
        </div>
        <div class="col-md-6 mb-3 form-group">
            <label class="form-label">Cierre de venta</label>
            <input type="text" name="cierre_venta" id="cierre_venta" class="form-control" required readonly style="cursor:pointer;">
            <div id="tooltip_cierre_venta" class="tooltip-error"></div>
            <div class="help-text">Se ajusta 2h después de la última función.</div>
        </div>
    </div>

    <div class="mb-3 form-group">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" id="descripcion" class="form-control" rows="4" placeholder="Detalles del evento" required></textarea>
        <div class="help-text">Información adicional sobre el evento.</div>
    </div>

    <div class="mb-3 form-group">
        <label class="form-label">Imagen de cartelera</label>
        <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*" required>
        <div class="help-text">Suba una imagen representativa del evento.</div>
    </div>

    <div class="mb-3 form-group">
        <label class="form-label">Tipo de escenario</label>
        <select name="tipo" id="tipo" class="form-select" required>
            <option value="">Seleccione</option>
            <option value="1">Escenario completo (420 asientos)</option>
            <option value="2">Escenario pasarela (540 asientos)</option>
        </select>
        <div class="help-text">Seleccione el tipo de escenario.</div>
    </div>

    <div id="hidden-precios-container">
        <input type="hidden" name="precios[General]" value="80">
        <input type="hidden" name="precios[Discapacitado]" value="80">
    </div>

    <button type="submit" id="btn-submit" class="btn btn-primary w-100 btn-lg" disabled>Crear Evento</button>
</form>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// (Tu JavaScript existente va aquí... no necesita cambios)
document.addEventListener('DOMContentLoaded', function() {
    
    const ahora = new Date();
    
    // --- 1. Almacén de datos y elementos del DOM ---
    let listaDeFunciones = []; // Array de objetos Date()
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
        dateFormat: "Y-m-d H:i", // Formato para PHP/MySQL
        time_24hr: true,
        minuteIncrement: 1, // Permitir cualquier minuto
        minDate: ahora
    };

    const fpFecha = flatpickr("#funcion_fecha", {
        minDate: ahora,
        dateFormat: "Y-m-d", // Solo fecha
        onChange: checkAddButton
    });
    
    const fpHora = flatpickr("#funcion_hora", {
        enableTime: true,
        noCalendar: true, // Solo hora
        dateFormat: "H:i", // Solo hora
        time_24hr: true,
        minuteIncrement: 15, // Intervalos de 15 min para la hora
        onChange: checkAddButton
    });

    // Calendarios de venta (habilitados más adelante)
    const fpInicioVenta = flatpickr("#inicio_venta", { ...configComun, enableTime: true, onChange: validarFormulario });
    const fpCierreVenta = flatpickr("#cierre_venta", { ...configComun, enableTime: true, onChange: validarFormulario });

    // Habilita el botón "+" solo si se ha elegido fecha Y hora válidas
    function checkAddButton() {
        // Validación adicional: si la fecha es hoy, la hora no puede ser pasada
        let horaValida = true;
        if (fpFecha.selectedDates.length > 0 && fpHora.selectedDates.length > 0) {
            const fechaSeleccionada = fpFecha.selectedDates[0];
            const horaSeleccionada = fpHora.selectedDates[0];
            const fechaHoraSeleccionada = new Date(
                fechaSeleccionada.getFullYear(),
                fechaSeleccionada.getMonth(),
                fechaSeleccionada.getDate(),
                horaSeleccionada.getHours(),
                horaSeleccionada.getMinutes()
            );
            if (fechaHoraSeleccionada < ahora) {
                horaValida = false;
                // Opcional: mostrar un mensaje de error o deshabilitar
            }
        }
        btnAddFuncion.disabled = !(fpFecha.selectedDates.length > 0 && fpHora.selectedDates.length > 0 && horaValida);
    }

    // --- 3. Lógica de AÑADIR Función ---
    btnAddFuncion.addEventListener('click', function() {
        const fechaStr = fpFecha.input.value;
        const horaStr = fpHora.input.value;
        
        // Combinamos fecha y hora
        const fechaHoraCompletaStr = `${fechaStr} ${horaStr}`;
        const nuevaFecha = new Date(fechaHoraCompletaStr.replace(/-/g, '/')); // Formato compatible JS

        // Validar que la fecha/hora combinada no sea pasada (doble chequeo)
        if (nuevaFecha < ahora) {
             alert("No puedes añadir una función en una fecha u hora pasada.");
             return;
        }

        // Validar que no esté duplicada
        const esDuplicado = listaDeFunciones.some(f => f.getTime() === nuevaFecha.getTime());
        if (esDuplicado) {
            alert("Esa función (día y hora) ya ha sido añadida.");
            return;
        }

        // Añadir al array
        listaDeFunciones.push(nuevaFecha);
        
        // Ordenar el array (muy importante)
        listaDeFunciones.sort((a, b) => a.getTime() - b.getTime());

        // Limpiar los selectores
        fpFecha.clear();
        fpHora.clear();
        btnAddFuncion.disabled = true;

        // Actualizar la UI
        actualizarUIFunciones();
    });

    // --- 4. Lógica de ACTUALIZAR la UI y los Inputs Ocultos ---
    function actualizarUIFunciones() {
        // Limpiar contenedores
        listaContainer.innerHTML = '';
        hiddenContainer.innerHTML = '';

        if (listaDeFunciones.length === 0) {
            listaContainer.appendChild(noFuncionesMsg); // Mostrar mensaje "vacío"
            // Resetear calendarios de venta
            fpInicioVenta.set('maxDate', null); // Sin límite superior
            fpInicioVenta.clear(); // Limpiar fecha si había
            fpCierreVenta.set('minDate', ahora); // Mínimo es ahora
            fpCierreVenta.clear();
            tooltipFunciones.style.display = 'block'; // Mostrar error si se vacía
            tooltipFunciones.textContent = 'Debe añadir al menos una función.';
        } else {
            // Si hay funciones, ocultar el mensaje de "vacío" y el error
            if (noFuncionesMsg.parentNode) {
                noFuncionesMsg.remove();
            }
            tooltipFunciones.style.display = 'none';

            listaDeFunciones.forEach((fecha, index) => {
                // Formato legible para el usuario
                const fechaLegible = fecha.toLocaleString('es-ES', {
                    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false // Formato 24h
                });
                
                // Formato MySQL "YYYY-MM-DD HH:MM:SS"
                const y = fecha.getFullYear();
                const m = String(fecha.getMonth() + 1).padStart(2, '0');
                const d = String(fecha.getDate()).padStart(2, '0');
                const h = String(fecha.getHours()).padStart(2, '0');
                const i = String(fecha.getMinutes()).padStart(2, '0');
                const fechaMySQL = `${y}-${m}-${d} ${h}:${i}:00`;

                // Crear la "píldora" (badge) visible
                const item = document.createElement('span');
                item.className = 'funcion-item';
                item.innerHTML = `${fechaLegible} <button type="button" data-index="${index}" title="Eliminar función">&times;</button>`;
                listaContainer.appendChild(item);

                // Crear el input oculto para el formulario
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'funciones[]'; // <-- Se envía como array a PHP
                hiddenInput.value = fechaMySQL;
                hiddenContainer.appendChild(hiddenInput);
            });

            // Ajustar calendarios de venta automáticamente
            const primeraFuncion = listaDeFunciones[0];
            const ultimaFuncion = listaDeFunciones[listaDeFunciones.length - 1];

            // Inicio Venta: debe ser ANTES de la primera función
            fpInicioVenta.set('maxDate', primeraFuncion);
            // Si la fecha actual de inicio venta es posterior a la nueva primera función, limpiarla
            if (fpInicioVenta.selectedDates.length > 0 && fpInicioVenta.selectedDates[0] >= primeraFuncion) {
                fpInicioVenta.clear();
            }
            
            // Cierre Venta: debe ser DESPUÉS de la última función
            const minCierre = new Date(ultimaFuncion.getTime() + 2 * 60 * 60 * 1000); // 2h después
            fpCierreVenta.set('minDate', ultimaFuncion);
            // Forzar la fecha mínima si la actual es anterior, o establecerla si no hay
             if (fpCierreVenta.selectedDates.length === 0 || fpCierreVenta.selectedDates[0] < minCierre) {
                fpCierreVenta.setDate(minCierre, true); // Auto-establece la fecha y dispara onChange
             } else {
                 validarFormulario(); // Validar si no se cambió la fecha
             }
        }
        
        // Llamar a validarFormulario al final SIEMPRE
         validarFormulario();
    }

    // --- 5. Lógica de QUITAR Función ---
    listaContainer.addEventListener('click', function(e) {
        // Usamos delegación de eventos
        if (e.target.tagName === 'BUTTON') {
            const index = parseInt(e.target.dataset.index, 10);
            listaDeFunciones.splice(index, 1); // Quitar del array
            actualizarUIFunciones(); // Volver a dibujar todo
        }
    });

    // --- 6. Validación General del Formulario ---
    function validarFormulario() {
        let esValido = true;
        let primerError = null; // Para enfocar el primer campo con error

        // Validar Título
        const tituloInput = document.getElementById('titulo');
        if (tituloInput.value.trim() === '') {
             esValido = false;
             // Opcional: Añadir clase de error a tituloInput
             if(!primerError) primerError = tituloInput;
        }

        // Validar Funciones
        if (listaDeFunciones.length === 0) {
            tooltipFunciones.textContent = 'Debe añadir al menos una función.';
            tooltipFunciones.style.display = 'block';
            esValido = false;
             if(!primerError) primerError = fpFecha.input; // Enfocar el input de fecha
        } else {
            tooltipFunciones.style.display = 'none';
        }

        // Validar Inicio Venta
        const inicioVentaInput = document.getElementById('inicio_venta');
        if (fpInicioVenta.selectedDates.length === 0) {
             tooltipInicioVenta.textContent = 'Seleccione inicio de venta.';
             tooltipInicioVenta.style.display = 'block';
             inicioVentaInput.classList.add('input-error');
             esValido = false;
             if(!primerError) primerError = inicioVentaInput;
        } else {
             const startSale = fpInicioVenta.selectedDates[0];
             const primeraFuncion = listaDeFunciones.length > 0 ? listaDeFunciones[0] : null;
             if (primeraFuncion && startSale >= primeraFuncion) {
                 tooltipInicioVenta.textContent = 'Debe ser ANTES de la primera función.';
                 tooltipInicioVenta.style.display = 'block';
                 inicioVentaInput.classList.add('input-error');
                 esValido = false;
                 if(!primerError) primerError = inicioVentaInput;
             } else if (startSale < ahora) { // Doble chequeo por si minDate falla
                 tooltipInicioVenta.textContent = 'No puede ser en el pasado.';
                 tooltipInicioVenta.style.display = 'block';
                 inicioVentaInput.classList.add('input-error');
                 esValido = false;
                 if(!primerError) primerError = inicioVentaInput;
             }
             else {
                 tooltipInicioVenta.style.display = 'none';
                 inicioVentaInput.classList.remove('input-error');
             }
        }

        // Validar Cierre Venta
        const cierreVentaInput = document.getElementById('cierre_venta');
         if (fpCierreVenta.selectedDates.length === 0) {
             tooltipCierreVenta.textContent = 'Seleccione cierre de venta.';
             tooltipCierreVenta.style.display = 'block';
             cierreVentaInput.classList.add('input-error');
             esValido = false;
              if(!primerError) primerError = cierreVentaInput;
        } else {
             const endSale = fpCierreVenta.selectedDates[0];
             const ultimaFuncion = listaDeFunciones.length > 0 ? listaDeFunciones[listaDeFunciones.length - 1] : null;
             const startSale = fpInicioVenta.selectedDates[0]; // Ya validado arriba
              const minCierreReq = ultimaFuncion ? new Date(ultimaFuncion.getTime() + 2 * 60 * 60 * 1000 - 1000) : ahora; // -1 seg margen

             if (ultimaFuncion && endSale < minCierreReq) {
                 tooltipCierreVenta.textContent = 'Debe ser al menos 2h después de la última función.';
                 tooltipCierreVenta.style.display = 'block';
                 cierreVentaInput.classList.add('input-error');
                 esValido = false;
                 if(!primerError) primerError = cierreVentaInput;
             } else if (startSale && endSale <= startSale) {
                 tooltipCierreVenta.textContent = 'Debe ser POSTERIOR al inicio de venta.';
                 tooltipCierreVenta.style.display = 'block';
                 cierreVentaInput.classList.add('input-error');
                 esValido = false;
                  if(!primerError) primerError = cierreVentaInput;
             } else {
                 tooltipCierreVenta.style.display = 'none';
                 cierreVentaInput.classList.remove('input-error');
             }
        }

        // Validar Descripción
         const descInput = document.getElementById('descripcion');
        if (descInput.value.trim() === '') {
             esValido = false;
             if(!primerError) primerError = descInput;
        }

        // Validar Imagen
         const imgInput = document.getElementById('imagen');
        if (imgInput.files.length === 0) {
             esValido = false;
             if(!primerError) primerError = imgInput;
        }

        // Validar Tipo
        const tipoSelect = document.getElementById('tipo');
        if (tipoSelect.value === '') {
             esValido = false;
             if(!primerError) primerError = tipoSelect;
        }


        // Habilitar o deshabilitar el botón de envío
        btnSubmit.disabled = !esValido;
        
        // Retornar validez (para el listener del submit)
        return esValido;
    }

    // Escuchar cambios en inputs y select para re-validar
    ['titulo', 'descripcion', 'imagen', 'tipo'].forEach(id => {
        const element = document.getElementById(id);
        element.addEventListener('input', validarFormulario); // 'input' para texto/textarea
        element.addEventListener('change', validarFormulario); // 'change' para file/select
    });

    // Validar una vez al cargar (para que el botón esté deshabilitado)
    validarFormulario();

    // --- 7. Listener para el SUBMIT ---
    document.getElementById('eventoForm').addEventListener('submit', function(e){
        if(!validarFormulario()){ // Llama a la validación una última vez
            e.preventDefault();
            alert("Por favor, corrija los errores marcados en el formulario antes de continuar.");
            // Enfocar el primer campo con error
            const primerErrorCampo = document.querySelector('.input-error, #lista-funciones-container .tooltip-error[style*="display: block"]');
             if(primerErrorCampo){
                 // Si el error es de funciones, enfocar el input de fecha
                 if(primerErrorCampo.id === 'tooltip_funciones'){
                     document.getElementById('funcion_fecha').focus();
                 } else {
                     primerErrorCampo.focus();
                 }
             }
        }
        // Si es válido, el formulario se envía normalmente
    });
});
</script>
</body>
</html>