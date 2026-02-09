<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Menú Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>Bienvenido, <?= htmlspecialchars($_SESSION['user_name']) ?></h2>
    <p>Elige una opción para continuar:</p>
    <div class="d-grid gap-2 col-6 mx-auto">
        <!-- Solicitud de Desembolsos -->
        <?php if ($_SESSION['user_role'] === 'Solicitante'): ?>
            <a href="nueva_solicitud.php" class="btn btn-primary">Nueva Solicitud</a>
            <a href="rendicion.php" class="btn btn-success">Rendir Comprobantes</a>
        <?php endif; ?>

        <!-- Consulta de Solicitudes -->
        <a href="listado_solicitudes.php" class="btn btn-secondary">Consultar Solicitudes</a>

        <!-- Aprobaciones -->
        <?php if ($_SESSION['user_role'] === 'Aprobador' || $_SESSION['user_role'] === 'Admin'): ?>
            <a href="aprobaciones.php" class="btn btn-warning">Aprobaciones</a>
            <a href="revisar_rendiciones.php" class="btn btn-info">Revisión de Rendiciones</a>
        <?php endif; ?>

        <!-- Administración -->
        <?php if ($_SESSION['user_role'] === 'Admin'): ?>
            <a href="usuarios.php" class="btn btn-dark">Administrar Usuarios</a>
            <a href="dashboard_estadistico.php" class="btn btn-dark">Dashboard Estadístico</a>
        <?php endif; ?>

        <!-- Cierre de Sesión -->
        <a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
    </div>
</body>
</html>
