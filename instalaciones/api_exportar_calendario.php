<?php
header('Content-Type: text/calendar');
header('Content-Disposition: attachment; filename="calendario_instalaciones_' . date('Y-m-d') . '.ics"');

session_start();
if (!isset($_SESSION['user_id'])) {
    exit;
}

require_once '../config/database.php';

// Obtener parámetros
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
$salon_id = isset($_GET['salon_id']) ? intval($_GET['salon_id']) : 0;

// Validar mes y año
if ($mes < 1 || $mes > 12) $mes = date('n');
if ($anio < 2020 || $anio > 2030) $anio = date('Y');

// Obtener reservas del mes
$fecha_inicio = "$anio-$mes-01";
$fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

$where_salon = $salon_id > 0 ? "AND r.salon_id = $salon_id" : "";

$stmt = $pdo->prepare("
    SELECT r.*, s.nombre as salon_nombre, s.numero as salon_numero, 
           u.nombre as usuario_nombre
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.fecha BETWEEN ? AND ? AND r.estado = 'aprobada' $where_salon
    ORDER BY r.fecha, r.hora_inicio
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar archivo iCal
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Sistema de Instalaciones//Calendario de Reservas//ES\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";

foreach ($reservas as $reserva) {
    $fecha_inicio = $reserva['fecha'];
    $hora_inicio = $reserva['hora_inicio'];
    $hora_fin = $reserva['hora_fin'];
    
    $dtstart = $fecha_inicio . 'T' . str_replace(':', '', $hora_inicio) . '00';
    $dtend = $fecha_inicio . 'T' . str_replace(':', '', $hora_fin) . '00';
    $created = date('Ymd\THis', strtotime($reserva['created_at']));
    
    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . md5($reserva['id']) . "@instalaciones\r\n";
    echo "DTSTART:$dtstart\r\n";
    echo "DTEND:$dtend\r\n";
    echo "CREATED:$created\r\n";
    echo "SUMMARY:" . htmlspecialchars($reserva['salon_numero'] . ' - ' . $reserva['motivo']) . "\r\n";
    echo "DESCRIPTION:" . htmlspecialchars(
        "Salón: " . $reserva['salon_numero'] . " - " . $reserva['salon_nombre'] . "\\n" .
        "Horario: " . $hora_inicio . " - " . $hora_fin . "\\n" .
        "Usuario: " . $reserva['usuario_nombre'] . "\\n" .
        "Motivo: " . $reserva['motivo'] . 
        ($reserva['descripcion'] ? "\\n\\nDescripción: " . $reserva['descripcion'] : '')
    ) . "\r\n";
    echo "LOCATION:" . htmlspecialchars($reserva['salon_nombre']) . "\r\n";
    echo "STATUS:CONFIRMED\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";
?>
