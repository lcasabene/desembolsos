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
$hora_inicio = $data['hora_inicio'] ?? '';
$hora_fin = $data['hora_fin'] ?? '';

if (empty($salon_id) || empty($fecha) || empty($hora_inicio) || empty($hora_fin)) {
    echo json_encode(['disponible' => false, 'motivo' => 'Faltan datos']);
    exit;
}

try {
    $disponible = true;
    $motivo = '';

    // Verificar si el salón está activo
    $stmt = $pdo->prepare("SELECT estado FROM salones WHERE id = ?");
    $stmt->execute([$salon_id]);
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salon || $salon['estado'] !== 'activo') {
        echo json_encode(['disponible' => false, 'motivo' => 'El salón no está disponible']);
        exit;
    }

    // Verificar si es feriado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feriados 
                          WHERE fecha = ? OR (recurrente = 'anual' AND MONTH(fecha) = MONTH(?) AND DAY(fecha) = DAY(?))");
    $stmt->execute([$fecha, $fecha, $fecha]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['disponible' => false, 'motivo' => 'La fecha es un feriado']);
        exit;
    }

    // Verificar conflictos de horario (overlap por minutos)
    $inicio_min = (int)substr($hora_inicio, 0, 2) * 60 + (int)substr($hora_inicio, 3, 2);
    $fin_min = (int)substr($hora_fin, 0, 2) * 60 + (int)substr($hora_fin, 3, 2);

    $stmt = $pdo->prepare("
        SELECT r.hora_inicio, r.hora_fin, r.estado, u.nombre as usuario_nombre
        FROM reservas r
        JOIN usuarios u ON r.usuario_id = u.id
        WHERE r.salon_id = ? AND r.fecha = ? AND r.estado IN ('aprobada', 'pendiente')
        ORDER BY r.hora_inicio
    ");
    $stmt->execute([$salon_id, $fecha]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reservas as $reserva) {
        $ex_inicio = (int)substr($reserva['hora_inicio'], 0, 2) * 60 + (int)substr($reserva['hora_inicio'], 3, 2);
        $ex_fin = (int)substr($reserva['hora_fin'], 0, 2) * 60 + (int)substr($reserva['hora_fin'], 3, 2);

        if (($inicio_min < $ex_fin) && ($fin_min > $ex_inicio)) {
            $motivo = "Conflicto: reserva {$reserva['estado']} de {$reserva['hora_inicio']} a {$reserva['hora_fin']} ({$reserva['usuario_nombre']})";
            echo json_encode(['disponible' => false, 'motivo' => $motivo]);
            exit;
        }
    }

    echo json_encode(['disponible' => true, 'motivo' => '']);

} catch (PDOException $e) {
    echo json_encode(['disponible' => false, 'motivo' => 'Error en la base de datos']);
}
?>
