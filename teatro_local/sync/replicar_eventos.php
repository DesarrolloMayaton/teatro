<?php
/**
 * Script de Replicación de Eventos - Local → Online
 * =================================================
 * Copia eventos, funciones, categorías, asientos y boletos
 * con sus imágenes y códigos QR a la base de datos online
 */

require_once '../config/database.php';

// Conexión a base de datos local
$conn_local = getLocalConnection();
if (!$conn_local) {
    die("Error: No se pudo conectar a la base de datos local");
}

// Conexión a base de datos online
$conn_online = new mysqli('localhost', 'root', '', 'trt_25_online');
if ($conn_online->connect_error) {
    die("Error: No se pudo conectar a la base de datos online: " . $conn_online->connect_error);
}

$conn_online->set_charset("utf8mb4");

echo "<h1>Replicación de Eventos - Local → Online</h1>";
echo "<p>Este script copia eventos con imágenes y QR a la base de datos online.</p>";

// Obtener última fecha de sincronización
$result = $conn_online->query("SELECT valor FROM configuracion WHERE clave = 'ultima_sincronizacion'");
$ultima_sync = null;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ultima_sync = $row['valor'];
}

echo "<p><strong>Última sincronización:</strong> " . ($ultima_sync ?: 'Nunca') . "</p>";

// Obtener eventos nuevos o modificados
$sql = "SELECT id_evento, titulo, descripcion, tipo, imagen, mapa_json, finalizado, fecha_creacion, fecha_actualizacion 
        FROM evento 
        WHERE finalizado = 0";

if ($ultima_sync) {
    $sql .= " AND fecha_actualizacion > '$ultima_sync'";
}

$sql .= " ORDER BY fecha_actualizacion DESC";

$result = $conn_local->query($sql);
$eventos = [];

while ($row = $result->fetch_assoc()) {
    $eventos[] = $row;
}

echo "<h2>Eventos a replicar: " . count($eventos) . "</h2>";

if (empty($eventos)) {
    echo "<p>No hay eventos nuevos o modificados para replicar.</p>";
    $conn_local->close();
    $conn_online->close();
    exit;
}

