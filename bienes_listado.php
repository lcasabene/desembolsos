<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion('Bienes');

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Usuario';
$isAdmin = ($user_role === 'Admin');

// Traer préstamos
$params = [];

if ($isAdmin) {
    $sql = "SELECT p.*, u.nombre AS solicitante
            FROM prestamos_bienes p
            LEFT JOIN usuarios u ON u.id = p.usuario_solicitante_id
            WHERE p.activo = 1
            ORDER BY p.fecha_solicitud DESC";
} else {
    $sql = "SELECT p.*, u.nombre AS solicitante
            FROM prestamos_bienes p
            LEFT JOIN usuarios u ON u.id = p.usuario_solicitante_id
            WHERE p.activo = 1 AND p.usuario_solicitante_id = ?
            ORDER BY p.fecha_solicitud DESC";
    $params[] = $user_id;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Préstamos de Bienes - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --card-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            min-height: 100vh;
        }

        .modern-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .table-modern {
            background: transparent;
        }

        .table-modern thead th {
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table-modern tbody tr {
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }

        .table-modern tbody tr:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .btn-modern {
            border-radius: 10px;
            font-weight: 500;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .navbar-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 5px 30px rgba(0,0,0,0.1);
        }

        .brand-text {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-modern sticky-top">
        <div class="container">
            <a class="navbar-brand brand-text" href="menu_bienes_moderno.php">
                <i class="bi bi-box"></i> Bienes
            </a>
            <div class="navbar-nav ms-auto">
                <a href="menu_bienes_moderno.php" class="btn btn-outline-secondary btn-modern">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="modern-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="bi bi-list-ul"></i> Listado de Préstamos
                </h2>
                <a href="bienes_form.php" class="btn btn-success btn-modern">
                    <i class="bi bi-plus-circle"></i> Nuevo Préstamo
                </a>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_GET['msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover table-modern">
                    <thead>
                        <tr>
                            <th>Nº Préstamo</th>
                            <th>Fecha</th>
                            <th>Solicitante</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prestamos as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['nro_prestamo']) ?></strong></td>
                                <td><?= date('d/m/Y', strtotime($p['fecha_solicitud'])) ?></td>
                                <td><?= htmlspecialchars($p['solicitante']) ?></td>
                                <td><?= htmlspecialchars($p['descripcion_bien']) ?></td>
                                <td>$<?= number_format($p['cantidad'], 2, ',', '.') ?></td>
                                <td><?= badgeEstado($p['estado'], (int)$p['devuelto_pendiente_aprobacion']) ?></td>
                                <td>
                                    <?php
                                    $esSolicitante = ($p['usuario_solicitante_id'] == $user_id);
                                    $estado = $p['estado'];
                                    $devPend = (int)$p['devuelto_pendiente_aprobacion'] === 1;

                                    // Editar/Eliminar: usuario solicitante o admin, solo pendiente
                                    if ($estado === 'pendiente' && ($esSolicitante || $isAdmin)) {
                                        echo '<a href="bienes_form.php?id=' . (int)$p['id'] . '" class="btn btn-sm btn-primary btn-modern me-1">Editar</a>';
                                        echo '<a href="bienes_eliminar.php?id=' . (int)$p['id'] . '" class="btn btn-sm btn-danger btn-modern me-1" onclick="return confirm(\'¿Seguro que desea eliminar esta solicitud?\');">Eliminar</a>';
                                    }

                                    // Acciones de admin
                                    if ($isAdmin) {
                                        if ($estado === 'pendiente') {
                                            echo '<a href="bienes_cambiar_estado.php?action=autorizar&id=' . (int)$p['id'] . '" class="btn btn-sm btn-success btn-modern me-1">Autorizar</a>';
                                            echo '<a href="bienes_cambiar_estado.php?action=rechazar&id=' . (int)$p['id'] . '" class="btn btn-sm btn-outline-danger btn-modern me-1">Rechazar</a>';
                                        } elseif ($estado === 'autorizado') {
                                            echo '<a href="bienes_cambiar_estado.php?action=entregar&id=' . (int)$p['id'] . '" class="btn btn-sm btn-info btn-modern me-1">Entregar</a>';
                                        } elseif ($estado === 'entregado' && $devPend) {
                                            echo '<a href="bienes_cambiar_estado.php?action=confirmar_devolucion&id=' . (int)$p['id'] . '" class="btn btn-sm btn-success btn-modern me-1">Confirmar Devolución</a>';
                                        }
                                    }

                                    // Devolver para solicitante
                                    if ($esSolicitante && $estado === 'entregado' && !$devPend) {
                                        echo '<a href="bienes_cambiar_estado.php?action=solicitar_devolucion&id=' . (int)$p['id'] . '" class="btn btn-sm btn-warning btn-modern" onclick="return confirm(\'¿Confirmás que devolvés el bien?\');">Devolver</a>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
