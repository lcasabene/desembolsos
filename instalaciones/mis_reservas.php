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

// Procesar edición de reserva (solo usuarios regulares)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar' && !$es_admin_o_portero) {
    $reserva_id = $_POST['reserva_id'];
    $nueva_fecha = $_POST['fecha'];
    $nueva_hora_inicio = $_POST['hora_inicio'];
    $nueva_hora_fin = $_POST['hora_fin'];
    $nuevo_motivo = $_POST['motivo'];
    $nueva_descripcion = $_POST['descripcion'];
    
    // Verificar que la reserva pertenezca al usuario y esté pendiente o aprobada
    $stmt = $pdo->prepare("SELECT * FROM reservas WHERE id = ? AND usuario_id = ? AND estado IN ('pendiente', 'aprobada')");
    $stmt->execute([$reserva_id, $usuario_id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reserva) {
        // Verificar que no sea una reserva para hoy o pasado
        $fecha_reserva = new DateTime($nueva_fecha);
        $hoy = new DateTime();
        $hoy->setTime(0, 0, 0);
        
        if ($fecha_reserva > $hoy) {
            // Verificar disponibilidad para el nuevo horario (excluyendo esta reserva)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as conflictos 
                FROM reservas 
                WHERE salon_id = ? AND fecha = ? AND estado IN ('pendiente', 'aprobada') AND id != ?
                AND ((hora_inicio < ? AND hora_fin > ?) OR (hora_inicio < ? AND hora_fin > ?))
            ");
            $stmt->execute([
                $reserva['salon_id'], $nueva_fecha, $reserva_id,
                $nueva_hora_fin, $nueva_hora_inicio,
                $nueva_hora_fin, $nueva_hora_inicio
            ]);
            $conflictos = $stmt->fetch(PDO::FETCH_ASSOC)['conflictos'];
            
            if ($conflictos == 0) {
                $stmt = $pdo->prepare("
                    UPDATE reservas 
                    SET fecha = ?, hora_inicio = ?, hora_fin = ?, motivo = ?, descripcion = ?,
                        observaciones = CONCAT(IFNULL(observaciones, ''), '\nEditada por usuario el: ', NOW())
                    WHERE id = ?
                ");
                $stmt->execute([$nueva_fecha, $nueva_hora_inicio, $nueva_hora_fin, $nuevo_motivo, $nueva_descripcion, $reserva_id]);
                $mensaje = "Reserva actualizada exitosamente";
            } else {
                $error = "El nuevo horario choca con otra reserva existente";
            }
        } else {
            $error = "No se puede editar para hoy o fechas pasadas";
        }
    } else {
        $error = "Reserva no encontrada o no tienes permisos para editarla";
    }
}

// Procesar cancelación (solo usuarios regulares, no porteros ni admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancelar' && !$es_admin_o_portero) {
    $reserva_id = $_POST['reserva_id'];
    
    // Verificar que la reserva pertenezca al usuario y esté pendiente o aprobada
    $stmt = $pdo->prepare("SELECT * FROM reservas WHERE id = ? AND usuario_id = ? AND estado IN ('pendiente', 'aprobada')");
    $stmt->execute([$reserva_id, $usuario_id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reserva) {
        // Verificar tiempo de anticipación (2 horas)
        $fecha_reserva = new DateTime($reserva['fecha'] . ' ' . $reserva['hora_inicio']);
        $ahora = new DateTime();
        $anticipacion_minima = new DateInterval('PT2H'); // 2 horas
        $limite_cancelacion = (clone $fecha_reserva)->sub($anticipacion_minima);
        
        if ($ahora < $limite_cancelacion) {
            $stmt = $pdo->prepare("UPDATE reservas SET estado = 'cancelada', observaciones = CONCAT(IFNULL(observaciones, ''), '\nCancelada por usuario el: ', NOW()) WHERE id = ?");
            $stmt->execute([$reserva_id]);
            $mensaje = "Reserva cancelada exitosamente";
        } else {
            $error = "Solo se pueden cancelar reservas con al menos 2 horas de anticipación. Para esta reserva, debías cancelar antes de las " . $limite_cancelacion->format('H:i');
        }
    } else {
        $error = "Reserva no encontrada o no tienes permisos para cancelarla";
    }
}

// Procesar cancelación masiva (solo admin/portero)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancelar_masivo' && $es_admin_o_portero) {
    $reservas_ids_json = $_POST['reservas_ids'] ?? '[]';
    $reservas_ids = json_decode($reservas_ids_json, true) ?? [];
    $motivo_cancelacion = $_POST['motivo_cancelacion'] ?? '';
    
    if (!empty($reservas_ids) && $motivo_cancelacion) {
        $placeholders = str_repeat('?,', count($reservas_ids) - 1) . '?';
        
        $stmt = $pdo->prepare("
            UPDATE reservas 
            SET estado = 'cancelada', 
                observaciones = CONCAT(IFNULL(observaciones, ''), '\nCancelación masiva por admin/portero el: ', NOW(), ' - Motivo: ', ?)
            WHERE id IN ($placeholders) AND estado IN ('pendiente', 'aprobada')
        ");
        $params = array_merge([$motivo_cancelacion], $reservas_ids);
        $stmt->execute($params);
        
        $mensaje = "Se cancelaron {$stmt->rowCount()} reservas exitosamente";
    } else {
        $error = "Debes seleccionar al menos una reserva y proporcionar un motivo";
    }
}

// Obtener reservas (todas si es admin/portero, solo las del usuario si es regular)
$where_clause = $es_admin_o_portero ? "" : "WHERE r.usuario_id = ?";
$params = $es_admin_o_portero ? [] : [$usuario_id];

$select_fields = $es_admin_o_portero ? "r.*, s.nombre as salon_nombre, s.numero as salon_numero, u.nombre as usuario_nombre" : "r.*, s.nombre as salon_nombre, s.numero as salon_numero";
$join_clause = $es_admin_o_portero ? "JOIN usuarios u ON r.usuario_id = u.id" : "";

$stmt = $pdo->prepare("
    SELECT $select_fields
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    $join_clause
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
    <style>
        /* Mejoras para móvil */
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .table th, .table td {
                padding: 0.5rem;
                vertical-align: middle;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .badge {
                font-size: 0.7rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            /* Ocultar columnas menos importantes en móvil */
            .table th:nth-child(4), /* Motivo */
            .table td:nth-child(4) {
                display: none;
            }
            
            /* Vista de cards para móvil */
            .mobile-view {
                display: none;
            }
            
            .desktop-view {
                display: table;
            }
        }
        
        @media (max-width: 576px) {
            .mobile-view {
                display: block;
            }
            
            .desktop-view {
                display: none;
            }
            
            .reserva-card {
                background: white;
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 1rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                border-left: 4px solid #0d6efd;
            }
            
            .reserva-card.cancelada {
                border-left-color: #6c757d;
                opacity: 0.7;
            }
            
            .reserva-card.pendiente {
                border-left-color: #ffc107;
            }
            
            .reserva-card.rechazada {
                border-left-color: #dc3545;
            }
            
            .reserva-header {
                display: flex;
                justify-content: between;
                align-items: center;
                margin-bottom: 0.5rem;
            }
            
            .reserva-date {
                font-weight: bold;
                color: #0d6efd;
            }
            
            .reserva-salon {
                font-size: 0.9rem;
                color: #6c757d;
            }
            
            .reserva-time {
                background: #f8f9fa;
                padding: 0.25rem 0.5rem;
                border-radius: 4px;
                font-size: 0.8rem;
            }
            
            .reserva-actions {
                margin-top: 0.75rem;
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            
            .reserva-actions .btn {
                flex: 1;
                min-width: 80px;
            }
        }
        
        /* Checkbox mejorado para móvil */
        .form-check-input {
            transform: scale(1.2);
        }
        
        @media (max-width: 768px) {
            .form-check-input {
                transform: scale(1.1);
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../menu_instalaciones_moderno.php">
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
        <div class="container mt-4">
            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $mensaje ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="mb-0"><i class="bi bi-list-check"></i> Mis Reservas</h2>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($es_admin_o_portero): ?>
                <a href="configuracion_horarios.php" class="btn btn-outline-warning">
                    <i class="bi bi-clock"></i> Configurar Horarios
                </a>
                <?php endif; ?>
                <a href="nueva_reserva.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nueva Reserva
                </a>
            </div>
        </div>

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
                <!-- Vista desktop -->
                <div class="table-responsive d-none d-md-block desktop-view">
                    <table id="tablaReservas" class="table table-striped">
                    <thead>
                        <tr>
                            <?php if ($es_admin_o_portero): ?><th><input type="checkbox" id="selectAll" class="form-check-input"></th><?php endif; ?>
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
                                <?php if ($es_admin_o_portero): ?>
                                <td><input type="checkbox" class="form-check-input reserva-checkbox" value="<?= $reserva['id'] ?>" <?= $reserva['estado'] !== 'pendiente' && $reserva['estado'] !== 'aprobada' ? 'disabled' : '' ?>></td>
                                <?php endif; ?>
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
                                        // Verificar tiempo de anticipación (2 horas)
                                        $fecha_reserva = new DateTime($reserva['fecha'] . ' ' . $reserva['hora_inicio']);
                                        $ahora = new DateTime();
                                        $anticipacion_minima = new DateInterval('PT2H');
                                        $limite_cancelacion = (clone $fecha_reserva)->sub($anticipacion_minima);
                                        $puede_cancelar = ($ahora < $limite_cancelacion);
                                        
                                        // Para edición, solo se permite si no es hoy
                                        $fecha_solo = new DateTime($reserva['fecha']);
                                        $hoy_solo = new DateTime();
                                        $hoy_solo->setTime(0, 0, 0);
                                        $puede_editar = ($fecha_solo > $hoy_solo);
                                        
                                        if ($puede_cancelar || $puede_editar): 
                                        ?>
                                            <?php if ($puede_editar || $es_admin_o_portero): ?>
                                                <a class="btn btn-sm btn-outline-primary" 
                                                   href="editar_reserva.php?id=<?= $reserva['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Editar
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($puede_cancelar): ?>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="cancelarReserva(<?= $reserva['id'] ?>, '<?= htmlspecialchars($reserva['motivo']) ?>')">
                                                    <i class="bi bi-x-circle"></i> Cancelar
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-outline-info" onclick="verDetalles(<?= htmlspecialchars(json_encode($reserva)) ?>)">
                                        <i class="bi bi-eye"></i> Ver
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                
                <!-- Vista móvil (solo visible en pantallas pequeñas) -->
                <div class="mobile-view d-block d-md-none">
                    <?php foreach ($reservas as $reserva): ?>
                        <?php 
                        // Verificar tiempo de anticipación (2 horas)
                        $fecha_reserva = new DateTime($reserva['fecha'] . ' ' . $reserva['hora_inicio']);
                        $ahora = new DateTime();
                        $anticipacion_minima = new DateInterval('PT2H');
                        $limite_cancelacion = (clone $fecha_reserva)->sub($anticipacion_minima);
                        $puede_cancelar = ($ahora < $limite_cancelacion);
                        
                        // Para edición, solo se permite si no es hoy
                        $fecha_solo = new DateTime($reserva['fecha']);
                        $hoy_solo = new DateTime();
                        $hoy_solo->setTime(0, 0, 0);
                        $puede_editar = ($fecha_solo > $hoy_solo);
                        ?>
                        
                        <div class="reserva-card <?= $reserva['estado'] ?>">
                            <?php if ($es_admin_o_portero): ?>
                                <div class="mb-2">
                                    <input type="checkbox" class="form-check-input reserva-checkbox" value="<?= $reserva['id'] ?>" <?= $reserva['estado'] !== 'pendiente' && $reserva['estado'] !== 'aprobada' ? 'disabled' : '' ?>>
                                    <label class="form-check-label ms-2">Seleccionar</label>
                                </div>
                            <?php endif; ?>
                            
                            <div class="reserva-header">
                                <div>
                                    <div class="reserva-date"><?= date('d/m/Y', strtotime($reserva['fecha'])) ?></div>
                                    <div class="reserva-salon"><?= htmlspecialchars($reserva['salon_numero']) ?> - <?= htmlspecialchars($reserva['salon_nombre']) ?></div>
                                </div>
                                <div class="reserva-time"><?= htmlspecialchars($reserva['hora_inicio']) ?> - <?= htmlspecialchars($reserva['hora_fin']) ?></div>
                            </div>
                            
                            <?php
                            $estado_class = match($reserva['estado']) {
                                'aprobada' => 'success',
                                'pendiente' => 'warning',
                                'rechazada' => 'danger',
                                'cancelada' => 'secondary',
                                default => 'light'
                            };
                            ?>
                            <div class="mb-2">
                                <span class="badge bg-<?= $estado_class ?>"><?= htmlspecialchars($reserva['estado']) ?></span>
                            </div>
                            
                            <?php if ($reserva['motivo']): ?>
                                <div class="mb-2">
                                    <small class="text-muted"><strong>Motivo:</strong> <?= htmlspecialchars($reserva['motivo']) ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="reserva-actions">
                                <?php if ($reserva['estado'] === 'pendiente' || $reserva['estado'] === 'aprobada'): ?>
                                    <?php if ($puede_editar || $es_admin_o_portero): ?>
                                        <a class="btn btn-sm btn-outline-primary" 
                                           href="editar_reserva.php?id=<?= $reserva['id'] ?>">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($puede_cancelar): ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="cancelarReserva(<?= $reserva['id'] ?>, '<?= htmlspecialchars($reserva['motivo']) ?>')">
                                            <i class="bi bi-x-circle"></i> Cancelar
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-outline-info" onclick="verDetalles(<?= htmlspecialchars(json_encode($reserva)) ?>)">
                                    <i class="bi bi-eye"></i> Ver
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <?php if ($es_admin_o_portero): ?>
        <!-- Botones de acción masiva -->
        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <button class="btn btn-danger" onclick="mostrarCancelacionMasiva()" id="btnCancelarMasivo" disabled>
                            <i class="bi bi-x-circle"></i> Cancelar Seleccionadas
                        </button>
                        <span class="ms-3 text-muted" id="contadorSeleccion">0 reservas seleccionadas</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
    
    <!-- Modal de edición -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEditar" method="POST">
                    <input type="hidden" name="action" value="editar">
                    <input type="hidden" name="reserva_id" id="editarReservaId">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Fecha *</label>
                                <input type="date" class="form-control" name="fecha" id="editarFecha" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Hora Inicio *</label>
                                <input type="time" class="form-control" name="hora_inicio" id="editarHoraInicio" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Hora Fin *</label>
                                <input type="time" class="form-control" name="hora_fin" id="editarHoraFin" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Motivo *</label>
                                <input type="text" class="form-control" name="motivo" id="editarMotivo" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion" id="editarDescripcion" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de cancelación masiva -->
    <div class="modal fade" id="modalCancelarMasivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancelar Reservas Masivamente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formCancelarMasivo" method="POST">
                    <input type="hidden" name="action" value="cancelar_masivo">
                    <input type="hidden" name="reservas_ids" id="cancelarMasivoIds">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> Estás por cancelar <strong id="cantidadCancelar">0</strong> reservas. Esta acción no se puede deshacer.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo de cancelación *</label>
                            <textarea class="form-control" name="motivo_cancelacion" rows="3" required 
                                      placeholder="Especifica el motivo por el cual se cancelan estas reservas..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar Cancelación</button>
                    </div>
                </form>
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

        function editarReserva(reserva) {
            document.getElementById('editarReservaId').value = reserva.id;
            document.getElementById('editarFecha').value = reserva.fecha;
            document.getElementById('editarHoraInicio').value = reserva.hora_inicio;
            document.getElementById('editarHoraFin').value = reserva.hora_fin;
            document.getElementById('editarMotivo').value = reserva.motivo;
            document.getElementById('editarDescripcion').value = reserva.descripcion || '';
            
            new bootstrap.Modal(document.getElementById('modalEditar')).show();
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
                ${reserva.usuario_nombre ? `
                <div class="row mt-2">
                    <div class="col-6"><strong>Usuario:</strong></div>
                    <div class="col-6">${reserva.usuario_nombre}</div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('detallesContenido').innerHTML = contenido;
            new bootstrap.Modal(document.getElementById('modalDetalles')).show();
        }
        
        function mostrarCancelacionMasiva() {
            const seleccionadas = $('.reserva-checkbox:checked').map(function() { return this.value; }).get();
            if (seleccionadas.length === 0) {
                alert('Debes seleccionar al menos una reserva');
                return;
            }
            
            document.getElementById('cantidadCancelar').textContent = seleccionadas.length;
            document.getElementById('cancelarMasivoIds').value = JSON.stringify(seleccionadas);
            new bootstrap.Modal(document.getElementById('modalCancelarMasivo')).show();
        }
        
        // Checkbox select all functionality
        $(document).ready(function() {
            $('#selectAll').change(function() {
                $('.reserva-checkbox:not(:disabled)').prop('checked', this.checked);
                actualizarContador();
            });
            
            $('.reserva-checkbox').change(function() {
                actualizarContador();
                // Actualizar el estado del checkbox principal
                const total = $('.reserva-checkbox:not(:disabled)').length;
                const checked = $('.reserva-checkbox:checked').length;
                $('#selectAll').prop('indeterminate', checked > 0 && checked < total);
                $('#selectAll').prop('checked', checked === total);
            });
            
            function actualizarContador() {
                const count = $('.reserva-checkbox:checked').length;
                $('#contadorSeleccion').text(count + ' reserva' + (count !== 1 ? 's' : '') + ' seleccionada' + (count !== 1 ? 's' : ''));
                $('#btnCancelarMasivo').prop('disabled', count === 0);
            }
        });
    </script>
</body>
</html>
