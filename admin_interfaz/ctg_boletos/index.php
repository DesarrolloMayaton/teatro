<?php
// 1. CONEXIÓN
include "../../evt_interfaz/conexion.php"; 

$id_evento_seleccionado = null;
$nombre_evento = "";
$eventos = [];
$categorias_del_evento = [];

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
    
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-primary);
            line-height: 1.6;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card h2, .card h3 {
            color: var(--text-primary);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h2 {
            font-size: 1.75rem;
            margin-bottom: 12px;
        }

        .card h3 {
            font-size: 1.4rem;
            margin-bottom: 16px;
        }

        .text-muted {
            color: var(--text-secondary) !important;
            font-size: 0.95rem;
        }

        .event-selector-form {
            margin-top: 16px;
        }

        .form-select {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.2s;
            background-color: var(--bg-primary);
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .form-select:hover {
            border-color: var(--primary-color);
        }

        .precio-rapido-help {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 8px;
            margin-bottom: 20px;
        }

        .form-card {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-grid label,
        .actions label {
            margin-bottom: 8px;
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .form-grid .full-width {
            grid-column: 1 / -1;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background-color: var(--bg-secondary);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .form-control:hover {
            border-color: var(--primary-color);
        }

        .form-control-color {
            height: 45px;
            cursor: pointer;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            border-radius: var(--radius-sm);
            padding: 12px 20px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .actions .btn {
            width: 100%;
        }

        .btn i {
            font-size: 1.1em;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-info {
            background: var(--info-color);
            color: white;
        }

        .btn-info:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-sm {
            padding: 8px 14px;
            font-size: 0.875rem;
        }

        .color-dot {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: var(--shadow-sm);
            vertical-align: middle;
        }

        .table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-responsive {
            border-radius: var(--radius-sm);
            overflow: hidden;
        }

        .table thead {
            background: var(--bg-primary);
        }

        .table th {
            font-weight: 600;
            color: var(--text-primary);
            padding: 14px 16px;
            border-bottom: 2px solid var(--border-color);
        }

        .table td {
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr {
            transition: all 0.2s;
        }

        .table tbody tr:hover {
            background-color: var(--bg-primary);
        }

        .table-actions .btn {
            margin-right: 8px;
            margin-bottom: 4px;
        }

        #alert-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 1050;
            max-width: 400px;
        }

        .alert {
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            font-size: 0.95rem;
            border: none;
            box-shadow: var(--shadow-lg);
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: fadeIn 0.4s ease;
        }

        .modal-content {
            border-radius: var(--radius-lg);
            border: none;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 20px 24px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 16px 24px;
        }

        .list-group-item {
            border: 1px solid var(--border-color);
            padding: 14px 18px;
            transition: all 0.2s;
        }

        .list-group-item:hover {
            background-color: var(--bg-primary);
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .actions {
                grid-template-columns: 1fr;
            }

            .table-actions .btn {
                width: 100%;
                margin-right: 0;
                margin-bottom: 8px;
            }

            #alert-container {
                right: 16px;
                left: 16px;
                max-width: none;
            }
        }

        /* Mejoras adicionales */
        .bg-light {
            background: var(--bg-primary) !important;
        }

        hr {
            border: none;
            height: 1px;
            background: var(--border-color);
            margin: 24px 0;
        }

        /* Animación para botones */
        .btn:active {
            transform: scale(0.98);
        }

        /* Mejora en inputs de color */
        input[type="color"] {
            border: 2px solid var(--border-color);
            transition: all 0.2s;
        }

        input[type="color"]:hover {
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>

    <div id="alert-container">
    </div>
    <div class="container-fluid">
        
        <div class="card p-3">
            <h2><i class="bi bi-tags-fill"></i> Gestión de Categorías por Evento</h2>
            <p class="text-muted">
                Define los precios (General, VIP, etc.) para cada evento individualmente.
            </p>
            
            <form method="GET" action="" class="event-selector-form">
                <select name="id_evento" class="form-select form-select-lg" onchange="this.form.submit()">
                    <option value="">-- Selecciona un Evento --</option>
                    <?php foreach ($eventos as $evento): ?>
                        <option value="<?php echo $evento['id_evento']; ?>" <?php echo ($id_evento_seleccionado == $evento['id_evento']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($evento['titulo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                </form>
            </div>

        <div class="card p-4">
            <h3 class="mb-3">Actualización Rápida de Precios</h3>
            <p class="precio-rapido-help">
                Usa este formulario para establecer rápidamente el precio de 'General' y 'Discapacitado' en uno o todos los eventos.
            </p>

            <form id="form-actualizacion-rapida" action="action.php" method="POST">
                
                <div class="form-grid">
                    <div>
                        <label for="precio_general" class="form-label">Precio General (MXN)</label>
                        <input type="number" name="precio_general" id="precio_general" class="form-control" step="0.01" min="0" placeholder="Ej: 100.00">
                    </div>
                    <div>
                        <label for="precio_discapacitado" class="form-label">Precio Discapacitado (MXN)</label>
                        <input type="number" name="precio_discapacitado" id="precio_discapacitado" class="form-control" step="0.01" min="0" placeholder="Ej: 80.00">
                    </div>
                </div>

                <div class="actions">
                    <button type="button" id="btn-actualizar-todos" data-accion="actualizar_todos" class="btn btn-primary">
                        <i class="bi bi-globe"></i> Actualizar TODOS
                    </button>
                    
                    <?php if ($id_evento_seleccionado): ?>
                        <input type="hidden" name="id_evento" value="<?php echo $id_evento_seleccionado; ?>">
                        <button type="button" id="btn-actualizar-seleccionado" data-accion="actualizar_seleccionado" class="btn btn-info">
                            <i class="bi bi-check-square"></i> Actualizar SÓLO "<?php echo htmlspecialchars($nombre_evento); ?>"
                        </button>
                    <?php endif; ?>
                </div>

            </form>
        </div>
        <?php if ($id_evento_seleccionado): ?>
            <div class="card p-4 form-card">
                <h3 id="form-title" class="mb-3">Crear Nueva Categoría</h3>
                
                <form action="action.php" method="POST">
                    
                    <input type="hidden" name="id_categoria" id="id_categoria" value="">
                    <input type="hidden" name="id_evento" value="<?php echo $id_evento_seleccionado; ?>">
                    <input type="hidden" name="accion" id="accion" value="crear"> 
                    
                    <div class="form-grid">
                        
                        <div class="full-width">
                            <label for="nombre_categoria" class="form-label">Nombre Categoría</label>
                            <input type="text" name="nombre_categoria" id="nombre_categoria" class="form-control" placeholder="Ej: General" required>
                        </div>
                        
                        <div>
                            <label for="precio" class="form-label">Precio (MXN)</label>
                            <input type="number" name="precio" id="precio" class="form-control" step="0.01" min="0" placeholder="Ej: 150.00" required>
                        </div>
                        
                        <div>
                            <label for="color" class="form-label">Color</label>
                            <input type="color" name="color" id="color" class="form-control form-control-color" value="#E0E0E0" title="Elige un color">
                        </div>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> 
                            <span id="btn-submit-text">Guardar Categoría</span>
                        </button>
                        
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="bi bi-x-circle"></i> Cancelar Edición
                        </button>
                    </div>
                </form>
            </div>

            <div class="card p-3 mt-4">
                <h3 class="mb-3">Categorías para "<?php echo htmlspecialchars($nombre_evento); ?>"</h3>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Color</th>
                                <th>Nombre</th>
                                <th>Precio Base</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($categorias_del_evento)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted p-4">
                                    Aún no hay categorías definidas para este evento.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categorias_del_evento as $cat): ?>
                                <tr>
                                    <td>
                                        <span class="color-dot" style="background-color: <?php echo htmlspecialchars($cat['color']); ?>;" 
                                              title="<?php echo htmlspecialchars($cat['color']); ?>"></span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($cat['nombre_categoria']); ?></strong></td>
                                    <td>$<?php echo number_format($cat['precio'], 2); ?></td>
                                    <td class="text-end table-actions">
                                        <button class="btn btn-warning btn-sm" 
                                                onclick="editCat('<?php echo htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8'); ?>')">
                                            <i class="bi bi-pencil-fill"></i> Editar
                                        </button>
                                        
                                        <button class="btn btn-info btn-sm" 
                                                onclick="copyCat('<?php echo htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8'); ?>')">
                                            <i class="bi bi-clipboard"></i> Copiar
                                        </button>
                                        
                                        <a href="action.php?accion=borrar&id_categoria=<?php echo $cat['id_categoria']; ?>&id_evento=<?php echo $id_evento_seleccionado; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('¿Estás seguro de borrar la categoría <?php echo htmlspecialchars(addslashes($cat['nombre_categoria'])); ?>?')">
                                            <i class="bi bi-trash-fill"></i> Borrar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="card p-5 text-center bg-light">
                <h4 class="text-muted">Por favor, selecciona un evento del menú superior para comenzar.</h4>
            </div>
        <?php endif; ?>

    </div> 

<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="confirmModalLabel"><i class="bi bi-exclamation-triangle-fill"></i> Confirmar Actualización Masiva</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="fs-5" id="modal-mensaje-accion"></p>
        
        <ul class="list-group mb-3">
            <li class="list-group-item d-flex justify-content-between align-items-center">
                Precio General: <strong id="modal-precio-general">N/A</strong>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                Precio Discapacitado: <strong id="modal-precio-discapacitado">N/A</strong>
            </li>
        </ul>

        <p class="text-danger fw-bold">
            <i class="bi bi-shield-lock-fill"></i> Esta acción no se puede deshacer.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-warning" id="modal-btn-confirmar">
            <i class="bi bi-check-circle"></i> Sí, Aplicar Cambios
        </button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- LÓGICA CRUD JS (Edición Individual) ---
    function editCat(jsonString) {
        const cat = JSON.parse(jsonString);
        
        document.getElementById('form-title').innerText = "Editar Categoría";
        document.getElementById('id_categoria').value = cat.id_categoria;
        document.getElementById('nombre_categoria').value = cat.nombre_categoria;
        document.getElementById('precio').value = cat.precio;
        document.getElementById('color').value = cat.color;
        document.getElementById('accion').value = "actualizar";
        document.getElementById('btn-submit-text').innerText = "Actualizar Categoría";
        
        window.scrollTo(0, 0);
    }
    function copyCat(jsonString) {
        const cat = JSON.parse(jsonString);
        
        document.getElementById('form-title').innerText = "Copiar Categoría (Crear Nueva)";
        document.getElementById('id_categoria').value = "";
        document.getElementById('nombre_categoria').value = cat.nombre_categoria + " (Copia)";
        document.getElementById('precio').value = cat.precio;
        document.getElementById('color').value = cat.color;
        document.getElementById('accion').value = "crear";
        document.getElementById('btn-submit-text').innerText = "Guardar Categoría";
        
        window.scrollTo(0, 0);
    }
    function resetForm() {
        document.getElementById('form-title').innerText = "Crear Nueva Categoría";
        document.getElementById('id_categoria').value = "";
        document.getElementById('nombre_categoria').value = "";
        document.getElementById('precio').value = "";
        document.getElementById('color').value = "#E0E0E0";
        document.getElementById('accion').value = "crear";
        document.getElementById('btn-submit-text').innerText = "Guardar Categoría";
    }

    // --- NUEVO: Lógica de la Alerta y el Modal de Confirmación ---
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const msg = urlParams.get('msg');
        const alertContainer = document.getElementById('alert-container');
        
        // --- 1. Control de Alerta de Éxito/Error ---
        if (status) {
            let alertHTML = '';
            
            if (status === 'success') {
                alertHTML = `
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        ¡Operación completada con éxito!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
            } else if (status === 'error' && msg) {
                const decodedMsg = decodeURIComponent(msg);
                alertHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-x-octagon-fill me-2"></i>
                        Error: ${decodedMsg}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
            }

            alertContainer.innerHTML = alertHTML;

            // Ocultar después de 4 segundos (solo éxito)
            if (status === 'success') {
                setTimeout(() => {
                    const alertElement = alertContainer.querySelector('.alert');
                    if (alertElement) {
                         // Usa la función dismiss de Bootstrap para la animación
                        const bsAlert = bootstrap.Alert.getOrCreateInstance(alertElement);
                        bsAlert.dispose(); 
                    }
                }, 4000); 
            }
            
            // Limpiar los parámetros de la URL DESPUÉS de mostrar el mensaje
            // Usamos un pequeño delay para que la alerta no desaparezca inmediatamente si es un error.
            setTimeout(() => {
                urlParams.delete('status');
                urlParams.delete('msg'); 
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }, 100); 
        }

        // --- 2. Control del Modal de Confirmación ---
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        const formRapida = document.getElementById('form-actualizacion-rapida');
        const modalBtnConfirmar = document.getElementById('modal-btn-confirmar');
        
        let accionPendiente = null;
        
        // Listener para los botones de actualización rápida
        document.querySelectorAll('#form-actualizacion-rapida .btn').forEach(button => {
            button.addEventListener('click', function() {
                const precioGeneral = formRapida.querySelector('#precio_general').value.trim();
                const precioDiscapacitado = formRapida.querySelector('#precio_discapacitado').value.trim();
                const accion = this.dataset.accion;
                const nombreEvento = '<?php echo $nombre_evento; ?>';
                
                // --- Validación: Al menos un campo debe tener valor ---
                if (!precioGeneral && !precioDiscapacitado) {
                    alert('Debes ingresar un precio en al menos uno de los campos (General o Discapacitado).');
                    return;
                }
                
                // Almacenar la acción
                accionPendiente = accion;
                
                // Rellenar el modal
                document.getElementById('modal-precio-general').textContent = precioGeneral ? `$${parseFloat(precioGeneral).toFixed(2)}` : 'Sin cambios';
                document.getElementById('modal-precio-discapacitado').textContent = precioDiscapacitado ? `$${parseFloat(precioDiscapacitado).toFixed(2)}` : 'Sin cambios';

                if (accion === 'actualizar_todos') {
                    document.getElementById('modal-mensaje-accion').innerHTML = 
                        '<i class="bi bi-globe me-2"></i> ¿Deseas aplicar estos precios a **TODOS** los eventos?';
                } else {
                    document.getElementById('modal-mensaje-accion').innerHTML = 
                        `<i class="bi bi-check-square me-2"></i> ¿Deseas aplicar estos precios **SÓLO** al evento: **${nombreEvento}**?`;
                }

                confirmModal.show();
            });
        });
        
        // Listener para el botón de confirmar dentro del modal
        modalBtnConfirmar.addEventListener('click', function() {
            if (accionPendiente) {
                // Crear un campo oculto temporal para enviar la acción
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'accion';
                hiddenInput.value = accionPendiente;
                
                formRapida.appendChild(hiddenInput);
                
                // Cerrar el modal y enviar el formulario
                confirmModal.hide();
                formRapida.submit();
            }
        });
    });
</script>

</body>
</html>