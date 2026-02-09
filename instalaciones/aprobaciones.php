<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

// Verificar si el usuario tiene acceso al módulo y es admin
$modulos = $_SESSION['modulos'] ?? [];
if (!in_array('Instalaciones', $modulos) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../acceso_denegado.php");
    exit;
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $reserva_id = $_POST['reserva_id'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';
        
        switch ($_POST['action']) {
            case 'aprobar':
                $stmt = $pdo->prepare("
                    UPDATE reservas 
                    SET estado = 'aprobada', 
                        aprobado_por = ?, 
                        fecha_aprobacion = NOW(),
                        observaciones = CONCAT(IFNULL(observaciones, ''), '\nAprobada por: ', ?, ' el: ', NOW(), IFNULL(CONCAT('\n', ?), ''))
                    WHERE id = ? AND estado = 'pendiente'
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_SESSION['user_name'],
                    $observaciones,
                    $reserva_id
                ]);
                $mensaje = "Reserva aprobada exitosamente";
                break;
                
            case 'rechazar':
                $stmt = $pdo->prepare("
                    UPDATE reservas 
                    SET estado = 'rechazada', 
                        aprobado_por = ?, 
                        fecha_aprobacion = NOW(),
                        observaciones = CONCAT(IFNULL(observaciones, ''), '\nRechazada por: ', ?, ' el: ', NOW(), IFNULL(CONCAT('\nMotivo: ', ?), ''))
                    WHERE id = ? AND estado = 'pendiente'
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_SESSION['user_name'],
                    $observaciones,
                    $reserva_id
                ]);
                $mensaje = "Reserva rechazada exitosamente";
                break;
                
            case 'aprobar_multiple':
                if (isset($_POST['reservas_seleccionadas'])) {
                    foreach ($_POST['reservas_seleccionadas'] as $reserva_id) {
                        $stmt = $pdo->prepare("
                            UPDATE reservas 
                            SET estado = 'aprobada', 
                                aprobado_por = ?, 
                                fecha_aprobacion = NOW(),
                                observaciones = CONCAT(IFNULL(observaciones, ''), '\nAprobada masivamente por: ', ?, ' el: ', NOW())
                            WHERE id = ? AND estado = 'pendiente'
                        ");
                        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_name'], $reserva_id]);
                    }
                    $mensaje = "Reservas aprobadas masivamente";
                }
                break;
        }
    }
}

