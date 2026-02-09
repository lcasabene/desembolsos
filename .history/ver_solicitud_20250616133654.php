<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config/database.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo "<div class='alert alert-danger'>ID de solicitud no especificado.</div>";
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, u.nombre AS nombre_usuario, d.nombre AS nombre_departamento
    FROM solicitudes s
    JOIN usuarios u ON s.usuario_id = u.id
    LEFT JOIN departamentos d ON s.departamento_id = d.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    echo "<div class='alert alert-danger'>Solicitud no encontrada.</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Solicitud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h3>Detalle de Solicitud #<?= $solicitud['id'] ?></h3>
    <a href="listado_solicitudes.php" class="btn btn-secondary mb-3">Volver</a>

    <table class="table table-bordered">
        <tr><th>Fecha</th><td><?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])) ?></td></tr>
        <tr><th>Solicitante</th><td><?= htmlspecialchars($solicitud['nombre_usuario']) ?></td></tr>
        <tr><th>Departamento</th><td><?= htmlspecialchars($solicitud['nombre_departamento']) ?></td></tr>
        <tr><th>Modalidad</th><td><?= $solicitud['modalidad'] ?></td></tr>
        <tr><th>Alias / CBU</th><td><?= htmlspecialchars($solicitud['alias_cbu'] ?? '-') ?></td></tr>
        <tr><th>Monto</th><td>$<?= number_format($solicitud['monto'], 2, ',', '.') ?></td></tr>
        <tr><th>Fecha Disponibilidad</th><td><?= date('d/m/Y', strtotime($solicitud['fecha_disponibilidad'])) ?></td></tr>
        <tr><th>Estado</th><td><?= $solicitud['estado'] ?></td></tr>
        <tr><th>Observaciones</th><td><?= nl2br(htmlspecialchars($solicitud['observaciones'])) ?></td></tr>
        <?php if ($solicitud['modalidad'] === 'Reintegro' && $solicitud['archivo_reintegro']): ?>
        <tr>
            <th>Archivo Reintegro</th>
            <td><a href="uploads_reintegros/<?= urlencode($solicitud['archivo_reintegro']) ?>" target="_blank">Ver Archivo</a></td>
        </tr>
        <tr>
            <th>Cantidad de Comprobantes</th>
            <td><?= (int)$solicitud['cantidad_comprobantes'] ?></td>
        </tr>
        <?php endif; ?>
    </table>
</body>
</html>
