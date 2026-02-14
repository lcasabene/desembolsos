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

// Obtener parámetros
$fecha_seleccionada = $_GET['fecha'] ?? date('Y-m-d');
$salon_id = $_GET['salon_id'] ?? 0;

// Validar fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_seleccionada)) {
    $fecha_seleccionada = date('Y-m-d');
}

$fecha_obj = new DateTime($fecha_seleccionada);
$hoy = new DateTime();
$hoy->setTime(0, 0, 0);

if ($fecha_obj < $hoy) {
    $fecha_seleccionada = date('Y-m-d');
    $fecha_obj = new DateTime($fecha_seleccionada);
}

// Obtener salones
$stmt = $pdo->query("SELECT * FROM salones WHERE estado = 'activo' ORDER BY numero");
$salones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener configuración
$stmt = $pdo->query("SELECT parametro, valor FROM configuracion_instalaciones");
$config = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['parametro']] = $row['valor'];
}

// Generar franjas horarias (de 6:00 a 23:59 en intervalos de 30 minutos)
$franjas_horarias = [];
$hora_actual = 6;
$minuto_actual = 0;

while ($hora_actual < 24) {
    $hora_inicio = sprintf('%02d:%02d', $hora_actual, $minuto_actual);
    $minuto_siguiente = $minuto_actual + 30;
    $hora_siguiente = $hora_actual;
    
    if ($minuto_siguiente >= 60) {
        $minuto_siguiente = 0;
        $hora_siguiente++;
    }
    
    $hora_fin = sprintf('%02d:%02d', $hora_siguiente, $minuto_siguiente);
    
    $franjas_horarias[] = [
        'inicio' => $hora_inicio,
        'fin' => $hora_fin,
        'display' => $hora_inicio . ' - ' . substr($hora_fin, 0, 5)
    ];
    
    $minuto_actual = $minuto_siguiente;
    $hora_actual = $hora_siguiente;
}

