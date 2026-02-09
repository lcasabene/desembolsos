<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

// Verificar acceso al módulo
$modulos = $_SESSION['modulos'] ?? [];
$user_role = $_SESSION['user_role'] ?? '';
if (!in_array('Instalaciones', $modulos)) {
    header("Location: ../acceso_denegado.php");
    exit;
}

// Determinar nivel de acceso
$es_admin_o_portero = ($user_role === 'Admin' || $user_role === 'Portero');
$usuario_id = $_SESSION['user_id'];

// Procesar cancelación (solo usuarios regulares, no porteros ni admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancelar' && !$es_admin_o_portero) {
    $reserva_id = $_POST['reserva_id'];
    
    // Verificar que la reserva pertenezca al usuario y esté pendiente o aprobada
    $stmt = $pdo->prepare("SELECT * FROM reservas WHERE id = ? AND usuario_id = ? AND estado IN ('pendiente', 'aprobada')");
    $stmt->execute([$reserva_id, $usuario_id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reserva) {
        // Verificar que no sea una reserva para hoy o pasado
        $fecha_reserva = new DateTime($reserva['fecha']);
        $hoy = new DateTime();
        $hoy->setTime(0, 0, 0);
        
        if ($fecha_reserva > $hoy) {
            $stmt = $pdo->prepare("UPDATE reservas SET estado = 'cancelada', observaciones = CONCAT(IFNULL(observaciones, ''), '\nCancelada por usuario el: ', NOW()) WHERE id = ?");
            $stmt->execute([$reserva_id]);
            $mensaje = "Reserva cancelada exitosamente";
        } else {
            $error = "No se pueden cancelar reservas para hoy o fechas pasadas";
        }
    } else {
        $error = "Reserva no encontrada o no tienes permisos para cancelarla";
    }
}

// Obtener reservas (todas si es admin/portero, solo las del usuario si es regular)
$where_clause = $es_admin_o_portero ? "" : "WHERE r.usuario_id = ?";
$params = $es_admin_o_portero ? [] : [$usuario_id];

