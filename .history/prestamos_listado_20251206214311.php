<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Usuario';

$isAdmin = ($user_role === 'Admin');

// Traer préstamos
if ($isAdmin) {
    $sql = "SELECT p.*, u.nombre AS solicitante
            FROM prestamos_bienes p
            LEFT JOIN usuarios u ON u.id = p.usuario_solicitante_id
            WHERE p.activo = 1
            ORDER BY p.fecha_solicitud DESC";
    $stmt = $pdo->prepare($sql);
} else {
    $sql = "SELECT p.*, u.nombre AS solicitante
            FROM prestamos_bienes p
            LEFT JOIN usuarios u ON u.id = p.usuario_solicitante_id
            WHERE p.activo = 1 AND p.usuario_solicitante_id = ?
            ORDER BY p.fecha_solicitud DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->close();

function badgeEstado($estado, $devueltoPendiente) {
    if ($devueltoPendiente && $estado === 'entregado') {
        return '<span class="badge bg-warning">Devolución pendiente</span>';
    }
    switch ($estado) {
        case 'pendiente':
            return '<span class="badge bg-secondary">Pendiente</span>';
        case 'autorizado':
            return '<span class="badge bg-primary">Autorizado</span>';
        case 'entregado':
            return '<span class="badge bg-info text-dark">Entregado</span>';
        case 'devuelto':
            return '<span class="badge bg-success">Devuelto</span>';
        case 'rechazado':
            return '<span class="badge bg-danger">Rechazado</span>';
        default:
            return '<span class="badge bg-light text-dark">' . htmlspecialchars($estado) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Préstamos de bienes</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
</head>
<body>
<?php include 'menu_instalaciones.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Préstamos de bienes</h3>
        <a href="prestamos_form.php" class="btn btn-success">Nueva solicitud</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <table id="tablaPrestamos" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Nº Préstamo</th>
                <th>Fecha solicitud</th>
                <th>Solicitante</th>
                <th>Cantidad</th>
                <th>Descripción</th>
                <th>Fecha necesaria</th>
                <th>Tiempo</th>
                <th>Estado</th>
                <th style="width: 220px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prestamos as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['nro_prestamo']); ?></td>
                    <td><?php echo htmlspecialchars($p['fecha_solicitud']); ?></td>
                    <td><?php echo htmlspecialchars($p['solicitante']); ?></td>
                    <td><?php echo number_format($p['cantidad'], 2, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($p['descripcion_bien']); ?></td>
                    <td><?php echo htmlspecialchars($p['fecha_necesita_desde']); ?></td>
                    <td>
                        <?php
                        if ($p['tiempo_tipo'] === 'indeterminado') {
                            echo 'Indeterminado';
                        } else {
                            echo 'Determinado';
                            if (!empty($p['fecha_estimada_devolucion'])) {
                                echo '<br><small>Hasta: ' . htmlspecialchars($p['fecha_estimada_devolucion']) . '</small>';
                            }
                        }
                        ?>
                    </td>
                    <td><?php echo badgeEstado($p['estado'], (int)$p['devuelto_pendiente_aprobacion']); ?></td>
                    <td>
                        <?php
                        $esSolicitante = ($p['usuario_solicitante_id'] == $user_id);
                        $estado = $p['estado'];
                        $devPend = (int)$p['devuelto_pendiente_aprobacion'] === 1;

                        // Editar / eliminar: usuario solicitante o admin, solo pendiente
                        if ($estado === 'pendiente' && ($esSolicitante || $isAdmin)) {
                            echo '<a href="prestamos_form.php?id=' . (int)$p['id'] . '" class="btn btn-sm btn-primary me-1">Editar</a>';
                            echo '<a href="prestamos_eliminar.php?id=' . (int)$p['id'] . '" class="btn btn-sm btn-danger me-1" onclick="return confirm(\'¿Seguro que desea eliminar esta solicitud?\');">Eliminar</a>';
                        }

                        // Acciones de admin
                        if ($isAdmin) {
                            if ($estado === 'pendiente') {
                                echo '<a href="prestamos_cambiar_estado.php?action=autorizar&id=' . (int)$p['id'] . '" class="btn btn-sm btn-success me-1">Autorizar</a>';
                                echo '<a href="prestamos_cambiar_estado.php?action=rechazar&id=' . (int)$p['id'] . '" class="btn btn-sm btn-outline-danger me-1">Rechazar</a>';
                            } elseif ($estado === 'autorizado') {
                                echo '<a href="prestamos_cambiar_estado.php?action=entregar&id=' . (int)$p['id'] . '" class="btn btn-sm btn-info me-1">Marcar entregado</a>';
                            } elseif ($estado === 'entregado' && $devPend) {
                                echo '<a href="prestamos_cambiar_estado.php?action=confirmar_devolucion&id=' . (int)$p['id'] . '" class="btn btn-sm btn-success me-1">Confirmar devolución</a>';
                            }
                        }

                        // Acción "Devolver" para solicitante
                        if ($esSolicitante && $estado === 'entregado' && !$devPend) {
                            echo '<a href="prestamos_cambiar_estado.php?action=solicitar_devolucion&id=' . (int)$p['id'] . '" class="btn btn-sm btn-warning" onclick="return confirm(\'¿Confirmás que devolvés el bien?\');">Devolver</a>';
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaPrestamos').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        },
        order: [[1, 'desc']]
    });
});
</script>
</body>
</html>
