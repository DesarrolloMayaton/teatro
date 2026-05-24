<?php
/**
 * Teatro Online - Compra de Boletos en Línea
 * ==========================================
 * Interfaz para clientes que compran boletos desde internet
 * Mismo diseño que vnt_interfaz
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teatro Online - Compra de Boletos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/teatro-style.css">
    <link rel="stylesheet" href="assets/css/seat-map.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/carrito.css">
</head>
<body>
    <div class="teatro-container">
        <!-- Header -->
        <div class="teatro-header">
            <h1><i class="bi bi-ticket-perforated"></i> Teatro Online</h1>
            <p>Compra tus boletos desde cualquier lugar</p>
        </div>

        <!-- Paso 1: Selección de Evento -->
        <div id="paso-evento" class="paso-activo">
            <div class="teatro-card">
                <div class="teatro-card-header">
                    <div class="teatro-card-icon"><i class="bi bi-calendar-event"></i></div>
                    <h3 class="teatro-card-title">1. Selecciona un Evento</h3>
                </div>
                <div class="card-body">
                    <div id="eventos-container" class="row g-3">
                        <div class="col-12 text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Cargando eventos...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paso 2: Selección de Función -->
        <div id="paso-funcion" class="paso-inactivo">
            <div class="teatro-card">
                <div class="teatro-card-header">
                    <div class="teatro-card-icon"><i class="bi bi-clock"></i></div>
                    <h3 class="teatro-card-title">2. Selecciona una Función</h3>
                </div>
                <div class="card-body">
                    <button onclick="volverEventos()" class="teatro-btn teatro-btn-secondary mb-3">
                        <i class="bi bi-arrow-left"></i> Volver a eventos
                    </button>
                    <div id="funciones-container" class="row g-3">
                        <div class="col-12 text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Cargando funciones...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paso 3: Selección de Asientos -->
        <div id="paso-asientos" class="paso-inactivo">
            <div class="row">
                <!-- Mapa de Asientos -->
                <div class="col-lg-8">
                    <div class="teatro-card">
                        <div class="teatro-card-header">
                            <div class="teatro-card-icon"><i class="bi bi-grid-3x3"></i></div>
                            <h3 class="teatro-card-title">3. Selecciona tus Asientos</h3>
                        </div>
                        <div class="card-body">
                            <button onclick="volverFunciones()" class="teatro-btn teatro-btn-secondary mb-3">
                                <i class="bi bi-arrow-left"></i> Volver a funciones
                            </button>
                            
                            <div class="seat-map-wrapper">
                                <div class="seat-map-content">
                                    <div class="screen">ESCENARIO</div>
                                    <div id="asientos-grid" class="asientos-grid">
                                        <!-- Asientos se generan dinámicamente -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Leyenda -->
                            <div class="mt-3 d-flex gap-3 flex-wrap">
                                <div class="d-flex align-items-center">
                                    <div class="seat-legend" style="background: #0066ff; width: 30px; height: 30px; border-radius: 8px;"></div>
                                    <span class="ms-2 text-muted">Disponible</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="seat-legend" style="background: #32d74b; width: 30px; height: 30px; border-radius: 8px;"></div>
                                    <span class="ms-2 text-muted">Seleccionado</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="seat-legend" style="background: #2b2b2b; width: 30px; height: 30px; border-radius: 8px;"></div>
                                    <span class="ms-2 text-muted">Vendido</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Carrito -->
                <div class="col-lg-4">
                    <div class="teatro-card controls-panel">
                        <div class="teatro-card-header">
                            <div class="teatro-card-icon"><i class="bi bi-cart"></i></div>
                            <h3 class="teatro-card-title">Carrito</h3>
                        </div>
                        <div class="card-body">
                            <div id="carrito-items">
                                <div class="carrito-vacio">
                                    No hay asientos seleccionados
                                </div>
                            </div>
                            <div class="total-section">
                                <h4>Total: <span id="total-precio">$0.00</span></h4>
                            </div>
                            <button id="btn-continuar-datos" class="teatro-btn teatro-btn-success w-100" disabled>
                                <i class="bi bi-arrow-right"></i> Continuar a Datos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paso 4: Datos del Cliente -->
        <div id="paso-datos" class="paso-inactivo">
            <div class="teatro-card">
                <div class="teatro-card-header">
                    <div class="teatro-card-icon"><i class="bi bi-person"></i></div>
                    <h3 class="teatro-card-title">4. Tus Datos</h3>
                </div>
                <div class="card-body">
                    <button onclick="volverAsientos()" class="teatro-btn teatro-btn-secondary mb-3">
                        <i class="bi bi-arrow-left"></i> Volver a asientos
                    </button>
                    
                    <form id="form-datos-cliente">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="teatro-label">Nombre Completo *</label>
                                <input type="text" class="teatro-input" id="nombre_cliente" required>
                            </div>
                            <div class="col-md-6">
                                <label class="teatro-label">Email *</label>
                                <input type="email" class="teatro-input" id="email_cliente" required>
                            </div>
                            <div class="col-md-6">
                                <label class="teatro-label">Teléfono</label>
                                <input type="tel" class="teatro-input" id="telefono_cliente">
                            </div>
                        </div>
                        
                        <div class="teatro-card mt-3">
                            <div class="card-body">
                                <h6 class="mb-3">Resumen de Compra</h6>
                                <div id="resumen-compra"></div>
                                <hr class="border-subtle">
                                <div class="d-flex justify-content-between">
                                    <strong>Total a Pagar:</strong>
                                    <strong id="total-pagar" class="text-success">$0.00</strong>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="teatro-btn teatro-btn-primary w-100 mt-3">
                            <i class="bi bi-credit-card"></i> Completar Compra
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Paso 5: Confirmación -->
        <div id="paso-confirmacion" class="paso-inactivo">
            <div class="teatro-card">
                <div class="card-body text-center py-5">
                    <div class="teatro-card-icon mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2.5rem;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h3 class="teatro-card-title text-center">¡Compra Exitosa!</h3>
                    <p id="confirmacion-mensaje" class="text-muted mt-3"></p>
                    <div id="boletos-generados" class="mt-4"></div>
                    <button onclick="location.reload()" class="teatro-btn teatro-btn-primary mt-4">
                        <i class="bi bi-plus-circle"></i> Nueva Compra
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Carga -->
    <div id="modal-carga" class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 mb-0 text-muted" id="mensaje-carga">Procesando...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
