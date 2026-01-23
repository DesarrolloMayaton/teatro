<?php
session_start();
require_once '../conexion.php';
require_once '../transacciones_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['usuario_rol'] !== 'admin') {
    die("Acceso denegado.");
}

function esAdminPrincipal($conn, $id) {
    $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE rol = 'admin' ORDER BY id_usuario ASC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['id_usuario'] == $id;
    }
    return false;
}

// API AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    if ($_POST['ajax'] === 'obtener_usuario') {
        $id = (int) $_POST['id'];
        $stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, rol, activo FROM usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $usuario = $result->fetch_assoc();
            $usuario['es_admin_principal'] = esAdminPrincipal($conn, $id);
            echo json_encode(['success' => true, 'usuario' => $usuario]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
        exit;
    }

    if ($_POST['ajax'] === 'guardar_usuario') {
        $id = (int) $_POST['id'];
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $password_nuevo = trim($_POST['password'] ?? '');
        $rol = $_POST['rol'];
        $admin_password = $_POST['admin_password'] ?? '';

        $stmt_verify = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
        $stmt_verify->bind_param("i", $_SESSION['usuario_id']);
        $stmt_verify->execute();
        $admin = $stmt_verify->get_result()->fetch_assoc();
        
        if (!password_verify($admin_password, $admin['password'])) {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            exit;
        }

        if (esAdminPrincipal($conn, $id) && $rol !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'No se puede cambiar el rol del admin principal']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ? AND id_usuario != ?");
        $stmt->bind_param("si", $nombre, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'El nombre ya existe']);
            exit;
        }

        $stmt_ant = $conn->prepare("SELECT nombre, apellido, rol FROM usuarios WHERE id_usuario = ?");
        $stmt_ant->bind_param("i", $id);
        $stmt_ant->execute();
        $datos_ant = $stmt_ant->get_result()->fetch_assoc();

        if (!empty($password_nuevo)) {
            if (strlen($password_nuevo) < 6) {
                echo json_encode(['success' => false, 'message' => 'Mínimo 6 caracteres']);
                exit;
            }
            $hash = password_hash($password_nuevo, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellido=?, password=?, rol=? WHERE id_usuario=?");
            $stmt->bind_param("ssssi", $nombre, $apellido, $hash, $rol, $id);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellido=?, rol=? WHERE id_usuario=?");
            $stmt->bind_param("sssi", $nombre, $apellido, $rol, $id);
        }

        if ($stmt->execute()) {
            registrar_transaccion('usuario_editar', "Usuario editado: {$datos_ant['nombre']} (ID: $id)");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar']);
        }
        exit;
    }

    if ($_POST['ajax'] === 'toggle_estado') {
        $id = (int) $_POST['id'];
        $admin_password = $_POST['admin_password'] ?? '';

        $stmt_verify = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
        $stmt_verify->bind_param("i", $_SESSION['usuario_id']);
        $stmt_verify->execute();
        $admin = $stmt_verify->get_result()->fetch_assoc();
        
        if (!password_verify($admin_password, $admin['password'])) {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            exit;
        }

        if (esAdminPrincipal($conn, $id)) {
            echo json_encode(['success' => false, 'message' => 'No se puede desactivar al admin principal']);
            exit;
        }

        $conn->query("UPDATE usuarios SET activo = NOT activo WHERE id_usuario = $id");
        $result = $conn->query("SELECT activo FROM usuarios WHERE id_usuario = $id")->fetch_assoc();
        registrar_transaccion('usuario_estado', "Estado cambiado ID: $id");
        echo json_encode(['success' => true, 'activo' => $result['activo']]);
        exit;
    }

    if ($_POST['ajax'] === 'eliminar_usuario') {
        $id = (int) $_POST['id'];
        $admin_password = $_POST['admin_password'] ?? '';

        $stmt_verify = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
        $stmt_verify->bind_param("i", $_SESSION['usuario_id']);
        $stmt_verify->execute();
        $admin = $stmt_verify->get_result()->fetch_assoc();
        
        if (!password_verify($admin_password, $admin['password'])) {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            exit;
        }

        if (esAdminPrincipal($conn, $id)) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar']);
            exit;
        }

        if ($id == $_SESSION['usuario_id']) {
            echo json_encode(['success' => false, 'message' => 'No puedes eliminarte']);
            exit;
        }

        $conn->query("DELETE FROM usuarios WHERE id_usuario = $id");
        registrar_transaccion('usuario_eliminar', "Usuario eliminado ID: $id");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['ajax'] === 'crear_usuario') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol = $_POST['rol'] ?? 'empleado';
        $admin_password = $_POST['admin_password'] ?? '';

        $stmt_verify = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
        $stmt_verify->bind_param("i", $_SESSION['usuario_id']);
        $stmt_verify->execute();
        $admin = $stmt_verify->get_result()->fetch_assoc();
        
        if (!password_verify($admin_password, $admin['password'])) {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            exit;
        }

        if (empty($nombre) || empty($apellido) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Campos obligatorios']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Mínimo 6 caracteres']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ?");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Usuario ya existe']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellido, password, rol, activo) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", $nombre, $apellido, $hash, $rol);

        if ($stmt->execute()) {
            registrar_transaccion('usuario_crear', "Nuevo usuario: $nombre $apellido");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al crear']);
        }
        exit;
    }
}

