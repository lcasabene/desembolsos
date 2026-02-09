<?php
require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/database.php';

// Verificar autenticación y acceso al módulo
verificar_autenticacion('Instalaciones');

// Establecer headers de seguridad
headers_seguridad();

// Obtener y sanitizar parámetros
$fecha_seleccionada = sanitizar_entrada($_GET['fecha'] ?? date('Y-m-d'));
$salon_id_filtro = sanitizar_entrada($_GET['salon_id'] ?? 0);

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_seleccionada)) {
    $fecha_seleccionada = date('Y-m-d');
}

// Validar que la fecha no sea pasada (permitir hoy y futuras)
$hoy = new DateTime();
$fecha_seleccionada_dt = new DateTime($fecha_seleccionada);
if ($fecha_seleccionada_dt < $hoy) {
    $fecha_seleccionada = date('Y-m-d');
}

// Obtener salones activos
$stmt = $pdo->query("SELECT * FROM salones WHERE estado = 'activo' ORDER BY numero");
$salones = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Disponibilidad Segura - Sistema de Instalaciones</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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

        .horario-cell:hover { filter: brightness(0.95); }
        
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

        .security-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar segura -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../menu.php">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                    <small class="ms-2"><i class="bi bi-shield-check"></i> Sesión Segura</small>
                </span>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Información de seguridad -->
        <div class="security-info">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="mb-1"><i class="bi bi-shield-check"></i> Conexión Segura</h6>
                    <small class="d-block">Tu sesión está protegida con medidas de seguridad avanzadas</small>
                </div>
                <div class="col-md-4 text-end">
                    <small class="d-block">Última actividad: <?= date('H:i:s') ?></small>
                    <a href="../logout_mejorado.php" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>

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

        <div class="table-responsive shadow-sm bg-white rounded">
            <div class="disponibilidad-grid">
                <div class="grid-header-hora">Hora</div>
                <?php foreach ($salones_mostrar as $salon): ?>
                    <div class="grid-header-salon">
                        <strong><?= $salon['numero'] ?></strong><br>
                        <small><?= $salon['nombre'] ?></small>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($horarios as $h): ?>
                    <div class="grid-cell-hora"><?= $h['display'] ?></div>
                    <?php foreach ($salones_mostrar as $salon): 
                        $info = getEstadoHorario($h['inicio'], $reservas_por_salon[$salon['id']] ?? []);
                        $clase = $info['ocupado'] ? 'ocupado-' . $info['estado'] : 'disponible';
                        $icono = $info['ocupado'] ? 'bi-x-lg' : 'bi-plus-circle';
                        $text_tooltip = $info['ocupado'] 
                            ? "RESERVADO\nUsuario: {$info['usuario']}\nMotivo: {$info['motivo']}" 
                            : "Disponible\nClick para reservar {$h['display']}";
                    ?>
                        <div class="horario-cell <?= $clase ?>" 
                             onclick="gestionarReserva('<?= $h['inicio'] ?>', <?= $salon['id'] ?>, <?= $info['ocupado'] ? 'true' : 'false' ?>)"
                             onmouseover="showTip(event, '<?= htmlspecialchars($text_tooltip) ?>')"
                             onmouseout="hideTip()">
                            <i class="bi <?= $icono ?> fs-4"></i>
                            <?php if ($info['ocupado']): ?>
                                <small class="d-block mt-1 fw-bold">Ocupado</small>
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
            window.location.href = `nueva_reserva_v2.php?salon_id=${salonId}&fecha=${fecha}&hora_inicio=${hora}`;
        }

        function cambiarDia(direccion) {
            const fechaActual = new Date(document.getElementById('fecha').value);
            fechaActual.setDate(fechaActual.getDate() + direccion);
            
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
            
            if (direccion > 0 && anioActual >= anioLimite && mesActual >= 11) {
                alert('No puedes navegar a fechas futuras más allá del año actual');
                return;
            }
            
            fechaActual.setMonth(fechaActual.getMonth() + direccion);
            
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

        // Verificar actividad periódica
        setInterval(() => {
            fetch('../config/verificar_sesion.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.valid) {
                        window.location.href = '../login_mejorado.php?timeout=1';
                    }
                })
                .catch(error => console.error('Error verificando sesión:', error));
        }, 60000); // Verificar cada minuto
    </script>
</body>
</html>
