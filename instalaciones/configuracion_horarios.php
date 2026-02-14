<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

// Verificar acceso al módulo
$modulos = $_SESSION['modulos'] ?? [];
$user_role = $_SESSION['user_role'] ?? '';
if (!in_array('Instalaciones', $modulos) || $user_role !== 'Admin') {
    header("Location: ../acceso_denegado.php");
    exit;
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'agregar_horario':
                $nombre = $_POST['nombre'] ?? '';
                $hora_inicio = $_POST['hora_inicio'] ?? '';
                $hora_fin = $_POST['hora_fin'] ?? '';
                $dias_semana = json_encode($_POST['dias_semana'] ?? []);
                
                if ($nombre && $hora_inicio && $hora_fin && !empty($_POST['dias_semana'])) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO configuracion_horarios (nombre, hora_inicio, hora_fin, dias_semana) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$nombre, $hora_inicio, $hora_fin, $dias_semana]);
                        $mensaje = "Horario agregado exitosamente";
                    } catch (PDOException $e) {
                        // Si no existe la tabla, usar rangos_horarios
                        $stmt = $pdo->prepare("
                            INSERT INTO rangos_horarios (nombre, hora_inicio, hora_fin, dias_semana) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$nombre, $hora_inicio, $hora_fin, $dias_semana]);
                        $mensaje = "Rango horario agregado exitosamente";
                    }
                } else {
                    $error = "Todos los campos son obligatorios";
                }
                break;
                
            case 'eliminar_horario':
                $id = (int)($_POST['id'] ?? 0);
                if ($id) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM configuracion_horarios WHERE id = ?");
                        $stmt->execute([$id]);
                        $mensaje = "Horario eliminado";
                    } catch (PDOException $e) {
                        // Si no existe la tabla, usar rangos_horarios
                        $stmt = $pdo->prepare("DELETE FROM rangos_horarios WHERE id = ?");
                        $stmt->execute([$id]);
                        $mensaje = "Rango horario eliminado";
                    }
                }
                break;
                
            case 'agregar_feriado':
                $nombre = $_POST['nombre'] ?? '';
                $fecha = $_POST['fecha'] ?? '';
                $descripcion = $_POST['descripcion'] ?? '';
                $recurrente = $_POST['recurrente'] ?? 'unico';
                
                if ($nombre && $fecha) {
                    $stmt = $pdo->prepare("
                        INSERT INTO feriados (nombre, fecha, descripcion, recurrente) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$nombre, $fecha, $descripcion, $recurrente]);
                    $mensaje = "Feriado agregado exitosamente";
                } else {
                    $error = "Nombre y fecha son obligatorios";
                }
                break;
                
            case 'eliminar_feriado':
                $id = (int)($_POST['id'] ?? 0);
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM feriados WHERE id = ?");
                    $stmt->execute([$id]);
                    $mensaje = "Feriado eliminado";
                }
                break;
        }
    }
}