// Obtener reservas del día
$reservas_por_salon = [];
if ($salon_id > 0) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.nombre as usuario_nombre, u.email as usuario_email
        FROM reservas r
        JOIN usuarios u ON r.usuario_id = u.id
        WHERE r.salon_id = ? AND r.fecha = ? 
        AND r.estado IN ('aprobada', 'pendiente')
        ORDER BY r.hora_inicio
    ");
    $stmt->execute([$salon_id, $fecha_seleccionada]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $reservas_por_salon[$salon_id] = $reservas;
} else {
    // Obtener reservas para todos los salones
    foreach ($salones as $salon) {
        $stmt = $pdo->prepare("
            SELECT r.*, u.nombre as usuario_nombre, u.email as usuario_email
            FROM reservas r
            JOIN usuarios u ON r.usuario_id = u.id
            WHERE r.salon_id = ? AND r.fecha = ? 
            AND r.estado IN ('aprobada', 'pending')
            ORDER BY r.hora_inicio
        ");
        $stmt->execute([$salon['id'], $fecha_seleccionada]);
        $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reservas_por_salon[$salon['id']] = $reservas;
    }
}

// Verificar si es feriado
$stmt = $pdo->prepare("SELECT COUNT(*) FROM feriados WHERE fecha = ? OR (recurrente = 'anual' AND MONTH(fecha) = MONTH(?) AND DAY(fecha) = DAY(?))");
$stmt->execute([$fecha_seleccionada, $fecha_seleccionada, $fecha_seleccionada]);
$es_feriado = $stmt->fetchColumn() > 0;

// Función para verificar si una franja está ocupada
function estaOcupada($franja_inicio, $franja_fin, $reservas) {
    foreach ($reservas as $reserva) {
        $reserva_inicio = $reserva['hora_inicio'];
        $reserva_fin = $reserva['hora_fin'];
        
        // Verificar traslape
        if (($franja_inicio < $reserva_fin) && ($franja_fin > $reserva_inicio)) {
            return [
                'ocupada' => true,
                'reserva' => $reserva
            ];
        }
    }
    return ['ocupada' => false, 'reserva' => null];
}

// Función para obtener clase de estado
function getEstadoClass($estado) {
    switch ($estado) {
        case 'aprobada': return 'success';
        case 'pendiente': return 'warning';
        case 'rechazada': return 'danger';
        case 'cancelada': return 'secondary';
        default: return 'light';
    }
}

// Función para obtener icono de estado
function getEstadoIcon($estado) {
    switch ($estado) {
        case 'aprobada': return 'bi-check-circle-fill';
        case 'pendiente': return 'bi-clock-fill';
        case 'rechazada': return 'bi-x-circle-fill';
        case 'cancelada': return 'bi-x-circle';
        default: return 'bi-circle';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Disponibilidad de Salones - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .disponibilidad-grid {
            display: grid;
            grid-template-columns: 80px repeat(auto-fit, minmax(120px, 1fr));
            gap: 2px;
            background: #dee2e6;
            padding: 2px;
            border-radius: 8px;
            overflow-x: auto;
        }
        
        .franja-hora {
            background: #f8f9fa;
            padding: 8px 4px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: bold;
            border-radius: 4px;
            position: sticky;
            left: 0;
            z-index: 10;
        }
        
        .celda-disponibilidad {
            background: white;
            min-height: 40px;
            padding: 4px;
            text-align: center;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .celda-disponibilidad:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 5;
        }
        
        .celda-disponible {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #b8daff;
        }
        
        .celda-disponible:hover {
            background: linear-gradient(135deg, #c3e6cb 0%, #b8daff 100%);
        }
        
        .celda-ocupada {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 1px solid #f8d7da;
        }
        
        .celda-ocupada:hover {
            background: linear-gradient(135deg, #f5c6cb 0%, #f1b0b7 100%);
        }
        
        .celda-pendiente {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
        }
        
        .celda-pendiente:hover {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
        }
        
        .salon-header {
            background: #007bff;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            border-radius: 8px 8px 0 0;
            margin-bottom: 2px;
        }
        
        .tooltip-custom {
            position: absolute;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            z-index: 1000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
            max-width: 250px;
        }
        
        .tooltip-custom.show {
            opacity: 1;
        }
        
        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 20px;
            font-size: 0.9rem;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            margin-right: 8px;
            border: 1px solid #dee2e6;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .feriado-alert {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border: none;
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .disponibilidad-grid {
                grid-template-columns: 60px repeat(auto-fit, minmax(100px, 1fr));
            }
            
            .franja-hora {
                font-size: 0.7rem;
                padding: 6px 2px;
            }
            
            .celda-disponibilidad {
                min-height: 35px;
                padding: 2px;
            }
        }
    </style>
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
        <!-- Encabezado -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-grid-3x3-gap"></i> 
                            Disponibilidad de Salones
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Controles -->
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label for="fecha" class="form-label">Fecha</label>
                                <input type="date" class="form-control" id="fecha" name="fecha" 
                                       value="<?= htmlspecialchars($fecha_seleccionada) ?>"
                                       min="<?= date('Y-m-d') ?>"
                                       max="<?= date('Y-12-31') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="salon_id" class="form-label">Salón</label>
                                <select class="form-select" id="salon_id" name="salon_id">
                                    <option value="0">Todos los salones</option>
                                    <?php foreach ($salones as $salon): ?>
                                        <option value="<?= $salon['id'] ?>" <?= ($salon_id == $salon['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($salon['numero']) ?> - <?= htmlspecialchars($salon['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-primary w-100" onclick="actualizarVista()">
                                    <i class="bi bi-search"></i> Ver Disponibilidad
                                </button>
                            </div>
                        </div>
                        
                        <!-- Alerta de feriado -->
                        <?php if ($es_feriado): ?>
                            <div class="alert feriado-alert mt-3">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>¡Atención!</strong> El día seleccionado es un feriado. Las reservas pueden estar limitadas.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-card">
            <div class="row text-center">
                <div class="col-md-3">
                    <h5><i class="bi bi-door-open"></i> <?= count($salones) ?></h5>
                    <small>Salones Totales</small>
                </div>
                <div class="col-md-3">
                    <h5><i class="bi bi-clock"></i> <?= count($franjas_horarias) ?></h5>
                    <small>Franjas Horarias</small>
                </div>
                <div class="col-md-3">
                    <h5><i class="bi bi-check-circle"></i> 
                        <?php 
                        $total_disponibles = 0;
                        $total_celdas = 0;
                        foreach ($salones as $salon) {
                            if ($salon_id == 0 || $salon_id == $salon['id']) {
                                foreach ($franjas_horarias as $franja) {
                                    $total_celdas++;
                                    $resultado = estaOcupada($franja['inicio'], $franja['fin'], $reservas_por_salon[$salon['id']] ?? []);
                                    if (!$resultado['ocupada']) {
                                        $total_disponibles++;
                                    }
                                }
                            }
                        }
                        echo $total_disponibles;
                        ?>
                    </h5>
                    <small>Horarios Disponibles</small>
                </div>
                <div class="col-md-3">
                    <h5><i class="bi bi-x-circle"></i> <?= $total_celdas - $total_disponibles ?></h5>
                    <small>Horarios Ocupados</small>
                </div>
            </div>
        </div>

        <!-- Leyenda -->
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="card-title">Leyenda</h6>
                <div class="d-flex flex-wrap">
                    <div class="legend-item">
                        <div class="legend-color celda-disponible"></div>
                        <span>Disponible</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color celda-ocupada"></div>
                        <span>Ocupado (Aprobado)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color celda-pendiente"></div>
                        <span>Pendiente de Aprobación</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grid de Disponibilidad -->
        <?php if ($salon_id > 0): ?>
            <!-- Vista de un solo salón -->
            <?php 
            $salon_actual = null;
            foreach ($salones as $salon) {
                if ($salon['id'] == $salon_id) {
                    $salon_actual = $salon;
                    break;
                }
            }
            ?>
            
            <div class="card">
                <div class="salon-header">
                    <i class="bi bi-door-open"></i> 
                    <?= htmlspecialchars($salon_actual['numero']) ?> - <?= htmlspecialchars($salon_actual['nombre']) ?>
                    <small class="d-block mt-1">Capacidad: <?= $salon_actual['capacidad'] ?> personas</small>
                </div>
                <div class="card-body p-2">
                    <div class="disponibilidad-grid">
                        <!-- Franjas horarias -->
                        <div class="franja-hora">Hora</div>
                        <?php foreach ($franjas_horarias as $franja): ?>
                            <div class="celda-disponibilidad" 
                                 onclick="seleccionarHorario('<?= $franja['inicio'] ?>', '<?= $franja['fin'] ?>')"
                                 data-hora="<?= $franja['inicio'] ?>"
                                 data-estado="disponible">
                                <small><?= substr($franja['inicio'], 0, 5) ?></small>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Estado de disponibilidad -->
                        <div class="franja-hora">Estado</div>
                        <?php foreach ($franjas_horarias as $franja): ?>
                            <?php 
                            $resultado = estaOcupada($franja['inicio'], $franja['fin'], $reservas_por_salon[$salon_id] ?? []);
                            if ($resultado['ocupada']) {
                                $clase = 'celda-ocupada';
                                if ($resultado['reserva']['estado'] === 'pendiente') {
                                    $clase = 'celda-pendiente';
                                }
                                $icono = getEstadoIcon($resultado['reserva']['estado']);
                                $tooltip = "Reserva {$resultado['reserva']['estado']}<br>" .
                                          "Usuario: {$resultado['reserva']['usuario_nombre']}<br>" .
                                          "Horario: {$resultado['reserva']['hora_inicio']} - {$resultado['reserva']['hora_fin']}<br>" .
                                          "Motivo: {$resultado['reserva']['motivo']}";
                            } else {
                                $clase = 'celda-disponible';
                                $icono = 'bi-plus-circle';
                                $tooltip = "Disponible para reservar<br>{$franja['display']}";
                            }
                            ?>
                            <div class="celda-disponibilidad <?= $clase ?>" 
                                 onmouseover="mostrarTooltip(event, '<?= htmlspecialchars($tooltip) ?>')"
                                 onmouseout="ocultarTooltip()"
                                 onclick="seleccionarHorario('<?= $franja['inicio'] ?>', '<?= $franja['fin'] ?>')">
                                <i class="bi <?= $icono ?>"></i>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Vista de todos los salones -->
            <?php foreach ($salones as $salon): ?>
                <div class="card mb-3">
                    <div class="salon-header">
                        <i class="bi bi-door-open"></i> 
                        <?= htmlspecialchars($salon['numero']) ?> - <?= htmlspecialchars($salon['nombre']) ?>
                        <small class="d-block mt-1">Capacidad: <?= $salon['capacidad'] ?> personas</small>
                    </div>
                    <div class="card-body p-2">
                        <div class="disponibilidad-grid">
                            <!-- Franjas horarias -->
                            <div class="franja-hora">Hora</div>
                            <?php foreach ($franjas_horarias as $franja): ?>
                                <div class="celda-disponibilidad">
                                    <small><?= substr($franja['inicio'], 0, 5) ?></small>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Estado de disponibilidad -->
                            <div class="franja-hora">Estado</div>
                            <?php foreach ($franjas_horarias as $franja): ?>
                                <?php 
                                $resultado = estaOcupada($franja['inicio'], $franja['fin'], $reservas_por_salon[$salon['id']] ?? []);
                                if ($resultado['ocupada']) {
                                    $clase = 'celda-ocupada';
                                    if ($resultado['reserva']['estado'] === 'pendiente') {
                                        $clase = 'celda-pendiente';
                                    }
                                    $icono = getEstadoIcon($resultado['reserva']['estado']);
                                    $tooltip = "Reserva {$resultado['reserva']['estado']}<br>" .
                                              "Usuario: {$resultado['reserva']['usuario_nombre']}<br>" .
                                              "Horario: {$resultado['reserva']['hora_inicio']} - {$resultado['reserva']['hora_fin']}<br>" .
                                              "Motivo: {$resultado['reserva']['motivo']}";
                                } else {
                                    $clase = 'celda-disponible';
                                    $icono = 'bi-plus-circle';
                                    $tooltip = "Disponible para reservar<br>{$franja['display']}";
                                }
                                ?>
                                <div class="celda-disponibilidad <?= $clase ?>" 
                                     onmouseover="mostrarTooltip(event, '<?= htmlspecialchars($tooltip) ?>')"
                                     onmouseout="ocultarTooltip()"
                                     onclick="seleccionarHorario('<?= $franja['inicio'] ?>', '<?= $franja['fin'] ?>', <?= $salon['id'] ?>)">
                                    <i class="bi <?= $icono ?>"></i>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Botones de acción -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <a href="nueva_reserva.php" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle"></i> Crear Nueva Reserva
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="calendario.php" class="btn btn-primary w-100">
                            <i class="bi bi-calendar"></i> Vista de Calendario
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tooltip personalizado -->
    <div id="tooltip" class="tooltip-custom"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function actualizarVista() {
            const fecha = document.getElementById('fecha').value;
            const salonId = document.getElementById('salon_id').value;
            window.location.href = `disponibilidad_mejorada.php?fecha=${fecha}&salon_id=${salonId}`;
        }

        function seleccionarHorario(horaInicio, horaFin, salonId = null) {
            // Verificar si está disponible
            event.stopPropagation();
            
            const celda = event.currentTarget;
            if (celda.classList.contains('celda-ocupada')) {
                return; // No permitir reservar en horarios ocupados
            }
            
            // Construir URL para nueva reserva
            const fecha = document.getElementById('fecha').value;
            const salonSeleccionado = salonId || document.getElementById('salon_id').value;
            
            if (salonSeleccionado == 0) {
                alert('Por favor, selecciona un salón específico primero.');
                return;
            }
            
            const url = `nueva_reserva.php?salon_id=${salonSeleccionado}&fecha=${fecha}&hora_inicio=${horaInicio}&hora_fin=${horaFin}`;
            window.location.href = url;
        }

        function mostrarTooltip(event, texto) {
            const tooltip = document.getElementById('tooltip');
            tooltip.innerHTML = texto.replace(/<br>/g, '\n');
            tooltip.style.left = event.pageX + 10 + 'px';
            tooltip.style.top = event.pageY + 10 + 'px';
            tooltip.classList.add('show');
        }

        function ocultarTooltip() {
            const tooltip = document.getElementById('tooltip');
            tooltip.classList.remove('show');
        }

        // Navegación con teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                // Día anterior
                const fechaActual = new Date(document.getElementById('fecha').value);
                fechaActual.setDate(fechaActual.getDate() - 1);
                const nuevaFecha = fechaActual.toISOString().split('T')[0];
                if (nuevaFecha >= new Date().toISOString().split('T')[0]) {
                    document.getElementById('fecha').value = nuevaFecha;
                    actualizarVista();
                }
            } else if (e.key === 'ArrowRight') {
                // Día siguiente
                const fechaActual = new Date(document.getElementById('fecha').value);
                fechaActual.setDate(fechaActual.getDate() + 1);
                document.getElementById('fecha').value = fechaActual.toISOString().split('T')[0];
                actualizarVista();
            }
        });

        // Auto-actualizar cada 5 minutos
        setInterval(function() {
            if (confirm('¿Deseas actualizar la disponibilidad?')) {
                actualizarVista();
            }
        }, 300000);
    </script>
</body>
</html>
