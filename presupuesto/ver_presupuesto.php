<?php
require_once __DIR__ . '/../config/seguridad.php';
verificar_autenticacion();

require_once __DIR__ . '/../config/database.php';

// Verificar si el usuario tiene acceso al módulo de presupuesto
$modulos = $_SESSION['modulos'] ?? [];
if (!in_array('Presupuesto', $modulos) && $_SESSION['user_role'] !== 'Admin') {
    header('Location: acceso_denegado.php');
    exit;
}

$presupuesto_id = $_GET['id'] ?? 0;
$nombre = $_SESSION['user_name'] ?? 'Usuario';
$rol = $_SESSION['user_role'] ?? 'User';

try {
    // Obtener información del presupuesto
    $stmt = $pdo->prepare("SELECT * FROM vista_resumen_presupuesto_anual WHERE id = ?");
    $stmt->execute([$presupuesto_id]);
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$presupuesto) {
        header('Location: index.php?error=not_found');
        exit;
    }
    
    // Obtener detalles mensuales
    $stmt = $pdo->prepare("
        SELECT pmd.*, 
               COALESCE(SUM(ep.monto_ejecutado), 0) as monto_ejecutado,
               (pmd.presupuesto_estimado - COALESCE(SUM(ep.monto_ejecutado), 0)) as saldo_disponible,
               COALESCE(d.nombre, 'Sin Departamento') as departamento_nombre
        FROM presupuesto_mensual_detalle pmd
        LEFT JOIN ejecucion_presupuestaria ep ON pmd.id = ep.presupuesto_detalle_id
        LEFT JOIN departamentos d ON pmd.departamento_id = d.id
        WHERE pmd.presupuesto_anual_id = ?
        GROUP BY pmd.id
        ORDER BY FIELD(pmd.mes, 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'), pmd.fecha_inicio
    ");
    $stmt->execute([$presupuesto_id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por mes
    $detalles_por_mes = [];
    foreach ($detalles as $detalle) {
        $detalles_por_mes[$detalle['mes']][] = $detalle;
    }
    
} catch (PDOException $e) {
    $error = "Error al cargar el presupuesto: " . $e->getMessage();
    $presupuesto = null;
    $detalles_por_mes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ver Presupuesto <?= htmlspecialchars($presupuesto['anio'] ?? '') ?> - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        }

        .main-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .form-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }

        .estado-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .estado-borrador { background: #f8f9fa; color: #6c757d; }
        .estado-enviado { background: #fff3cd; color: #856404; }
        .estado-aprobado { background: #d4edda; color: #155724; }

        .meses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .mes-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
        }

        .mes-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .mes-total {
            background: var(--primary-gradient);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .actividad-item {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
        }

        .progress-bar-container {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: var(--success-gradient);
            transition: width 0.3s ease;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            text-align: center;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .objetivos-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 1rem;
            height: 300px;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            margin: 0.25rem;
        }

        .ejecucion-indicator {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .ejecucion-baja { background: #d4edda; color: #155724; }
        .ejecucion-media { background: #fff3cd; color: #856404; }
        .ejecucion-alta { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="main-container">
        <?php if (!$presupuesto): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error ?? 'Presupuesto no encontrado') ?>
            </div>
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Volver al Listado
            </a>
        <?php else: ?>
            <!-- Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="header-title">Presupuesto <?= htmlspecialchars($presupuesto['anio']) ?></h1>
                        <p class="text-muted mb-2">
                            Creado por <?= htmlspecialchars($presupuesto['creado_por_nombre']) ?> 
                            el <?= date('d/m/Y', strtotime($presupuesto['fecha_creacion'])) ?>
                        </p>
                        <span class="estado-badge estado-<?= $presupuesto['estado'] ?>">
                            <?= str_replace('_', ' ', $presupuesto['estado']) ?>
                        </span>
                        <?php if ($presupuesto['fecha_aprobacion']): ?>
                            <span class="badge bg-success ms-2">
                                Aprobado: <?= date('d/m/Y', strtotime($presupuesto['fecha_aprobacion'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                        <?php if ($presupuesto['estado'] === 'borrador' || ($rol === 'Admin' && $presupuesto['estado'] !== 'aprobado')): ?>
                            <a href="editar_presupuesto.php?id=<?= $presupuesto['id'] ?>" class="btn btn-warning">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                        <?php endif; ?>
                        <a href="exportar_presupuesto.php?id=<?= $presupuesto['id'] ?>" class="btn btn-success">
                            <i class="bi bi-download"></i> Exportar Excel
                        </a>
                        <?php if ($rol === 'Admin' && $presupuesto['estado'] === 'enviado_aprobacion'): ?>
                            <button class="btn btn-success" onclick="aprobarPresupuesto(<?= $presupuesto['id'] ?>)">
                                <i class="bi bi-check-circle"></i> Aprobar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--primary-gradient);">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <div class="stat-value">$<?= number_format($presupuesto['total_presupuestado'], 2, ',', '.') ?></div>
                    <div class="stat-label">Total Presupuestado</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--success-gradient);">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-value">$<?= number_format($presupuesto['total_ejecutado_anual'], 2, ',', '.') ?></div>
                    <div class="stat-label">Total Ejecutado</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--warning-gradient);">
                        <i class="bi bi-piggy-bank"></i>
                    </div>
                    <div class="stat-value">$<?= number_format($presupuesto['saldo_anual_disponible'], 2, ',', '.') ?></div>
                    <div class="stat-label">Saldo Disponible</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="bi bi-list-task"></i>
                    </div>
                    <div class="stat-value"><?= $presupuesto['total_actividades_anual'] ?></div>
                    <div class="stat-label">Actividades Planificadas</div>
                </div>
            </div>

            <!-- Objetivos Estratégicos -->
            <div class="objetivos-section">
                <h2 class="mb-3">
                    <i class="bi bi-bullseye"></i> Objetivos Estratégicos del Año
                </h2>
                <div class="bg-white bg-opacity-10 rounded p-3">
                    <?= nl2br(htmlspecialchars($presupuesto['objetivos_estrategicos'] ?? 'No definidos')) ?>
                </div>
            </div>

            <!-- Presupuesto Mensual Detallado -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-calendar-month"></i> Presupuesto Mensual Detallado
                </h2>
                
                <div class="meses-grid">
                    <?php
                    $meses = [
                        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
                    ];
                    
                    foreach ($meses as $mes):
                        $actividades_mes = $detalles_por_mes[$mes] ?? [];
                        $total_mes = array_sum(array_column($actividades_mes, 'presupuesto_estimado'));
                        $ejecutado_mes = array_sum(array_column($actividades_mes, 'monto_ejecutado'));
                        $porcentaje_ejecucion = $total_mes > 0 ? ($ejecutado_mes / $total_mes) * 100 : 0;
                    ?>
                        <div class="mes-card">
                            <div class="mes-title">
                                <span><?= ucfirst($mes) ?></span>
                                <div>
                                    <span class="mes-total">$<?= number_format($total_mes, 2, ',', '.') ?></span>
                                    <?php if ($ejecutado_mes > 0): ?>
                                        <span class="ejecucion-indicator <?= $porcentaje_ejecucion < 50 ? 'ejecucion-baja' : ($porcentaje_ejecucion < 80 ? 'ejecucion-media' : 'ejecucion-alta') ?>">
                                            <?= number_format($porcentaje_ejecucion, 1) ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (empty($actividades_mes)): ?>
                                <p class="text-muted text-center py-3">No hay actividades planificadas para este mes</p>
                            <?php else: ?>
                                <?php foreach ($actividades_mes as $actividad): ?>
                                    <div class="actividad-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-1"><?= htmlspecialchars($actividad['nombre_actividad']) ?></h6>
                                            <strong>$<?= number_format($actividad['presupuesto_estimado'], 2, ',', '.') ?></strong>
                                        </div>
                                        
                                        <?php if ($actividad['fecha_inicio'] || $actividad['fecha_fin']): ?>
                                            <div class="text-muted small mb-2">
                                                <?php if ($actividad['fecha_inicio']): ?>
                                                    <i class="bi bi-calendar-event"></i> 
                                                    Del <?= date('d/m', strtotime($actividad['fecha_inicio'])) ?>
                                                <?php endif; ?>
                                                <?php if ($actividad['fecha_fin']): ?>
                                                    al <?= date('d/m', strtotime($actividad['fecha_fin'])) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($actividad['descripcion'])): ?>
                                            <p class="text-muted small mb-2"><?= htmlspecialchars($actividad['descripcion']) ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="text-muted small mb-2">
                                            <i class="bi bi-building"></i> 
                                            <?= htmlspecialchars($actividad['departamento_nombre']) ?>
                                        </div>
                                        
                                        <div class="row align-items-center">
                                            <div class="col-6">
                                                <small class="text-muted">Presupuesto: $<?= number_format($actividad['presupuesto_estimado'], 2, ',', '.') ?></small>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Ejecutado: $<?= number_format($actividad['monto_ejecutado'], 2, ',', '.') ?></small>
                                            </div>
                                        </div>
                                        
                                        <?php if ($actividad['monto_ejecutado'] > 0): ?>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar-fill" style="width: <?= min(($actividad['monto_ejecutado'] / $actividad['presupuesto_estimado']) * 100, 100) ?>%"></div>
                                            </div>
                                            <small class="text-muted">
                                                <?= number_format(($actividad['monto_ejecutado'] / $actividad['presupuesto_estimado']) * 100, 1) ?>% ejecutado
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Gráfico de Ejecución -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-graph-up"></i> Resumen de Ejecución Mensual
                </h2>
                <div class="chart-container">
                    <canvas id="ejecucionChart"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function aprobarPresupuesto(id) {
            if (confirm('¿Está seguro de aprobar este presupuesto? Esta acción no se puede deshacer.')) {
                fetch('api_aprobar_presupuesto.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al aprobar el presupuesto');
                });
            }
        }

        // Crear gráfico de ejecución
        <?php if ($presupuesto): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('ejecucionChart').getContext('2d');
            
            const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            const presupuestos = [];
            const ejecutados = [];
            
            <?php
            foreach ($meses as $mes):
                $actividades_mes = $detalles_por_mes[$mes] ?? [];
                $total_mes = array_sum(array_column($actividades_mes, 'presupuesto_estimado'));
                $ejecutado_mes = array_sum(array_column($actividades_mes, 'monto_ejecutado'));
            ?>
                presupuestos.push(<?= $total_mes ?>);
                ejecutados.push(<?= $ejecutado_mes ?>);
            <?php endforeach; ?>
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: meses,
                    datasets: [
                        {
                            label: 'Presupuestado',
                            data: presupuestos,
                            backgroundColor: 'rgba(102, 126, 234, 0.5)',
                            borderColor: 'rgba(102, 126, 234, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Ejecutado',
                            data: ejecutados,
                            backgroundColor: 'rgba(79, 172, 254, 0.5)',
                            borderColor: 'rgba(79, 172, 254, 1)',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-AR');
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': $' + context.parsed.y.toLocaleString('es-AR');
                                }
                            }
                        }
                    }
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