// Obtener configuración de horarios (usar rangos_horarios mientras no exista configuracion_horarios)
try {
    $horarios_config = $pdo->query("
        SELECT * FROM configuracion_horarios 
        WHERE estado = 'activo' 
        ORDER BY hora_inicio
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si no existe la tabla, usar rangos_horarios
    $horarios_config = $pdo->query("
        SELECT * FROM rangos_horarios 
        WHERE estado = 'activo' 
        ORDER BY hora_inicio
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener feriados (próximos 30 días + recurrentes)
$feriados = $pdo->query("
    SELECT * FROM feriados 
    WHERE fecha >= CURDATE() OR recurrente = 'anual'
    ORDER BY fecha
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Nombres de días de la semana
$dias_semana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración de Horarios - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
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

    <div class="container py-4">
        <div class="container mt-4">
            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $mensaje ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <h2><i class="bi bi-clock"></i> Configuración de Horarios y Feriados</h2>

        <!-- Horarios Permitidos -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Horarios Permitidos para Reservas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Horario</th>
                                <th>Días</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horarios_config as $horario): ?>
                                <tr>
                                    <td><?= htmlspecialchars($horario['nombre']) ?></td>
                                    <td><?= $horario['hora_inicio'] ?> - <?= $horario['hora_fin'] ?></td>
                                    <td>
                                        <?php 
                                        $dias = json_decode($horario['dias_semana'], true) ?? [];
                                        foreach ($dias as $dia) {
                                            echo '<span class="badge bg-secondary me-1">' . $dias_semana[$dia] . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="eliminarHorario(<?= $horario['id'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($horarios_config)): ?>
                                <tr><td colspan="4" class="text-center text-muted">No hay horarios configurados</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Formulario agregar rango -->
                <hr>
                <h6>Agregar Nuevo Horario Permitido</h6>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="agregar_horario">
                    <div class="col-md-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" name="nombre" required 
                               placeholder="Ej: Turno Mañana">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hora Inicio *</label>
                        <input type="time" class="form-control" name="hora_inicio" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hora Fin *</label>
                        <input type="time" class="form-control" name="hora_fin" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Días *</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="dias_semana[]" value="1" id="lunes">
                            <label class="form-check-label" for="lunes">Lun</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="dias_semana[]" value="2" id="martes">
                            <label class="form-check-label" for="martes">Mar</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="dias_semana[]" value="3" id="miercoles">
                            <label class="form-check-label" for="miercoles">Mié</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="dias_semana[]" value="4" id="jueves">
                            <label class="form-check-label" for="jueves">Jue</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="dias_semana[]" value="5" id="viernes">
                            <label class="form-check-label" for="viernes">Vie</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="dias_semana[]" value="6" id="sabado">
                            <label class="form-check-label" for="sabado">Sáb</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="dias_semana[]" value="7" id="domingo">
                            <label class="form-check-label" for="domingo">Dom</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Agregar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Feriados -->
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-calendar-x me-2"></i>Feriados y Días No Laborables</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Tipo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feriados as $feriado): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($feriado['fecha'])) ?></td>
                                    <td><?= htmlspecialchars($feriado['nombre']) ?></td>
                                    <td><?= htmlspecialchars($feriado['descripcion'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $feriado['recurrente'] === 'anual' ? 'info' : 'secondary' ?>">
                                            <?= $feriado['recurrente'] === 'anual' ? 'Anual' : 'Único' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="eliminarFeriado(<?= $feriado['id'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($feriados)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No hay feriados configurados</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Formulario agregar feriado -->
                <hr>
                <h6>Agregar Nuevo Feriado</h6>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="agregar_feriado">
                    <div class="col-md-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" name="nombre" required 
                               placeholder="Ej: Día de la Independencia">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha *</label>
                        <input type="date" class="form-control" name="fecha" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="descripcion" 
                               placeholder="Opcional">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="recurrente">
                            <option value="unico">Único</option>
                            <option value="anual">Anual (se repite cada año)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-plus-circle"></i> Agregar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Información -->
        <div class="alert alert-info mt-4">
            <h6><i class="bi bi-info-circle me-2"></i>Información importante:</h6>
            <ul class="mb-0">
                <li>Los horarios permitidos definen en qué días y rangos de horas se pueden hacer reservas.</li>
                <li>Los usuarios pueden seleccionar cualquier hora DENTRO de los rangos configurados.</li>
                <li>Los feriados anuales se repiten automáticamente cada año en la misma fecha.</li>
                <li>Los feriados bloquean todos los salones para esa fecha, sin importar el horario.</li>
                <li>El sistema validará que el horario seleccionado esté dentro de los rangos permitidos.</li>
            </ul>
        </div>
    </div>

    <!-- Formularios ocultos para eliminación -->
    <form id="formEliminarHorario" method="POST" style="display: none;">
        <input type="hidden" name="action" value="eliminar_horario">
        <input type="hidden" name="id" id="eliminarHorarioId">
    </form>
    
    <form id="formEliminarFeriado" method="POST" style="display: none;">
        <input type="hidden" name="action" value="eliminar_feriado">
        <input type="hidden" name="id" id="eliminarFeriadoId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function eliminarHorario(id) {
            if (confirm('¿Estás seguro de eliminar este horario?')) {
                document.getElementById('eliminarHorarioId').value = id;
                document.getElementById('formEliminarHorario').submit();
            }
        }
        
        function eliminarFeriado(id) {
            if (confirm('¿Estás seguro de eliminar este feriado?')) {
                document.getElementById('eliminarFeriadoId').value = id;
                document.getElementById('formEliminarFeriado').submit();
            }
        }
    </script>
</body>
</html>
