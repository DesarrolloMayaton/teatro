<?php
session_start();

// Si ya está logueado, redirigir al sistema
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($nombre) || empty($password)) {
        $error = 'Por favor, ingrese nombre y contraseña';
    } else {
        // Buscar usuario en la base de datos
        $stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, password, rol, activo FROM usuarios WHERE nombre = ? AND activo = 1");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $usuario = $result->fetch_assoc();
            
            // Verificar contraseña (comparación directa - en producción usar password_verify)
            if ($password === $usuario['password']) {
                // Login exitoso - crear sesión
                $_SESSION['usuario_id'] = $usuario['id_usuario'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_apellido'] = $usuario['apellido'];
                $_SESSION['usuario_rol'] = $usuario['rol'];
                $_SESSION['login_time'] = time();
                
                header("Location: index.php");
                exit();
            } else {
                $error = 'Contraseña incorrecta';
            }
        } else {
            $error = 'Usuario no encontrado o inactivo';
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Teatro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .login-header i {
            font-size: 3em;
            margin-bottom: 10px;
        }

        .login-header h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }

        .login-header p {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 0.95em;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 1.1em;
        }

        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            padding: 20px 30px 30px;
            color: #666;
            font-size: 0.85em;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .remember-me label {
            cursor: pointer;
            font-weight: normal;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="bi bi-theatre-masks"></i>
            <h1>Sistema de Teatro</h1>
            <p>Bienvenido, ingrese sus credenciales</p>
        </div>
        
        <form method="POST" action="" class="login-body">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="nombre">Nombre de usuario</label>
                <div class="input-wrapper">
                    <i class="bi bi-person-fill"></i>
                    <input 
                        type="text" 
                        id="nombre" 
                        name="nombre" 
                        required 
                        autofocus
                        value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                        placeholder="Ingrese su nombre"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock-fill"></i>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        placeholder="Ingrese su contraseña"
                    >
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i>
                Iniciar Sesión
            </button>
        </form>
        
        <div class="login-footer">
            <p>© 2025 Sistema de Teatro - Todos los derechos reservados</p>
        </div>
    </div>

    <script>
        // Auto-focus en el campo de contraseña si hay error de password
        <?php if ($error === 'Contraseña incorrecta'): ?>
            document.getElementById('password').focus();
            document.getElementById('password').select();
        <?php endif; ?>
    </script>
</body>
</html>
