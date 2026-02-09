<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Usuario';
$isAdmin = ($user_role === 'Admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: prestamos_listado.php?msg=" . urlencode("Parámetros inválidos."));
    exit;
}

// Traer préstamo
$sql = "SELECT usuario_solicitante_id, estado FROM prestamos_bienes WHERE id = ? AND activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$row = $res->fetch_assoc()) {
    $stmt->close();
    header("Location: prestamos_listado.php?msg=" . urlencode("Préstamo no encontrado."));
    exit;
}
$stmt->close();

$esSolicitante = ($row['usuario_solicitante_id'] == $user_id);
$estado = $row['estado'];

// Solo Admin o solicitante, y solo estado pendiente (para solicitante)
if (!$isAdmin && (!$esSolicitante || $estado !== 'pendiente')) {
    header("Location: prestamos_listado.php?msg=" . urlencode("No tiene permiso para eliminar esta solicitud."));
    exit;
}

$sql = "UPDATE prestamos_bienes SET activo = 0, updated_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: prestamos_listado.php?msg=" . urlencode("Solicitud eliminada correctamente."));
exit;
