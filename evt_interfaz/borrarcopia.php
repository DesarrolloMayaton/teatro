<?php
// borrarcopia.php
include "../conexion.php";

// Ajusta los nombres de tus BD si son diferentes
define('DB_MAIN', 'trt_25');
define('DB_HIST', 'trt_historico');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_evento'])) {
    $id = intval($_POST['id_evento']);
    
    // Opcional: Verificación de seguridad extra aquí si la necesitas
    // if (!verificar_admin($_POST['auth_user'], $_POST['auth_pin'])) { die(json_encode(['status'=>'error','message'=>'Auth fallida'])); }

    $conn->begin_transaction();
    try {
        // 1. COPIAR TODO AL HISTORIAL
        // Usamos INSERT IGNORE para no fallar si ya existía un registro parcial
        $tablas = ['evento', 'funciones', 'categorias', 'promociones', 'boletos'];
        foreach ($tablas as $tabla) {
            $conn->query("INSERT IGNORE INTO " . DB_HIST . ".$tabla SELECT * FROM " . DB_MAIN . ".$tabla WHERE id_evento = $id");
        }
        // Asegurar que quede marcado como finalizado en el historial
        $conn->query("UPDATE " . DB_HIST . ".evento SET finalizado = 1 WHERE id_evento = $id");

        // 2. BORRAR IMÁGENES QR FÍSICAS (Opcional, para ahorrar espacio en disco)
        $res_qrs = $conn->query("SELECT qr_path FROM " . DB_MAIN . ".boletos WHERE id_evento = $id");
        if ($res_qrs) {
            while ($row = $res_qrs->fetch_assoc()) {
                if (!empty($row['qr_path'])) {
                    $ruta = __DIR__ . '/../' . $row['qr_path']; // Ajusta la ruta si es necesario
                    if (file_exists($ruta)) { @unlink($ruta); }
                }
            }
        }

        // 3. ELIMINAR DE LA BASE PRINCIPAL
        // El orden es crítico para respetar las llaves foráneas (primero hijos, al final el padre)
        $conn->query("DELETE FROM " . DB_MAIN . ".boletos WHERE id_evento = $id");
        $conn->query("DELETE FROM " . DB_MAIN . ".promociones WHERE id_evento = $id");
        $conn->query("DELETE FROM " . DB_MAIN . ".categorias WHERE id_evento = $id");
        $conn->query("DELETE FROM " . DB_MAIN . ".funciones WHERE id_evento = $id");
        $conn->query("DELETE FROM " . DB_MAIN . ".evento WHERE id_evento = $id");

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Evento archivado y borrado correctamente.']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Solicitud inválida.']);
}
$conn->close();
?>