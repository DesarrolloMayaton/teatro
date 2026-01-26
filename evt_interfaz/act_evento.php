<?php
// Activar reporte de errores
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('America/Mexico_City'); // Sincronizar zona horaria

include "../conexion.php";

if (file_exists("../transacciones_helper.php")) {
    require_once "../transacciones_helper.php";
}

require_once __DIR__ . "/../api/registrar_cambio.php";

// ==================================================================
// VERIFICACIÓN DE SESIÓN
// ==================================================================
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] !== 'admin' && (!isset($_SESSION['admin_verificado']) || !$_SESSION['admin_verificado']))) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px; color: red;"><h1>Acceso Denegado</h1><p>No tiene permiso para ver esta página.</p></div>');
}

// ==================================================================
// ACTUALIZACIÓN AUTOMÁTICA DE ESTADOS DE FUNCIONES
// ==================================================================
$conn->query("UPDATE funciones SET estado = 1 WHERE fecha_hora < NOW() AND estado = 0");

// ==================================================================
// AUTO-ARCHIVADO DE EVENTOS CADUCADOS
// ==================================================================
include_once __DIR__ . '/auto_archivar.php';


// ==================================================================
// FUNCIÓN PARA ARCHIVAR (MOVER A HISTÓRICO)
// ==================================================================

/**
 * Archiva un evento moviéndolo a la base de datos histórica.
 * Asume que la estructura de tablas es idéntica (gestionado por setup_historico.php)
 */
function archivar_evento_completo($id, $conn)
{
    $db_historico = 'trt_historico_evento';
    $db_principal = 'trt_25';
    $id = (int) $id;

    $tablas = ['evento', 'funciones', 'categorias', 'promociones', 'boletos'];

    // 1. COPIAR A HISTÓRICO (INSERT IGNORE ... SELECT)
    // Usamos INSERT IGNORE para evitar errores, aunque idealmente no debería haber duplicados de ID si se borraron antes.
    foreach ($tablas as $tabla) {
        $sql = "INSERT IGNORE INTO `$db_historico`.`$tabla` SELECT * FROM `$db_principal`.`$tabla` WHERE id_evento = $id";
        if (!$conn->query($sql)) {
            // Si falla, podría ser por discrepancia de columnas. 
            // En ese caso, registrar error pero intentar continuar o lanzar excepción.
            throw new Exception("Error archivando tabla $tabla: " . $conn->error . ". Ejecute setup_historico.php si hubo cambios de estructura.");
        }
    }

    // 2. BORRAR DE PRODUCCIÓN (Orden inverso por integridad referencial si la hubiera, aunque aquí es por ID)
    // Borramos boletos primero, luego lo demás.
    $conn->query("DELETE FROM `$db_principal`.boletos WHERE id_evento = $id");
    $conn->query("DELETE FROM `$db_principal`.promociones WHERE id_evento = $id");
    $conn->query("DELETE FROM `$db_principal`.categorias WHERE id_evento = $id");
    $conn->query("DELETE FROM `$db_principal`.funciones WHERE id_evento = $id");
    $conn->query("DELETE FROM `$db_principal`.evento WHERE id_evento = $id");

    return true;
}

