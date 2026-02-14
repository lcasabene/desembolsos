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

$es_admin = ($user_role === 'Admin');
$usuario_id = $_SESSION['user_id'];
$reserva_id = (int)($_GET['id'] ?? 0);

if (!$reserva_id) {
    header("Location: mis_reservas.php");
    exit;
}

// Obtener la reserva
$stmt = $pdo->prepare("
    SELECT r.*, s.nombre as salon_nombre, s.numero as salon_numero
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    WHERE r.id = ?
");
$stmt->execute([$reserva_id]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    header("Location: mis_reservas.php");
    exit;
}

// Verificar permisos: admin puede editar cualquiera, usuario solo las suyas
if (!$es_admin && $reserva['usuario_id'] != $usuario_id) {
    header("Location: ../acceso_denegado.php");
    exit;
}

// Solo se pueden editar reservas pendientes o aprobadas
if (!in_array($reserva['estado'], ['pendiente', 'aprobada'])) {
    header("Location: mis_reservas.php?error=no_editable");
    exit;
}

// Verificar que sea una fecha futura (con 2 horas de anticipación)
$fecha_reserva = new DateTime($reserva['fecha'] . ' ' . $reserva['hora_inicio']);
$ahora = new DateTime();
$anticipacion = new DateInterval('PT2H');
$limite = (clone $fecha_reserva)->sub($anticipacion);

if (!$es_admin && $ahora >= $limite) {
    header("Location: mis_reservas.php?error=sin_tiempo");
    exit;
}

// Verificar si pertenece a un grupo recurrente
$es_grupo_recurrente = !empty($reserva['grupo_recurrente']);
$reservas_grupo = [];
$total_grupo = 0;
$futuras_grupo = 0;

if ($es_grupo_recurrente) {
    $stmt = $pdo->prepare("
        SELECT id, fecha, hora_inicio, hora_fin, estado 
        FROM reservas 
        WHERE grupo_recurrente = ? AND estado IN ('pendiente', 'aprobada')
        ORDER BY fecha
    ");
    $stmt->execute([$reserva['grupo_recurrente']]);
    $reservas_grupo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_grupo = count($reservas_grupo);
    $futuras_grupo = count(array_filter($reservas_grupo, fn($r) => $r['fecha'] >= date('Y-m-d')));
}

// Obtener configuración
$stmt = $pdo->query("SELECT parametro, valor FROM configuracion_instalaciones");
$config = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['parametro']] = $row['valor'];
}

