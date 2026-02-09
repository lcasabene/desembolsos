<?php
require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/database.php';
verificar_autenticacion('Colaboradores');

$usuario_id = $_SESSION['user_id'];
$registro_id = $_GET['id'] ?? 0;

// Obtener registro
$stmt = $pdo->prepare("SELECT * FROM asistencia WHERE id = ? AND usuario_id = ?");
$stmt->execute([$registro_id, $usuario_id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registro) {
    header('Location: index.php');
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
        
        $valores_nuevos = [
            'hora_entrada' => $hora_entrada ?: null,
            'hora_salida' => $hora_salida ?: null,
            'observaciones' => $observaciones
        ];
        
        // Actualizar registro y cambiar estado a pendiente
        $stmt = $pdo->prepare("
            UPDATE asistencia 
            SET hora_entrada = ?, hora_salida = ?, observaciones = ?, 
                estado = 'pendiente_aprobacion', editado_por = ?, 
                fecha_edicion = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$hora_entrada, $hora_salida, $observaciones, $usuario_id, $registro_id]);
        
        // Registrar en auditoría
        $stmt = $pdo->prepare("
            INSERT INTO asistencia_auditoria (asistencia_id, usuario_id, accion, valor_anterior, valor_nuevo, ip_address, user_agent) 
            VALUES (?, ?, 'editar', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $registro_id, 
            $usuario_id, 
            json_encode($valores_originales),
            json_encode($valores_nuevos),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        header('Location: index.php?success=editado');
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
    <title>Editar Registro - Sistema de Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .form-container { max-width: 600px; margin: 0 auto; }
        .form-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="form-container">
        <!-- Botones de navegación -->
        <div class="mb-3">
            <a href="index.php" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Volver a Mi Registro
            </a>
            <a href="../menu_moderno.php" class="btn btn-primary">
                <i class="bi bi-house-door"></i> Menú Principal
            </a>
        </div>
        
        <div class="form-card">
            <h2 class="mb-4">Editar Registro de Asistencia</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Fecha</label>
                    <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($registro['fecha'])) ?>" readonly>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Hora de Entrada</label>
                    <input type="time" name="hora_entrada" class="form-control" 
                           value="<?= $registro['hora_entrada'] ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Hora de Salida</label>
                    <input type="time" name="hora_salida" class="form-control" 
                           value="<?= $registro['hora_salida'] ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3"><?= htmlspecialchars($registro['observaciones']) ?></textarea>
                </div>
                
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Importante:</strong> Al editar este registro, cambiará a estado "pendiente de aprobación" 
                    y no será computado en los resúmenes hasta que un administrador lo apruebe.
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-x"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
