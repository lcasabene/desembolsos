<?php
require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/database.php';

// Solo administradores pueden acceder
verificar_autenticacion();
if ($_SESSION['user_role'] !== 'Admin') {
    header('Location: ../acceso_denegado.php');
    exit;
}

// Procesar acciones de aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['registro_id'])) {
    $accion = $_POST['accion'];
    $registro_id = $_POST['registro_id'];
    $admin_id = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM asistencia WHERE id = ?");
        $stmt->execute([$registro_id]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registro) {
            $nuevo_estado = ($accion === 'aprobar') ? 'aprobado' : 'rechazado';
            
            // Actualizar estado
            $stmt = $pdo->prepare("
                UPDATE asistencia 
                SET estado = ?, editado_por = ?, fecha_edicion = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$nuevo_estado, $admin_id, $registro_id]);
            
            // Registrar en auditoría
            $stmt = $pdo->prepare("
                INSERT INTO asistencia_auditoria (asistencia_id, usuario_id, accion, valor_anterior, valor_nuevo, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $registro_id, 
                $admin_id, 
                $accion,
                json_encode(['estado' => $registro['estado']]),
                json_encode(['estado' => $nuevo_estado]),
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
            
            header('Location: admin_aprobaciones.php?success=' . $accion);
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error al procesar: " . $e->getMessage();
    }
}

// Obtener registros pendientes
$where_conditions = ["a.estado = 'pendiente_aprobacion'"];
$params = [];

// Si se especifica un usuario_id, filtrar por ese usuario
$usuario_filtro = $_GET['usuario_id'] ?? '';
if (!empty($usuario_filtro)) {
    $where_conditions[] = "a.usuario_id = ?";
    $params[] = $usuario_filtro;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$stmt = $pdo->prepare("
    SELECT a.*, u.nombre, u.email 
    FROM asistencia a
    JOIN usuarios u ON a.usuario_id = u.id
    $where_clause
    ORDER BY a.fecha DESC
");
$stmt->execute($params);
$pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$where_estadisticas = "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
if (!empty($usuario_filtro)) {
    $where_estadisticas .= " AND usuario_id = ?";
}

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_pendientes,
        COUNT(CASE WHEN estado = 'pendiente_aprobacion' THEN 1 END) as pendientes,
        COUNT(CASE WHEN estado = 'aprobado' THEN 1 END) as aprobados,
        COUNT(CASE WHEN estado = 'rechazado' THEN 1 END) as rechazados
    FROM asistencia 
    WHERE $where_estadisticas
");
$estadisticas_params = !empty($usuario_filtro) ? [$usuario_filtro] : [];
$stmt->execute($estadisticas_params);
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener información del usuario filtrado
$usuario_info = null;
if (!empty($usuario_filtro)) {
    $stmt = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_filtro]);
    $usuario_info = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aprobaciones - Sistema de Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .main-container { max-width: 1200px; margin: 0 auto; }
        .page-header { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .table-container { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 15px; padding: 1.5rem; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: 700; color: #2c3e50; }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">
                        <?php if (!empty($usuario_info)): ?>
                            Aprobaciones - <?= htmlspecialchars($usuario_info['nombre']) ?>
                        <?php else: ?>
                            Aprobaciones de Asistencia
                        <?php endif; ?>
                    </h1>
                    <p class="text-muted mb-0">
                        <?php if (!empty($usuario_info)): ?>
                            Gestiona la aprobación de registros de <?= htmlspecialchars($usuario_info['nombre']) ?> (<?= htmlspecialchars($usuario_info['email']) ?>)
                        <?php else: ?>
                            Gestiona la aprobación de registros editados por colaboradores
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if (!empty($usuario_filtro)): ?>
                    <a href="admin_aprobaciones.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Ver Todos
                    </a>
                    <?php endif; ?>
                    <a href="reportes_simple.php" class="btn btn-info me-2">
                        <i class="bi bi-file-earmark-pdf"></i> Reportes
                    </a>
                    <a href="index.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-clock-history"></i> Mi Registro
                    </a>
                    <a href="../menu_moderno.php" class="btn btn-primary">
                        <i class="bi bi-house-door"></i> Menú Principal
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i>
                <?= ucfirst($_GET['success']) ?> realizado correctamente
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $estadisticas['pendientes'] ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $estadisticas['aprobados'] ?></div>
                <div class="stat-label">Aprobados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $estadisticas['rechazados'] ?></div>
                <div class="stat-label">Rechazados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $estadisticas['total_pendientes'] ?></div>
                <div class="stat-label">Total Mes</div>
            </div>
        </div>

        <div class="table-container">
            <h3 class="mb-4">Registros Pendientes de Aprobación</h3>
            
            <?php if (empty($pendientes)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle" style="font-size: 4rem; color: #28a745;"></i>
                    <h4 class="mt-3">No hay registros pendientes</h4>
                    <p class="text-muted">
                        <?php if (!empty($usuario_info)): ?>
                            <?= htmlspecialchars($usuario_info['nombre']) ?> no tiene registros pendientes de aprobación.
                        <?php else: ?>
                            Todos los registros están aprobados o no hay ediciones pendientes.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($usuario_filtro)): ?>
                    <a href="admin_aprobaciones.php" class="btn btn-outline-primary mt-3">
                        <i class="bi bi-arrow-left"></i> Ver Todos los Registros
                    </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tablaPendientes" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th>Fecha</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Horas</th>
                                <th>Observaciones</th>
                                <th>Última Edición</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendientes as $registro): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($registro['nombre']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($registro['email']) ?></small>
                                </td>
                                <td><?= date('d/m/Y', strtotime($registro['fecha'])) ?></td>
                                <td><?= $registro['hora_entrada'] ? date('H:i', strtotime($registro['hora_entrada'])) : '-' ?></td>
                                <td><?= $registro['hora_salida'] ? date('H:i', strtotime($registro['hora_salida'])) : '-' ?></td>
                                <td>
                                    <?php 
                                    if ($registro['hora_entrada'] && $registro['hora_salida']) {
                                        $minutos = (strtotime($registro['hora_salida']) - strtotime($registro['hora_entrada'])) / 60;
                                        echo number_format($minutos / 60, 1) . 'h';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?= $registro['observaciones'] ?: '-' ?></td>
                                <td>
                                    <?php if ($registro['fecha_edicion']): ?>
                                        <?= date('d/m/Y H:i', strtotime($registro['fecha_edicion'])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="editar_registro_admin.php?id=<?= $registro['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Confirmar aprobación?')">
                                            <input type="hidden" name="registro_id" value="<?= $registro['id'] ?>">
                                            <input type="hidden" name="accion" value="aprobar">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="bi bi-check"></i> Aprobar
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline; margin-left: 5px;" onsubmit="return confirm('¿Confirmar rechazo?')">
                                            <input type="hidden" name="registro_id" value="<?= $registro['id'] ?>">
                                            <input type="hidden" name="accion" value="rechazar">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-x"></i> Rechazar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="mt-4 d-flex gap-2">
                <?php if (!empty($usuario_filtro)): ?>
                <a href="reportes_simple.php" class="btn btn-info">
                    <i class="bi bi-file-earmark-pdf"></i> Volver a Reportes
                </a>
                <a href="admin_aprobaciones.php" class="btn btn-outline-secondary">
                    <i class="bi bi-people"></i> Ver Todos
                </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-primary me-2">
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
            $('#tablaPendientes').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                pageLength: 25,
                order: [[1, 'desc']]
            });
        });
    </script>
</body>
</html>
