<?php
// reactivarcopia.php
include "../conexion.php";

define('DB_MAIN', 'trt_25');
define('DB_HIST', 'trt_historico');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_evento'])) {
    $id_hist = intval($_POST['id_evento']);

    $conn->begin_transaction();
    try {
        // 1. LEER DATOS DEL EVENTO HISTÓRICO
        $res = $conn->query("SELECT * FROM " . DB_HIST . ".evento WHERE id_evento = $id_hist");
        $evt = $res->fetch_assoc();
        
        if (!$evt) {
            throw new Exception("Evento no encontrado en el historial.");
        }

        // 2. INSERTAR COMO NUEVO EN LA PRINCIPAL
        // Se establecen fechas de venta por defecto (hoy -> en 7 días) para que no nazca "vencido"
        // 'finalizado' se establece en 0 (activo)
        $sql_insert = "INSERT INTO " . DB_MAIN . ".evento 
                      (titulo, descripcion, imagen, tipo, mapa_json, inicio_venta, cierre_venta, finalizado) 
                      VALUES (?, ?, ?, ?, ?, NOW(), NOW() + INTERVAL 7 DAY, 0)";
        
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("sssis", $evt['titulo'], $evt['descripcion'], $evt['imagen'], $evt['tipo'], $evt['mapa_json']);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al crear el nuevo evento.");
        }
        $nuevo_id = $conn->insert_id;
        $stmt->close();

        // 3. COPIAR CATEGORÍAS
        $res_cat = $conn->query("SELECT nombre_categoria, precio, color FROM " . DB_HIST . ".categorias WHERE id_evento = $id_hist");
        if ($res_cat) {
            $stmt_cat = $conn->prepare("INSERT INTO " . DB_MAIN . ".categorias (id_evento, nombre_categoria, precio, color) VALUES (?, ?, ?, ?)");
            while ($cat = $res_cat->fetch_assoc()) {
                $stmt_cat->bind_param("isds", $nuevo_id, $cat['nombre_categoria'], $cat['precio'], $cat['color']);
                $stmt_cat->execute();
            }
            $stmt_cat->close();
        }

        // 4. COPIAR PROMOCIONES/DESCUENTOS (Si existen)
        // Solo copiamos la regla, no su historial de uso
        $res_promo = $conn->query("SELECT nombre, tipo_regla, codigo, modo_calculo, valor, condiciones, activo FROM " . DB_HIST . ".promociones WHERE id_evento = $id_hist");
        if ($res_promo) {
             $stmt_promo = $conn->prepare("INSERT INTO " . DB_MAIN . ".promociones (id_evento, nombre, tipo_regla, codigo, modo_calculo, valor, condiciones, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
             while ($p = $res_promo->fetch_assoc()) {
                 $stmt_promo->bind_param("issssdsi", $nuevo_id, $p['nombre'], $p['tipo_regla'], $p['codigo'], $p['modo_calculo'], $p['valor'], $p['condiciones'], $p['activo']);
                 $stmt_promo->execute();
             }
             $stmt_promo->close();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Evento reactivado correctamente.', 'new_id' => $nuevo_id]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Solicitud inválida.']);
}
$conn->close();
?>