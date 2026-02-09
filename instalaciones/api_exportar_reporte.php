<?php
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="reporte_instalaciones_' . date('Y-m-d') . '.xls"');

session_start();
if (!isset($_SESSION['user_id'])) {
    exit;
}

require_once '../config/database.php';

// Verificar si es admin
if ($_SESSION['user_role'] !== 'Admin') {
    exit;
}

// Obtener parámetros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$salon_id = $_GET['salon_id'] ?? '0';
$estado = $_GET['estado'] ?? 'todos';

// Construir cláusula WHERE
$where_clauses = ["1=1"];
$params = [];

if ($salon_id !== '0') {
    $where_clauses[] = "r.salon_id = ?";
    $params[] = $salon_id;
}

if ($estado !== 'todos') {
    $where_clauses[] = "r.estado = ?";
    $params[] = $estado;
}

$where_clause = implode(" AND ", $where_clauses);

// Obtener datos para exportar
$stmt = $pdo->prepare("
    SELECT 
        r.fecha,
        r.estado,
        r.motivo,
        r.descripcion,
        r.created_at as fecha_solicitud,
        r.fecha_aprobacion,
        r.observaciones,
        s.numero as salon_numero,
        s.nombre as salon_nombre,
        rh.nombre as horario_nombre,
        rh.hora_inicio,
        rh.hora_fin,
        u.nombre as usuario_nombre,
        u.email as usuario_email,
        IFNULL(ap.nombre, '') as aprobado_por
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    JOIN rangos_horarios rh ON r.rango_horario_id = rh.id
    JOIN usuarios u ON r.usuario_id = u.id
    LEFT JOIN usuarios ap ON r.aprobado_por = ap.id
    WHERE r.fecha BETWEEN ? AND ? AND $where_clause
    ORDER BY r.fecha DESC, rh.hora_inicio DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin, ...$params]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Crear archivo Excel
echo "<table border='1'>";
echo "<tr>";
echo "<th>Fecha</th>";
echo "<th>Salón</th>";
echo "<th>Horario</th>";
echo "<th>Usuario</th>";
echo "<th>Email Usuario</th>";
echo "<th>Motivo</th>";
echo "<th>Descripción</th>";
echo "<th>Estado</th>";
echo "<th>Fecha Solicitud</th>";
echo "<th>Fecha Aprobación</th>";
echo "<th>Aprobado Por</th>";
echo "<th>Observaciones</th>";
echo "</tr>";

foreach ($reservas as $reserva) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars(date('d/m/Y', strtotime($reserva['fecha']))) . "</td>";
    echo "<td>" . htmlspecialchars($reserva['salon_numero'] . ' - ' . $reserva['salon_nombre']) . "</td>";
    echo "<td>" . htmlspecialchars($reserva['horario_nombre'] . ' (' . $reserva['hora_inicio'] . ' - ' . $reserva['hora_fin'] . ')') . "</td>";
    echo "<td>" . htmlspecialchars($reserva['usuario_nombre']) . "</td>";
    echo "<td>" . htmlspecialchars($reserva['usuario_email']) . "</td>";
    echo "<td>" . htmlspecialchars($reserva['motivo']) . "</td>";
    echo "<td>" . htmlspecialchars($reserva['descripcion']) . "</td>";
    echo "<td>" . htmlspecialchars($reserva['estado']) . "</td>";
    echo "<td>" . htmlspecialchars(date('d/m/Y H:i', strtotime($reserva['fecha_solicitud']))) . "</td>";
    echo "<td>" . ($reserva['fecha_aprobacion'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($reserva['fecha_aprobacion']))) : '') . "</td>";
    echo "<td>" . htmlspecialchars($reserva['aprobado_por']) . "</td>";
    echo "<td>" . htmlspecialchars($reserva['observaciones']) . "</td>";
    echo "</tr>";
}

echo "</table>";
?>
