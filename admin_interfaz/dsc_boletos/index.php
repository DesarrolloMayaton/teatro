<?php
// 1. CONEXIÓN
include "../../evt_interfaz/conexion.php"; 

$id_evento_seleccionado = $_GET['id_evento'] ?? null; 
$nombre_evento = "";
$eventos = [];
$all_categorias = [];

// 2. Cargar todos los eventos para el dropdown
$res_eventos = $conn->query("SELECT id_evento, titulo FROM evento WHERE finalizado = 0 ORDER BY titulo ASC");
if ($res_eventos) {
    while ($row = $res_eventos->fetch_assoc()) {
        $eventos[] = $row;
    }
}

// 3. Cargar TODAS las categorías para pasarlas a JavaScript
$res_cats = $conn->query("SELECT id_categoria, id_evento, nombre_categoria, precio FROM categorias ORDER BY nombre_categoria ASC");
if($res_cats) {
    $all_categorias = $res_cats->fetch_all(MYSQLI_ASSOC);
}

// 4. Obtener el nombre del evento para el título
if ($id_evento_seleccionado) {
    if ($id_evento_seleccionado == 'todos') {
         $nombre_evento = "Todos los Eventos";
    } else {
        foreach ($eventos as $evento) {
            if ($evento['id_evento'] == $id_evento_seleccionado) {
                $nombre_evento = $evento['titulo'];
                break;
            }
        }
    }
}
$conn->close();

