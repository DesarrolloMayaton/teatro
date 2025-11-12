<?php
session_start();
require_once '../conexion.php';

// Verificar que hay sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

// Verificar que es admin
if ($_SESSION['usuario_rol'] !== 'admin') {
    die("Acceso denegado. Solo administradores pueden registrar empleados.");
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    $rol = $_POST['rol'] ?? 'empleado';
    
    // Validaciones
    if (empty($nombre) || empty($apellido) || empty($password)) {
        $mensaje = 'Todos los campos son obligatorios';
        $tipo_mensaje = 'error';
    } elseif ($password !== $password_confirm) {
        $mensaje = 'Las contraseñas no coinciden';
        $tipo_mensaje = 'error';
    } elseif (strlen($password) !== 6) {
        $mensaje = 'La contraseña debe tener exactamente 6 caracteres';
        $tipo_mensaje = 'error';
    } else {
        // Verificar si el nombre de usuario ya existe
        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ?");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $mensaje = 'El nombre de usuario ya existe';
            $tipo_mensaje = 'error';
        } else {
            // Insertar nuevo usuario
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellido, password, rol, activo) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $nombre, $apellido, $password, $rol);
            
            if ($stmt->execute()) {
                $mensaje = 'Empleado registrado exitosamente';
                $tipo_mensaje = 'success';
                // Limpiar formulario
                $_POST = array();
            } else {
                $mensaje = 'Error al registrar el empleado: ' . $conn->error;
                $tipo_mensaje = 'error';
            }
        }
        
        $stmt->close();
    }
}

// Obtener lista de empleados
$query = "SELECT id_usuario, nombre, apellido, rol, activo, fecha_registro FROM usuarios ORDER BY fecha_registro DESC";
$empleados = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Empleado</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f7f6;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h1 i {
            color: #667eea;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        .form-card, .list-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 1.3em;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empleados-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .empleados-table thead {
            background: #f8f9fa;
        }

        .empleados-table th,
        .empleados-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .empleados-table th {
            font-weight: 600;
            color: #2c3e50;
        }

        .empleados-table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-admin {
            background: #667eea;
            color: white;
        }

        .badge-empleado {
            background: #6c757d;
            color: white;
        }

        .badge-activo {
            background: #28a745;
            color: white;
        }

        .badge-inactivo {
            background: #dc3545;
            color: white;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s;
        }

        .btn-toggle {
            background: #ffc107;
            color: #000;
        }

        .btn-toggle:hover {
            background: #e0a800;
        }

        @media (max-width: 1024px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .back-button:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../index.php" class="back-button">
            <i class="bi bi-arrow-left"></i>
            Volver al Sistema
        </a>

        <div class="page-header">
            <h1><i class="bi bi-person-plus-fill"></i> Registro de Empleados</h1>
        </div>

        <div class="grid-layout">
            <div class="form-card">
                <h2 class="card-title">Nuevo Empleado</h2>
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                        <i class="bi bi-<?php echo $tipo_mensaje === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?>"></i>
                        <span><?php echo htmlspecialchars($mensaje); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nombre">Nombre de usuario</label>
                        <input 
                            type="text" 
                            id="nombre" 
                            name="nombre" 
                            required
                            value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                            placeholder="Ej: juan.perez"
                        >
                    </div>

                    <div class="form-group">
                        <label for="apellido">Apellido</label>
                        <input 
                            type="text" 
                            id="apellido" 
                            name="apellido" 
                            required
                            value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>"
                            placeholder="Ej: Pérez García"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña (6 caracteres)</label>
                        <input 
                            type="text" 
                            id="password" 
                            name="password" 
                            required
                            maxlength="6"
                            placeholder="123456"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirmar contraseña</label>
                        <input 
                            type="text" 
                            id="password_confirm" 
                            name="password_confirm" 
                            required
                            maxlength="6"
                            placeholder="123456"
                        >
                    </div>

                    <div class="form-group">
                        <label for="rol">Rol</label>
                        <select id="rol" name="rol" required>
                            <option value="empleado">Empleado</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="bi bi-check-circle-fill"></i>
                        Registrar Empleado
                    </button>
                </form>
            </div>

            <div class="list-card">
                <h2 class="card-title">Lista de Empleados</h2>
                
                <?php if ($empleados && $empleados->num_rows > 0): ?>
                    <table class="empleados-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Apellido</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Fecha Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($emp = $empleados->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $emp['id_usuario']; ?></td>
                                    <td><?php echo htmlspecialchars($emp['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['apellido']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $emp['rol']; ?>">
                                            <?php echo strtoupper($emp['rol']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $emp['activo'] ? 'activo' : 'inactivo'; ?>">
                                            <?php echo $emp['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($emp['fecha_registro'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #999; padding: 20px;">No hay empleados registrados</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
