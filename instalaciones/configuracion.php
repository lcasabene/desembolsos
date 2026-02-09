<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

// Verificar si el usuario tiene acceso al módulo y es admin
$modulos = $_SESSION['modulos'] ?? [];
if (!in_array('Instalaciones', $modulos) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../acceso_denegado.php");
    exit;
}

// Procesar asignación de rol Portero
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'asignar_portero') {
    $usuario_id = (int)($_POST['usuario_id'] ?? 0);
    $accion = $_POST['accion_portero'] ?? '';
    
    if ($usuario_id > 0 && in_array($accion, ['asignar', 'revocar'])) {
        try {
            if ($accion === 'asignar') {
                // Asignar rol Portero
                $stmt = $pdo->prepare("UPDATE usuarios SET rol = 'Portero' WHERE id = ? AND rol != 'Admin'");
                $stmt->execute([$usuario_id]);
                
                // Asegurar que tenga acceso al módulo Instalaciones
                $stmt = $pdo->prepare("INSERT IGNORE INTO usuario_modulos (usuario_id, modulo) VALUES (?, 'Instalaciones')");
                $stmt->execute([$usuario_id]);
                
                $mensaje = "Usuario asignado como Portero exitosamente";
            } else {
                // Revocar rol Portero (volver a Solicitante)
                $stmt = $pdo->prepare("UPDATE usuarios SET rol = 'Solicitante' WHERE id = ? AND rol = 'Portero'");
                $stmt->execute([$usuario_id]);
                
                $mensaje = "Rol de Portero revocado exitosamente";
            }
        } catch (Exception $e) {
            $error = "Error al procesar la solicitud: " . $e->getMessage();
        }
    }
}

// Procesar actualización de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'actualizar') {
        $configuraciones = [
            'max_dias_anticipacion' => $_POST['max_dias_anticipacion'] ?? 30,
            'min_horas_anticipacion' => $_POST['min_horas_anticipacion'] ?? 2,
            'max_duracion_reserva' => $_POST['max_duracion_reserva'] ?? 4,
            'max_horas_diarias' => $_POST['max_horas_diarias'] ?? 8,
            'max_reservas_semana' => $_POST['max_reservas_semana'] ?? 5,
            'requiere_aprobacion' => $_POST['requiere_aprobacion'] ?? 'true',
            'permiso_admin_duracion' => $_POST['permiso_admin_duracion'] ?? 'false'
        ];

        foreach ($configuraciones as $parametro => $valor) {
            // Primero verificar si el parámetro existe
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM configuracion_instalaciones WHERE parametro = ?");
            $stmt_check->execute([$parametro]);
            $existe = $stmt_check->fetchColumn() > 0;
            
            if ($existe) {
                // Actualizar si existe
                $stmt = $pdo->prepare("UPDATE configuracion_instalaciones SET valor = ?, updated_at = NOW() WHERE parametro = ?");
                $stmt->execute([$valor, $parametro]);
            } else {
                // Insertar si no existe (con descripción por defecto)
                $descripciones = [
                    'max_dias_anticipacion' => 'Máximo de días de anticipación para reservar',
                    'min_horas_anticipacion' => 'Mínimo de horas de anticipación para reservar',
                    'max_duracion_reserva' => 'Duración máxima en horas por reserva',
                    'max_horas_diarias' => 'Máximo de horas totales que un usuario puede reservar por día',
                    'max_reservas_semana' => 'Máximo de reservas que un usuario puede hacer por semana',
                    'requiere_aprobacion' => 'Las reservas requieren aprobación automática',
                    'permiso_admin_duracion' => 'Permite a administradores extender la duración de reservas existentes'
                ];
                
                $descripcion = $descripciones[$parametro] ?? 'Parámetro de configuración';
                $stmt = $pdo->prepare("INSERT INTO configuracion_instalaciones (parametro, valor, descripcion, updated_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$parametro, $valor, $descripcion]);
            }
        }

        $mensaje = "Configuración actualizada exitosamente";
    }
}

// Obtener configuración actual
$stmt = $pdo->query("SELECT parametro, valor, descripcion FROM configuracion_instalaciones ORDER BY parametro");
$config = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $config[$row['parametro']] = [
        'valor' => $row['valor'],
        'descripcion' => $row['descripcion']
    ];
}

