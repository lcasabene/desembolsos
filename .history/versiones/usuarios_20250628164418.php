<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

include 'config/database.php';

// Mostrar errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Procesar acciones: Dar de Baja y Reactivar
if (isset($_GET['eliminar_id'])) {
    $eliminar_id = $_GET['eliminar_id'];
    if ($eliminar_id != $_SESSION['user_id']) {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
            $stmt->execute([$eliminar_id]);
            header("Location: usuarios.php");
            exit;
        } catch (PDOException $e) {
            die("Error al dar de baja el usuario: " . $e->getMessage());
        }
    } else {
        echo "<div class='alert alert-warning'>No puedes eliminar tu propio usuario mientras estás logueado.</div>";
    }
}

if (isset($_GET['reactivar_id'])) {
    $reactivar_id = $_GET['reactivar_id'];
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?");
        $stmt->execute([$reactivar_id]);
        header("Location: usuarios.php");
        exit;
    } catch (PDOException $e) {
        die("Error al reactivar el usuario: " . $e->getMessage());
    }
}

// Filtro: Activos / Inactivos / Todos
$filtro = $_GET['filtro'] ?? 'activos'; // Por defecto muestra activos

switch ($filtro) {
    case 'inactivos':
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE activo = 0");
        break;
    case 'todos':
        $stmt = $pdo->prepare("SELECT * FROM usuarios");
        break;
    default: // activos
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE activo = 1");
        break;
}

$stmt->execute();
$usuarios = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>Administración de Usuarios</h2>
    <a href="menu.php" class="btn btn-secondary mb-3">Volver al Menú</a>

    <div class="mb-3">
        <strong>Filtrar:</strong>
        <a href="usuarios.php?filtro=activos" class="btn btn-outline-success btn-sm <?= ($filtro === 'activos') ? 'active' : '' ?>">Activos</a>
        <a href="usuarios.php?filtro=inactivos" class="btn btn-outline-danger btn-sm <?= ($filtro === 'inactivos') ? 'active' : '' ?>">Inactivos</a>
        <a href="usuarios.php?filtro=todos" class="btn btn-outline-primary btn-sm <?= ($filtro === 'todos') ? 'active' : '' ?>">Todos</a>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Monto Aprobación</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['nombre'] ?? '') ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($u['rol'] ?? '') ?></td>
                    <td>$<?= number_format($u['monto_aprobacion'], 2) ?></td>
                    <td><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></td>
                    <td>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <?php if ($u['activo']): ?>
                                <a href="editar_usuario.php?id=<?= $u['id'] ?>" class="btn btn-primary btn-sm">Editar</a>
                                <a href="usuarios.php?eliminar_id=<?= $u['id'] ?>&filtro=<?= $filtro ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de dar de baja este usuario?');">Dar de Baja</a>
                            <?php else: ?>
                                <a href="usuarios.php?reactivar_id=<?= $u['id'] ?>&filtro=<?= $filtro ?>" class="btn btn-success btn-sm" onclick="return confirm('¿Estás seguro de reactivar este usuario?');">Reactivar</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">No puedes eliminarte o reactivarte a ti mismo</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>