<?php
session_start();
require_once '../config/database.php';

// Validación básica de acceso
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Acceso denegado");
}

$sql = "SELECT s.*, u.nombre AS nombre_usuario 
        FROM asistencia_solicitudes_manual s
        JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.estado = 'PENDIENTE'
        ORDER BY s.fecha_solicitud DESC";

$stmt = $pdo->query($sql);
$solicitudes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Solicitudes Pendientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

    <h3>Solicitudes de Corrección Pendientes</h3>

    <a href="../menu_colaboradores.php" class="btn btn-secondary mb-3">← Volver al Menú Principal</a>

    <?php if (count($solicitudes) === 0): ?>
        <div class="alert alert-info mt-4">No hay solicitudes pendientes.</div>
    <?php else: ?>
        <table class="table table-bordered mt-4">
            <thead class="table-light">
                <tr>
                    <th>Usuario</th>
                    <th>Fecha</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Comentario</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['nombre_usuario']) ?></td>
                        <td><?= $s['fecha'] ?></td>
                        <td><?= $s['hora_entrada'] ?: '-' ?></td>
                        <td><?= $s['hora_salida'] ?: '-' ?></td>
                        <td><?= nl2br(htmlspecialchars($s['comentario'])) ?></td>
                        <td>
                            <form action="resolver_solicitud.php" method="post" class="d-flex flex-column gap-2">
                                <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                <button name="accion" value="aprobar" class="btn btn-success btn-sm">Aprobar</button>
                                <button name="accion" value="rechazar" class="btn btn-danger btn-sm">Rechazar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>
