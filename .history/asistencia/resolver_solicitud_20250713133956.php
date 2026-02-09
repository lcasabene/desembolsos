<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id'], $_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado");
}

$admin_id = $_SESSION['user_id'];
$solicitud_id = $_POST['solicitud_id'];
$accion = $_POST['accion'];

// Obtener la solicitud
$stmt = $pdo->prepare("SELECT * FROM asistencia_solicitudes_manual WHERE id = ?");
$stmt->execute([$solicitud_id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    die("Solicitud no encontrada");
}

if ($accion === 'rechazar') {
    $stmt = $pdo->prepare("UPDATE asistencia_solicitudes_manual 
        SET estado = 'RECHAZADA', fecha_respuesta = NOW(), resuelto_por = ? 
        WHERE id = ?");
    $stmt->execute([$admin_id, $solicitud_id]);
    header("Location: admin_solicitudes.php?msg=rechazada");
    exit;
}

// Aprobación: insertar o actualizar en asistencias
$usuario_id = $solicitud['usuario_id'];
$fecha = $solicitud['fecha'];
$hora_entrada = $solicitud['hora_entrada'];
$hora_salida = $solicitud['hora_salida'];

// Buscar si ya existe un registro
$stmt = $pdo->prepare("SELECT * FROM asistencias WHERE usuario_id = ? AND fecha = ?");
$stmt->execute([$usuario_id, $fecha]);
$registro = $stmt->fetch();

if ($registro) {
    // Actualizar
    $stmt = $pdo->prepare("UPDATE asistencias 
        SET hora_entrada = ?, hora_salida = ?, modificado_en = NOW(), modificado_por = ?
        WHERE id = ?");
    $stmt->execute([$hora_entrada, $hora_salida, $admin_id, $registro['id']]);

    // Log
    $stmt = $pdo->prepare("INSERT INTO asistencia_logs 
        (asistencia_id, accion, hora_original, hora_nueva, realizado_por) 
        VALUES (?, 'ENTRADA', ?, ?, ?)");
    $stmt->execute([$registro['id'], $registro['hora_entrada'], $hora_entrada, $admin_id]);

    $stmt = $pdo->prepare("INSERT INTO asistencia_logs 
        (asistencia_id, accion, hora_original, hora_nueva, realizado_por) 
        VALUES (?, 'SALIDA', ?, ?, ?)");
    $stmt->execute([$registro['id'], $registro['hora_salida'], $hora_salida, $admin_id]);

} else {
    // Insertar nuevo
    $stmt = $pdo->prepare("INSERT INTO asistencias 
        (usuario_id, fecha, hora_entrada, hora_salida, modificado_en, modificado_por) 
        VALUES (?, ?, ?, ?, NOW(), ?)");
    $stmt->execute([$usuario_id, $fecha, $hora_entrada, $hora_salida, $admin_id]);
}

// Marcar como aprobada
$stmt = $pdo->prepare("UPDATE asistencia_solicitudes_manual 
    SET estado = 'APROBADA', fecha_respuesta = NOW(), resuelto_por = ? 
    WHERE id = ?");
$stmt->execute([$admin_id, $solicitud_id]);

header("Location: admin_solicitudes.php?msg=aprobada");
