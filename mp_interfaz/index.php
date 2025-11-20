<?php
// 1. CONEXIÓN
include "../evt_interfaz/conexion.php"; 

$id_evento_seleccionado = null;
$evento_info = null;
$eventos_lista = [];
$categorias_palette = [];
$mapa_guardado = []; 
$colores_por_id = []; 

// 2. Cargar eventos
$res_eventos = $conn->query("SELECT id_evento, titulo, tipo, mapa_json FROM evento WHERE finalizado = 0 ORDER BY titulo ASC");
if ($res_eventos) {
    $eventos_lista = $res_eventos->fetch_all(MYSQLI_ASSOC);
}

// 3. Verificar selección
if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    $id_evento_seleccionado = (int)$_GET['id_evento'];
    foreach ($eventos_lista as $evt) {
        if ($evt['id_evento'] == $id_evento_seleccionado) {
            $evento_info = $evt;
            break;
        }
    }

    if ($evento_info) {
        // 4. Cargar categorías
        $stmt_cat = $conn->prepare("SELECT * FROM categorias WHERE id_evento = ? ORDER BY precio ASC");
        $stmt_cat->bind_param("i", $id_evento_seleccionado);
        $stmt_cat->execute();
        $res_categorias = $stmt_cat->get_result();
        
        $id_categoria_general = null; 
        $color_categoria_general = '#e2e8f0'; // Color base (gris claro visualmente mejor)

        if ($res_categorias) {
            $categorias_palette = $res_categorias->fetch_all(MYSQLI_ASSOC);
            foreach ($categorias_palette as $c) {
                $colores_por_id[$c['id_categoria']] = $c['color'];
                if (is_null($id_categoria_general) && strtolower($c['nombre_categoria']) === 'general') {
                    $id_categoria_general = (int)$c['id_categoria'];
                    $color_categoria_general = $c['color'];
                }
            }
        }
        if (is_null($id_categoria_general)) {
             $id_categoria_general = 0; 
             $colores_por_id[0] = $color_categoria_general;
        }

        // 5. Cargar mapa
        if (!empty($evento_info['mapa_json'])) {
            $mapa_guardado = json_decode($evento_info['mapa_json'], true);
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
<title>Mapeador de Asientos</title>
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

    /* === BASE === */
    html, body { height: 100vh; overflow: hidden; margin: 0; }
    body {
        font-family: "Inter", system-ui, -apple-system, sans-serif;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        color: var(--text-primary);
    }
    .container-fluid { display: flex; height: 100%; padding: 20px; gap: 20px; }
    .card {
        background: var(--bg-secondary); border: 1px solid var(--border-color);
        border-radius: var(--radius-lg); box-shadow: var(--shadow-md); transition: all 0.3s ease;
    }

    /* === MAPPER AREA === */
    .mapper-container { flex: 1; display: flex; overflow: hidden; position: relative; }
    .seat-map-wrapper {
        flex: 1; background: var(--bg-secondary); border-radius: var(--radius-lg);
        padding: 40px; overflow: auto; border: 1px solid var(--border-color);
        box-shadow: var(--shadow-md); display: flex; justify-content: center;
    }
    .seat-map-content { min-width: min-content; transform-origin: top center; transition: transform 0.3s ease; }

    .screen {
        background: linear-gradient(135deg, var(--text-primary) 0%, #334155 100%);
        color: white; padding: 15px; text-align: center; font-weight: 700;
        letter-spacing: 2px; border-radius: var(--radius-md); margin-bottom: 50px;
        box-shadow: var(--shadow-md); position: sticky; top: 0; z-index: 10;
    }

    /* === ASIENTOS === */
    .seat {
        width: 48px; height: 48px; background: #e2e8f0; color: var(--text-primary);
        border-radius: var(--radius-sm); font-size: 13px; font-weight: 600;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid transparent; cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 2px; box-sizing: border-box; text-align: center; line-height: 1;
        will-change: transform; box-shadow: var(--shadow-sm);
    }
    .seat:hover { 
        transform: translateY(-2px) scale(1.05); 
        box-shadow: var(--shadow-md); 
        border-color: var(--primary-color); 
    }
    
    .seat-row-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 10px;
    }

    .seats-block {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pasillo {
        width: 32px;
    }

    .row-label {
        width: 48px; text-align: center; font-weight: 700; font-size: 1.1rem;
        color: var(--text-secondary); border-radius: var(--radius-sm);
        padding: 8px 0; cursor: pointer; transition: all 0.2s ease; user-select: none;
    }
    .row-label:hover { 
        background-color: var(--bg-primary); 
        color: var(--primary-color); 
        transform: scale(1.05); 
    }
    .seats-group { display: flex; gap: 8px; }
    .aisle { width: 32px; }

    .pasarela-container {
        position: relative;
        width: 100px;
        flex-shrink: 0;
    }

    .pasarela {
        width: 100px;
        background: linear-gradient(180deg, var(--text-primary) 0%, #334155 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-md);
        position: absolute;
        top: 0;
        left: 0;
        box-shadow: var(--shadow-md);
    }

    .pasarela-text {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        font-weight: 700;
        letter-spacing: 6px;
        font-size: 1rem;
    }

    /* === SIDEBAR === */
    .sidebar {
        width: 320px; display: flex; flex-direction: column;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); flex-shrink: 0; position: relative;
    }
    .sidebar.collapsed { width: 0; opacity: 0; margin-left: -20px; pointer-events: none; }
    
    .palette-header {
        padding: 20px; border-bottom: 1px solid var(--border-color);
        background: linear-gradient(to right, var(--bg-primary), var(--bg-secondary));
    }
    .palette-body { padding: 20px; overflow-y: auto; flex: 1; }
    .palette-footer { padding: 20px; border-top: 1px solid var(--border-color); background: var(--bg-primary); }

    .palette-item {
        display: flex; align-items: center; padding: 12px; border-radius: var(--radius-sm);
        cursor: pointer; margin-bottom: 8px; border: 2px solid transparent;
        background: var(--bg-primary); transition: all 0.2s;
    }
    .palette-item:hover { border-color: var(--primary-color); transform: translateX(5px); background: #fff; }
    .palette-item.active {
        background: #eff6ff; border-color: var(--primary-color); box-shadow: var(--shadow-sm);
    }
    .color-dot {
        width: 24px; height: 24px; border-radius: 6px; margin-right: 12px;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,0.1);
    }
    .palette-info { flex: 1; font-weight: 600; font-size: 0.9rem; color: var(--text-primary); }

    /* CONTROLES */
    .toggle-sidebar-btn {
        position: absolute; top: 25px; right: 25px; z-index: 100;
        width: 45px; height: 45px; border-radius: 50%; border: none;
        background: var(--primary-color); color: white; box-shadow: var(--shadow-lg);
        display: flex; align-items: center; justify-content: center; cursor: pointer;
        transition: all 0.2s; font-size: 1.3rem;
    }
    .toggle-sidebar-btn:hover { transform: scale(1.1); background: var(--primary-dark); }

    .btn { border-radius: var(--radius-sm); padding: 12px; font-weight: 600; border: none; transition: all 0.2s; }
    .btn-primary { background: var(--primary-color); color: white; }
    .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .btn-success { background: var(--success-color); color: white; }
    .btn-success:hover { background: #059669; transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .form-select { padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background-color: var(--bg-primary); }

    /* HELPERS */
    #loading-overlay {
        position: fixed; inset: 0; background: rgba(255,255,255,0.8); z-index: 9999;
        display: flex; align-items: center; justify-content: center; backdrop-filter: blur(3px);
    }
    .shortcuts-info {
        background: #eff6ff; border: 1px solid #dbeafe; color: #1e40af;
        padding: 15px; border-radius: var(--radius-sm); font-size: 0.85rem;
    }
    .shortcuts-info li { margin-bottom: 4px; }
</style>
</head>
<body>

<div id="loading-overlay" class="d-none">
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
</div>

<div class="container-fluid">
    
    <button class="toggle-sidebar-btn" id="btnToggleSidebar" title="Alternar Panel">
        <i class="bi bi-layout-sidebar-inset-reverse"></i>
    </button>

    <div class="mapper-container">
        <?php if ($evento_info): ?>
        <div class="seat-map-wrapper">
            <div class="seat-map-content" id="seatMapContent">
                <div class="screen"><?= ($evento_info['tipo']==1)?'ESCENARIO PRINCIPAL':'PASARELA 360°' ?></div>
                
                <div style="position: relative; margin: 0 auto; width: fit-content;">
                    
                    <?php if ($evento_info['tipo'] == 2): /* PASARELA */ ?>
                        <div style="position: relative; display: flex; flex-direction: column;">
                        <?php for ($f=1; $f<=10; $f++): $nom="PB".$f; $n=1; ?>
                        <div class="seat-row-wrapper">
                            <div class="row-label" data-row="<?= $nom ?>"><?= $nom ?></div>
                            <div class="seats-block">
                                <?php for($i=1;$i<=6;$i++): $na=$nom.'-'.$n++; $ic=$mapa_guardado[$na]??0; ?>
                                <div class="seat" style="background-color:<?= $colores_por_id[$ic]??'#e2e8f0' ?>" 
                                     data-id="<?= $na ?>" data-cat="<?= $ic ?>"><?= $na ?></div>
                                <?php endfor; ?>
                            </div>
                            <div style="width: 140px; flex-shrink:0;"></div>
                            <div class="seats-block">
                                <?php for($i=1;$i<=6;$i++): $na=$nom.'-'.$n++; $ic=$mapa_guardado[$na]??0; ?>
                                <div class="seat" style="background-color:<?= $colores_por_id[$ic]??'#e2e8f0' ?>" 
                                     data-id="<?= $na ?>" data-cat="<?= $ic ?>"><?= $na ?></div>
                                <?php endfor; ?>
                            </div>
                            <div class="row-label" data-row="<?= $nom ?>"><?= $nom ?></div>
                        </div>
                        <?php endfor; ?>
                        
                        <!-- Pasarela posicionada absolutamente sobre todas las filas -->
                        <div class="pasarela" style="position: absolute; width: 100px; height: <?= (48 + 10) * 10 ?>px; top: 0; left: 50%; transform: translateX(-50%); background: linear-gradient(180deg, var(--text-primary) 0%, #334155 100%); color: #fff; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); box-shadow: var(--shadow-md);">
                            <span class="pasarela-text">PASARELA</span>
                        </div>
                        </div>
                        <hr style="margin-top: 20px; margin-bottom: 20px; border-width: 2px;">
                    <?php endif; ?>

                    <?php foreach (range('A','O') as $fila): $n=1; ?>
                    <div class="seat-row-wrapper">
                        <div class="row-label" data-row="<?= $fila ?>"><?= $fila ?></div>
                        <div class="seats-block">
                            <?php 
                            for($i=0;$i<6;$i++): $na=$fila.$n++; $ic=$mapa_guardado[$na]??0;
                                echo "<div class='seat' style='background-color:".($colores_por_id[$ic]??'#e2e8f0')."' data-id='$na' data-cat='$ic'>$na</div>";
                            endfor;
                            echo '<div class="aisle"></div>';
                            for($i=0;$i<14;$i++): $na=$fila.$n++; $ic=$mapa_guardado[$na]??0;
                                echo "<div class='seat' style='background-color:".($colores_por_id[$ic]??'#e2e8f0')."' data-id='$na' data-cat='$ic'>$na</div>";
                            endfor;
                            echo '<div class="aisle"></div>';
                            for($i=0;$i<6;$i++): $na=$fila.$n++; $ic=$mapa_guardado[$na]??0;
                                echo "<div class='seat' style='background-color:".($colores_por_id[$ic]??'#e2e8f0')."' data-id='$na' data-cat='$ic'>$na</div>";
                            endfor;
                            ?>
                        </div>
                        <div class="row-label" data-row="<?= $fila ?>"><?= $fila ?></div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="seat-row-wrapper">
                        <div class="row-label" data-row="P">P</div>
                        <div class="seats-block">
                            <?php $n=1; for($i=0;$i<30;$i++): $na='P'.$n++; $ic=$mapa_guardado[$na]??0; ?>
                            <div class="seat" style="background-color:<?= $colores_por_id[$ic]??'#e2e8f0' ?>" data-id="<?= $na ?>" data-cat="<?= $ic ?>"><?= $na ?></div>
                            <?php endfor; ?>
                        </div>
                        <div class="row-label" data-row="P">P</div>
                    </div>

                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="d-flex align-items-center justify-content-center flex-grow-1 text-muted bg-white rounded-4 shadow-sm border">
            <div class="text-center">
                <i class="bi bi-arrow-left-square fs-1 text-primary mb-3"></i>
                <h3>Selecciona un evento para comenzar el mapeo</h3>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="sidebar card" id="sidebarPanel">
        <div class="palette-header">
            <h4 class="m-0 fw-bold text-primary"><i class="bi bi-palette2 me-2"></i>Mapeador</h4>
        </div>
        
        <div class="palette-body">
            <div class="mb-4">
                <label class="form-label fw-bold small text-uppercase text-secondary ls-1">Evento Activo</label>
                <form method="GET">
                    <select name="id_evento" class="form-select fw-bold" onchange="this.form.submit()">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($eventos_lista as $e): ?>
                        <option value="<?= $e['id_evento'] ?>" <?= ($id_evento_seleccionado==$e['id_evento'])?'selected':'' ?>>
                            <?= htmlspecialchars($e['titulo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($evento_info): ?>
            <div class="shortcuts-info mb-4">
                <div class="fw-bold mb-2"><i class="bi bi-keyboard me-2"></i>Atajos de Teclado</div>
                <ul class="m-0 ps-3">
                    <li><strong>Click:</strong> Pintar un asiento.</li>
                    <li><strong>Ctrl + Click:</strong> Pintar rango desde el último.</li>
                    <li><strong>Click en Letra:</strong> Pintar fila completa.</li>
                </ul>
            </div>

            <h6 class="fw-bold small text-uppercase text-secondary mb-3 ls-1">Categorías</h6>
            <div id="paletteContainer">
                <div class="palette-item" data-color="#e2e8f0" data-cat-id="0">
                    <div class="color-dot" style="background:#e2e8f0; border: 2px solid #cbd5e1;"></div>
                    <div class="palette-info text-secondary">Sin Asignar / Borrar</div>
                </div>

                <?php foreach ($categorias_palette as $c): ?>
                <div class="palette-item <?= (strtolower($c['nombre_categoria'])==='general') ? 'active' : '' ?>" 
                     data-color="<?= htmlspecialchars($c['color']) ?>" 
                     data-cat-id="<?= $c['id_categoria'] ?>">
                    <div class="color-dot" style="background:<?= htmlspecialchars($c['color']) ?>"></div>
                    <div class="palette-info"><?= htmlspecialchars($c['nombre_categoria']) ?></div>
                    <span class="badge bg-light text-dark border">$<?= number_format($c['precio'],0) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($evento_info): ?>
        <div class="palette-footer">
            <button type="button" class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#modalNuevaCategoria">
                <i class="bi bi-plus-lg"></i> Nueva Categoría
            </button>
            <button id="btnGuardar" class="btn btn-primary w-100 py-3 fs-6">
                <i class="bi bi-cloud-arrow-up-fill me-2"></i> Guardar Mapa
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalNuevaCategoria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-bookmark-plus-fill me-2 text-success"></i>Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-4">
                <form id="formNuevaCategoria">
                    <input type="hidden" name="id_evento" value="<?= $id_evento_seleccionado ?>">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="catNombre" name="nombre" required placeholder="Nombre">
                        <label for="catNombre">Nombre de la Categoría</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="catPrecio" name="precio" step="0.01" min="0" required placeholder="0.00">
                                <label for="catPrecio">Precio ($)</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="h-100 border rounded-3 p-1 bg-light">
                                <input type="color" class="form-control form-control-color w-100 h-100 border-0" name="color" value="#2563eb" title="Elige color">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-light text-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success px-4" form="formNuevaCategoria">Guardar Categoría</button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="current_event_id" value="<?= $id_evento_seleccionado ?>">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // --- ESTADO GLOBAL ---
    let activeColor = '<?= $color_categoria_general ?? "#e2e8f0" ?>';
    let activeCatId = <?= $id_categoria_general ?? 0 ?>;
    let lastClickedSeatIndex = null;

    // Cache de elementos
    const allSeats = Array.from(document.querySelectorAll('.seat'));
    const overlay = document.getElementById('loading-overlay');
    const sidebar = document.getElementById('sidebarPanel');
    const seatMapWrapper = document.querySelector('.seat-map-wrapper');
    const seatMapContent = document.getElementById('seatMapContent');

    // --- 1. GESTIÓN DE PALETA ---
    document.querySelectorAll('.palette-item').forEach(item => {
        item.addEventListener('click', () => {
            document.querySelectorAll('.palette-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            activeColor = item.dataset.color;
            activeCatId = item.dataset.catId;
        });
    });

    // --- 2. INTERACCIÓN AVANZADA CON ASIENTOS ---
    allSeats.forEach((seat, index) => {
        seat.addEventListener('click', (e) => {
            // RANGO: Si presiona Ctrl o Shift y ya había hecho click antes
            if ((e.ctrlKey || e.shiftKey) && lastClickedSeatIndex !== null) {
                const start = Math.min(lastClickedSeatIndex, index);
                const end = Math.max(lastClickedSeatIndex, index);
                for (let i = start; i <= end; i++) {
                    paintSeat(allSeats[i]);
                }
            } else {
                // CLICK NORMAL
                paintSeat(seat);
            }
            lastClickedSeatIndex = index;
        });
    });

    function paintSeat(seatElement) {
        if (seatElement.dataset.cat == activeCatId) return; // Evitar repintar lo mismo
        seatElement.style.backgroundColor = activeColor;
        seatElement.dataset.cat = activeCatId;
        // Animación visual "pop"
        seatElement.animate([
            { transform: 'scale(1)' }, { transform: 'scale(1.4)' }, { transform: 'scale(1)' }
        ], { duration: 300, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' });
    }

    // --- 3. PINTADO DE FILA COMPLETA ---
    document.querySelectorAll('.row-label').forEach(label => {
        label.addEventListener('click', () => {
            const rowName = label.dataset.row;
            // Usar selector de atributo para mayor precisión
            document.querySelectorAll(`.seat[data-id^="${rowName}"]`).forEach(s => paintSeat(s));
        });
    });

    // --- 4. GUARDAR MAPA (AJAX) ---
    const btnGuardar = document.getElementById('btnGuardar');
    if (btnGuardar) {
        btnGuardar.addEventListener('click', async () => {
            overlay.classList.remove('d-none'); // Mostrar spinner full screen
            const eventId = document.getElementById('current_event_id').value;
            // Preparar datos exactamente como el backend los espera
            const mapaArray = allSeats.map(s => ({ asiento: s.dataset.id, cat_id: s.dataset.cat }));

            try {
                const res = await fetch('ajax_guardar_mapa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_evento: eventId, mapa: mapaArray })
                });
                const data = await res.json();
                
                // Notificar cambio si hay id_evento
                if (data.notify_change && data.id_evento) {
                    localStorage.setItem('mapa_actualizado', JSON.stringify({
                        id_evento: data.id_evento,
                        timestamp: Date.now()
                    }));
                }
                
                showToast(data.status === 'success' ? 'Mapa guardado exitosamente' : 'Error: ' + data.message, 
                          data.status === 'success' ? 'success' : 'danger');
            } catch (e) {
                showToast('Error de conexión al guardar.', 'danger');
            } finally {
                overlay.classList.add('d-none');
            }
        });
    }

    // --- 5. NUEVA CATEGORÍA (AJAX) ---
    const formCat = document.getElementById('formNuevaCategoria');
    const modalCat = new bootstrap.Modal(document.getElementById('modalNuevaCategoria'));
    if(formCat) {
        formCat.addEventListener('submit', async (e) => {
            e.preventDefault();
            overlay.classList.remove('d-none');
            try {
                const res = await fetch('ajax_guardar_categoria.php', { method: 'POST', body: new FormData(formCat) });
                const data = await res.json();
                if(data.status === 'success') {
                    window.location.reload(); // Recargar para actualizar paleta
                } else {
                    alert('Error: ' + data.message);
                    overlay.classList.add('d-none');
                }
            } catch (e) {
                alert('Error de conexión.');
                overlay.classList.add('d-none');
            }
        });
    }

    // --- UI: TOGGLE SIDEBAR & ESCALADO ---
    document.getElementById('btnToggleSidebar')?.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        setTimeout(escalarMapa, 350); // Reajustar mapa tras la animación
    });

    function escalarMapa() {
        if (!seatMapWrapper || !seatMapContent) return;
        // Calcular escala para que el mapa quepa siempre en el contenedor
        const scale = Math.min((seatMapWrapper.clientWidth - 80) / seatMapContent.scrollWidth, 1);
        seatMapContent.style.transform = `scale(${Math.max(scale, 0.4)})`; // Limitar zoom mínimo
    }
    window.addEventListener('resize', escalarMapa);
    if (seatMapContent) setTimeout(escalarMapa, 100); // Escalar al inicio

    // --- HELPER: NOTIFICACIONES TOAST ---
    function showToast(msg, type = 'primary') {
        const toast = document.createElement('div');
        toast.className = `position-fixed bottom-0 end-0 m-4 p-3 text-white rounded-3 shadow-lg d-flex align-items-center`;
        toast.style.zIndex = 10000;
        toast.style.background = `var(--${type}-color)`; // Usar colores del root
        toast.innerHTML = `<i class="bi bi-info-circle-fill fs-5 me-3"></i><div class="fw-semibold">${msg}</div>`;
        document.body.appendChild(toast);
        // Animación de entrada
        toast.animate([{ opacity: 0, transform: 'translateY(20px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 300 });
        setTimeout(() => {
            // Animación de salida y remover
            toast.animate([{ opacity: 1 }, { opacity: 0, transform: 'translateY(-20px)' }], { duration: 300 })
                 .onfinish = () => toast.remove();
        }, 3000);
    }
    
    // --- ESCUCHAR CAMBIOS EN CATEGORÍAS ---
    window.addEventListener('storage', (e) => {
        if (e.key === 'categorias_actualizadas' && e.newValue) {
            try {
                const data = JSON.parse(e.newValue);
                const eventoActual = document.getElementById('current_event_id')?.value;
                
                console.log('Categorías actualizadas:', data.id_evento, 'Evento actual:', eventoActual);
                
                if (data.id_evento == eventoActual && eventoActual) {
                    showToast('Las categorías han sido actualizadas, recargando...', 'info');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } catch (error) {
                console.error('Error al procesar categorias_actualizadas:', error);
            }
        }
    });
});
</script>
</body>
</html>