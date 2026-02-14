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

// Obtener parámetros
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
$salon_id = isset($_GET['salon_id']) ? intval($_GET['salon_id']) : 0;

// Validar mes y año
if ($mes < 1 || $mes > 12) $mes = date('n');
$anio_actual = date('Y');
if ($anio < 2020 || $anio > $anio_actual) $anio = $anio_actual;

// Obtener salones
$stmt = $pdo->query("SELECT * FROM salones WHERE estado = 'activo' ORDER BY numero");
$salones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener reservas del mes
$fecha_inicio = "$anio-$mes-01";
$fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

$where_salon = $salon_id > 0 ? "AND r.salon_id = $salon_id" : "";

// Los administradores y porteros pueden ver todas las reservas, los demás solo las suyas
$where_usuario = $es_admin_o_portero ? "" : "AND r.usuario_id = " . $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT r.*, s.nombre as salon_nombre, s.numero as salon_numero, 
           u.nombre as usuario_nombre
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.fecha BETWEEN ? AND ? $where_salon $where_usuario
    ORDER BY r.fecha, r.hora_inicio
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener feriados del mes
$stmt = $pdo->prepare("
    SELECT * FROM feriados 
    WHERE fecha BETWEEN ? AND ? 
       OR (recurrente = 'anual' AND 
           DATE(CONCAT(?, '-', MONTH(fecha), '-', DAY(fecha))) BETWEEN ? AND ?)
");
$stmt->execute([$fecha_inicio, $fecha_fin, $anio, $fecha_inicio, $fecha_fin]);
$feriados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar datos por día
$reservas_por_dia = [];
foreach ($reservas as $reserva) {
    $dia = date('j', strtotime($reserva['fecha']));
    $reservas_por_dia[$dia][] = $reserva;
}

$feriados_por_dia = [];
foreach ($feriados as $feriado) {
    $dia = date('j', strtotime($feriado['fecha']));
    $feriados_por_dia[$dia] = $feriado;
}

// Calcular días del mes
$primer_dia = date('N', strtotime($fecha_inicio));
$dias_mes = date('t', strtotime($fecha_inicio));
$mes_anterior = $mes == 1 ? 12 : $mes - 1;
$anio_anterior = $mes == 1 ? $anio - 1 : $anio;
$mes_siguiente = $mes == 12 ? 1 : $mes + 1;
$anio_siguiente = $mes == 12 ? $anio + 1 : $anio;

$nombres_meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calendario de Reservas - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .calendar-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #dee2e6;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        .calendar-day-header {
            background: #0d6efd;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
        }
        .calendar-day {
            background: white;
            min-height: 100px;
            padding: 5px;
            position: relative;
        }
        .calendar-day-number {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .calendar-day.other-month {
            background: #f8f9fa;
            color: #6c757d;
        }
        .calendar-day.today {
            background: #e7f3ff;
        }
        .calendar-day.feriado {
            background: #fff3cd;
        }
        .calendar-day.weekend {
            background: #f8f9fa;
        }
        .reserva-item {
            font-size: 0.75rem;
            padding: 2px 4px;
            margin: 1px 0;
            border-radius: 3px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .reserva-item.aprobada {
            background: #d1e7dd;
            color: #0f5132;
        }
        .reserva-item.pendiente {
            background: #fff3cd;
            color: #664d03;
        }
        .reserva-item.rechazada {
            background: #f8d7da;
            color: #721c24;
        }
        .reserva-tooltip {
            position: absolute;
            background: #333;
            color: white;
            padding: 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            z-index: 1000;
            max-width: 250px;
            display: none;
        }
        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        .legend-color {
            width: 20px;
            height: 15px;
            border-radius: 3px;
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
        <div class="calendar-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-calendar-week"></i> Calendario de Reservas</h2>
                <div class="d-flex gap-2">
                    <select class="form-select" id="salonFilter" style="width: 200px;">
                        <option value="0">Todos los salones</option>
                        <?php foreach ($salones as $salon): ?>
                            <option value="<?= $salon['id'] ?>" <?= $salon_id == $salon['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($salon['numero']) ?> - <?= htmlspecialchars($salon['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary" onclick="exportarCalendario()">
                        <i class="bi bi-download"></i> Exportar
                    </button>
                </div>
            </div>

            <!-- Leyenda -->
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #d1e7dd;"></div>
                    <span>Reserva Aprobada</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #fff3cd;"></div>
                    <span>Reserva Pendiente</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #f8d7da;"></div>
                    <span>Reserva Rechazada</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #fff3cd;"></div>
                    <span>Feriado</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #e7f3ff;"></div>
                    <span>Hoy</span>
                </div>
                <div class="legend-item">
                    <i class="bi bi-info-circle text-primary"></i>
                    <span>Las reservas están limitadas al año <?= date('Y') ?></span>
                </div>
            </div>

            <!-- Navegación del calendario -->
            <div class="calendar-header">
                <button class="btn btn-outline-primary" onclick="cambiarMes(-1)">
                    <i class="bi bi-chevron-left"></i> Anterior
                </button>
                <h3 class="mb-0">
                    <?= $nombres_meses[$mes] ?> <?= $anio ?>
                </h3>
                <button class="btn btn-outline-primary" onclick="cambiarMes(1)" 
                        <?= ($mes == 12 && $anio == date('Y')) ? 'disabled' : '' ?>>
                    Siguiente <i class="bi bi-chevron-right"></i>
                </button>
            </div>

            <!-- Calendario -->
            <div class="calendar-grid">
                <!-- Días de la semana -->
                <div class="calendar-day-header">Lunes</div>
                <div class="calendar-day-header">Martes</div>
                <div class="calendar-day-header">Miércoles</div>
                <div class="calendar-day-header">Jueves</div>
                <div class="calendar-day-header">Viernes</div>
                <div class="calendar-day-header">Sábado</div>
                <div class="calendar-day-header">Domingo</div>

                <!-- Días del mes -->
                <?php
                // Días del mes anterior
                $dias_mes_anterior = date('t', strtotime("$anio_anterior-$mes_anterior-01"));
                for ($i = $primer_dia - 2; $i >= 0; $i--) {
                    $dia = $dias_mes_anterior - $i;
                    echo "<div class='calendar-day other-month'>";
                    echo "<div class='calendar-day-number'>$dia</div>";
                    echo "</div>";
                }

                // Días del mes actual
                $hoy = date('j');
                $mes_actual = date('n');
                $anio_actual = date('Y');

                for ($dia = 1; $dia <= $dias_mes; $dia++) {
                    $clases = ['calendar-day'];
                    
                    if ($dia == $hoy && $mes == $mes_actual && $anio == $anio_actual) {
                        $clases[] = 'today';
                    }
                    
                    if (date('N', strtotime("$anio-$mes-$dia")) >= 6) {
                        $clases[] = 'weekend';
                    }
                    
                    if (isset($feriados_por_dia[$dia])) {
                        $clases[] = 'feriado';
                    }

                    echo "<div class='" . implode(' ', $clases) . "'>";
                    echo "<div class='calendar-day-number'>$dia</div>";
                    
                    // Mostrar feriado
                    if (isset($feriados_por_dia[$dia])) {
                        $feriado = $feriados_por_dia[$dia];
                        echo "<div class='badge bg-warning mb-1' title='" . htmlspecialchars($feriado['nombre']) . "'>";
                        echo "<i class='bi bi-calendar-x'></i> " . htmlspecialchars($feriado['nombre']);
                        echo "</div>";
                    }
                    
                    // Mostrar reservas
                    if (isset($reservas_por_dia[$dia])) {
                        $reservas_dia = $reservas_por_dia[$dia];
                        $max_mostrar = 3;
                        $cont = 0;
                        
                        foreach ($reservas_dia as $reserva) {
                            if ($cont >= $max_mostrar) break;
                            
                            $estado_class = $reserva['estado'];
                            $titulo = "Salón: {$reserva['salon_numero']} - {$reserva['salon_nombre']}\n";
                            $titulo .= "Horario: {$reserva['hora_inicio']} a {$reserva['hora_fin']}\n";
                            $titulo .= "Usuario: {$reserva['usuario_nombre']}\n";
                            $titulo .= "Motivo: {$reserva['motivo']}\n";
                            $titulo .= "Estado: {$reserva['estado']}";
                            
                            echo "<div class='reserva-item $estado_class' title='" . htmlspecialchars($titulo) . "'>";
                            echo "<i class='bi bi-door-open'></i> ";
                            echo htmlspecialchars($reserva['salon_numero']) . " ";
                            echo htmlspecialchars($reserva['hora_inicio']) . "-" . htmlspecialchars($reserva['hora_fin']);
                            echo "</div>";
                            
                            $cont++;
                        }
                        
                        if (count($reservas_dia) > $max_mostrar) {
                            echo "<div class='text-muted small'>+" . (count($reservas_dia) - $max_mostrar) . " más</div>";
                        }
                    }
                    
                    echo "</div>";
                }

                // Días del mes siguiente
                $total_celdas = $primer_dia - 1 + $dias_mes;
                $celdas_restantes = ceil($total_celdas / 7) * 7 - $total_celdas;
                
                for ($dia = 1; $dia <= $celdas_restantes; $dia++) {
                    echo "<div class='calendar-day other-month'>";
                    echo "<div class='calendar-day-number'>$dia</div>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cambiarMes(direccion) {
            let mes = <?= $mes ?> + direccion;
            let anio = <?= $anio ?>;
            const anioActual = new Date().getFullYear();
            
            if (mes > 12) {
                mes = 1;
                anio++;
            } else if (mes < 1) {
                mes = 12;
                anio--;
            }
            
            // No permitir navegar más allá del año actual
            if (anio > anioActual) {
                return; // No hacer nada si intenta ir a un año futuro
            }
            
            const salonId = document.getElementById('salonFilter').value;
            window.location.href = `calendario.php?mes=${mes}&anio=${anio}&salon_id=${salonId}`;
        }

        document.getElementById('salonFilter').addEventListener('change', function() {
            const salonId = this.value;
            window.location.href = `calendario.php?mes=<?= $mes ?>&anio=<?= $anio ?>&salon_id=${salonId}`;
        });

        function exportarCalendario() {
            const salonId = document.getElementById('salonFilter').value;
            window.location.href = `api_exportar_calendario.php?mes=<?= $mes ?>&anio=<?= $anio ?>&salon_id=${salonId}`;
        }

        // Agregar tooltips a las reservas
        document.addEventListener('DOMContentLoaded', function() {
            const reservaItems = document.querySelectorAll('.reserva-item');
            reservaItems.forEach(item => {
                item.addEventListener('click', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'reserva-tooltip';
                    tooltip.textContent = this.getAttribute('title');
                    tooltip.style.display = 'block';
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = rect.left + 'px';
                    tooltip.style.top = (rect.bottom + 5) + 'px';
                    
                    document.body.appendChild(tooltip);
                    
                    setTimeout(() => {
                        tooltip.remove();
                    }, 3000);
                });
            });
        });
    </script>
</body>
</html>
