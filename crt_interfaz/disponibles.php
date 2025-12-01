<?php
// 1. CONEXIÓN
// Ajustamos la ruta asumiendo que estamos en /crt_interfaz/
include "../conexion.php"; 

// Obtener ID del evento
$id_evento = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$evento = null;
$mapa_guardado = [];
$colores_por_id = [];
$info_categorias = []; // Para guardar nombre y precio
$asientos_vendidos = [];

if ($id_evento > 0) {
    // A. Datos del Evento
    $stmt = $conn->prepare("SELECT titulo, tipo, mapa_json FROM evento WHERE id_evento = ?");
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $evento = $row;
        $mapa_guardado = json_decode($row['mapa_json'], true) ?? [];
    }
    $stmt->close();

    // B. Cargar Categorías (Colores y Precios)
    $res_cat = $conn->query("SELECT id_categoria, nombre_categoria, color, precio FROM categorias WHERE id_evento = $id_evento");
    if ($res_cat) {
        while ($c = $res_cat->fetch_assoc()) {
            $colores_por_id[$c['id_categoria']] = $c['color'];
            $info_categorias[$c['id_categoria']] = [
                'nombre' => $c['nombre_categoria'],
                'precio' => $c['precio']
            ];
        }
    }

    // C. Cargar Asientos Vendidos
    $sql_vendidos = "SELECT a.codigo_asiento 
                     FROM boletos b 
                     JOIN asientos a ON b.id_asiento = a.id_asiento 
                     WHERE b.id_evento = $id_evento AND b.estatus = 1";
    $res_ven = $conn->query($sql_vendidos);
    if ($res_ven) {
        while ($v = $res_ven->fetch_assoc()) {
            $asientos_vendidos[] = $v['codigo_asiento'];
        }
    }
}

// Valores por defecto
$id_categoria_general = 0; 
$color_default = '#BDBDBD'; 

