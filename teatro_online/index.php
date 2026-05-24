<?php
/**
 * Teatro Online - Compra de Boletos en Línea
 * ==========================================
 * Interfaz intuitiva, contraste alto, botones grandes.
 * Replicada desde la base de datos local (trt_25_online).
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎭 Teatro Online - Compra tus Boletos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/online-style.css">
</head>
<body>

    <!-- HEADER -->
    <header class="online-header">
        <div class="online-header-content">
            <div class="online-logo">
                <div class="online-logo-icon">
                    <i class="bi bi-mask"></i>
                </div>
                <div class="online-logo-text">
                    <h1>Teatro Online</h1>
                    <p>Compra fácil y segura desde donde estés</p>
                </div>
            </div>
            <div class="online-badge">
                En vivo · Tiempo real
            </div>
        </div>
    </header>

    <!-- PROGRESS STEPS -->
    <div class="progress-container">
        <div class="progress-steps" id="progressSteps">
            <div class="progress-line-active" id="progressLine"></div>
            <div class="progress-step active" data-step="1">
                <div class="progress-step-circle">1</div>
                <div class="progress-step-label">Evento</div>
            </div>
            <div class="progress-step" data-step="2">
                <div class="progress-step-circle">2</div>
                <div class="progress-step-label">Función</div>
            </div>
            <div class="progress-step" data-step="3">
                <div class="progress-step-circle">3</div>
                <div class="progress-step-label">Asientos</div>
            </div>
            <div class="progress-step" data-step="4">
                <div class="progress-step-circle">4</div>
                <div class="progress-step-label">Datos</div>
            </div>
            <div class="progress-step" data-step="5">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">¡Listo!</div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTAINER -->
    <main class="main-container">

        <!-- PASO 1: SELECCIÓN DE EVENTO -->
        <section id="paso-evento" class="paso activo">
            <div class="paso-title">
                <h2>🎬 ¿Qué quieres ver?</h2>
                <p>Selecciona un evento para continuar</p>
            </div>
            <div id="eventos-grid" class="eventos-grid">
                <div class="loading-container" style="grid-column: 1/-1;">
                    <div class="loading-spinner"></div>
                    <p>Cargando eventos...</p>
                </div>
            </div>
        </section>

        <!-- PASO 2: SELECCIÓN DE FUNCIÓN -->
        <section id="paso-funcion" class="paso">
            <button class="btn-volver" onclick="irAPaso(1)">
                <i class="bi bi-arrow-left"></i> Cambiar evento
            </button>
            <div class="paso-title">
                <h2>📅 Elige el día y horario</h2>
                <p id="evento-titulo-funcion">—</p>
            </div>
            <div id="funciones-grid" class="funciones-grid">
                <div class="loading-container" style="grid-column: 1/-1;">
                    <div class="loading-spinner"></div>
                    <p>Cargando funciones...</p>
                </div>
            </div>
        </section>

        <!-- PASO 3: SELECCIÓN DE ASIENTOS -->
        <section id="paso-asientos" class="paso">
            <button class="btn-volver" onclick="irAPaso(2)">
                <i class="bi bi-arrow-left"></i> Cambiar función
            </button>
            <div class="paso-title">
                <h2>💺 Elige tus asientos</h2>
                <p>Toca los asientos disponibles para seleccionarlos</p>
            </div>

            <div class="mapa-layout">
                <!-- Mapa -->
                <div class="mapa-container">
                    <div class="mapa-toolbar">
                        <div class="leyenda">
                            <div class="leyenda-item">
                                <div class="leyenda-color" style="background:#1561f0;"></div>
                                <span>Disponible</span>
                            </div>
                            <div class="leyenda-item">
                                <div class="leyenda-color" style="background:#32d74b;"></div>
                                <span>Tu selección</span>
                            </div>
                            <div class="leyenda-item">
                                <div class="leyenda-color" style="background:#2b2b2b;border:1px solid #ff453a;"></div>
                                <span>Vendido</span>
                            </div>
                        </div>
                        <div class="zoom-controls">
                            <button class="zoom-btn" onclick="zoomMapa(-0.1)" title="Alejar">
                                <i class="bi bi-zoom-out"></i>
                            </button>
                            <div class="zoom-level" id="zoomLevel">100%</div>
                            <button class="zoom-btn" onclick="zoomMapa(0.1)" title="Acercar">
                                <i class="bi bi-zoom-in"></i>
                            </button>
                            <button class="zoom-btn" onclick="zoomMapa(0)" title="Restablecer">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mapa-scroll">
                        <div class="mapa-content" id="mapa-content">
                            <div class="escenario" id="escenario-label">ESCENARIO</div>
                            <div id="asientos-grid"></div>
                        </div>
                    </div>
                </div>

                <!-- Carrito Lateral -->
                <aside class="carrito-sidebar">
                    <div class="carrito-header">
                        <div class="carrito-icon">
                            <i class="bi bi-bag-check-fill"></i>
                        </div>
                        <div>
                            <h3>Tu Carrito</h3>
                            <span id="carrito-count">0 boletos</span>
                        </div>
                    </div>
                    <div class="carrito-body" id="carrito-body">
                        <div class="carrito-vacio-msg">
                            <i class="bi bi-cart"></i>
                            <p>Selecciona asientos para verlos aquí</p>
                        </div>
                    </div>
                    <div class="carrito-total">
                        <span>Total</span>
                        <strong id="carrito-total">$0.00</strong>
                    </div>
                    <button class="btn-continuar" id="btn-continuar" onclick="continuarADatos()" disabled>
                        Continuar <i class="bi bi-arrow-right"></i>
                    </button>
                </aside>
            </div>
        </section>

        <!-- PASO 4: DATOS DEL CLIENTE -->
        <section id="paso-datos" class="paso">
            <button class="btn-volver" onclick="irAPaso(3)">
                <i class="bi bi-arrow-left"></i> Volver a asientos
            </button>
            <div class="paso-title">
                <h2>📝 Tus datos</h2>
                <p>Necesitamos tu información para enviarte los boletos</p>
            </div>

            <div class="datos-container">
                <form id="form-datos" autocomplete="on">
                    <div class="form-group-online">
                        <label for="nombre_cliente">
                            <i class="bi bi-person-fill"></i> Nombre completo <span class="required">*</span>
                        </label>
                        <input type="text" id="nombre_cliente" name="nombre" required autocomplete="name" placeholder="Juan Pérez">
                    </div>

                    <div class="form-group-online">
                        <label for="email_cliente">
                            <i class="bi bi-envelope-fill"></i> Correo electrónico <span class="required">*</span>
                        </label>
                        <input type="email" id="email_cliente" name="email" required autocomplete="email" placeholder="tu@correo.com">
                    </div>

                    <div class="form-group-online">
                        <label for="telefono_cliente">
                            <i class="bi bi-phone-fill"></i> Teléfono (opcional)
                        </label>
                        <input type="tel" id="telefono_cliente" name="telefono" autocomplete="tel" placeholder="555 123 4567">
                    </div>

                    <div class="resumen-compra">
                        <h6>📋 Resumen de tu compra</h6>
                        <div id="resumen-detalle"></div>
                        <div class="resumen-total">
                            <span>Total a pagar</span>
                            <span class="total-amount" id="resumen-total">$0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-pagar">
                        <i class="bi bi-credit-card-fill"></i> Confirmar Compra
                    </button>
                </form>
            </div>
        </section>

        <!-- PASO 5: CONFIRMACIÓN -->
        <section id="paso-confirmacion" class="paso">
            <div class="confirmacion-container">
                <div class="confirmacion-icon">
                    <i class="bi bi-check-lg"></i>
                </div>
                <h2 class="confirmacion-titulo">¡Compra exitosa!</h2>
                <p class="confirmacion-mensaje" id="confirmacion-mensaje">
                    Tus boletos han sido generados.
                </p>
                <div class="boletos-lista" id="boletos-lista"></div>
                <button class="btn-pagar" onclick="location.reload()">
                    <i class="bi bi-plus-circle-fill"></i> Comprar otros boletos
                </button>
            </div>
        </section>

    </main>

    <!-- AYUDA FLOTANTE -->
    <div class="help-bubble" onclick="alert('Si necesitas ayuda escribe a soporte@teatro.com o llama al 555-1234')">
        <i class="bi bi-question-circle-fill"></i> ¿Necesitas ayuda?
    </div>

    <!-- MODAL DE CARGA -->
    <div class="modal-overlay" id="modal-carga">
        <div class="modal-content">
            <div class="loading-spinner" style="margin: 0 auto 16px;"></div>
            <p id="mensaje-carga" style="color:#a1a1a6;">Procesando...</p>
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
