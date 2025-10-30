<?php
// 1. CONEXIÓN
include "../conexion.php"; // Sube un nivel a 'teatro/conexion.php'
// Base de datos 'trt_25' debe estar seleccionada.

// 2. INICIALIZAR VARIABLES
$id_evento_actual = null;
$evento = null;
$mapa_asientos = [];
$eventos_activos = []; // Para el menú de eventos

// 3. VERIFICAR EL MODO DE LA PÁGINA
// Si la URL tiene ?id_evento=X, cargamos el MODO ASIENTOS
if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    
    // ===================================
    // === MODO: MOSTRAR ASIENTOS ========
    // ===================================
    $id_evento_actual = (int)$_GET['id_evento'];

    // --- 3A. OBTENER INFORMACIÓN DEL EVENTO (Título y Tipo de Layout) ---
    $stmt_evt = $conn->prepare("SELECT titulo, tipo FROM evento WHERE id_evento = ? AND finalizado = 0");
    $stmt_evt->bind_param("i", $id_evento_actual);
    $stmt_evt->execute();
    $res_evt = $stmt_evt->get_result();
    $evento = $res_evt->fetch_assoc();
    $stmt_evt->close();

    if (!$evento) {
        die("Error: El evento no existe o ya no está activo. <a href='vnt_interfaz/index.php'>Volver al menú</a>");
    }

    // --- 3B. OBTENER PLANTILLA DE ASIENTOS (Tabla 'asientos') ---
    // NO consultamos la tabla 'boletos', como pediste.
    
    $sql_plantilla_base = "SELECT id_asiento, fila, numero, codigo_asiento FROM asientos";
    $filtro_layout = "";

    // Si el evento es Tipo 1 (420 asientos), filtramos la fila 'PB'.
    if ($evento['tipo'] == 1) {
        $filtro_layout = " WHERE fila != 'PB'";
    }
    // Si es Tipo 2 (540 asientos), no filtramos nada.
    
    $sql_plantilla_final = $sql_plantilla_base . $filtro_layout . " ORDER BY fila, numero";
    
    $res_plantilla = $conn->query($sql_plantilla_final);
    
    while ($asiento = $res_plantilla->fetch_assoc()) {
        $fila = $asiento['fila'];
        if (!isset($mapa_asientos[$fila])) {
            $mapa_asientos[$fila] = [];
        }
        // Todos los asientos están 'disponibles'
        $asiento['status'] = 'disponible';
        $mapa_asientos[$fila][] = $asiento;
    }

} else {
    
    // ===================================
    // === MODO: MOSTRAR MENÚ DE EVENTOS ===
    // ===================================
    // (Buscamos también el campo 'imagen' de la tabla 'evento')
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

// Cerramos la conexión
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
        /* Estilos para AMBOS modos */
        body { background-color: #f4f4f4; }
        .content { overflow-y: auto; height: 100vh; padding: 30px; }

        /* Estilos solo para MODO MENÚ (Cartelera) */
        .hero { background-color: #fff; border-bottom: 1px solid #dee2e6; padding: 3rem 0; margin-bottom: 2rem; }
        .card-link { text-decoration: none; color: inherit; }
        .card { transition: transform 0.2s ease, box-shadow 0.2s ease; height: 100%; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12); }
        .card-img-top { width: 100%; height: 350px; object-fit: cover; }
        .card-body { display: flex; flex-direction: column; justify-content: space-between; }
        
        /* Estilos solo para MODO ASIENTOS */
        .seat-map-container { max-width: 1600px; margin: 20px auto; background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow-x: auto; }
        .screen { background-color: #333; color: white; padding: 10px 0; text-align: center; font-size: 1.5em; margin-bottom: 30px; }
        .seat-row { display: flex; justify-content: center; margin-bottom: 8px; min-width: 1100px; }
        .seat-row-pb { min-width: 4000px; } /* Fila 'PB' extra ancha */
        .row-label { width: 40px; font-weight: bold; align-self: center; flex-shrink: 0; }
        .seats-block { display: flex; flex-wrap: nowrap; }
        .seat { width: 30px; height: 30px; margin: 3px; border-radius: 5px; font-size: 10px; display: flex; justify-content: center; align-items: center; font-weight: bold; color: #fff; cursor: pointer; transition: all 0.2s ease; flex-shrink: 0; }
        .seat.disponible { background-color: #28a745; }
        .seat.disponible:hover { background-color: #218838; }
        .seat.vendido { background-color: #dc3545; cursor: not-allowed; opacity: 0.7; }
        .seat.seleccionado { background-color: #007bff; }
        .legend { display: flex; justify-content: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .checkout-summary { max-width: 1600px; margin: 30px auto; padding: 20px; background: #f8f9fa; border-radius: 8px; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <?php if ($id_evento_actual !== null): ?>
            
            <div class="col-12 content">
                
                <a href="vnt_interfaz/index.php" class="btn btn-outline-secondary mb-3">
                    <i class="bi bi-arrow-left"></i> Volver a Eventos
                </a>

                <h2>Simular Compra: <span class="text-success"><?php echo htmlspecialchars($evento['titulo']); ?></span></h2>
                <h5 class="text-muted">
                    Diseño: <?php echo ($evento['tipo'] == 1) ? "Teatro (420 asientos)" : "Teatro + Pasarela (540 asientos)"; ?>
                </h5>
                <p class="text-info-emphasis">
                    <i class="bi bi-info-circle-fill"></i> <strong>Modo Simulación:</strong> Todos los asientos se muestran como disponibles. No se guardará ninguna compra.
                </p>
                <hr>

                <div class="seat-map-container">
                    <div class="screen">ESCENARIO</div>
                    
                    <div class="seat-map">
                        <?php foreach ($mapa_asientos as $fila => $asientos_en_fila): ?>
                            <div class="seat-row <?php echo ($fila == 'PB') ? 'seat-row-pb' : ''; ?>">
                                <div class="row-label text-end me-2"><?php echo $fila; ?></div>
                                <div class="seats-block">
                                    <?php foreach ($asientos_en_fila as $asiento): ?>
                                        <div class="seat <?php echo $asiento['status']; ?>" 
                                             data-id-asiento="<?php echo $asiento['id_asiento']; ?>"
                                             data-codigo-asiento="<?php echo $asiento['codigo_asiento']; ?>"
                                             title="<?php echo $asiento['codigo_asiento']; ?>">
                                            <?php echo $asiento['numero']; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="row-label text-start ms-2"><?php echo $fila; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="legend">
                        <div class="legend-item"><div class="seat disponible"></div><span>Disponible</span></div>
                        <div class="legend-item"><div class="seat seleccionado"></div><span>Seleccionado</span></div>
                    </div>
                </div>

                <div class="checkout-summary" id="form-comprar">
                    <h4>Resumen de Compra</h4>
                    <p>Has seleccionado <strong id="conteo-asientos">0</strong> asientos.
                       Total: <strong id="total-precio">$0.00</strong></p>
                    <ul id="lista-asientos-seleccionados" class="list-group mb-3"></ul>
                    
                    <button type="button" class="btn btn-success btn-lg" id="btn-comprar-simulado">
                        <i class="bi bi-cart-check"></i> Comprar Boletos (Simulación)
                    </button>
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
                                
                                // ==========================================
                                // --- LÓGICA DE IMAGEN COPIADA DE evt_interfaz ---
                                // ==========================================
                                $rutaImagen = 'evt_interfaz/imagenes/placeholder.png'; // Placeholder por defecto
                                if (!empty($evt_menu['imagen'])) {
                                    // Construye la ruta: carpeta + nombre de archivo
                                    $rutaImagen = 'evt_interfaz/imagenes/' . $evt_menu['imagen'];
                                }
                                // ==========================================
                                
                                // Enlace a esta MISMA página
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
document.addEventListener('DOMContentLoaded', function() {
    const PRECIO_POR_BOLETO = 150.00; // Puedes cambiar esto
    const mapa = document.querySelector('.seat-map');
    const conteoEl = document.getElementById('conteo-asientos');
    const totalEl = document.getElementById('total-precio');
    const listaAsientosEl = document.getElementById('lista-asientos-seleccionados');
    let asientosSeleccionados = new Set(); 

    mapa.addEventListener('click', function(e) {
        if (!e.target.classList.contains('seat') || e.target.classList.contains('vendido')) {
            return;
        }
        const seatId = e.target.dataset.idAsiento;
        
        if (asientosSeleccionados.has(seatId)) {
            asientosSeleccionados.delete(seatId);
            e.target.classList.remove('seleccionado');
        } else {
            asientosSeleccionados.add(seatId);
            e.target.classList.add('seleccionado');
        }
        actualizarResumen();
    });
    
    function actualizarResumen() {
        const conteo = asientosSeleccionados.size;
        conteoEl.innerText = conteo;
        totalEl.innerText = `$${(conteo * PRECIO_POR_BOLETO).toFixed(2)}`;
        listaAsientosEl.innerHTML = ''; 
        
        const idsArray = Array.from(asientosSeleccionados);
        
        idsArray.forEach(id => {
            const seatElement = mapa.querySelector(`.seat[data-id-asiento="${id}"]`);
            const seatCode = seatElement.dataset.codigoAsiento;
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.innerHTML = `Asiento: <strong>${seatCode}</strong> <span>$${PRECIO_POR_BOLETO.toFixed(2)}</span>`;
            listaAsientosEl.appendChild(li);
        });

        const btnComprar = document.getElementById('btn-comprar-simulado');
        btnComprar.disabled = (conteo === 0);
    }
    
    // --- LÓGICA DEL BOTÓN DE SIMULACIÓN ---
    document.getElementById('btn-comprar-simulado').addEventListener('click', function(e) {
        if (asientosSeleccionados.size === 0) {
            alert("Por favor, selecciona al menos un asiento para la simulación.");
        } else {
            const idsArray = Array.from(asientosSeleccionados);
            let asientosCodigos = [];
            idsArray.forEach(id => {
                const seatElement = mapa.querySelector(`.seat[data-id-asiento="${id}"]`);
                asientosCodigos.push(seatElement.dataset.codigoAsiento);
            });

            alert(
                "¡SIMULACIÓN EXITOSA!\n\n" +
                "Compraste " + asientosSeleccionados.size + " boletos.\n" +
                "Asientos: " + asientosCodigos.join(', ') + "\n\n" +
                "NO SE GUARDÓ NADA EN LA BASE DE DATOS."
            );
            
            // Redirigir de vuelta al menú principal
            window.location.href = 'vnt_interfaz/index.php';
        }
    });

    actualizarResumen();
});
</script>
<?php endif; ?>

</body>
</html>