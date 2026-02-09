<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Aprobador')) {
    header("Location: login.php");
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config/database.php';

if (!isset($_GET['id'])) {
    die("ID de rendición no especificado.");
}

$rendicion_id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT r.*, s.id AS solicitud_id, s.monto AS monto_solicitud, u.nombre AS solicitante
    FROM rendiciones r
    JOIN solicitudes s ON r.solicitud_id = s.id
    JOIN usuarios u ON s.usuario_id = u.id
    WHERE r.id = ?
");
$stmt->execute([$rendicion_id]);
$rendicion = $stmt->fetch();

if (!$rendicion) {
    die("Rendición no encontrada.");
}

$stmt = $pdo->prepare("
    SELECT * 
    FROM comprobantes
    WHERE rendicion_id = ?
");
$stmt->execute([$rendicion_id]);
$comprobantes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Rendición</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>Detalle de Rendición #<?= $rendicion['id'] ?></h2>
    <a href="revisar_rendiciones.php" class="btn btn-secondary mb-3">Volver al Listado</a>

    <p><strong>Solicitante:</strong> <?= htmlspecialchars($rendicion['solicitante']) ?></p>
    <p><strong>Solicitud Asociada:</strong> #<?= $rendicion['solicitud_id'] ?> - Monto Solicitado: $<?= number_format($rendicion['monto_solicitud'], 2) ?></p>
    <p><strong>Fecha de Rendición:</strong> <?= $rendicion['fecha_rendicion'] ?></p>
    <p><strong>Observaciones:</strong> <?= htmlspecialchars($rendicion['observaciones']) ?></p>
    <p><strong>Estado de Rendición:</strong> <?= $rendicion['estado_rendicion'] ?></p>

    <h5>Comprobantes Adjuntos:</h5>
    <?php if (empty($comprobantes)): ?>
        <div class="alert alert-info">No hay comprobantes registrados para esta rendición.</div>
    <?php else: ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Monto</th>
                    <th>Archivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comprobantes as $c): ?>
                    <tr class="<?= $c['tipo_comprobante'] === 'Reintegro' ? 'table-warning' : '' ?>">
                        <td><?= htmlspecialchars($c['tipo_comprobante']) ?></td>
                        <td>$<?= number_format($c['importe'], 2) ?></td>
                        <td><a href="<?= htmlspecialchars($c['archivo']) ?>" target="_blank">Ver Archivo</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
