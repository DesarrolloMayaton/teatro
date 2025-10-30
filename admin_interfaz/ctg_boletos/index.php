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
        /* (Tu CSS de "Bento Box" está perfecto y se queda igual) */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f7f6;
            padding: 20px;
        }
        .card {
            border: 1px solid #e6ebf0;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
            margin-bottom: 25px; 
        }

        .event-selector-form {
            margin-bottom: 20px;
        }
        
        .precio-rapido-help {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: -15px;
            margin-bottom: 15px;
        }
        
        .form-card {
            background-color: #f8f9fa;
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
            font-weight: 500;
        }
        .form-grid .full-width {
            grid-column: 1 / -1; 
        }
        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px; 
            margin-top: 25px; 
        }
        .actions .btn {
            width: 100%;
            padding: 12px 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .actions .btn i {
            margin-right: 8px;
        }
        .color-dot {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 3px rgba(0,0,0,0.3);
            vertical-align: middle;
        }
        .btn-sm i {
            font-size: 0.9rem;
        }
        .table-actions .btn {
            margin-right: 10px; 
        }
        
        /* --- ESTILO PARA LA ALERTA DE CONFIRMACIÓN --- */
        #alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            transition: opacity 0.5s ease-out;
        }
        /* ------------------------------------------- */
        
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .actions { grid-template-columns: 1fr; }
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

            <form action="action.php" method="POST">
                
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
                    <button type="submit" name="accion" value="actualizar_todos" class="btn btn-primary" 
                            onclick="return confirm('¿Estás seguro de actualizar estos precios para TODOS los eventos?')">
                        <i class="bi bi-globe"></i> Actualizar TODOS
                    </button>
                    
                    <?php if ($id_evento_seleccionado): ?>
                        <input type="hidden" name="id_evento" value="<?php echo $id_evento_seleccionado; ?>">
                        <button type="submit" name="accion" value="actualizar_seleccionado" class="btn btn-info">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- LÓGICA CRUD JS (Igual) ---
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

    // --- NUEVO: Lógica de la Alerta de Confirmación ---
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const msg = urlParams.get('msg');
        const alertContainer = document.getElementById('alert-container');
        
        if (status === 'success') {
            const alertHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    ¡Operación completada con éxito!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            alertContainer.innerHTML = alertHTML;

            // Ocultar después de 4 segundos
            setTimeout(() => {
                const alertElement = alertContainer.querySelector('.alert');
                if (alertElement) {
                    alertElement.classList.remove('show');
                    alertElement.classList.add('fade');
                    // Después de que la transición termine, eliminar de la URL y el DOM
                    setTimeout(() => {
                        // Limpiar el parámetro 'status' de la URL sin recargar la página
                        urlParams.delete('status');
                        urlParams.delete('msg'); // Borrar también el mensaje de error si existe
                        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                        window.history.replaceState({}, document.title, newUrl);
                        
                        alertContainer.innerHTML = '';
                    }, 500); // Esperar a la transición CSS
                }
            }, 4000); 

        } else if (status === 'error' && msg) {
            const decodedMsg = decodeURIComponent(msg);
            const errorHTML = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-x-octagon-fill me-2"></i>
                    Error: ${decodedMsg}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            alertContainer.innerHTML = errorHTML;
            
            // Limpiar la URL inmediatamente si hay un error
            setTimeout(() => {
                urlParams.delete('status');
                urlParams.delete('msg');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }, 10);
        }
    });
    // ----------------------------------------------------
</script>

</body>
</html>