// Obtener lista de usuarios para gestión de porteros
$stmt_usuarios = $pdo->query("
    SELECT u.id, u.nombre, u.email, u.rol, 
           GROUP_CONCAT(um.modulo ORDER BY um.modulo SEPARATOR ', ') AS modulos
    FROM usuarios u
    LEFT JOIN usuario_modulos um ON u.id = um.usuario_id
    WHERE u.activo = 1
    GROUP BY u.id, u.nombre, u.email, u.rol
    ORDER BY u.nombre
");
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
            <h2><i class="bi bi-gear"></i> Configuración del Sistema</h2>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Información importante -->
        <div class="alert alert-info">
            <h6><i class="bi bi-info-circle"></i> Información Importante</h6>
            <p class="mb-0">
                Estos parámetros controlan el comportamiento del sistema de reservas. 
                Modifícalos con cuidado ya que afectan directamente cómo los usuarios pueden hacer reservas.
            </p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-sliders"></i> Parámetros de Configuración</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="actualizar">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_dias_anticipacion" class="form-label">
                                            Máximo días de anticipación
                                        </label>
                                        <input type="number" class="form-control" id="max_dias_anticipacion" 
                                               name="max_dias_anticipacion" min="1" max="365" required
                                               value="<?= htmlspecialchars($config['max_dias_anticipacion']['valor'] ?? 30) ?>">
                                        <div class="form-text">
                                            <?= htmlspecialchars($config['max_dias_anticipacion']['descripcion'] ?? '') ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="min_horas_anticipacion" class="form-label">
                                            Mínimo horas de anticipación
                                        </label>
                                        <input type="number" class="form-control" id="min_horas_anticipacion" 
                                               name="min_horas_anticipacion" min="0" max="24" required
                                               value="<?= htmlspecialchars($config['min_horas_anticipacion']['valor'] ?? 2) ?>">
                                        <div class="form-text">
                                            <?= htmlspecialchars($config['min_horas_anticipacion']['descripcion'] ?? '') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_duracion_reserva" class="form-label">
                                            Duración máxima por reserva (horas)
                                        </label>
                                        <input type="number" class="form-control" id="max_duracion_reserva" 
                                               name="max_duracion_reserva" min="1" max="12" required
                                               value="<?= htmlspecialchars($config['max_duracion_reserva']['valor'] ?? 4) ?>">
                                        <div class="form-text">
                                            <?= htmlspecialchars($config['max_duracion_reserva']['descripcion'] ?? '') ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_horas_diarias" class="form-label">
                                            Máximo horas diarias por usuario
                                        </label>
                                        <input type="number" class="form-control" id="max_horas_diarias" 
                                               name="max_horas_diarias" min="1" max="24" required
                                               value="<?= htmlspecialchars($config['max_horas_diarias']['valor'] ?? 8) ?>">
                                        <div class="form-text">
                                            <?= htmlspecialchars($config['max_horas_diarias']['descripcion'] ?? '') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_reservas_semana" class="form-label">
                                            Máximo reservas semanales por usuario
                                        </label>
                                        <input type="number" class="form-control" id="max_reservas_semana" 
                                               name="max_reservas_semana" min="1" max="50" required
                                               value="<?= htmlspecialchars($config['max_reservas_semana']['valor'] ?? 5) ?>">
                                        <div class="form-text">
                                            <?= htmlspecialchars($config['max_reservas_semana']['descripcion'] ?? '') ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="requiere_aprobacion" class="form-label">
                                            Requiere aprobación automática
                                        </label>
                                        <select class="form-select" id="requiere_aprobacion" name="requiere_aprobacion">
                                            <option value="true" <?= ($config['requiere_aprobacion']['valor'] ?? 'true') === 'true' ? 'selected' : '' ?>>
                                                Sí - Las reservas requieren aprobación
                                            </option>
                                            <option value="false" <?= ($config['requiere_aprobacion']['valor'] ?? 'true') === 'false' ? 'selected' : '' ?>>
                                                No - Las reservas se aprueban automáticamente
                                            </option>
                                        </select>
                                        <div class="form-text">
                                            <?= htmlspecialchars($config['requiere_aprobacion']['descripcion'] ?? '') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="permiso_admin_duracion" class="form-label">
                                            Admin puede modificar duración
                                        </label>
                                        <select class="form-select" id="permiso_admin_duracion" name="permiso_admin_duracion">
                                            <option value="true" <?= ($config['permiso_admin_duracion']['valor'] ?? 'false') === 'true' ? 'selected' : '' ?>>
                                                Sí - Los administradores pueden extender reservas
                                            </option>
                                            <option value="false" <?= ($config['permiso_admin_duracion']['valor'] ?? 'false') === 'false' ? 'selected' : '' ?>>
                                                No - Respetar límites establecidos
                                            </option>
                                        </select>
                                        <div class="form-text">
                                            <?= htmlspecialchars($config['permiso_admin_duracion']['descripcion'] ?? 'Permite a administradores extender la duración de reservas existentes') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="../menu_instalaciones.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Guardar Configuración
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Resumen de configuración actual -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-eye"></i> Configuración Actual</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <strong>Anticipación:</strong><br>
                                <small class="text-muted">
                                    <?= $config['min_horas_anticipacion']['valor'] ?? 2 ?>h mínimo / 
                                    <?= $config['max_dias_anticipacion']['valor'] ?? 30 ?> días máximo
                                </small>
                            </li>
                            <li class="mb-2">
                                <strong>Límites por usuario:</strong><br>
                                <small class="text-muted">
                                    <?= $config['max_duracion_reserva']['valor'] ?? 4 ?>h por reserva<br>
                                    <?= $config['max_horas_diarias']['valor'] ?? 8 ?>h por día<br>
                                    <?= $config['max_reservas_semana']['valor'] ?? 5 ?> reservas por semana
                                </small>
                            </li>
                            <li class="mb-2">
                                <strong>Aprobación:</strong><br>
                                <small class="text-muted">
                                    <?= ($config['requiere_aprobacion']['valor'] ?? 'true') === 'true' ? 'Requiere aprobación' : 'Automática' ?>
                                </small>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Impacto de los cambios -->
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Impacto de los Cambios</h6>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0 small">
                            <li>Los cambios afectan inmediatamente las nuevas reservas</li>
                            <li>Las reservas existentes no se modifican</li>
                            <li>Los usuarios verán las nuevas reglas al intentar reservar</li>
                            <li>Se recomienda notificar los cambios importantes</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gestión de Porteros -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-shield-check"></i> Gestión de Porteros</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> ¿Qué es un Portero?</h6>
                            <p class="mb-0">
                                Los <strong>Porteros</strong> son usuarios especiales que pueden ver todas las actividades y reservas del sistema 
                                de instalaciones para estar al tanto de lo que sucede. Tienen acceso limitado:
                            </p>
                            <ul class="mb-0 mt-2">
                                <li>✅ Pueden ver el calendario y todas las reservas</li>
                                <li>✅ Pueden ver reportes y estadísticas</li>
                                <li>✅ Pueden ver detalles de reservas</li>
                                <li>❌ No pueden aprobar/rechazar reservas</li>
                                <li>❌ No pueden modificar configuración</li>
                                <li>❌ No pueden eliminar reservas</li>
                            </ul>
                        </div>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Rol Actual</th>
                                        <th>Módulos</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($usuario['nombre']) ?></strong>
                                                <?php if ($usuario['rol'] === 'Portero'): ?>
                                                    <span class="badge bg-info ms-2">Portero</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?= $usuario['rol'] === 'Admin' ? 'bg-danger' : 
                                                       ($usuario['rol'] === 'Portero' ? 'bg-info' : 
                                                       ($usuario['rol'] === 'Aprobador' ? 'bg-warning' : 'bg-secondary')) ?>">
                                                    <?= htmlspecialchars($usuario['rol']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($usuario['modulos'] ?? 'Sin módulos') ?></td>
                                            <td>
                                                <?php if ($usuario['rol'] !== 'Admin'): ?>
                                                    <?php if ($usuario['rol'] === 'Portero'): ?>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('¿Estás seguro de revocar el rol de Portero a este usuario?')">
                                                            <input type="hidden" name="action" value="asignar_portero">
                                                            <input type="hidden" name="usuario_id" value="<?= $usuario['id'] ?>">
                                                            <input type="hidden" name="accion_portero" value="revocar">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-shield-x"></i> Revocar Portero
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('¿Estás seguro de asignar el rol de Portero a este usuario?')">
                                                            <input type="hidden" name="action" value="asignar_portero">
                                                            <input type="hidden" name="usuario_id" value="<?= $usuario['id'] ?>">
                                                            <input type="hidden" name="accion_portero" value="asignar">
                                                            <button type="submit" class="btn btn-sm btn-outline-info">
                                                                <i class="bi bi-shield-plus"></i> Hacer Portero
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="bi bi-shield-fill-check"></i> Administrador
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> 
                                Los porteros pueden acceder al módulo de instalaciones a través del menú principal 
                                y verán todas las actividades pero con permisos limitados.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validar que los valores sean lógicos
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(e) {
                const minHoras = parseInt(document.getElementById('min_horas_anticipacion').value);
                const maxDias = parseInt(document.getElementById('max_dias_anticipacion').value);
                const maxDuracion = parseInt(document.getElementById('max_duracion_reserva').value);
                const maxHorasDiarias = parseInt(document.getElementById('max_horas_diarias').value);
                const maxReservasSemana = parseInt(document.getElementById('max_reservas_semana').value);
                
                // Validaciones lógicas
                if (maxDuracion > maxHorasDiarias) {
                    e.preventDefault();
                    alert('La duración máxima por reserva no puede ser mayor que las horas diarias máximas');
                    return;
                }
                
                if (minHoras > 24) {
                    e.preventDefault();
                    alert('El mínimo de horas de anticipación no puede ser mayor a 24');
                    return;
                }
                
                if (maxDias > 365) {
                    e.preventDefault();
                    alert('El máximo de días de anticipación no puede ser mayor a 365');
                    return;
                }
                
                if (maxHorasDiarias > 24) {
                    e.preventDefault();
                    alert('Las horas diarias máximas no pueden ser mayor a 24');
                    return;
                }
            });
        });
    </script>
</body>
</html>
