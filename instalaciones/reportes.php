<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

// Verificar si el usuario tiene acceso al módulo
$modulos = $_SESSION['modulos'] ?? [];
$user_role = $_SESSION['user_role'] ?? '';
if (!in_array('Instalaciones', $modulos)) {
    header("Location: ../acceso_denegado.php");
    exit;
}

// Solo Admin y Portero pueden ver reportes
if (!in_array($user_role, ['Admin', 'Portero'])) {
    header("Location: ../acceso_denegado.php");
    exit;
}

// Obtener parámetros de filtrado
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$salon_id = $_GET['salon_id'] ?? '0';
$estado = $_GET['estado'] ?? 'todos';

// Obtener salones para filtros
$stmt = $pdo->query("SELECT * FROM salones ORDER BY numero");
$salones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir cláusula WHERE
$where_clauses = ["1=1"];
$params = [];

if ($salon_id !== '0') {
    $where_clauses[] = "r.salon_id = ?";
    $params[] = $salon_id;
}

if ($estado !== 'todos') {
    $where_clauses[] = "r.estado = ?";
    $params[] = $estado;
}

$where_clause = implode(" AND ", $where_clauses);

// Estadísticas generales
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reservas,
        COUNT(CASE WHEN r.estado = 'aprobada' THEN 1 END) as aprobadas,
        COUNT(CASE WHEN r.estado = 'pendiente' THEN 1 END) as pendientes,
        COUNT(CASE WHEN r.estado = 'rechazada' THEN 1 END) as rechazadas,
        COUNT(CASE WHEN r.estado = 'cancelada' THEN 1 END) as canceladas,
        COUNT(CASE WHEN r.fecha = CURDATE() THEN 1 END) as hoy
    FROM reservas r
    WHERE r.fecha BETWEEN ? AND ? AND $where_clause
");
$stmt->execute([$fecha_inicio, $fecha_fin, ...$params]);
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Uso por salón
$stmt = $pdo->prepare("
    SELECT 
        s.numero,
        s.nombre,
        COUNT(*) as total_reservas,
        COUNT(CASE WHEN r.estado = 'aprobada' THEN 1 END) as aprobadas,
        COUNT(CASE WHEN r.estado = 'aprobada' AND r.fecha >= CURDATE() THEN 1 END) as futuras
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    WHERE r.fecha BETWEEN ? AND ? AND $where_clause
    GROUP BY s.id, s.numero, s.nombre
    ORDER BY total_reservas DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin, ...$params]);
$uso_por_salon = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Uso por mes
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(r.fecha, '%Y-%m') as mes,
        DATE_FORMAT(r.fecha, '%M %Y') as mes_nombre,
        COUNT(*) as total_reservas,
        COUNT(CASE WHEN r.estado = 'aprobada' THEN 1 END) as aprobadas
    FROM reservas r
    WHERE r.fecha BETWEEN ? AND ? AND $where_clause
    GROUP BY DATE_FORMAT(r.fecha, '%Y-%m'), DATE_FORMAT(r.fecha, '%M %Y')
    ORDER BY mes DESC
    LIMIT 12
");
$stmt->execute([$fecha_inicio, $fecha_fin, ...$params]);
$uso_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Usuarios más activos
$stmt = $pdo->prepare("
    SELECT 
        u.nombre,
        u.email,
        COUNT(*) as total_reservas,
        COUNT(CASE WHEN r.estado = 'aprobada' THEN 1 END) as aprobadas
    FROM reservas r
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.fecha BETWEEN ? AND ? AND $where_clause
    GROUP BY u.id, u.nombre, u.email
    ORDER BY total_reservas DESC
    LIMIT 10
");
$stmt->execute([$fecha_inicio, $fecha_fin, ...$params]);
$usuarios_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Horarios más solicitados
$stmt = $pdo->prepare("
    SELECT 
        r.hora_inicio,
        r.hora_fin,
        COUNT(*) as total_solicitudes,
        COUNT(CASE WHEN r.estado = 'aprobada' THEN 1 END) as aprobadas
    FROM reservas r
    WHERE r.fecha BETWEEN ? AND ? AND $where_clause
    GROUP BY r.hora_inicio, r.hora_fin
    ORDER BY total_solicitudes DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin, ...$params]);
