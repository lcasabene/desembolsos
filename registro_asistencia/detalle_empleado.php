<?php
require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/database.php';

// Solo administradores pueden acceder
verificar_autenticacion();
if ($_SESSION['user_role'] !== 'Admin') {
    header('Location: ../acceso_denegado.php');
    exit;
}

$usuario_id = $_GET['usuario_id'] ?? '';
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

if (empty($usuario_id)) {
    header('Location: reportes_simple.php');
    exit;
}

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: reportes_simple.php');
    exit;
}

// Obtener todos los registros del usuario en el período
$stmt = $pdo->prepare("
    SELECT a.*, 
           CASE 
               WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL 
               THEN TIMESTAMPDIFF(MINUTE, a.hora_entrada, a.hora_salida) / 60 
               ELSE NULL 
           END as horas_dia,
           CASE 
               WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL 
               THEN SEC_TO_TIME(TIMESTAMPDIFF(SECOND, a.hora_entrada, a.hora_salida))
               ELSE NULL 
           END as tiempo_trabajado
    FROM asistencia a
    WHERE a.usuario_id = ? AND MONTH(a.fecha) = ? AND YEAR(a.fecha) = ?
    ORDER BY a.fecha DESC, a.secuencia ASC
");
$stmt->execute([$usuario_id, $mes, $anio]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_horas = 0;
$total_dias = 0;
$horas_aprobadas = 0;
$horas_pendientes = 0;

foreach ($registros as $registro) {
    if ($registro['horas_dia']) {
        $total_horas += $registro['horas_dia'];
        $total_dias++;
        
        if ($registro['estado'] === 'aprobado') {
            $horas_aprobadas += $registro['horas_dia'];
        } elseif ($registro['estado'] === 'pendiente_aprobacion') {
            $horas_pendientes += $registro['horas_dia'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de Asistencia - <?= htmlspecialchars($usuario['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .main-container { max-width: 1400px; margin: 0 auto; }
        .page-header { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .summary-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 15px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .summary-value { font-size: 2rem; font-weight: 700; color: #2c3e50; }
        .summary-label { color: #6c757d; font-size: 0.9rem; }
        .table-container { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .status-aprobado { background: #d4edda; color: #155724; }
        .status-pendiente { background: #fff3cd; color: #856404; }
        .status-rechazado { background: #f8d7da; color: #721c24; }
        .secuencia-badge { background: #6c757d; color: white; padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">Detalle de Asistencia</h1>
                    <h4 class="text-primary"><?= htmlspecialchars($usuario['nombre']) ?></h4>
                    <p class="text-muted mb-0"><?= htmlspecialchars($usuario['email']) ?> - Período: <?= $mes ?>/<?= $anio ?></p>
                </div>
                <div>
                    <a href="reportes_simple.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Volver a Reportes
                    </a>
                    <a href="admin_aprobaciones.php?usuario_id=<?= $usuario_id ?>" class="btn btn-warning me-2">
                        <i class="bi bi-check-square"></i> Aprobaciones
                    </a>
                    <a href="../menu_moderno.php" class="btn btn-primary">
                        <i class="bi bi-house-door"></i> Menú Principal
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'editado'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i>
                Registro editado correctamente. El registro ahora está pendiente de aprobación.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Resumen -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-value"><?= count($registros) ?></div>
                <div class="summary-label">Total Registros</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?= $total_dias ?></div>
                <div class="summary-label">Días Trabajados</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?= number_format($total_horas, 2) ?>h</div>
                <div class="summary-label">Total Horas</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?= number_format($total_horas / max($total_dias, 1), 2) ?>h</div>
                <div class="summary-label">Promedio Diario</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?= number_format($horas_aprobadas, 2) ?>h</div>
                <div class="summary-label">Horas Aprobadas</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?= number_format($horas_pendientes, 2) ?>h</div>
                <div class="summary-label">Horas Pendientes</div>
            </div>
        </div>

        <!-- Tabla Detallada -->
        <div class="table-container">
            <h3 class="mb-4">Movimientos Detallados</h3>
            
            <?php if (empty($registros)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x" style="font-size: 4rem; color: #6c757d;"></i>
                    <h4 class="mt-3">No hay registros</h4>
                    <p class="text-muted"><?= htmlspecialchars($usuario['nombre']) ?> no tiene registros de asistencia en este período.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tablaDetalle" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Sec</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Tiempo</th>
                                <th>Horas</th>
                                <th>Estado</th>
                                <th>Ubicación</th>
                                <th>Observaciones</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $registro): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($registro['fecha'])) ?></td>
                                <td><span class="secuencia-badge"><?= $registro['secuencia'] ?></span></td>
                                <td><?= $registro['hora_entrada'] ? date('H:i:s', strtotime($registro['hora_entrada'])) : '-' ?></td>
                                <td><?= $registro['hora_salida'] ? date('H:i:s', strtotime($registro['hora_salida'])) : '-' ?></td>
                                <td><?= $registro['tiempo_trabajado'] ?: '-' ?></td>
                                <td><strong><?= $registro['horas_dia'] ? number_format($registro['horas_dia'], 2) . 'h' : '-' ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?= $registro['estado'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $registro['estado'])) ?>
                                    </span>
                                </td>
                                <td><?= $registro['ubicacion_entrada'] ? substr($registro['ubicacion_entrada'], 0, 20) . '...' : '-' ?></td>
                                <td><?= $registro['observaciones'] ?: '-' ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="editar_registro_admin.php?id=<?= $registro['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($registro['estado'] === 'pendiente_aprobacion'): ?>
                                        <a href="admin_aprobaciones.php#registro-<?= $registro['id'] ?>" class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-check"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="mt-4 d-flex gap-2">
                <a href="reportes_simple.php" class="btn btn-info">
                    <i class="bi bi-file-earmark-pdf"></i> Volver a Reportes
                </a>
                <a href="admin_aprobaciones.php?usuario_id=<?= $usuario_id ?>" class="btn btn-warning">
                    <i class="bi bi-check-square"></i> Ver Aprobaciones
                </a>
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-clock-history"></i> Mi Registro
                </a>
                <a href="../menu_moderno.php" class="btn btn-primary">
                    <i class="bi bi-house-door"></i> Menú Principal
                </a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tablaDetalle').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                pageLength: 50,
                order: [[0, 'desc']],
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'excel', 'pdf'
                ]
            });
        });
    </script>
</body>
</html>
