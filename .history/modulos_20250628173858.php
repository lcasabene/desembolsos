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
        $stmt = $pdo->prepare("INSERT INTO modulos (nombre) VALUES (?)");
        $stmt->execute([$nombre]);
    }
    header("Location: modulos.php");
    exit;
}


// Eliminación lógica (activo = 0)
if (isset($_GET['eliminar'])) {
    $stmt = $pdo->prepare("UPDATE modulos SET activo = 0 WHERE id = ?");
    $stmt->execute([$_GET['eliminar']]);
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
       <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="menu.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left-circle"></i> Volver al menú
        </a>
       
    </div>
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
                <th>Editar</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($modulos as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                    <td>
                        <form method="POST" class="d-flex">
                            <input type="hidden" name="modulo_id" value="<?= $m['id'] ?>">
                            <input type="text" name="nuevo_nombre" class="form-control form-control-sm me-2" value="<?= htmlspecialchars($m['nombre']) ?>" required>
                            <button type="submit" name="editar" class="btn btn-sm btn-primary">Guardar</button>
                        </form>
                    </td>
                    <td>
                        <a href="modulos.php?eliminar=<?= $m['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este módulo?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <a href="usuarios.php" class="btn btn-secondary mt-3">Volver a usuarios</a>
</body>
</html>