$horarios_populares = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Detalles de reservas para tabla
$stmt = $pdo->prepare("
    SELECT 
        r.fecha,
        r.estado,
        r.hora_inicio,
        r.hora_fin,
        s.numero as salon_numero,
        s.nombre as salon_nombre,
        u.nombre as usuario_nombre,
        r.motivo
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.fecha BETWEEN ? AND ? AND $where_clause
    ORDER BY r.fecha DESC, r.hora_inicio DESC
    LIMIT 100
");
$stmt->execute([$fecha_inicio, $fecha_fin, ...$params]);
$reservas_detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-graph-up"></i> Reportes y Estadísticas</h2>
            <button class="btn btn-success" onclick="exportarReporte()">
                <i class="bi bi-download"></i> Exportar Excel
            </button>
        </div>

        <!-- Filtros -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                               value="<?= htmlspecialchars($fecha_inicio) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                               value="<?= htmlspecialchars($fecha_fin) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="salon_id" class="form-label">Salón</label>
                        <select class="form-select" id="salon_id" name="salon_id">
                            <option value="0">Todos los salones</option>
                            <?php foreach ($salones as $salon): ?>
                                <option value="<?= $salon['id'] ?>" <?= $salon_id == $salon['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($salon['numero']) ?> - <?= htmlspecialchars($salon['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="todos" <?= $estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="aprobada" <?= $estado === 'aprobada' ? 'selected' : '' ?>>Aprobadas</option>
                            <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="rechazada" <?= $estado === 'rechazada' ? 'selected' : '' ?>>Rechazadas</option>
                            <option value="cancelada" <?= $estado === 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Aplicar Filtros
                        </button>
                        <a href="reportes.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Estadísticas Generales -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-center border-primary">
                    <div class="card-body">
                        <h4 class="text-primary"><?= $estadisticas['total_reservas'] ?></h4>
                        <p class="card-text small mb-0">Total Reservas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <h4 class="text-success"><?= $estadisticas['aprobadas'] ?></h4>
                        <p class="card-text small mb-0">Aprobadas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <h4 class="text-warning"><?= $estadisticas['pendientes'] ?></h4>
                        <p class="card-text small mb-0">Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <h4 class="text-danger"><?= $estadisticas['rechazadas'] ?></h4>
                        <p class="card-text small mb-0">Rechazadas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-secondary">
                    <div class="card-body">
                        <h4 class="text-secondary"><?= $estadisticas['canceladas'] ?></h4>
                        <p class="card-text small mb-0">Canceladas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <h4 class="text-info"><?= $estadisticas['hoy'] ?></h4>
                        <p class="card-text small mb-0">Para Hoy</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">Uso por Salón</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="graficoSalones" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">Reservas por Mes</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="graficoMeses" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tablas de estadísticas -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">Usuarios Más Activos</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Aprobadas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios_activos as $usuario): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($usuario['nombre']) ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($usuario['email']) ?></small>
                                            </td>
                                            <td class="text-center"><?= $usuario['total_reservas'] ?></td>
                                            <td class="text-center"><?= $usuario['aprobadas'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">Horarios Más Populares</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Horario</th>
                                        <th class="text-center">Solicitudes</th>
                                        <th class="text-center">Aprobadas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($horarios_populares as $horario): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($horario['hora_inicio']) ?> - <?= htmlspecialchars($horario['hora_fin']) ?>
                                            </td>
                                            <td class="text-center"><?= $horario['total_solicitudes'] ?></td>
                                            <td class="text-center"><?= $horario['aprobadas'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle de Reservas -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Detalle de Reservas</h5>
            </div>
            <div class="card-body">
                <table id="tablaReservas" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Salón</th>
                            <th>Horario</th>
                            <th>Usuario</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservas_detalle as $reserva): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($reserva['fecha'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($reserva['salon_numero']) ?> - 
                                    <?= htmlspecialchars($reserva['salon_nombre']) ?>
                                </td>
                                <td><?= htmlspecialchars($reserva['hora_inicio']) ?> - <?= htmlspecialchars($reserva['hora_fin']) ?></td>
                                <td><?= htmlspecialchars($reserva['usuario_nombre']) ?></td>
                                <td><?= htmlspecialchars($reserva['motivo']) ?></td>
                                <td>
                                    <?php
                                    $estado_class = match($reserva['estado']) {
                                        'aprobada' => 'success',
                                        'pendiente' => 'warning',
                                        'rechazada' => 'danger',
                                        'cancelada' => 'secondary',
                                        default => 'light'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $estado_class ?>">
                                        <?= htmlspecialchars($reserva['estado']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tablaReservas').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                responsive: true,
                order: [[0, 'desc']]
            });

            // Gráfico de uso por salón
            const ctxSalones = document.getElementById('graficoSalones').getContext('2d');
            new Chart(ctxSalones, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($uso_por_salon, 'numero')) ?>,
                    datasets: [{
                        label: 'Total Reservas',
                        data: <?= json_encode(array_column($uso_por_salon, 'total_reservas')) ?>,
                        backgroundColor: 'rgba(13, 110, 253, 0.6)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Gráfico de reservas por mes
            const ctxMeses = document.getElementById('graficoMeses').getContext('2d');
            new Chart(ctxMeses, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($uso_por_mes, 'mes_nombre')) ?>,
                    datasets: [{
                        label: 'Total Reservas',
                        data: <?= json_encode(array_column($uso_por_mes, 'total_reservas')) ?>,
                        backgroundColor: 'rgba(25, 135, 84, 0.2)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 2,
                        tension: 0.4
                    }, {
                        label: 'Aprobadas',
                        data: <?= json_encode(array_column($uso_por_mes, 'aprobadas')) ?>,
                        backgroundColor: 'rgba(13, 202, 240, 0.2)',
                        borderColor: 'rgba(13, 202, 240, 1)',
                        borderWidth: 2,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });

        function exportarReporte() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.location.href = `api_exportar_reporte.php?${params.toString()}`;
        }
    </script>
</body>
</html>
