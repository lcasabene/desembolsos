<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion('Anticipos');

// Redirigir al menú moderno
header('Location: menu_anticipos_moderno.php');
exit;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menú Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container-fluid mt-3 px-2">
    <h2 class="text-center mb-4">Seleccione una opción para continuar:</h2>
    <div class="d-grid gap-3 col-12 col-sm-8 col-md-6 col-lg-4 mx-auto">
       
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
         <?php if ($_SESSION['user_role'] === 'Admin'): ?>
            <a href="modulos.php" class="btn btn-dark">Administrar Modulos</a>   
        <?php endif; ?>

        <!-- Cierre de Sesión -->
        <a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
