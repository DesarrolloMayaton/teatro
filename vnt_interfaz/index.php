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
<link rel="stylesheet" href="css/carrito.css">
<link rel="stylesheet" href="css/notifications.css">
<link rel="stylesheet" href="css/animations.css">
<link rel="stylesheet" href="css/menu-mejoras.css">
<link rel="stylesheet" href="css/seleccion-multiple.css">
<style>
:root {
  --primary-color: #2563eb;
  --primary-dark: #1e40af;
  --success-color: #10b981;
  --danger-color: #ef4444;
  --warning-color: #f59e0b;
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

html, body {
  height: 100vh;
  overflow: hidden;
}

body {
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif;
  color: var(--text-primary);
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.container-fluid {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 24px;
  gap: 20px;
}

.card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  transition: all 0.3s ease;
}

.mapper-container {
  display: flex;
  gap: 24px;
  flex: 1;
  min-height: 0;
}

.seat-map-wrapper {
  flex-grow: 1;
  background: var(--bg-secondary);
  border-radius: var(--radius-lg);
  padding: 32px;
  overflow-y: auto;
  overflow-x: hidden;
  height: 100%;
  display: flex;
  justify-content: center;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-md);
}

.seat-map-wrapper::-webkit-scrollbar {
  width: 8px;
}

.seat-map-wrapper::-webkit-scrollbar-track {
  background: var(--bg-primary);
  border-radius: 4px;
}

.seat-map-wrapper::-webkit-scrollbar-thumb {
  background: var(--border-color);
  border-radius: 4px;
  transition: background 0.2s;
}

.seat-map-wrapper::-webkit-scrollbar-thumb:hover {
  background: var(--text-secondary);
}

.seat-map-content {
  transform-origin: top center;
  width: fit-content;
  min-height: min-content;
}

.screen {
  background: linear-gradient(135deg, var(--text-primary) 0%, #334155 100%);
  color: white;
  padding: 16px 24px;
  text-align: center;
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 32px;
  border-radius: var(--radius-md);
  position: sticky;
  top: -32px;
  z-index: 10;
  box-shadow: var(--shadow-lg);
  letter-spacing: 0.5px;
}

.seat {
  width: 48px;
  height: 48px;
  background: #e2e8f0;
  color: var(--text-primary);
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  padding: 2px;
  box-sizing: border-box;
  text-align: center;
  line-height: 1;
  will-change: transform;
  box-shadow: var(--shadow-sm);
}

.seat:hover {
  transform: translateY(-2px) scale(1.05);
  box-shadow: var(--shadow-md);
  border-color: var(--primary-color);
}

.seat.selected {
  border: 3px solid var(--success-color) !important;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
  transform: scale(1.05);
}

.seat.vendido {
  background: repeating-linear-gradient(
    45deg,
    #ef4444,
    #ef4444 10px,
    #dc2626 10px,
    #dc2626 20px
  ) !important;
  color: white !important;
  cursor: not-allowed !important;
  opacity: 0.7;
}

.seat:active {
  transform: scale(0.95);
}

.row-label {
  width: 48px;
  text-align: center;
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--text-secondary);
  border-radius: var(--radius-sm);
  padding: 8px 0;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.row-label:hover {
  background-color: var(--bg-primary);
  color: var(--primary-color);
  transform: scale(1.05);
}

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

.seats-block {
  display: flex;
  align-items: center;
  gap: 8px;
}

.seat-row-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 10px;
}

.pasillo {
  width: 32px;
}

.controls-panel {
  width: 380px;
  background: var(--bg-secondary);
  border-radius: var(--radius-lg);
  padding: 24px;
  overflow-y: auto;
  overflow-x: hidden;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-md);
  display: flex;
  flex-direction: column;
}

.controls-panel::-webkit-scrollbar {
  width: 8px;
}

.controls-panel::-webkit-scrollbar-track {
  background: var(--bg-primary);
  border-radius: 4px;
}

.controls-panel::-webkit-scrollbar-thumb {
  background: var(--border-color);
  border-radius: 4px;
  transition: background 0.2s;
}

.controls-panel::-webkit-scrollbar-thumb:hover {
  background: var(--text-secondary);
}

.controls-panel h2 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.controls-panel h5 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.form-label {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 8px;
}

.form-select {
  border: 1px solid var(--border-color);
  border-radius: var(--radius-sm);
  padding: 10px 14px;
  font-size: 0.95rem;
  transition: all 0.2s;
  background-color: var(--bg-primary);
}

.form-select:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  outline: none;
}