// Obtener reservas pendientes
$stmt = $pdo->prepare("
    SELECT r.*, s.nombre as salon_nombre, s.numero as salon_numero,
           u.nombre as usuario_nombre, u.email as usuario_email
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.estado = 'pendiente'
    ORDER BY r.fecha ASC, r.hora_inicio ASC
");
$stmt->execute();
$reservas_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug - mostrar SQL y resultados
echo "<!-- Debug SQL: " . $stmt->queryString . " -->";
echo "<!-- Debug resultados pendientes: " . print_r($reservas_pendientes, true) . " -->";

// Debug - ver todas las reservas
$stmt_debug = $pdo->query("SELECT COUNT(*) as total FROM reservas");
$total_reservas = $stmt_debug->fetchColumn();
echo "<!-- Debug total reservas en BD: $total_reservas -->";

// Debug - ver reservas por estado
$stmt_debug = $pdo->query("SELECT estado, COUNT(*) as cantidad FROM reservas GROUP BY estado");
$estados = $stmt_debug->fetchAll(PDO::FETCH_ASSOC);
echo "<!-- Debug reservas por estado: " . print_r($estados, true) . " -->";

// Obtener estadísticas
$stmt = $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente'");
$total_pendientes = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'aprobada' AND fecha >= CURDATE()");
$total_aprobadas_futuras = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'aprobada' AND fecha = CURDATE()");
$total_hoy = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aprobación de Reservas - Sistema de Instalaciones</title>
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
            <h2><i class="bi bi-clipboard-check"></i> Aprobación de Reservas</h2>
            <button class="btn btn-success" onclick="aprobarSeleccionadas()" id="btnAprobarMultiple" style="display: none;">
                <i class="bi bi-check-square"></i> Aprobar Seleccionadas
            </button>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <h3 class="text-warning"><?= $total_pendientes ?></h3>
                        <p class="card-text mb-0">Reservas Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <h3 class="text-success"><?= $total_aprobadas_futuras ?></h3>
                        <p class="card-text mb-0">Reservas Aprobadas Futuras</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <h3 class="text-info"><?= $total_hoy ?></h3>
                        <p class="card-text mb-0">Reservas para Hoy</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-clock"></i> Reservas Pendientes de Aprobación
                    <?php if ($total_pendientes > 0): ?>
                        <span class="badge bg-danger"><?= $total_pendientes ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($total_pendientes > 0): ?>
                    <!-- Checkbox para selección múltiple -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label" for="selectAll">
                                Seleccionar todas
                            </label>
                        </div>
                    </div>

                    <form id="formAprobacionMultiple" method="POST">
                        <input type="hidden" name="action" value="aprobar_multiple">
                        <table id="tablaPendientes" class="table table-striped">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAllTable"></th>
                                    <th>Fecha</th>
                                    <th>Salón</th>
                                    <th>Horario</th>
                                    <th>Usuario</th>
                                    <th>Motivo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservas_pendientes as $reserva): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="reservas_seleccionadas[]" value="<?= $reserva['id'] ?>" class="reserva-checkbox">
                                        </td>
                                        <td>
                                            <?= date('d/m/Y', strtotime($reserva['fecha'])) ?>
                                            <?php if ($reserva['fecha'] === date('Y-m-d')): ?>
                                                <span class="badge bg-info">Hoy</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($reserva['salon_numero']) ?> - 
                                            <?= htmlspecialchars($reserva['salon_nombre']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($reserva['hora_inicio']) ?> - <?= htmlspecialchars($reserva['hora_fin']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($reserva['usuario_nombre']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($reserva['usuario_email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($reserva['motivo']) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="aprobarReserva(<?= $reserva['id'] ?>, '<?= htmlspecialchars($reserva['motivo']) ?>')">
                                                    <i class="bi bi-check-circle"></i> Aprobar
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="rechazarReserva(<?= $reserva['id'] ?>, '<?= htmlspecialchars($reserva['motivo']) ?>')">
                                                    <i class="bi bi-x-circle"></i> Rechazar
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="verDetalles(<?= htmlspecialchars(json_encode($reserva)) ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">No hay reservas pendientes</h5>
                        <p class="text-muted">Todas las reservas han sido procesadas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de aprobación/rechazo -->
    <div class="modal fade" id="modalAccion" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Confirmar Acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formAccion" method="POST">
                    <input type="hidden" name="action" id="accionType">
                    <input type="hidden" name="reserva_id" id="reservaId">
                    <div class="modal-body">
                        <p id="mensajeConfirmacion"></p>
                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones (opcional)</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                      placeholder="Comentarios adicionales sobre esta decisión..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn" id="btnConfirmar">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de detalles -->
    <div class="modal fade" id="modalDetalles" tabindex="-1">
        <div class="modal-dialog modal-lg">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tablaPendientes').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                responsive: true,
                order: [[1, 'asc']] // Ordenar por fecha
            });

            // Manejo de checkboxes
            $('#selectAll, #selectAllTable').on('change', function() {
                $('.reserva-checkbox').prop('checked', $(this).prop('checked'));
                actualizarBotonAprobarMultiple();
            });

            $('.reserva-checkbox').on('change', function() {
                actualizarBotonAprobarMultiple();
                actualizarSelectAll();
            });
        });

        function actualizarBotonAprobarMultiple() {
            const seleccionadas = $('.reserva-checkbox:checked').length;
            $('#btnAprobarMultiple').toggle(seleccionadas > 0);
        }

        function actualizarSelectAll() {
            const total = $('.reserva-checkbox').length;
            const seleccionadas = $('.reserva-checkbox:checked').length;
            $('#selectAll, #selectAllTable').prop('checked', total === seleccionadas);
        }

        function aprobarReserva(id, motivo) {
            $('#modalTitle').text('Aprobar Reserva');
            $('#mensajeConfirmacion').text(`¿Estás seguro de aprobar la reserva "${motivo}"?`);
            $('#accionType').val('aprobar');
            $('#reservaId').val(id);
            $('#btnConfirmar').removeClass('btn-danger').addClass('btn-success').text('Aprobar');
            $('#observaciones').val('');
            new bootstrap.Modal(document.getElementById('modalAccion')).show();
        }

        function rechazarReserva(id, motivo) {
            $('#modalTitle').text('Rechazar Reserva');
            $('#mensajeConfirmacion').text(`¿Estás seguro de rechazar la reserva "${motivo}"?`);
            $('#accionType').val('rechazar');
            $('#reservaId').val(id);
            $('#btnConfirmar').removeClass('btn-success').addClass('btn-danger').text('Rechazar');
            $('#observaciones').val('');
            new bootstrap.Modal(document.getElementById('modalAccion')).show();
        }

        function aprobarSeleccionadas() {
            if (confirm('¿Estás seguro de aprobar todas las reservas seleccionadas?')) {
                $('#formAprobacionMultiple').submit();
            }
        }

        function verDetalles(reserva) {
            const contenido = `
                <div class="row">
                    <div class="col-md-6"><strong>ID Reserva:</strong></div>
                    <div class="col-md-6">${reserva.id}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6"><strong>Fecha:</strong></div>
                    <div class="col-md-6">${new Date(reserva.fecha).toLocaleDateString('es-ES')}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6"><strong>Salón:</strong></div>
                    <div class="col-md-6">${reserva.salon_numero} - ${reserva.salon_nombre}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6"><strong>Horario:</strong></div>
                    <div class="col-md-6">${reserva.hora_inicio} - ${reserva.hora_fin}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6"><strong>Usuario:</strong></div>
                    <div class="col-md-6">${reserva.usuario_nombre} (${reserva.usuario_email})</div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6"><strong>Motivo:</strong></div>
                    <div class="col-md-6">${reserva.motivo}</div>
                </div>
                ${reserva.descripcion ? `
                <div class="row mt-2">
                    <div class="col-md-6"><strong>Descripción:</strong></div>
                    <div class="col-md-6">${reserva.descripcion}</div>
                </div>
                ` : ''}
                <div class="row mt-2">
                    <div class="col-md-6"><strong>Estado:</strong></div>
                    <div class="col-md-6">
                        <span class="badge bg-warning">Pendiente</span>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6"><strong>Fecha de solicitud:</strong></div>
                    <div class="col-md-6">${new Date(reserva.created_at).toLocaleString('es-ES')}</div>
                </div>
            `;
            
            document.getElementById('detallesContenido').innerHTML = contenido;
            new bootstrap.Modal(document.getElementById('modalDetalles')).show();
        }
    </script>
</body>
</html>
