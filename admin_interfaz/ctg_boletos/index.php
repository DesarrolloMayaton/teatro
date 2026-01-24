<?php
// 1. CONEXIÓN
include "../../evt_interfaz/conexion.php"; 

$id_evento_seleccionado = null;
$nombre_evento = "";
$eventos = [];
$categorias_del_evento = [];

// Precios por tipo de boleto (globales por defecto)
$precios_tipo = [
    'adulto' => 0,
    'nino' => 0,
    'adulto_mayor' => 0,
    'discapacitado' => 0,
    'cortesia' => 0
];

// Verificar si existe la tabla de precios por tipo
$tabla_existe = false;
$check_table = $conn->query("SHOW TABLES LIKE 'precios_tipo_boleto'");
if ($check_table && $check_table->num_rows > 0) {
    $tabla_existe = true;
}

// Si no existe, crearla
if (!$tabla_existe) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS precios_tipo_boleto (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_evento INT NULL COMMENT 'NULL = precio global para todos los eventos',
            tipo_boleto VARCHAR(50) NOT NULL,
            precio DECIMAL(10,2) NOT NULL DEFAULT 0,
            activo TINYINT(1) DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_evento_tipo (id_evento, tipo_boleto)
        )
    ");
    
    // Insertar precios globales por defecto
    $tipos = ['adulto', 'nino', 'adulto_mayor', 'discapacitado', 'cortesia'];
    $precios_default = [80, 50, 60, 40, 0];
    
    foreach ($tipos as $i => $tipo) {
        $precio = $precios_default[$i];
        $conn->query("INSERT INTO precios_tipo_boleto (id_evento, tipo_boleto, precio) VALUES (NULL, '$tipo', $precio)");
    }
}