$stmt = $pdo->prepare("
    SELECT r.*, s.nombre as salon_nombre, s.numero as salon_numero,
           u.nombre as usuario_nombre
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    " . ($es_admin_o_portero ? "JOIN usuarios u ON r.usuario_id = u.id" : "") . "
    $where_clause
    ORDER BY r.fecha DESC, r.hora_inicio DESC
");
$stmt->execute($params);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $es_admin_o_portero ? 'Todas las Reservas' : 'Mis Reservas' ?> - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../menu_instalaciones.php">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-list-check"></i> Mis Reservas</h2>
            <a href="nueva_reserva.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nueva Reserva
            </a>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas rápidas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-primary">
                            <?= count(array_filter($reservas, fn($r) => $r['estado'] === 'aprobada' && $r['fecha'] >= date('Y-m-d'))) ?>
                        </h5>
                        <p class="card-text">Reservas Activas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning">
                            <?= count(array_filter($reservas, fn($r) => $r['estado'] === 'pendiente')) ?>
                        </h5>
                        <p class="card-text">Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-info">
                            <?= count(array_filter($reservas, fn($r) => $r['estado'] === 'aprobada' && $r['fecha'] === date('Y-m-d'))) ?>
                        </h5>
                        <p class="card-text">Para Hoy</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-secondary">
                            <?= count(array_filter($reservas, fn($r) => $r['estado'] === 'cancelada')) ?>
                        </h5>
                        <p class="card-text">Canceladas</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <table id="tablaReservas" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Salón</th>
                            <th>Horario</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservas as $reserva): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($reserva['fecha'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($reserva['salon_numero']) ?> - 
                                    <?= htmlspecialchars($reserva['salon_nombre']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($reserva['hora_inicio']) ?> - <?= htmlspecialchars($reserva['hora_fin']) ?>
                                </td>
                                <td><?= htmlspecialchars($reserva['motivo']) ?></td>
                                <td>
                                    <?php
                                    $estado_class = match($reserva['estado']) {
                                        'aprobada' => 'success',
                                        'pendiente' => 'warning',
                                        'rechazada' => 'danger',
                                        'cancelada' => 'secondary',
                                        default => 'light'
                                    };
                                    $estado_icon = match($reserva['estado']) {
                                        'aprobada' => 'check-circle',
                                        'pendiente' => 'clock',
                                        'rechazada' => 'x-circle',
                                        'cancelada' => 'x-circle',
                                        default => 'question-circle'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $estado_class ?>">
                                        <i class="bi bi-<?= $estado_icon ?>"></i>
                                        <?= htmlspecialchars($reserva['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($reserva['estado'] === 'pendiente' || $reserva['estado'] === 'aprobada'): ?>
                                        <?php 
                                        $fecha_reserva = new DateTime($reserva['fecha']);
                                        $hoy = new DateTime();
                                        $hoy->setTime(0, 0, 0);
                                        
                                        if ($fecha_reserva > $hoy): 
                                        ?>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="cancelarReserva(<?= $reserva['id'] ?>, '<?= htmlspecialchars($reserva['motivo']) ?>')">
                                                <i class="bi bi-x-circle"></i> Cancelar
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($reserva['estado'] === 'aprobada' && $reserva['fecha'] >= date('Y-m-d')): ?>
                                        <button class="btn btn-sm btn-outline-info" onclick="verDetalles(<?= htmlspecialchars(json_encode($reserva)) ?>)">
                                            <i class="bi bi-eye"></i> Ver
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de detalles -->
    <div class="modal fade" id="modalDetalles" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles de la Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detallesContenido">
                    <!-- Contenido dinámico -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario para cancelación -->
    <form id="formCancelar" method="POST" style="display: none;">
        <input type="hidden" name="action" value="cancelar">
        <input type="hidden" name="reserva_id" id="cancelarReservaId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tablaReservas').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                responsive: true,
                order: [[0, 'desc']] // Ordenar por fecha descendente
            });
        });

        function cancelarReserva(id, motivo) {
            if (confirm(`¿Estás seguro de cancelar la reserva "${motivo}"?`)) {
                document.getElementById('cancelarReservaId').value = id;
                document.getElementById('formCancelar').submit();
            }
        }

        function verDetalles(reserva) {
            const contenido = `
                <div class="row">
                    <div class="col-6"><strong>Fecha:</strong></div>
                    <div class="col-6">${new Date(reserva.fecha).toLocaleDateString('es-ES')}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-6"><strong>Salón:</strong></div>
                    <div class="col-6">${reserva.salon_numero} - ${reserva.salon_nombre}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-6"><strong>Horario:</strong></div>
                    <div class="col-6">${reserva.hora_inicio} - ${reserva.hora_fin}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-6"><strong>Motivo:</strong></div>
                    <div class="col-6">${reserva.motivo}</div>
                </div>
                ${reserva.descripcion ? `
                <div class="row mt-2">
                    <div class="col-6"><strong>Descripción:</strong></div>
                    <div class="col-6">${reserva.descripcion}</div>
                </div>
                ` : ''}
                <div class="row mt-2">
                    <div class="col-6"><strong>Estado:</strong></div>
                    <div class="col-6">
                        <span class="badge bg-${reserva.estado === 'aprobada' ? 'success' : reserva.estado === 'pendiente' ? 'warning' : 'secondary'}">
                            ${reserva.estado}
                        </span>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-6"><strong>Fecha de solicitud:</strong></div>
                    <div class="col-6">${new Date(reserva.created_at).toLocaleString('es-ES')}</div>
                </div>
                ${reserva.fecha_aprobacion ? `
                <div class="row mt-2">
                    <div class="col-6"><strong>Fecha de aprobación:</strong></div>
                    <div class="col-6">${new Date(reserva.fecha_aprobacion).toLocaleString('es-ES')}</div>
                </div>
                ` : ''}
                ${reserva.observaciones ? `
                <div class="row mt-2">
                    <div class="col-6"><strong>Observaciones:</strong></div>
                    <div class="col-6">${reserva.observaciones}</div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('detallesContenido').innerHTML = contenido;
            new bootstrap.Modal(document.getElementById('modalDetalles')).show();
        }
    </script>
</body>
</html>
