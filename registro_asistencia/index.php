<?php
require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/database.php';
verificar_autenticacion('Colaboradores');

$usuario_id = $_SESSION['user_id'];
$nombre = $_SESSION['user_name'] ?? 'Usuario';
$hoy = date('Y-m-d');

// Obtener el último registro del usuario para hoy
$stmt = $pdo->prepare("
    SELECT * FROM asistencia 
    WHERE usuario_id = ? AND fecha = ? 
    ORDER BY secuencia DESC, created_at DESC 
    LIMIT 1
");
$stmt->execute([$usuario_id, $hoy]);
$ultimo_registro = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener todos los registros del día
$stmt = $pdo->prepare("
    SELECT id, secuencia, hora_entrada, hora_salida, estado, observaciones,
           CASE 
               WHEN hora_entrada IS NOT NULL AND hora_salida IS NOT NULL 
               THEN TIMESTAMPDIFF(MINUTE, hora_entrada, hora_salida) / 60 
               ELSE NULL 
           END as horas_dia
    FROM asistencia 
    WHERE usuario_id = ? AND fecha = ?
    ORDER BY secuencia ASC
");
$stmt->execute([$usuario_id, $hoy]);
$registros_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener resumen mensual
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE 
            WHEN hora_entrada IS NOT NULL AND hora_salida IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, hora_entrada, hora_salida) / 60 
            ELSE 0 
        END) as horas_trabajadas,
        SUM(CASE 
            WHEN estado = 'aprobado' AND hora_entrada IS NOT NULL AND hora_salida IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, hora_entrada, hora_salida) / 60 
            ELSE 0 
        END) as horas_aprobadas,
        SUM(CASE 
            WHEN estado = 'pendiente_aprobacion' AND hora_entrada IS NOT NULL AND hora_salida IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, hora_entrada, hora_salida) / 60 
            ELSE 0 
        END) as horas_pendientes,
        COUNT(*) as total_dias
    FROM asistencia 
    WHERE usuario_id = ? AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