// Obtener salones activos
$stmt = $pdo->query("SELECT * FROM salones WHERE estado = 'activo' ORDER BY numero");
$salones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener horarios permitidos
try {
    $stmt = $pdo->query("SELECT * FROM configuracion_horarios WHERE estado = 'activo' ORDER BY hora_inicio");
    $horarios_config = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $pdo->query("SELECT * FROM rangos_horarios WHERE estado = 'activo' ORDER BY hora_inicio");
    $horarios_config = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Procesar formulario
$errores = [];
$exito = false;
$msg_exito = '';

// Procesar cancelación de grupo recurrente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancelar_grupo') {
    if ($es_grupo_recurrente) {
        $editor = $es_admin ? 'admin' : 'usuario';
        $stmt = $pdo->prepare("
            UPDATE reservas 
            SET estado = 'cancelada', 
                observaciones = CONCAT(IFNULL(observaciones, ''), '\nCancelación de grupo recurrente por $editor el: ', NOW())
            WHERE grupo_recurrente = ? AND fecha >= CURDATE() AND estado IN ('pendiente', 'aprobada')
        ");
        $stmt->execute([$reserva['grupo_recurrente']]);
        $canceladas = $stmt->rowCount();
        
        $exito = true;
        $msg_exito = "Se cancelaron $canceladas reservas del grupo recurrente.";
        
        // Recargar reserva
        $stmt = $pdo->prepare("SELECT r.*, s.nombre as salon_nombre, s.numero as salon_numero FROM reservas r JOIN salones s ON r.salon_id = s.id WHERE r.id = ?");
        $stmt->execute([$reserva_id]);
        $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Recargar grupo
        $stmt = $pdo->prepare("SELECT id, fecha, hora_inicio, hora_fin, estado FROM reservas WHERE grupo_recurrente = ? AND estado IN ('pendiente', 'aprobada') ORDER BY fecha");
        $stmt->execute([$reserva['grupo_recurrente']]);
        $reservas_grupo = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_grupo = count($reservas_grupo);
        $futuras_grupo = count(array_filter($reservas_grupo, fn($r) => $r['fecha'] >= date('Y-m-d')));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'cancelar_grupo')) {
    $salon_id = $_POST['salon_id'] ?? $reserva['salon_id'];
    $fecha = $_POST['fecha'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $motivo = $_POST['motivo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $aplicar_grupo = isset($_POST['aplicar_grupo']) && $_POST['aplicar_grupo'] === '1';

    // Validaciones
    if (empty($fecha)) $errores[] = "Debe seleccionar una fecha";
    if (empty($hora_inicio)) $errores[] = "Debe seleccionar hora de inicio";
    if (empty($hora_fin)) $errores[] = "Debe seleccionar hora de fin";
    if (empty($motivo)) $errores[] = "Debe indicar el motivo de la reserva";

    if ($hora_inicio && $hora_fin && $hora_fin <= $hora_inicio) {
        $errores[] = "La hora de fin debe ser posterior a la hora de inicio";
    }

    // Validar fecha futura
    if (empty($errores)) {
        $fecha_seleccionada = new DateTime($fecha);
        $hoy = new DateTime();
        $hoy->setTime(0, 0, 0);

        if ($fecha_seleccionada < $hoy) {
            $errores[] = "La fecha no puede ser anterior a hoy";
        }
    }

    // Verificar si es feriado
    if (empty($errores)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM feriados WHERE fecha = ? OR (recurrente = 'anual' AND MONTH(fecha) = MONTH(?) AND DAY(fecha) = DAY(?))");
        $stmt->execute([$fecha, $fecha, $fecha]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = "La fecha seleccionada es un feriado";
        }
    }

    // Verificar horarios permitidos
    if (empty($errores)) {
        $dia_semana = date('N', strtotime($fecha));

        try {
            $stmt = $pdo->prepare("SELECT * FROM configuracion_horarios WHERE estado = 'activo' AND ? >= hora_inicio AND ? <= hora_fin");
            $stmt->execute([$hora_inicio, $hora_fin]);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("SELECT * FROM rangos_horarios WHERE estado = 'activo' AND ? >= hora_inicio AND ? <= hora_fin");
            $stmt->execute([$hora_inicio, $hora_fin]);
        }
        $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $horario_valido = false;
        foreach ($horarios as $horario) {
            $dias_permitidos = json_decode($horario['dias_semana'], true) ?? [];
            if (in_array($dia_semana, $dias_permitidos)) {
                $horario_valido = true;
                break;
            }
        }

        if (!$horario_valido && !$es_admin) {
            $errores[] = "El horario seleccionado no está disponible. Consulta los horarios permitidos.";
        }
    }

    // Verificar conflictos (excluyendo esta reserva)
    if (empty($errores)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM reservas 
            WHERE salon_id = ? AND fecha = ? AND estado IN ('aprobada', 'pendiente') AND id != ?
            AND ((hora_inicio < ? AND hora_fin > ?) OR (hora_inicio < ? AND hora_fin > ?))
        ");
        $stmt->execute([$salon_id, $fecha, $reserva_id, $hora_fin, $hora_inicio, $hora_fin, $hora_inicio]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = "El salón ya está reservado en ese horario";
        }
    }

    // Validar duración máxima
    if (empty($errores)) {
        $inicio = new DateTime($hora_inicio);
        $fin = new DateTime($hora_fin);
        $duracion = ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
        $max_duracion = $config['max_duracion_reserva'] ?? 4;

        if (!$es_admin && $duracion > $max_duracion) {
            $errores[] = "La duración máxima por reserva es de $max_duracion horas";
        }
    }

    // Guardar cambios
    if (empty($errores)) {
        $editor = $es_admin ? 'admin' : 'usuario';
        
        // Editar esta reserva individual
        $stmt = $pdo->prepare("
            UPDATE reservas 
            SET salon_id = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, motivo = ?, descripcion = ?,
                observaciones = CONCAT(IFNULL(observaciones, ''), '\nEditada por $editor el: ', NOW())
            WHERE id = ?
        ");
        $stmt->execute([$salon_id, $fecha, $hora_inicio, $hora_fin, $motivo, $descripcion, $reserva_id]);

        // Si se pidió aplicar al grupo recurrente, actualizar horario en todas las futuras
        $grupo_actualizadas = 0;
        $grupo_conflictos = [];
        if ($aplicar_grupo && $es_grupo_recurrente) {
            $stmt = $pdo->prepare("
                SELECT id, fecha FROM reservas 
                WHERE grupo_recurrente = ? AND id != ? AND fecha >= CURDATE()
                AND estado IN ('pendiente', 'aprobada')
            ");
            $stmt->execute([$reserva['grupo_recurrente'], $reserva_id]);
            $otras_reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($otras_reservas as $otra) {
                // Verificar conflictos para cada fecha del grupo
                $stmt_conf = $pdo->prepare("
                    SELECT COUNT(*) FROM reservas 
                    WHERE salon_id = ? AND fecha = ? AND estado IN ('aprobada', 'pendiente') AND id != ?
                    AND ((hora_inicio < ? AND hora_fin > ?) OR (hora_inicio < ? AND hora_fin > ?))
                ");
                $stmt_conf->execute([$salon_id, $otra['fecha'], $otra['id'], $hora_fin, $hora_inicio, $hora_fin, $hora_inicio]);
                
                if ($stmt_conf->fetchColumn() == 0) {
                    $stmt_upd = $pdo->prepare("
                        UPDATE reservas 
                        SET salon_id = ?, hora_inicio = ?, hora_fin = ?, motivo = ?, descripcion = ?,
                            observaciones = CONCAT(IFNULL(observaciones, ''), '\nEditada en grupo por $editor el: ', NOW())
                        WHERE id = ?
                    ");
                    $stmt_upd->execute([$salon_id, $hora_inicio, $hora_fin, $motivo, $descripcion, $otra['id']]);
                    $grupo_actualizadas++;
                } else {
                    $grupo_conflictos[] = date('d/m/Y', strtotime($otra['fecha']));
                }
            }
        }

        $exito = true;
        $msg_exito = "Reserva actualizada exitosamente.";
        if ($aplicar_grupo && $es_grupo_recurrente) {
            if ($grupo_actualizadas > 0) {
                $msg_exito .= " Se actualizaron $grupo_actualizadas reservas del grupo.";
            }
            if (!empty($grupo_conflictos)) {
                $msg_exito .= " No se pudieron actualizar " . count($grupo_conflictos) . " reservas por conflictos de horario: " . implode(', ', array_slice($grupo_conflictos, 0, 5));
                if (count($grupo_conflictos) > 5) $msg_exito .= "...";
            }
        }
        
        // Recargar datos de la reserva
        $stmt = $pdo->prepare("
            SELECT r.*, s.nombre as salon_nombre, s.numero as salon_numero
            FROM reservas r JOIN salones s ON r.salon_id = s.id WHERE r.id = ?
        ");
        $stmt->execute([$reserva_id]);
        $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Reserva - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="mis_reservas.php">
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
        <h2><i class="bi bi-pencil-square"></i> Editar Reserva #<?= $reserva_id ?></h2>

        <?php if ($exito): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?= $msg_exito ?>
                <a href="mis_reservas.php" class="alert-link ms-2">Volver a mis reservas</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($errores as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Modificar Reserva</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formEditar">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="salon_id" class="form-label">Salón *</label>
                                    <select class="form-select" id="salon_id" name="salon_id" required>
                                        <?php foreach ($salones as $salon): ?>
                                            <option value="<?= $salon['id'] ?>" <?= ($reserva['salon_id'] == $salon['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($salon['numero']) ?> - <?= htmlspecialchars($salon['nombre']) ?> (Cap: <?= $salon['capacidad'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="fecha" class="form-label">Fecha *</label>
                                    <input type="date" class="form-control" id="fecha" name="fecha" required 
                                           value="<?= htmlspecialchars($_POST['fecha'] ?? $reserva['fecha']) ?>"
                                           min="<?= date('Y-m-d') ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="hora_inicio" class="form-label">Hora Inicio *</label>
                                    <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" required 
                                           value="<?= htmlspecialchars($_POST['hora_inicio'] ?? substr($reserva['hora_inicio'], 0, 5)) ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="hora_fin" class="form-label">Hora Fin *</label>
                                    <input type="time" class="form-control" id="hora_fin" name="hora_fin" required 
                                           value="<?= htmlspecialchars($_POST['hora_fin'] ?? substr($reserva['hora_fin'], 0, 5)) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Estado actual</label>
                                    <div class="form-control bg-light">
                                        <?php
                                        $estado_class = match($reserva['estado']) {
                                            'aprobada' => 'success', 'pendiente' => 'warning',
                                            'rechazada' => 'danger', 'cancelada' => 'secondary', default => 'light'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $estado_class ?>"><?= htmlspecialchars($reserva['estado']) ?></span>
                                        <small class="text-muted ms-2">Salón: <?= htmlspecialchars($reserva['salon_numero']) ?> - <?= htmlspecialchars($reserva['salon_nombre']) ?></small>
                                    </div>
                                </div>
                            </div>

                            <!-- Horarios disponibles como referencia -->
                            <?php if (!empty($horarios_config)): ?>
                            <div class="alert alert-info small mb-3">
                                <strong><i class="bi bi-clock me-1"></i>Horarios disponibles:</strong>
                                <?php foreach ($horarios_config as $horario): ?>
                                    <?php 
                                    $dias = json_decode($horario['dias_semana'], true) ?? [];
                                    $dias_nombres = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
                                    $dias_texto = array_map(fn($d) => $dias_nombres[$d] ?? '', $dias);
                                    ?>
                                    <div class="mb-1">
                                        <i class="bi bi-clock"></i> <?= $horario['hora_inicio'] ?> - <?= $horario['hora_fin'] ?> 
                                        <span class="badge bg-secondary ms-1"><?= implode(', ', $dias_texto) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="motivo" class="form-label">Motivo *</label>
                                <input type="text" class="form-control" id="motivo" name="motivo" required 
                                       value="<?= htmlspecialchars($_POST['motivo'] ?? $reserva['motivo']) ?>">
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción (opcional)</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($_POST['descripcion'] ?? $reserva['descripcion'] ?? '') ?></textarea>
                            </div>

                            <?php if ($es_grupo_recurrente && $futuras_grupo > 1): ?>
                            <div class="card border-info mb-3">
                                <div class="card-header bg-info text-white py-2">
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    <strong>Reserva Recurrente</strong> - <?= $total_grupo ?> reservas (<?= $futuras_grupo ?> futuras)
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="aplicar_grupo" id="aplicarGrupoInput" value="0">
                                    <div class="d-flex flex-column gap-2">
                                        <button type="submit" class="btn btn-primary" onclick="document.getElementById('aplicarGrupoInput').value='0'">
                                            <i class="bi bi-pencil me-1"></i> Guardar solo esta reserva
                                        </button>
                                        <button type="submit" class="btn btn-warning" onclick="document.getElementById('aplicarGrupoInput').value='1'"
                                                title="Se actualizan horario, salón, motivo y descripción en todas las futuras. La fecha de cada una se mantiene.">
                                            <i class="bi bi-arrow-repeat me-1"></i> Guardar cambios en todo el grupo
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        "Todo el grupo" actualiza horario, salón, motivo y descripción en las <?= $futuras_grupo ?> reservas futuras. Se validan conflictos en cada fecha.
                                    </small>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="mis_reservas.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Volver
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Guardar Cambios
                                </button>
                            </div>
                            <?php endif; ?>
                        </form>

                        <?php if ($es_grupo_recurrente && $futuras_grupo > 0): ?>
                        <hr>
                        <form method="POST" onsubmit="return confirm('¿Estás seguro de cancelar TODAS las reservas futuras de este grupo recurrente? Esta acción no se puede deshacer.')">
                            <input type="hidden" name="action" value="cancelar_grupo">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-trash me-1"></i> Cancelar todo el grupo recurrente (<?= $futuras_grupo ?> reservas futuras)
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Datos Originales</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-2"><strong>Salón:</strong> <?= htmlspecialchars($reserva['salon_numero']) ?> - <?= htmlspecialchars($reserva['salon_nombre']) ?></li>
                            <li class="mb-2"><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($reserva['fecha'])) ?></li>
                            <li class="mb-2"><strong>Horario:</strong> <?= $reserva['hora_inicio'] ?> - <?= $reserva['hora_fin'] ?></li>
                            <li class="mb-2"><strong>Motivo:</strong> <?= htmlspecialchars($reserva['motivo']) ?></li>
                            <?php if ($reserva['observaciones']): ?>
                                <li class="mb-2"><strong>Observaciones:</strong><br><small class="text-muted"><?= nl2br(htmlspecialchars($reserva['observaciones'])) ?></small></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <?php if ($es_grupo_recurrente && !empty($reservas_grupo)): ?>
                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-arrow-repeat"></i> Reservas del Grupo (<?= $total_grupo ?>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($reservas_grupo as $rg): 
                                $es_actual = ($rg['id'] == $reserva_id);
                                $es_pasada = ($rg['fecha'] < date('Y-m-d'));
                            ?>
                                <div class="list-group-item small <?= $es_actual ? 'list-group-item-primary' : ($es_pasada ? 'text-muted' : '') ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>
                                            <?= $es_actual ? '<i class="bi bi-arrow-right-circle-fill me-1"></i>' : '' ?>
                                            <?= date('d/m/Y', strtotime($rg['fecha'])) ?>
                                            <small>(<?= substr($rg['hora_inicio'], 0, 5) ?> - <?= substr($rg['hora_fin'], 0, 5) ?>)</small>
                                        </span>
                                        <?php if (!$es_actual && !$es_pasada): ?>
                                            <a href="editar_reserva.php?id=<?= $rg['id'] ?>" class="btn btn-outline-primary btn-sm py-0 px-1" title="Editar esta">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
