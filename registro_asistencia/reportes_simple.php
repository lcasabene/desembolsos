<?php
require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/database.php';

// Solo administradores pueden acceder
verificar_autenticacion();
if ($_SESSION['user_role'] !== 'Admin') {
    header('Location: ../acceso_denegado.php');
    exit;
}

$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$usuario_id = $_GET['usuario_id'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// Obtener lista de usuarios para el filtro
$stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios ORDER BY nombre");
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir consulta de reporte
$where_conditions = [];
$params = [];

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    // Rango de fechas específico
    $where_conditions[] = "a.fecha BETWEEN ? AND ?";
    $params[] = $fecha_inicio;
    $params[] = $fecha_fin;
} else {
    // Por mes y año (por defecto)
    $where_conditions[] = "MONTH(a.fecha) = ? AND YEAR(a.fecha) = ?";
    $params[] = $mes;
    $params[] = $anio;
}

if (!empty($usuario_id)) {
    $where_conditions[] = "a.usuario_id = ?";
    $params[] = $usuario_id;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Obtener datos del reporte
$stmt = $pdo->prepare("
    SELECT 
        u.id as usuario_id,
        u.nombre,
        u.email,
        COUNT(*) as total_registros,
        SUM(CASE 
            WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, a.hora_entrada, a.hora_salida) / 60 
            ELSE 0 
        END) as horas_trabajadas,
        SUM(CASE 
            WHEN a.estado = 'aprobado' AND a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, a.hora_entrada, a.hora_salida) / 60 
            ELSE 0 
        END) as horas_aprobadas,
        SUM(CASE 
            WHEN a.estado = 'pendiente_aprobacion' AND a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, a.hora_entrada, a.hora_salida) / 60 
            ELSE 0 
        END) as horas_pendientes,
        COUNT(CASE WHEN a.estado = 'aprobado' THEN 1 END) as registros_aprobados,
        COUNT(CASE WHEN a.estado = 'pendiente_aprobacion' THEN 1 END) as registros_pendientes,
        COUNT(CASE WHEN a.estado = 'rechazado' THEN 1 END) as registros_rechazados,
        MIN(a.fecha) as primera_fecha,
        MAX(a.fecha) as ultima_fecha
    FROM asistencia a
    JOIN usuarios u ON a.usuario_id = u.id
    $where_clause
    GROUP BY u.id, u.nombre, u.email
    ORDER BY u.nombre
