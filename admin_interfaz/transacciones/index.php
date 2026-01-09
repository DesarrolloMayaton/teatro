<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['usuario_rol'] !== 'admin') {
    if (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']) {
        die('Acceso denegado');
    }
}

require_once '../../transacciones_helper.php';

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$transacciones = [];

$sql_base = "SELECT t.id_transaccion, t.accion, t.descripcion, t.fecha_hora, u.nombre, u.apellido
             FROM transacciones t
             JOIN usuarios u ON t.id_usuario = u.id_usuario";

if ($fecha_desde && $fecha_hasta) {
    $sql = $sql_base . " WHERE t.fecha_hora >= ? AND t.fecha_hora <= ? ORDER BY t.fecha_hora DESC LIMIT 500";
    $stmt = $conn->prepare($sql);
    $desde = $fecha_desde . ' 00:00:00';
    $hasta = $fecha_hasta . ' 23:59:59';
    $stmt->bind_param('ss', $desde, $hasta);
} elseif ($fecha_desde) {
    $sql = $sql_base . " WHERE t.fecha_hora >= ? ORDER BY t.fecha_hora DESC LIMIT 500";
    $stmt = $conn->prepare($sql);
    $desde = $fecha_desde . ' 00:00:00';
    $stmt->bind_param('s', $desde);
} elseif ($fecha_hasta) {
    $sql = $sql_base . " WHERE t.fecha_hora <= ? ORDER BY t.fecha_hora DESC LIMIT 500";
    $stmt = $conn->prepare($sql);
    $hasta = $fecha_hasta . ' 23:59:59';
    $stmt->bind_param('s', $hasta);
} else {
    $sql = $sql_base . " ORDER BY t.fecha_hora DESC LIMIT 200";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $transacciones[] = $row;
}

