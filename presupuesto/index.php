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

// Obtener presupuestos existentes
try {
    $stmt = $pdo->query("SELECT * FROM vista_resumen_presupuesto_anual ORDER BY anio DESC");
    $presupuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $presupuestos = [];
    $error = "Error al cargar presupuestos: " . $e->getMessage();
}

$nombre = $_SESSION['user_name'] ?? 'Usuario';
$rol = $_SESSION['user_role'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Módulo de Presupuesto - Sistema de Gestión</title>
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

        .presupuesto-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }

        .presupuesto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
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

        .monto-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .floating-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .floating-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
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
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="header-title">Módulo de Presupuesto</h1>
                    <p class="text-muted mb-0">Planificación y gestión presupuestaria anual</p>
                </div>
                <div>
                    <a href="menu_moderno.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver al Menú
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--primary-gradient);">
                    <i class="bi bi-calendar-year"></i>
                </div>
                <div class="stat-value"><?= count($presupuestos) ?></div>
                <div class="stat-label">Presupuestos Creados</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: var(--success-gradient);">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-value"><?= count(array_filter($presupuestos, fn($p) => $p['estado'] === 'aprobado')) ?></div>
                <div class="stat-label">Presupuestos Aprobados</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: var(--warning-gradient);">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="stat-value"><?= count(array_filter($presupuestos, fn($p) => $p['estado'] === 'enviado_aprobacion')) ?></div>
                <div class="stat-label">Pendientes de Aprobación</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="stat-value">$<?= number_format(array_sum(array_column($presupuestos, 'total_presupuestado')), 2, ',', '.') ?></div>
                <div class="stat-label">Total Presupuestado</div>
            </div>
        </div>

        <!-- Presupuestos List -->
        <div class="row">
            <div class="col-12">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($presupuestos)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #6c757d;"></i>
                        <h3 class="mt-3">No hay presupuestos creados</h3>
                        <p class="text-muted">Comienza creando tu primer presupuesto anual</p>
                        <a href="nuevo_presupuesto.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-plus-circle"></i> Crear Nuevo Presupuesto
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($presupuestos as $presupuesto): ?>
                        <div class="presupuesto-card">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h4 class="mb-1">Presupuesto <?= htmlspecialchars($presupuesto['anio']) ?></h4>
                                    <small class="text-muted">
                                        Creado: <?= date('d/m/Y', strtotime($presupuesto['fecha_creacion'])) ?>
                                    </small>
                                </div>
                                <div class="col-md-2">
                                    <span class="estado-badge estado-<?= $presupuesto['estado'] ?>">
                                        <?= str_replace('_', ' ', $presupuesto['estado']) ?>
                                    </span>
                                </div>
                                <div class="col-md-2">
                                    <div class="monto-display">$<?= number_format($presupuesto['total_presupuestado'], 2, ',', '.') ?></div>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-muted">
                                        <small><?= $presupuesto['total_actividades_anual'] ?> actividades</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="action-buttons">
                                        <a href="ver_presupuesto.php?id=<?= $presupuesto['id'] ?>" class="btn btn-primary btn-action">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                        <?php if ($presupuesto['estado'] === 'borrador' || ($rol === 'Admin' && $presupuesto['estado'] !== 'aprobado')): ?>
                                            <a href="editar_presupuesto.php?id=<?= $presupuesto['id'] ?>" class="btn btn-warning btn-action">
                                                <i class="bi bi-pencil"></i> Editar
                                            </a>
                                        <?php endif; ?>
                                        <a href="exportar_presupuesto.php?id=<?= $presupuesto['id'] ?>" class="btn btn-success btn-action">
                                            <i class="bi bi-download"></i> Excel
                                        </a>
                                        <?php if ($rol === 'Admin' && $presupuesto['estado'] === 'enviado_aprobacion'): ?>
                                            <button class="btn btn-success btn-action" onclick="aprobarPresupuesto(<?= $presupuesto['id'] ?>)">
                                                <i class="bi bi-check-circle"></i> Aprobar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <a href="nuevo_presupuesto.php" class="floating-btn">
        <i class="bi bi-plus"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
    </script>
</body>
</html>
