<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config/database.php';

$usuario_id = $_SESSION['user_id'];
$rol = $_SESSION['user_role'] ?? 'Solicitante';

// Encabezados para descarga de CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=solicitudes.csv');

// Abrir salida para el archivo
$output = fopen('php://output', 'w');

// Encabezados de las columnas
fputcsv($output, ['ID', 'Solicitante', 'Fecha Solicitud', 'Monto', 'Fecha Disponibilidad', 'Estado']);

// Obtener los datos
if ($rol === 'Admin' || $rol === 'Aprobador') {
    $stmt = $pdo->query("
        SELECT s.*, u.nombre 
        FROM solicitudes s
        JOIN usuarios u ON s.usuario_id = u.id
        ORDER BY s.fecha_solicitud DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, u.nombre 
        FROM solicitudes s
        JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.usuario_id = ?
        ORDER BY s.fecha_solicitud DESC
    ");
    $stmt->execute([$usuario_id]);
}

$solicitudes = $stmt->fetchAll();

// Escribir los datos en el archivo CSV
foreach ($solicitudes as $s) {
    fputcsv($output, [
        $s['id'],
        $s['nombre'],
        $s['fecha_solicitud'],
        number_format($s['monto'], 2),
        $s['fecha_disponibilidad'],
        $s['estado']
    ]);
}

fclose($output);
exit;
?>