.btn {
  border-radius: var(--radius-sm);
  padding: 10px 20px;
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 0.2s;
  border: none;
  cursor: pointer;
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

.btn-success:hover:not(:disabled) {
  background: #059669;
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.btn-success:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.alert {
  border-radius: var(--radius-sm);
  padding: 12px 16px;
  font-size: 0.9rem;
  border: none;
  background: #dbeafe;
  color: #1e40af;
}

.list-group-item {
  border: 1px solid var(--border-color);
  padding: 12px 16px;
  font-size: 0.9rem;
  transition: all 0.2s;
}

.list-group-item:first-child {
  border-top-left-radius: var(--radius-sm);
  border-top-right-radius: var(--radius-sm);
}

.list-group-item:last-child {
  border-bottom-left-radius: var(--radius-sm);
  border-bottom-right-radius: var(--radius-sm);
}

.list-group-item:hover {
  background-color: var(--bg-primary);
}

.palette-color {
  box-shadow: var(--shadow-sm);
  border: 2px solid white;
}

hr {
  border: none;
  height: 1px;
  background: var(--border-color);
  margin: 20px 0;
}

.modal-content {
  border-radius: var(--radius-lg);
  border: none;
  box-shadow: var(--shadow-lg);
}

.modal-header {
  border-bottom: 1px solid var(--border-color);
  padding: 20px 24px;
}

.modal-body {
  padding: 24px;
}

.modal-footer {
  border-top: 1px solid var(--border-color);
  padding: 16px 24px;
}

/* Spinner personalizado */
.spinner-border {
  width: 2.5rem;
  height: 2.5rem;
  border-width: 3px;
}

/* Mejoras en inputs y selects */
.form-select:hover {
  border-color: var(--primary-color);
}

/* Animaciones suaves */
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

.controls-panel > * {
  animation: fadeIn 0.3s ease;
}

/* Mejoras en botones */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn i {
  font-size: 1.1em;
}

/* Botón para ocultar paneles */
.toggle-panels-btn {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 1000;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--primary-color);
  color: white;
  border: none;
  box-shadow: var(--shadow-lg);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  font-size: 1.3rem;
}

.toggle-panels-btn:hover {
  background: var(--primary-dark);
  transform: scale(1.1);
}

.toggle-panels-btn:active {
  transform: scale(0.95);
}

/* Clases para ocultar paneles */
.controls-panel.hidden {
  display: none;
}

.seat-map-wrapper.fullscreen {
  width: 100%;
}

/* Responsive */
@media (max-width: 1200px) {
  .controls-panel {
    width: 340px;
  }
}

@media (max-width: 992px) {
  .mapper-container {
    flex-direction: column;
  }
  
  .controls-panel {
    width: 100%;
    max-height: 400px;
  }
  
  .seat-map-wrapper {
    min-height: 500px;
  }
}

@media (max-width: 768px) {
  .container-fluid {
    padding: 16px;
  }
  
  .seat {
    width: 42px;
    height: 42px;
    font-size: 11px;
  }
  
  .row-label {
    width: 42px;
    font-size: 1rem;
  }
  
  .seats-block {
    gap: 6px;
  }
  
  .seat-row-wrapper {
    margin-bottom: 8px;
  }
}

/* Sección de Categorías Separada */
.seccion-categorias-separada {
  margin-top: 20px;
  padding-top: 0;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  overflow: hidden;
  background: var(--bg-secondary);
}

.seccion-categorias-separada .seccion-header {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-bottom: 1px solid #fbbf24;
}

.seccion-categorias-separada .seccion-header:hover {
  background: linear-gradient(135deg, #fde68a 0%, #fcd34d 100%);
}

.seccion-categorias-separada .seccion-header h5 {
  color: #92400e;
}

.seccion-categorias-separada .seccion-header .toggle-icon {
  color: #92400e;
}

.categorias-info-text {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 6px;
  padding: 8px 12px;
}

.categorias-info-text i {
  color: #2563eb;
}

/* Mejoras visuales adicionales */
.list-group {
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.d-grid .btn {
  padding: 12px 20px;
}

/* Efecto hover en categorías */
.list-group-item {
  cursor: default;
  transition: all 0.2s ease;
}

/* Sombra suave en elementos interactivos */
.form-select:focus,
.btn:focus {
  outline: none;
}

/* Mejora en el diseño del total */
.total-section h4 {
  font-size: 1.3rem;
  letter-spacing: -0.5px;
}

/* ===== ESTILOS MEJORADOS DEL PANEL ===== */

.panel-header {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid var(--border-color);
}

.panel-header h2 {
  margin-bottom: 8px;
}

.evento-badge {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  border: 1px solid #93c5fd;
  border-radius: 8px;
  padding: 8px 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  color: #1e40af;
  font-weight: 600;
  margin-top: 8px;
}

.evento-badge i {
  font-size: 1rem;
}

.seccion-evento {
  margin-bottom: 20px;
}

.input-group-text {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
}

/* Estadísticas Rápidas */
.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 20px;
}

.stat-card {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.2s ease;
}

.stat-card:hover {
  border-color: var(--primary-color);
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.stat-card i {
  font-size: 1.8rem;
  color: var(--primary-color);
}

.stat-value {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1;
}

.stat-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 2px;
}

/* Secciones Colapsables */
.seccion-carrito,
.seccion-categorias-separada {
  margin-bottom: 16px;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  overflow: hidden;
  background: var(--bg-secondary);
}

/* Contenedor de items del carrito con scroll */
.carrito-items-container {
  max-height: 250px;
  overflow-y: auto;
  overflow-x: hidden;
  margin-bottom: 16px;
  padding-right: 4px;
}

.carrito-items-container::-webkit-scrollbar {
  width: 6px;
}

.carrito-items-container::-webkit-scrollbar-track {
  background: var(--bg-primary);
  border-radius: 3px;
}

.carrito-items-container::-webkit-scrollbar-thumb {
  background: var(--border-color);
  border-radius: 3px;
  transition: background 0.2s;
}

.carrito-items-container::-webkit-scrollbar-thumb:hover {
  background: var(--text-secondary);
}

/* Footer fijo del carrito */
.carrito-footer {
  position: relative;
  background: var(--bg-secondary);
  padding-top: 12px;
  border-top: 2px solid var(--border-color);
}

.seccion-header {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 12px 16px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all 0.2s ease;
  user-select: none;
}

.seccion-header:hover {
  background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
}

.seccion-header h5 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.seccion-header .toggle-icon {
  transition: transform 0.3s ease;
  color: var(--text-secondary);
}

.seccion-header.collapsed .toggle-icon {
  transform: rotate(-90deg);
}

.seccion-content {
  padding: 16px;
  max-height: 1000px;
  overflow: hidden;
  transition: max-height 0.3s ease, padding 0.3s ease;
}

.seccion-content.collapsed {
  max-height: 0;
  padding: 0 16px;
}

/* Selector de Descuentos Minimalista */
.descuento-selector-separado {
  background: transparent;
  border: none;
  border-radius: 0;
  padding: 0;
  margin-bottom: 16px;
}

.descuento-selector-separado .form-label {
  margin-bottom: 6px;
  font-size: 0.75rem;
  color: var(--text-secondary);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.descuento-selector-separado .form-label i {
  font-size: 0.9rem;
}

.descuento-selector-separado .form-select {
  font-size: 0.9rem;
  border: 1px solid var(--border-color);
  background-color: var(--bg-secondary);
  font-weight: 500;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  transition: all 0.2s ease;
}

.descuento-selector-separado .form-select:hover {
  border-color: var(--text-secondary);
}

.descuento-selector-separado .form-select:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.descuento-selector-separado small {
  font-size: 0.75rem;
  color: var(--success-color);
  font-weight: 500;
}

.descuento-selector .form-label {
  margin-bottom: 6px;
  font-size: 0.85rem;
  color: #92400e;
  font-weight: 600;
}

.descuento-selector .form-select {
  font-size: 0.9rem;
}

.descuento-selector small {
  display: block;
  margin-top: 6px;
  font-size: 0.8rem;
  color: #92400e;
}

/* Acciones Rápidas */
.acciones-rapidas {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--border-color);
}

.acciones-rapidas .btn {
  font-weight: 600;
  padding: 12px 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.acciones-rapidas .btn-outline-secondary {
  border: 2px solid var(--border-color);
  color: var(--text-secondary);
}

.acciones-rapidas .btn-outline-secondary:hover {
  background: var(--bg-primary);
  border-color: var(--text-secondary);
  color: var(--text-primary);
}

/* Sección de Categorías Separada */
.seccion-categorias-separada {
  margin-top: 20px;
  padding-top: 0;
}

.seccion-categorias-separada .seccion-header {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-bottom: 1px solid #fbbf24;
}

.seccion-categorias-separada .seccion-header:hover {
  background: linear-gradient(135deg, #fde68a 0%, #fcd34d 100%);
}

.seccion-categorias-separada .seccion-header h5 {
  color: #92400e;
}

.seccion-categorias-separada .seccion-header .toggle-icon {
  color: #92400e;
}

.categorias-info-text {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 6px;
  padding: 8px 12px;
}

.categorias-info-text i {
  color: #2563eb;
}

/* Mejoras en badges */
.badge {
  padding: 6px 12px;
  font-weight: 600;
  font-size: 0.85rem;
}

/* Animaciones suaves para las secciones */
@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.seccion-content:not(.collapsed) {
  animation: slideDown 0.3s ease;
}

/* Responsive para el panel */
@media (max-width: 992px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .stat-card {
    padding: 12px;
  }
}
</style>
</head>
<body>

<div class="container-fluid">
  
  <!-- Botón para ocultar/mostrar paneles -->
  <button class="toggle-panels-btn" id="togglePanelsBtn" onclick="togglePanels()" title="Ocultar/Mostrar paneles">
    <i class="bi bi-arrows-angle-expand" id="toggleIcon"></i>
  </button>
  
  <div class="mapper-container">
    
    <?php if ($evento_info): ?>
    <div class="seat-map-wrapper">
      <div class="seat-map-content" id="seatMapContent">
      <div class="screen"><?= ($evento_info['tipo']==1)?'ESCENARIO':'PASARELA / ESCENARIO' ?></div>

      <?php 
      // ========== PASARELA 540 (PB + Teatro) ==========
      if ($evento_info['tipo'] == 2): 
      ?>
      <div style="position: relative; display: flex; flex-direction: column;">
      <?php
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
          <div style="width: 100px; flex-shrink: 0;"></div>
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
      
      <!-- Pasarela posicionada absolutamente sobre todas las filas -->
      <div class="pasarela" style="position: absolute; width: 100px; height: <?= (48 + 10) * 10 ?>px; top: 0; left: 50%; transform: translateX(-50%); background: linear-gradient(180deg, var(--text-primary) 0%, #334155 100%); color: #fff; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); box-shadow: var(--shadow-md);">
        <span class="pasarela-text">PASARELA</span>
      </div>
      </div>
      <hr style="margin-top: 20px; margin-bottom: 20px; border-width: 2px;">

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
    </div>
    <?php endif; ?>
    <div class="controls-panel card">
      
        <!-- Header del Panel -->
        <div class="panel-header">
            <h2><i class="bi bi-shop"></i> Punto de Venta</h2>
            <?php if ($evento_info): ?>
            <div class="evento-badge">
                <i class="bi bi-calendar-event"></i>
                <span><?= htmlspecialchars($evento_info['titulo']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Selector de Evento -->
        <div class="seccion-evento">
            <form method="GET" id="formEvento">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                    <select name="id_evento" class="form-select" onchange="cambiarEvento(this)">
                        <option value="">Seleccionar evento...</option>
                        <?php foreach ($eventos_lista as $e): ?>
                        <option value="<?= $e['id_evento'] ?>" <?= ($id_evento_seleccionado==$e['id_evento'])?'selected':'' ?>>
                            <?= htmlspecialchars($e['titulo']) ?> 
                            <?php 
                                if ($e['tipo'] == 1) {
                                    echo '• Teatro 420';
                                } elseif ($e['tipo'] == 2) {
                                    echo '• Pasarela 540';
                                } else {
                                    echo '• Otro';
                                }
                            ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <div id="loadingIndicator" style="display:none;" class="text-center mt-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2" style="color: var(--text-secondary); font-size: 0.9rem;">Cargando evento...</p>
            </div>
        </div>
        
        <?php if ($evento_info): ?>
        
        <!-- Estadísticas Rápidas -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="bi bi-ticket-perforated"></i>
                <div>
                    <div class="stat-value" id="statAsientos">0</div>
                    <div class="stat-label">Asientos</div>
                </div>
            </div>
            <div class="stat-card">
                <i class="bi bi-cash-coin"></i>
                <div>
                    <div class="stat-value" id="statTotal">$0</div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
        </div>
        
        <!-- Carrito de Compras -->
        <div class="seccion-carrito">
            <div class="seccion-header" onclick="toggleSeccion('carrito')">
                <h5><i class="bi bi-cart3"></i> Carrito de Compras</h5>
                <i class="bi bi-chevron-down toggle-icon"></i>
            </div>
            <div class="seccion-content" id="seccion-carrito">
                <!-- Lista de items con scroll -->
                <div class="carrito-items-container">
                    <div id="carritoItems">
                        <div class="carrito-vacio">Selecciona asientos en el mapa</div>
                    </div>
                </div>
                
                <!-- Solo el total -->
                <div class="carrito-footer">
                    <div class="total-section">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4>Total a Pagar</h4>
                            <h4 id="totalCompra">$0.00</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botón de Pago Principal -->
        <button id="btnPagar" class="btn btn-success btn-lg w-100 mb-3" onclick="procesarPago()" disabled style="padding: 16px; font-size: 1.1rem; font-weight: 700; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);">
            <i class="bi bi-credit-card"></i> Procesar Pago
        </button>
        
        <!-- Selector de Descuentos -->
        <div class="descuento-selector-separado">
            <label class="form-label">
                <i class="bi bi-tag"></i> Descuento
            </label>
            <select id="selectDescuento" class="form-select form-select-sm" onchange="aplicarDescuento()">
                <option value="">Sin descuento</option>
            </select>
            <small class="d-block mt-1" id="descuentoInfo"></small>
        </div>
        
        <!-- Acciones Rápidas -->
        <div class="acciones-rapidas">
            <button class="btn btn-primary w-100 mb-2" onclick="abrirEscanerQR()">
                <i class="bi bi-qr-code-scan"></i> Escanear Boleto QR
            </button>
            
            <button class="btn btn-danger w-100 mb-2" onclick="abrirCancelarBoleto()">
                <i class="bi bi-x-circle"></i> Cancelar/Devolver Boleto
            </button>
            
            <button class="btn btn-warning w-100 mb-2" onclick="verCategorias()">
                <i class="bi bi-palette"></i> Categorías
            </button>
            
            <!-- Selección Múltiple -->
            <div class="alert alert-info p-2 mb-2" style="font-size: 0.85rem;">
                <i class="bi bi-info-circle"></i> 
                <strong>Selección rápida:</strong><br>
                • <strong>Ctrl + Click:</strong> Seleccionar rango<br>
                • <strong>Doble Click:</strong> Seleccionar fila completa
            </div>
            
            <button class="btn btn-outline-secondary w-100" onclick="limpiarSeleccion()">
                <i class="bi bi-arrow-counterclockwise"></i> Limpiar Selección
            </button>
        </div>

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

<!-- Modal Escáner QR -->
<div class="modal fade" id="modalEscanerQR" tabindex="-1" aria-labelledby="modalEscanerLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalEscanerLabel">
          <i class="bi bi-qr-code-scan"></i> Escanear Código QR
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="qr-reader" style="width: 100%;"></div>
        <div id="qr-reader-results" class="mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary" onclick="toggleEfectoEspejo()" id="btnEspejo">
          <i class="bi bi-arrow-left-right"></i> Efecto Espejo
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Información del Boleto -->
<div class="modal fade" id="modalBoletoInfo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" id="boletoInfoHeader">
        <h5 class="modal-title" id="boletoInfoTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="boletoInfoBody">
        <!-- Contenido dinámico -->
      </div>
      <div class="modal-footer" id="boletoInfoFooter">
        <!-- Botones dinámicos -->
      </div>
    </div>
  </div>
</div>

<!-- Modal Ver Categorías -->
<div class="modal fade" id="modalCategorias" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title">
          <i class="bi bi-palette"></i> Categorías de Precios
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info mb-3">
          <i class="bi bi-info-circle"></i> 
          Los colores en el mapa representan diferentes precios
        </div>
        <ul class="list-group">
          <?php foreach ($categorias_palette as $c): ?>
            <li class="list-group-item d-flex align-items-center gap-3">
              <span class="palette-color d-inline-block" style="width: 24px; height: 24px; border-radius: 8px; background-color:<?= htmlspecialchars($c['color']) ?>; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></span>
              <span style="flex: 1; font-size: 1rem; font-weight: 500;"><?= htmlspecialchars($c['nombre_categoria']) ?></span>
              <span class="badge bg-success fs-6">${<?= number_format($c['precio'],2) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Ver Asientos Vendidos -->
<div class="modal fade" id="modalAsientosVendidos" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">
          <i class="bi bi-eye"></i> Asientos Vendidos
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="listaAsientosVendidos">
          <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2">Cargando asientos vendidos...</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Cancelar/Devolver Boleto -->
<div class="modal fade" id="modalCancelarBoleto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">
          <i class="bi bi-x-circle"></i> Cancelar/Devolver Boleto
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle"></i>
          <strong>Atención:</strong> Esta acción cancelará el boleto y liberará el asiento para nueva venta.
        </div>
        
        <div class="mb-3">
          <label for="inputCodigoBoleto" class="form-label">
            <i class="bi bi-ticket-perforated"></i> Código del Boleto
          </label>
          <input type="text" class="form-control" id="inputCodigoBoleto" placeholder="Ingrese el código del boleto" autocomplete="off">
          <small class="text-muted">Puede escanear el código QR o ingresarlo manualmente</small>
        </div>
        
        <button class="btn btn-outline-primary w-100 mb-3" onclick="escanearParaCancelar()">
          <i class="bi bi-qr-code-scan"></i> Escanear Código QR
        </button>
        
        <div id="infoBoletoACancelar" style="display: none;">
          <hr>
          <h6 class="mb-3">Información del Boleto:</h6>
          <div class="card bg-light">
            <div class="card-body">
              <p class="mb-2"><strong>Asiento:</strong> <span id="cancelarAsiento"></span></p>
              <p class="mb-2"><strong>Categoría:</strong> <span id="cancelarCategoria"></span></p>
              <p class="mb-2"><strong>Precio:</strong> <span id="cancelarPrecio" class="text-success"></span></p>
              <p class="mb-0"><strong>Fecha de compra:</strong> <span id="cancelarFecha"></span></p>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-danger" id="btnConfirmarCancelacion" onclick="confirmarCancelacion()" disabled>
          <i class="bi bi-trash"></i> Confirmar Cancelación
        </button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
    // --- MODIFICADO: Estos datos ahora vienen de la lógica de PHP ---
    const CATEGORIAS_INFO = <?= json_encode($categorias_js, JSON_NUMERIC_CHECK) ?>;
    
    // --- MODIFICADO: Pasamos el ID real de "General" a JavaScript ---
    const DEFAULT_CAT_ID = <?= $id_categoria_general ?? 0 ?>;
    
    // Variable global para almacenar datos del boleto a cancelar
    let boletoACancelar = null;
    
    // Función para cambiar evento con indicador de carga
    function cambiarEvento(select) {
        const idEvento = select.value;
        if (!idEvento) return;
        
        const loading = document.getElementById('loadingIndicator');
        if (loading) loading.style.display = 'block';
        
        select.form.submit();
    }
    
    // Función para abrir el modal de cancelar boleto
    function abrirCancelarBoleto() {
        const modal = new bootstrap.Modal(document.getElementById('modalCancelarBoleto'));
        document.getElementById('inputCodigoBoleto').value = '';
        document.getElementById('infoBoletoACancelar').style.display = 'none';
        document.getElementById('btnConfirmarCancelacion').disabled = true;
        boletoACancelar = null;
        modal.show();
    }
    
    // Función para buscar boleto por código (con debounce para evitar múltiples llamadas)
    let timeoutBusqueda = null;
    document.getElementById('inputCodigoBoleto')?.addEventListener('input', function(e) {
        const codigo = e.target.value.trim();
        
        // Limpiar timeout anterior
        if (timeoutBusqueda) {
            clearTimeout(timeoutBusqueda);
        }
        
        if (codigo.length >= 8) {
            // Esperar 500ms antes de buscar (debounce)
            timeoutBusqueda = setTimeout(() => {
                console.log('Buscando boleto con código:', codigo);
                buscarBoletoPorCodigo(codigo);
            }, 500);
        } else {
            document.getElementById('infoBoletoACancelar').style.display = 'none';
            document.getElementById('btnConfirmarCancelacion').disabled = true;
            boletoACancelar = null;
        }
    });
    
    // Función para buscar boleto en la base de datos
    function buscarBoletoPorCodigo(codigo) {
        console.log('Enviando petición para buscar boleto:', codigo);
        
        fetch('buscar_boleto.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'codigo=' + encodeURIComponent(codigo) + '&id_evento=<?= $id_evento_seleccionado ?>'
        })
        .then(response => {
            console.log('Respuesta recibida:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Texto de respuesta:', text);
            try {
                const data = JSON.parse(text);
                console.log('Datos parseados:', data);
                
                if (data.success) {
                    mostrarInfoBoletoParaCancelar(data.boleto);
                } else {
                    document.getElementById('infoBoletoACancelar').style.display = 'none';
                    document.getElementById('btnConfirmarCancelacion').disabled = true;
                    boletoACancelar = null;
                    if (data.message && typeof notify !== 'undefined') {
                        notify.error(data.message);
                    }
                }
            } catch (e) {
                console.error('Error al parsear JSON:', e);
                console.error('Texto recibido:', text);
                if (typeof notify !== 'undefined') {
                    notify.error('Error al procesar la respuesta del servidor');
                }
            }
        })
        .catch(error => {
            console.error('Error en la petición:', error);
            if (typeof notify !== 'undefined') {
                notify.error('Error al buscar el boleto');
            }
        });
    }
    
    // Función para mostrar información del boleto para cancelar
    function mostrarInfoBoletoParaCancelar(boleto) {
        boletoACancelar = boleto;
        document.getElementById('cancelarAsiento').textContent = boleto.asiento;
        document.getElementById('cancelarCategoria').textContent = boleto.categoria;
        document.getElementById('cancelarPrecio').textContent = '$' + parseFloat(boleto.precio).toFixed(2);
        document.getElementById('cancelarFecha').textContent = boleto.fecha_compra;
        document.getElementById('infoBoletoACancelar').style.display = 'block';
        document.getElementById('btnConfirmarCancelacion').disabled = false;
    }
    
    // Función para confirmar la cancelación
    function confirmarCancelacion() {
        if (!boletoACancelar) {
            if (typeof notify !== 'undefined') {
                notify.error('No hay boleto seleccionado');
            }
            return;
        }
        
        if (!confirm('¿Está seguro de que desea cancelar este boleto? Esta acción no se puede deshacer.')) {
            return;
        }
        
        const btnConfirmar = document.getElementById('btnConfirmarCancelacion');
        btnConfirmar.disabled = true;
        btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cancelando...';
        
        fetch('cancelar_boleto.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id_boleto=' + encodeURIComponent(boletoACancelar.id_boleto)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof notify !== 'undefined') {
                    notify.success('Boleto cancelado exitosamente');
                }
                
                // Cerrar el modal
                bootstrap.Modal.getInstance(document.getElementById('modalCancelarBoleto')).hide();
                
                // Liberar el asiento visualmente
                const asientoId = boletoACancelar.asiento;
                
                // Remover del set de asientos vendidos
                if (typeof asientosVendidos !== 'undefined') {
                    asientosVendidos.delete(asientoId);
                }
                
                // Buscar el elemento del asiento en el DOM y quitarle la clase 'vendido'
                const seatElement = document.querySelector(`.seat[data-asiento-id="${asientoId}"]`);
                if (seatElement) {
                    seatElement.classList.remove('vendido');
                    seatElement.style.pointerEvents = 'auto';
                    seatElement.style.cursor = 'pointer';
                    console.log('Asiento liberado visualmente:', asientoId);
                }
                
                // Recargar los asientos vendidos para asegurar sincronización
                setTimeout(() => {
                    if (typeof cargarAsientosVendidos === 'function') {
                        cargarAsientosVendidos();
                        console.log('Asientos vendidos recargados desde el servidor');
                    }
                }, 300);
                
                // Resetear el formulario
                document.getElementById('inputCodigoBoleto').value = '';
                document.getElementById('infoBoletoACancelar').style.display = 'none';
                boletoACancelar = null;
            } else {
                if (typeof notify !== 'undefined') {
                    notify.error(data.message || 'Error al cancelar el boleto');
                }
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = '<i class="bi bi-trash"></i> Confirmar Cancelación';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof notify !== 'undefined') {
                notify.error('Error al cancelar el boleto');
            }
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = '<i class="bi bi-trash"></i> Confirmar Cancelación';
        });
    }
    
    // Función para escanear QR para cancelar
    function escanearParaCancelar() {
        // Cerrar el modal de cancelación
        const modalCancelar = bootstrap.Modal.getInstance(document.getElementById('modalCancelarBoleto'));
        if (modalCancelar) {
            modalCancelar.hide();
        }
        
        // Esperar a que se cierre el modal antes de abrir el escáner
        setTimeout(() => {
            // Configurar el escáner para modo cancelación
            window.modoCancelacion = true;
            
            // Abrir el modal del escáner
            const modalEscaner = new bootstrap.Modal(document.getElementById('modalEscanerQR'));
            modalEscaner.show();
            
            // Iniciar escáner cuando el modal se muestre completamente
            document.getElementById('modalEscanerQR').addEventListener('shown.bs.modal', function () {
                if (typeof iniciarEscaner === 'function') {
                    iniciarEscaner();
                }
            }, { once: true });
            
            // Resetear modo cuando se cierre el modal
            document.getElementById('modalEscanerQR').addEventListener('hidden.bs.modal', function () {
                window.modoCancelacion = false;
            }, { once: true });
        }, 300);
    }
    
    // Función para escalar el mapa de asientos automáticamente (optimizada)
    let resizeTimeout;
    function escalarMapa() {
        const wrapper = document.querySelector('.seat-map-wrapper');
        const content = document.getElementById('seatMapContent');
        
        if (!wrapper || !content) return;
        
        const wrapperWidth = wrapper.clientWidth - 40;
        const contentWidth = content.scrollWidth;
        const scale = Math.min(wrapperWidth / contentWidth, 1);
        
        content.style.transform = scale < 1 ? `scale(${scale})` : 'scale(1)';
    }
    
    function escalarMapaDebounced() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(escalarMapa, 150);
    }
    
    // Ejecutar al cargar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', escalarMapa);
    } else {
        escalarMapa();
    }
    
    window.addEventListener('resize', escalarMapaDebounced);
    
    // Función para ocultar/mostrar paneles laterales
    let panelsHidden = false;
    
    function togglePanels() {
        const controlsPanel = document.querySelector('.controls-panel');
        const seatMapWrapper = document.querySelector('.seat-map-wrapper');
        const toggleIcon = document.getElementById('toggleIcon');
        
        panelsHidden = !panelsHidden;
        
        if (panelsHidden) {
            // Ocultar panel de controles
            if (controlsPanel) controlsPanel.classList.add('hidden');
            if (seatMapWrapper) seatMapWrapper.classList.add('fullscreen');
            if (toggleIcon) {
                toggleIcon.classList.remove('bi-arrows-angle-expand');
                toggleIcon.classList.add('bi-arrows-angle-contract');
            }
        } else {
            // Mostrar panel de controles
            if (controlsPanel) controlsPanel.classList.remove('hidden');
            if (seatMapWrapper) seatMapWrapper.classList.remove('fullscreen');
            if (toggleIcon) {
                toggleIcon.classList.remove('bi-arrows-angle-contract');
                toggleIcon.classList.add('bi-arrows-angle-expand');
            }
        }
        
        // Re-escalar el mapa después de cambiar la visibilidad
        setTimeout(escalarMapa, 100);
    }
    
    // ==================================================================
    // ACTUALIZACIÓN AUTOMÁTICA DEL SELECTOR DE EVENTOS
    // ==================================================================
    
    // Función para actualizar el selector de eventos sin recargar la página
    async function actualizarSelectorEventos() {
        try {
            const response = await fetch('obtener_eventos.php');
            const data = await response.json();
            
            if (data.success) {
                const selectEvento = document.querySelector('select[name="id_evento"]');
                if (!selectEvento) return;
                
                const eventoActual = selectEvento.value;
                const eventosActuales = Array.from(selectEvento.options).map(opt => opt.value);
                const eventosNuevos = data.eventos.map(e => e.id_evento.toString());
                
                // Verificar si hay cambios
                const hayNuevos = eventosNuevos.some(id => !eventosActuales.includes(id));
                const hayEliminados = eventosActuales.some(id => id && !eventosNuevos.includes(id));
                
                if (hayNuevos || hayEliminados) {
                    // Reconstruir el selector
                    selectEvento.innerHTML = '<option value="">Seleccionar evento...</option>';
                    
                    data.eventos.forEach(evento => {
                        const option = document.createElement('option');
                        option.value = evento.id_evento;
                        option.textContent = `${evento.titulo} • ${evento.tipo == 1 ? 'Teatro 420' : 'Pasarela 540'}`;
                        if (evento.id_evento.toString() === eventoActual) {
                            option.selected = true;
                        }
                        selectEvento.appendChild(option);
                    });
                    
                    // Mostrar notificación si hay eventos nuevos
                    if (hayNuevos && typeof notify !== 'undefined') {
                        notify.success('Nuevos eventos disponibles');
                    }
                }
            }
        } catch (error) {
            console.error('Error al actualizar eventos:', error);
        }
    }
    
    // Escuchar cambios en localStorage (cuando se crea un evento desde otra pestaña)
    window.addEventListener('storage', (e) => {
        if (e.key === 'evt_upd') {
            console.log('Evento creado detectado, actualizando selector...');
            actualizarSelectorEventos();
        }
    });
    
    // Polling cada 30 segundos para detectar cambios (por si acaso)
    setInterval(actualizarSelectorEventos, 30000);
</script>

<script src="js/notifications.js"></script>
<script src="js/carrito.js?v=5"></script>
<script src="js/escaner_qr.js"></script>
<script src="js/menu-mejoras.js?v=1"></script>
<script src="js/seleccion-multiple.js?v=1"></script>

</body>
</html>