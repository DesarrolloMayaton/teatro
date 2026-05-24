<?php
/**
 * API de Compra - Teatro Online
 * ==============================
 * Procesa compra de boletos en línea
 * Comparte lógica con vnt_interfaz/procesar_compra.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once __DIR__ . '/../config/database.php';

if (!$conn_online || $conn_online->connect_error) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos'
    ]);
    exit;
}

// Leer datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['id_evento']) || !isset($data['asientos'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Datos incompletos'
    ]);
    exit;
}

$id_evento = (int)$data['id_evento'];
$id_funcion = isset($data['id_funcion']) ? (int)$data['id_funcion'] : 0;
$asientos = $data['asientos'];
$nombre_cliente = isset($data['nombre_cliente']) ? $data['nombre_cliente'] : 'Cliente Online';
$email_cliente = isset($data['email_cliente']) ? $data['email_cliente'] : '';

if (empty($asientos)) {
    echo json_encode([
        'success' => false,
        'message' => 'No hay asientos seleccionados'
    ]);
    exit;
}

$conn_online->begin_transaction();

try {
    $boletos_generados = [];
    
    foreach ($asientos as $asiento_data) {
        $codigo_asiento = $asiento_data['asiento'];
        $categoria_id = (int)$asiento_data['categoriaId'];
        $precio = (float)$asiento_data['precio'];
        $precio_final = isset($asiento_data['precio_final']) ? (float)$asiento_data['precio_final'] : $precio;
        $tipo_boleto = isset($asiento_data['tipo_boleto']) ? $asiento_data['tipo_boleto'] : 'adulto';
        
        // Validar categoría
        $stmt = $conn_online->prepare("SELECT id_categoria FROM categorias WHERE id_categoria = ? AND id_evento = ?");
        $stmt->bind_param("ii", $categoria_id, $id_evento);
        $stmt->execute();
        $result_cat = $stmt->get_result();
        
        if ($result_cat->num_rows === 0) {
            // Buscar categoría por defecto
            $stmt->close();
            $stmt = $conn_online->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? AND LOWER(nombre_categoria) = 'general' LIMIT 1");
            $stmt->bind_param("i", $id_evento);
            $stmt->execute();
            $result_cat = $stmt->get_result();
            
            if ($result_cat->num_rows > 0) {
                $row_cat = $result_cat->fetch_assoc();
                $categoria_id = (int)$row_cat['id_categoria'];
                $stmt->close();
            } else {
                $stmt->close();
                $stmt = $conn_online->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? ORDER BY precio ASC LIMIT 1");
                $stmt->bind_param("i", $id_evento);
                $stmt->execute();
                $result_cat = $stmt->get_result();
                
                if ($result_cat->num_rows > 0) {
                    $row_cat = $result_cat->fetch_assoc();
                    $categoria_id = (int)$row_cat['id_categoria'];
                    $stmt->close();
                } else {
                    throw new Exception("El evento no tiene categorías configuradas");
                }
            }
        } else {
            $stmt->close();
        }
        
        // Obtener o crear asiento
        $stmt = $conn_online->prepare("SELECT id_asiento FROM asientos WHERE codigo_asiento = ?");
        $stmt->bind_param("s", $codigo_asiento);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            preg_match('/^([A-Z]+\d*)[-]?(\d+)$/', $codigo_asiento, $matches);
            $fila = isset($matches[1]) ? $matches[1] : substr($codigo_asiento, 0, 1);
            $numero = isset($matches[2]) ? (int)$matches[2] : (int)filter_var($codigo_asiento, FILTER_SANITIZE_NUMBER_INT);
            
            $stmt = $conn_online->prepare("INSERT INTO asientos (codigo_asiento, fila, numero) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $codigo_asiento, $fila, $numero);
            if (!$stmt->execute()) {
                throw new Exception("Error al crear asiento $codigo_asiento: " . $stmt->error);
            }
            $id_asiento = $conn_online->insert_id;
            $stmt->close();
        } else {
            $row = $result->fetch_assoc();
            $id_asiento = $row['id_asiento'];
            $stmt->close();
        }
        
        // Verificar si existe boleto
        $sql = "SELECT id_boleto, estatus FROM boletos WHERE id_evento = ? AND id_asiento = ?";
        $params = [$id_evento, $id_asiento];
        $types = "ii";
        
        if ($id_funcion > 0) {
            $sql .= " AND id_funcion = ?";
            $params[] = $id_funcion;
            $types .= "i";
        } else {
            $sql .= " AND (id_funcion IS NULL OR id_funcion = 0)";
        }
        
        $stmt = $conn_online->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $boleto_existente = null;
        if ($result->num_rows > 0) {
            $boleto_existente = $result->fetch_assoc();
        }
        $stmt->close();
        
        if ($boleto_existente && $boleto_existente['estatus'] == 1) {
            throw new Exception("El asiento $codigo_asiento ya está vendido");
        }
        
        // Generar código único
        $codigo_unico = strtoupper(bin2hex(random_bytes(8)));
        
        // Generar código QR (si la librería está disponible)
        $qr_blob = null;
        if (class_exists('Endroid\\QrCode\\QrCode')) {
            try {
                $qrCode = new QrCode($codigo_unico);
                $writer = new PngWriter();
                $resultQr = $writer->write($qrCode);
                $qr_blob = $resultQr->getString();
            } catch (Throwable $eqr) {
                $qr_blob = null;
            }
        }
        
        // Insertar o actualizar boleto
        if ($boleto_existente) {
            $stmt = $conn_online->prepare("
                UPDATE boletos SET
                    id_funcion = ?,
                    id_categoria = ?,
                    codigo_unico = ?,
                    qr_code = ?,
                    precio_base = ?,
                    precio_final = ?,
                    tipo_boleto = ?,
                    fecha_compra = NOW(),
                    estatus = 1
                WHERE id_boleto = ?
            ");
            $stmt->bind_param("iisbddsii", $id_funcion, $categoria_id, $codigo_unico, $qr_blob, $precio, $precio_final, $tipo_boleto, $boleto_existente['id_boleto']);
        } else {
            $stmt = $conn_online->prepare("
                INSERT INTO boletos (
                    id_evento, id_funcion, id_asiento, id_categoria,
                    codigo_unico, qr_code, precio_base, precio_final, tipo_boleto,
                    fecha_compra, estatus
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
            ");
            $stmt->bind_param("iiiisbdds", $id_evento, $id_funcion, $id_asiento, $categoria_id, $codigo_unico, $qr_blob, $precio, $precio_final, $tipo_boleto);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Error al procesar boleto: " . $stmt->error);
        }
        $stmt->close();
        
        $boletos_generados[] = [
            'asiento' => $codigo_asiento,
            'codigo_unico' => $codigo_unico,
            'precio' => $precio_final,
            'tipo_boleto' => $tipo_boleto
        ];
    }
    
    // Registrar cambio para sincronización
    $cambios_log_exists = $conn_online->query("SHOW TABLES LIKE 'cambios_log'");
    if ($cambios_log_exists && $cambios_log_exists->num_rows > 0) {
        $datos_json = json_encode([
            'asientos' => array_column($boletos_generados, 'asiento'),
            'cantidad' => count($boletos_generados),
            'origen' => 'online',
            'cliente' => $nombre_cliente
        ]);
        
        $conn_online->query("
            INSERT INTO cambios_log (tipo_cambio, id_evento, id_funcion, datos)
            VALUES ('venta', $id_evento, " . ($id_funcion > 0 ? $id_funcion : 'NULL') . ", '$datos_json')
        ");
    }
    
    $conn_online->commit();

    // ========================================================================
    // REPLICAR LA VENTA A LA BASE DE DATOS LOCAL (trt_25) Y AL BACKUP
    // ========================================================================
    // Esto garantiza que cuando alguien compra ONLINE, los boletos también
    // aparezcan vendidos en el sistema LOCAL (vnt_interfaz) en tiempo real.
    $boletos_locales_ids = [];
    try {
        $conn_local = @new mysqli('localhost', 'root', '', 'trt_25');
        if (!$conn_local->connect_error) {
            $conn_local->set_charset('utf8mb4');

            // Obtener id_evento_local desde id_evento online
            $stmt = $conn_online->prepare("SELECT id_evento_local FROM evento WHERE id_evento = ? LIMIT 1");
            $stmt->bind_param('i', $id_evento);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $id_evento_local = $row && $row['id_evento_local'] ? (int)$row['id_evento_local'] : null;

            $id_funcion_local = null;
            if ($id_funcion) {
                $stmt = $conn_online->prepare("SELECT id_funcion_local FROM funciones WHERE id_funcion = ? LIMIT 1");
                $stmt->bind_param('i', $id_funcion);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $id_funcion_local = $row && $row['id_funcion_local'] ? (int)$row['id_funcion_local'] : null;
            }

            if ($id_evento_local) {
                foreach ($boletos_generados as $bg) {
                    // Asegurar asiento en local
                    $st = $conn_local->prepare("SELECT id_asiento FROM asientos WHERE codigo_asiento = ? LIMIT 1");
                    $st->bind_param('s', $bg['asiento']);
                    $st->execute();
                    $row = $st->get_result()->fetch_assoc();
                    $st->close();
                    if (!$row) {
                        $st = $conn_local->prepare("INSERT INTO asientos (codigo_asiento, id_evento, id_categoria) VALUES (?, ?, 1)");
                        // Algunas BD locales tienen schemas distintos; intentar versión mínima
                        @$st->bind_param('si', $bg['asiento'], $id_evento_local);
                        @$st->execute();
                        $id_asiento_local = $st->insert_id;
                        $st->close();
                    } else {
                        $id_asiento_local = (int)$row['id_asiento'];
                    }

                    // Categoría local: tomar la primera del evento
                    $st = $conn_local->prepare("SELECT id_categoria FROM categorias WHERE id_evento = ? LIMIT 1");
                    $st->bind_param('i', $id_evento_local);
                    $st->execute();
                    $rcat = $st->get_result()->fetch_assoc();
                    $st->close();
                    $id_cat_local = $rcat ? (int)$rcat['id_categoria'] : 1;

                    // Insertar boleto en local
                    $st = $conn_local->prepare("
                        INSERT INTO boletos (id_evento, id_funcion, id_asiento, id_categoria, codigo_unico, precio_base, precio_final, tipo_boleto, fecha_compra, estatus)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'adulto', NOW(), 1)
                    ");
                    $precio_b = (float)$bg['precio'];
                    $precio_f = (float)$bg['precio'];
                    $st->bind_param('iiiisdd',
                        $id_evento_local, $id_funcion_local, $id_asiento_local, $id_cat_local,
                        $bg['codigo_unico'], $precio_b, $precio_f
                    );
                    if (@$st->execute()) {
                        $boletos_locales_ids[] = $st->insert_id;
                    }
                    $st->close();
                }

                // Registrar cambio en cambios_log local para que vnt_interfaz lo detecte en tiempo real
                $datos_json = $conn_local->real_escape_string(json_encode([
                    'asientos' => array_column($boletos_generados, 'asiento'),
                    'cantidad' => count($boletos_generados),
                    'origen' => 'online',
                    'cliente' => $nombre_cliente,
                ]));
                $idf_sql = $id_funcion_local ? (int)$id_funcion_local : 'NULL';
                @$conn_local->query("
                    INSERT INTO cambios_log (tipo_cambio, id_evento, id_funcion, datos)
                    VALUES ('venta', $id_evento_local, $idf_sql, '$datos_json')
                ");

                // ===== BACKUP INMUTABLE =====
                if (file_exists(__DIR__ . '/../../teatro_local/sync/backup_helper.php')) {
                    require_once __DIR__ . '/../../teatro_local/sync/backup_helper.php';
                    @respaldarEvento($conn_local, $id_evento_local, 'online');
                    foreach ($boletos_locales_ids as $idb) {
                        @respaldarBoleto($conn_local, $idb, 'online');
                    }
                    $total_venta = array_sum(array_column($boletos_generados, 'precio'));
                    @registrarVentaDetallada([
                        'origen' => 'online',
                        'id_evento' => $id_evento_local,
                        'id_funcion' => $id_funcion_local,
                        'cliente_nombre' => $nombre_cliente,
                        'cliente_email' => $data['email_cliente'] ?? null,
                        'cliente_telefono' => $data['telefono_cliente'] ?? null,
                        'lugar_venta' => 'Online',
                        'metodo_pago' => 'online',
                        'cantidad_boletos' => count($boletos_generados),
                        'subtotal' => (float)$total_venta,
                        'total' => (float)$total_venta,
                        'asientos' => array_column($boletos_generados, 'asiento'),
                        'codigos_boletos' => array_column($boletos_generados, 'codigo_unico'),
                        'boletos_ids' => $boletos_locales_ids,
                    ]);
                }
            }
            $conn_local->close();
        }
    } catch (Throwable $eSync) {
        error_log('[OnlineSync→Local] ' . $eSync->getMessage());
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Compra procesada exitosamente',
        'boletos' => $boletos_generados
    ]);
    
} catch (Exception $e) {
    $conn_online->rollback();
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn_online->close();
ob_end_flush();
?>
