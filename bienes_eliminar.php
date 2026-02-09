<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion('Bienes');

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Usuario';
$isAdmin = ($user_role === 'Admin');

if (!isset($_GET['id'])) {
    header("Location: bienes_listado.php");
    exit;
}

$prestamo_id = intval($_GET['id']);

// Verificar que el préstamo existe y el usuario tiene permisos
$stmt = $pdo->prepare("SELECT * FROM prestamos_bienes WHERE id = ? AND activo = 1");
$stmt->execute([$prestamo_id]);
$prestamo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prestamo) {
    header("Location: bienes_listado.php?msg=Préstamo no encontrado");
    exit;
}

// Solo se pueden eliminar préstamos pendientes
if ($prestamo['estado'] !== 'pendiente') {
    header("Location: bienes_listado.php?msg=Solo se pueden eliminar préstamos pendientes");
    exit;
}

// Verificar permisos: solo el solicitante o admin pueden eliminar
if ($prestamo['usuario_solicitante_id'] != $user_id && !$isAdmin) {
    header("Location: bienes_listado.php?msg=Acceso denegado");
    exit;
}

try {
    // Eliminar lógicamente (marcar como inactivo)
    $stmt = $pdo->prepare("UPDATE prestamos_bienes SET activo = 0 WHERE id = ?");
    $stmt->execute([$prestamo_id]);
    
    header("Location: bienes_listado.php?msg=" . urlencode("Préstamo eliminado correctamente"));
    exit;

} catch (PDOException $e) {
    header("Location: bienes_listado.php?msg=Error: " . urlencode($e->getMessage()));
    exit;
}
?>