");
$stmt->execute($params);
$reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar PDF si se solicita
if (isset($_GET['pdf']) && $_GET['pdf'] === '1') {
    // Configurar headers para PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="reporte_asistencia_' . date('Ymd') . '.pdf"');
    
    // Iniciar buffer de salida
    ob_start();
    
    // Generar HTML para el PDF
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Asistencia</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            font-size: 12px;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .title { 
            font-size: 20px; 
            font-weight: bold; 
            color: #333; 
            margin-bottom: 5px;
        }
        .subtitle { 
            font-size: 12px; 
            color: #666; 
            margin-bottom: 5px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
        }
        th { 
            background-color: #f8f9fa; 
            border: 1px solid #ddd; 
            padding: 8px; 
            text-align: left; 
            font-weight: bold; 
            font-size: 11px;
        }
        td { 
            border: 1px solid #ddd; 
            padding: 6px; 
            font-size: 11px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { 
            font-weight: bold; 
            background-color: #e9ecef; 
        }
        .footer { 
            margin-top: 30px; 
            font-size: 10px; 
            color: #666; 
            text-align: center; 
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .periodo {
            background-color: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>';

    // Determinar el período del reporte
    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        $periodo_texto = "Del " . date('d/m/Y', strtotime($fecha_inicio)) . " al " . date('d/m/Y', strtotime($fecha_fin));
    } else {
        $periodo_texto = "Mes: " . $mes . "/" . $anio;
    }
    
    $html .= '
    <div class="header">
        <div class="title">REPORTE DE ASISTENCIA</div>
        <div class="subtitle">Sistema de Gestión de Asistencia</div>
        <div class="periodo">' . $periodo_texto . '</div>
        <div class="subtitle">Generado el: ' . date('d/m/Y H:i:s') . '</div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Empleado</th>
                <th>Email</th>
                <th class="text-center">Registros</th>
                <th class="text-right">Horas Totales</th>
                <th class="text-right">Horas Aprobadas</th>
                <th class="text-right">Horas Pendientes</th>
                <th class="text-center">Aprobados</th>
                <th class="text-center">Pendientes</th>
                <th class="text-center">Rechazados</th>
            </tr>
        </thead>
        <tbody>';
    
    $total_horas_trabajadas = 0;
    $total_horas_aprobadas = 0;
    $total_horas_pendientes = 0;
    $total_registros = 0;
    
    foreach ($reporte_data as $row) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars($row['nombre']) . '</td>
                <td>' . htmlspecialchars($row['email']) . '</td>
                <td class="text-center">' . $row['total_registros'] . '</td>
                <td class="text-right">' . number_format($row['horas_trabajadas'], 2) . 'h</td>
                <td class="text-right">' . number_format($row['horas_aprobadas'], 2) . 'h</td>
                <td class="text-right">' . number_format($row['horas_pendientes'], 2) . 'h</td>
                <td class="text-center">' . $row['registros_aprobados'] . '</td>
                <td class="text-center">' . $row['registros_pendientes'] . '</td>
                <td class="text-center">' . $row['registros_rechazados'] . '</td>
            </tr>';
        
        $total_horas_trabajadas += $row['horas_trabajadas'];
        $total_horas_aprobadas += $row['horas_aprobadas'];
        $total_horas_pendientes += $row['horas_pendientes'];
        $total_registros += $row['total_registros'];
    }
    
    $html .= '
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2">TOTALES</td>
                <td class="text-center">' . $total_registros . '</td>
                <td class="text-right">' . number_format($total_horas_trabajadas, 2) . 'h</td>
                <td class="text-right">' . number_format($total_horas_aprobadas, 2) . 'h</td>
                <td class="text-right">' . number_format($total_horas_pendientes, 2) . 'h</td>
                <td class="text-center">' . array_sum(array_column($reporte_data, 'registros_aprobados')) . '</td>
                <td class="text-center">' . array_sum(array_column($reporte_data, 'registros_pendientes')) . '</td>
                <td class="text-center">' . array_sum(array_column($reporte_data, 'registros_rechazados')) . '</td>
            </tr>
        </tfoot>
    </table>
    
    <div class="footer">
        <p><strong>Resumen del Reporte:</strong></p>
        <p>Total Empleados: ' . count($reporte_data) . ' | Total Horas Trabajadas: ' . number_format($total_horas_trabajadas, 2) . 'h | Promedio por Empleado: ' . (count($reporte_data) > 0 ? number_format($total_horas_trabajadas / count($reporte_data), 2) : 0) . 'h</p>
        <p>Reporte generado por el Sistema de Gestión de Asistencia</p>
    </div>
</body>
</html>';
    
    echo $html;
    
    // Limpiar buffer y enviar al navegador
    ob_end_flush();
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reportes de Asistencia - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .main-container { max-width: 1400px; margin: 0 auto; }
        .page-header { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .filters-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .table-container { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 15px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: 700; color: #2c3e50; }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
        .filter-section { border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; margin-bottom: 1rem; }
        .filter-section:last-child { border-bottom: none; margin-bottom: 0; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">Reportes de Asistencia</h1>
                    <p class="text-muted mb-0">Análisis de horas trabajadas por colaborador</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-clock-history"></i> Mi Registro
                    </a>
                    <a href="../menu_moderno.php" class="btn btn-primary">
                        <i class="bi bi-house-door"></i> Menú Principal
                    </a>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <form method="GET" class="row g-3">
                <div class="col-12">
                    <h5 class="mb-3"><i class="bi bi-funnel"></i> Filtros del Reporte</h5>
                </div>
                
                <!-- Filtro por Rango de Fechas -->
                <div class="filter-section col-12">
                    <label class="form-label fw-bold">Opción 1: Rango de Fechas Específico</label>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control" value="<?= $fecha_inicio ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" name="fecha_fin" class="form-control" value="<?= $fecha_fin ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Empleado</label>
                            <select name="usuario_id" class="form-select">
                                <option value="">Todos los empleados</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>" <?= $usuario['id'] == $usuario_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($usuario['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Filtro por Mes/Año -->
                <div class="filter-section col-12">
                    <label class="form-label fw-bold">Opción 2: Por Mes y Año</label>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Mes</label>
                            <select name="mes" class="form-select">
                                <?php for($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Año</label>
                            <select name="anio" class="form-select">
                                <?php for($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                                    <option value="<?= $y ?>" <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Generar Reporte
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Estadísticas Generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= count($reporte_data) ?></div>
                <div class="stat-label">Empleados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format(array_sum(array_column($reporte_data, 'horas_trabajadas')), 1) ?>h</div>
                <div class="stat-label">Total Horas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format(array_sum(array_column($reporte_data, 'horas_aprobadas')), 1) ?>h</div>
                <div class="stat-label">Horas Aprobadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format(array_sum(array_column($reporte_data, 'horas_pendientes')), 1) ?>h</div>
                <div class="stat-label">Horas Pendientes</div>
            </div>
        </div>

        <!-- Tabla de Reporte -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">Reporte Detallado</h3>
                <a href="?<?= http_build_query(array_merge($_GET, ['pdf' => '1'])) ?>" class="btn btn-success">
                    <i class="bi bi-file-pdf"></i> Generar PDF
                </a>
            </div>
            
            <div class="table-responsive">
                <table id="tablaReporte" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Email</th>
                            <th class="text-center">Registros</th>
                            <th class="text-right">Horas Totales</th>
                            <th class="text-right">Horas Aprobadas</th>
                            <th class="text-right">Horas Pendientes</th>
                            <th class="text-center">Aprobados</th>
                            <th class="text-center">Pendientes</th>
                            <th class="text-center">Rechazados</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reporte_data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nombre']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td class="text-center"><?= $row['total_registros'] ?></td>
                            <td class="text-right"><strong><?= number_format($row['horas_trabajadas'], 2) ?>h</strong></td>
                            <td class="text-right"><?= number_format($row['horas_aprobadas'], 2) ?>h</td>
                            <td class="text-right"><?= number_format($row['horas_pendientes'], 2) ?>h</td>
                            <td class="text-center"><?= $row['registros_aprobados'] ?></td>
                            <td class="text-center"><?= $row['registros_pendientes'] ?></td>
                            <td class="text-center"><?= $row['registros_rechazados'] ?></td>
                            <td class="text-center">
                                <a href="detalle_empleado.php?usuario_id=<?= $row['usuario_id'] ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-eye"></i> Ver Detalle
                                </a>
                                <a href="admin_aprobaciones.php?usuario_id=<?= $row['usuario_id'] ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-check-square"></i> Aprobaciones
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tablaReporte').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                pageLength: 25,
                order: [[3, 'desc']],
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'excel'
                ]
            });
        });
    </script>
</body>
</html>
