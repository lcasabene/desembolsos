<?php
require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/database.php';

// Solo administradores pueden acceder
verificar_autenticacion();
if ($_SESSION['user_role'] !== 'Admin') {
    header('Location: ../acceso_denegado.php');
    exit;
}

$registro_id = $_GET['id'] ?? 0;
$usuario_id = $_GET['usuario_id'] ?? '';

// Obtener registro
$stmt = $pdo->prepare("
    SELECT a.*, u.nombre, u.email 
    FROM asistencia a
    JOIN usuarios u ON a.usuario_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$registro_id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registro) {
    header('Location: reportes_simple.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hora_entrada = $_POST['hora_entrada'] ?? null;
    $hora_salida = $_POST['hora_salida'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
    
    try {
        // Guardar valores originales para auditoría
        $valores_originales = [
            'hora_entrada' => $registro['hora_entrada'],
            'hora_salida' => $registro['hora_salida'],
            'observaciones' => $registro['observaciones']
        ];
        
        // Actualizar registro
        $stmt = $pdo->prepare("
            UPDATE asistencia 
            SET hora_entrada = ?, hora_salida = ?, observaciones = ?, 
                estado = 'pendiente_aprobacion', editado_por = ?, fecha_edicion = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$hora_entrada, $hora_salida, $observaciones, $_SESSION['user_id'], $registro_id]);
        
        // Registrar en auditoría
        $valores_nuevos = [
            'hora_entrada' => $hora_entrada,
            'hora_salida' => $hora_salida,
            'observaciones' => $observaciones
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO asistencia_auditoria (asistencia_id, usuario_id, accion, valor_anterior, valor_nuevo, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $registro_id,
            $_SESSION['user_id'],
            'edicion_admin',
            json_encode($valores_originales),
            json_encode($valores_nuevos),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        // Redirigir con mensaje de éxito
        $redirect_url = "detalle_empleado.php?usuario_id={$registro['usuario_id']}&mes=" . date('m', strtotime($registro['fecha'])) . "&anio=" . date('Y', strtotime($registro['fecha']));
        header("Location: $redirect_url&success=editado");
        exit;
        
    } catch (PDOException $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Registro - <?= htmlspecialchars($registro['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .form-container { max-width: 700px; margin: 0 auto; }
        .form-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .info-section { background: #f8f9fa; border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="form-container">
        <!-- Botones de navegación -->
        <div class="mb-3">
            <a href="detalle_empleado.php?usuario_id=<?= $registro['usuario_id'] ?>&mes=<?= date('m', strtotime($registro['fecha'])) ?>&anio=<?= date('Y', strtotime($registro['fecha'])) ?>" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Volver al Detalle
            </a>
            <a href="admin_aprobaciones.php?usuario_id=<?= $registro['usuario_id'] ?>" class="btn btn-warning me-2">
                <i class="bi bi-check-square"></i> Aprobaciones
            </a>
            <a href="reportes_simple.php" class="btn btn-info me-2">
                <i class="bi bi-file-earmark-pdf"></i> Reportes
            </a>
            <a href="../menu_moderno.php" class="btn btn-primary">
                <i class="bi bi-house-door"></i> Menú Principal
            </a>
        </div>
        
        <div class="form-card">
            <h2 class="mb-4">Editar Registro de Asistencia</h2>
            
            <!-- Información del empleado -->
            <div class="info-section">
                <h5 class="mb-3"><i class="bi bi-person-badge"></i> Información del Empleado</h5>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Nombre:</strong> <?= htmlspecialchars($registro['nombre']) ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Email:</strong> <?= htmlspecialchars($registro['email']) ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Fecha:</strong> <?= date('d/m/Y', strtotime($registro['fecha'])) ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Secuencia:</strong> #<?= $registro['secuencia'] ?>
                    </div>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Hora de Entrada</label>
                            <input type="time" name="hora_entrada" class="form-control" 
                                   value="<?= $registro['hora_entrada'] ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Hora de Salida</label>
                            <input type="time" name="hora_salida" class="form-control" 
                                   value="<?= $registro['hora_salida'] ?>">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3"><?= htmlspecialchars($registro['observaciones'] ?? '') ?></textarea>
                </div>
                
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Nota:</strong> Al editar este registro, cambiará su estado a "Pendiente de Aprobación" y requerirá aprobación administrativa.
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                    <a href="detalle_empleado.php?usuario_id=<?= $registro['usuario_id'] ?>&mes=<?= date('m', strtotime($registro['fecha'])) ?>&anio=<?= date('Y', strtotime($registro['fecha'])) ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
