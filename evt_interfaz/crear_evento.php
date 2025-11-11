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
    h2 { color: var(--text-primary); font-weight: 700; font-size: 1.75rem; margin: 0; }
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
            <h2><i class="bi bi-plus-circle-fill me-2 text-success"></i>Crear Nuevo Evento</h2>
        </div>

        <form id="eventoForm" action="procesar_evento.php" method="POST" enctype="multipart/form-data">

            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label" for="titulo">Título del Evento</label>
                    <input type="text" name="titulo" id="titulo" class="form-control form-control-lg fw-bold" placeholder="Ej: Concierto de Rock..." required>
                    <div class="help-text">Un nombre descriptivo y atractivo.</div>
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
                                <i class="bi bi-inbox me-1"></i> Aún no hay funciones añadidas.
                            </p>
                        </div>
                        <div id="hidden-funciones-container"></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-shop me-1"></i>Inicio de Venta</label>
                    <input type="text" name="inicio_venta" id="inicio_venta" class="form-control" required readonly style="cursor:pointer;" placeholder="Selecciona fecha y hora...">
                    <div id="tooltip_inicio_venta" class="tooltip-error"></div>
                    <div class="help-text">Cuándo pueden empezar a comprar boletos.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-door-closed me-1"></i>Cierre de Venta</label>
                    <input type="text" name="cierre_venta" id="cierre_venta" class="form-control" required readonly style="cursor:pointer;" placeholder="Selecciona fecha y hora...">
                    <div id="tooltip_cierre_venta" class="tooltip-error"></div>
                    <div class="help-text">Se ajusta 2h después de la última función.</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Descripción / Sinopsis</label>
                    <textarea name="descripcion" id="descripcion" class="form-control" rows="4" placeholder="Detalles del evento..." required></textarea>
                </div>

                <div class="col-md-7">
                    <label class="form-label"><i class="bi bi-image me-1"></i>Imagen de Cartelera</label>
                    <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*" required>
                    <div class="help-text">Formatos: JPG, PNG. Idealmente vertical.</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label"><i class="bi bi-diagram-3 me-1"></i>Tipo de Escenario</label>
                    <select name="tipo" id="tipo" class="form-select form-select-lg" required>
                        <option value="">-- Selecciona --</option>
                        <option value="1">🎭 Completo (420)</option>
                        <option value="2">🚶 Pasarela (540)</option>
                    </select>
                </div>

            </div> <div id="hidden-precios-container">
                <input type="hidden" name="precios[General]" value="80">
                <input type="hidden" name="precios[Discapacitado]" value="80">
            </div>

            <hr class="my-4" style="border-color: var(--border-color);">

            <button type="submit" id="btn-submit" class="btn btn-primary w-100 py-3 fs-5 shadow-sm" disabled>
                <i class="bi bi-check2-circle me-2"></i> Crear Evento
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
    
    let listaDeFunciones = [];
    const btnAddFuncion = document.getElementById('btn-add-funcion');
    const btnSubmit = document.getElementById('btn-submit');
    const listaContainer = document.getElementById('lista-funciones-container');
    const hiddenContainer = document.getElementById('hidden-funciones-container');
    const noFuncionesMsg = document.getElementById('no-funciones-msg');
    const tooltipFunciones = document.getElementById('tooltip_funciones');
    const tooltipInicioVenta = document.getElementById('tooltip_inicio_venta');
    const tooltipCierreVenta = document.getElementById('tooltip_cierre_venta');

    const configComun = {
        dateFormat: "Y-m-d H:i", 
        time_24hr: true,
        minuteIncrement: 15, 
        minDate: ahora,
        disableMobile: "true"
    };

    const fpFecha = flatpickr("#funcion_fecha", {
        minDate: ahora, dateFormat: "Y-m-d", onChange: checkAddButton
    });
    const fpHora = flatpickr("#funcion_hora", {
        enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true, minuteIncrement: 15, onChange: checkAddButton
    });

    const fpInicioVenta = flatpickr("#inicio_venta", { ...configComun, enableTime: true, onChange: validarFormulario });
    const fpCierreVenta = flatpickr("#cierre_venta", { ...configComun, enableTime: true, onChange: validarFormulario });

    function checkAddButton() {
        let horaValida = true;
        if (fpFecha.selectedDates.length > 0 && fpHora.selectedDates.length > 0) {
            const f = fpFecha.selectedDates[0], h = fpHora.selectedDates[0];
            const dt = new Date(f.getFullYear(), f.getMonth(), f.getDate(), h.getHours(), h.getMinutes());
            if (dt <= new Date(ahora.getTime() + 60000)) horaValida = false;
        }
        btnAddFuncion.disabled = !(fpFecha.selectedDates.length > 0 && fpHora.selectedDates.length > 0 && horaValida);
    }

    btnAddFuncion.addEventListener('click', function() {
        const fStr = fpFecha.input.value, hStr = fpHora.input.value;
        const nuevaFecha = new Date(fStr + 'T' + hStr); 

        if (nuevaFecha <= new Date(ahora.getTime() + 60000)) { alert("Fecha/hora inválida (pasada o muy próxima)."); return; }
        if (listaDeFunciones.some(f => f.getTime() === nuevaFecha.getTime())) { alert("Función duplicada."); return; }

        listaDeFunciones.push(nuevaFecha);
        listaDeFunciones.sort((a, b) => a.getTime() - b.getTime());
        fpFecha.clear(); fpHora.clear(); checkAddButton();
        actualizarUIFunciones();
    });

    function actualizarUIFunciones() {
        listaContainer.innerHTML = ''; hiddenContainer.innerHTML = '';
        if (listaDeFunciones.length === 0) {
            listaContainer.appendChild(noFuncionesMsg);
            fpInicioVenta.set('maxDate', null); fpInicioVenta.clear();
            fpCierreVenta.set('minDate', ahora); fpCierreVenta.clear();
        } else {
            listaDeFunciones.forEach((fecha, index) => {
                const item = document.createElement('span'); item.className = 'funcion-item';
                item.innerHTML = `${fecha.toLocaleString('es-ES', {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'})} <button type="button" data-index="${index}">×</button>`;
                listaContainer.appendChild(item);
                const hiddenInput = document.createElement('input'); hiddenInput.type = 'hidden'; hiddenInput.name = 'funciones[]';
                // Formato YYYY-MM-DD HH:MM:SS manual para asegurar compatibilidad
                const y = fecha.getFullYear(), m = String(fecha.getMonth() + 1).padStart(2, '0'), d = String(fecha.getDate()).padStart(2, '0');
                const h = String(fecha.getHours()).padStart(2, '0'), i = String(fecha.getMinutes()).padStart(2, '0');
                hiddenInput.value = `${y}-${m}-${d} ${h}:${i}:00`;
                hiddenContainer.appendChild(hiddenInput);
            });
            fpInicioVenta.set('maxDate', listaDeFunciones[0]);
            const minCierre = new Date(listaDeFunciones[listaDeFunciones.length - 1].getTime() + 7200000); // +2h
            fpCierreVenta.set('minDate', minCierre);
            if (fpCierreVenta.selectedDates.length === 0 || fpCierreVenta.selectedDates[0] < minCierre) {
                 fpCierreVenta.setDate(minCierre, true);
            }
        }
        validarFormulario();
    }

    listaContainer.addEventListener('click', function(e) {
        if (e.target.tagName === 'BUTTON') {
            listaDeFunciones.splice(parseInt(e.target.dataset.index, 10), 1);
            actualizarUIFunciones();
        }
    });

    function validarFormulario() {
        let esValido = true;
        // Reset visual errors
        [tooltipFunciones, tooltipInicioVenta, tooltipCierreVenta].forEach(el => el.style.display = 'none');
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));

        if (document.getElementById('titulo').value.trim() === '') esValido = false;
        if (listaDeFunciones.length === 0) {
            tooltipFunciones.textContent = 'Añade al menos una función.'; tooltipFunciones.style.display = 'block'; esValido = false;
        }

        const iniInput = document.getElementById('inicio_venta'), finInput = document.getElementById('cierre_venta');
        if (fpInicioVenta.selectedDates.length === 0) {
             resaltarError(iniInput, tooltipInicioVenta, 'Requerido.'); esValido = false;
        } else if (listaDeFunciones.length > 0 && fpInicioVenta.selectedDates[0] >= listaDeFunciones[0]) {
             resaltarError(iniInput, tooltipInicioVenta, 'Debe ser antes de la 1ª función.'); esValido = false;
        }

        if (fpCierreVenta.selectedDates.length === 0) {
            resaltarError(finInput, tooltipCierreVenta, 'Requerido.'); esValido = false;
        } else if (fpInicioVenta.selectedDates.length > 0 && fpCierreVenta.selectedDates[0] <= fpInicioVenta.selectedDates[0]) {
            resaltarError(finInput, tooltipCierreVenta, 'Debe ser posterior al inicio.'); esValido = false;
        }

        if (document.getElementById('descripcion').value.trim() === '') esValido = false;
        if (document.getElementById('imagen').files.length === 0) esValido = false;
        if (document.getElementById('tipo').value === '') esValido = false;

        btnSubmit.disabled = !esValido;
        return esValido;
    }

    function resaltarError(input, tooltip, msg) {
        input.classList.add('input-error'); tooltip.textContent = msg; tooltip.style.display = 'block';
    }

    ['titulo', 'descripcion', 'imagen', 'tipo'].forEach(id => {
        document.getElementById(id).addEventListener(id === 'imagen' || id === 'tipo' ? 'change' : 'input', validarFormulario);
    });

    document.getElementById('eventoForm').addEventListener('submit', function(e){
        if(!validarFormulario()){ e.preventDefault(); alert("Revisa los errores del formulario."); }
    });
});
</script>
</body>
</html>