// Cargar precios globales actuales
$res_precios = $conn->query("SELECT tipo_boleto, precio FROM precios_tipo_boleto WHERE id_evento IS NULL");
if ($res_precios) {
    while ($row = $res_precios->fetch_assoc()) {
        $precios_tipo[$row['tipo_boleto']] = $row['precio'];
    }
}

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
    
    // Cargar precios específicos del evento (si existen)
    $stmt2 = $conn->prepare("SELECT tipo_boleto, precio FROM precios_tipo_boleto WHERE id_evento = ?");
    $stmt2->bind_param("i", $id_evento_seleccionado);
    $stmt2->execute();
    $res_precios_evento = $stmt2->get_result();
    $tiene_precios_propios = false;
    if ($res_precios_evento && $res_precios_evento->num_rows > 0) {
        $tiene_precios_propios = true;
        while ($row = $res_precios_evento->fetch_assoc()) {
            $precios_tipo[$row['tipo_boleto']] = $row['precio'];
        }
    }
    $stmt2->close();
    
    // Obtener el nombre del evento para el título
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
    <title>Categorías y Precios por Tipo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #1561f0;
            --primary-dark: #0d4fc4;
            --success: #32d74b;
            --danger: #ff453a;
            --warning: #ff9f0a;
            --info: #64d2ff;
            --bg-main: #131313;
            --bg-card: #1c1c1e;
            --bg-input: #2b2b2b;
            --text-primary: #ffffff;
            --text-secondary: #86868b;
            --border: #3a3a3c;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container-main { max-width: 1200px; margin: 0 auto; }
        
        .page-header {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
        }
        
        .page-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 16px;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }
        
        .card-title i { font-size: 1.2rem; color: var(--primary); }
        
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .form-control, .form-select {
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            font-size: 0.9rem;
        }
        
        .form-control:focus, .form-select:focus {
            background: var(--bg-input);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            color: var(--text-primary);
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn:hover:not(:disabled) { transform: translateY(-1px); }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: #1e293b; }
        .btn-info { background: var(--info); color: white; }
        .btn-secondary { background: var(--bg-input); color: var(--text-primary); border: 1px solid var(--border); }
        .btn-sm { padding: 6px 10px; font-size: 0.8rem; }
        
        /* Tipo de boleto cards */
        .tipo-boleto-card {
            background: var(--bg-input);
            border-radius: var(--radius-md);
            padding: 16px;
            text-align: center;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        
        .tipo-boleto-card:hover {
            border-color: var(--primary);
        }
        
        .tipo-boleto-card .icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .tipo-boleto-card .nombre {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: #e2e8f0;
        }
        
        .tipo-boleto-card .input-precio {
            text-align: center;
            font-size: 1.2rem;
            font-weight: 700;
            background: var(--bg-card);
        }
        
        .event-selector {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-input));
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .event-selector select {
            max-width: 450px;
            margin: 0 auto;
            font-size: 1rem;
            font-weight: 600;
            padding: 12px 16px;
        }
        
        .table {
            color: var(--text-primary);
        }
        
        .table th {
            border-color: var(--border);
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .table td {
            border-color: var(--border);
            vertical-align: middle;
        }
        
        .color-dot {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: inline-block;
            border: 2px solid var(--border);
        }
        
        .back-btn {
            position: fixed;
            bottom: 16px;
            left: 16px;
            z-index: 100;
        }
        
        .badge-global {
            background: var(--success);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-especifico {
            background: var(--info);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="container-main">
    
    <div class="page-header">
        <h1><i class="bi bi-tags-fill"></i> Categorías y Precios</h1>
        <p>Define los precios por tipo de boleto y categorías de asiento</p>
    </div>
    
    <!-- Selector de Evento -->
    <div class="event-selector">
        <form method="GET" action="">
            <select name="id_evento" class="form-select" onchange="this.form.submit()">
                <option value="" <?= ($id_evento_seleccionado == null) ? 'selected' : '' ?>>
                    🌐 Precios Globales (todos los eventos)
                </option>
                <?php foreach ($eventos as $evento): ?>
                    <option value="<?= $evento['id_evento'] ?>" <?= ($id_evento_seleccionado == $evento['id_evento']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($evento['titulo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    
    <!-- Precios por Tipo de Boleto -->
    <div class="card">
        <div class="card-title">
            <i class="bi bi-people-fill"></i>
            <span>Precios por Tipo de Boleto</span>
            <?php if (!$id_evento_seleccionado): ?>
                <span class="badge-global ms-2">GLOBAL</span>
            <?php else: ?>
                <span class="badge-especifico ms-2"><?= htmlspecialchars($nombre_evento) ?></span>
            <?php endif; ?>
        </div>
        
        <form id="form-precios-tipo" method="POST" action="action_precios_tipo.php">
            <input type="hidden" name="id_evento" value="<?= $id_evento_seleccionado ?? '' ?>">
            
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="tipo-boleto-card">
                        <div class="icon">👤</div>
                        <div class="nombre">Adulto</div>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-0 text-white">$</span>
                            <input type="number" name="precio_adulto" class="form-control input-precio" 
                                   value="<?= $precios_tipo['adulto'] ?>" min="0" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="tipo-boleto-card">
                        <div class="icon">👶</div>
                        <div class="nombre">Niño</div>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-0 text-white">$</span>
                            <input type="number" name="precio_nino" class="form-control input-precio" 
                                   value="<?= $precios_tipo['nino'] ?>" min="0" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="tipo-boleto-card">
                        <div class="icon">👴</div>
                        <div class="nombre">3ra Edad</div>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-0 text-white">$</span>
                            <input type="number" name="precio_adulto_mayor" class="form-control input-precio" 
                                   value="<?= $precios_tipo['adulto_mayor'] ?>" min="0" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="tipo-boleto-card" style="opacity: 0.6;">
                        <div class="icon">🎁</div>
                        <div class="nombre">Cortesía</div>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-0 text-white">$</span>
                            <input type="number" class="form-control input-precio" value="0.00" disabled>
                        </div>
                        <small class="text-muted">Siempre gratis</small>
                    </div>
                </div>
            </div>
            
            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" name="accion" value="guardar" class="btn btn-success flex-grow-1">
                    <i class="bi bi-check-circle"></i> Guardar Precios
                </button>
                <?php if ($id_evento_seleccionado): ?>
                <button type="submit" name="accion" value="usar_global" class="btn btn-secondary">
                    <i class="bi bi-globe"></i> Usar Precios Globales
                </button>
                <?php else: ?>
                <button type="submit" name="accion" value="aplicar_todos" class="btn btn-primary">
                    <i class="bi bi-broadcast"></i> Aplicar a Todos los Eventos
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <?php if ($id_evento_seleccionado): ?>
    <!-- Categorías del Evento -->
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-title">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span id="form-title-text">Nueva Categoría</span>
                </div>
                
                <form id="formCRUD" action="action.php" method="POST">
                    <input type="hidden" name="id_categoria" id="id_categoria" value="">
                    <input type="hidden" name="id_evento" value="<?= $id_evento_seleccionado ?>">
                    <input type="hidden" name="accion" id="accion" value="crear">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre Categoría</label>
                        <input type="text" name="nombre_categoria" id="nombre_categoria" class="form-control" placeholder="Ej: General, VIP" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Precio Base (MXN)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-0 text-white">$</span>
                            <input type="number" name="precio" id="precio" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color" id="color" class="form-control form-control-color w-100" value="#6366f1">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" id="btn-submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-secondary d-none" id="btn-cancel" onclick="resetForm()">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="col-lg-8">
            <div class="card">
                <div class="card-title">
                    <i class="bi bi-list-ul"></i>
                    <span>Categorías de "<?= htmlspecialchars($nombre_evento) ?>"</span>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
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
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                                    No hay categorías definidas
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($categorias_del_evento as $cat): ?>
                            <tr>
                                <td><span class="color-dot" style="background-color: <?= htmlspecialchars($cat['color']) ?>"></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($cat['nombre_categoria']) ?></td>
                                <td class="text-success fw-bold">$<?= number_format($cat['precio'], 2) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-warning btn-sm" onclick='editCat(<?= json_encode($cat) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="borrar(<?= $cat['id_categoria'] ?>, '<?= addslashes($cat['nombre_categoria']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<a href="../../index.php" target="_top" class="btn btn-secondary back-btn">
    <i class="bi bi-arrow-left"></i> Menú
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editCat(cat) {
    document.getElementById('id_categoria').value = cat.id_categoria;
    document.getElementById('nombre_categoria').value = cat.nombre_categoria;
    document.getElementById('precio').value = cat.precio;
    document.getElementById('color').value = cat.color;
    document.getElementById('accion').value = 'editar';
    document.getElementById('form-title-text').textContent = 'Editar Categoría';
    document.getElementById('btn-cancel').classList.remove('d-none');
}

function resetForm() {
    document.getElementById('formCRUD').reset();
    document.getElementById('id_categoria').value = '';
    document.getElementById('accion').value = 'crear';
    document.getElementById('form-title-text').textContent = 'Nueva Categoría';
    document.getElementById('btn-cancel').classList.add('d-none');
}

function borrar(id, nombre) {
    Swal.fire({
        title: `¿Eliminar "${nombre}"?`,
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `action.php?accion=borrar&id_categoria=${id}&id_evento=<?= $id_evento_seleccionado ?>`;
        }
    });
}

// Mostrar mensajes de éxito/error
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
            timerProgressBar: true
        });
    } else if (status === 'error') {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: decodeURIComponent(msg) || 'Ocurrió un problema'
        });
    }
    
    if (status) {
        const newUrl = window.location.pathname + window.location.search.replace(/[?&]status=[^&]+|[?&]msg=[^&]+/g, '');
        window.history.replaceState({}, document.title, newUrl);
    }
});
</script>

</body>
</html>