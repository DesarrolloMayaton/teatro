<?php
require_once __DIR__ . '/conexion.php';

// Crear tabla transacciones si no existe
$conn->query("CREATE TABLE IF NOT EXISTS transacciones (
    id_transaccion INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT(11) NOT NULL,
    accion VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_fecha (id_usuario, fecha_hora),
    CONSTRAINT fk_transacciones_usuarios FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

function registrar_transaccion($accion, $descripcion = null) {
    if (!isset($_SESSION['usuario_id'])) {
        return;
    }

    $id_usuario = (int)$_SESSION['usuario_id'];

    global $conn;

    $stmt = $conn->prepare("INSERT INTO transacciones (id_usuario, accion, descripcion) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $id_usuario, $accion, $descripcion);
    $stmt->execute();
    $stmt->close();
}
