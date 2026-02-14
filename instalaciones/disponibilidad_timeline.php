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

// Obtener reservas del día para todos los salones
$reservas_por_salon = [];
foreach ($salones as $salon) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.nombre as usuario_nombre, u.email as usuario_email
        FROM reservas r
        JOIN usuarios u ON r.usuario_id = u.id
        WHERE r.salon_id = ? AND r.fecha = ? 
        AND r.estado IN ('aprobada', 'pendiente')
        ORDER BY r.hora_inicio
    ");
    $stmt->execute([$salon['id'], $fecha_seleccionada]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $reservas_por_salon[$salon['id']] = $reservas;
}

// Verificar si es feriado
$stmt = $pdo->prepare("SELECT COUNT(*) FROM feriados WHERE fecha = ? OR (recurrente = 'anual' AND MONTH(fecha) = MONTH(?) AND DAY(fecha) = DAY(?))");
$stmt->execute([$fecha_seleccionada, $fecha_seleccionada, $fecha_seleccionada]);
$es_feriado = $stmt->fetchColumn() > 0;

// Generar timeline completo del día (de 6:00 a 23:00)
$timeline = [];
for ($hora = 6; $hora <= 23; $hora++) {
    for ($minuto = 0; $minuto < 60; $minuto += 30) {
        $hora_inicio = sprintf('%02d:%02d', $hora, $minuto);
        $minuto_siguiente = $minuto + 30;
        $hora_siguiente = $hora;
        
        if ($minuto_siguiente >= 60) {
            $minuto_siguiente = 0;
            $hora_siguiente++;
        }
        
        $hora_fin = sprintf('%02d:%02d', $hora_siguiente, $minuto_siguiente);
        
        $timeline[] = [
            'hora_inicio' => $hora_inicio,
            'hora_fin' => $hora_fin,
            'display' => substr($hora_inicio, 0, 5) . ' - ' . substr($hora_fin, 0, 5),
            'minutos_inicio' => $hora * 60 + $minuto,
            'minutos_fin' => $hora_siguiente * 60 + $minuto_siguiente
        ];
    }
}

