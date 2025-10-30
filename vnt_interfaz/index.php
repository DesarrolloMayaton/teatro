<?php
// 1. CONEXIÓN
require_once "conexion.php";

// 2. INICIALIZAR VARIABLES
$id_evento_actual = null;
$evento = null;
$mapa_asientos = [];
$eventos_activos = [];
$categorias_palette = [];
$mapa_guardado = [];
$colores_por_codigo = [];
$categorias_js = [0 => ['nombre' => 'Sin Categoría', 'precio' => 0.00]];

// 3. VERIFICAR EL MODO DE LA PÁGINA
if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    
    // ===================================
    // === MODO: MOSTRAR ASIENTOS ========
    // ===================================
    $id_evento_actual = (int)$_GET['id_evento'];

    // --- 3A. OBTENER INFORMACIÓN DEL EVENTO ---
    $stmt_evt = $conn->prepare("SELECT titulo, tipo, mapa_json FROM evento WHERE id_evento = ? AND finalizado = 0");
    $stmt_evt->bind_param("i", $id_evento_actual);
    $stmt_evt->execute();
    $res_evt = $stmt_evt->get_result();
    $evento = $res_evt->fetch_assoc();
    $stmt_evt->close();

    if (!$evento) {
        die("Error: El evento no existe o ya no está activo. <a href='vnt_interfaz/index.php'>Volver al menú</a>");
    }

    // --- 3B. CARGAR CATEGORÍAS Y COLORES ---
    $stmt_cat = $conn->prepare("SELECT * FROM categorias WHERE id_evento = ? ORDER BY precio ASC");
    $stmt_cat->bind_param("i", $id_evento_actual);
    $stmt_cat->execute();
    $res_categorias = $stmt_cat->get_result();
    if ($res_categorias) {
        $categorias_palette = $res_categorias->fetch_all(MYSQLI_ASSOC);
        foreach ($categorias_palette as $c) {
            $categorias_js[$c['id_categoria']] = [
                'nombre' => $c['nombre_categoria'],
                'precio' => $c['precio']
            ];
        }
    }
    $stmt_cat->close();

    // --- 3C. CARGAR MAPA JSON ---
    if (!empty($evento['mapa_json'])) {
        $mapa_guardado = json_decode($evento['mapa_json'], true);
    }

    // --- 3D. OBTENER PLANTILLA DE ASIENTOS ---
    $sql_plantilla_base = "SELECT id_asiento, fila, numero, codigo_asiento FROM asientos";
    $filtro_layout = "";

    if ($evento['tipo'] == 1) {
        $filtro_layout = " WHERE fila NOT LIKE 'PB%'";
    }
    
    $sql_plantilla_final = $sql_plantilla_base . $filtro_layout . " ORDER BY fila, numero";
    $res_plantilla = $conn->query($sql_plantilla_final);
    
    // Obtener asientos vendidos
    $stmt_vendidos = $conn->prepare("SELECT id_asiento FROM boletos WHERE id_evento = ?");
    $stmt_vendidos->bind_param("i", $id_evento_actual);
    $stmt_vendidos->execute();
    $res_vendidos = $stmt_vendidos->get_result();
    $asientos_vendidos = [];
    while ($row = $res_vendidos->fetch_assoc()) {
        $asientos_vendidos[] = $row['id_asiento'];
    }
    $stmt_vendidos->close();
    
    while ($asiento = $res_plantilla->fetch_assoc()) {
        $codigo = $asiento['codigo_asiento'];
        $id_cat = $mapa_guardado[$codigo] ?? 0;
        
        // Obtener color y precio de la categoría
        $color_asiento = '#BDBDBD';
        $precio_asiento = 0.00;
        foreach ($categorias_palette as $cat) {
            if ($cat['id_categoria'] == $id_cat) {
                $color_asiento = $cat['color'];
                $precio_asiento = $cat['precio'];
                break;
            }
        }
        
        $asiento['status'] = in_array($asiento['id_asiento'], $asientos_vendidos) ? 'vendido' : 'disponible';
        $asiento['color'] = $color_asiento;
        $asiento['id_categoria'] = $id_cat;
        $asiento['precio'] = $precio_asiento;
        
        $fila = $asiento['fila'];
        if (!isset($mapa_asientos[$fila])) {
            $mapa_asientos[$fila] = [];
        }
        $mapa_asientos[$fila][] = $asiento;
    }

} else {
    
    // ===================================
    // === MODO: MOSTRAR MENÚ DE EVENTOS ===
    // ===================================
    $query_menu = "
        SELECT e.*, 
               (SELECT MIN(f.fecha_hora) 
                FROM funciones f 
                WHERE f.id_evento = e.id_evento AND f.fecha_hora >= NOW()) AS proxima_funcion_fecha
        FROM evento e
        WHERE e.finalizado = 0 
        HAVING proxima_funcion_fecha IS NOT NULL
        ORDER BY proxima_funcion_fecha ASC;
    ";
    $res_menu = $conn->query($query_menu);
    if ($res_menu) {
        $eventos_activos = $res_menu->fetch_all(MYSQLI_ASSOC);
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $id_evento_actual ? 'Simular Compra - ' . htmlspecialchars($evento['titulo']) : 'Cartelera de Eventos'; ?></title>
    <base href="/teatro/">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        html, body { height: 100vh; overflow: hidden; }
        body { background-color: #f4f7f6; font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 0; }
        
        /* Estilos del Menú */
        .hero { background-color: #fff; border-bottom: 1px solid #dee2e6; padding: 3rem 0; margin-bottom: 2rem; }
        .card-link { text-decoration: none; color: inherit; }
        .card { transition: transform 0.2s ease, box-shadow 0.2s ease; height: 100%; border-radius: 14px; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12); }
        .card-img-top { width: 100%; height: 350px; object-fit: cover; }
        .card-body { display: flex; flex-direction: column; justify-content: space-between; }
        
        /* Estilos de Asientos */
        .sidebar { background-color: #2c3e50; min-height: 100vh; padding: 20px; }
        .content { overflow-y: auto; height: 100vh; padding: 30px; }
        .seat-map-container { max-width: 1600px; margin: 20px auto; background: #fff; border-radius: 14px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); overflow-x: auto; }
        .screen { background-color: #333; color: white; padding: 10px; text-align: center; font-size: 1.5em; margin-bottom: 25px; border-radius: 5px; }
        
        .seat-row { display: flex; justify-content: center; align-items: center; margin-bottom: 12px; }
        .row-label { width: 50px; text-align: center; font-weight: 600; font-size: 1.25em; border-radius: 8px; padding: 5px 0; cursor: default; }
        .seats-block { display: flex; align-items: center; gap: 10px; }
        .pasillo { width: 40px; }
        .pasarela { width: 100px; height: 60px; background: #333; color: #fff; display: flex; align-items: center; justify-content: center; border-radius: 6px; flex-shrink: 0; }
        .pasarela-text { writing-mode: vertical-rl; text-orientation: mixed; font-weight: 700; letter-spacing: 4px; font-size: 1.1em; }
        
        .seat { width: 50px; height: 50px; background: #BDBDBD; color: #212121; border-radius: 8px; font-size: 16px; font-weight: 700; display: flex; align-items: center; justify-content: center; border: 2px solid #9E9E9E; cursor: pointer; transition: transform .15s ease, filter .15s ease; padding: 2px; box-sizing: border-box; text-align: center; line-height: 1; }
        .seat:hover { transform: scale(1.1); filter: brightness(0.9); }
        .seat.vendido { opacity: 0.5; cursor: not-allowed; filter: grayscale(100%); }
        .seat.seleccionado { border: 3px solid #007bff; box-shadow: 0 0 10px rgba(0,123,255,0.5); }
        
        .legend { display: flex; justify-content: center; gap: 20px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .palette-color { width: 20px; height: 20px; border-radius: 50%; display: inline-block; }
        
        .checkout-summary { max-width: 1600px; margin: 30px auto; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        .boleto-info { background: #fff; border: 2px solid #28a745; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .boleto-info.usado { border-color: #dc3545; }
        .boleto-detalle { display: flex; justify-content: space-between; margin: 8px 0; }
        .boleto-detalle strong { color: #555; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <?php if ($id_evento_actual !== null): ?>
            
            <div class="col-md-2 sidebar">
                 <h4 class="text-white">Mi Teatro</h4>
                 <hr class="text-white">
                 <a href="vnt_interfaz/index.php" class="nav-link text-white-50"><i class="bi bi-arrow-left"></i> Volver a Eventos</a>
            </div>

            <div class="col-md-10 content">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2>Compra de Boletos: <span class="text-success"><?php echo htmlspecialchars($evento['titulo']); ?></span></h2>
                        <h5 class="text-muted">
                            Diseño: <?php echo ($evento['tipo'] == 1) ? "Teatro (420 asientos)" : "Teatro + Pasarela (540 asientos)"; ?>
                        </h5>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEscanear">
                        <i class="bi bi-qr-code-scan"></i> Escanear Boleto
                    </button>
                </div>
                <hr>

                <div class="seat-map-container">
                    <div class="screen"><?= ($evento['tipo']==1)?'ESCENARIO':'PASARELA / ESCENARIO' ?></div>
                    
                    <div class="seat-map">
                        <?php 
                        // ========== PASARELA 540 (PB + Teatro) ==========
                        if ($evento['tipo'] == 2): 
                            // Filas PB (Pasarela)
                            for ($fila_num=1; $fila_num<=10; $fila_num++):
                                $nombre_fila = "PB".$fila_num;
                                if (!isset($mapa_asientos[$nombre_fila])) continue;
                                $asientos_fila = $mapa_asientos[$nombre_fila];
                                $mitad = ceil(count($asientos_fila) / 2);
                        ?>
                            <div class="seat-row">
                                <div class="row-label"><?= $nombre_fila ?></div>
                                <div class="seats-block">
                                    <?php for ($i=0; $i<$mitad; $i++): 
                                        $asiento = $asientos_fila[$i];
                                    ?>
                                        <div class="seat <?= $asiento['status'] ?>" 
                                             style="background-color: <?= $asiento['color'] ?>"
                                             data-id-asiento="<?= $asiento['id_asiento'] ?>"
                                             data-codigo-asiento="<?= $asiento['codigo_asiento'] ?>"
                                             data-categoria-id="<?= $asiento['id_categoria'] ?>"
                                             data-precio="<?= $asiento['precio'] ?>">
                                            <?= $asiento['codigo_asiento'] ?>
                                        </div>
                                    <?php endfor; ?>
                                    <div class="pasarela">
                                        <?php if ($fila_num==5) echo '<span class="pasarela-text">PASARELA</span>'; ?>
                                    </div>
                                    <?php for ($i=$mitad; $i<count($asientos_fila); $i++): 
                                        $asiento = $asientos_fila[$i];
                                    ?>
                                        <div class="seat <?= $asiento['status'] ?>" 
                                             style="background-color: <?= $asiento['color'] ?>"
                                             data-id-asiento="<?= $asiento['id_asiento'] ?>"
                                             data-codigo-asiento="<?= $asiento['codigo_asiento'] ?>"
                                             data-categoria-id="<?= $asiento['id_categoria'] ?>"
                                             data-precio="<?= $asiento['precio'] ?>">
                                            <?= $asiento['codigo_asiento'] ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <div class="row-label"><?= $nombre_fila ?></div>
                            </div>
                        <?php 
                            endfor; 
                        ?>
                            <hr style="margin-top: 30px; margin-bottom: 30px; border-width: 2px;">
                        <?php
                        endif;
                        
                        // ========== TEATRO (Filas A-O y P) ==========
                        $letras = range('A','O');
                        foreach ($letras as $fila):
                            if (!isset($mapa_asientos[$fila])) continue;
                            $asientos_fila = $mapa_asientos[$fila];
                        ?>
                            <div class="seat-row">
                                <div class="row-label"><?= $fila ?></div>
                                <div class="seats-block">
                                    <?php 
                                    $contador = 0;
                                    foreach ($asientos_fila as $asiento): 
                                        if ($contador == 6) echo '<div class="pasillo"></div>';
                                        if ($contador == 20) echo '<div class="pasillo"></div>';
                                    ?>
                                        <div class="seat <?= $asiento['status'] ?>" 
                                             style="background-color: <?= $asiento['color'] ?>"
                                             data-id-asiento="<?= $asiento['id_asiento'] ?>"
                                             data-codigo-asiento="<?= $asiento['codigo_asiento'] ?>"
                                             data-categoria-id="<?= $asiento['id_categoria'] ?>"
                                             data-precio="<?= $asiento['precio'] ?>">
                                            <?= $asiento['codigo_asiento'] ?>
                                        </div>
                                    <?php 
                                        $contador++;
                                    endforeach; 
                                    ?>
                                </div>
                                <div class="row-label"><?= $fila ?></div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (isset($mapa_asientos['P'])): ?>
                            <div class="seat-row">
                                <div class="row-label">P</div>
                                <div class="seats-block">
                                    <?php foreach ($mapa_asientos['P'] as $asiento): ?>
                                        <div class="seat <?= $asiento['status'] ?>" 
                                             style="background-color: <?= $asiento['color'] ?>"
                                             data-id-asiento="<?= $asiento['id_asiento'] ?>"
                                             data-codigo-asiento="<?= $asiento['codigo_asiento'] ?>"
                                             data-categoria-id="<?= $asiento['id_categoria'] ?>"
                                             data-precio="<?= $asiento['precio'] ?>">
                                            <?= $asiento['codigo_asiento'] ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="row-label">P</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="legend">
                        <?php foreach ($categorias_palette as $c): ?>
                            <div class="legend-item">
                                <span class="palette-color" style="background-color:<?= $c['color'] ?>"></span>
                                <span><?= htmlspecialchars($c['nombre_categoria']) ?> ($<?= number_format($c['precio'],2) ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="checkout-summary" id="form-comprar">
                    <h4>Resumen de Compra</h4>
                    <p>Has seleccionado <strong id="conteo-asientos">0</strong> asientos.
                       Total: <strong id="total-precio">$0.00</strong></p>
                    <ul id="lista-asientos-seleccionados" class="list-group mb-3"></ul>
                    
                    <button type="button" class="btn btn-success btn-lg" id="btn-comprar">
                        <i class="bi bi-cart-check"></i> Comprar Boletos
                    </button>
                </div>
                
                <!-- Modal para mostrar boletos comprados -->
                <div class="modal fade" id="modalBoletos" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title"><i class="bi bi-check-circle"></i> ¡Compra Exitosa!</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" id="contenido-boletos"></div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal para escanear boleto -->
                <div class="modal fade" id="modalEscanear" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title"><i class="bi bi-qr-code-scan"></i> Escanear Boleto</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="input-codigo" class="form-label">Ingresa el código del boleto:</label>
                                    <input type="text" class="form-control" id="input-codigo" placeholder="TRT-XXXXXX-XXXXXX">
                                </div>
                                <button type="button" class="btn btn-primary w-100" id="btn-verificar">
                                    <i class="bi bi-search"></i> Verificar Boleto
                                </button>
                                <div id="resultado-verificacion" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal de información del asiento -->
                <div class="modal fade" id="modalAsientoInfo" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Detalles del Asiento</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <h3 id="info_asiento_nombre" class="text-center"></h3>
                                <p id="info_asiento_categoria" class="fs-5 text-center"></p>
                                <p id="info_asiento_precio" class="fs-4 fw-bold text-center text-success"></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                <button type="button" class="btn btn-primary" id="btn-seleccionar-asiento">Seleccionar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            
            <div class="col-12 p-0">
                <div class="hero text-center">
                    <div class="container">
                        <h1 class="display-4">Cartelera (Simulación)</h1>
                        <p class="lead">Selecciona un evento para simular la compra</p>
                    </div>
                </div>

                <div class="container">
                    <div class="row">
                        <?php if (!empty($eventos_activos)): ?>
                            <?php foreach ($eventos_activos as $evt_menu): ?>
                                <?php
                                $fechaFormateada = date('D, M d, Y - h:i A', strtotime($evt_menu['proxima_funcion_fecha']));
                                $rutaImagen = $evt_menu['imagen'] ? $evt_menu['imagen'] : 'evt_interfaz/imagen/placeholder.png';
                                $enlaceVenta = "vnt_interfaz/index.php?id_evento=" . $evt_menu['id_evento'];
                                $tipo_layout = ($evt_menu['tipo'] == 1) ? "Teatro (420)" : "Pasarela (540)";
                                ?>

                                <div class="col-lg-4 col-md-6 mb-4">
                                    <a href="<?php echo $enlaceVenta; ?>" class="card-link">
                                        <div class="card shadow-sm overflow-hidden">
                                            <img src="<?php echo $rutaImagen; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($evt_menu['titulo']); ?>">
                                            <div class="card-body">
                                                <div>
                                                    <h5 class="card-title"><?php echo htmlspecialchars($evt_menu['titulo']); ?></h5>
                                                    <span class="badge bg-secondary mb-2"><?php echo $tipo_layout; ?></span>
                                                    <p class="card-text text-danger fw-bold">
                                                        <i class="bi bi-calendar-event"></i> Próxima función:
                                                        <br>
                                                        <small class="text-dark fw-normal"><?php echo $fechaFormateada; ?></small>
                                                    </p>
                                                </div>
                                                <div class="mt-3 text-center">
                                                    <span class="btn btn-success w-100">
                                                        <i class="bi bi-ticket-perforated"></i> Seleccionar Evento
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="card text-center shadow-sm">
                                    <div class="card-body" style="padding: 50px;">
                                        <h4 class="card-title text-muted">No hay eventos</h4>
                                        <p class="card-text text-secondary">No hay eventos activos en la base de datos.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
        
    </div>
</div>

<?php if ($id_evento_actual !== null): ?>
<script>
const CATEGORIAS_INFO = <?= json_encode($categorias_js, JSON_NUMERIC_CHECK) ?>;
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapa = document.querySelector('.seat-map');
    const conteoEl = document.getElementById('conteo-asientos');
    const totalEl = document.getElementById('total-precio');
    const listaAsientosEl = document.getElementById('lista-asientos-seleccionados');
    let asientosSeleccionados = new Set();
    let asientosData = new Map();

    // Modal de información
    const infoModalElement = document.getElementById('modalAsientoInfo');
    const infoModal = new bootstrap.Modal(infoModalElement);
    const infoNombre = document.getElementById('info_asiento_nombre');
    const infoCategoria = document.getElementById('info_asiento_categoria');
    const infoPrecio = document.getElementById('info_asiento_precio');
    const btnSeleccionarAsiento = document.getElementById('btn-seleccionar-asiento');
    let asientoActualModal = null;

    mapa.addEventListener('click', function(e) {
        if (!e.target.classList.contains('seat')) return;
        
        asientoActualModal = e.target;
        mostrarInfoAsiento(e.target);
    });
    
    function mostrarInfoAsiento(seatElement) {
        const asientoId = seatElement.dataset.codigoAsiento;
        const catId = seatElement.dataset.categoriaId;
        const precio = parseFloat(seatElement.dataset.precio);
        
        const categoriaInfo = CATEGORIAS_INFO[catId] || CATEGORIAS_INFO[0];
        const precioFormateado = precio.toLocaleString('es-MX', {
            style: 'currency',
            currency: 'MXN'
        });
        
        infoNombre.textContent = `Asiento: ${asientoId}`;
        infoCategoria.textContent = `Categoría: ${categoriaInfo.nombre}`;
        infoPrecio.textContent = precioFormateado;
        
        if (seatElement.classList.contains('vendido')) {
            btnSeleccionarAsiento.style.display = 'none';
        } else {
            btnSeleccionarAsiento.style.display = 'block';
            const isSelected = seatElement.classList.contains('seleccionado');
            btnSeleccionarAsiento.textContent = isSelected ? 'Deseleccionar' : 'Seleccionar';
            btnSeleccionarAsiento.className = isSelected ? 'btn btn-warning' : 'btn btn-primary';
        }
        
        infoModal.show();
    }
    
    btnSeleccionarAsiento.addEventListener('click', function() {
        if (!asientoActualModal) return;
        
        const seatId = asientoActualModal.dataset.idAsiento;
        const precio = parseFloat(asientoActualModal.dataset.precio);
        
        if (asientosSeleccionados.has(seatId)) {
            asientosSeleccionados.delete(seatId);
            asientosData.delete(seatId);
            asientoActualModal.classList.remove('seleccionado');
        } else {
            asientosSeleccionados.add(seatId);
            asientosData.set(seatId, {
                codigo: asientoActualModal.dataset.codigoAsiento,
                precio: precio
            });
            asientoActualModal.classList.add('seleccionado');
        }
        
        actualizarResumen();
        infoModal.hide();
    });
    
    function actualizarResumen() {
        const conteo = asientosSeleccionados.size;
        let total = 0;
        
        listaAsientosEl.innerHTML = '';
        
        asientosSeleccionados.forEach(id => {
            const data = asientosData.get(id);
            total += data.precio;
            
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.innerHTML = `Asiento: <strong>${data.codigo}</strong> <span>$${data.precio.toFixed(2)}</span>`;
            listaAsientosEl.appendChild(li);
        });
        
        conteoEl.innerText = conteo;
        totalEl.innerText = `$${total.toFixed(2)}`;
        
        const btnComprar = document.getElementById('btn-comprar');
        btnComprar.disabled = (conteo === 0);
    }
    
    // --- LÓGICA DEL BOTÓN DE COMPRA ---
    document.getElementById('btn-comprar').addEventListener('click', async function(e) {
        if (asientosSeleccionados.size === 0) {
            alert("Por favor, selecciona al menos un asiento.");
            return;
        }
        
        const btnComprar = e.target;
        btnComprar.disabled = true;
        btnComprar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';
        
        // Preparar datos con precios individuales
        const asientosArray = Array.from(asientosSeleccionados).map(id => ({
            id_asiento: id,
            precio: asientosData.get(id).precio
        }));
        
        try {
            const response = await fetch('vnt_interfaz/procesar_compra.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_evento: <?php echo $id_evento_actual; ?>,
                    asientos: asientosArray
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                mostrarBoletosComprados(data.boletos);
            } else {
                alert('Error: ' + data.message);
                btnComprar.disabled = false;
                btnComprar.innerHTML = '<i class="bi bi-cart-check"></i> Comprar Boletos';
            }
        } catch (error) {
            alert('Error al procesar la compra: ' + error.message);
            btnComprar.disabled = false;
            btnComprar.innerHTML = '<i class="bi bi-cart-check"></i> Comprar Boletos';
        }
    });
    
    function mostrarBoletosComprados(boletos) {
        let html = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Se han generado ' + boletos.length + ' boleto(s) exitosamente.</div>';
        
        boletos.forEach(boleto => {
            html += `
                <div class="boleto-info">
                    <h6>Boleto #${boleto.id_boleto}</h6>
                    <p class="mb-2"><strong>Código:</strong> ${boleto.codigo_unico}</p>
                    <a href="vnt_interfaz/generar_pdf.php?id_boleto=${boleto.id_boleto}" class="btn btn-sm btn-primary" target="_blank">
                        <i class="bi bi-file-pdf"></i> Descargar PDF
                    </a>
                </div>
            `;
        });
        
        html += '<button type="button" class="btn btn-success w-100 mt-3" onclick="location.reload()">Realizar otra compra</button>';
        
        document.getElementById('contenido-boletos').innerHTML = html;
        const modal = new bootstrap.Modal(document.getElementById('modalBoletos'));
        modal.show();
    }
    
    // --- LÓGICA DE ESCANEO DE BOLETOS ---
    document.getElementById('btn-verificar').addEventListener('click', async function() {
        const codigo = document.getElementById('input-codigo').value.trim();
        
        if (!codigo) {
            alert('Por favor ingresa un código de boleto');
            return;
        }
        
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verificando...';
        
        try {
            const response = await fetch('vnt_interfaz/verificar_boleto.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ codigo_unico: codigo })
            });
            
            const data = await response.json();
            
            if (data.success) {
                mostrarInfoBoleto(data.boleto);
            } else {
                document.getElementById('resultado-verificacion').innerHTML = 
                    '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ' + data.message + '</div>';
            }
        } catch (error) {
            document.getElementById('resultado-verificacion').innerHTML = 
                '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Error al verificar: ' + error.message + '</div>';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search"></i> Verificar Boleto';
        }
    });
    
    function mostrarInfoBoleto(boleto) {
        const estatus = boleto.estatus == 1 ? 'Válido' : 'Ya Usado';
        const claseEstatus = boleto.estatus == 1 ? 'success' : 'danger';
        const claseBoleto = boleto.estatus == 1 ? '' : 'usado';
        
        let html = `
            <div class="boleto-info ${claseBoleto}">
                <h5 class="text-${claseEstatus}"><i class="bi bi-ticket-perforated"></i> ${estatus}</h5>
                <hr>
                <div class="boleto-detalle"><strong>Evento:</strong> <span>${boleto.evento_titulo}</span></div>
                <div class="boleto-detalle"><strong>Asiento:</strong> <span>${boleto.codigo_asiento} (Fila ${boleto.fila}, #${boleto.numero})</span></div>
                <div class="boleto-detalle"><strong>Precio:</strong> <span>${parseFloat(boleto.precio_final).toFixed(2)}</span></div>
                <div class="boleto-detalle"><strong>Fecha Compra:</strong> <span>${new Date(boleto.fecha_compra).toLocaleString('es-MX')}</span></div>
                <div class="boleto-detalle"><strong>Código:</strong> <span class="text-muted small">${boleto.codigo_unico}</span></div>
        `;
        
        if (boleto.estatus == 1) {
            html += `
                <hr>
                <button type="button" class="btn btn-success w-100" onclick="confirmarAcceso(${boleto.id_boleto})">
                    <i class="bi bi-check-circle"></i> Confirmar Acceso
                </button>
            `;
        } else {
            html += '<div class="alert alert-warning mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> Este boleto ya fue utilizado anteriormente.</div>';
        }
        
        html += '</div>';
        
        document.getElementById('resultado-verificacion').innerHTML = html;
    }
    
    window.confirmarAcceso = async function(id_boleto) {
        if (!confirm('¿Confirmar el acceso de este boleto? Esta acción no se puede deshacer.')) {
            return;
        }
        
        try {
            const response = await fetch('vnt_interfaz/confirmar_acceso.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_boleto: id_boleto })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('✓ Acceso confirmado exitosamente');
                document.getElementById('input-codigo').value = '';
                document.getElementById('resultado-verificacion').innerHTML = 
                    '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> ' + data.message + '</div>';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            alert('Error al confirmar acceso: ' + error.message);
        }
    };

    // Inicializar el resumen al cargar
    actualizarResumen();
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