// Replicar cada evento
foreach ($eventos as $evento) {
    echo "<h3>Procesando evento: " . htmlspecialchars($evento['titulo']) . "</h3>";
    
    // Verificar si el evento ya existe en online
    $stmt = $conn_online->prepare("SELECT id_evento FROM evento WHERE id_evento_local = ?");
    $stmt->bind_param("i", $evento['id_evento']);
    $stmt->execute();
    $result_check = $stmt->get_result();
    
    if ($result_check->num_rows > 0) {
        // Actualizar evento existente
        $row = $result_check->fetch_assoc();
        $id_evento_online = $row['id_evento'];
        
        $stmt_update = $conn_online->prepare("
            UPDATE evento SET 
                titulo = ?, 
                descripcion = ?, 
                tipo = ?, 
                imagen = ?, 
                mapa_json = ?, 
                finalizado = ?,
                fecha_actualizacion = NOW()
            WHERE id_evento = ?
        ");
        $stmt_update->bind_param("sssisii", 
            $evento['titulo'], 
            $evento['descripcion'], 
            $evento['tipo'], 
            $evento['imagen'], 
            $evento['mapa_json'], 
            $evento['finalizado'],
            $id_evento_online
        );
        
        if ($stmt_update->execute()) {
            echo "<p style='color: green;'>✓ Evento actualizado en online</p>";
        } else {
            echo "<p style='color: red;'>✗ Error actualizando evento: " . $stmt_update->error . "</p>";
        }
        
        $stmt_update->close();
    } else {
        // Insertar nuevo evento
        $stmt_insert = $conn_online->prepare("
            INSERT INTO evento (titulo, descripcion, tipo, imagen, mapa_json, finalizado, id_evento_local)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_insert->bind_param("sssisii", 
            $evento['titulo'], 
            $evento['descripcion'], 
            $evento['tipo'], 
            $evento['imagen'], 
            $evento['mapa_json'], 
            $evento['finalizado'],
            $evento['id_evento']
        );
        
        if ($stmt_insert->execute()) {
            $id_evento_online = $conn_online->insert_id;
            echo "<p style='color: green;'>✓ Evento insertado en online (ID: $id_evento_online)</p>";
        } else {
            echo "<p style='color: red;'>✗ Error insertando evento: " . $stmt_insert->error . "</p>";
            $stmt_insert->close();
            continue;
        }
        
        $stmt_insert->close();
    }
    
    $stmt->close();
    
    // Replicar funciones
    replicarFunciones($conn_local, $conn_online, $evento['id_evento'], $id_evento_online);
    
    // Replicar categorías
    replicarCategorias($conn_local, $conn_online, $evento['id_evento'], $id_evento_online);
    
    // Replicar asientos
    replicarAsientos($conn_local, $conn_online);
    
    // Replicar boletos con QR
    replicarBoletos($conn_local, $conn_online, $evento['id_evento'], $id_evento_online);
}

// Actualizar fecha de última sincronización
$stmt = $conn_online->prepare("UPDATE configuracion SET valor = NOW() WHERE clave = 'ultima_sincronizacion'");
$stmt->execute();
$stmt->close();

echo "<h2 style='color: green;'>¡Replicación completada!</h2>";

$conn_local->close();
$conn_online->close();

// Funciones auxiliares
function replicarFunciones($conn_local, $conn_online, $id_evento_local, $id_evento_online) {
    echo "<h4>Replicando funciones...</h4>";
    
    $result = $conn_local->query("SELECT * FROM funciones WHERE id_evento = $id_evento_local");
    
    while ($row = $result->fetch_assoc()) {
        // Verificar si existe
        $stmt = $conn_online->prepare("SELECT id_funcion FROM funciones WHERE id_funcion_local = ?");
        $stmt->bind_param("i", $row['id_funcion']);
        $stmt->execute();
        $check = $stmt->get_result();
        
        if ($check->num_rows > 0) {
            // Actualizar
            $stmt_update = $conn_online->prepare("
                UPDATE funciones SET fecha_hora = ?, estado = ? WHERE id_funcion_local = ?
            ");
            $stmt_update->bind_param("sii", $row['fecha_hora'], $row['estado'], $row['id_funcion']);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            // Insertar
            $stmt_insert = $conn_online->prepare("
                INSERT INTO funciones (id_evento, fecha_hora, estado, id_funcion_local)
                VALUES (?, ?, ?, ?)
            ");
            $stmt_insert->bind_param("isii", $id_evento_online, $row['fecha_hora'], $row['estado'], $row['id_funcion']);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        
        $stmt->close();
    }
    
    echo "<p style='color: green;'>✓ Funciones replicadas</p>";
}

function replicarCategorias($conn_local, $conn_online, $id_evento_local, $id_evento_online) {
    echo "<h4>Replicando categorías...</h4>";
    
    $result = $conn_local->query("SELECT * FROM categorias WHERE id_evento = $id_evento_local");
    
    while ($row = $result->fetch_assoc()) {
        // Verificar si existe
        $stmt = $conn_online->prepare("SELECT id_categoria FROM categorias WHERE id_categoria_local = ?");
        $stmt->bind_param("i", $row['id_categoria']);
        $stmt->execute();
        $check = $stmt->get_result();
        
        if ($check->num_rows > 0) {
            // Actualizar
            $stmt_update = $conn_online->prepare("
                UPDATE categorias SET nombre_categoria = ?, precio = ?, color = ? WHERE id_categoria_local = ?
            ");
            $stmt_update->bind_param("sdsi", $row['nombre_categoria'], $row['precio'], $row['color'], $row['id_categoria']);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            // Insertar
            $stmt_insert = $conn_online->prepare("
                INSERT INTO categorias (id_evento, nombre_categoria, precio, color, id_categoria_local)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt_insert->bind_param("isdsi", $id_evento_online, $row['nombre_categoria'], $row['precio'], $row['color'], $row['id_categoria']);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        
        $stmt->close();
    }
    
    echo "<p style='color: green;'>✓ Categorías replicadas</p>";
}

function replicarAsientos($conn_local, $conn_online) {
    echo "<h4>Replicando asientos...</h4>";
    
    $result = $conn_local->query("SELECT * FROM asientos");
    
    while ($row = $result->fetch_assoc()) {
        // Verificar si existe
        $stmt = $conn_online->prepare("SELECT id_asiento FROM asientos WHERE id_asiento_local = ?");
        $stmt->bind_param("i", $row['id_asiento']);
        $stmt->execute();
        $check = $stmt->get_result();
        
        if ($check->num_rows === 0) {
            // Insertar
            $stmt_insert = $conn_online->prepare("
                INSERT INTO asientos (codigo_asiento, fila, numero, id_asiento_local)
                VALUES (?, ?, ?, ?)
            ");
            $stmt_insert->bind_param("ssii", $row['codigo_asiento'], $row['fila'], $row['numero'], $row['id_asiento']);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        
        $stmt->close();
    }
    
    echo "<p style='color: green;'>✓ Asientos replicados</p>";
}

function replicarBoletos($conn_local, $conn_online, $id_evento_local, $id_evento_online) {
    echo "<h4>Replicando boletos con QR...</h4>";
    
    $result = $conn_local->query("SELECT * FROM boletos WHERE id_evento = $id_evento_local AND estatus = 1");
    
    while ($row = $result->fetch_assoc()) {
        // Obtener IDs correspondientes en online
        $id_asiento_online = getIdAsientoOnline($conn_online, $row['id_asiento']);
        $id_categoria_online = getIdCategoriaOnline($conn_online, $row['id_categoria'], $id_evento_online);
        
        if (!$id_asiento_online || !$id_categoria_online) {
            continue;
        }
        
        // Verificar si existe
        $stmt = $conn_online->prepare("SELECT id_boleto FROM boletos WHERE id_boleto_local = ?");
        $stmt->bind_param("i", $row['id_boleto']);
        $stmt->execute();
        $check = $stmt->get_result();
        
        if ($check->num_rows > 0) {
            // Actualizar
            $stmt_update = $conn_online->prepare("
                UPDATE boletos SET 
                    qr_code = ?, 
                    estatus = ? 
                WHERE id_boleto_local = ?
            ");
            $stmt_update->bind_param("sii", $row['qr_code'], $row['estatus'], $row['id_boleto']);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            // Insertar
            $id_funcion_online = $row['id_funcion'] ? getIdFuncionOnline($conn_online, $row['id_funcion'], $id_evento_online) : null;
            
            $stmt_insert = $conn_online->prepare("
                INSERT INTO boletos (id_evento, id_funcion, id_asiento, id_categoria, codigo_unico, qr_code, precio_base, precio_final, tipo_boleto, estatus, id_boleto_local)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert->bind_param("iiisssdsii", 
                $id_evento_online, 
                $id_funcion_online,
                $id_asiento_online, 
                $id_categoria_online, 
                $row['codigo_unico'], 
                $row['qr_code'], 
                $row['precio_base'], 
                $row['precio_final'], 
                $row['tipo_boleto'], 
                $row['estatus'],
                $row['id_boleto']
            );
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        
        $stmt->close();
    }
    
    echo "<p style='color: green;'>✓ Boletos replicados</p>";
}

function getIdAsientoOnline($conn_online, $id_asiento_local) {
    $stmt = $conn_online->prepare("SELECT id_asiento FROM asientos WHERE id_asiento_local = ?");
    $stmt->bind_param("i", $id_asiento_local);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id_asiento'];
    }
    
    $stmt->close();
    return null;
}

function getIdCategoriaOnline($conn_online, $id_categoria_local, $id_evento_online) {
    $stmt = $conn_online->prepare("SELECT id_categoria FROM categorias WHERE id_categoria_local = ? AND id_evento = ?");
    $stmt->bind_param("ii", $id_categoria_local, $id_evento_online);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id_categoria'];
    }
    
    $stmt->close();
    return null;
}

function getIdFuncionOnline($conn_online, $id_funcion_local, $id_evento_online) {
    $stmt = $conn_online->prepare("SELECT id_funcion FROM funciones WHERE id_funcion_local = ? AND id_evento = ?");
    $stmt->bind_param("ii", $id_funcion_local, $id_evento_online);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id_funcion'];
    }
    
    $stmt->close();
    return null;
}
?>