$query = "SELECT id_usuario, nombre, apellido, rol, activo FROM usuarios ORDER BY CASE WHEN rol='admin' THEN 0 ELSE 1 END, id_usuario";
$empleados = $conn->query($query);
$usuario_actual_id = $_SESSION['usuario_id'];

$admin_principal_id = 0;
$res = $conn->query("SELECT id_usuario FROM usuarios WHERE rol='admin' ORDER BY id_usuario LIMIT 1");
if ($res->num_rows > 0) $admin_principal_id = $res->fetch_assoc()['id_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/teatro-style.css">
    <style>
        body {
            background: var(--bg-primary);
            min-height: 100vh;
            padding: 24px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding: 24px;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
        
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 14px;
            color: var(--text-primary);
        }
        
        .page-header h1 i {
            color: var(--accent-blue);
            font-size: 1.6rem;
        }
        
        .btn-nuevo {
            background: var(--gradient-primary);
            border: none;
            padding: 14px 26px;
            border-radius: var(--radius-md);
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition-normal);
        }
        
        .btn-nuevo:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }
        
        /* Grid de usuarios */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 18px;
        }
        
        .user-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 22px;
            transition: var(--transition-normal);
        }
        
        .user-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .user-card.admin-principal {
            background: linear-gradient(135deg, rgba(21, 97, 240, 0.1) 0%, rgba(168, 85, 247, 0.08) 100%);
            border-color: rgba(21, 97, 240, 0.3);
        }
        
        .user-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }
        
        .user-avatar {
            width: 52px;
            height: 52px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        
        .user-avatar.admin {
            background: var(--gradient-primary);
            color: white;
        }
        
        .user-avatar.empleado {
            background: var(--bg-tertiary);
            color: var(--text-muted);
        }
        
        .user-info { flex: 1; }
        
        .user-name {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-name .crown { color: var(--warning); }
        
        .user-apellido {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 2px;
        }
        
        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 8px;
        }
        
        .user-badge.admin {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }
        
        .user-badge.empleado {
            background: var(--bg-tertiary);
            color: var(--text-muted);
        }
        
        .user-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        
        /* Toggle */
        .toggle-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toggle-label {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .teatro-toggle.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Botones de acción */
        .user-actions { display: flex; gap: 8px; }
        
        .btn-action {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            transition: var(--transition-fast);
        }
        
        .btn-edit {
            background: rgba(21, 97, 240, 0.15);
            color: var(--accent-blue);
        }
        
        .btn-edit:hover {
            background: var(--accent-blue);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }
        
        .btn-delete.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .btn-delete.disabled:hover {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        /* Modal personalizado para formularios */
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .teatro-modal-footer .teatro-btn {
            min-width: 100px;
        }
        
        @media (max-width: 640px) {
            body { padding: 16px; }
            .page-header { flex-direction: column; gap: 16px; text-align: center; padding: 20px; }
            .users-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="bi bi-people-fill"></i> Gestión de Usuarios</h1>
            <button class="btn-nuevo" onclick="abrirModalNuevo()">
                <i class="bi bi-person-plus-fill"></i> Nuevo Usuario
            </button>
        </div>
        
        <div class="users-grid">
            <?php while ($u = $empleados->fetch_assoc()):
                $es_principal = ($u['id_usuario'] == $admin_principal_id);
                $iniciales = strtoupper(substr($u['nombre'], 0, 1) . substr($u['apellido'], 0, 1));
            ?>
            <div class="user-card <?= $es_principal ? 'admin-principal' : '' ?>" id="card-<?= $u['id_usuario'] ?>">
                <div class="user-header">
                    <div class="user-avatar <?= $u['rol'] ?>"><?= $iniciales ?></div>
                    <div class="user-info">
                        <div class="user-name">
                            <?= htmlspecialchars($u['nombre']) ?>
                            <?php if ($es_principal): ?>
                                <i class="bi bi-shield-fill-check crown" title="Admin Principal"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-apellido"><?= htmlspecialchars($u['apellido']) ?></div>
                        <span class="user-badge <?= $u['rol'] ?>">
                            <i class="bi bi-<?= $u['rol'] == 'admin' ? 'star-fill' : 'person' ?>"></i>
                            <?= $u['rol'] ?>
                        </span>
                    </div>
                </div>
                
                <div class="user-footer">
                    <div class="toggle-container">
                        <span class="toggle-label" id="lbl-<?= $u['id_usuario'] ?>"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span>
                        <label class="teatro-toggle <?= $es_principal ? 'disabled' : '' ?>" onclick="<?= !$es_principal ? "pedirPassword('toggle', {$u['id_usuario']})" : '' ?>">
                            <input type="checkbox" <?= $u['activo'] ? 'checked' : '' ?> disabled>
                            <span class="teatro-toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="user-actions">
                        <button class="btn-action btn-edit" onclick="abrirModalEditar(<?= $u['id_usuario'] ?>)" title="Editar">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <?php if (!$es_principal && $u['id_usuario'] != $usuario_actual_id): ?>
                            <button class="btn-action btn-delete" onclick="pedirPassword('delete', <?= $u['id_usuario'] ?>, '<?= htmlspecialchars($u['nombre']) ?>')" title="Eliminar">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn-action btn-delete disabled" disabled title="Protegido">
                                <i class="bi bi-shield-lock-fill"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Modal Usuario (Crear/Editar) -->
    <div class="teatro-modal-overlay" id="modalUsuario">
        <div class="teatro-modal" style="max-width: 420px;">
            <div class="teatro-modal-header">
                <h3><i class="bi bi-person-plus-fill" id="mIcon"></i> <span id="mTitle">Nuevo Usuario</span></h3>
                <button class="teatro-modal-close" onclick="cerrarModalUsuario()">&times;</button>
            </div>
            <div class="teatro-modal-body">
                <div class="teatro-alert teatro-alert-error" id="mError" style="display:none;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span id="mErrorText"></span>
                </div>
                
                <input type="hidden" id="uid">
                
                <div class="form-group">
                    <label>Usuario</label>
                    <input type="text" class="teatro-input" id="unombre" placeholder="juan.perez">
                </div>
                
                <div class="form-group">
                    <label>Apellido</label>
                    <input type="text" class="teatro-input" id="uapellido" placeholder="Pérez García">
                </div>
                
                <div class="form-group">
                    <label id="passLabel">Contraseña</label>
                    <input type="text" class="teatro-input" id="upass" placeholder="Mínimo 6 caracteres">
                </div>
                
                <div class="form-group" id="rolGroup">
                    <label>Rol</label>
                    <select class="teatro-select" id="urol">
                        <option value="empleado">Empleado</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
            </div>
            <div class="teatro-modal-footer">
                <button class="teatro-btn teatro-btn-secondary" onclick="cerrarModalUsuario()">Cancelar</button>
                <button class="teatro-btn teatro-btn-primary" onclick="intentarGuardar()">
                    <i class="bi bi-check-lg"></i> Guardar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Contraseña (Ventana emergente separada) -->
    <div class="teatro-modal-overlay" id="modalPassword">
        <div class="teatro-modal" style="max-width: 380px;">
            <div class="teatro-modal-header">
                <h3><i class="bi bi-shield-lock-fill"></i> Verificación Requerida</h3>
                <button class="teatro-modal-close" onclick="cerrarModalPassword()">&times;</button>
            </div>
            <div class="teatro-modal-body">
                <i class="teatro-security-icon bi bi-key-fill"></i>
                <p class="teatro-security-text">
                    Para realizar este cambio, introduce tu contraseña de administrador.
                </p>
                <input type="password" class="teatro-password-input" id="adminPass" placeholder="••••••" autocomplete="off">
                <div class="teatro-alert teatro-alert-error mt-2" id="passError" style="display:none;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span id="passErrorText">Contraseña incorrecta</span>
                </div>
            </div>
            <div class="teatro-modal-footer" style="justify-content: center;">
                <button class="teatro-btn teatro-btn-secondary" onclick="cerrarModalPassword()">Cancelar</button>
                <button class="teatro-btn teatro-btn-primary" onclick="confirmarPassword()">
                    <i class="bi bi-unlock-fill"></i> Confirmar
                </button>
            </div>
        </div>
    </div>

    <script>
        let editando = false;
        let accionPendiente = null; // { tipo: 'save'|'toggle'|'delete', id, nombre }
        
        // ================================
        // MODAL USUARIO (Crear/Editar)
        // ================================
        function abrirModalNuevo() {
            editando = false;
            document.getElementById('mTitle').textContent = 'Nuevo Usuario';
            document.getElementById('mIcon').className = 'bi bi-person-plus-fill';
            document.getElementById('uid').value = '';
            document.getElementById('unombre').value = '';
            document.getElementById('uapellido').value = '';
            document.getElementById('upass').value = '';
            document.getElementById('urol').value = 'empleado';
            document.getElementById('passLabel').textContent = 'Contraseña';
            document.getElementById('upass').placeholder = 'Mínimo 6 caracteres';
            document.getElementById('rolGroup').style.opacity = '1';
            document.getElementById('urol').disabled = false;
            document.getElementById('mError').style.display = 'none';
            document.getElementById('modalUsuario').classList.add('active');
            document.getElementById('unombre').focus();
        }
        
        async function abrirModalEditar(id) {
            editando = true;
            document.getElementById('mTitle').textContent = 'Editar Usuario';
            document.getElementById('mIcon').className = 'bi bi-pencil-square';
            document.getElementById('passLabel').textContent = 'Nueva contraseña (opcional)';
            document.getElementById('upass').placeholder = 'Dejar vacío para no cambiar';
            document.getElementById('mError').style.display = 'none';
            
            const fd = new FormData();
            fd.append('ajax', 'obtener_usuario');
            fd.append('id', id);
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                document.getElementById('uid').value = data.usuario.id_usuario;
                document.getElementById('unombre').value = data.usuario.nombre;
                document.getElementById('uapellido').value = data.usuario.apellido;
                document.getElementById('upass').value = '';
                document.getElementById('urol').value = data.usuario.rol;
                
                const rg = document.getElementById('rolGroup');
                const rs = document.getElementById('urol');
                if (data.usuario.es_admin_principal) {
                    rg.style.opacity = '0.5';
                    rs.disabled = true;
                } else {
                    rg.style.opacity = '1';
                    rs.disabled = false;
                }
                
                document.getElementById('modalUsuario').classList.add('active');
                document.getElementById('unombre').focus();
            }
        }
        
        function cerrarModalUsuario() {
            document.getElementById('modalUsuario').classList.remove('active');
        }
        
        // Cuando el usuario hace clic en Guardar, primero pedimos contraseña
        function intentarGuardar() {
            accionPendiente = { tipo: 'save' };
            abrirModalPassword();
        }
        
        // ================================
        // MODAL CONTRASEÑA (Emergente separada)
        // ================================
        function pedirPassword(tipo, id, nombre = '') {
            accionPendiente = { tipo, id, nombre };
            abrirModalPassword();
        }
        
        function abrirModalPassword() {
            document.getElementById('adminPass').value = '';
            document.getElementById('passError').style.display = 'none';
            document.getElementById('modalPassword').classList.add('active');
            setTimeout(() => document.getElementById('adminPass').focus(), 100);
        }
        
        function cerrarModalPassword() {
            document.getElementById('modalPassword').classList.remove('active');
            accionPendiente = null;
        }
        
        async function confirmarPassword() {
            const pass = document.getElementById('adminPass').value;
            
            if (!pass) {
                document.getElementById('passErrorText').textContent = 'Ingresa tu contraseña';
                document.getElementById('passError').style.display = 'flex';
                return;
            }
            
            if (!accionPendiente) return;
            
            if (accionPendiente.tipo === 'save') {
                await guardarUsuario(pass);
            } else if (accionPendiente.tipo === 'toggle') {
                await ejecutarToggle(accionPendiente.id, pass);
            } else if (accionPendiente.tipo === 'delete') {
                await ejecutarEliminar(accionPendiente.id, pass);
            }
        }
        
        // ================================
        // ACCIONES CON PASSWORD
        // ================================
        async function guardarUsuario(pass) {
            const fd = new FormData();
            fd.append('ajax', editando ? 'guardar_usuario' : 'crear_usuario');
            fd.append('id', document.getElementById('uid').value);
            fd.append('nombre', document.getElementById('unombre').value);
            fd.append('apellido', document.getElementById('uapellido').value);
            fd.append('password', document.getElementById('upass').value);
            fd.append('rol', document.getElementById('urol').value);
            fd.append('admin_password', pass);
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                cerrarModalPassword();
                cerrarModalUsuario();
                location.reload();
            } else {
                if (data.message === 'Contraseña incorrecta') {
                    document.getElementById('passErrorText').textContent = data.message;
                    document.getElementById('passError').style.display = 'flex';
                    document.getElementById('adminPass').value = '';
                    document.getElementById('adminPass').focus();
                } else {
                    cerrarModalPassword();
                    document.getElementById('mErrorText').textContent = data.message;
                    document.getElementById('mError').style.display = 'flex';
                }
            }
        }
        
        async function ejecutarToggle(id, pass) {
            const fd = new FormData();
            fd.append('ajax', 'toggle_estado');
            fd.append('id', id);
            fd.append('admin_password', pass);
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                cerrarModalPassword();
                const cb = document.querySelector(`#card-${id} .teatro-toggle input`);
                const lbl = document.getElementById(`lbl-${id}`);
                cb.checked = data.activo;
                lbl.textContent = data.activo ? 'Activo' : 'Inactivo';
            } else {
                document.getElementById('passErrorText').textContent = data.message;
                document.getElementById('passError').style.display = 'flex';
                document.getElementById('adminPass').value = '';
                document.getElementById('adminPass').focus();
            }
        }
        
        async function ejecutarEliminar(id, pass) {
            const fd = new FormData();
            fd.append('ajax', 'eliminar_usuario');
            fd.append('id', id);
            fd.append('admin_password', pass);
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                cerrarModalPassword();
                document.getElementById('card-' + id).remove();
            } else {
                document.getElementById('passErrorText').textContent = data.message;
                document.getElementById('passError').style.display = 'flex';
                document.getElementById('adminPass').value = '';
                document.getElementById('adminPass').focus();
            }
        }
        
        // ================================
        // ATAJOS DE TECLADO
        // ================================
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                cerrarModalPassword();
                cerrarModalUsuario();
            }
        });
        
        document.getElementById('adminPass').addEventListener('keypress', e => {
            if (e.key === 'Enter') confirmarPassword();
        });
    </script>
</body>
</html>