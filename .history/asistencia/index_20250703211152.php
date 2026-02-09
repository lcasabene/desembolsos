<?php
session_start();
require_once '../includes/db.php';
require_once 'includes/funciones.php';
require_once '../includes/header.php';

if (!isset($_SESSION['usuario_id'])) {
    echo "<div class='alert alert-danger'>Sesión no iniciada.</div>";
    exit;
}

if (!tieneAccesoModulo('Asistencia')) {
    echo "<div class='alert alert-warning'>No tiene acceso al módulo de asistencia.</div>";
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$fecha_hoy = date('Y-m-d');
$asistencia = obtenerAsistenciaDelDia($pdo, $usuario_id, $fecha_hoy);
?>

<div class="container mt-5">
    <h3>Registro de Asistencia - <?= date('d/m/Y') ?></h3>
    <form action="registrar.php" method="post">
        <button name="entrada" class="btn btn-success" <?= ($asistencia && $asistencia['hora_entrada']) ? 'disabled' : '' ?>>Registrar Entrada</button>
        <button name="salida" class="btn btn-danger" <?= ($asistencia && $asistencia['hora_entrada'] && !$asistencia['hora_salida']) ? '' : 'disabled' ?>>Registrar Salida</button>
    </form>
    <p class="mt-3">
        <?php if ($asistencia && $asistencia['hora_entrada']) echo "Entrada: <strong>{$asistencia['hora_entrada']}</strong><br>"; ?>
        <?php if ($asistencia && $asistencia['hora_salida']) echo "Salida: <strong>{$asistencia['hora_salida']}</strong>"; ?>
    </p>
</div>

<?php require_once '../includes/footer.php'; ?>
