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

    // Validaciones
    if (empty($salon_id)) $errores[] = "Debe seleccionar un salón";
    if (empty($fecha)) $errores[] = "Debe seleccionar una fecha";
    if (empty($hora_inicio)) $errores[] = "Debe seleccionar una hora de inicio";
    if (empty($hora_fin)) $errores[] = "Debe seleccionar una hora de fin";
    if (empty($motivo)) $errores[] = "Debe indicar el motivo de la reserva";

    // Validar horas
    if (!empty($hora_inicio) && !empty($hora_fin)) {
        $inicio = new DateTime($fecha . ' ' . $hora_inicio);
        $fin = new DateTime($fecha . ' ' . $hora_fin);
        
        if ($fin <= $inicio) {
            $errores[] = "La hora de fin debe ser posterior a la hora de inicio";
        }
        
        $duracion = ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
        if ($duracion > ($config['max_duracion_reserva'] ?? 4)) {
            $errores[] = "La duración máxima por reserva es de " . ($config['max_duracion_reserva'] ?? 4) . " horas";
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

    // Validar recurrente
    if ($es_recurrente !== 'no') {
        if (empty($fecha_fin_recurrente)) {
            $errores[] = "Debe especificar la fecha fin para reservas recurrentes";
        } elseif (empty($dias_semana)) {
            $errores[] = "Debe seleccionar los días de la semana para reservas recurrentes";
        }
        
        if (!empty($fecha_fin_recurrente)) {
            $fin_recurrente = new DateTime($fecha_fin_recurrente);
            if ($fin_recurrente <= $fecha_seleccionada) {
                $errores[] = "La fecha fin debe ser posterior a la fecha de inicio";
            }
        }
    }

    // Verificar si es feriado
    if (empty($errores)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM feriados WHERE fecha = ? OR (recurrente = 'anual' AND MONTH(fecha) = MONTH(?) AND DAY(fecha) = DAY(?))");
        $stmt->execute([$fecha, $fecha, $fecha]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = "La fecha seleccionada es un feriado, no se pueden hacer reservas";
        }
    }

    // Verificar disponibilidad - Validación mejorada para evitar que las reservas se encimen
    if (empty($errores)) {
        // Convertir las horas a minutos para comparación precisa
        $inicio_minutos = (int)substr($hora_inicio, 0, 2) * 60 + (int)substr($hora_inicio, 3, 2);
        $fin_minutos = (int)substr($hora_fin, 0, 2) * 60 + (int)substr($hora_fin, 3, 2);
        
        // Buscar reservas existentes que puedan chocar
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
            // Convertir horas existentes a minutos
            $existente_inicio = (int)substr($reserva_existente['hora_inicio'], 0, 2) * 60 + (int)substr($reserva_existente['hora_inicio'], 3, 2);
            $existente_fin = (int)substr($reserva_existente['hora_fin'], 0, 2) * 60 + (int)substr($reserva_existente['hora_fin'], 3, 2);
            
            // Verificar si hay traslape:
            // La nueva reserva se encime con la existente si:
            // - Inicia antes de que termine la existente Y
            // - Termina después de que inicia la existente
            if (($inicio_minutos < $existente_fin) && ($fin_minutos > $existente_inicio)) {
                $errores[] = "El salón ya está reservado en ese horario. " .
                             "Existe una reserva {$reserva_existente['estado']} de " .
                             "{$reserva_existente['hora_inicio']} a {$reserva_existente['hora_fin']} " .
                             "a nombre de {$reserva_existente['usuario_nombre']}.";
                break; // No necesitamos seguir verificando
            }
        }
    }

    // Validar límites diarios del usuario
    if (empty($errores)) {
        $stmt = $pdo->prepare("
            SELECT hora_inicio, hora_fin 
            FROM reservas 
            WHERE usuario_id = ? AND fecha = ? AND estado IN ('aprobada', 'pendiente')
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
        if (!empty($hora_inicio) && !empty($hora_fin)) {
            $inicio = new DateTime($hora_inicio);
            $fin = new DateTime($hora_fin);
            $horas_totales += ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
        }
        
        if ($horas_totales > ($config['max_horas_diarias'] ?? 8)) {
            $errores[] = "Excede el límite de " . ($config['max_horas_diarias'] ?? 8) . " horas diarias por usuario";
        }
    }

    // Si no hay errores, guardar la reserva
    if (empty($errores)) {
        $estado = ($config['requiere_aprobacion'] === 'true') ? 'pendiente' : 'aprobada';
        
        if ($es_recurrente === 'no') {
            // Reserva simple
            $stmt = $pdo->prepare("
                INSERT INTO reservas (salon_id, usuario_id, fecha, hora_inicio, hora_fin, motivo, descripcion, estado, es_recurrente) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$salon_id, $_SESSION['user_id'], $fecha, $hora_inicio, $hora_fin, $motivo, $descripcion, $estado, 'no']);
        } else {
            // Crear reservas recurrentes con validación de encimamiento
            $fecha_actual = new DateTime($fecha);
            $fecha_fin = new DateTime($fecha_fin_recurrente);
            $dias_semana_json = json_encode($dias_semana);
            $reservas_creadas = 0;
            $errores_recurrente = [];
            
            while ($fecha_actual <= $fecha_fin) {
                $dia_semana = (int)$fecha_actual->format('N'); // 1=Lunes, 7=Domingo
                
                if (in_array($dia_semana, $dias_semana)) {
                    // Verificar si no es feriado
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feriados WHERE fecha = ? OR (recurrente = 'anual' AND MONTH(fecha) = MONTH(?) AND DAY(fecha) = DAY(?))");
                    $stmt->execute([$fecha_actual->format('Y-m-d'), $fecha_actual->format('Y-m-d'), $fecha_actual->format('Y-m-d')]);
                    
                    if ($stmt->fetchColumn() == 0) {
                        // Verificar disponibilidad para esta fecha específica
                        $fecha_formateada = $fecha_actual->format('Y-m-d');
                        $inicio_minutos = (int)substr($hora_inicio, 0, 2) * 60 + (int)substr($hora_inicio, 3, 2);
                        $fin_minutos = (int)substr($hora_fin, 0, 2) * 60 + (int)substr($hora_fin, 3, 2);
                        
                        $stmt = $pdo->prepare("
                            SELECT r.hora_inicio, r.hora_fin, u.nombre as usuario_nombre
                            FROM reservas r 
                            JOIN usuarios u ON r.usuario_id = u.id
                            WHERE r.salon_id = ? AND r.fecha = ? 
                            AND r.estado IN ('aprobada', 'pendiente')
                        ");
                        $stmt->execute([$salon_id, $fecha_formateada]);
                        $reservas_fecha = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $hay_conflicto = false;
                        foreach ($reservas_fecha as $reserva_fecha) {
                            $existente_inicio = (int)substr($reserva_fecha['hora_inicio'], 0, 2) * 60 + (int)substr($reserva_fecha['hora_inicio'], 3, 2);
                            $existente_fin = (int)substr($reserva_fecha['hora_fin'], 0, 2) * 60 + (int)substr($reserva_fecha['hora_fin'], 3, 2);
                            
                            if (($inicio_minutos < $existente_fin) && ($fin_minutos > $existente_inicio)) {
                                $errores_recurrente[] = "Conflicto en " . $fecha_formateada . ": " .
                                    "existe reserva de {$reserva_fecha['hora_inicio']} a {$reserva_fecha['hora_fin']} " .
                                    "a nombre de {$reserva_fecha['usuario_nombre']}";
                                $hay_conflicto = true;
                                break;
                            }
                        }
                        
                        if (!$hay_conflicto) {
                            $stmt = $pdo->prepare("
                                INSERT INTO reservas (salon_id, usuario_id, fecha, hora_inicio, hora_fin, motivo, descripcion, estado, es_recurrente, fecha_fin_recurrente, dias_semana) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $salon_id, 
                                $_SESSION['user_id'], 
                                $fecha_formateada, 
                                $hora_inicio, 
                                $hora_fin, 
                                $motivo, 
                                $descripcion, 
                                $estado, 
                                $es_recurrente,
                                $fecha_fin_recurrente,
                                $dias_semana_json
                            ]);
                            $reservas_creadas++;
                        }
                    }
                }
                
                // Avanzar al siguiente día
                $fecha_actual->modify('+1 day');
            }
            
            if (!empty($errores_recurrente)) {
                $errores[] = "No se pudieron crear todas las reservas recurrentes por conflictos: " . implode("; ", array_slice($errores_recurrente, 0, 3));
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
            <div class="col-lg-10">
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
                                <?= ($es_recurrente !== 'no' ? 'Se han creado todas las reservas recurrentes.' : '') ?>
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
                                    <label for="hora_inicio" class="form-label">Hora de inicio *</label>
                                    <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" required
                                           value="<?= htmlspecialchars($_POST['hora_inicio'] ?? '') ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="hora_fin" class="form-label">Hora de fin *</label>
                                    <input type="time" class="form-control" id="hora_fin" name="hora_fin" required
                                           value="<?= htmlspecialchars($_POST['hora_fin'] ?? '') ?>">
                                </div>
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
                                                <option value="mensual" <?= (isset($_POST['es_recurrente']) && $_POST['es_recurrente'] === 'mensual') ? 'selected' : '' ?>>Mensual</option>
                                                <option value="anual" <?= (isset($_POST['es_recurrente']) && $_POST['es_recurrente'] === 'anual') ? 'selected' : '' ?>>Anual</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="fecha_fin_recurrente" class="form-label">Repetir hasta</label>
                                            <input type="date" class="form-control" id="fecha_fin_recurrente" name="fecha_fin_recurrente"
                                                   value="<?= htmlspecialchars($_POST['fecha_fin_recurrente'] ?? '') ?>"
                                                   min="<?= date('Y-m-d') ?>"
                                                   max="<?= date('Y-12-31') ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Días de la semana</label>
                                        <div class="row">
                                            <div class="col-6 col-md-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="1" id="dia_1" name="dias_semana[]"
                                                           <?= (isset($_POST['dias_semana']) && in_array('1', $_POST['dias_semana'])) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="dia_1">Lunes</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="2" id="dia_2" name="dias_semana[]"
                                                           <?= (isset($_POST['dias_semana']) && in_array('2', $_POST['dias_semana'])) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="dia_2">Martes</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="3" id="dia_3" name="dias_semana[]"
                                                           <?= (isset($_POST['dias_semana']) && in_array('3', $_POST['dias_semana'])) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="dia_3">Miércoles</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="4" id="dia_4" name="dias_semana[]"
                                                           <?= (isset($_POST['dias_semana']) && in_array('4', $_POST['dias_semana'])) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="dia_4">Jueves</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="5" id="dia_5" name="dias_semana[]"
                                                           <?= (isset($_POST['dias_semana']) && in_array('5', $_POST['dias_semana'])) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="dia_5">Viernes</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="6" id="dia_6" name="dias_semana[]"
                                                           <?= (isset($_POST['dias_semana']) && in_array('6', $_POST['dias_semana'])) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="dia_6">Sábado</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="7" id="dia_7" name="dias_semana[]"
                                                           <?= (isset($_POST['dias_semana']) && in_array('7', $_POST['dias_semana'])) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="dia_7">Domingo</label>
                                                </div>
                                            </div>
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
                            <li>Las reservas recurrentes crearán múltiples reservas individuales</li>
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
                recurrenteSelect.required = true;
                document.getElementById('fecha_fin_recurrente').required = true;
            } else {
                opciones.style.display = 'none';
                recurrenteSelect.required = false;
                document.getElementById('fecha_fin_recurrente').required = false;
                recurrenteSelect.value = 'no';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('formReserva');
            const fechaInput = document.getElementById('fecha');
            const horaInicioInput = document.getElementById('hora_inicio');
            const horaFinInput = document.getElementById('hora_fin');
            const salonSelect = document.getElementById('salon_id');

            // Validar que las horas sean lógicas
            form.addEventListener('submit', function(e) {
                if (horaInicioInput.value && horaFinInput.value) {
                    const inicio = new Date('2000-01-01T' + horaInicioInput.value);
                    const fin = new Date('2000-01-01T' + horaFinInput.value);
                    
                    if (fin <= inicio) {
                        e.preventDefault();
                        alert('La hora de fin debe ser posterior a la hora de inicio');
                        return;
                    }
                }
            });

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
                
                // Verificar disponibilidad en tiempo real cuando cambie la fecha o el salón
                if (this.value && salonSelect.value && horaInicioInput.value && horaFinInput.value) {
                    verificarDisponibilidadTiempoReal();
                }
            });

            // Verificar disponibilidad cuando cambien los campos relevantes
            [horaInicioInput, horaFinInput, salonSelect].forEach(element => {
                element.addEventListener('change', function() {
                    if (fechaInput.value && salonSelect.value && horaInicioInput.value && horaFinInput.value) {
                        verificarDisponibilidadTiempoReal();
                    }
                });
            });

            // Función para verificar disponibilidad en tiempo real
            function verificarDisponibilidadTiempoReal() {
                const datos = {
                    salon_id: salonSelect.value,
                    fecha: fechaInput.value,
                    hora_inicio: horaInicioInput.value,
                    hora_fin: horaFinInput.value
                };

                fetch('api_verificar_disponibilidad_v2.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(datos)
                })
                .then(response => response.json())
                .then(data => {
                    // Mostrar u ocultar alerta de disponibilidad
                    let alertDiv = document.getElementById('alerta-disponibilidad');
                    if (!alertDiv) {
                        alertDiv = document.createElement('div');
                        alertDiv.id = 'alerta-disponibilidad';
                        alertDiv.className = 'alert mt-3';
                        form.insertBefore(alertDiv, form.firstChild);
                    }

                    if (data.disponible) {
                        alertDiv.className = 'alert alert-success mt-3';
                        alertDiv.innerHTML = '<i class="bi bi-check-circle"></i> <strong>¡Disponible!</strong> El salón está libre en ese horario.';
                    } else {
                        alertDiv.className = 'alert alert-danger mt-3';
                        alertDiv.innerHTML = '<i class="bi bi-x-circle"></i> <strong>No disponible:</strong> ' + (data.motivo || 'El salón ya está reservado en ese horario.');
                    }
                })
                .catch(error => {
                    console.error('Error verificando disponibilidad:', error);
                });
            }
        });
    </script>
</body>
</html>
