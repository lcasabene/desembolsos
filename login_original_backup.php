<?php
require_once __DIR__ . '/config/seguridad.php';
require_once __DIR__ . '/config/database.php';

// Establecer headers de seguridad
headers_seguridad();

$error = '';
$timeout = $_GET['timeout'] ?? '';
$security = $_GET['security'] ?? '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verificar_token_csrf($_POST['csrf_token'])) {
        $error = 'Solicitud inválida. Por favor recarga la página.';
    } else {
        $usuario = sanitizar_entrada($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($usuario === '' || $password === '') {
            $error = 'Por favor completá todos los campos.';
        } elseif (!validar_intentos_login($usuario)) {
            $error = 'Demasiados intentos fallidos. Por favor esperá 15 minutos.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
                $stmt->execute([$usuario]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Login exitoso - limpiar intentos
                    limpiar_intentos_login($usuario);
                    
                    // Regenerar ID de sesión
                    session_regenerate_id(true);
                    
                    // Iniciar sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['nombre'];
                    $_SESSION['user_role'] = $user['rol'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['monto_aprobacion'] = $user['monto_aprobacion'] ?? 0;
                    $_SESSION['login_time'] = time();

                    // Cargar módulos asignados
                    $stmtMod = $pdo->prepare("SELECT modulo FROM usuario_modulos WHERE usuario_id = ?");
                    $stmtMod->execute([$user['id']]);
                    $_SESSION['modulos'] = $stmtMod->fetchAll(PDO::FETCH_COLUMN);

                    // Redirigir
                    $redirect_url = $redirect ?: 'menu.php';
                    header('Location: ' . $redirect_url);
                    exit;
                } else {
                    // Login fallido - registrar intento
                    registrar_intento_fallido($usuario);
                    $error = 'Usuario o contraseña incorrectos.';
                }
            } catch (PDOException $e) {
                error_log('Error en login: ' . $e->getMessage());
                $error = 'Error al procesar el login. Por favor intentá más tarde.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        .security-notice {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="login-container d-flex align-items-center">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5">
            <div class="card login-card">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock text-primary" style="font-size: 3rem;"></i>
                        <h3 class="mt-3">Sistema Seguro</h3>
                        <p class="text-muted">Iniciá sesión de forma segura</p>
                    </div>

                    <?php if ($timeout): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-clock-history"></i>
                            <strong>Sesión expirada:</strong> Tu sesión ha expirado por inactividad. Por favor iniciá sesión nuevamente.
                        </div>
                    <?php endif; ?>

                    <?php if ($security): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-shield-exclamation"></i>
                            <strong>Alerta de seguridad:</strong> Se detectó una actividad sospechosa. Por favor iniciá sesión nuevamente.
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                        
                        <div class="mb-3">
                            <label for="usuario" class="form-label">
                                <i class="bi bi-envelope"></i> Email
                            </label>
                            <input type="email" 
                                   name="usuario" 
                                   id="usuario" 
                                   class="form-control form-control-lg" 
                                   required 
                                   autocomplete="username"
                                   value="<?= htmlspecialchars($usuario ?? '') ?>"
                                   placeholder="tu@email.com">
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock"></i> Contraseña
                            </label>
                            <input type="password" 
                                   name="password" 
                                   id="password" 
                                   class="form-control form-control-lg" 
                                   required 
                                   autocomplete="current-password"
                                   placeholder="•••••••••">
                        </div>
                        
                        <div class="security-notice text-white">
                            <small class="d-block">
                                <i class="bi bi-shield-check"></i>
                                Conexión segura con cifrado SSL/TLS
                            </small>
                            <small class="d-block">
                                <i class="bi bi-clock"></i>
                                Sesión expira después de 2 horas de inactividad
                            </small>
                        </div>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-box-arrow-in-right"></i> Ingresar Seguro
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Prevenir back button después de logout
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Limpiar formulario al recargar
    if (window.performance && window.performance.navigation.type === 1) {
        document.getElementById('usuario').value = '';
        document.getElementById('password').value = '';
    }
</script>
</body>
</html>
