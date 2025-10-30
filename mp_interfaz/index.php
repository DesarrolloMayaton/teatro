<?php
// 1. CONEXIÓN
include "../evt_interfaz/conexion.php"; 

$id_evento_seleccionado = null;
$evento_info = null;
$eventos_lista = [];
$categorias_palette = [];

// 2. Cargar todos los eventos
$res_eventos = $conn->query("SELECT id_evento, titulo, tipo FROM evento WHERE finalizado = 0 ORDER BY titulo ASC");
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
        if ($res_categorias) {
            $categorias_palette = $res_categorias->fetch_all(MYSQLI_ASSOC);
        }
        $stmt_cat->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Mapeador de Asientos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* --- MODIFICADO: Ajuste de layout para 100vh --- */
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
  box-sizing: border-box; /* Asegura que el padding no desborde */
}
.card {
  border-radius: 14px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

/* --- MODIFICADO: Layout principal (Mapa y Menú) --- */
.mapper-container { 
  display: flex; 
  gap: 20px; 
  height: 100%; /* Ocupa toda la altura disponible */
  overflow: hidden; 
}
.seat-map-wrapper {
  flex-grow: 1; 
  background: #fff; 
  border-radius: 14px;
  padding: 20px; 
  overflow: auto; /* Scroll SÓLO para el mapa */
  height: 100%; /* Asegura que ocupe la altura */
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

/* --- ESTILOS DE ASIENTOS (Grandes para zoom 80%) --- */
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
  transition: transform .15s ease, background-color .15s ease, border-color .15s ease;
  padding: 2px;
  box-sizing: border-box;
  text-align: center;
  line-height: 1; 
}
.seat:hover {
  transform: scale(1.1);
  background-color: #9E9E9E; 
  border-color: #757575;
  color: #fff;
}
.row-label {
  width: 50px; 
  text-align: center; 
  font-weight: 600;
  font-size: 1.25em; 
  border-radius: 8px; /* --- NUEVO: Estilo --- */
  padding: 5px 0;
  transition: background-color 0.2s ease;
  cursor: pointer; /* --- NUEVO: Indica que es clicable --- */
}
/* --- NUEVO: Hover para filas --- */
.row-label:hover {
  background-color: #e0eafc;
  color: #0d6efd;
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

/* --- MODIFICADO: Paleta lateral (Siempre visible) --- */
.category-palette {
  width: 320px; /* Un poco más ancho para el selector */
  background: #fff; 
  border-radius: 14px;
  padding: 15px; 
  overflow-y: auto; /* Scroll SÓLO para la paleta */
  height: 100%;
  flex-shrink: 0;
  position: relative;
  transition: all 0.3s ease-in-out;
}
.palette-item {
  display: flex; align-items: center; padding: 10px; border-radius: 8px;
  cursor: pointer; transition: 0.2s ease;
}
.palette-item:hover { background: #f0f0f0; }
.palette-item.selected { background: #e0eafc; border: 2px solid #0d6efd; }
.palette-color { width: 24px; height: 24px; border-radius: 50%; margin-right: 10px; }

#togglePaletteBtn {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 5;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border-radius: 50%;
}
.palette-content {
  transition: opacity 0.2s ease, visibility 0.2s ease;
  opacity: 1;
  visibility: visible;
}
.category-palette.collapsed {
  width: 60px;
  padding: 10px;
}
.category-palette.collapsed #togglePaletteBtn {
  position: relative; 
  top: 0;
  right: 0;
  margin: 0 auto; 
}
.category-palette.collapsed .palette-content {
  opacity: 0;
  visibility: hidden;
  height: 0;
  overflow: hidden;
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
          <?php for ($i=1; $i<=6; $i++): ?>
            <div class="seat"><?= $nombre_fila ?>-<?= $numero_en_fila_pb++; ?></div>
          <?php endfor; ?>
          <div class="pasarela">
            <?php if ($fila==5) echo '<span class="pasarela-text">PASARELA</span>'; ?>
          </div>
          <?php for ($i=1; $i<=6; $i++): ?>
            <div class="seat"><?= $nombre_fila ?>-<?= $numero_en_fila_pb++; ?></div>
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
              <?php for ($i=0;$i<6;$i++): ?>
                <div class="seat"><?= $fila ?><?= $numero_en_fila++; ?></div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<14;$i++): ?>
                <div class="seat"><?= $fila ?><?= $numero_en_fila++; ?></div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<6;$i++): ?>
                <div class="seat"><?= $fila ?><?= $numero_en_fila++; ?></div>
              <?php endfor; ?>
            </div>
            <div class="row-label"><?= $fila ?></div>
          </div>
          <?php endforeach; ?>
          
          <div class="seat-row-wrapper">
            <div class="row-label">P</div>
            <div class="seats-block">
              <?php $numero_en_fila_p = 1; ?>
              <?php for ($i=0;$i<30;$i++): ?>
                <div class="seat"><?= 'P' ?><?= $numero_en_fila_p++; ?></div>
              <?php endfor; ?>
            </div>
            <div class="row-label">P</div>
          </div>
      <?php 
      elseif ($evento_info['tipo'] == 1):
          $letras = range('A','O'); 
          foreach ($letras as $fila): 
            $numero_en_fila = 1; 
      ?>
          <div class="seat-row-wrapper">
            <div class="row-label"><?= $fila ?></div>
            <div class="seats-block">
              <?php for ($i=0;$i<6;$i++): ?>
                <div class="seat"><?= $fila ?><?= $numero_en_fila++; ?></div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<14;$i++): ?>
                <div class="seat"><?= $fila ?><?= $numero_en_fila++; ?></div>
              <?php endfor; ?>
              <div class="pasillo"></div>
              <?php for ($i=0;$i<6;$i++): ?>
                <div class="seat"><?= $fila ?><?= $numero_en_fila++; ?></div>
              <?php endfor; ?>
            </div>
            <div class="row-label"><?= $fila ?></div>
          </div>
          <?php endforeach; ?>
          
          <div class="seat-row-wrapper">
            <div class="row-label">P</div>
            <div class="seats-block">
              <?php $numero_en_fila_p = 1; ?>
              <?php for ($i=0;$i<30;$i++): ?>
                <div class="seat"><?= 'P' ?><?= $numero_en_fila_p++; ?></div>
              <?php endfor; ?>
            </div>
            <div class="row-label">P</div>
          </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="category-palette card" id="palette-sidebar">
      
      <button class="btn btn-outline-primary" id="togglePaletteBtn" title="Minimizar Paleta">
          <i class="bi bi-chevron-right"></i>
      </button>

      <div class="palette-content">
        
        <h2><i class="bi bi-palette"></i> Mapeador</h2>
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
        <h5><i class="bi bi-paint-bucket"></i> Paleta</h5><hr>
        <div class="palette-item selected" data-color="#BDBDBD">
          <span class="palette-color" style="background-color:#BDBDBD"></span>
          <div>Borrador</div>
        </div>
        <?php foreach ($categorias_palette as $c): ?>
        <div class="palette-item" data-color="<?= htmlspecialchars($c['color']) ?>">
          <span class="palette-color" style="background-color:<?= htmlspecialchars($c['color']) ?>"></span>
          <div><?= htmlspecialchars($c['nombre_categoria']) ?> - $<?= number_format($c['precio'],2) ?></div>
        </div>
        <?php endforeach; ?>

        <hr>
        <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#modalNuevaCategoria">
          <i class="bi bi-plus-circle-fill"></i> Nueva Categoría
        </button>
        <?php endif; ?>
        </div>
    </div>
    </div>
