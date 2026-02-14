<?php
require_once __DIR__ . '/../config/seguridad.php';
verificar_autenticacion('Instalaciones');

require_once '../config/database.php';

// Obtener parámetros
$fecha_seleccionada = $_GET['fecha'] ?? date('Y-m-d');
$salon_id_filtro = $_GET['salon_id'] ?? 0;

// Obtener salones activos
$stmt = $pdo->query("SELECT * FROM salones WHERE estado = 'activo' ORDER BY numero");
$salones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener horarios permitidos
try {
    $stmt_h = $pdo->query("SELECT * FROM configuracion_horarios WHERE estado = 'activo' ORDER BY hora_inicio");
    $horarios_permitidos = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt_h = $pdo->query("SELECT * FROM rangos_horarios WHERE estado = 'activo' ORDER BY hora_inicio");
        $horarios_permitidos = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $horarios_permitidos = [];
    }
}

// Verificar si la fecha seleccionada es feriado
$es_feriado = false;
$nombre_feriado = '';
try {
    $stmt_f = $pdo->prepare("SELECT nombre FROM feriados WHERE fecha = ? OR (recurrente = 'anual' AND MONTH(fecha) = MONTH(?) AND DAY(fecha) = DAY(?))");
    $stmt_f->execute([$fecha_seleccionada, $fecha_seleccionada, $fecha_seleccionada]);
    $feriado = $stmt_f->fetch(PDO::FETCH_ASSOC);
    if ($feriado) {
        $es_feriado = true;
        $nombre_feriado = $feriado['nombre'];
    }
} catch (PDOException $e) {}

// Día de la semana de la fecha seleccionada (1=Lunes, 7=Domingo)
$dia_semana_sel = date('N', strtotime($fecha_seleccionada));

// Filtrar salones para la vista
$salones_mostrar = ($salon_id_filtro > 0) 
    ? array_filter($salones, fn($s) => $s['id'] == $salon_id_filtro) 
    : $salones;