// ==================================================================
// PROCESADOR AJAX
// ==================================================================
if (isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $id = (int) ($_POST['id_evento'] ?? 0);

    // Acción: Consultar boletos vendidos
    if ($_POST['accion'] === 'consultar_boletos') {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM boletos WHERE id_evento = ? AND estatus = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $total = $result->fetch_assoc()['total'];
        $stmt->close();
        echo json_encode(['status' => 'success', 'boletos' => (int) $total]);
        exit;
    }

    $conn->begin_transaction();
    try {
        if ($_POST['accion'] === 'finalizar') {
            $password = $_POST['password'] ?? '';

            $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ? AND rol = 'admin'");
            $stmt->bind_param("i", $_SESSION['usuario_id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Error de autenticación.");
            }

            $admin = $result->fetch_assoc();

            if (!password_verify($password, $admin['password'])) {
                throw new Exception("Contraseña incorrecta.");
            }
            $stmt->close();

            archivar_evento_completo($id, $conn);

            if (function_exists('registrar_transaccion')) {
                registrar_transaccion('evento_archivar', 'Archivó evento ID ' . $id . ' (Autorizado por: ' . $_SESSION['usuario_nombre'] . ')');
            }

            registrar_cambio('evento', $id, null, ['accion' => 'archivar']);
        }
        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// CARGA DE DATOS (SOLO ACTIVOS) - OPTIMIZADA
// Usamos LEFT JOIN con tabla derivada para evitar subconsulta correlacionada por cada fila
$activos = $conn->query("
    SELECT e.*, COALESCE(b.total, 0) as boletos_vendidos
    FROM trt_25.evento e 
    LEFT JOIN (
        SELECT id_evento, COUNT(*) as total 
        FROM boletos 
        WHERE estatus = 1 
        GROUP BY id_evento
    ) b ON e.id_evento = b.id_evento
    WHERE e.finalizado = 0 
    ORDER BY e.inicio_venta DESC
");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/teatro-style.css">
    <style>
        body {
            opacity: 0;
            transition: opacity 0.4s;
        }

        body.loaded {
            opacity: 1;
        }

        .content-wrapper {
            padding: 24px;
        }

        /* Empty State - Premium Design */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            background: linear-gradient(135deg, rgba(21, 97, 240, 0.03) 0%, rgba(139, 92, 246, 0.05) 50%, rgba(21, 97, 240, 0.03) 100%);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .empty-state::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(21, 97, 240, 0.05) 0%, transparent 50%);
            animation: floatBg 15s ease-in-out infinite;
        }

        @keyframes floatBg {

            0%,
            100% {
                transform: translate(0, 0) rotate(0deg);
            }

            33% {
                transform: translate(10px, -10px) rotate(5deg);
            }

            66% {
                transform: translate(-10px, 10px) rotate(-5deg);
            }
        }

        .empty-state-content {
            position: relative;
            z-index: 1;
        }

        .empty-state-icon {
            width: 140px;
            height: 140px;
            background: linear-gradient(135deg, var(--accent-blue) 0%, #8b5cf6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 20px 60px rgba(21, 97, 240, 0.3), 0 0 0 15px rgba(21, 97, 240, 0.1);
            animation: iconFloat 3s ease-in-out infinite;
        }

        @keyframes iconFloat {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .empty-state-icon i {
            font-size: 4rem;
            color: white;
        }

        .empty-state h3 {
            color: #ffffff !important;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 12px;
        }

        .empty-state p {
            color: #cbd5e1 !important;
            font-size: 1rem;
            margin: 0 0 30px;
            max-width: 400px;
            line-height: 1.6;
        }

        .empty-state .btn-create {
            background: linear-gradient(135deg, var(--accent-blue) 0%, #8b5cf6 100%);
            color: white;
            padding: 16px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 30px rgba(21, 97, 240, 0.4);
            text-decoration: none;
        }

        .empty-state .btn-create:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 40px rgba(21, 97, 240, 0.5);
        }

        .empty-state .sparkle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: var(--accent-blue);
            border-radius: 50%;
            opacity: 0.6;
            animation: sparkle 2s ease-in-out infinite;
        }

        .empty-state .sparkle:nth-child(1) {
            top: 20%;
            left: 15%;
            animation-delay: 0s;
        }

        .empty-state .sparkle:nth-child(2) {
            top: 30%;
            right: 20%;
            animation-delay: 0.5s;
        }

        .empty-state .sparkle:nth-child(3) {
            bottom: 25%;
            left: 25%;
            animation-delay: 1s;
        }

        .empty-state .sparkle:nth-child(4) {
            bottom: 35%;
            right: 15%;
            animation-delay: 1.5s;
        }

        @keyframes sparkle {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.6;
            }

            50% {
                transform: scale(1.5);
                opacity: 1;
            }
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-header h4 {
            margin: 0;
            font-weight: 700;
            color: var(--accent-blue);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge-count {
            background: var(--bg-tertiary);
            color: var(--text-muted);
            padding: 6px 14px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Grid de tarjetas */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }

        /* Tarjeta de evento */
        .event-card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            background: var(--bg-card);
            overflow: hidden;
            transition: var(--transition-normal);
            animation: cardEntry 0.4s ease forwards;
            opacity: 0;
            transform: translateY(15px);
        }

        @keyframes cardEntry {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-blue);
        }

        .card-img-container {
            width: 100%;
            aspect-ratio: 3 / 4;
            background-color: var(--bg-tertiary);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s;
        }

        .event-card:hover .card-img {
            transform: scale(1.08);
        }

        .card-body {
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.3;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Mensaje de evento terminado */
        .evento-terminado-msg {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
            border: 1px solid rgba(139, 92, 246, 0.4);
            color: #a5b4fc;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: pulse 2s infinite;
        }

        .evento-terminado-msg i {
            font-size: 1rem;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        /* Contenedor de funciones */
        .funcs-container {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            max-height: 70px;
            overflow-y: auto;
        }

        .funcs-container::-webkit-scrollbar {
            width: 3px;
        }

        .funcs-container::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }

        .func-badge {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 6px;
            white-space: nowrap;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .func-badge.active {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .func-badge.expired {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid rgba(255, 69, 58, 0.3);
            text-decoration: line-through;
            opacity: 0.7;
        }

        .card-footer {
            padding: 12px 14px;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 8px;
        }

        .btn-card {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-card.primary {
            background: var(--accent-blue);
            color: white;
        }

        .btn-card.primary:hover {
            background: var(--accent-blue-hover);
        }

        .btn-card.danger {
            background: var(--bg-tertiary);
            color: var(--danger);
            border: 1px solid var(--border-color);
        }

        .btn-card.danger:hover {
            background: var(--danger-bg);
            border-color: var(--danger);
        }

        /* Badge de boletos en tarjetas */
        .boletos-badge {
            background: var(--danger);
            color: white;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 4px;
            font-weight: 700;
        }

        /* Alert de auto-archivado */
        .auto-archive-alert {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #a5b4fc;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .auto-archive-alert i {
            font-size: 1.5rem;
        }

        .auto-archive-alert .btn-close {
            margin-left: auto;
            background: none;
            border: none;
            color: #a5b4fc;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.7;
        }

        .auto-archive-alert .btn-close:hover {
            opacity: 1;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        /* Modal Ultra Premium */
        .modal-box {
            background: linear-gradient(160deg, #1e1e22 0%, #141416 50%, #1a1a1e 100%);
            border-radius: 24px;
            width: 92%;
            max-width: 440px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.05),
                0 25px 80px rgba(0, 0, 0, 0.7),
                0 0 100px rgba(255, 69, 58, 0.15);
            animation: modalIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
            position: relative;
        }

        .modal-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header-premium {
            background: linear-gradient(135deg, #ff453a 0%, #d70015 100%);
            padding: 40px 30px;
            position: relative;
            text-align: center;
            overflow: hidden;
        }

        .modal-header-premium::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.2) 0%, transparent 50%);
        }

        .modal-header-premium::after {
            content: '';
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 30px solid transparent;
            border-right: 30px solid transparent;
            border-top: 25px solid #d70015;
        }

        .modal-header-premium h5 {
            margin: 0;
            font-weight: 800;
            font-size: 1.5rem;
            color: white;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .modal-header-premium .btn-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            transition: all 0.2s;
        }

        .modal-header-premium .btn-close:hover {
            background: rgba(0, 0, 0, 0.4);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 50px 35px 30px;
            text-align: center;
            position: relative;
        }

        .modal-body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 10%;
            right: 10%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 69, 58, 0.3), transparent);
        }

        .modal-body .modal-icon {
            font-size: 4rem;
            color: var(--danger);
            margin-bottom: 20px;
            filter: drop-shadow(0 0 20px rgba(255, 69, 58, 0.3));
            animation: iconPulse 2s ease-in-out infinite;
        }

        @keyframes iconPulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .modal-body p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 8px;
            font-size: 1rem;
            line-height: 1.6;
        }

        .modal-body .event-name {
            color: white;
            font-weight: 800;
            font-size: 1.3rem;
            margin: 10px 0 20px;
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.1);
        }

        .modal-body input[type="password"] {
            width: 100%;
            padding: 16px;
            margin-top: 25px;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 6px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            transition: all 0.3s;
        }

        .modal-body input[type="password"]:focus {
            outline: none;
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(255, 69, 58, 0.2);
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-error {
            color: #ff453a;
            font-size: 0.9rem;
            margin-top: 15px;
            display: none;
            padding: 10px;
            background: rgba(255, 69, 58, 0.1);
            border-radius: 8px;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .boletos-warning {
            background: rgba(255, 159, 10, 0.1);
            border: 1px solid rgba(255, 159, 10, 0.3);
            color: var(--warning);
            padding: 16px;
            border-radius: 12px;
            margin-top: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
        }

        .boletos-warning strong {
            color: #ff9f0a;
        }

        .modal-footer {
            padding: 20px 35px 35px;
            display: flex;
            justify-content: center;
            border: none;
        }

        .modal-footer button {
            padding: 16px 30px;
            width: 100%;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .modal-footer .btn-confirm {
            background: linear-gradient(135deg, #ff453a 0%, #d70015 100%);
            color: white;
            box-shadow: 0 8px 25px rgba(255, 69, 58, 0.3);
        }

        .modal-footer .btn-confirm:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(255, 69, 58, 0.4);
        }

        .modal-footer .btn-confirm:active {
            transform: translateY(0);
        }

        .modal-footer .btn-confirm::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50%;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.1), transparent);
            pointer-events: none;
        }

        .modal-footer .btn-confirm:disabled {
            background: #3a3a3c;
            color: #86868b;
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }
    </style>
