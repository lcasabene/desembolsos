<?php
// login.php - inicio de sesión responsivo basado en estructura original
session_start();

// Mostrar errores durante desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Conexión a la base de datos
require_once __DIR__ . '/config/database.php'; // retorna $pdo

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {
        $error = 'Por favor completá todos los campos.';
    } else {
        try {
            // Buscar usuario por email
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();

            if ($user && $user['activo'] == 1 && password_verify($password, $user['password'])) {
                // Iniciar sesión
                $_SESSION['user_id']             = $user['id'];
                $_SESSION['user_name']           = $user['nombre'];
                $_SESSION['user_role']           = $user['rol'];
                $_SESSION['monto_aprobacion']    = $user['monto_aprobacion'] ?? 0;
                header('Location: menu.php');
                exit;
            } else {
                $error = 'Usuario o contraseña incorrectos, o el usuario está inactivo.';
            }
        } catch (PDOException $e) {
            $error = 'Error al procesar el login: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Gestión Anticipos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center bg-light" style="min-height: 100vh;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="card-title text-center mb-4">Iniciar Sesión</h3>
            <?php if ($error): ?>
              <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
              <div class="mb-3">
                <label for="usuario" class="form-label">Usuario (Email)</label>
                <input type="text" name="usuario" id="usuario" class="form-control form-control-lg" required autofocus value="<?php echo htmlspecialchars($usuario ?? ''); ?>">
              </div>
              <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" name="password" id="password" class="form-control form-control-lg" required>
              </div>
              <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Ingresar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>