// Obtener reservas del día
$reservas_por_salon = [];
foreach ($salones_mostrar as $salon) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.nombre as usuario_nombre
        FROM reservas r
        JOIN usuarios u ON r.usuario_id = u.id
        WHERE r.salon_id = ? AND r.fecha = ? 
        AND r.estado IN ('aprobada', 'pendiente')
        ORDER BY r.hora_inicio
    ");
    $stmt->execute([$salon['id'], $fecha_seleccionada]);
    $reservas_por_salon[$salon['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Generar bloques de 30 min (06:30 a 00:00)
$horarios = [];
for ($hora = 6; $hora <= 23; $hora++) {
    for ($minuto = 0; $minuto < 60; $minuto += 30) {
        if ($hora == 6 && $minuto == 0) continue; 
        $inicio = sprintf('%02d:%02d', $hora, $minuto);
        $horarios[] = [
            'inicio' => $inicio,
            'display' => $inicio
        ];
    }
}
$horarios[] = ['inicio' => '00:00', 'display' => '00:00'];

function getEstadoHorario($hora, $reservas) {
    foreach ($reservas as $reserva) {
        // Convertir tiempos a minutos para comparación correcta
        $hora_actual = strtotime($hora);
        $reserva_inicio = strtotime($reserva['hora_inicio']);
        $reserva_fin = strtotime($reserva['hora_fin']);
        
        // Verificar si la hora actual está dentro del rango de reserva
        if ($hora_actual >= $reserva_inicio && $hora_actual < $reserva_fin) {
            return [
                'ocupado' => true,
                'estado' => $reserva['estado'],
                'usuario' => $reserva['usuario_nombre'],
                'motivo' => $reserva['motivo']
            ];
        }
    }
    return ['ocupado' => false];
}

function esHorarioHabilitado($hora, $dia_semana, $horarios_permitidos, $es_feriado) {
    if ($es_feriado) return false;
    if (empty($horarios_permitidos)) return true; // Si no hay config, todo habilitado
    
    $hora_ts = strtotime($hora);
    foreach ($horarios_permitidos as $rango) {
        $inicio_ts = strtotime($rango['hora_inicio']);
        $fin_ts = strtotime($rango['hora_fin']);
        $dias = json_decode($rango['dias_semana'], true) ?? [];
        
        if (in_array($dia_semana, $dias) && $hora_ts >= $inicio_ts && $hora_ts < $fin_ts) {
            return true;
        }
    }
    return false;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Disponibilidad - Sistema de Instalaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --bg-disponible: #d4edda;
            --color-disponible: #28a745;
            --bg-ocupado: #f8d7da;
            --color-ocupado: #dc3545;
            --bg-pendiente: #fff3cd;
        }

        .disponibilidad-grid {
            display: grid;
            /* Primera columna para hora, el resto para los salones dinámicamente */
            grid-template-columns: 100px repeat(<?= count($salones_mostrar) ?>, minmax(120px, 1fr));
            background-color: #dee2e6;
            gap: 1px;
            border: 1px solid #dee2e6;
        }

        .grid-header-hora, .grid-cell-hora {
            background-color: #495057;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            padding: 10px;
        }

        .grid-header-salon {
            background-color: #007bff;
            color: white;
            text-align: center;
            padding: 15px 5px;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .horario-cell {
            min-height: 55px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .disponible { background-color: var(--bg-disponible); color: var(--color-disponible); }
        .ocupado-aprobada { background-color: var(--bg-ocupado); color: var(--color-ocupado); }
        .ocupado-pendiente { background-color: var(--bg-pendiente); color: #856404; }
        .no-habilitado { background-color: #e9ecef; color: #adb5bd; cursor: not-allowed; }
        .no-habilitado i { opacity: 0.3; }
        .feriado-banner { background: #dc3545; color: white; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; font-weight: bold; }

        .horario-cell:hover { filter: brightness(0.95); }
        .no-habilitado:hover { filter: none; }
        
        .tooltip-box {
            position: absolute;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.75rem;
            z-index: 100;
            display: none;
            pointer-events: none;
            white-space: pre-line;
            min-width: 150px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
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

    <div class="container-fluid py-4">
        <div class="card shadow-sm mb-4">
            <div class="card-body bg-primary text-white">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Navegación</label>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-light" onclick="cambiarDia(-1)" title="Día anterior">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button type="button" class="btn btn-outline-light" onclick="cambiarDia(1)" title="Día siguiente">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" id="fecha" class="form-control" value="<?= $fecha_seleccionada ?>" onchange="cambiarVista()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Navegación Mes</label>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-light" onclick="cambiarMes(-1)" title="Mes anterior">
                                <i class="bi bi-chevron-double-left"></i>
                            </button>
                            <button type="button" class="btn btn-outline-light" onclick="cambiarMes(1)" title="Mes siguiente">
                                <i class="bi bi-chevron-double-right"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Salón</label>
                        <select id="salon_id" class="form-select" onchange="cambiarVista()">
                            <option value="0">Todos los salones</option>
                            <?php foreach ($salones as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($salon_id_filtro == $s['id']) ? 'selected' : '' ?>><?= $s['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Acciones Rápidas</label>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-light" onclick="irHoy()" title="Ir a hoy">
                                <i class="bi bi-calendar-day"></i> Hoy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($es_feriado): ?>
            <div class="feriado-banner">
                <i class="bi bi-calendar-x me-2"></i>FERIADO: <?= htmlspecialchars($nombre_feriado) ?> - No se permiten reservas este día
            </div>
        <?php endif; ?>

        <!-- Leyenda -->
        <div class="d-flex flex-wrap gap-3 mb-3 small">
            <span><span style="display:inline-block;width:16px;height:16px;background:var(--bg-disponible);border:1px solid #ccc;vertical-align:middle"></span> Disponible</span>
            <span><span style="display:inline-block;width:16px;height:16px;background:var(--bg-ocupado);border:1px solid #ccc;vertical-align:middle"></span> Reservado (aprobada)</span>
            <span><span style="display:inline-block;width:16px;height:16px;background:var(--bg-pendiente);border:1px solid #ccc;vertical-align:middle"></span> Reservado (pendiente)</span>
            <span><span style="display:inline-block;width:16px;height:16px;background:#e9ecef;border:1px solid #ccc;vertical-align:middle"></span> No habilitado</span>
            <?php if (!empty($horarios_permitidos)): ?>
                <?php 
                $dias_nombres = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
                foreach ($horarios_permitidos as $hp): 
                    $dias = json_decode($hp['dias_semana'], true) ?? [];
                    $dias_txt = array_map(fn($d) => $dias_nombres[$d] ?? '', $dias);
                ?>
                    <span class="text-muted"><i class="bi bi-clock"></i> <?= $hp['hora_inicio'] ?>-<?= $hp['hora_fin'] ?> (<?= implode(',', $dias_txt) ?>)</span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="table-responsive shadow-sm bg-white rounded">
            <div class="disponibilidad-grid">
                <div class="grid-header-hora">Hora</div>
                <?php foreach ($salones_mostrar as $salon): ?>
                    <div class="grid-header-salon" 
                         title="<?= htmlspecialchars($salon['nombre']) ?> - Cap: <?= $salon['capacidad'] ?> personas<?= $salon['descripcion'] ? ' - ' . $salon['descripcion'] : '' ?>">
                        <strong><?= $salon['numero'] ?></strong><br>
                        <small><?= $salon['nombre'] ?></small><br>
                        <small style="opacity:0.8">Cap: <?= $salon['capacidad'] ?></small>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($horarios as $h): ?>
                    <div class="grid-cell-hora"><?= $h['display'] ?></div>
                    <?php foreach ($salones_mostrar as $salon): 
                        $habilitado = esHorarioHabilitado($h['inicio'], $dia_semana_sel, $horarios_permitidos, $es_feriado);
                        $info = getEstadoHorario($h['inicio'], $reservas_por_salon[$salon['id']] ?? []);
                        
                        if (!$habilitado) {
                            $clase = 'no-habilitado';
                            $icono = 'bi-dash';
                            $text_tooltip = $es_feriado 
                                ? "FERIADO: $nombre_feriado\nNo se permiten reservas" 
                                : "Horario no habilitado\nFuera de franja permitida";
                            $clickable = false;
                        } elseif ($info['ocupado']) {
                            $clase = 'ocupado-' . $info['estado'];
                            $icono = 'bi-x-lg';
                            $text_tooltip = "RESERVADO\nUsuario: {$info['usuario']}\nMotivo: {$info['motivo']}\nSalón: {$salon['nombre']}";
                            if ($salon['descripcion']) $text_tooltip .= "\n" . $salon['descripcion'];
                            $clickable = false;
                        } else {
                            $clase = 'disponible';
                            $icono = 'bi-plus-circle';
                            $text_tooltip = "Disponible\nSalón: {$salon['nombre']}\nCapacidad: {$salon['capacidad']} personas";
                            if ($salon['descripcion']) $text_tooltip .= "\n" . $salon['descripcion'];
                            $text_tooltip .= "\nClick para reservar {$h['display']}";
                            $clickable = true;
                        }
                    ?>
                        <div class="horario-cell <?= $clase ?>" 
                             <?php if ($clickable): ?>onclick="gestionarReserva('<?= $h['inicio'] ?>', <?= $salon['id'] ?>, false)"<?php endif; ?>
                             onmouseover="showTip(event, '<?= htmlspecialchars($text_tooltip) ?>')"
                             onmouseout="hideTip()">
                            <i class="bi <?= $icono ?> fs-4"></i>
                            <?php if ($info['ocupado']): ?>
                                <small class="d-block mt-1 fw-bold">Ocupado</small>
                            <?php elseif (!$habilitado): ?>
                                <small class="d-block mt-1" style="font-size:0.65rem">No disp.</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="tooltip" class="tooltip-box"></div>

    <script>
        function cambiarVista() {
            const f = document.getElementById('fecha').value;
            const s = document.getElementById('salon_id').value;
            window.location.href = `?fecha=${f}&salon_id=${s}`;
        }

        function gestionarReserva(hora, salonId, ocupado) {
            if (ocupado) return;
            const fecha = document.getElementById('fecha').value;
            window.location.href = `nueva_reserva.php?salon_id=${salonId}&fecha=${fecha}&hora_inicio=${hora}`;
        }

        function cambiarDia(direccion) {
            const fechaActual = new Date(document.getElementById('fecha').value);
            fechaActual.setDate(fechaActual.getDate() + direccion);
            
            // Validar que no sea una fecha pasada
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            
            if (fechaActual < hoy) {
                alert('No puedes navegar a fechas pasadas');
                return;
            }
            
            const nuevaFecha = fechaActual.toISOString().split('T')[0];
            const salonId = document.getElementById('salon_id').value;
            window.location.href = `?fecha=${nuevaFecha}&salon_id=${salonId}`;
        }

        function cambiarMes(direccion) {
            const fechaActual = new Date(document.getElementById('fecha').value);
            const mesActual = fechaActual.getMonth();
            const anioActual = fechaActual.getFullYear();
            const anioLimite = new Date().getFullYear();
            
            // Validar que no exceda el año actual
            if (direccion > 0 && anioActual >= anioLimite && mesActual >= 11) {
                alert('No puedes navegar a fechas futuras más allá del año actual');
                return;
            }
            
            fechaActual.setMonth(fechaActual.getMonth() + direccion);
            
            // Validar que no sea una fecha pasada
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            
            if (fechaActual < hoy) {
                alert('No puedes navegar a fechas pasadas');
                return;
            }
            
            const nuevaFecha = fechaActual.toISOString().split('T')[0];
            const salonId = document.getElementById('salon_id').value;
            window.location.href = `?fecha=${nuevaFecha}&salon_id=${salonId}`;
        }

        function irHoy() {
            const hoy = new Date().toISOString().split('T')[0];
            const salonId = document.getElementById('salon_id').value;
            window.location.href = `?fecha=${hoy}&salon_id=${salonId}`;
        }

        // Navegación con teclado
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
            
            switch(e.key) {
                case 'ArrowLeft':
                    cambiarDia(-1);
                    break;
                case 'ArrowRight':
                    cambiarDia(1);
                    break;
                case 'PageUp':
                    cambiarMes(-1);
                    break;
                case 'PageDown':
                    cambiarMes(1);
                    break;
                case 'Home':
                    irHoy();
                    break;
            }
        });

        const tip = document.getElementById('tooltip');
        function showTip(e, msg) {
            tip.innerText = msg;
            tip.style.display = 'block';
            tip.style.left = (e.pageX + 15) + 'px';
            tip.style.top = (e.pageY + 15) + 'px';
        }
        function hideTip() { tip.style.display = 'none'; }
    </script>
</body>
</html>