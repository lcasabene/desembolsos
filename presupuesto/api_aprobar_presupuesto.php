<?php
require_once __DIR__ . '/../config/seguridad.php';
verificar_autenticacion();

require_once __DIR__ . '/../config/database.php';

// Solo administradores pueden aprobar presupuestos
if ($_SESSION['user_role'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $presupuesto_id = $data['id'] ?? 0;
    $usuario_id = $_SESSION['user_id'] ?? 0;

    if (empty($presupuesto_id)) {
        throw new Exception('ID de presupuesto no válido');
    }

    // Verificar que el presupuesto existe y está en estado "enviado_aprobacion"
    $stmt = $pdo->prepare("SELECT id, estado FROM presupuestos_anuales WHERE id = ?");
    $stmt->execute([$presupuesto_id]);
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$presupuesto) {
        throw new Exception('Presupuesto no encontrado');
    }

    if ($presupuesto['estado'] !== 'enviado_aprobacion') {
        throw new Exception('El presupuesto no está en estado de "Enviado para Aprobación"');
    }

    // Actualizar estado del presupuesto
    $stmt = $pdo->prepare("
        UPDATE presupuestos_anuales 
        SET estado = 'aprobado', 
            aprobado_por = ?, 
            fecha_aprobacion = CURRENT_TIMESTAMP,
            actualizado_por = ?,
            fecha_actualizacion = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$usuario_id, $usuario_id, $presupuesto_id]);

    echo json_encode([
        'success' => true, 
        'message' => 'Presupuesto aprobado exitosamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
