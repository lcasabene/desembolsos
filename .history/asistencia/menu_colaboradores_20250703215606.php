<?php
session_start();
require_once 'includes/header.php';
?>

<div class="container mt-5">
    <h3>Menú de asistencia</h3>

    <?php if (in_array('asistencia', $_SESSION['modulos'])): ?>
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                Módulo de asistencia – Registro de Asistencia
            </div>
            <div class="card-body d-grid gap-2">
                <a href="../asistencia/index.php" class="btn btn-outline-success btn-lg">Registrar Entrada / Salida</a>
                <a href="../asistencia/listado.php" class="btn btn-outline-primary btn-lg">Ver Mis Asistencias</a>
                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                    <a href="../asistencia/admin/index.php" class="btn btn-outline-warning btn-lg">Panel Administrador</a>
                    <a href="../asistencia/admin/log.php" class="btn btn-outline-dark btn-lg">Historial de Cambios</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mt-4">No tiene habilitado el módulo de asistencia.</div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
