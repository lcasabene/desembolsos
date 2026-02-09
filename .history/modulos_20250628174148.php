<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config/database.php';

// Crear módulo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre']);
    if ($nombre) {
        $stmt = $pdo->prepare("INSERT INTO modulos (nombre, activo) VALUES (?, 1)");
        $stmt->execute([$nombre]);
    }
    header("Location: modulos.php");
    exit;
}

// Editar módulo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $nuevo_nombre = trim($_POST['nuevo_nombre']);
    $modulo_id = $_POST['modulo_id'];
    if ($nuevo_nombre && $modulo_id) {
        $stmt = $pdo->prepare("UPDATE modulos SET nombre = ? WHERE id = ?");
        $stmt->execute([$nuevo_nombre, $modulo_id]);
    }
    header("Location: modulos.php");
    exit;
}

// Eliminación lógica
if (isset($_GET['eliminar'])) {
    $stmt = $pdo->prepare("UPDATE modulos SET activo = 0 WHERE id = ?");
    $stmt->execute([$_GET['eliminar']]);
    header("Location: modulos.php");
    exit;
}

// Reactivar módulo
if (isset($_GET['activar'])) {
    $stmt = $pdo->prepare("UPDATE modulos SET activo = 1 WHERE id = ?");
    $stmt->execute([$_GET['activar']]);
    header("Location: modulos.php");
    exit;
}

// Obtener todos los módulos
$stmt = $pdo->query("SELECT * FROM modulos ORDER BY nombre");
$modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Módulos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
    <h3>Administración de Módulos</h3>

    <form method="POST" class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label">Nuevo módulo</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="col-md-2 align-self-end">
            <button type="submit" name="crear" class="btn btn-success">Agregar</button>
        </div>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Nombre del módulo</th>
                <th>Estado</th>
                <th>Editar</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($modulos as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                    <td><?= $m['activo'] ? 'Activo' : 'Inactivo' ?></td>
                    <td>
                        <form method="POST" class="d-flex">
                            <input type="hidden" name="modulo_id" value="<?= $m['id'] ?>">
                            <input type="text" name="nuevo_nombre" class="form-control form-control-sm me-2" value="<?= htmlspecialchars($m['nombre']) ?>" required>
                            <button type="submit" name="editar" class="btn btn-sm btn-primary">Guardar</button>
                        </form>
                    </td>
                    <td>
                        <?php if ($m['activo']): ?>
                            <a href="modulos.php?eliminar=<?= $m['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('¿Desactivar este módulo?')">Desactivar</a>
                        <?php else: ?>
                            <a href="modulos.php?activar=<?= $m['id'] ?>" class="btn btn-sm btn-success">Reactivar</a>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <a href="usuarios.php" class="btn btn-secondary mt-3">Volver a usuarios</a>
</body>
</html>
