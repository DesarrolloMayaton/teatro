<?php
// 1. CONEXIÓN
include "../../evt_interfaz/conexion.php"; 

$id_evento_seleccionado = null;
$nombre_evento = "";
$eventos = [];
$categorias_del_evento = [];
// NUEVO: Inicializar variables de precio
$precio_general_actual = '';
$precio_discapacitado_actual = '';

// 2. Cargar todos los eventos para el dropdown
$res_eventos = $conn->query("SELECT id_evento, titulo FROM evento WHERE finalizado = 0 ORDER BY titulo ASC");
if ($res_eventos) {
    while ($row = $res_eventos->fetch_assoc()) {
        $eventos[] = $row;
    }
}

// 3. Verificar si se seleccionó un evento
if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    $id_evento_seleccionado = (int)$_GET['id_evento'];
    
    // 4. Cargar las categorías SÓLO para ESE evento
    $stmt = $conn->prepare("SELECT * FROM categorias WHERE id_evento = ? ORDER BY precio ASC");
    $stmt->bind_param("i", $id_evento_seleccionado);
    $stmt->execute();
    $res_categorias = $stmt->get_result();
    if ($res_categorias) {
        while ($row = $res_categorias->fetch_assoc()) {
            $categorias_del_evento[] = $row;
        }
    }
    $stmt->close();
    
    // NUEVO: Buscar precios actuales de General y Discapacitado
    foreach ($categorias_del_evento as $cat) {
        if (strtolower($cat['nombre_categoria']) === 'general') {
            $precio_general_actual = $cat['precio'];
        }
        if (strtolower($cat['nombre_categoria']) === 'discapacitado') {
            $precio_discapacitado_actual = $cat['precio'];
        }
    }
    
    // (Opcional) Obtener el nombre del evento para el título
    foreach ($eventos as $evento) {
        if ($evento['id_evento'] == $id_evento_seleccionado) {
            $nombre_evento = $evento['titulo'];
            break;
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías de Boletos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* --- ESTILOS UNIFICADOS (COMO EN index.php) --- */
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
            --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--bg-primary), #e2e8f0);
            color: var(--text-primary); padding: 30px; min-height: 100vh;
        }
        .container-fluid { max-width: 1200px; margin: 0 auto; }
        .card {
            background: var(--bg-secondary); border: 1px solid var(--border-color);
            border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
            margin-bottom: 24px; transition: all 0.3s ease;
        }
        .card:hover { box-shadow: var(--shadow-lg); }
        h2, h3 { color: var(--text-primary); font-weight: 700; letter-spacing: -0.5px; }
        .form-label { font-weight: 600; color: var(--text-primary); font-size: 0.9rem; margin-bottom: 8px; }
        .form-control, .form-select {
            border-radius: var(--radius-sm); padding: 12px 15px;
            border: 1px solid var(--border-color); background: var(--bg-primary);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
        }
        .form-control.is-invalid {
            border-color: var(--danger-color);
            background-image: none;
        }
        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }
        .validation-error {
            color: var(--danger-color);
            font-weight: 600;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        .btn {
            padding: 10px 20px; border-radius: var(--radius-sm); font-weight: 600;
            border: none; display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; transition: all 0.2s;
        }
        .btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-success { background: var(--success-color); color: white; }
        .btn-info { background: var(--info-color); color: white; }
        .btn-warning { background: var(--warning-color); color: white; }
        .btn-danger { background: var(--danger-color); color: white; }
        .btn-secondary { background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-sm { padding: 8px 14px; font-size: 0.875rem; }
        .color-dot {
            width: 24px; height: 24px; border-radius: 6px; display: inline-block;
            border: 2px solid #fff; box-shadow: var(--shadow-sm); vertical-align: middle;
        }
        .table thead { background: var(--bg-primary); }
        .table th { color: var(--text-secondary); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
        .table td { vertical-align: middle; }
    </style>
</head>
<body>

<div class="container-fluid">
    
    <div class="card p-4">
        <h2 class="m-0 text-primary d-flex align-items-center"><i class="bi bi-tags-fill me-3"></i>Gestión de Categorías</h2>
        <p class="text-secondary mt-2 mb-4">Define los precios (General, VIP, etc.) para cada evento individualmente.</p>
        
        <form method="GET" action="" class="event-selector-form">
            <select name="id_evento" class="form-select form-select-lg fw-bold" onchange="this.form.submit()">
                <option value="">-- Selecciona un Evento --</option>
                <?php foreach ($eventos as $evento): ?>
                    <option value="<?= $evento['id_evento'] ?>" <?= ($id_evento_seleccionado == $evento['id_evento']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($evento['titulo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="card p-4 bg-light border-0">
        <h3 class="mb-3 text-secondary fw-bold"><i class="bi bi-lightning-charge-fill me-2"></i>Actualización Rápida</h3>
        <p class="text-secondary mb-4 small">
            Usa esto para establecer rápidamente el precio de 'General' y 'Discapacitado'.
        </p>

        <form id="form-actualizacion-rapida" action="action.php" method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="precio_general" class="form-label small text-uppercase fw-bold">Precio General</label>
                    <div class="input-group">
                        <span class="input-group-text border-0">$</span>
                        <input type="number" name="precio_general" id="precio_general" class="form-control" step="0.01" min="0" placeholder="0.00" value="<?= htmlspecialchars($precio_general_actual) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="precio_discapacitado" class="form-label small text-uppercase fw-bold">Precio Discapacitado</label>
                    <div class="input-group">
                        <span class="input-group-text border-0">$</span>
                        <input type="number" name="precio_discapacitado" id="precio_discapacitado" class="form-control" step="0.01" min="0" placeholder="0.00" value="<?= htmlspecialchars($precio_discapacitado_actual) ?>">
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3 mt-4">
                <button type="button" class="btn btn-primary flex-grow-1 py-3" onclick="confirmarRapida('actualizar_todos', 'TODOS los eventos')">
                    <i class="bi bi-globe-americas"></i> Aplicar a TODOS
                </button>
                <?php if ($id_evento_seleccionado): ?>
                    <input type="hidden" name="id_evento" value="<?= $id_evento_seleccionado ?>">
                    <button type="button" class="btn btn-info text-white flex-grow-1 py-3" onclick="confirmarRapida('actualizar_seleccionado', 'este evento')">
                        <i class="bi bi-check-square"></i> Aplicar SÓLO a "<?= htmlspecialchars($nombre_evento) ?>"
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <?php if ($id_evento_seleccionado): ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h3 id="form-title" class="mb-4 fw-bold text-success"><i class="bi bi-plus-circle-fill me-2"></i>Nueva Categoría</h3>
                <form id="formCRUD" action="action.php" method="POST">
                    <input type="hidden" name="id_categoria" id="id_categoria" value="">
                    <input type="hidden" name="id_evento" id="id_evento_crud" value="<?= $id_evento_seleccionado ?>">
                    <input type="hidden" name="accion" id="accion" value="crear"> 
                    
                    <div class="mb-3">
                        <label for="nombre_categoria" class="form-label">Nombre Categoría</label>
                        <input type="text" name="nombre_categoria" id="nombre_categoria" class="form-control" placeholder="Ej: General" required>
                        <div id="error-nombre-categoria" class="validation-error d-none"></div>
                    </div>
                    <div class="mb-3">
                        <label for="precio" class="form-label">Precio (MXN)</label>
                        <input type="number" name="precio" id="precio" class="form-control" step="0.01" min="0" placeholder="Ej: 150.00" required>
                    </div>
                    <div class="mb-4">
                        <label for="color" class="form-label">Color</label>
                        <input type="color" name="color" id="color" class="form-control form-control-color w-100" value="#E0E0E0" title="Elige un color">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" id="btn-submit" class="btn btn-success py-2 fs-6">
                            <i class="bi bi-check-circle"></i> Guardar
                        </button>
                        <button type="button" id="btn-cancel" class="btn btn-secondary py-2 fs-6 d-none" onclick="resetForm()">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card p-4 h-100">
                <h3 class="mb-3 fw-bold">Categorías para "<?= htmlspecialchars($nombre_evento) ?>"</h3>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr><th>Color</th><th>Nombre</th><th>Precio</th><th class="text-end">Acciones</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($categorias_del_evento)): ?>
                            <tr><td colspan="4" class="text-center text-muted p-4">Aún no hay categorías definidas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($categorias_del_evento as $cat): ?>
                            <tr>
                                <td><span class="color-dot" style="background-color: <?= htmlspecialchars($cat['color']) ?>"></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($cat['nombre_categoria']) ?></td>
                                <td class="text-success fw-bold">$<?= number_format($cat['precio'], 2) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-warning btn-sm text-white" onclick='editCat(<?= json_encode($cat) ?>)'><i class="bi bi-pencil-fill"></i></button>
                                    <button class="btn btn-info btn-sm text-white" onclick='copyCat(<?= json_encode($cat) ?>)'><i class="bi bi-clipboard-fill"></i></button>
                                    <button class="btn btn-danger btn-sm" onclick="borrar(<?= $cat['id_categoria'] ?>, '<?= addslashes($cat['nombre_categoria']) ?>')"><i class="bi bi-trash-fill"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div> <?php endif; ?>

</div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const form = document.getElementById('formCRUD');
    const btnSub = document.getElementById('btn-submit');
    const btnCan = document.getElementById('btn-cancel');
    const title = document.getElementById('form-title');

    function editCat(cat) { /* ... (sin cambios) ... */ }
    function copyCat(cat) { /* ... (sin cambios) ... */ }
    function resetForm() { /* ... (sin cambios) ... */ }

    // --- MANEJO DE SWEETALERT (ADVERTENCIAS) ---

    function confirmarRapida(accion, scopeText) {
        const gen = document.getElementById('precio_general').value;
        const dis = document.getElementById('precio_discapacitado').value;
        
        // CAMBIO: Validación estricta. Ambas deben estar llenas y ser >= 0
        if (gen === '' || dis === '' || parseFloat(gen) < 0 || parseFloat(dis) < 0) {
            Swal.fire({
                icon: 'error',
                title: 'Datos Incompletos',
                text: 'Debes ingresar un precio válido (0 o mayor) en AMBOS campos (General y Discapacitado) para usar esta función.',
            });
            return;
        }
        
        Swal.fire({
            title: `¿Actualizar precios para ${scopeText}?`,
            html: `Se establecerán los siguientes precios:<br><b>General:</b> $${gen} | <b>Discapacitado:</b> $${dis}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--primary-color)',
            cancelButtonColor: 'var(--text-secondary)',
            confirmButtonText: 'Sí, aplicar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'accion';
                hiddenInput.value = accion;
                document.getElementById('form-actualizacion-rapida').appendChild(hiddenInput);
                document.getElementById('form-actualizacion-rapida').submit();
            }
        });
    }

    function borrar(id, nombre) { /* ... (sin cambios) ... */ }

    // --- MANEJO DE VENTANAS FLOTANTES (ÉXITO/ERROR) ---
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const msg = urlParams.get('msg');

        if (status === 'success') {
            Swal.fire({
                icon: 'success',
                title: msg || '¡Operación exitosa!',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                showClass: { popup: 'animate__animated animate__fadeInDown' },
                hideClass: { popup: 'animate__animated animate__fadeOutUp' }
            });
        } else if (status === 'error') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: decodeURIComponent(msg) || 'Ocurrió un problema.'
            });
        }
        
        if(status) {
            const newUrl = window.location.pathname + window.location.search.replace(/[?&]status=[^&]+|[?&]msg=[^&]+/g, '');
            window.history.replaceState({}, document.title, newUrl);
        }

        // --- VALIDACIÓN DE NOMBRE REPETIDO ---
        const inputNombre = document.getElementById('nombre_categoria');
        const errorDiv = document.getElementById('error-nombre-categoria');
        const btnSubmit = document.getElementById('btn-submit');
        const idEvento = document.getElementById('id_evento_crud').value;
        const idCategoriaInput = document.getElementById('id_categoria');
        let debounceTimer;

        inputNombre.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const nombre = inputNombre.value.trim();
                const idCatActual = idCategoriaInput.value; 
                
                if (nombre === '') {
                    inputNombre.classList.remove('is-invalid');
                    errorDiv.classList.add('d-none');
                    btnSubmit.disabled = false;
                    return;
                }

                const formData = new FormData();
                formData.append('id_evento', idEvento);
                formData.append('nombre_categoria', nombre);
                formData.append('id_categoria_actual', idCatActual);

                fetch('ajax_check_categoria.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'error') {
                        inputNombre.classList.add('is-invalid');
                        errorDiv.textContent = data.message;
                        errorDiv.classList.remove('d-none');
                        btnSubmit.disabled = true; 
                    } else {
                        inputNombre.classList.remove('is-invalid');
                        errorDiv.classList.add('d-none');
                        btnSubmit.disabled = false; 
                    }
                });
            }, 500); 
        });
    });
</script>

</body>
</html>