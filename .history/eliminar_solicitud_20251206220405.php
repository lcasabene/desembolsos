<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once 'auth_helper.php';
verificar_acceso_modulo('Anticipos');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config/database.php'; // Debe definir $pdo (PDO)

$user_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
$isAdmin   = ($user_role === 'Admin');

// ID de la solicitud a eliminar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: listado_solicitudes.php?msg=" . urlencode("Parámetro inválido."));
    exit;
}

try {
    // 1) Buscar la solicitud
    $sql = "SELECT id, usuario_id, estado, 
                   CASE WHEN COLUMN_NAME IS NOT NULL THEN 1 ELSE 0 END AS tiene_activo
            FROM solicitudes s
            JOIN INFORMATION_SCHEMA.COLUMNS c 
              ON c.TABLE_SCHEMA = DATABASE()
             AND c.TABLE_NAME = 'solicitudes'
             AND c.COLUMN_NAME = 'activo'
            WHERE s.id = :id
            LIMIT 1";
    /**
     * 👆 Nota:
     * - Esto intenta verificar si existe la columna 'activo' de forma genérica.
     * - Si no querés complicarte, podés simplificar a:
     *   SELECT id, usuario_id, estado, activo FROM solicitudes WHERE id = :id
     *   y asumir que ya existe 'activo'.
     */

    // Para no complicar con INFORMATION_SCHEMA, uso una versión simple:
    $sql = "SELECT id, usuario_id, estado, 
                   IFNULL(activo, 1) AS activo
            FROM solicitudes
            WHERE id = :id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        header("Location: listado_solicitudes.php?msg=" . urlencode("La solicitud no existe."));
        exit;
    }

    $sol_usuario_id = (int)$solicitud['usuario_id'];
    $sol_estado     = $solicitud['estado'];

    // 2) Verificar permisos
    if (!$isAdmin) {
        // No admin: solo puede eliminar sus propias solicitudes pendientes
        if ($sol_usuario_id !== $user_id) {
            header("Location: listado_solicitudes.php?msg=" . urlencode("No tenés permiso para eliminar esta solicitud."));
            exit;
        }
        if ($sol_estado !== 'Pendiente') {
            header("Location: listado_solicitudes.php?msg=" . urlencode("Solo se pueden eliminar solicitudes en estado Pendiente."));
            exit;
        }
    }

    // 3) Eliminación lógica
    // Se asume que la tabla tiene 'activo' TINYINT(1) y 'fecha_baja' DATETIME (ajustá si tu esquema es distinto).
    $sqlDelete = "UPDATE solicitudes
                  SET activo = 0,
                      fecha_baja = NOW()
                  WHERE id = :id";

    $stmtDel = $pdo->prepare($sqlDelete);
    $stmtDel->execute([':id' => $id]);

    header("Location: listado_solicitudes.php?msg=" . urlencode("Solicitud eliminada correctamente (baja lógica)."));
    exit;

} catch (Exception $e) {
    // Si algo falla, logueamos y devolvemos un mensaje genérico
    error_log("Error en eliminar_solicitud.php (ID {$id}): " . $e->getMessage());
    header("Location: listado_solicitudes.php?msg=" . urlencode("Ocurrió un error al eliminar la solicitud."));
    exit;
}