// Función para verificar si una franja está ocupada
function estaOcupada($franja, $reservas) {
    foreach ($reservas as $reserva) {
        $reserva_inicio = strtotime($reserva['hora_inicio']);
        $reserva_fin = strtotime($reserva['hora_fin']);
        
        if (($franja['minutos_inicio'] < $reserva_fin) && ($franja['minutos_fin'] > $reserva_inicio)) {
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
        case 'aprobada': return 'bg-danger text-white';
        case 'pendiente': return 'bg-warning text-dark';
        case 'rechazada': return 'bg-secondary text-white';
        case 'cancelada': return 'bg-light text-dark';
        default: return 'bg-light text-dark';
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
    <title>Timeline de Disponibilidad - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .timeline-container {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            overflow-x: auto;
        }
        
        .timeline-header {
            display: grid;
            grid-template-columns: 120px repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .timeline-grid {
            display: grid;
            grid-template-columns: 120px repeat(auto-fit, minmax(200px, 1fr));
            gap: 2px;
            background: #dee2e6;
            padding: 2px;
            border-radius: 8px;
        }
        
        .hora-column {
            background: #495057;
            color: white;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 0.85rem;
            border-radius: 4px;
            position: sticky;
            left: 0;
            z-index: 10;
        }
        
        .salon-column {
            background: white;
            min-height: 50px;
            position: relative;
            border-radius: 4px;
        }
        
        .bloque-ocupado {
            position: absolute;
            left: 0;
            right: 0;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 0.75rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 5;
        }
        
        .bloque-ocupado:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10;
        }
        
        .salon-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            border-radius: 8px;
            margin-bottom: 2px;
        }
        
        .timeline-legend {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 25px;
            margin-bottom: 10px;
        }
        
        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            margin-right: 10px;
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
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .tooltip-custom {
            position: absolute;
            background: rgba(0,0,0,0.95);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            z-index: 1000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
            max-width: 300px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }
        
        .tooltip-custom.show {
            opacity: 1;
        }
        
        .reserva-detalle {
            line-height: 1.4;
        }
        
        .reserva-detalle strong {
            color: #ffd700;
        }
        
        @media (max-width: 768px) {
            .timeline-grid {
                grid-template-columns: 80px repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .timeline-header {
                grid-template-columns: 80px repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .hora-column {
                font-size: 0.75rem;
                padding: 6px 4px;
            }
            
            .bloque-ocupado {
                font-size: 0.7rem;
                padding: 3px 6px;
            }
        }
        
        /* Línea de tiempo actual */
        .current-time-line {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #ff6b6b;
            z-index: 20;
            pointer-events: none;
        }
        
        .current-time-label {
            position: absolute;
            top: -20px;
            background: #ff6b6b;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: bold;
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
                            <i class="bi bi-clock-history"></i> 
                            Timeline de Disponibilidad
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
                                       max="<?= date('Y-12-31') ?>"
                                       onchange="actualizarVista()">
                            </div>
                            <div class="col-md-4">
                                <label for="salon_id" class="form-label">Salón</label>
                                <select class="form-select" id="salon_id" name="salon_id" onchange="actualizarVista()">
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
                                    <i class="bi bi-search"></i> Actualizar Vista
                                </button>
                            </div>
                        </div>
                        
                        <!-- Alerta de feriado -->
                        <?php if ($es_feriado): ?>
                            <div class="feriado-alert mt-3">
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
                    <small>Salones Activos</small>
                </div>
                <div class="col-md-3">
                    <h5><i class="bi bi-clock"></i> <?= count($timeline) ?></h5>
                    <small>Franjas Horarias</small>
                </div>
                <div class="col-md-3">
                    <h5><i class="bi bi-check-circle"></i> 
                        <?php 
                        $total_disponibles = 0;
                        $total_celdas = 0;
                        foreach ($salones as $salon) {
                            if ($salon_id == 0 || $salon_id == $salon['id']) {
                                foreach ($timeline as $franja) {
                                    $total_celdas++;
                                    $resultado = estaOcupada($franja, $reservas_por_salon[$salon['id']] ?? []);
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

        <!-- Timeline Principal -->
        <div class="timeline-container">
            <?php 
            // Filtrar salones según selección
            $salones_mostrar = [];
            if ($salon_id > 0) {
                foreach ($salones as $salon) {
                    if ($salon['id'] == $salon_id) {
                        $salones_mostrar = [$salon];
                        break;
                    }
                }
            } else {
                $salones_mostrar = $salones;
            }
            
            foreach ($salones_mostrar as $salon): 
            ?>
                <div class="mb-4">
                    <!-- Header del salón -->
                    <div class="salon-header">
                        <i class="bi bi-door-open"></i> 
                        <?= htmlspecialchars($salon['numero']) ?> - <?= htmlspecialchars($salon['nombre']) ?>
                        <small class="d-block mt-1">Capacidad: <?= $salon['capacidad'] ?> personas</small>
                    </div>
                    
                    <!-- Grid del timeline -->
                    <div class="timeline-grid">
                        <!-- Columna de horas -->
                        <div class="hora-column">Hora</div>
                        <?php foreach ($timeline as $franja): ?>
                            <div class="hora-column"><?= substr($franja['hora_inicio'], 0, 5) ?></div>
                        <?php endforeach; ?>
                        
                        <!-- Columna del salón -->
                        <div class="hora-column">Estado</div>
                        <?php foreach ($timeline as $franja): ?>
                            <?php 
                            $resultado = estaOcupada($franja, $reservas_por_salon[$salon['id']] ?? []);
                            if ($resultado['ocupada']) {
                                $clase = getEstadoClass($resultado['reserva']['estado']);
                                $icono = getEstadoIcon($resultado['reserva']['estado']);
                                $tooltip = "Reserva {$resultado['reserva']['estado']}<br>" .
                                          "Usuario: {$resultado['reserva']['usuario_nombre']}<br>" .
                                          "Horario: {$resultado['reserva']['hora_inicio']} - {$resultado['reserva']['hora_fin']}<br>" .
                                          "Motivo: {$resultado['reserva']['motivo']}";
                                
                                // Calcular posición y tamaño del bloque
                                $reserva_inicio = strtotime($resultado['reserva']['hora_inicio']);
                                $reserva_fin = strtotime($resultado['reserva']['hora_fin']);
                                $franja_inicio = $franja['minutos_inicio'];
                                $franja_fin = $franja['minutos_fin'];
                                
                                // Si esta franja está dentro de una reserva más grande
                                if ($franja_inicio >= $reserva_inicio && $franja_fin <= $reserva_fin) {
                                    $width = '100%';
                                    $left = '0%';
                                } else {
                                    $width = '100%';
                                    $left = '0%';
                                }
                            } else {
                                $clase = 'bg-success text-white';
                                $icono = 'bi-plus-circle';
                                $tooltip = "Disponible para reservar<br>{$franja['display']}";
                                $width = '100%';
                                $left = '0%';
                            }
                            ?>
                            <div class="salon-column" 
                                 style="position: relative;"
                                 onmouseover="mostrarTooltip(event, '<?= htmlspecialchars($tooltip) ?>')"
                                 onmouseout="ocultarTooltip()"
                                 onclick="seleccionarHorario('<?= $franja['hora_inicio'] ?>', '<?= $franja['hora_fin'] ?>', <?= $salon['id'] ?>)">
                                
                                <?php if ($resultado['ocupada']): ?>
                                    <div class="bloque-ocupado <?= $clase ?>" 
                                         style="width: <?= $width ?>; left: <?= $left ?>;">
                                        <i class="bi <?= $icono ?>"></i>
                                        <span class="ms-1"><?= htmlspecialchars($resultado['reserva']['usuario_nombre']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100">
                                        <i class="bi <?= $icono ?> fs-5"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Leyenda -->
        <div class="timeline-legend">
            <h6 class="mb-3">Leyenda</h6>
            <div class="d-flex flex-wrap">
                <div class="legend-item">
                    <div class="legend-color bg-success"></div>
                    <span>Disponible</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color bg-warning"></div>
                    <span>Pendiente de Aprobación</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color bg-danger"></div>
                    <span>Ocupado (Aprobado)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color bg-secondary"></div>
                    <span>Cancelado/Rechazado</span>
                </div>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <a href="nueva_reserva.php" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle"></i> Crear Nueva Reserva
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="disponibilidad_mejorada.php" class="btn btn-info w-100">
                            <i class="bi bi-grid-3x3-gap"></i> Vista Grid
                        </a>
                    </div>
                    <div class="col-md-4">
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
            window.location.href = `disponibilidad_timeline.php?fecha=${fecha}&salon_id=${salonId}`;
        }

        function seleccionarHorario(horaInicio, horaFin, salonId) {
            // Verificar si está disponible
            event.stopPropagation();
            
            const celda = event.currentTarget;
            const bloqueOcupado = celda.querySelector('.bloque-ocupado');
            
            if (bloqueOcupado) {
                return; // No permitir reservar en horarios ocupados
            }
            
            // Construir URL para nueva reserva
            const fecha = document.getElementById('fecha').value;
            
            const url = `nueva_reserva.php?salon_id=${salonId}&fecha=${fecha}&hora_inicio=${horaInicio}&hora_fin=${horaFin}`;
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
