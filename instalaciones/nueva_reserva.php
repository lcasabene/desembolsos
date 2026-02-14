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

$user_role = $_SESSION['user_role'] ?? '';
$es_admin = ($user_role === 'Admin');

// Obtener configuración
$stmt = $pdo->query("SELECT parametro, valor FROM configuracion_instalaciones");
$config = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['parametro']] = $row['valor'];
}

// Obtener salones activos
$stmt = $pdo->query("SELECT * FROM salones WHERE estado = 'activo' ORDER BY numero");
$salones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener usuarios (solo para admin)
$usuarios = [];
if ($es_admin) {
    $stmt = $pdo->query("SELECT id, nombre, email FROM usuarios ORDER BY nombre");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener configuración de horarios permitidos (compatible con ambas tablas)
try {
    $stmt = $pdo->query("SELECT * FROM configuracion_horarios WHERE estado = 'activo' ORDER BY hora_inicio");
    $horarios_config = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si no existe configuracion_horarios, usar rangos_horarios
    $stmt = $pdo->query("SELECT * FROM rangos_horarios WHERE estado = 'activo' ORDER BY hora_inicio");
    $horarios_config = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Procesar formulario
$errores = [];
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $salon_id = $_POST['salon_id'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $motivo = $_POST['motivo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $es_recurrente = $_POST['es_recurrente'] ?? 'no';
    $fecha_fin_recurrente = $_POST['fecha_fin_recurrente'] ?? '';
    $dias_semana = $_POST['dias_semana'] ?? [];
    $usuario_seleccionado = $_POST['usuario_id'] ?? '';
    if (!$es_admin || empty($usuario_seleccionado)) {
        $usuario_seleccionado = $_SESSION['user_id'];
    }

    // Validaciones
    if (empty($salon_id)) $errores[] = "Debe seleccionar un salón";
    if (empty($fecha)) $errores[] = "Debe seleccionar una fecha";
    if (empty($hora_inicio)) $errores[] = "Debe seleccionar hora de inicio";
    if (empty($hora_fin)) $errores[] = "Debe seleccionar hora de fin";
    if (empty($motivo)) $errores[] = "Debe indicar el motivo de la reserva";
    
    // Validar que hora fin sea posterior a hora inicio
    if ($hora_inicio && $hora_fin && $hora_fin <= $hora_inicio) {
        $errores[] = "La hora de fin debe ser posterior a la hora de inicio";
    }

    // Validar recurrencia
    if ($es_recurrente !== 'no') {
        if (empty($fecha_fin_recurrente)) {
            $errores[] = "Debe especificar la fecha fin para reservas recurrentes";
        } elseif (empty($dias_semana)) {
            $errores[] = "Debe seleccionar los días de la semana para reservas recurrentes";
        }
        if (!empty($fecha_fin_recurrente)) {
            $fin_recurrente = new DateTime($fecha_fin_recurrente);
            if ($fin_recurrente <= new DateTime($fecha)) {
                $errores[] = "La fecha fin de recurrencia debe ser posterior a la fecha de inicio";
            }
        }
    }

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

    // Verificar que el horario esté dentro de los rangos permitidos
    if (empty($errores)) {
        $dia_semana = date('N', strtotime($fecha)); // 1=Lunes, 7=Domingo
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM configuracion_horarios WHERE estado = 'activo' AND ? BETWEEN hora_inicio AND hora_fin");
            $stmt->execute([$hora_inicio]);
        } catch (PDOException $e) {
            // Si no existe configuracion_horarios, usar rangos_horarios
            $stmt = $pdo->prepare("SELECT * FROM rangos_horarios WHERE estado = 'activo' AND ? BETWEEN hora_inicio AND hora_fin");
            $stmt->execute([$hora_inicio]);
        }
        $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $horario_valido = false;
        foreach ($horarios as $horario) {
            $dias_permitidos = json_decode($horario['dias_semana'], true) ?? [];
            if (in_array($dia_semana, $dias_permitidos)) {
                // Verificar que el horario completo esté dentro del rango
                if ($hora_fin <= $horario['hora_fin']) {
                    $horario_valido = true;
                    break;
                }
            }
        }
        
        if (!$horario_valido) {
            $errores[] = "El horario seleccionado no está disponible. Consulta los horarios permitidos.";
        }
    }

    // Verificar disponibilidad (conflictos de horario) - validación detallada
    if (empty($errores)) {
        $inicio_minutos = (int)substr($hora_inicio, 0, 2) * 60 + (int)substr($hora_inicio, 3, 2);
        $fin_minutos = (int)substr($hora_fin, 0, 2) * 60 + (int)substr($hora_fin, 3, 2);
        
        $stmt = $pdo->prepare("
            SELECT r.id, r.hora_inicio, r.hora_fin, r.estado, u.nombre as usuario_nombre
            FROM reservas r 
            JOIN usuarios u ON r.usuario_id = u.id
            WHERE r.salon_id = ? AND r.fecha = ? 
            AND r.estado IN ('aprobada', 'pendiente')
            ORDER BY r.hora_inicio
        ");
        $stmt->execute([$salon_id, $fecha]);
        $reservas_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reservas_existentes as $reserva_existente) {
            $existente_inicio = (int)substr($reserva_existente['hora_inicio'], 0, 2) * 60 + (int)substr($reserva_existente['hora_inicio'], 3, 2);
            $existente_fin = (int)substr($reserva_existente['hora_fin'], 0, 2) * 60 + (int)substr($reserva_existente['hora_fin'], 3, 2);
            
            if (($inicio_minutos < $existente_fin) && ($fin_minutos > $existente_inicio)) {
                $errores[] = "El salón ya está reservado. Existe una reserva {$reserva_existente['estado']} de " .
                             "{$reserva_existente['hora_inicio']} a {$reserva_existente['hora_fin']} " .
                             "a nombre de {$reserva_existente['usuario_nombre']}.";
                break;
            }
        }
    }

    // Validar duración máxima de reserva
    if (empty($errores)) {
        $inicio = new DateTime($hora_inicio);
        $fin = new DateTime($hora_fin);
        $duracion = ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
        
        if ($duracion > ($config['max_duracion_reserva'] ?? 4)) {
            $errores[] = "La duración máxima por reserva es de " . ($config['max_duracion_reserva'] ?? 4) . " horas";
        }
    }

    // Validar límites diarios del usuario
    if (empty($errores)) {
        $stmt = $pdo->prepare("
            SELECT hora_inicio, hora_fin 
            FROM reservas 
            WHERE usuario_id = ? AND fecha = ? AND estado IN ('aprobada', 'pendiente')
        ");
        $stmt->execute([$usuario_seleccionado, $fecha]);
        $reservas_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $horas_totales = 0;
        foreach ($reservas_usuario as $reserva) {
            $inicio = new DateTime($reserva['hora_inicio']);
            $fin = new DateTime($reserva['hora_fin']);
            $horas_totales += ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
        }
        
        // Agregar la duración de esta reserva
        $inicio_nuevo = new DateTime($hora_inicio);
        $fin_nuevo = new DateTime($hora_fin);
        $horas_totales += ($fin_nuevo->getTimestamp() - $inicio_nuevo->getTimestamp()) / 3600;
        
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
        $stmt->execute([$usuario_seleccionado, $inicio_semana->format('Y-m-d'), $fin_semana->format('Y-m-d')]);
        $reservas_semana = $stmt->fetchColumn();
        
        if ($reservas_semana >= ($config['max_reservas_semana'] ?? 5)) {
            $errores[] = "Ha alcanzado el límite de " . ($config['max_reservas_semana'] ?? 5) . " reservas semanales";
        }
    }

    // Si no hay errores, guardar la reserva
    if (empty($errores)) {
        $estado = ($config['requiere_aprobacion'] === 'true') ? 'pendiente' : 'aprobada';
        
        $observaciones = '';
        if ($es_admin && $usuario_seleccionado != $_SESSION['user_id']) {
            $usuario_info = array_filter($usuarios, fn($u) => $u['id'] == $usuario_seleccionado);
            $nombre_usuario = reset($usuario_info)['nombre'] ?? 'Usuario';
            $observaciones = "Reserva creada por admin ({$_SESSION['user_name']}) para: $nombre_usuario";
        }
        
        if ($es_recurrente === 'no') {
            // Reserva simple
            $stmt = $pdo->prepare("INSERT INTO reservas (salon_id, usuario_id, fecha, hora_inicio, hora_fin, motivo, descripcion, estado, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$salon_id, $usuario_seleccionado, $fecha, $hora_inicio, $hora_fin, $motivo, $descripcion, $estado, $observaciones]);
        } else {
            // Crear reservas recurrentes con validación de conflictos
            $fecha_actual = new DateTime($fecha);
            $fecha_fin_rec = new DateTime($fecha_fin_recurrente);
            $reservas_creadas = 0;
            $errores_recurrente = [];
            
            while ($fecha_actual <= $fecha_fin_rec) {
                $dia_semana_rec = (int)$fecha_actual->format('N');
                
                if (in_array($dia_semana_rec, $dias_semana)) {
                    $fecha_formateada = $fecha_actual->format('Y-m-d');
                    
                    // Verificar si no es feriado
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feriados WHERE fecha = ? OR (recurrente = 'anual' AND MONTH(fecha) = MONTH(?) AND DAY(fecha) = DAY(?))");
                    $stmt->execute([$fecha_formateada, $fecha_formateada, $fecha_formateada]);
                    
                    if ($stmt->fetchColumn() == 0) {
                        // Verificar disponibilidad para esta fecha
                        $ini_min = (int)substr($hora_inicio, 0, 2) * 60 + (int)substr($hora_inicio, 3, 2);
                        $fin_min = (int)substr($hora_fin, 0, 2) * 60 + (int)substr($hora_fin, 3, 2);
                        
                        $stmt = $pdo->prepare("
                            SELECT r.hora_inicio, r.hora_fin, u.nombre as usuario_nombre
                            FROM reservas r JOIN usuarios u ON r.usuario_id = u.id
                            WHERE r.salon_id = ? AND r.fecha = ? AND r.estado IN ('aprobada', 'pendiente')
                        ");
                        $stmt->execute([$salon_id, $fecha_formateada]);
                        $reservas_fecha = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $hay_conflicto = false;
                        foreach ($reservas_fecha as $rf) {
                            $ex_ini = (int)substr($rf['hora_inicio'], 0, 2) * 60 + (int)substr($rf['hora_inicio'], 3, 2);
                            $ex_fin = (int)substr($rf['hora_fin'], 0, 2) * 60 + (int)substr($rf['hora_fin'], 3, 2);
                            if (($ini_min < $ex_fin) && ($fin_min > $ex_ini)) {
                                $errores_recurrente[] = "Conflicto en $fecha_formateada: reserva de {$rf['hora_inicio']} a {$rf['hora_fin']} ({$rf['usuario_nombre']})";
                                $hay_conflicto = true;
                                break;
                            }
                        }
                        
                        if (!$hay_conflicto) {
                            $obs_rec = $observaciones ?: '';
                            $stmt = $pdo->prepare("INSERT INTO reservas (salon_id, usuario_id, fecha, hora_inicio, hora_fin, motivo, descripcion, estado, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$salon_id, $usuario_seleccionado, $fecha_formateada, $hora_inicio, $hora_fin, $motivo, $descripcion, $estado, $obs_rec]);
                            $reservas_creadas++;
                        }
                    }
                }
                $fecha_actual->modify('+1 day');
            }
            
            if (!empty($errores_recurrente)) {
                $errores[] = "Se crearon $reservas_creadas reservas, pero hubo conflictos: " . implode("; ", array_slice($errores_recurrente, 0, 3));
                if (count($errores_recurrente) > 3) {
                    $errores[] = "Y " . (count($errores_recurrente) - 3) . " conflictos más...";
                }
            }
            
            if ($reservas_creadas == 0 && empty($errores)) {
                $errores[] = "No se pudo crear ninguna reserva recurrente. Verifique los días seleccionados.";
            }
        }
        
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
                                <?= (isset($es_recurrente) && $es_recurrente !== 'no' && isset($reservas_creadas)) ? "Se crearon $reservas_creadas reservas recurrentes." : '' ?>
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

                            <?php if ($es_admin): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="usuario_id" class="form-label">Usuario para la reserva *</label>
                                    <select class="form-select" id="usuario_id" name="usuario_id">
                                        <option value="">Mi usuario</option>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <option value="<?= $usuario['id'] ?>" <?= (isset($_POST['usuario_id']) && $_POST['usuario_id'] == $usuario['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($usuario['nombre']) ?> (<?= htmlspecialchars($usuario['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Como administrador, puedes crear reservas para cualquier usuario</div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="hora_inicio" class="form-label">Hora Inicio *</label>
                                    <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" required 
                                           value="<?= htmlspecialchars($_POST['hora_inicio'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="hora_fin" class="form-label">Hora Fin *</label>
                                    <input type="time" class="form-control" id="hora_fin" name="hora_fin" required 
                                           value="<?= htmlspecialchars($_POST['hora_fin'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Disponibilidad</label>
                                    <div id="disponibilidad" class="form-control bg-light">
                                        <span class="text-muted">Seleccione fecha y horario para verificar</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Horarios disponibles como referencia -->
                            <div class="alert alert-info small">
                                <strong>Horarios disponibles:</strong>
                                <?php if (!empty($horarios_config)): ?>
                                    <?php foreach ($horarios_config as $horario): ?>
                                        <?php 
                                        $dias = json_decode($horario['dias_semana'], true) ?? [];
                                        $dias_nombres = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
                                        $dias_texto = [];
                                        foreach ($dias as $dia) {
                                            $dias_texto[] = $dias_nombres[$dia] ?? '';
                                        }
                                        ?>
                                        <div class="mb-1">
                                            <i class="bi bi-clock"></i> <?= $horario['hora_inicio'] ?> - <?= $horario['hora_fin'] ?> 
                                            <span class="badge bg-secondary ms-1"><?= implode(', ', $dias_texto) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">No hay horarios configurados. Contacte al administrador.</span>
                                <?php endif; ?>
                            </div>

                            <!-- Opciones de recurrencia -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <input type="checkbox" id="es_recurrente_check" 
                                               <?= (isset($_POST['es_recurrente']) && $_POST['es_recurrente'] !== 'no') ? 'checked' : '' ?>
                                               onchange="toggleRecurrente()">
                                        <label for="es_recurrente_check" class="form-check-label ms-2">
                                            Reserva recurrente
                                        </label>
                                    </h6>
                                </div>
                                <div class="card-body" id="opciones_recurrente" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="es_recurrente" class="form-label">Tipo de recurrencia</label>
                                            <select class="form-select" id="es_recurrente" name="es_recurrente">
                                                <option value="semanal" <?= (isset($_POST['es_recurrente']) && $_POST['es_recurrente'] === 'semanal') ? 'selected' : '' ?>>Semanal</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="fecha_fin_recurrente" class="form-label">Repetir hasta</label>
                                            <input type="date" class="form-control" id="fecha_fin_recurrente" name="fecha_fin_recurrente"
                                                   value="<?= htmlspecialchars($_POST['fecha_fin_recurrente'] ?? '') ?>"
                                                   min="<?= date('Y-m-d') ?>" max="<?= date('Y-12-31') ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Días de la semana</label>
                                        <div class="row">
                                            <?php 
                                            $dias_nombres = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
                                            foreach ($dias_nombres as $num => $nombre): ?>
                                            <div class="col-6 col-md-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="<?= $num ?>" id="dia_<?= $num ?>" name="dias_semana[]"
                                                           <?= (isset($_POST['dias_semana']) && in_array($num, $_POST['dias_semana'])) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="dia_<?= $num ?>"><?= $nombre ?></label>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
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
                                <a href="../menu_instalaciones_moderno.php" class="btn btn-secondary">
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
        function toggleRecurrente() {
            const checkbox = document.getElementById('es_recurrente_check');
            const opciones = document.getElementById('opciones_recurrente');
            const recurrenteSelect = document.getElementById('es_recurrente');
            
            if (checkbox.checked) {
                opciones.style.display = 'block';
                document.getElementById('fecha_fin_recurrente').required = true;
            } else {
                opciones.style.display = 'none';
                document.getElementById('fecha_fin_recurrente').required = false;
                recurrenteSelect.value = 'no';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar estado de recurrencia
            toggleRecurrente();
            const form = document.getElementById('formReserva');
            const salonSelect = document.getElementById('salon_id');
            const fechaInput = document.getElementById('fecha');
            const horaInicioInput = document.getElementById('hora_inicio');
            const horaFinInput = document.getElementById('hora_fin');
            const disponibilidadDiv = document.getElementById('disponibilidad');

            function verificarDisponibilidad() {
                const salonId = salonSelect.value;
                const fecha = fechaInput.value;
                const horaInicio = horaInicioInput.value;
                const horaFin = horaFinInput.value;

                if (!salonId || !fecha || !horaInicio || !horaFin) {
                    disponibilidadDiv.innerHTML = '<span class="text-muted">Seleccione fecha y horario para verificar</span>';
                    return;
                }

                // Validar que hora fin sea posterior a hora inicio
                if (horaFin <= horaInicio) {
                    disponibilidadDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> La hora de fin debe ser posterior a la hora de inicio</span>';
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
                        hora_inicio: horaInicio,
                        hora_fin: horaFin
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
            horaInicioInput.addEventListener('change', verificarDisponibilidad);
            horaFinInput.addEventListener('change', verificarDisponibilidad);

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