</head>

<body>

    <div class="content-wrapper">
        <?php
        if (!empty($eventos_auto_archivados)):
            ?>
            <div class="auto-archive-alert">
                <i class="bi bi-archive-fill"></i>
                <div>
                    <strong>Auto-archivado:</strong>
                    <?php
                    $nombres = array_column($eventos_auto_archivados, 'titulo');
                    echo count($nombres) . ' evento(s) (' . implode(', ', $nombres) . ') fueron movidos al historial automáticamente.';
                    ?>
                </div>
                <button class="btn-close" onclick="this.parentElement.remove()">×</button>
            </div>
            <?php
            unset($_SESSION['eventos_auto_archivados']);
        endif;
        ?>

        <div class="page-header">
            <h4><i class="bi bi-calendar-event"></i> Eventos Activos</h4>
            <span class="badge-count">
                <?= $activos->num_rows ?> eventos
            </span>
        </div>

        <?php if ($activos && $activos->num_rows > 0): ?>
            <div class="events-grid">
                <?php
                $delay = 0;
                while ($e = $activos->fetch_assoc()):
                    $delay += 40;
                    $img = '';
                    if (!empty($e['imagen'])) {
                        $rutas = ["../evt_interfaz/" . $e['imagen'], $e['imagen']];
                        foreach ($rutas as $r) {
                            if (file_exists($r)) {
                                $img = $r;
                                break;
                            }
                        }
                    }

                    $id_evt = $e['id_evento'];
                    $sql_func = "SELECT fecha_hora, estado FROM funciones WHERE id_evento = $id_evt ORDER BY fecha_hora ASC";
                    $res_func = $conn->query($sql_func);
                    $funciones = [];
                    if ($res_func) {
                        while ($f = $res_func->fetch_assoc()) {
                            $funciones[] = $f;
                        }
                    }
                    ?>
                    <div class="event-card" style="animation-delay: <?= $delay ?>ms">
                        <div class="card-img-container">
                            <?php if ($img): ?>
                                <img src="<?= htmlspecialchars($img) ?>" class="card-img" loading="lazy"
                                    alt="<?= htmlspecialchars($e['titulo']) ?>">
                            <?php else: ?>
                                <i class="bi bi-image" style="font-size: 2rem; color: var(--text-muted); opacity: 0.4;"></i>
                            <?php endif; ?>
                        </div>

                        <div class="card-body">
                            <h6 class="card-title" title="<?= htmlspecialchars($e['titulo']) ?>">
                                <?= htmlspecialchars($e['titulo']) ?>
                            </h6>

                            <?php
                            // Verificar si TODAS las funciones están vencidas
                            $todasVencidas = true;
                            $funcionesActivas = 0;
                            foreach ($funciones as $f) {
                                if ($f['estado'] != 1 && strtotime($f['fecha_hora']) > time()) {
                                    $todasVencidas = false;
                                    $funcionesActivas++;
                                }
                            }
                            ?>

                            <?php if ($todasVencidas && count($funciones) > 0): ?>
                                <div class="evento-terminado-msg">
                                    <i class="bi bi-clock-history"></i>
                                    <span>Evento terminado. Se archivará a medianoche.</span>
                                </div>
                            <?php endif; ?>

                            <div class="funcs-container">
                                <?php if (count($funciones) > 0): ?>
                                    <?php foreach ($funciones as $f):
                                        $esVencida = ($f['estado'] == 1) || (strtotime($f['fecha_hora']) < time());
                                        $clase = $esVencida ? 'expired' : 'active';
                                        $icono = $esVencida ? 'bi-hourglass-bottom' : 'bi-clock';
                                        ?>
                                        <span class="func-badge <?= $clase ?>">
                                            <i class="bi <?= $icono ?>"></i>
                                            <?= date('d/m H:i', strtotime($f['fecha_hora'])) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="func-badge"
                                        style="background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(255,69,58,0.3);">Sin
                                        funciones</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button onclick="window.location.href='editar_evento.php?id=<?= $e['id_evento'] ?>'"
                                class="btn-card primary">
                                <i class="bi bi-pencil"></i> Editar
                            </button>

                            <button class="btn-card danger"
                                onclick="prepararArchivado(<?= $e['id_evento'] ?>, '<?= addslashes($e['titulo']) ?>', <?= (int) $e['boletos_vendidos'] ?>)"
                                title="Mover al Historial">
                                <i class="bi bi-archive"></i>
                                <?php if ($e['boletos_vendidos'] > 0): ?>
                                    <span class="boletos-badge">
                                        <?= $e['boletos_vendidos'] ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="sparkle"></div>
                <div class="sparkle"></div>
                <div class="sparkle"></div>
                <div class="sparkle"></div>

                <div class="empty-state-content">
                    <div class="empty-state-icon">
                        <i class="bi bi-calendar-plus"></i>
                    </div>
                    <h3 style="color: #ffffff !important;">No hay eventos activos</h3>
                    <p style="color: #cbd5e1 !important;">Comienza creando tu primer evento. Agrega funciones, configura
                        precios y empieza a vender boletos.</p>
                    <a href="crear_evento.php" class="btn-create">
                        <i class="bi bi-plus-lg"></i>
                        Crear Primer Evento
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="modalArchivar">
        <div class="modal-box">
            <div class="modal-header-premium">
                <h5>Acceso Restringido</h5>
                <button class="btn-close" onclick="cerrarModal()">×</button>
            </div>
            <div class="modal-body">
                <i class="bi bi-shield-lock-fill modal-icon"></i>
                <p>Estás a punto de archivar el evento:</p>
                <div class="event-name" id="nombreEventoArchivar"></div>

                <p style="font-size: 0.9rem; margin-top: 15px;">El evento dejará de ser visible y pasará al historial.
                </p>

                <!-- Advertencia de boletos vendidos -->
                <div class="boletos-warning" id="boletosWarning" style="display: none;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div style="font-size: 0.9rem;">
                        <strong>Atención:</strong> Este evento tiene <span id="cantidadBoletos"
                            style="font-weight: 700; color: white;">0</span> boletos vendidos que también serán
                        archivados.
                    </div>
                </div>

                <input type="password" id="auth_pass" placeholder="••••••" maxlength="20">
                <div class="modal-error" id="errorArchivar">
                    <i class="bi bi-exclamation-circle-fill"></i> <span id="errorArchivarText">Contraseña
                        incorrecta</span>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btnConfirmarFinal" class="btn-confirm" onclick="ejecutarArchivado()">
                    <i class="bi bi-archive-fill"></i> Confirmar Archivo
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => document.body.classList.add('loaded'));

        let eventoIdSeleccionado = null;
        let boletosEvento = 0;

        function prepararArchivado(id, titulo, boletos) {
            eventoIdSeleccionado = id;
            boletosEvento = boletos || 0;

            document.getElementById('nombreEventoArchivar').textContent = titulo;
            document.getElementById('auth_pass').value = '';
            document.getElementById('errorArchivar').style.display = 'none';
            document.getElementById('btnConfirmarFinal').disabled = false;

            // Mostrar advertencia de boletos si hay
            const warningDiv = document.getElementById('boletosWarning');
            const cantidadSpan = document.getElementById('cantidadBoletos');

            if (boletosEvento > 0) {
                warningDiv.style.display = 'flex';
                cantidadSpan.textContent = boletosEvento;
                document.getElementById('btnConfirmarFinal').innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Archivar con ' + boletosEvento + ' boletos';
            } else {
                warningDiv.style.display = 'none';
                document.getElementById('btnConfirmarFinal').innerHTML = '<i class="bi bi-archive-fill"></i> Confirmar Archivo';
            }

            document.getElementById('modalArchivar').classList.add('active');
            setTimeout(() => document.getElementById('auth_pass').focus(), 300);
        }

        function cerrarModal() {
            document.getElementById('modalArchivar').classList.remove('active');
        }

        document.getElementById('auth_pass').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                ejecutarArchivado();
            }
        });

        function ejecutarArchivado() {
            const password = document.getElementById('auth_pass').value.trim();
            const errorDiv = document.getElementById('errorArchivar');
            const errorText = document.getElementById('errorArchivarText');

            if (!password) {
                errorDiv.style.display = 'block';
                errorText.textContent = 'Ingresa tu contraseña';
                document.getElementById('auth_pass').focus();
                return;
            }

            const btn = document.getElementById('btnConfirmarFinal');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Procesando...';
            errorDiv.style.display = 'none';

            let fd = new FormData();
            fd.append('accion', 'finalizar');
            fd.append('id_evento', eventoIdSeleccionado);
            fd.append('password', password);

            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        cerrarModal();
                        location.reload();
                    } else {
                        errorDiv.style.display = 'block';
                        errorText.textContent = data.message || 'Error al procesar';
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-archive-fill"></i> Confirmar Archivo';
                        document.getElementById('auth_pass').value = '';
                        document.getElementById('auth_pass').focus();
                    }
                })
                .catch(() => {
                    errorDiv.style.display = 'block';
                    errorText.textContent = 'Error de conexión';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-archive-fill"></i> Confirmar Archivo';
                });
        }
    </script>
</body>

</html>