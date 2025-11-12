-- Script para agregar campo de rol a la tabla usuarios
-- Ejecutar este script en phpMyAdmin o consola MySQL

-- Agregar columna rol a la tabla usuarios
ALTER TABLE usuarios 
ADD COLUMN rol ENUM('empleado', 'admin') NOT NULL DEFAULT 'empleado' AFTER password;

-- Agregar columna activo para habilitar/deshabilitar usuarios
ALTER TABLE usuarios 
ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER rol;

-- Agregar columna fecha_registro
ALTER TABLE usuarios 
ADD COLUMN fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER activo;

-- Crear un usuario administrador por defecto
-- Usuario: admin, Password: 123456
INSERT INTO usuarios (nombre, apellido, password, rol, activo) 
VALUES ('Administrador', 'Sistema', '123456', 'admin', 1);

-- Nota: En producción, se debe usar password_hash() en PHP para encriptar contraseñas
-- Este es solo un ejemplo de desarrollo
