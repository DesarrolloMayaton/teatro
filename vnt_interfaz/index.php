<?php
// 1. CONEXIÓN
// Ajusta la ruta si es necesario. Asumo que está en /vnt_interfaz/
include "../conexion.php"; 

$id_evento_seleccionado = null;
$evento_info = null;
$eventos_lista = [];
$categorias_palette = [];
$mapa_guardado = []; 

// --- MODIFICADO: Empezar vacíos. Se llenarán dinámicamente ---
$colores_por_id = []; 
$categorias_js = []; 

// 2. Cargar todos los eventos
$res_eventos = $conn->query("SELECT id_evento, titulo, tipo, mapa_json FROM evento WHERE finalizado = 0 ORDER BY titulo ASC");
if ($res_eventos) {
    $eventos_lista = $res_eventos->fetch_all(MYSQLI_ASSOC);
}

// 3. Verificar si se seleccionó un evento
if (isset($_GET['id_evento']) && is_numeric($_GET['id_evento'])) {
    $id_evento_seleccionado = (int)$_GET['id_evento'];

    foreach ($eventos_lista as $evt) {
        if ($evt['id_evento'] == $id_evento_seleccionado) {
            $evento_info = $evt;
            break;
        }
    }

    if ($evento_info) {
        // 4. Cargar la paleta
        $stmt_cat = $conn->prepare("SELECT * FROM categorias WHERE id_evento = ? ORDER BY precio ASC");
        $stmt_cat->bind_param("i", $id_evento_seleccionado);
        $stmt_cat->execute();
        $res_categorias = $stmt_cat->get_result();
        
        // --- INICIO: MODIFICADO (Lógica para buscar "General" como default) ---
        $id_categoria_general = null; 
        $color_categoria_general = '#BDBDBD'; // Color fallback (gris)
        $nombre_categoria_general = 'General (Default)';
        $precio_categoria_general = 0.00;

        if ($res_categorias) {
            $categorias_palette = $res_categorias->fetch_all(MYSQLI_ASSOC);
            foreach ($categorias_palette as $c) {
                // Llenar los arrays para JS y PHP
                $colores_por_id[$c['id_categoria']] = $c['color'];
                $categorias_js[$c['id_categoria']] = [
                    'nombre' => $c['nombre_categoria'],
                    'precio' => $c['precio']
                ];
                
                // Buscar "General" (ignorando mayúsculas/minúsculas)
                if (is_null($id_categoria_general) && strtolower($c['nombre_categoria']) === 'general') {
                    $id_categoria_general = (int)$c['id_categoria'];
                    $color_categoria_general = $c['color'];
                    $nombre_categoria_general = $c['nombre_categoria'];
                    $precio_categoria_general = $c['precio'];
                }
            }
        }
        $stmt_cat->close();
        
        // Si por alguna razón "General" NO existe en la BD, creamos un fallback con ID 0
        // para evitar que la página se rompa.
        if (is_null($id_categoria_general)) {
             $id_categoria_general = 0; 
             // Asegurarnos de que el fallback exista en los arrays
             if (!isset($colores_por_id[0])) {
                 $colores_por_id[0] = $color_categoria_general;
             }
             if (!isset($categorias_js[0])) {
                 $categorias_js[0] = [
                     'nombre' => $nombre_categoria_general, 
                     'precio' => $precio_categoria_general
                 ];
             }
        }
        // --- FIN: MODIFICADO ---

        // 5. Cargar el mapa desde JSON
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
<title>Punto de Venta</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* --- (Tus estilos CSS son iguales) --- */
html, body {
  height: 100vh;
  overflow: hidden; 
}
body {
  background-color: #f4f7f6;
  font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  padding: 0; 
  display: flex;
  flex-direction: column;
}
.container-fluid {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 20px;
  box-sizing: border-box; 
}
.card {
  border-radius: 14px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}
.mapper-container { 
  display: flex; 
  gap: 20px; 
  height: 100%; 
  overflow: hidden; 
}
.seat-map-wrapper {
  flex-grow: 1; 
  background: #fff; 
  border-radius: 14px;
  padding: 20px; 
  overflow: auto; 
  height: 100%; 
}
.screen {
  background-color: #333; color: white; padding: 10px;
  text-align: center; font-size: 1.5em; 
  margin-bottom: 25px;
  border-radius: 5px;
  position: sticky; 
  top: -20px;
  z-index: 10;
}
.seat {
  width: 50px; 
  height: 50px; 
  background: #BDBDBD; 
  color: #212121;
  border-radius: 8px; 
  font-size: 16px; 
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid #9E9E9E; 
  cursor: pointer;
  transition: transform .15s ease, filter .15s ease;
  padding: 2px;
  box-sizing: border-box;
  text-align: center;
  line-height: 1; 
}
.seat:hover {
  transform: scale(1.1);
  filter: brightness(0.9);
}
.row-label {
  width: 50px; 
  text-align: center; 
  font-weight: 600;
  font-size: 1.25em; 
  border-radius: 8px; 
  padding: 5px 0;
  cursor: default; 
}
.pasarela {
  width: 100px; 
  height: 60px; 
  background: #333; color: #fff;
  display: flex; align-items: center; justify-content: center;
  border-radius: 6px;
  flex-shrink: 0;
}
.pasarela-text {
  writing-mode: vertical-rl;
  text-orientation: mixed;
  font-weight: 700;
  letter-spacing: 4px;
  font-size: 1.1em; 
}
.seats-block { display: flex; align-items: center; gap: 10px; } 
.seat-row-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 12px; 
}
.pasillo { width: 40px; } 
.controls-panel {
  width: 320px; 
  background: #fff; 
  border-radius: 14px;
  padding: 15px; 
  overflow-y: auto; 
  height: 100%;
  flex-shrink: 0;
}
</style>
</head>
<body>

<div class="container-fluid">
  
  <div class="mapper-container">
    
    <?php if ($evento_info): ?>
    <div class="seat-map-wrapper">
      <div class="screen"><?= ($evento_info['tipo']==1)?'ESCENARIO':'PASARELA / ESCENARIO' ?></div>

      <?php 
      // ========== PASARELA 540 (PB + Teatro) ==========
      if ($evento_info['tipo'] == 2): 
          
          for ($fila=1; $fila<=10; $fila++):
              $nombre_fila = "PB".$fila;
              $numero_en_fila_pb = 1; 
      ?>
      <div class="seat-row-wrapper">
        <div class="row-label"><?= $nombre_fila ?></div>
        <div class="seats-block">
          <?php for ($i=1; $i<=6; $i++): 
                  $nombre_asiento = $nombre_fila . '-' . $numero_en_fila_pb++;
                  // --- MODIFICADO: Default a $id_categoria_general ---
                  $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                  $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
          ?>
            <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                <?= $nombre_asiento ?>
            </div>
          <?php endfor; ?>
          <div class="pasarela">
            <?php if ($fila==5) echo '<span class="pasarela-text">PASARELA</span>'; ?>
          </div>
          <?php for ($i=1; $i<=6; $i++): 
                  $nombre_asiento = $nombre_fila . '-' . $numero_en_fila_pb++;
                  // --- MODIFICADO: Default a $id_categoria_general ---
                  $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                  $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
          ?>
            <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                <?= $nombre_asiento ?>
            </div>
          <?php endfor; ?>
        </div>
        <div class="row-label"><?= $nombre_fila ?></div>
      </div>
      <?php endfor; ?>
      <hr style="margin-top: 30px; margin-bottom: 30px; border-width: 2px;">

      <?php
          $letras = range('A','O'); 
          foreach ($letras as $fila): 
            $numero_en_fila = 1; 
      ?>
          <div class="seat-row-wrapper">
            <div class="row-label"><?= $fila ?></div>
            <div class="seats-block">
              <?php for ($i=0;$i<6;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<14;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<6;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
            </div>
            <div class="row-label"><?= $fila ?></div>
          </div>
          <?php endforeach; ?>
          
          <div class="seat-row-wrapper">
            <div class="row-label">P</div>
            <div class="seats-block">
              <?php $numero_en_fila_p = 1; ?>
              <?php for ($i=0;$i<30;$i++): 
                      $nombre_asiento = 'P' . $numero_en_fila_p++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
            </div>
            <div class="row-label">P</div>
          </div>
      <?php 
      // ========== TEATRO 420 (Solo) ==========
      elseif ($evento_info['tipo'] == 1):
          $letras = range('A','O'); 
          foreach ($letras as $fila): 
            $numero_en_fila = 1; 
      ?>
          <div class="seat-row-wrapper">
            <div class="row-label"><?= $fila ?></div>
            <div class="seats-block">
              <?php for ($i=0;$i<6;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<14;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<6;$i++): 
                      $nombre_asiento = $fila . $numero_en_fila++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
            </div>
            <div class="row-label"><?= $fila ?></div>
          </div>
          <?php endforeach; ?>
          
          <div class="seat-row-wrapper">
            <div class="row-label">P</div>
            <div class="seats-block">
              <?php $numero_en_fila_p = 1; ?>
              <?php for ($i=0;$i<30;$i++): 
                      $nombre_asiento = 'P' . $numero_en_fila_p++;
                      // --- MODIFICADO: Default a $id_categoria_general ---
                      $id_cat = $mapa_guardado[$nombre_asiento] ?? $id_categoria_general;
                      $color_asiento = $colores_por_id[$id_cat] ?? $color_categoria_general;
              ?>
                <div class="seat" style="background-color: <?= $color_asiento ?>" data-asiento-id="<?= $nombre_asiento ?>" data-categoria-id="<?= $id_cat ?>">
                    <?= $nombre_asiento ?>
                </div>
              <?php endfor; ?>
            </div>
            <div class="row-label">P</div>
          </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="controls-panel card">
      
        <h2><i class="bi bi-ticket-perforated"></i> Punto de Venta</h2>
        <form method="GET" class="mb-3">
          <label class="form-label fw-bold">Selecciona un Evento:</label>
          <select name="id_evento" class="form-select form-select-lg" onchange="this.form.submit()">
            <option value="">-- Selecciona un Evento --</option>
            <?php foreach ($eventos_lista as $e): ?>
            <option value="<?= $e['id_evento'] ?>" <?= ($id_evento_seleccionado==$e['id_evento'])?'selected':'' ?>>
              <?= htmlspecialchars($e['titulo']) ?> 
              (<?php 
                      if ($e['tipo'] == 1) {
                          echo 'Teatro 420';
                      } elseif ($e['tipo'] == 2) {
                          echo 'Pasarela 540';
                      } else {
                          echo 'Otro';
                      }
                  ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </form>
        
        <?php if ($evento_info): ?>
        <hr>
        <div class="alert alert-info">
            <i class="bi bi-info-circle-fill"></i>
            Haga clic en un asiento para ver su categoría y precio.
        </div>
        
        <h5>Categorías del Evento</h5>
        <ul class="list-group">
            <?php foreach ($categorias_palette as $c): ?>
                <li class="list-group-item d-flex align-items-center">
                    <span class="palette-color d-inline-block me-2" style="width: 20px; height: 20px; border-radius: 50%; background-color:<?= htmlspecialchars($c['color']) ?>"></span>
                    <?= htmlspecialchars($c['nombre_categoria']) ?> ($<?= number_format($c['precio'],2) ?>)
                </li>
            <?php endforeach; ?>
        </ul>

        <?php endif; ?>
    </div>
    </div>
</div>


<div class="modal fade" id="modalAsientoInfo" tabindex="-1" aria-labelledby="modalInfoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalInfoLabel">Detalles del Asiento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h3 id="info_asiento_nombre" class="text-center"></h3>
        <p id="info_asiento_categoria" class="fs-5 text-center"></p>
        <p id="info_asiento_precio" class="fs-4 fw-bold text-center text-success"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal">Aceptar</button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // --- MODIFICADO: Estos datos ahora vienen de la lógica de PHP ---
    const CATEGORIAS_INFO = <?= json_encode($categorias_js, JSON_NUMERIC_CHECK) ?>;
    
    // --- MODIFICADO: Pasamos el ID real de "General" a JavaScript ---
    const DEFAULT_CAT_ID = <?= $id_categoria_general ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded',()=>{
  
  // --- NUEVO: Lógica del Modal de Información ---

  // 1. Instancia del Modal
  const infoModalElement = document.getElementById('modalAsientoInfo');
  const infoModal = new bootstrap.Modal(infoModalElement);
  
  // 2. Elementos de texto dentro del Modal
  const infoNombre = document.getElementById('info_asiento_nombre');
  const infoCategoria = document.getElementById('info_asiento_categoria');
  const infoPrecio = document.getElementById('info_asiento_precio');

  // 3. Agregar listener a CADA asiento
  document.querySelectorAll('.seat').forEach(s=>{
    s.addEventListener('click',()=>{ 
        // 4. Obtener datos del asiento clickeado
        const asientoId = s.dataset.asientoId;
        const catId = s.dataset.categoriaId;

        // 5. Buscar la info de la categoría en nuestro objeto JS
        // --- MODIFICADO: Usar el ID de "General" como fallback dinámico ---
        // Si no encuentra el ID (ej: un '0' antiguo), usa la info de la categoría "General"
        const categoriaInfo = CATEGORIAS_INFO[catId] || CATEGORIAS_INFO[DEFAULT_CAT_ID];

        // 6. Formatear el precio
        const precioFormateado = parseFloat(categoriaInfo.precio).toLocaleString('es-MX', {
            style: 'currency',
            currency: 'MXN'
        });

        // 7. Rellenar el modal con la información
        infoNombre.textContent = `Asiento: ${asientoId}`;
        infoCategoria.textContent = `Categoría: ${categoriaInfo.nombre}`;
        infoPrecio.textContent = precioFormateado;
        
        // 8. Mostrar el modal
        infoModal.show();
    });
  });

});
</script>

</body>
</html>