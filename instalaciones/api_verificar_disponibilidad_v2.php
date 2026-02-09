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
    echo json_encode(['disponible' => false, 'error' => 'Faltan datos']);
    exit;
}

try {
    // Verificar si ya existe una reserva para ese salón, fecha y horario
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservas 
        WHERE salon_id = ? AND fecha = ? 
        AND ((hora_inicio <= ? AND hora_fin > ?) OR (hora_inicio < ? AND hora_fin >= ?))
        AND estado IN ('aprobada', 'pendiente')
    ");
    $stmt->execute([$salon_id, $fecha, $hora_inicio, $hora_inicio, $hora_fin, $hora_fin]);
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

    // Obtener configuración
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_instalaciones");
    $config = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config[$row['parametro']] = $row['valor'];
    }

    // Validar duración máxima
    $inicio = new DateTime($hora_inicio);
    $fin = new DateTime($hora_fin);
    $duracion = ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
    $excede_duracion = $duracion > ($config['max_duracion_reserva'] ?? 4);

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
    } elseif ($excede_duracion) {
        $disponible = false;
        $motivo = 'Excede la duración máxima permitida';
    }

    echo json_encode([
        'disponible' => $disponible,
        'motivo' => $motivo,
        'duracion' => $duracion,
        'duracion_maxima' => $config['max_duracion_reserva'] ?? 4
    ]);

} catch (PDOException $e) {
    echo json_encode(['disponible' => false, 'error' => 'Error en la base de datos']);
}
?>