$stmt->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transacciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            --radius-lg: 16px;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container-fluid {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
            transition: all 0.2s ease;
        }
        
        .card:hover { box-shadow: var(--shadow-lg); }
        
        h2, h3 {
            color: var(--text-primary);
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .table {
            color: var(--text-primary);
            margin: 0;
        }
        
        .table thead { background: var(--bg-input); }
        
        .table th {
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border-color: var(--border);
        }
        
        .table td {
            border-color: var(--border);
        }
        
        .badge-accion {
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary);
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .descripcion {
            max-width: 320px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-secondary);
        }
        
        tbody tr {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        tbody tr:hover {
            background-color: rgba(99, 102, 241, 0.08);
        }
        
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-primary);
        }
        
        .modal-header, .modal-footer {
            border-color: var(--border);
        }
        
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .detalle-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .detalle-item:last-child { border-bottom: none; }
        
        .detalle-label {
            font-weight: 600;
            color: var(--text-secondary);
            min-width: 150px;
        }
        
        .detalle-valor {
            color: var(--text-primary);
            word-break: break-word;
            flex: 1;
            text-align: right;
        }
        
        .json-viewer {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
            color: var(--text-primary);
        }
        
        .badge-accion-modal {
            display: inline-block;
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary);
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .filtros-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .form-control {
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: 8px;
        }
        
        .form-control:focus {
            background: var(--bg-input);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            color: var(--text-primary);
        }
        
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-secondary {
            color: var(--text-secondary);
            border-color: var(--border);
        }
        
        .btn-outline-secondary:hover {
            background: var(--bg-input);
            color: var(--text-primary);
        }
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }
        
        .nueva-transaccion {
            animation: slideInDown 0.5s ease-out;
            background: rgba(99, 102, 241, 0.1) !important;
        }
        
        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-indicator.active {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }
        
        .status-indicator.paused {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }
        
        .status-indicator .pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .text-secondary { color: var(--text-secondary) !important; }
        .btn-close { filter: invert(1); }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
            <div>
                <h2 class="m-0 d-flex align-items-center"><i class="bi bi-clock-history me-3"></i>Transacciones de Usuarios</h2>
                <p class="text-secondary mb-0">Historial de acciones realizadas por los usuarios logeados.</p>
            </div>
            <div class="text-end">
                <div class="mb-2">
                    <span class="status-indicator active" id="statusIndicator">
                        <span class="pulse"></span>
                        <span id="statusText">Actualizando en tiempo real</span>
                    </span>
                </div>
                <span class="text-secondary small">Registros mostrados:</span>
                <div class="fs-4 fw-bold" id="countTransacciones"><?php echo count($transacciones); ?></div>
            </div>
        </div>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="fecha_desde" class="filtros-label">Desde</label>
                <input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde); ?>">
            </div>
            <div class="col-md-4">
                <label for="fecha_hasta" class="filtros-label">Hasta</label>
                <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="button" onclick="aplicarFiltros()" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                <button type="button" onclick="toggleAutoUpdate()" class="btn btn-outline-primary" id="btnToggleUpdate" title="Pausar/Reanudar actualización automática">
                    <i class="bi bi-pause-fill" id="iconToggleUpdate"></i>
                </button>
            </div>
        </form>
    </div>

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody id="transaccionesBody">
                <?php if (empty($transacciones)): ?>
                    <tr id="emptyRow">
                        <td colspan="4" class="text-center text-secondary py-4">No hay transacciones para el criterio seleccionado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transacciones as $t): ?>
                        <tr data-id="<?php echo $t['id_transaccion']; ?>">
                            <td class="text-nowrap"><?php echo date('d/m/Y H:i:s', strtotime($t['fecha_hora'])); ?></td>
                            <td><?php echo htmlspecialchars($t['nombre'] . ' ' . $t['apellido']); ?></td>
                            <td><span class="badge-accion"><?php echo htmlspecialchars($t['accion']); ?></span></td>
                            <td class="descripcion" title="<?php echo htmlspecialchars($t['descripcion'] ?? ''); ?>"><?php echo htmlspecialchars($t['descripcion'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Detalles de Transacción -->
<div class="modal fade" id="modalDetalleTransaccion" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="modalDetalleLabel">
                    <i class="bi bi-info-circle me-2"></i>Detalles de la Transacción
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="detalleContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Estado de la aplicación
let autoUpdateEnabled = true;
let updateInterval = null;
let ultimaIdTransaccion = 0;
let filtroDesde = '<?php echo htmlspecialchars($fecha_desde); ?>';
let filtroHasta = '<?php echo htmlspecialchars($fecha_hasta); ?>';

// Inicializar última ID
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('#transaccionesBody tr[data-id]');
    if (rows.length > 0) {
        const ids = Array.from(rows).map(row => parseInt(row.dataset.id));
        ultimaIdTransaccion = Math.max(...ids);
        
        // Agregar eventos de clic a las filas existentes
        rows.forEach(row => {
            row.addEventListener('click', () => {
                abrirDetalleTransaccion(row.dataset.id);
            });
        });
    }
    
    // Iniciar actualización automática
    iniciarAutoUpdate();
});

// Función para formatear fecha
function formatearFecha(fechaStr) {
    const fecha = new Date(fechaStr);
    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const anio = fecha.getFullYear();
    const horas = String(fecha.getHours()).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');
    const segundos = String(fecha.getSeconds()).padStart(2, '0');
    return `${dia}/${mes}/${anio} ${horas}:${minutos}:${segundos}`;
}

// Función para crear una fila de transacción
function crearFilaTransaccion(t) {
    const tr = document.createElement('tr');
    tr.dataset.id = t.id_transaccion;
    tr.className = 'nueva-transaccion';
    
    tr.innerHTML = `
        <td class="text-nowrap">${formatearFecha(t.fecha_hora)}</td>
        <td>${escapeHtml(t.nombre + ' ' + t.apellido)}</td>
        <td><span class="badge-accion">${escapeHtml(t.accion)}</span></td>
        <td class="descripcion" title="${escapeHtml(t.descripcion || '')}">${escapeHtml(t.descripcion || '')}</td>
    `;
    
    // Agregar evento de clic para abrir modal
    tr.addEventListener('click', () => {
        abrirDetalleTransaccion(t.id_transaccion);
    });
    
    // Remover la clase de animación después de que termine
    setTimeout(() => {
        tr.classList.remove('nueva-transaccion');
    }, 500);
    
    return tr;
}

