<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$modulos = $_SESSION['modulos'] ?? [];
$nombre  = $_SESSION['user_name'] ?? 'Usuario';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Menú Principal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Bienvenido, <?= htmlspecialchars($nombre) ?></h2>

    <div class="d-grid gap-3 col-12 col-md-6 mx-auto">
        <?php if (in_array('Anticipos', $modulos)): ?>
            <a href="menu_anticipos.php" class="btn btn-outline-primary btn-lg">Anticipos y Rendiciones</a>
        <?php endif; ?>

        <?php if (in_array('Instalaciones', $modulos)): ?>
            <a href="menu_instalaciones.php" class="btn btn-outline-success btn-lg">Uso de Instalaciones</a>
        <?php endif; ?>

        <?php if (in_array('Colaboradores', $modulos)): ?>
            <a href="asistencia/menu_colaboradores.php" class="btn btn-outline-warning btn-lg">Registro de Colaboradores</a>
        <?php endif; ?>
        <?php if (in_array('Bienes', $modulos)): ?>
            <a href="prestamos_listado.php" class="btn btn-outline-warning btn-lg">Administracion de Bienes</a>
        <?php endif; ?>

        <?php if ($_SESSION['user_role'] === 'Admin'): ?>
            <a href="usuarios.php" class="btn btn-outline-dark btn-lg">Administrar Usuarios</a>
            <a href="modulos.php" class="btn btn-outline-dark btn-lg">Administrar Módulos</a>
             <a href="departamentos.php" class="btn btn-outline-dark btn-lg">Administrar Departamentos</a>
        <?php endif; ?>

        <a href="logout.php" class="btn btn-outline-danger btn-lg">Cerrar Sesión</a>
    </div>
</div>
</body>
</html>
