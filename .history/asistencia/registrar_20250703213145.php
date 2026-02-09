<?php
session_start();
require_once '../config/database.php';
require_once 'includes/funciones.php';

if (!isset($_SESSION['usuario_id']) || !tieneAccesoModulo('Asistencia')) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$usuario_id = $_SESSION['usuario_id'];
$fecha_hoy = date('Y-m-d');
$hora_actual = date('H:i:s');

$asistencia = obtenerAsistenciaDelDia($pdo, $usuario_id, $fecha_hoy);

if (isset($_POST['entrada']) && (!$asistencia || !$asistencia['hora_entrada'])) {
    $stmt = $pdo->prepare("INSERT INTO asistencias (usuario_id, fecha, hora_entrada) VALUES (?, ?, ?)");
    $stmt->execute([$usuario_id, $fecha_hoy, $hora_actual]);

    $id_asistencia = $pdo->lastInsertId();
    $log = $pdo->prepare("INSERT INTO asistencias_log (asistencia_id, accion, hora_nueva, realizado_por) VALUES (?, 'ENTRADA', ?, ?)");
    $log->execute([$id_asistencia, $hora_actual, $usuario_id]);
}

if (isset($_POST['salida']) && $asistencia && $asistencia['hora_entrada'] && !$asistencia['hora_salida']) {
    $stmt = $pdo->prepare("UPDATE asistencias SET hora_salida = ? WHERE id = ?");
    $stmt->execute([$hora_actual, $asistencia['id']]);

    $log = $pdo->prepare("INSERT INTO asistencias_log (asistencia_id, accion, hora_original, hora_nueva, realizado_por)
                          VALUES (?, 'SALIDA', ?, ?, ?)");
    $log->execute([$asistencia['id'], $asistencia['hora_salida'], $hora_actual, $usuario_id]);
}

header("Location: index.php");
exit;
?>
