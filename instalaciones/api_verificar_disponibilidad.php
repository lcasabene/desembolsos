<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../config/database.php';

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);
$salon_id = $data['salon_id'] ?? '';
$fecha = $data['fecha'] ?? '';
$rango_horario_id = $data['rango_horario_id'] ?? '';

if (empty($salon_id) || empty($fecha) || empty($rango_horario_id)) {
    echo json_encode(['disponible' => false, 'error' => 'Faltan datos']);
    exit;
}

try {
    // Verificar si ya existe una reserva para ese salón, fecha y horario
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas 
                          WHERE salon_id = ? AND fecha = ? AND rango_horario_id = ? 
                          AND estado IN ('aprobada', 'pendiente')");
    $stmt->execute([$salon_id, $fecha, $rango_horario_id]);
    $reservas_existentes = $stmt->fetchColumn();

    // Verificar si es feriado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feriados 
                          WHERE fecha = ? OR (recurrente = 'anual' AND MONTH(fecha) = MONTH(?) AND DAY(fecha) = DAY(?))");
    $stmt->execute([$fecha, $fecha, $fecha]);
    $es_feriado = $stmt->fetchColumn();

    // Verificar si el salón está activo
    $stmt = $pdo->prepare("SELECT estado FROM salones WHERE id = ?");
    $stmt->execute([$salon_id]);
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    $disponible = true;
    $motivo = '';

    if ($reservas_existentes > 0) {
        $disponible = false;
        $motivo = 'El salón ya está reservado para ese horario';
    } elseif ($es_feriado > 0) {
        $disponible = false;
        $motivo = 'La fecha es un feriado';
    } elseif (!$salon || $salon['estado'] !== 'activo') {
        $disponible = false;
        $motivo = 'El salón no está disponible';
    }

    echo json_encode([
        'disponible' => $disponible,
        'motivo' => $motivo
    ]);

} catch (PDOException $e) {
    echo json_encode(['disponible' => false, 'error' => 'Error en la base de datos']);
}
?>
