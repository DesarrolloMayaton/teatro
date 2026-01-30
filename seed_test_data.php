<?php
require_once 'evt_interfaz/conexion.php';

// Configuration
$num_eventos = 3;
$start_date = new DateTime();
$precios_base = [150, 300, 500, 800, 1200];
$categorias_nombres = ['General', 'Preferente', 'VIP', 'Estudiante', 'Palco'];

echo "Iniciando generación de datos de prueba...\n";

try {
    $conn->begin_transaction();

    // 1. Get or Create User for logs
    $id_usuario = 1; // Default admin
    $resUser = $conn->query("SELECT id_usuario FROM usuarios LIMIT 1");
    if($resUser && $rowUsr = $resUser->fetch_assoc()) {
        $id_usuario = $rowUsr['id_usuario'];
    }

    // 2. Create Categories if not exist (simplification: assume some exist or create dummies)
    $cat_ids = [];
    foreach($categorias_nombres as $cat_nombre) {
        $check = $conn->query("SELECT id_categoria FROM categorias WHERE nombre_categoria = '$cat_nombre'");
        if($check->num_rows > 0) {
            $cat_ids[] = $check->fetch_assoc()['id_categoria'];
        } else {
            $conn->query("INSERT INTO categorias (nombre_categoria) VALUES ('$cat_nombre')");
            $cat_ids[] = $conn->insert_id;
        }
    }

    for ($i = 1; $i <= $num_eventos; $i++) {
        // --- Create Event ---
        $titulo = "Evento Prueba #" . rand(1000, 9999);
        $desc = "Descripción generada automáticamente para pruebas de estadísticas.";
        $img = "uploads/dummy.jpg"; // Placeholder
        
        $stmtEv = $conn->prepare("INSERT INTO evento (titulo, descripcion, imagen_url, estado) VALUES (?, ?, ?, 'activo')");
        $stmtEv->bind_param("sss", $titulo, $desc, $img);
        $stmtEv->execute();
        $id_evento = $conn->insert_id;
        
        echo "Creado Evento: $titulo (ID: $id_evento)\n";

        // --- Create Functions (1-3) ---
        $num_funciones = rand(1, 3);
        $funciones_ids = [];
        
        for ($f = 0; $f < $num_funciones; $f++) {
            $fecha_funcion = clone $start_date;
            $fecha_funcion->modify("+" . rand(1, 30) . " days");
            $fecha_funcion->setTime(rand(18, 22), 0); // 18:00 - 22:00
            $fecha_str = $fecha_funcion->format('Y-m-d H:i:s');
            
            // Check if 'funcion' table structure matches standard (id_evento, fecha_hora, etc)
            // Assuming simplified structure or logic handled by code usually. 
            // Checking basic insert:
            $stmtFun = $conn->prepare("INSERT INTO funcion (id_evento, fecha, hora) VALUES (?, ?, ?)");
            $f_date = $fecha_funcion->format('Y-m-d');
            $f_time = $fecha_funcion->format('H:i:s');
            $stmtFun->bind_param("iss", $id_evento, $f_date, $f_time);
            $stmtFun->execute();
            $funciones_ids[] = $conn->insert_id;
        }

        // --- Simulate Sales ---
        $total_boletos = rand(20, 150);
        echo "  - Generando $total_boletos ventas...\n";

        for ($b = 0; $b < $total_boletos; $b++) {
            $id_funcion = $funciones_ids[array_rand($funciones_ids)];
            $id_categoria = $cat_ids[array_rand($cat_ids)];
            $precio = $precios_base[array_rand($precios_base)] + rand(-20, 20); // Dispersed price
            
            // Generate distinct sold dates for "Sales by Hour" chart
            $fecha_venta = clone $start_date;
            $fecha_venta->modify("-" . rand(0, 7) . " days"); // Sold in last week
            $fecha_venta->setTime(rand(9, 23), rand(0, 59));
            $fecha_venta_str = $fecha_venta->format('Y-m-d H:i:s');
            
            // 1. Insert Boleto
            $tipo_boleto = ['adulto', 'nino', 'inapam'][rand(0,2)];
            $asiento = "A-" . rand(1, 100);
            
            $stmtBol = $conn->prepare("INSERT INTO boletos (id_evento, id_funcion, id_categoria, precio_final, estatus, fecha_compra, tipo_boleto, asiento, nombre_cliente) VALUES (?, ?, ?, ?, 'vendido', ?, ?, ?, ?)");
            $cliente = "Cliente " . rand(1, 500);
            $stmtBol->bind_param("iiidssss", $id_evento, $id_funcion, $id_categoria, $precio, $fecha_venta_str, $tipo_boleto, $asiento, $cliente);
            $stmtBol->execute();
            
            // 2. Log Transaction (Optional but good for completeness)
            if ($b % 5 === 0) { // Log grouped transaction every 5 tickets to simulate cart
                 $stmtLog = $conn->prepare("INSERT INTO transacciones (id_usuario, accion, descripcion, datos_json, fecha_hora) VALUES (?, 'venta', ?, ?, ?)");
                 $desc_log = "Venta de boletos para $titulo";
                 $json = json_encode(['evento'=>$titulo, 'total'=>$precio, 'cantidad'=>1, 'cliente'=>$cliente]);
                 $stmtLog->bind_param("isss", $id_usuario, $desc_log, $json, $fecha_venta_str);
                 $stmtLog->execute();
            }
        }
    }

    $conn->commit();
    echo "¡Datos generados exitosamente!";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
?>