$EVENTOS_JSON = json_encode($eventos, JSON_UNESCAPED_UNICODE);
$ALL_CATEGORIAS_JSON = json_encode($all_categorias, JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Descuentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        /* (Estilos sin cambios) */
        :root {
            --primary-color: #2563eb; --primary-dark: #1e40af;
            --success-color: #10b981; --danger-color: #ef4444;
            --warning-color: #f59e0b; --info-color: #3b82f6;
            --bg-primary: #f8fafc; --bg-secondary: #ffffff;
            --text-primary: #0f172a; --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius-sm: 6px; 
            --radius-md: 8px; 
            --radius-lg: 12px;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--bg-primary), #e2e8f0);
            color: var(--text-primary); 
            padding: 20px;
            min-height: 100vh;
            font-size: 15px;
        }
        .container-fluid { max-width: 1400px; margin: 0 auto; }
        .card {
            background: var(--bg-secondary); border: 1px solid var(--border-color);
            border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
            margin-bottom: 20px;
            padding: 20px;
        }
        h2, h3 { 
            color: var(--text-primary); 
            font-weight: 700; 
            letter-spacing: -0.5px;
            margin-bottom: 12px;
        }
        h2 { font-size: 1.6rem; }
        h3 { font-size: 1.3rem; }
        .form-label { 
            font-weight: 600; 
            color: var(--text-primary); 
            font-size: 0.85rem;
            margin-bottom: 4px;
        }
        .form-control, .form-select {
            border-radius: var(--radius-sm); 
            padding: 8px 12px;
            border: 1px solid var(--border-color); 
            background: var(--bg-primary);
            font-size: 0.9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
        }
        .form-select-lg {
            font-size: 1.1rem;
            padding: 12px 15px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: var(--radius-sm); 
            font-weight: 600;
            border: none; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            gap: 6px; 
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-success { background: var(--success-color); color: white; }
        .btn-info { background: var(--info-color); color: white; }
        .btn-warning { background: var(--warning-color); color: white; }
        .btn-danger { background: var(--danger-color); color: white; }
        .btn-secondary { background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-sm { 
            padding: 5px 10px; 
            font-size: 0.8rem; 
            gap: 4px;
        }
        .table thead { background: var(--bg-primary); }
        .table th { 
            color: var(--text-secondary); 
            text-transform: uppercase; 
            font-size: 0.75rem;
            letter-spacing: 0.5px; 
            padding: 10px 12px;
        }
        .table td { 
            vertical-align: middle; 
            padding: 10px 12px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    
    <div class="card p-4">
        <h2 class="m-0 text-primary d-flex align-items-center"><i class="bi bi-filter-circle-fill me-3"></i>Seleccionar Vista</h2>
        <p class="text-secondary mt-2 mb-4" style="font-size: 0.9rem;">Elige un evento para ver sus descuentos o selecciona "Todos".</p>
        
        <form method="GET" action="" class="event-selector-form">
            <select name="id_evento" class="form-select form-select-lg fw-bold" onchange="this.form.submit()">
                <option value="" <?= ($id_evento_seleccionado == null) ? 'selected' : '' ?> disabled>
                    -- Selecciona un evento para comenzar --
                </option>
                <option value="todos" <?= ($id_evento_seleccionado == 'todos') ? 'selected' : '' ?>>
                    -- Mostrar Todos los Eventos --
                </option>
                <?php foreach ($eventos as $evento): ?>
                    <option value="<?= $evento['id_evento'] ?>" <?= ($id_evento_seleccionado == $evento['id_evento']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($evento['titulo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($id_evento_seleccionado): ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h3 id="form-title" class="mb-4 fw-bold text-success"><i class="bi bi-plus-circle-fill me-2"></i>Nueva Promoción</h3>
                <form id="form-promocion" class="row g-3" autocomplete="off">
                    <input type="hidden" id="id_promocion" value="">
                    <div class="col-12" id="campo-nombre-fijo" style="display:none;">
                        <label for="nombre_fijo" class="form-label">Nombre de Promoción</label>
                        <input type="text" id="nombre_fijo" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label for="id_evento" class="form-label">Evento Aplicable</label>
                        <select id="id_evento" class="form-select">
                            <option value="">-- Global (para todos) --</option>
                            <?php foreach ($eventos as $evento): ?>
                                <option value="<?= $evento['id_evento'] ?>">
                                    <?= htmlspecialchars($evento['titulo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6" id="campo-categoria-select">
                        <label for="id_categoria_select" class="form-label">Categoría (Boleto)</label>
                        <select id="id_categoria_select" class="form-select" required>
                            <option value="">-- Primero elija un evento --</option>
                        </select>
                        <input type="hidden" id="tipo_boleto_hidden" value="">
                    </div>
                    <div class="col-md-4">
                        <label for="precio_base" class="form-label">Precio Base ($)</label>
                        <input type="number" id="precio_base" class="form-control" min="0" step="0.01" placeholder="0.00" required>
                    </div>
                    <div class="col-md-4">
                        <label for="modo" class="form-label">Modo Desc.</label>
                        <select id="modo" class="form-select">
                            <option value="porcentaje">%</option>
                            <option value="fijo">$</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="valor" class="form-label">Valor Desc.</label>
                        <input type="number" id="valor" class="form-control" min="0" step="0.01" placeholder="Ej: 20" required>
                    </div>
                    <div class="col-md-4">
                        <label for="min_cantidad" class="form-label">Mín. Bol.</label>
                        <input type="number" id="min_cantidad" class="form-control" min="1" step="1" value="1">
                    </div>
                    <div class="col-md-4">
                        <label for="desde" class="form-label">Desde</label>
                        <input type="date" id="desde" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="hasta" class="form-label">Hasta</label>
                        <input type="date" id="hasta" class="form-control">
                    </div>
                     <div class="col-12">
                        <label for="condiciones" class="form-label">Condiciones (Opcional)</label>
                        <input type="text" id="condiciones" class="form-control" placeholder="Ej: No aplica con otras promos">
                    </div>
                    <div class="d-grid gap-2 pt-2">
                        <button type="submit" id="btn-guardar" class="btn btn-success py-2 fs-6">
                            <i class="bi bi-check-circle"></i> Guardar
                        </button>
                        <button type="button" id="btn-clear" class="btn btn-secondary py-2 fs-6" onclick="limpiarForm()">
                            <i class="bi bi-x-circle"></i> Cancelar / Limpiar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card p-4 h-100">
                <h3 class="mb-3 fw-bold">Promociones para "<?= htmlspecialchars($nombre_evento) ?>"</h3>
                <div class="table-responsive">
                    <table id="tabla-promos" class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Precio Base</th>
                                <th>Descuento</th>
                                <th>Min.</th>
                                <th>Vigencia</th>
                                <th>Activo</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="7" class="text-center text-muted p-4">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <div class="card p-5 text-center">
        <h3 class="text-secondary"><i class="bi bi-mouse me-2"></i>Por favor, selecciona una vista</h3>
        <p class="text-secondary">Elige un evento o "Mostrar Todos" en el menú superior para ver y crear promociones.</p>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API_URL = 'promos_api.php';
const PHP_EVENTOS = <?= $EVENTOS_JSON ?>;
const ALL_CATEGORIAS = <?= $ALL_CATEGORIAS_JSON ?>;

const state = { 
    promos: [], 
    editingId: null,
    filtroId: <?= json_encode($id_evento_seleccionado) ?>,
    allCategorias: ALL_CATEGORIAS
};

// ====== HELPERS ======
const $ = s => document.querySelector(s);
const fmtMoney = n => Number(n||0).toLocaleString('es-MX',{style:'currency','currency':'MXN'});
function getTodayString() {
    const today = new Date();
    today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
    return today.toISOString().split('T')[0];
}

// ====== API (AJAX) ======
// ***** CAMBIO AQUÍ *****
// Se modificó la función api para enviar el filtro en la URL de "list"
async function api(action, payload = {}, id = null) {
    let url = `${API_URL}?action=${action}`;
    if (id) {
        url += `&id=${id}`;
    }
    
    // NUEVO: Añadir filtro a la URL si es la acción 'list'
    if (action === 'list' && payload.filtro_evento) {
        url += `&filtro_evento=${payload.filtro_evento}`;
    }

    const config = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    };

    if (action === 'list' || action === 'delete') {
        config.method = 'GET';
        // 'list' ya no necesita body, se envía por URL
    } else {
        // create y update sí envían body
        config.body = JSON.stringify(payload);
    }
    
    try {
        const r = await fetch(url, config);
        // ... (resto de la función api sin cambios) ...
        if (action === 'export' && r.ok) {
            const blob = await r.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = 'promociones.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            return { ok: true, message: 'Exportado' };
        }
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'Error de API');
        return j;
    } catch (e) { 
        if (action === 'export') {
             Swal.fire('Error de Red', 'No se pudo generar el archivo de exportación.', 'error'); 
        } else {
             Swal.fire('Error de Red', e.message, 'error'); 
        }
        throw e; 
    }
}

// ====== LÓGICA DE UI ======
function renderTable() {
    const tb = $('#tabla-promos tbody');
    tb.innerHTML = '';
    
    // ***** CAMBIO AQUÍ *****
    // Ya no se filtra en JS. 'state.promos' ya viene filtrado desde el API.
    const promosFiltradas = state.promos; 
    
    if (!promosFiltradas.length) {
        tb.innerHTML = `<tr><td colspan="7" class="text-center text-secondary p-4">No hay promociones para esta vista.</td></tr>`;
        return;
    }
    
    promosFiltradas.forEach(p => {
        // (Renderizado de fila sin cambios)
        let descTxt = p.modo_calculo === 'porcentaje' ? `${p.valor}% OFF` : `${fmtMoney(p.valor)} OFF`;
        let estado = p.activo ? '<span style="color:var(--success-color)">Activa</span>' : '<span style="color:var(--text-secondary)">Inactiva</span>';
        let precio = fmtMoney(p.precio);
        let eventoNombre = p.evento_titulo || 'Global (Todos)';
        let vigencia = (p.fecha_desde || 'N/A').split(' ')[0] + ' al ' + (p.fecha_hasta || 'N/A').split(' ')[0];
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong style="color:var(--primary-color)">${p.nombre}</strong><br><small class="text-secondary">${eventoNombre}</small></td>
            <td>${precio}</td>
            <td style="color:var(--success-color); font-weight: 600;">${descTxt}</td>
            <td>${p.min_cantidad}</td>
            <td><small class="text-secondary">${vigencia}</small></td>
            <td>${estado}</td>
            <td class="text-end">
                <button class="btn btn-warning btn-sm text-white" onclick='cargarEnForm(${p.id_promocion})' title="Editar"><i class="bi bi-pencil-fill"></i></button>
                <button class="btn btn-danger btn-sm" onclick="eliminar(${p.id_promocion}, '${p.nombre.replace(/'/g, "\\'")}')"><i class="bi bi-trash-fill"></i></button>
            </td>
        `;
        tb.appendChild(tr);
    });
}

// (popularCategorias, limpiarForm, cargarEnForm, guardar, eliminar... sin cambios)
function popularCategorias(eventoId) {
    const selectCat = $('#id_categoria_select');
    selectCat.innerHTML = '<option value="">-- Selecciona una categoría --</option>';
    if (!eventoId) {
        selectCat.innerHTML = '<option value="">-- Primero elija un evento --</option>';
        return;
    }
    const categoriasFiltradas = state.allCategorias.filter(c => c.id_evento == eventoId);
    if (!categoriasFiltradas.length) {
        selectCat.innerHTML = '<option value="">-- No hay categorías para este evento --</option>';
        return;
    }
    categoriasFiltradas.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.id_categoria;
        option.textContent = `${cat.nombre_categoria} ($${cat.precio})`;
        selectCat.appendChild(option);
    });
}

function limpiarForm() {
    $('#form-promocion').reset();
    $('#id_promocion').value = '';
    state.editingId = null;
    $('#form-title').innerHTML = '<i class="bi bi-plus-circle-fill me-2 text-success"></i>Nueva Promoción';
    $('#form-title').classList.remove('text-warning');
    $('#form-title').classList.add('text-success');
    $('#btn-guardar').className = 'btn btn-success py-2 fs-6';
    $('#btn-guardar').innerHTML = '<i class="bi bi-check-circle"></i> Guardar';
    $('#campo-nombre-fijo').style.display = 'none';
    $('#campo-categoria-select').style.display = 'block';
    $('#precio_base').disabled = false;
    $('#tipo_boleto_hidden').value = '';
    const defaultEventId = (state.filtroId && state.filtroId !== 'todos') ? state.filtroId : '';
    $('#id_evento').value = defaultEventId;
    const todayString = getTodayString();
    $('#desde').min = todayString;
    $('#hasta').min = todayString;
    $('#desde').max = '';
    $('#hasta').value = '';
    $('#desde').value = '';
    popularCategorias(defaultEventId);
}

function cargarEnForm(id) {
    const p = state.promos.find(x => x.id_promocion == id);
    if (!p) return;
    state.editingId = id;
    $('#id_promocion').value = p.id_promocion;
    $('#nombre_fijo').value = p.nombre;
    $('#precio_base').value = p.precio;
    $('#modo').value = p.modo_calculo;
    $('#valor').value = p.valor;
    $('#min_cantidad').value = p.min_cantidad;
    const todayString = getTodayString();
    const fechaDesde = p.fecha_desde ? p.fecha_desde.split(' ')[0] : '';
    $('#desde').value = fechaDesde;
    $('#desde').min = todayString;
    const fechaHasta = p.fecha_hasta ? p.fecha_hasta.split(' ')[0] : '';
    $('#hasta').value = fechaHasta;
    $('#hasta').min = (fechaDesde && fechaDesde > todayString) ? fechaDesde : todayString; 
    if (fechaHasta) {
        $('#desde').max = fechaHasta;
    } else {
        $('#desde').max = '';
    }
    $('#condiciones').value = p.condiciones || '';
    $('#id_evento').value = p.id_evento || '';
    $('#form-title').innerHTML = '<i class="bi bi-pencil-square me-2 text-warning"></i>Editar Promoción';
    $('#form-title').classList.remove('text-success');
    $('#form-title').classList.add('text-warning');
    $('#btn-guardar').className = 'btn btn-warning py-2 fs-6';
    $('#btn-guardar').innerHTML = '<i class="bi bi-check-circle"></i> Actualizar';
    $('#campo-nombre-fijo').style.display = 'block';
    $('#campo-categoria-select').style.display = 'none';
    $('#precio_base').disabled = true;
    $('#form-title').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function guardar(e) {
    e.preventDefault();
    const btn = $('#btn-guardar');
    const payload = {
        id_evento: $('#id_evento').value || null,
        precio_base: $('#precio_base').value,
        modo: $('#modo').value,
        valor: $('#valor').value,
        min_cantidad: $('#min_cantidad').value,
        desde: $('#desde').value,
        hasta: $('#hasta').value,
        condiciones: $('#condiciones').value,
        tipo_boleto: $('#tipo_boleto_hidden').value,
        nombre_fijo: $('#nombre_fijo').value
    };
    if (!payload.precio_base || payload.precio_base <= 0) {
        Swal.fire('Error', 'El "Precio Base" debe ser mayor a 0.', 'error');
        return;
    }
     if (!payload.valor || payload.valor <= 0) {
        Swal.fire('Error', 'El "Valor Descuento" debe ser mayor a 0.', 'error');
        return;
    }
    if (!state.editingId && !payload.tipo_boleto) {
         Swal.fire('Error', 'Debe seleccionar una "Categoría (Boleto)"', 'error');
        return;
    }
    const action = state.editingId ? 'update' : 'create';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Guardando...';
    try {
        const data = await api(action, payload, state.editingId);
        
        // Notificar cambio si hay id_evento
        if (data.notify_change && data.id_evento) {
            localStorage.setItem('descuentos_actualizados', JSON.stringify({
                id_evento: data.id_evento,
                timestamp: Date.now()
            }));
        }
        
        await cargarDatos(); 
        limpiarForm(); 
        Swal.fire({
            icon: 'success', title: '¡Guardado!',
            toast: true, position: 'top-end', showConfirmButton: false, timer: 2000,
            showClass: { popup: 'animate__animated animate__fadeInDown' },
            hideClass: { popup: 'animate__animated animate__fadeOutUp' }
        });
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    } finally {
        btn.disabled = false;
    }
}

function eliminar(id, nombre) {
    Swal.fire({
        title: `¿Eliminar "${nombre}"?`,
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'var(--danger-color)',
        cancelButtonColor: 'var(--text-secondary)',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                await api('delete', {}, id); 
                await cargarDatos(); 
                limpiarForm(); 
                Swal.fire('¡Eliminado!', 'La promoción ha sido borrada.', 'success');
            } catch (e) { 
                Swal.fire('Error', e.message, 'error'); 
            }
        }
    });
}

// ***** CAMBIO AQUÍ *****
// Se envía el filtro al API al cargar datos
async function cargarDatos() {
    try {
        // Enviar el filtro actual al API
        const data = await api('list', { filtro_evento: state.filtroId });
        state.promos = data.items || [];
        renderTable(); 
    } catch (e) {
        if ($('#tabla-promos tbody')) {
            $('#tabla-promos tbody').innerHTML = `<tr><td colspan="7" class="text-center text-danger p-4">Error al cargar: ${e.message}</td></tr>`;
        }
    }
}

// === INICIALIZACIÓN ===
document.addEventListener('DOMContentLoaded', () => {
    
    if (state.filtroId) {
        $('#form-promocion').addEventListener('submit', guardar);
        
        $('#id_evento').addEventListener('change', (e) => {
            popularCategorias(e.target.value);
            $('#id_categoria_select').value = '';
            $('#precio_base').value = '';
            $('#tipo_boleto_hidden').value = '';
            $('#precio_base').disabled = false;
        });

        $('#id_categoria_select').addEventListener('change', (e) => {
            const catId = e.target.value;
            const cat = state.allCategorias.find(c => c.id_categoria == catId);
            if (cat) {
                $('#precio_base').value = cat.precio;
                $('#tipo_boleto_hidden').value = cat.nombre_categoria;
                $('#precio_base').disabled = true;
            } else {
                $('#precio_base').value = '';
                $('#tipo_boleto_hidden').value = '';
                $('#precio_base').disabled = false;
            }
        });

        const todayString = getTodayString();
        const desdeInput = $('#desde');
        const hastaInput = $('#hasta');
        
        desdeInput.min = todayString;
        hastaInput.min = todayString;

        desdeInput.addEventListener('change', () => {
            if (desdeInput.value) {
                hastaInput.min = (desdeInput.value > todayString) ? desdeInput.value : todayString;
            } else {
                hastaInput.min = todayString;
            }
        });
        
        hastaInput.addEventListener('change', () => {
            if (hastaInput.value) {
                desdeInput.max = hastaInput.value;
            } else {
                desdeInput.max = '';
            }
        });

        cargarDatos();
        limpiarForm();
    }
});
</script>

</body>
</html>