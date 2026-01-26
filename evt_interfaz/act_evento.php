<?php
// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Mexico_City'); // Zona horaria local

session_start();
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
function archivar_evento_completo($id, $conn)
{
    $db_historico = 'trt_historico_evento';
    $db_principal = 'trt_25';

    // 1. COPIAR TODO A HISTÓRICO
    $result = $conn->query("INSERT IGNORE INTO {$db_historico}.evento SELECT * FROM {$db_principal}.evento WHERE id_evento = $id");
    if (!$result)
        throw new Exception("Error copiando evento: " . $conn->error);

    $result = $conn->query("INSERT IGNORE INTO {$db_historico}.funciones SELECT * FROM {$db_principal}.funciones WHERE id_evento = $id");
    if (!$result)
        throw new Exception("Error copiando funciones: " . $conn->error);

    $result = $conn->query("INSERT IGNORE INTO {$db_historico}.categorias SELECT * FROM {$db_principal}.categorias WHERE id_evento = $id");
    if (!$result)
        throw new Exception("Error copiando categorías: " . $conn->error);

    $result = $conn->query("INSERT IGNORE INTO {$db_historico}.promociones SELECT * FROM {$db_principal}.promociones WHERE id_evento = $id");
    if (!$result)
        throw new Exception("Error copiando promociones: " . $conn->error);

    $result = $conn->query("INSERT IGNORE INTO {$db_historico}.boletos SELECT * FROM {$db_principal}.boletos WHERE id_evento = $id");
    if (!$result)
        throw new Exception("Error copiando boletos: " . $conn->error);

    // 2. BORRAR DE PRODUCCIÓN
    $conn->query("DELETE FROM {$db_principal}.boletos WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.promociones WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.categorias WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.funciones WHERE id_evento = $id");
    $conn->query("DELETE FROM {$db_principal}.evento WHERE id_evento = $id");

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

// CARGA DE DATOS (SOLO ACTIVOS) - Incluye conteo de boletos vendidos
$activos = $conn->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM boletos b WHERE b.id_evento = e.id_evento AND b.estatus = 1) as boletos_vendidos
    FROM trt_25.evento e 
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state p {
            margin: 0;
            font-size: 1rem;
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

        .modal-box {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 420px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            animation: modalIn 0.3s ease;
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

        .modal-header {
            background: var(--danger);
            color: white;
            padding: 20px 24px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h5 {
            margin: 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header .btn-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-header .btn-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal-body {
            padding: 30px;
            text-align: center;
        }

        .modal-body .modal-icon {
            font-size: 4rem;
            color: var(--danger);
            margin-bottom: 20px;
        }

        .modal-body p {
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .modal-body .event-name {
            color: var(--warning);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .modal-body input[type="password"] {
            width: 100%;
            padding: 14px;
            margin-top: 20px;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 6px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .modal-body input[type="password"]:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(21, 97, 240, 0.2);
        }

        .modal-error {
            color: var(--danger);
            font-size: 0.85rem;
            margin-top: 10px;
            display: none;
        }

        .modal-warning {
            background: var(--warning-bg);
            border: 1px solid rgba(255, 159, 10, 0.3);
            color: var(--warning);
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-top: 20px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Advertencia de boletos vendidos */
        .boletos-warning {
            background: var(--danger-bg);
            border: 1px solid rgba(255, 69, 58, 0.4);
            color: var(--danger);
            padding: 16px;
            border-radius: var(--radius-sm);
            margin-top: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
        }

        .boletos-warning i {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .boletos-warning small {
            opacity: 0.8;
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

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
        }

        .modal-footer button {
            padding: 12px 30px;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition-fast);
        }

        .modal-footer .btn-confirm {
            background: var(--danger);
            color: white;
        }

        .modal-footer .btn-confirm:hover:not(:disabled) {
            filter: brightness(1.1);
        }

        .modal-footer .btn-confirm:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
            <span class="badge-count"><?= $activos->num_rows ?> eventos</span>
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
                                    <span class="boletos-badge"><?= $e['boletos_vendidos'] ?></span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No hay eventos activos.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="modalArchivar">
        <div class="modal-box">
            <div class="modal-header">
                <h5><i class="bi bi-shield-lock-fill"></i> Acceso Restringido</h5>
                <button class="btn-close" onclick="cerrarModal()">×</button>
            </div>
            <div class="modal-body">
                <i class="bi bi-person-badge-fill modal-icon"></i>
                <p>¿Archivar el evento:</p>
                <div class="event-name" id="nombreEventoArchivar"></div>

                <!-- Advertencia de boletos vendidos -->
                <div class="boletos-warning" id="boletosWarning" style="display: none;">
                    <i class="bi bi-ticket-perforated-fill"></i>
                    <div>
                        <strong>¡Atención!</strong> Este evento tiene <span id="cantidadBoletos">0</span> boletos
                        vendidos.
                        <br><small>Los boletos se moverán al histórico junto con el evento.</small>
                    </div>
                </div>

                <p style="margin-top: 16px; font-size: 0.9rem;">Ingresa tu contraseña de administrador para continuar.
                </p>
                <input type="password" id="auth_pass" placeholder="••••••" maxlength="20">
                <div class="modal-error" id="errorArchivar">
                    <i class="bi bi-exclamation-triangle"></i> <span id="errorArchivarText">Contraseña incorrecta</span>
                </div>
                <div class="modal-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>El evento dejará de ser visible y se moverá al histórico.</span>
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