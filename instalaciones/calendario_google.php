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
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$salon_id = $_GET['salon_id'] ?? 0;

// Validar mes y año
if ($mes < 1 || $mes > 12) $mes = date('m');
if ($anio < 2020 || $anio > 2030) $anio = date('Y');

// Validar que no exceda el año actual
$anio_actual = (int)date('Y');
if ($anio > $anio_actual) {
    $anio = $anio_actual;
    if ($mes > (int)date('m')) {
        $mes = date('m');
    }
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

// Obtener reservas del mes
$fecha_inicio = "$anio-$mes-01";
$fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

$where_salon = $salon_id > 0 ? "AND r.salon_id = $salon_id" : "";

$stmt = $pdo->prepare("
    SELECT r.*, s.nombre as salon_nombre, s.numero as salon_numero,
           u.nombre as usuario_nombre
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.fecha BETWEEN ? AND ? AND r.estado IN ('aprobada', 'pendiente') $where_salon
    ORDER BY r.fecha, r.hora_inicio
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar reservas por día y salón
$reservas_por_dia = [];
foreach ($reservas as $reserva) {
    $dia = date('j', strtotime($reserva['fecha']));
    $salon_key = $reserva['salon_id'];
    $reservas_por_dia[$dia][$salon_key][] = $reserva;
}

// Obtener feriados del mes
$stmt = $pdo->prepare("
    SELECT * FROM feriados 
    WHERE fecha BETWEEN ? AND ? 
       OR (recurrente = 'anual' AND 
           DATE(CONCAT(?, '-', MONTH(fecha), '-', DAY(fecha))) BETWEEN ? AND ?)
");
$stmt->execute([$fecha_inicio, $fecha_fin, $anio, $fecha_inicio, $fecha_fin]);
$feriados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$feriados_por_dia = [];
foreach ($feriados as $feriado) {
    $dia = date('j', strtotime($feriado['fecha']));
    $feriados_por_dia[$dia] = $feriado;
}

// Calcular días del mes
$dias_en_mes = date('t', mktime(0, 0, 0, $mes, 1, $anio));
$primer_dia_semana = date('N', mktime(0, 0, 0, $mes, 1, $anio));

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

// Nombre del mes
$nombres_mes = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calendario de Disponibilidad - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .calendar-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .calendar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: 80px repeat(auto-fit, minmax(250px, 1fr));
            gap: 1px;
            background: #dee2e6;
            border: 1px solid #dee2e6;
        }
        
        .day-header {
            background: #495057;
            color: white;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 0.9rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .day-cell {
            background: white;
            min-height: 600px;
            position: relative;
            overflow: hidden;
        }
        
        .day-number {
            background: #f8f9fa;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            border-bottom: 1px solid #dee2e6;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .day-number.today {
            background: #007bff;
            color: white;
        }
        
        .day-number.holiday {
            background: #dc3545;
            color: white;
        }
        
        .day-number.weekend {
            background: #6c757d;
            color: white;
        }
        
        .timeline-day {
            padding: 8px;
            height: calc(100% - 45px);
            overflow-y: auto;
        }
        
        .salon-timeline {
            margin-bottom: 8px;
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }
        
        .salon-header-mini {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            font-size: 0.75rem;
            font-weight: bold;
            border-radius: 4px 4px 0 0;
        }
        
        .time-blocks {
            position: relative;
            height: 400px;
            background: linear-gradient(to bottom, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .time-lines {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }
        
        .time-line {
            position: absolute;
            left: 0;
            right: 0;
            height: 1px;
            background: #dee2e6;
            font-size: 0.7rem;
        }
        
        .reservation-block {
            position: absolute;
            left: 2px;
            right: 2px;
            border-radius: 4px;
            padding: 2px 4px;
            font-size: 0.7rem;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 3;
        }
        
        .reservation-block:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 4;
        }
        
        .time-label {
            position: absolute;
            left: -35px;
            font-size: 0.65rem;
            color: #6c757d;
            font-weight: bold;
            width: 30px;
            text-align: right;
        }
        
        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 20px;
            font-size: 0.85rem;
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
        
        .salon-selector {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .calendar-grid {
                grid-template-columns: 60px repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .day-cell {
                min-height: 400px;
            }
            
            .time-label {
                font-size: 0.6rem;
                width: 25px;
            }
            
            .reservation-block {
                font-size: 0.6rem;
            }
        }
        
        /* Scrollbar personalizado */
        .timeline-day::-webkit-scrollbar {
            width: 6px;
        }
        
        .timeline-day::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .timeline-day::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .timeline-day::-webkit-scrollbar-thumb:hover {
            background: #555;
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
        <!-- Selector de salón -->
        <div class="salon-selector">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <label for="salon_id" class="form-label">Salón</label>
                    <select class="form-select" id="salon_id" onchange="cambiarSalon()">
                        <option value="0">Todos los salones</option>
                        <?php foreach ($salones as $salon): ?>
                            <option value="<?= $salon['id'] ?>" <?= ($salon_id == $salon['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($salon['numero']) ?> - <?= htmlspecialchars($salon['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <h4 class="mb-0">
                        <i class="bi bi-calendar3"></i> 
                        <?= $nombres_mes[(int)$mes] ?> <?= $anio ?>
                    </h4>
                </div>
                <div class="col-md-3 text-end">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="cambiarMes(-1)" 
                                <?= ($mes == 1 && $anio == $anio_actual) ? 'disabled' : '' ?>>
                            <i class="bi bi-chevron-left"></i> Anterior
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="irHoy()">Hoy</button>
                        <button type="button" class="btn btn-outline-primary" onclick="cambiarMes(1)" 
                                <?= ($mes == 12 && $anio == $anio_actual) ? 'disabled' : '' ?>>
                            Siguiente <i class="bi bi-chevron-right"></i>
                        </button>
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
                    <h5><i class="bi bi-calendar-check"></i> <?= $dias_en_mes ?></h5>
                    <small>Días del Mes</small>
                </div>
                <div class="col-md-3">
                    <h5><i class="bi bi-check-circle"></i> 
                        <?php 
                        $total_reservas = 0;
                        foreach ($reservas_por_dia as $dia_reservas) {
                            foreach ($dia_reservas as $salon_reservas) {
                                $total_reservas += count($salon_reservas);
                            }
                        }
                        echo $total_reservas;
                        ?>
                    </h5>
                    <small>Reservas Totales</small>
                </div>
                <div class="col-md-3">
                    <h5><i class="bi bi-x-circle"></i> 
                        <?php 
                        $total_ocupacion = 0;
                        foreach ($reservas_por_dia as $dia_reservas) {
                            foreach ($dia_reservas as $salon_reservas) {
                                $total_ocupacion += count($salon_reservas);
                            }
                        }
                        $posibles = $dias_en_mes * count($salones_mostrar = $salon_id > 0 ? 
                            array_filter($salones, fn($s) => $s['id'] == $salon_id) : 
                            $salones);
                        echo $total_ocupacion . '/' . ($posibles * 8); // 8 horas promedio por día
                        ?>
                    </h5>
                    <small>Ocupación Total</small>
                </div>
            </div>
        </div>

        <!-- Calendario Google Style -->
        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <button type="button" class="btn btn-light" onclick="cambiarMes(-1)" 
                            <?= ($mes == 1 && $anio == $anio_actual) ? 'disabled' : '' ?>>
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <h3 class="mb-0">
                        <?= $nombres_mes[(int)$mes] ?> <?= $anio ?>
                    </h3>
                    <button type="button" class="btn btn-light" onclick="cambiarMes(1)" 
                            <?= ($mes == 12 && $anio == $anio_actual) ? 'disabled' : '' ?>>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="calendar-grid">
                <!-- Encabezados de días -->
                <div class="day-header">Día</div>
                <?php 
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
                    <div class="day-header">
                        <?= htmlspecialchars($salon['numero']) ?>
                        <small class="d-block"><?= htmlspecialchars($salon['nombre']) ?></small>
                    </div>
                <?php endforeach; ?>
                
                <!-- Días del mes -->
                <?php for ($dia = 1; $dia <= $dias_en_mes; $dia++): ?>
                    <div class="day-cell">
                        <div class="day-number <?php 
                            $es_hoy = ($dia == date('j') && $mes == date('m') && $anio == date('Y'));
                            $es_finde = (date('N', mktime(0, 0, 0, $mes, $dia, $anio)) >= 6);
                            $es_feriado = isset($feriados_por_dia[$dia]);
                            
                            if ($es_hoy) echo 'today';
                            if ($es_feriado) echo 'holiday';
                            if ($es_finde) echo 'weekend';
                        ?>">
                            <?= $dia ?>
                            <?php if ($es_feriado): ?>
                                <i class="bi bi-star-fill ms-1"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="timeline-day">
                            <?php foreach ($salones_mostrar as $salon): ?>
                                <div class="salon-timeline">
                                    <div class="salon-header-mini">
                                        <?= htmlspecialchars($salon['numero']) ?>
                                    </div>
                                    <div class="time-blocks">
                                        <!-- Líneas de tiempo cada hora -->
                                        <div class="time-lines">
                                            <?php for ($hora = 6; $hora <= 22; $hora++): ?>
                                                <?php 
                                                $top = ($hora - 6) * 50; // 50px por hora
                                                $hora_formateada = sprintf('%02d:00', $hora);
                                                ?>
                                                <div class="time-line" style="top: <?= $top ?>px;">
                                                    <span class="time-label"><?= $hora_formateada ?></span>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                        
                                        <!-- Reservas del día -->
                                        <?php if (isset($reservas_por_dia[$dia][$salon['id']])): ?>
                                            <?php foreach ($reservas_por_dia[$dia][$salon['id']] as $reserva): ?>
                                                <?php 
                                                // Calcular posición y tamaño del bloque
                                                $reserva_inicio = strtotime($reserva['hora_inicio']);
                                                $reserva_fin = strtotime($reserva['hora_fin']);
                                                
                                                $minutos_inicio = (date('H', $reserva_inicio) * 60) + date('i', $reserva_inicio);
                                                $minutos_fin = (date('H', $reserva_fin) * 60) + date('i', $reserva_fin);
                                                
                                                $top = (($minutos_inicio - 360) / 60) * 50; // 360 = 6:00 AM en minutos
                                                $height = (($minutos_fin - $minutos_inicio) / 60) * 50;
                                                
                                                $clase = getEstadoClass($reserva['estado']);
                                                ?>
                                                <div class="reservation-block <?= $clase ?>" 
                                                     style="top: <?= $top ?>px; height: <?= $height ?>px;"
                                                     onmouseover="mostrarTooltip(event, '<?= htmlspecialchars($reserva['usuario_nombre']) ?>\n<?= $reserva['hora_inicio'] ?> - <?= $reserva['hora_fin'] ?>\n<?= htmlspecialchars($reserva['motivo']) ?>')"
                                                     onmouseout="ocultarTooltip()"
                                                     onclick="verReserva(<?= $reserva['id'] ?>)">
                                                    <i class="bi <?= getEstadoIcon($reserva['estado']) ?>"></i>
                                                    <div class="small"><?= htmlspecialchars($reserva['hora_inicio']) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
            </div>
        </div>
    </div>

        <!-- Leyenda -->
        <div class="card mt-4">
            <div class="card-body">
                <h6 class="card-title">Leyenda</h6>
                <div class="d-flex flex-wrap">
                    <div class="legend-item">
                        <div class="legend-color bg-danger"></div>
                        <span>Ocupado (Aprobado)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color bg-warning"></div>
                        <span>Pendiente de Aprobación</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color bg-primary"></div>
                        <span>Día Actual</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color bg-secondary"></div>
                        <span>Fin de Semana</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #dc3545;"></div>
                        <span>Día Feriado</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="nueva_reserva.php" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle"></i> Crear Nueva Reserva
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="disponibilidad_timeline.php" class="btn btn-info w-100">
                            <i class="bi bi-clock-history"></i> Timeline Diario
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="disponibilidad_mejorada.php" class="btn btn-primary w-100">
                            <i class="bi bi-grid-3x3-gap"></i> Vista Grid
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="calendario.php" class="btn btn-secondary w-100">
                            <i class="bi bi-calendar"></i> Calendario Original
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tooltip -->
    <div id="tooltip" class="tooltip-custom" style="
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
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    "></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cambiarMes(direccion) {
            let mes = <?= $mes ?> + direccion;
            let anio = <?= $anio ?>;
            
            if (mes > 12) {
                mes = 1;
                anio++;
            } else if (mes < 1) {
                mes = 12;
                anio--;
            }
            
            // Validar que no exceda el año actual
            const anioActual = new Date().getFullYear();
            if (anio > anioActual) {
                return; // No permitir navegar a años futuros
            }
            
            const salonId = document.getElementById('salon_id').value;
            window.location.href = `calendario_google.php?mes=${mes}&anio=${anio}&salon_id=${salonId}`;
        }

        function cambiarSalon() {
            const salonId = document.getElementById('salon_id').value;
            window.location.href = `calendario_google.php?mes=<?= $mes ?>&anio=<?= $anio ?>&salon_id=${salonId}`;
        }

        function irHoy() {
            const hoy = new Date();
            window.location.href = `calendario_google.php?mes=${hoy.getMonth() + 1}&anio=${hoy.getFullYear()}&salon_id=${document.getElementById('salon_id').value}`;
        }

        function verReserva(reservaId) {
            window.open(`reserva_detalle.php?id=${reservaId}`, '_blank', 'width=800,height=600');
        }

        function mostrarTooltip(event, texto) {
            const tooltip = document.getElementById('tooltip');
            tooltip.innerHTML = texto.replace(/\n/g, '<br>');
            tooltip.style.left = event.pageX + 10 + 'px';
            tooltip.style.top = event.pageY + 10 + 'px';
            tooltip.style.opacity = '1';
        }

        function ocultarTooltip() {
            const tooltip = document.getElementById('tooltip');
            tooltip.style.opacity = '0';
        }

        // Navegación con teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                cambiarMes(-1);
            } else if (e.key === 'ArrowRight') {
                cambiarMes(1);
            }
        });
    </script>
</body>
</html>
