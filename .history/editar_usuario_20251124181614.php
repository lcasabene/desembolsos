<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php'; // $pdo debe estar definido

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: usuarios.php");
    exit;
}

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute(array($id));
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header("Location: usuarios.php");
    exit;
}

// Obtener módulos disponibles desde tabla modulos (si existe)
try {
    $stmt_mod = $pdo->query("SELECT nombre FROM modulos ORDER BY nombre");
    $modulos_disponibles = $stmt_mod->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    // Fallback si no existe la tabla modulos
    $modulos_disponibles = array('Anticipos', 'Rendiciones', 'Reportes', 'Administración');
}

// Obtener módulos ya asignados al usuario
try {
    $stmt_um = $pdo->prepare("SELECT modulo FROM usuario_modulos WHERE usuario_id = ?");
    $stmt_um->execute(array($id));
    $modulos_usuario = $stmt_um->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $modulos_usuario = array();
}

$errores = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim(isset($_POST['nombre']) ? $_POST['nombre'] : $usuario['nombre']);
    $dni      = trim(isset($_POST['dni']) ? $_POST['dni'] : $usuario['dni']);
    $email    = trim(isset($_POST['email']) ? $_POST['email'] : $usuario['email']);
    $password = trim(isset($_POST['password']) ? $_POST['password'] : '');
    $rol      = trim(isset($_POST['rol']) ? $_POST['rol'] : $usuario['rol']);

    // Normalización de monto_aprobacion (ej: 1.234,56 -> 1234.56)
    if (isset($_POST['monto_aprobacion']) && $_POST['monto_aprobacion'] !== '') {
        $monto_str = str_replace('.', '', $_POST['monto_aprobacion']);  // quita separador de miles
        $monto_str = str_replace(',', '.', $monto_str);                 // coma decimal -> punto
        $monto_aprobacion = is_numeric($monto_str) ? (float)$monto_str : (float)$usuario['monto_aprobacion'];
    } else {
        $monto_aprobacion = (float)$usuario['monto_aprobacion'];
    }

    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    }
    if ($email === '') {
        $errores[] = 'El email/usuario es obligatorio.';
    }
    if (!in_array($rol, array('Solicitante','Aprobador','Admin'), true)) {
        $errores[] = 'Rol inválido.';
    }

    $modulos_seleccionados = isset($_POST['modulos']) && is_array($_POST['modulos'])
        ? $_POST['modulos']
        : array();

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql_upd = "UPDATE usuarios 
                            SET nombre = ?, dni = ?, email = ?, password = ?, rol = ?, monto_aprobacion = ? 
                            WHERE id = ?";
                $params_upd = array($nombre, $dni, $email, $hash, $rol, $monto_aprobacion, $id);
            } else {
                $sql_upd = "UPDATE usuarios 
                            SET nombre = ?, dni = ?, email = ?, rol = ?, monto_aprobacion = ? 
                            WHERE id = ?";
                $params_upd = array($nombre, $dni, $email, $rol, $monto_aprobacion, $id);
            }

            $stmt_upd = $pdo->prepare($sql_upd);
            $stmt_upd->execute($params_upd);

            // Actualizar módulos del usuario
            $pdo->prepare("DELETE FROM usuario_modulos WHERE usuario_id = ?")->execute(array($id));
            if (!empty($modulos_seleccionados)) {
                $stmt_ins = $pdo->prepare("INSERT INTO usuario_modulos (usuario_id, modulo) VALUES (?, ?)");
                foreach ($modulos_seleccionados as $m) {
                    $stmt_ins->execute(array($id, $m));
                }
            }

            $pdo->commit();

            header("Location: usuarios.php");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = 'Error al guardar los cambios: ' . $e->getMessage();
        }
    }

    // Si hubo errores, sobreescribimos $usuario con lo posteado para repintar el formulario
    $usuario['nombre']           = $nombre;
    $usuario['dni']              = $dni;
    $usuario['email']            = $email;
    $usuario['rol']              = $rol;
    $usuario['monto_aprobacion'] = $monto_aprobacion;
    $modulos_usuario             = $modulos_seleccionados;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.6.2/dist/select2-bootstrap4.min.css" rel="stylesheet" />
    <style>
        .select2-container--bootstrap4 .select2-selection--single {
            height: calc(2.5rem + 2px);
        }
        .select2-container--bootstrap4 .select2-selection__rendered {
            line-height: 2.5rem;
        }
        .select2-container--bootstrap4 .select2-selection__arrow {
            height: 2.5rem;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Editar Usuario</h1>
        <a href="usuarios.php" class="btn btn-secondary">Volver</a>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errores as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control" required
                       value="<?= htmlspecialchars($usuario['nombre']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">DNI</label>
                <input type="text" name="dni" class="form-control"
                       value="<?= htmlspecialchars($usuario['dni']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Email / Usuario</label>
                <input type="email" name="email" class="form-control" required
                       value="<?= htmlspecialchars($usuario['email']) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Nueva contraseña (opcional)</label>
                <input type="password" name="password" class="form-control"
                       placeholder="Dejar en blanco para no cambiar">
            </div>

            <div class="col-md-4">
                <label class="form-label">Rol</label>
                <select name="rol" id="rol" class="form-select" required>
                    <?php
                    $roles = array('Solicitante','Aprobador','Admin');
                    $rol_sel = isset($usuario['rol']) ? $usuario['rol'] : 'Solicitante';
                    foreach ($roles as $r):
                        $sel = ($rol_sel === $r) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= $sel ?>>
                            <?= htmlspecialchars($r) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Monto de aprobación</label>
                <input type="text" name="monto_aprobacion" class="form-control"
                       value="<?= htmlspecialchars(number_format((float)$usuario['monto_aprobacion'], 2, ',', '.')) ?>">
                <div class="form-text">Solo aplica si el rol es <strong>Aprobador</strong> o <strong>Admin</strong>.</div>
            </div>

            <div class="col-12">
                <label class="form-label">Módulos habilitados</label>
                <div>
                    <?php foreach ($modulos_disponibles as $modulo): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="modulos[]"
                                   value="<?= htmlspecialchars($modulo) ?>"
                                   <?= in_array($modulo, $modulos_usuario, true) ? 'checked' : '' ?>>
                            <label class="form-check-label">
                                <?= htmlspecialchars(str_replace('_', ' ', $modulo)) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){
    $('#rol').select2({
        theme: 'bootstrap4',
        placeholder: 'Seleccioná un rol',
        minimumResultsForSearch: 0,
        width: '100%'
    });
});
</script>
</body>
</html>