</div>


<div class="modal fade" id="modalNuevaCategoria" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Agregar Nueva Categoría</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="formNuevaCategoria">
          
          <input type="hidden" name="id_evento" value="<?= $id_evento_seleccionado ?>">

          <div class="mb-3">
            <label for="cat_nombre" class="form-label">Nombre Categoría</label>
            <input type="text" class="form-control" id="cat_nombre" name="nombre" required>
          </div>
          <div class="mb-3">
            <label for="cat_precio" class="form-label">Precio</label>
            <input type="number" class="form-control" id="cat_precio" name="precio" step="0.01" min="0" required>
          </div>
          <div class="mb-3">
            <label for="cat_color" class="form-label">Color</label>
            <input type="color" class="form-control form-control-color" id="cat_color" name="color" value="#E0E0E0" title="Elige un color">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="submit" class="btn btn-primary" form="formNuevaCategoria">Guardar Categoría</button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded',()=>{
  
  let activeColor = '#BDBDBD';
  
  // Lógica de selección de color
  document.querySelectorAll('.palette-item').forEach(el=>{
    el.addEventListener('click',()=>{
      document.querySelectorAll('.palette-item').forEach(i=>i.classList.remove('selected'));
      el.classList.add('selected');
      activeColor = el.dataset.color;
    });
  });
  
  // APLICAR EVENTO A ASIENTOS INDIVIDUALES
  document.querySelectorAll('.seat').forEach(s=>{
    s.addEventListener('click',()=>{ s.style.backgroundColor=activeColor; });
  });

  // APLICAR EVENTO A FILAS (ROW-LABEL)
  document.querySelectorAll('.row-label').forEach(label => {
    label.addEventListener('click', () => {
      const rowWrapper = label.closest('.seat-row-wrapper');
      if (rowWrapper) {
        const seatsInRow = rowWrapper.querySelectorAll('.seat');
        seatsInRow.forEach(seat => {
          seat.style.backgroundColor = activeColor;
        });
      }
    });
  });

  // Lógica para paleta minimizable
  const palette = document.getElementById('palette-sidebar');
  const toggleBtn = document.getElementById('togglePaletteBtn');
  
  if (palette && toggleBtn) { 
    toggleBtn.addEventListener('click', () => {
      const icon = toggleBtn.querySelector('i');
      const isCollapsed = palette.classList.toggle('collapsed');
      
      if (isCollapsed) {
        icon.classList.remove('bi-chevron-right');
        icon.classList.add('bi-chevron-left');
        toggleBtn.title = "Expandir Paleta";
      } else {
        icon.classList.remove('bi-chevron-left');
        icon.classList.add('bi-chevron-right');
        toggleBtn.title = "Minimizar Paleta";
      }
    });
  }


  // --- NUEVO: LÓGICA PARA GUARDAR CATEGORÍA (MODAL) ---
  const formNuevaCategoria = document.getElementById('formNuevaCategoria');
  const modalNuevaCategoria = new bootstrap.Modal(document.getElementById('modalNuevaCategoria'));

  if(formNuevaCategoria) {
    formNuevaCategoria.addEventListener('submit', function(e) {
      e.preventDefault(); // Evita que la página se recargue

      // 1. Recolectar datos del formulario
      const formData = new FormData(formNuevaCategoria);

      // 2. Enviar datos con Fetch (AJAX) al nuevo archivo PHP
      fetch('ajax_guardar_categoria.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          // 3. Si tiene éxito: avisar, cerrar modal y recargar la página
          alert(data.message);
          modalNuevaCategoria.hide();
          location.reload(); // Recarga la página para mostrar la nueva categoría en la paleta
        } else {
          // 4. Si falla: mostrar el error
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error en fetch:', error);
        alert('Error de conexión al intentar guardar.');
      });
    });
  }

});
</script>

</body>
</html>