");
$stmt->execute([$usuario_id]);
$resumen_mensual = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener registros del mes para la tabla
$stmt = $pdo->prepare("
    SELECT id, fecha, secuencia, hora_entrada, hora_salida, estado, observaciones,
           CASE 
               WHEN hora_entrada IS NOT NULL AND hora_salida IS NOT NULL 
               THEN TIMESTAMPDIFF(MINUTE, hora_entrada, hora_salida) / 60 
               ELSE NULL 
           END as horas_dia
    FROM asistencia 
    WHERE usuario_id = ? AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
    ORDER BY fecha DESC, secuencia ASC
");
$stmt->execute([$usuario_id]);
$registros_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro de Asistencia - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --card-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            text-align: center;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
        }

        .action-button {
            width: 100%;
            padding: 1rem 2rem;
            border: none;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .btn-entrada {
            background: var(--success-gradient);
        }

        .btn-salida {
            background: var(--warning-gradient);
        }

        .btn-entrada:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(79, 172, 254, 0.4);
        }

        .btn-salida:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(250, 112, 154, 0.4);
        }

        .action-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
        }

        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .summary-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-aprobado {
            background: #d4edda;
            color: #155724;
        }

        .status-pendiente {
            background: #fff3cd;
            color: #856404;
        }

        .status-rechazado {
            background: #f8d7da;
            color: #721c24;
        }

        .current-time {
            font-size: 3rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .current-date {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="mb-0">Registro de Asistencia</h1>
                    <p class="text-muted mb-0">Bienvenido, <?= htmlspecialchars($nombre) ?></p>
                </div>
                <div>
                    <?php if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != $_SERVER['REQUEST_URI']): ?>
                    <a href="<?= htmlspecialchars($_SERVER['HTTP_REFERER']) ?>" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                    <?php endif; ?>
                    <a href="../menu_moderno.php" class="btn btn-primary">
                        <i class="bi bi-house-door"></i> Menú Principal
                    </a>
                </div>
            </div>
            <div class="current-time" id="currentTime"><?= date('H:i:s') ?></div>
            <div class="current-date"><?= date('l, d \d\e F \d\e Y') ?></div>
        </div>

        <!-- Action Cards -->
        <div class="action-cards">
            <div class="action-card">
                <i class="bi bi-box-arrow-in-right" style="font-size: 3rem; color: #4facfe; margin-bottom: 1rem;"></i>
                <h3>Registrar Entrada</h3>
                <p class="text-muted mb-3">
                    <?php 
                    $tiene_entrada_pendiente = $ultimo_registro && $ultimo_registro['hora_entrada'] && !$ultimo_registro['hora_salida'];
                    if ($tiene_entrada_pendiente): 
                    ?>
                        Tienes una entrada registrada a las <?= date('H:i', strtotime($ultimo_registro['hora_entrada'])) ?> sin salida
                    <?php else: ?>
                        Registra el inicio de tu jornada o de un nuevo período de trabajo
                    <?php endif; ?>
                </p>
                <button class="action-button btn-entrada" 
                        onclick="registrarEntrada()" 
                        <?= $tiene_entrada_pendiente ? 'disabled' : '' ?>>
                    <i class="bi bi-clock-fill"></i> Registrar Entrada
                </button>
                <?php if (!$tiene_entrada_pendiente && !empty($registros_hoy)): ?>
                    <small class="text-muted d-block mt-2">
                        Será tu entrada #<?= (count($registros_hoy) + 1) ?> del día
                    </small>
                <?php endif; ?>
            </div>

            <div class="action-card">
                <i class="bi bi-box-arrow-right" style="font-size: 3rem; color: #fa709a; margin-bottom: 1rem;"></i>
                <h3>Registrar Salida</h3>
                <p class="text-muted mb-3">
                    <?php 
                    if (!$tiene_entrada_pendiente): 
                        if (empty($registros_hoy)): ?>
                            No tienes entradas registradas hoy
                        <?php else: ?>
                            No tienes entradas pendientes de salida
                        <?php endif;
                    else: ?>
                        Registra el fin de tu período actual de trabajo
                    <?php endif; ?>
                </p>
                <button class="action-button btn-salida" 
                        onclick="registrarSalida()"
                        <?= !$tiene_entrada_pendiente ? 'disabled' : '' ?>>
                    <i class="bi bi-clock-history"></i> Registrar Salida
                </button>
                <?php if ($tiene_entrada_pendiente): ?>
                    <small class="text-muted d-block mt-2">
                        Entrada: <?= date('H:i', strtotime($ultimo_registro['hora_entrada'])) ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Resumen del Día -->
        <?php if (!empty($registros_hoy)): ?>
        <div class="table-container mb-4">
            <h4 class="mb-3">Resumen de Hoy</h4>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Horas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_horas_hoy = 0;
                        foreach ($registros_hoy as $registro): 
                            $total_horas_hoy += $registro['horas_dia'] ?: 0;
                        ?>
                        <tr>
                            <td><?= $registro['secuencia'] ?></td>
                            <td><?= $registro['hora_entrada'] ? date('H:i', strtotime($registro['hora_entrada'])) : '-' ?></td>
                            <td><?= $registro['hora_salida'] ? date('H:i', strtotime($registro['hora_salida'])) : '-' ?></td>
                            <td><?= $registro['horas_dia'] ? number_format($registro['horas_dia'], 2) . 'h' : '-' ?></td>
                            <td>
                                <span class="status-badge status-<?= $registro['estado'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $registro['estado'])) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th colspan="3">Total del Día</th>
                            <th><?= number_format($total_horas_hoy, 2) ?>h</th>
                            <th><?= count($registros_hoy) ?> registros</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-value"><?= number_format($resumen_mensual['horas_trabajadas'], 1) ?>h</div>
                <div class="summary-label">Total Horas Mes</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?= number_format($resumen_mensual['horas_aprobadas'], 1) ?>h</div>
                <div class="summary-label">Horas Aprobadas</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?= number_format($resumen_mensual['horas_pendientes'], 1) ?>h</div>
                <div class="summary-label">Horas Pendientes</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?= $resumen_mensual['total_dias'] ?></div>
                <div class="summary-label">Días Registrados</div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <h3 class="mb-4">Registros del Mes</h3>
            <div class="table-responsive">
                <table id="tablaAsistencia" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>#</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Horas</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros_mes as $registro): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($registro['fecha'])) ?></td>
                            <td><span class="badge bg-secondary"><?= $registro['secuencia'] ?></span></td>
                            <td><?= $registro['hora_entrada'] ? date('H:i', strtotime($registro['hora_entrada'])) : '-' ?></td>
                            <td><?= $registro['hora_salida'] ? date('H:i', strtotime($registro['hora_salida'])) : '-' ?></td>
                            <td><?= $registro['horas_dia'] ? number_format($registro['horas_dia'], 1) . 'h' : '-' ?></td>
                            <td>
                                <span class="status-badge status-<?= $registro['estado'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $registro['estado'])) ?>
                                </span>
                            </td>
                            <td><?= $registro['observaciones'] ?: '-' ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editarRegistro(<?= $registro['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Token CSRF para las peticiones AJAX
        const csrfToken = '<?= generar_token_csrf() ?>';
        
        // Actualizar tiempo cada segundo
        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = 
                now.toLocaleTimeString('es-AR', { hour12: false });
        }
        setInterval(updateTime, 1000);

        // Inicializar DataTable
        $(document).ready(function() {
            $('#tablaAsistencia').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });

        // Registrar entrada
        function registrarEntrada() {
            if (confirm('¿Confirmar registro de entrada?')) {
                $.ajax({
                    url: 'api_registrar.php',
                    method: 'POST',
                    data: {
                        accion: 'entrada',
                        csrf_token: csrfToken
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Entrada registrada correctamente');
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', xhr.responseText);
                        alert('Error de conexión: ' + error);
                    }
                });
            }
        }

        // Registrar salida
        function registrarSalida() {
            if (confirm('¿Confirmar registro de salida?')) {
                $.ajax({
                    url: 'api_registrar.php',
                    method: 'POST',
                    data: {
                        accion: 'salida',
                        csrf_token: csrfToken
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Salida registrada correctamente');
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', xhr.responseText);
                        alert('Error de conexión: ' + error);
                    }
                });
            }
        }

        // Editar registro
        function editarRegistro(id) {
            window.location.href = 'editar_registro.php?id=' + id;
        }
    </script>
</body>
</html>