// Función para escapar HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Función para cargar nuevas transacciones
async function cargarNuevasTransacciones() {
    if (!autoUpdateEnabled) return;
    
    try {
        const params = new URLSearchParams({
            ultima_id: ultimaIdTransaccion
        });
        
        if (filtroDesde) params.append('fecha_desde', filtroDesde);
        if (filtroHasta) params.append('fecha_hasta', filtroHasta);
        
        const response = await fetch(`api_transacciones.php?${params.toString()}`);
        const data = await response.json();
        
        if (data.success && data.transacciones.length > 0) {
            const tbody = document.getElementById('transaccionesBody');
            const emptyRow = document.getElementById('emptyRow');
            
            // Remover fila vacía si existe
            if (emptyRow) {
                emptyRow.remove();
            }
            
            // Agregar nuevas transacciones al inicio
            data.transacciones.reverse().forEach(t => {
                const nuevaFila = crearFilaTransaccion(t);
                tbody.insertBefore(nuevaFila, tbody.firstChild);
                
                // Actualizar última ID
                if (t.id_transaccion > ultimaIdTransaccion) {
                    ultimaIdTransaccion = t.id_transaccion;
                }
            });
            
            // Actualizar contador
            const totalRows = tbody.querySelectorAll('tr[data-id]').length;
            document.getElementById('countTransacciones').textContent = totalRows;
            
            // Limitar a 500 filas
            const allRows = tbody.querySelectorAll('tr[data-id]');
            if (allRows.length > 500) {
                for (let i = 500; i < allRows.length; i++) {
                    allRows[i].remove();
                }
            }
        }
    } catch (error) {
        console.error('Error al cargar transacciones:', error);
    }
}

// Función para recargar todas las transacciones
async function recargarTransacciones() {
    try {
        const params = new URLSearchParams();
        if (filtroDesde) params.append('fecha_desde', filtroDesde);
        if (filtroHasta) params.append('fecha_hasta', filtroHasta);
        
        const response = await fetch(`api_transacciones.php?${params.toString()}`);
        const data = await response.json();
        
        if (data.success) {
            const tbody = document.getElementById('transaccionesBody');
            tbody.innerHTML = '';
            
            if (data.transacciones.length === 0) {
                tbody.innerHTML = '<tr id="emptyRow"><td colspan="4" class="text-center text-secondary py-4">No hay transacciones para el criterio seleccionado.</td></tr>';
                ultimaIdTransaccion = 0;
            } else {
                data.transacciones.forEach(t => {
                    tbody.appendChild(crearFilaTransaccion(t));
                    if (t.id_transaccion > ultimaIdTransaccion) {
                        ultimaIdTransaccion = t.id_transaccion;
                    }
                });
            }
            
            document.getElementById('countTransacciones').textContent = data.count;
        }
    } catch (error) {
        console.error('Error al recargar transacciones:', error);
    }
}

// Función para aplicar filtros
function aplicarFiltros() {
    filtroDesde = document.getElementById('fecha_desde').value;
    filtroHasta = document.getElementById('fecha_hasta').value;
    ultimaIdTransaccion = 0;
    recargarTransacciones();
}

// Función para iniciar actualización automática
function iniciarAutoUpdate() {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
    updateInterval = setInterval(cargarNuevasTransacciones, 3000); // Cada 3 segundos
}

// Función para pausar/reanudar actualización
function toggleAutoUpdate() {
    autoUpdateEnabled = !autoUpdateEnabled;
    
    const statusIndicator = document.getElementById('statusIndicator');
    const statusText = document.getElementById('statusText');
    const btnIcon = document.getElementById('iconToggleUpdate');
    
    if (autoUpdateEnabled) {
        statusIndicator.classList.remove('paused');
        statusIndicator.classList.add('active');
        statusText.textContent = 'Actualizando en tiempo real';
        btnIcon.className = 'bi bi-pause-fill';
        iniciarAutoUpdate();
    } else {
        statusIndicator.classList.remove('active');
        statusIndicator.classList.add('paused');
        statusText.textContent = 'Actualización pausada';
        btnIcon.className = 'bi bi-play-fill';
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    }
}

