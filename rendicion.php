<?php
require_once 'auth_helper.php';
verificar_acceso_modulo('Anticipos');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Solicitante') {
    header("Location: login.php");
    exit;
}

// Mostrar errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config/database.php';

// Obtener solicitudes aprobadas y no rendidas
try {
    $stmt = $pdo->prepare("
        SELECT id, monto 
        FROM solicitudes 
        WHERE usuario_id = ? AND estado = 'Aprobado'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $solicitudes = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al consultar las solicitudes: " . $e->getMessage());
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }

        $solicitud_id = $_POST['solicitud_id'];
        $fecha_rendicion = date('Y-m-d');
        $observaciones = $_POST['observaciones'] ?? '';
        $reintegro = $_POST['reintegro'] ?? 'No';
        $monto_reintegro = $_POST['monto_reintegro'] ?? 0;

        // Insertar rendición con estado inicial 'Completo'
        $stmt = $pdo->prepare("
            INSERT INTO rendiciones (solicitud_id, fecha_rendicion, observaciones, estado_rendicion)
            VALUES (?, ?, ?, 'Completo')
        ");
        $stmt->execute([$solicitud_id, $fecha_rendicion, $observaciones]);
        $rendicion_id = $pdo->lastInsertId();

        // Insertar comprobantes
        for ($i = 0; $i < 10; $i++) {
            if (!empty($_FILES['archivo']['name'][$i])) {
                $concepto = $_POST['concepto'][$i] ?? '';
                $monto = $_POST['monto_comprobante'][$i] ?? 0;
                $archivo_nombre = basename($_FILES['archivo']['name'][$i]);
                $destino = 'uploads/' . uniqid() . '_' . $archivo_nombre;

                if (move_uploaded_file($_FILES['archivo']['tmp_name'][$i], $destino)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO comprobantes (rendicion_id, tipo_comprobante, nro_comprobante, importe, archivo)
                        VALUES (?, ?, '', ?, ?)
                    ");
                    $stmt->execute([$rendicion_id, $concepto, $monto, $destino]);
                } else {
                    die("Error al mover el archivo: " . $archivo_nombre);
                }
            }
        }

        // Insertar reintegro si corresponde
        if ($reintegro === 'Si' && !empty($_FILES['archivo_reintegro']['name'])) {
            $archivo_reintegro = basename($_FILES['archivo_reintegro']['name']);
            $destino_reintegro = 'uploads/' . uniqid() . '_' . $archivo_reintegro;

            if (move_uploaded_file($_FILES['archivo_reintegro']['tmp_name'], $destino_reintegro)) {
                $stmt = $pdo->prepare("
                    INSERT INTO comprobantes (rendicion_id, tipo_comprobante, nro_comprobante, importe, archivo)
                    VALUES (?, 'Reintegro', '', ?, ?)
                ");
                $stmt->execute([$rendicion_id, $monto_reintegro, $destino_reintegro]);
            } else {
                die("Error al mover el archivo de reintegro.");
            }
        }

        // Validar montos
        $stmt = $pdo->prepare("SELECT monto FROM solicitudes WHERE id = ?");
        $stmt->execute([$solicitud_id]);
        $monto_solicitado = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT SUM(importe) FROM comprobantes WHERE rendicion_id = ?");
        $stmt->execute([$rendicion_id]);
        $total_rendido = $stmt->fetchColumn() ?: 0;

        $estado_rendicion = 'Completo';
        if ($total_rendido > $monto_solicitado) {
            $estado_rendicion = 'Sobrante';
        } elseif ($total_rendido < $monto_solicitado) {
            $estado_rendicion = 'Faltante';
        }

        // Actualizar estado rendición
        $stmt = $pdo->prepare("
            UPDATE rendiciones SET estado_rendicion = ? WHERE id = ?
        ");
        $stmt->execute([$estado_rendicion, $rendicion_id]);

        // Marcar solicitud como rendida
        $stmt = $pdo->prepare("UPDATE solicitudes SET estado = 'Rendido' WHERE id = ?");
        $stmt->execute([$solicitud_id]);

        header("Location: menu.php");
        exit;
    } catch (PDOException $e) {
        die("Error al grabar la rendición: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rendir Comprobantes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>Rendir Comprobantes</h2>
    <a href="menu.php" class="btn btn-secondary mb-3">Volver al Menú</a>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label>Solicitud Asociada</label>
            <select name="solicitud_id" class="form-select" required>
                <?php foreach ($solicitudes as $s): ?>
                    <option value="<?= $s['id'] ?>">Solicitud #<?= $s['id'] ?> - Monto: $<?= number_format($s['monto'], 2) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <h5>Comprobantes (hasta 10)</h5>
        <?php for ($i = 0; $i < 10; $i++): ?>
            <div class="row mb-3 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="concepto[]" class="form-control" placeholder="Concepto">
                </div>
                <div class="col-md-3">
                    <input type="number" name="monto_comprobante[]" step="0.01" min="0" class="form-control" placeholder="Monto">
                </div>
                <div class="col-md-5">
                    <input type="file" name="archivo[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </div>
        <?php endfor; ?>

        <h5>Reintegro</h5>
        <div class="mb-3">
            <label>¿Corresponde reintegro?</label>
            <select name="reintegro" class="form-select">
                <option value="No">No</option>
                <option value="Si">Sí</option>
            </select>
        </div>
        <div class="mb-3">
            <label>Monto de Reintegro</label>
            <input type="number" name="monto_reintegro" step="0.01" min="0" class="form-control" placeholder="Ingrese el monto del reintegro">
        </div>
        <div class="mb-3">
            <label>Adjuntar Comprobante de Reintegro (si corresponde)</label>
            <input type="file" name="archivo_reintegro" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="mb-3">
            <label>Observaciones</label>
            <textarea name="observaciones" class="form-control" rows="3"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Registrar Rendición</button>
    </form>
</body>
</html>
