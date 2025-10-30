<?php
/**
 * ARCHIVO DE CONFIGURACIÓN - SISTEMA DE BOLETOS
 * 
 * Copia este archivo como 'config.php' y personaliza los valores
 * según tus necesidades.
 */

// ============================================
// CONFIGURACIÓN DE PRECIOS
// ============================================

// Precio base por boleto (puede ser sobrescrito por categorías)
define('PRECIO_BASE_BOLETO', 150.00);

// ============================================
// CONFIGURACIÓN DE QR
// ============================================

// Tamaño del código QR en píxeles
define('QR_SIZE', 300);

// Margen del código QR
define('QR_MARGIN', 10);

// Prefijo para códigos únicos
define('CODIGO_PREFIJO', 'TRT-');

// ============================================
// CONFIGURACIÓN DE PDF
// ============================================

// Tamaño de papel para PDFs
define('PDF_PAPER_SIZE', 'A4');

// Orientación del PDF (portrait o landscape)
define('PDF_ORIENTATION', 'portrait');

// Nombre del teatro (aparece en el PDF)
define('NOMBRE_TEATRO', 'MI TEATRO');

// ============================================
// CONFIGURACIÓN DE RUTAS
// ============================================

// Ruta donde se guardan los códigos QR
define('QR_DIRECTORY', __DIR__ . '/qr_codes/');

// Ruta relativa para URLs
define('QR_URL_PATH', 'vnt_interfaz/qr_codes/');

// ============================================
// CONFIGURACIÓN DE SEGURIDAD
// ============================================

// Tiempo de expiración de sesión (en segundos)
define('SESSION_TIMEOUT', 3600); // 1 hora

// Habilitar modo debug (mostrar errores)
define('DEBUG_MODE', true);

// ============================================
// CONFIGURACIÓN DE INTERFAZ
// ============================================

// Número máximo de asientos que se pueden seleccionar
define('MAX_ASIENTOS_SELECCION', 10);

// Mostrar precios en la interfaz
define('MOSTRAR_PRECIOS', true);

// Idioma del sistema
define('IDIOMA', 'es');

// ============================================
// MENSAJES PERSONALIZABLES
// ============================================

$MENSAJES = [
    'compra_exitosa' => '¡Compra realizada exitosamente!',
    'boleto_valido' => 'Boleto válido - Acceso permitido',
    'boleto_usado' => 'Este boleto ya fue utilizado anteriormente',
    'boleto_no_encontrado' => 'Boleto no encontrado en el sistema',
    'error_compra' => 'Error al procesar la compra. Intenta nuevamente.',
    'selecciona_asientos' => 'Por favor, selecciona al menos un asiento',
    'asiento_no_disponible' => 'Uno o más asientos ya no están disponibles',
];

// ============================================
// CONFIGURACIÓN DE EMAIL (OPCIONAL)
// ============================================

// Habilitar envío de emails con boletos
define('EMAIL_ENABLED', false);

// Configuración SMTP (si EMAIL_ENABLED = true)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu-email@gmail.com');
define('SMTP_PASS', 'tu-contraseña');
define('EMAIL_FROM', 'noreply@teatro.com');
define('EMAIL_FROM_NAME', 'Sistema de Boletos');

// ============================================
// FUNCIONES AUXILIARES
// ============================================

/**
 * Obtener mensaje personalizado
 */
function obtenerMensaje($clave) {
    global $MENSAJES;
    return isset($MENSAJES[$clave]) ? $MENSAJES[$clave] : '';
}

/**
 * Formatear precio
 */
function formatearPrecio($precio) {
    return '$' . number_format($precio, 2) . ' MXN';
}

/**
 * Generar código único para boleto
 */
function generarCodigoUnico() {
    return CODIGO_PREFIJO . strtoupper(uniqid()) . '-' . time();
}

/**
 * Validar código de boleto
 */
function validarCodigoBoleto($codigo) {
    return preg_match('/^' . CODIGO_PREFIJO . '[A-Z0-9]+-\d+$/', $codigo);
}

// ============================================
// NOTAS DE USO
// ============================================

/*
 * Para usar este archivo:
 * 
 * 1. Copia este archivo como 'config.php'
 * 2. Personaliza los valores según tus necesidades
 * 3. Incluye el archivo en tus scripts PHP:
 *    require_once 'config.php';
 * 
 * Ejemplo de uso:
 * 
 * $precio = PRECIO_BASE_BOLETO;
 * $mensaje = obtenerMensaje('compra_exitosa');
 * $codigo = generarCodigoUnico();
 */