// Limpiar intervalo al cerrar la página
window.addEventListener('beforeunload', () => {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
});

// Función para abrir el modal de detalles
async function abrirDetalleTransaccion(idTransaccion) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalleTransaccion'));
    const detalleContent = document.getElementById('detalleContent');
    
    // Mostrar spinner
    detalleContent.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    try {
        const response = await fetch(`api_detalle_transaccion.php?id=${idTransaccion}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        let data;
        
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Respuesta no es JSON válido:', text);
            throw new Error('La respuesta del servidor no es JSON válido');
        }
        
        if (data.success) {
            const t = data.transaccion;
            const datosAdicionales = data.datos_adicionales;
            
            let html = `
                <div class="detalle-item">
                    <span class="detalle-label">ID Transacción:</span>
                    <span class="detalle-valor">#${t.id_transaccion}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Fecha y Hora:</span>
                    <span class="detalle-valor">${formatearFecha(t.fecha_hora)}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Usuario:</span>
                    <span class="detalle-valor">${escapeHtml(t.nombre + ' ' + t.apellido)}</span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Acción:</span>
                    <span class="detalle-valor"><span class="badge-accion-modal">${escapeHtml(t.accion)}</span></span>
                </div>
                <div class="detalle-item">
                    <span class="detalle-label">Descripción:</span>
                    <span class="detalle-valor">${escapeHtml(t.descripcion || 'N/A')}</span>
                </div>
            `;
            
            // Si es evento_crear, mostrar detalles del evento
            if (t.accion === 'evento_crear' && data.evento_detalles) {
                const evt = data.evento_detalles;
                html += `
                    <hr class="my-3">
                    <div style="background: rgba(37, 99, 235, 0.05); padding: 12px; border-radius: 8px; margin-bottom: 12px;">
                        <h6 class="fw-bold mb-3" style="color: var(--primary-color);">
                            <i class="bi bi-calendar-event me-2"></i>Detalles del Evento Creado
                        </h6>
                        <div class="detalle-item">
                            <span class="detalle-label">ID Evento:</span>
                            <span class="detalle-valor">#${evt.id_evento}</span>
                        </div>
                        <div class="detalle-item">
                            <span class="detalle-label">Título:</span>
                            <span class="detalle-valor">${escapeHtml(evt.titulo)}</span>
                        </div>
                        <div class="detalle-item">
                            <span class="detalle-label">Descripción:</span>
                            <span class="detalle-valor">${escapeHtml(evt.descripcion || 'N/A')}</span>
                        </div>
                        <div class="detalle-item">
                            <span class="detalle-label">Tipo:</span>
                            <span class="detalle-valor">${evt.tipo == 1 ? '🎭 Teatro 420' : evt.tipo == 2 ? '🚶 Pasarela 540' : 'Otro'}</span>
                        </div>
                        <div class="detalle-item">
                            <span class="detalle-label">Inicio Venta:</span>
                            <span class="detalle-valor">${formatearFecha(evt.inicio_venta)}</span>
                        </div>
                        <div class="detalle-item">
                            <span class="detalle-label">Cierre Venta:</span>
                            <span class="detalle-valor">${formatearFecha(evt.cierre_venta)}</span>
                        </div>
                        <div class="detalle-item">
                            <span class="detalle-label">Estado:</span>
                            <span class="detalle-valor">${evt.finalizado ? '<span class="badge bg-danger">Finalizado</span>' : '<span class="badge bg-success">Activo</span>'}</span>
                        </div>
                    </div>
                `;
            }
            
            detalleContent.innerHTML = html;
        } else {
            detalleContent.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Error: ${data.error || 'No se pudo cargar la transacción'}
                </div>
            `;
        }
    } catch (error) {
        console.error('Error al cargar detalles:', error);
        detalleContent.innerHTML = `
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Error:</strong> ${escapeHtml(error.message || 'Error desconocido al cargar los detalles')}
            </div>
        `;
    }
}
</script>
</body>
</html>
