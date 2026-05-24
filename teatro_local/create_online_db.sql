-- Crear base de datos online para teatro_online
-- =========================================

CREATE DATABASE IF NOT EXISTS trt_25_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE trt_25_online;

-- Tabla de usuarios (para login online)
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('cliente', 'admin') DEFAULT 'cliente',
    activo TINYINT(1) DEFAULT 1,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de eventos (con imagen como BLOB)
CREATE TABLE IF NOT EXISTS evento (
    id_evento INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    tipo TINYINT(1) DEFAULT 1 COMMENT '1=Teatro 420, 2=Pasarela 540',
    imagen LONGBLOB COMMENT 'Imagen del evento almacenada como BLOB',
    imagen_mime VARCHAR(50) COMMENT 'Tipo MIME de la imagen',
    mapa_json TEXT COMMENT 'Mapa de asientos en JSON',
    finalizado TINYINT(1) DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    id_evento_local INT COMMENT 'ID del evento en la base de datos local',
    INDEX idx_finalizado (finalizado),
    INDEX idx_id_local (id_evento_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de funciones
CREATE TABLE IF NOT EXISTS funciones (
    id_funcion INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    fecha_hora DATETIME NOT NULL,
    estado TINYINT(1) DEFAULT 0 COMMENT '0=disponible, 1=vencida',
    id_funcion_local INT COMMENT 'ID de la función en la base de datos local',
    FOREIGN KEY (id_evento) REFERENCES evento(id_evento) ON DELETE CASCADE,
    INDEX idx_evento (id_evento),
    INDEX idx_fecha (fecha_hora),
    INDEX idx_id_local (id_funcion_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de categorías
CREATE TABLE IF NOT EXISTS categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    nombre_categoria VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    color VARCHAR(50) DEFAULT '#0066ff',
    id_categoria_local INT COMMENT 'ID de la categoría en la base de datos local',
    FOREIGN KEY (id_evento) REFERENCES evento(id_evento) ON DELETE CASCADE,
    INDEX idx_evento (id_evento),
    INDEX idx_id_local (id_categoria_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de asientos
CREATE TABLE IF NOT EXISTS asientos (
    id_asiento INT AUTO_INCREMENT PRIMARY KEY,
    codigo_asiento VARCHAR(20) NOT NULL UNIQUE,
    fila VARCHAR(10),
    numero INT,
    id_asiento_local INT COMMENT 'ID del asiento en la base de datos local',
    INDEX idx_codigo (codigo_asiento),
    INDEX idx_id_local (id_asiento_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de boletos (con QR como BLOB)
CREATE TABLE IF NOT EXISTS boletos (
    id_boleto INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    id_funcion INT,
    id_asiento INT NOT NULL,
    id_categoria INT NOT NULL,
    codigo_unico VARCHAR(50) NOT NULL UNIQUE,
    qr_code LONGBLOB COMMENT 'Código QR almacenado como BLOB',
    qr_mime VARCHAR(50) DEFAULT 'image/png',
    precio_base DECIMAL(10,2) NOT NULL,
    precio_final DECIMAL(10,2) NOT NULL,
    tipo_boleto ENUM('adulto', 'nino', 'inapam', 'estudiante') DEFAULT 'adulto',
    fecha_compra DATETIME DEFAULT CURRENT_TIMESTAMP,
    estatus TINYINT(1) DEFAULT 1 COMMENT '0=cancelado, 1=vendido, 2=usado',
    id_boleto_local INT COMMENT 'ID del boleto en la base de datos local',
    FOREIGN KEY (id_evento) REFERENCES evento(id_evento),
    FOREIGN KEY (id_asiento) REFERENCES asientos(id_asiento),
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria),
    INDEX idx_evento (id_evento),
    INDEX idx_funcion (id_funcion),
    INDEX idx_codigo (codigo_unico),
    INDEX idx_id_local (id_boleto_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de cambios_log para sincronización
CREATE TABLE IF NOT EXISTS cambios_log (
    id_cambio INT AUTO_INCREMENT PRIMARY KEY,
    tipo_cambio ENUM('venta', 'cancelacion', 'evento', 'categoria', 'descuento', 'mapa', 'funcion', 'precio') NOT NULL,
    id_evento INT NULL,
    id_funcion INT NULL,
    datos JSON NULL,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    procesado TINYINT(1) DEFAULT 0,
    INDEX idx_fecha (fecha_cambio),
    INDEX idx_tipo (tipo_cambio),
    INDEX idx_evento (id_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de transacciones
CREATE TABLE IF NOT EXISTS transacciones (
    id_transaccion INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    id_funcion INT,
    cantidad INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(50),
    fecha_transaccion DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_transaccion_local INT COMMENT 'ID de la transacción en la base de datos local',
    FOREIGN KEY (id_evento) REFERENCES evento(id_evento),
    INDEX idx_evento (id_evento),
    INDEX idx_fecha (fecha_transaccion),
    INDEX idx_id_local (id_transaccion_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de configuración
CREATE TABLE IF NOT EXISTS configuracion (
    id_config INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuración inicial
INSERT INTO configuracion (clave, valor) VALUES 
('ultima_sincronizacion', NULL),
('version', '1.0')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);
