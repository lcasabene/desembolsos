<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

// Verificar acceso al módulo
$modulos = $_SESSION['modulos'] ?? [];
if (!in_array('Instalaciones', $modulos)) {
    header("Location: ../acceso_denegado.php");
    exit;
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

// Obtener rangos horarios
$stmt = $pdo->query("SELECT * FROM rangos_horarios WHERE estado = 'activo' ORDER BY hora_inicio");
$rangos_horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
$errores = [];
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $salon_id = $_POST['salon_id'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $rango_horario_id = $_POST['rango_horario_id'] ?? '';
    $motivo = $_POST['motivo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';

    // Validaciones
    if (empty($salon_id)) $errores[] = "Debe seleccionar un salón";
    if (empty($fecha)) $errores[] = "Debe seleccionar una fecha";
    if (empty($rango_horario_id)) $errores[] = "Debe seleccionar un rango horario";
    if (empty($motivo)) $errores[] = "Debe indicar el motivo de la reserva";

    // Validar fecha futura
    $fecha_seleccionada = new DateTime($fecha);
    $hoy = new DateTime();
    $dias_diferencia = $hoy->diff($fecha_seleccionada)->days;
    $anio_actual = (int)$hoy->format('Y');
    $anio_seleccionado = (int)$fecha_seleccionada->format('Y');

    if ($fecha_seleccionada < $hoy) {
        $errores[] = "La fecha no puede ser anterior a hoy";
    } elseif ($anio_seleccionado > $anio_actual) {
        $errores[] = "Las reservas solo pueden realizarse dentro del año actual ($anio_actual)";
    } elseif ($dias_diferencia > ($config['max_dias_anticipacion'] ?? 30)) {
        $errores[] = "Solo se puede reservar con " . ($config['max_dias_anticipacion'] ?? 30) . " días de anticipación";
    }

    // Validar horas de anticipación
    $horas_diferencia = ($fecha_seleccionada->getTimestamp() - $hoy->getTimestamp()) / 3600;
    if ($horas_diferencia < ($config['min_horas_anticipacion'] ?? 2)) {
        $errores[] = "Debe reservar con al menos " . ($config['min_horas_anticipacion'] ?? 2) . " horas de anticipación";
    }

    // Verificar si es feriado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feriados WHERE fecha = ? OR (recurrente = 'anual' AND MONTH(fecha) = MONTH(?) AND DAY(fecha) = DAY(?))");
    $stmt->execute([$fecha, $fecha, $fecha]);
    if ($stmt->fetchColumn() > 0) {
        $errores[] = "La fecha seleccionada es un feriado, no se pueden hacer reservas";
    }

    // Verificar disponibilidad
    if (empty($errores)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE salon_id = ? AND fecha = ? AND rango_horario_id = ? AND estado IN ('aprobada', 'pendiente')");
        $stmt->execute([$salon_id, $fecha, $rango_horario_id]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = "El salón ya está reservado para esa fecha y horario";
        }
    }

    // Validar duración máxima de reserva
    if (empty($errores)) {
        $stmt = $pdo->prepare("SELECT hora_inicio, hora_fin FROM rangos_horarios WHERE id = ?");
        $stmt->execute([$rango_horario_id]);
        $rango = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rango) {
            $inicio = new DateTime($rango['hora_inicio']);
            $fin = new DateTime($rango['hora_fin']);
            $duracion = ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
            
            if ($duracion > ($config['max_duracion_reserva'] ?? 4)) {
                $errores[] = "La duración máxima por reserva es de " . ($config['max_duracion_reserva'] ?? 4) . " horas";
            }
        }
    }

    // Validar límites diarios del usuario
    if (empty($errores)) {
        $stmt = $pdo->prepare("
            SELECT rh.hora_inicio, rh.hora_fin 
            FROM reservas r 
            JOIN rangos_horarios rh ON r.rango_horario_id = rh.id 
            WHERE r.usuario_id = ? AND r.fecha = ? AND r.estado IN ('aprobada', 'pendiente')
        ");
        $stmt->execute([$_SESSION['user_id'], $fecha]);
        $reservas_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $horas_totales = 0;
        foreach ($reservas_usuario as $reserva) {
            $inicio = new DateTime($reserva['hora_inicio']);
            $fin = new DateTime($reserva['hora_fin']);
            $horas_totales += ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
        }
        
        // Agregar la duración de esta reserva
        $stmt = $pdo->prepare("SELECT hora_inicio, hora_fin FROM rangos_horarios WHERE id = ?");
        $stmt->execute([$rango_horario_id]);
        $rango_nuevo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rango_nuevo) {
            $inicio = new DateTime($rango_nuevo['hora_inicio']);
            $fin = new DateTime($rango_nuevo['hora_fin']);
            $horas_totales += ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
        }
        
        if ($horas_totales > ($config['max_horas_diarias'] ?? 8)) {
            $errores[] = "Excede el límite de " . ($config['max_horas_diarias'] ?? 8) . " horas diarias por usuario";
        }
    }

    // Validar límites semanales del usuario
    if (empty($errores)) {
        $inicio_semana = new DateTime($fecha);
        $inicio_semana->modify('Monday this week');
        $fin_semana = clone $inicio_semana;
        $fin_semana->modify('Sunday this week');
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM reservas 
            WHERE usuario_id = ? AND fecha BETWEEN ? AND ? AND estado IN ('aprobada', 'pendiente')
        ");
        $stmt->execute([$_SESSION['user_id'], $inicio_semana->format('Y-m-d'), $fin_semana->format('Y-m-d')]);
        $reservas_semana = $stmt->fetchColumn();
        
        if ($reservas_semana >= ($config['max_reservas_semana'] ?? 5)) {
            $errores[] = "Ha alcanzado el límite de " . ($config['max_reservas_semana'] ?? 5) . " reservas semanales";
        }
    }

    // Si no hay errores, guardar la reserva
    if (empty($errores)) {
        $estado = ($config['requiere_aprobacion'] === 'true') ? 'pendiente' : 'aprobada';
        
        $stmt = $pdo->prepare("INSERT INTO reservas (salon_id, usuario_id, fecha, rango_horario_id, motivo, descripcion, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$salon_id, $_SESSION['user_id'], $fecha, $rango_horario_id, $motivo, $descripcion, $estado]);
        
        $exito = true;
        // Limpiar formulario
        $_POST = [];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Reserva - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-calendar-plus"></i> Nueva Reserva</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($exito): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> 
                                Reserva creada exitosamente. 
                                <?= ($config['requiere_aprobacion'] === 'true') ? 'Queda pendiente de aprobación.' : 'Ya está aprobada y lista para usar.' ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errores)): ?>
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-exclamation-triangle"></i> Errores encontrados:</h6>
                                <ul class="mb-0">
                                    <?php foreach ($errores as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="formReserva">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="salon_id" class="form-label">Salón *</label>
                                    <select class="form-select" id="salon_id" name="salon_id" required>
                                        <option value="">Seleccione un salón...</option>
                                        <?php foreach ($salones as $salon): ?>
                                            <option value="<?= $salon['id'] ?>" <?= (isset($_POST['salon_id']) && $_POST['salon_id'] == $salon['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($salon['numero']) ?> - <?= htmlspecialchars($salon['nombre']) ?> (Capacidad: <?= $salon['capacidad'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="fecha" class="form-label">Fecha *</label>
                                    <input type="date" class="form-control" id="fecha" name="fecha" required 
                                           value="<?= htmlspecialchars($_POST['fecha'] ?? '') ?>"
                                           min="<?= date('Y-m-d') ?>"
                                           max="<?= date('Y-12-31') ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="rango_horario_id" class="form-label">Horario *</label>
                                    <select class="form-select" id="rango_horario_id" name="rango_horario_id" required>
                                        <option value="">Seleccione un horario...</option>
                                        <?php foreach ($rangos_horarios as $rango): ?>
                                            <option value="<?= $rango['id'] ?>" 
                                                    data-inicio="<?= $rango['hora_inicio'] ?>" 
                                                    data-fin="<?= $rango['hora_fin'] ?>"
                                                    data-dias="<?= htmlspecialchars($rango['dias_semana']) ?>"
                                                    <?= (isset($_POST['rango_horario_id']) && $_POST['rango_horario_id'] == $rango['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($rango['nombre']) ?> (<?= $rango['hora_inicio'] ?> - <?= $rango['hora_fin'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Disponibilidad</label>
                                    <div id="disponibilidad" class="form-control bg-light">
                                        <span class="text-muted">Seleccione fecha y horario para verificar</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="motivo" class="form-label">Motivo de la reserva *</label>
                                <input type="text" class="form-control" id="motivo" name="motivo" required 
                                       placeholder="Ej: Reunión de jóvenes, Estudio bíblico, etc."
                                       value="<?= htmlspecialchars($_POST['motivo'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción (opcional)</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" 
                                          placeholder="Detalles adicionales sobre el evento..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="../menu_instalaciones.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Crear Reserva
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Información importante -->
                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Información Importante</h6>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0 small">
                            <li>Las reservas deben hacerse con mínimo <?= $config['min_horas_anticipacion'] ?? 2 ?> horas de anticipación</li>
                            <li>Se puede reservar con hasta <?= $config['max_dias_anticipacion'] ?? 30 ?> días de anticipación</li>
                            <li>Las reservas solo pueden realizarse dentro del año actual (<?= date('Y') ?>)</li>
                            <li>La duración máxima por reserva es de <?= $config['max_duracion_reserva'] ?? 4 ?> horas</li>
                            <li>Límite de <?= $config['max_horas_diarias'] ?? 8 ?> horas diarias por usuario</li>
                            <li>Límite de <?= $config['max_reservas_semana'] ?? 5 ?> reservas semanales por usuario</li>
                            <li>Los feriados están automáticamente bloqueados</li>
                            <li><?= ($config['requiere_aprobacion'] === 'true') ? 'Las reservas requieren aprobación administrativa' : 'Las reservas se aprueban automáticamente' ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('formReserva');
            const salonSelect = document.getElementById('salon_id');
            const fechaInput = document.getElementById('fecha');
            const rangoSelect = document.getElementById('rango_horario_id');
            const disponibilidadDiv = document.getElementById('disponibilidad');

            function verificarDisponibilidad() {
                const salonId = salonSelect.value;
                const fecha = fechaInput.value;
                const rangoId = rangoSelect.value;

                if (!salonId || !fecha || !rangoId) {
                    disponibilidadDiv.innerHTML = '<span class="text-muted">Seleccione fecha y horario para verificar</span>';
                    return;
                }

                // Mostrar loading
                disponibilidadDiv.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Verificando...</span>';

                fetch('api_verificar_disponibilidad.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        salon_id: salonId,
                        fecha: fecha,
                        rango_horario_id: rangoId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.disponible) {
                        disponibilidadDiv.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Disponible</span>';
                    } else {
                        disponibilidadDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> No disponible</span>';
                    }
                })
                .catch(error => {
                    disponibilidadDiv.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Error al verificar</span>';
                });
            }

            // Verificar disponibilidad cuando cambian los campos
            salonSelect.addEventListener('change', verificarDisponibilidad);
            fechaInput.addEventListener('change', verificarDisponibilidad);
            rangoSelect.addEventListener('change', verificarDisponibilidad);

            // Validar que la fecha no sea en el pasado ni exceda el año actual
            fechaInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const currentYear = today.getFullYear();
                const selectedYear = selectedDate.getFullYear();

                if (selectedDate < today) {
                    this.setCustomValidity('La fecha no puede ser anterior a hoy');
                } else if (selectedYear > currentYear) {
                    this.setCustomValidity('Las reservas solo pueden realizarse dentro del año actual (' + currentYear + ')');
                } else {
                    this.setCustomValidity('');
                }
            });
        });
    </script>
</body>
</html>
