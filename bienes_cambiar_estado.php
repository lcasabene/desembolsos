<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion('Bienes');

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Usuario';
$isAdmin = ($user_role === 'Admin');

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header("Location: bienes_listado.php");
    exit;
}

$prestamo_id = intval($_GET['id']);
$action = $_GET['action'];

// Verificar que el préstamo existe
$stmt = $pdo->prepare("SELECT * FROM prestamos_bienes WHERE id = ? AND activo = 1");
$stmt->execute([$prestamo_id]);
$prestamo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prestamo) {
    header("Location: bienes_listado.php?msg=Préstamo no encontrado");
    exit;
}

// Verificar permisos según la acción
switch ($action) {
    case 'autorizar':
    case 'rechazar':
    case 'entregar':
    case 'confirmar_devolucion':
        if (!$isAdmin) {
            header("Location: bienes_listado.php?msg=Acceso denegado");
            exit;
        }
        break;
    case 'solicitar_devolucion':
        if ($prestamo['usuario_solicitante_id'] != $user_id) {
            header("Location: bienes_listado.php?msg=Acceso denegado");
            exit;
        }
        break;
    default:
        header("Location: bienes_listado.php?msg=Acción no válida");
        exit;
}

try {
    switch ($action) {
        case 'autorizar':
            $stmt = $pdo->prepare("UPDATE prestamos_bienes SET estado = 'autorizado' WHERE id = ?");
            $stmt->execute([$prestamo_id]);
            $msg = "Préstamo autorizado correctamente";
            break;

        case 'rechazar':
            $stmt = $pdo->prepare("UPDATE prestamos_bienes SET estado = 'rechazado' WHERE id = ?");
            $stmt->execute([$prestamo_id]);
            $msg = "Préstamo rechazado";
            break;

        case 'entregar':
            $stmt = $pdo->prepare("UPDATE prestamos_bienes SET estado = 'entregado', fecha_entrega = NOW(), usuario_entrega_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id, $prestamo_id]);
            $msg = "Préstamo entregado correctamente";
            break;

        case 'solicitar_devolucion':
            $stmt = $pdo->prepare("UPDATE prestamos_bienes SET devuelto_pendiente_aprobacion = 1, fecha_devolucion_solicitada = NOW(), usuario_devuelve_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id, $prestamo_id]);
            $msg = "Solicitud de devolución registrada";
            break;

        case 'confirmar_devolucion':
            $stmt = $pdo->prepare("UPDATE prestamos_bienes SET estado = 'devuelto', devuelto_pendiente_aprobacion = 0, fecha_devolucion_confirmada = NOW(), usuario_recibe_devolucion_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id, $prestamo_id]);
            $msg = "Devolución confirmada correctamente";
            break;
    }

    header("Location: bienes_listado.php?msg=" . urlencode($msg));
    exit;

} catch (PDOException $e) {
    header("Location: bienes_listado.php?msg=Error: " . urlencode($e->getMessage()));
    exit;
}
?>