// --- FUNCIÓN HELPER PARA RENDERIZAR ASIENTO ---
function renderSeat($codigo, $mapa, $vendidos, $colores, $infos, $id_def, $col_def) {
    $id_cat = $mapa[$codigo] ?? $id_def;
    $color = $colores[$id_cat] ?? $col_def;
    $esta_vendido = in_array($codigo, $vendidos);
    
    // Datos para tooltip
    $nombre_cat = $infos[$id_cat]['nombre'] ?? 'General';
    $precio = isset($infos[$id_cat]['precio']) ? number_format($infos[$id_cat]['precio'], 2) : '0.00';
    
    $clase = "seat";
    $style = "background-color: $color;";
    $title = "$codigo | $nombre_cat | $$precio";
    
    if ($esta_vendido) {
        $clase .= " vendido";
        $style = ""; // El estilo vendido se maneja por CSS
        $title = "$codigo | Ocupado";
    }

    return "<div class='$clase' style='$style' title='$title'>$codigo</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Disponibilidad - <?= htmlspecialchars($evento['titulo'] ?? 'Evento') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    /* ESTILOS RECICLADOS Y LIMPIADOS */
    :root { --bg-color: #f8fafc; --text-color: #334155; }
    body { background-color: var(--bg-color); font-family: sans-serif; height: 100vh; overflow: hidden; margin: 0; display: flex; flex-direction: column; }
    
    /* Header Simple */
    .header-simple {
        background: white; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 100;
        display: flex; justify-content: space-between; align-items: center;
    }
    .header-simple h4 { margin: 0; font-weight: 700; color: #1e293b; }

    /* Contenedor Mapa */
    .map-viewport {
        flex: 1; width: 100%; overflow: auto; display: flex;
        justify-content: center; padding: 40px; background: #f1f5f9;
        cursor: grab; /* Indicador de que se puede mover/scrollear */
    }
    .map-viewport:active { cursor: grabbing; }

    .map-content {
        transform-origin: top center; transition: transform 0.2s;
        padding-bottom: 100px;
    }

    /* Asientos */
    .seat-row-wrapper { display: flex; align-items: center; justify-content: center; margin-bottom: 8px; }
    .seats-block { display: flex; gap: 6px; }
    .row-label { width: 40px; text-align: center; font-weight: bold; color: #94a3b8; }
    .pasillo { width: 30px; }

    .seat {
        width: 40px; height: 40px; border-radius: 8px; display: flex;
        align-items: center; justify-content: center; font-size: 12px;
        font-weight: 600; color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        cursor: default; user-select: none;
    }

    .seat.vendido {
        background-color: #cbd5e1 !important; color: #94a3b8;
        background-image: repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(255,255,255,0.5) 5px, rgba(255,255,255,0.5) 10px);
    }

    .screen {
        background: #334155; color: white; padding: 10px; text-align: center;
        border-radius: 8px; margin-bottom: 40px; font-weight: bold; letter-spacing: 2px;
        width: 80%; margin-left: auto; margin-right: auto;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .pasarela {
        position: absolute; width: 80px; top: 0; left: 50%; transform: translateX(-50%);
        background: #475569; color: white; display: flex; align-items: center; justify-content: center;
        border-radius: 8px; z-index: 5; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .pasarela-text { writing-mode: vertical-rl; letter-spacing: 5px; font-weight: bold; }

    /* Leyenda Flotante */
    .leyenda {
        position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        background: white; padding: 10px 20px; border-radius: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: flex; gap: 20px; z-index: 100;
        font-size: 0.9rem; color: #64748b;
    }
    .leyenda-item { display: flex; align-items: center; gap: 8px; }
    .dot { width: 12px; height: 12px; border-radius: 50%; }
</style>
</head>
<body>

<div class="header-simple">
    <div>
        <h4><?= htmlspecialchars($evento['titulo'] ?? 'Evento no encontrado') ?></h4>
        <small class="text-muted">Consulta de Disponibilidad</small>
    </div>
    <a href="javascript:window.close()" class="btn btn-outline-secondary btn-sm">Cerrar</a>
</div>

<div class="map-viewport">
    <div class="map-content" id="mapContent">
        <?php if ($evento): ?>
            <div class="screen"><?= ($evento['tipo']==1)?'ESCENARIO':'ESCENARIO PRINCIPAL' ?></div>

            <?php 
            // =========================================================
            // CASO 1: PASARELA (TIPO 2)
            // =========================================================
            if ($evento['tipo'] == 2): 
            ?>
                <div style="position: relative; display: flex; flex-direction: column;">
                    <?php for ($fila=1; $fila<=10; $fila++):
                        $nombre_fila = "PB".$fila;
                        $numero_en_fila_pb = 1; ?>
                    <div class="seat-row-wrapper">
                        <div class="row-label"><?= $nombre_fila ?></div>
                        <div class="seats-block">
                            <?php for ($i=1; $i<=6; $i++): 
                                echo renderSeat($nombre_fila . '-' . $numero_en_fila_pb++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                            endfor; ?>

                            <div style="width: 80px; flex-shrink: 0;"></div>

                            <?php for ($i=1; $i<=6; $i++): 
                                echo renderSeat($nombre_fila . '-' . $numero_en_fila_pb++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                            endfor; ?>
                        </div>
                        <div class="row-label"><?= $nombre_fila ?></div>
                    </div>
                    <?php endfor; ?>
                    
                    <div class="pasarela" style="height: <?= (40 + 8) * 10 ?>px;">
                        <span class="pasarela-text">PASARELA</span>
                    </div>
                </div>
                <hr style="margin: 30px 0; border: 0; border-top: 2px dashed #cbd5e1;">
            <?php endif; ?>

            <?php 
            // =========================================================
            // ZONA DE BUTACAS (COMÚN TIPO 1 Y 2)
            // =========================================================
            $letras = range('A','O'); 
            foreach ($letras as $fila): $numero_en_fila = 1; ?>
            <div class="seat-row-wrapper">
                <div class="row-label"><?= $fila ?></div>
                <div class="seats-block">
                    <?php for ($i=0;$i<6;$i++): 
                        echo renderSeat($fila . $numero_en_fila++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                    endfor; ?>
                    
                    <div class="pasillo"></div>

                    <?php for ($i=0;$i<14;$i++): 
                        echo renderSeat($fila . $numero_en_fila++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                    endfor; ?>

                    <div class="pasillo"></div>

                    <?php for ($i=0;$i<6;$i++): 
                        echo renderSeat($fila . $numero_en_fila++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                    endfor; ?>
                </div>
                <div class="row-label"><?= $fila ?></div>
            </div>
            <?php endforeach; ?>

            <div class="seat-row-wrapper" style="margin-top: 15px;">
                <div class="row-label">P</div>
                <div class="seats-block">
                    <?php $numero_en_fila_p = 1; for ($i=0;$i<30;$i++): 
                        echo renderSeat('P' . $numero_en_fila_p++, $mapa_guardado, $asientos_vendidos, $colores_por_id, $info_categorias, $id_categoria_general, $color_default);
                    endfor; ?>
                </div>
                <div class="row-label">P</div>
            </div>

        <?php else: ?>
            <div class="alert alert-warning text-center">No se encontró información del evento o el ID es inválido.</div>
        <?php endif; ?>
    </div>
</div>

<div class="leyenda">
    <div class="leyenda-item"><div class="dot" style="background: #cbd5e1; border: 1px dashed #94a3b8;"></div> Ocupado</div>
    <div class="leyenda-item"><div class="dot" style="background: #e2e8f0; border: 1px solid #cbd5e1;"></div> Disponible</div>
</div>

<script>
    // Ajustar zoom automáticamente para que el mapa quepa en pantalla
    function ajustarMapa() {
        const viewport = document.querySelector('.map-viewport');
        const content = document.querySelector('.map-content');
        if (!viewport || !content) return;
        
        content.style.transform = 'scale(1)';
        const anchoDisponible = viewport.clientWidth - 40; 
        const anchoContenido = content.scrollWidth;
        
        let escala = 1;
        if (anchoContenido > anchoDisponible) {
            escala = anchoDisponible / anchoContenido;
        }
        // Límite mínimo para móviles
        if(escala < 0.5) escala = 0.5;
        
        content.style.transform = `scale(${escala})`;
    }
    window.addEventListener('load', ajustarMapa);
    window.addEventListener('resize', ajustarMapa);
</script>

</body>
</html>