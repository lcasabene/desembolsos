<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../config/database.php';

try {
    $usuario_id = $_SESSION['user_id'];
    $es_admin = $_SESSION['user_role'] === 'Admin';
    $hoy = date('Y-m-d');

    // Total de salones activos
    $stmt = $pdo->query("SELECT COUNT(*) FROM salones WHERE estado = 'activo'");
    $total_salones = $stmt->fetchColumn();

    // Reservas para hoy
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE fecha = ? AND estado = 'aprobada'");
    $stmt->execute([$hoy]);
    $reservas_hoy = $stmt->fetchColumn();

    // Reservas pendientes (solo para admin)
    if ($es_admin) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente'");
        $pendientes = $stmt->fetchColumn();
    } else {
        $pendientes = 0;
    }

    // Mis reservas activas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE usuario_id = ? AND estado IN ('aprobada', 'pendiente') AND fecha >= ?");
    $stmt->execute([$usuario_id, $hoy]);
    $mis_reservas = $stmt->fetchColumn();

    echo json_encode([
        'totalSalones' => (int)$total_salones,
        'reservasHoy' => (int)$reservas_hoy,
        'pendientes' => (int)$pendientes,
        'misReservas' => (int)$mis_reservas
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'totalSalones' => 0,
        'reservasHoy' => 0,
        'pendientes' => 0,
        'misReservas' => 0
    ]);
}
?>
