<?php
require_once '../config/database.php';
$usuario_id = $_POST['usuario_id'];
$fecha = $_POST['fecha'];
$hora_entrada = $_POST['hora_entrada'] ?: null;
$hora_salida = $_POST['hora_salida'] ?: null;
$comentario = $_POST['comentario'];

$sql = "INSERT INTO asistencia_solicitudes (
            usuario_id, fecha, hora_entrada, hora_salida, comentario, estado
        ) VALUES (
            :usuario_id, :fecha, :hora_entrada, :hora_salida, :comentario, 'PENDIENTE'
        )";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':usuario_id' => $usuario_id,
    ':fecha' => $fecha,
    ':hora_entrada' => $hora_entrada,
    ':hora_salida' => $hora_salida,
    ':comentario' => $comentario
]);

header("Location: listado.php?msg=solicitud_enviada");
exit;
