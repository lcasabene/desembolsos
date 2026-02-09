<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Usuario';
$isAdmin = ($user_role === 'Admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($id <= 0 || $action === '') {
    header("Location: prestamos_listado.php?msg=" . urlencode("Parámetros inválidos."));
    exit;
}

// 1. Traer préstamo (Corregido para PDO)
$sql = "SELECT * FROM prestamos_bienes WHERE id = ? AND activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]); // En PDO pasamos los parámetros directamente aquí
$prestamo = $stmt->fetch(PDO::FETCH_ASSOC); // Usamos fetch con estilo asociativo

if (!$prestamo) {
    // No hace falta close() explícito en PDO usualmente, pero liberamos la variable
    $stmt = null; 
    header("Location: prestamos_listado.php?msg=" . urlencode("Préstamo no encontrado."));
    exit;
}
$stmt = null; // Liberar recurso

$estado = $prestamo['estado'];
$solicitanteId = $prestamo['usuario_solicitante_id'];
// Asegurar conversión a booleano
$devPend = (int)$prestamo['devuelto_pendiente_aprobacion'] === 1;

$msg = "";

switch ($action) {

    case 'autorizar':
        if (!$isAdmin) {
            $msg = "No tiene permiso para autorizar.";
            break;
        }
        if ($estado !== 'pendiente') {
            $msg = "Solo se pueden autorizar préstamos pendientes.";
            break;
        }
        $sql = "UPDATE prestamos_bienes
                SET estado = 'autorizado', fecha_autorizacion = NOW(), usuario_autoriza_id = ?, updated_at = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        // El orden de los ? es: 1. usuario_autoriza_id, 2. id
        $stmt->execute([$user_id, $id]); 
        $msg = "Préstamo autorizado correctamente.";
        break;

    case 'rechazar':
        if (!$isAdmin) {
            $msg = "No tiene permiso para rechazar.";
            break;
        }
        if ($estado !== 'pendiente') {
            $msg = "Solo se pueden rechazar préstamos pendientes.";
            break;
        }
        $sql = "UPDATE prestamos_bienes
                SET estado = 'rechazado', updated_at = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $msg = "Préstamo rechazado.";
        break;

    case 'entregar':
        if (!$isAdmin) {
            $msg = "No tiene permiso para marcar como entregado.";
            break;
        }
        if ($estado !== 'autorizado') {
            $msg = "Solo se pueden marcar como entregados los préstamos autorizados.";
            break;
        }
        $sql = "UPDATE prestamos_bienes
                SET estado = 'entregado', fecha_entrega = NOW(), usuario_entrega_id = ?, updated_at = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $id]);
        $msg = "Préstamo marcado como entregado.";
        break;

    case 'solicitar_devolucion':
        // Solo solicitante, estado entregado, sin devolución pendiente
        if ($solicitanteId != $user_id) {
            $msg = "Solo el solicitante puede marcar la devolución.";
            break;
        }
        if ($estado !== 'entregado') {
            $msg = "Solo se pueden devolver préstamos entregados.";
            break;
        }
        if ($devPend) {
            $msg = "Ya hay una devolución pendiente de confirmación.";
            break;
        }
        $sql = "UPDATE prestamos_bienes
                SET devuelto_pendiente_aprobacion = 1,
                    fecha_devolucion_solicitada = NOW(),
                    usuario_devuelve_id = ?,
                    updated_at = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $id]);
        $msg = "Devolución solicitada. Pendiente de confirmación.";
        break;

    case 'confirmar_devolucion':
        if (!$isAdmin) {
            $msg = "No tiene permiso para confirmar la devolución.";
            break;
        }
        if ($estado !== 'entregado' || !$devPend) {
            $msg = "Solo se pueden confirmar devoluciones pendientes de préstamos entregados.";
            break;
        }
        $sql = "UPDATE prestamos_bienes
                SET estado = 'devuelto',
                    devuelto_pendiente_aprobacion = 0,
                    fecha_devolucion_confirmada = NOW(),
                    usuario_recibe_devolucion_id = ?,
                    updated_at = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $id]);
        $msg = "Devolución confirmada.";
        break;

    default:
        $msg = "Acción no reconocida.";
        break;
}

// Opcional: Cerrar conexión (PDO cierra automáticamente al acabar el script, pero puedes hacerlo manual)
$pdo = null;

header("Location: prestamos_listado.php?msg=" . urlencode($msg));
exit;