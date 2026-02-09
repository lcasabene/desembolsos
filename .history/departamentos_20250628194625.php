<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: acceso_denegado.php");
    exit;
}

require_once 'config/database.php';

// Crear
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre']);
    if ($nombre !== '') {
        $stmt = $pdo->prepare("INSERT INTO departamentos (nombre, activo) VALUES (?, 1)");
        $stmt->execute([$nombre]);
        header("Location: departamentos.php");
        exit;
    }
}

// Editar nombre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = $_POST['id'];
    $nombre = trim($_POST['nombre']);
    if ($nombre !== '') {
        $stmt = $pdo->prepare("UPDATE departamentos SET nombre = ? WHERE id = ?");
        $stmt->execute([$nombre, $id]);
        header("Location: departamentos.php");
        exit;
    }
}

// Baja lógica
if (isset($_GET['eliminar'])) {
    $stmt = $pdo->prepare("UPDATE departamentos SET activo = 0 WHERE id = ?");
    $stmt->execute([$_GET['eliminar']]);
    header("Location: departamentos.php");
    exit;
}

// Reactivar
if (isset($_GET['activar'])) {
    $stmt = $pdo->prepare("UPDATE departamentos SET activo = 1 WHERE id = ?");
    $stmt->execute([$_GET['activar']]);
    header("Location: departamentos.php");
    exit;
}

// Filtro de estado
$filtro = $_GET['filtro'] ?? 'activos';
switch ($filtro) {
    case 'inactivos':
        $stmt = $pdo->prepare("SELECT * FROM departamentos WHERE activo = 0 ORDER BY nombre");
        break;
    case 'todos':
        $stmt = $pdo->prepare("SELECT * FROM departamentos ORDER BY nombre");
        break;
    default:
        $stmt = $pdo->prepare("SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre");
}
$stmt->execute();
$departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Departamentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h2>Gestión de Departamentos</h2>

    <form method="POST" class="row g-3 my-4">
        <div class="col-md-6">
            <input type="text" name="nombre" placeholder="Nuevo departamento" class="form-control" required>
        </div>
        <div class="col-auto">
            <button type="submit" name="crear" class="btn btn-success">Agregar</button>
        </div>
    </form>

    <div class="mb-3">
        <form method="GET" class="d-inline">
            <label for="filtro">Filtrar:</label>
            <select name="filtro" onchange="this.form.submit()" class="form-select form-select-sm d-inline w-auto ms-2">
                <option value="activos" <?= $filtro === 'activos' ? 'selected' : '' ?>>Activos</option>
                <option value="inactivos" <?= $filtro === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                <option value="todos" <?= $filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
            </select>
        </form>
    </div>

    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Estado</th>
                <th style="width: 200px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($departamentos as $dep): ?>
            <tr>
                <form method="POST" class="row gx-1">
                    <input type="hidden" name="id" value="<?= $dep['id'] ?>">
                    <td>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($dep['nombre']) ?>" class="form-control" required>
                    </td>
                    <td><?= $dep['activo'] ? 'Activo' : 'Inactivo' ?></td>
                    <td>
                        <button type="submit" name="editar" class="btn btn-sm btn-primary">Guardar</button>
                        <?php if ($dep['activo']): ?>
                            <a href="?eliminar=<?= $dep['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Desactivar departamento?')">Desactivar</a>
                        <?php else: ?>
                            <a href="?activar=<?= $dep['id'] ?>" class="btn btn-sm btn-secondary">Reactivar</a>
                        <?php endif; ?>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
