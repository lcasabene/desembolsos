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

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$cantidad = isset($_POST['cantidad']) ? trim($_POST['cantidad']) : '';
$descripcion_bien = isset($_POST['descripcion_bien']) ? trim($_POST['descripcion_bien']) : '';
$fecha_necesita_desde = isset($_POST['fecha_necesita_desde']) ? trim($_POST['fecha_necesita_desde']) : '';
$tiempo_tipo = isset($_POST['tiempo_tipo']) ? $_POST['tiempo_tipo'] : 'indeterminado';
$fecha_estimada_devolucion = isset($_POST['fecha_estimada_devolucion']) ? trim($_POST['fecha_estimada_devolucion']) : null;
$observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : null;

if ($tiempo_tipo !== 'determinado') {
    $tiempo_tipo = 'indeterminado';
    $fecha_estimada_devolucion = null;
}

// Validaciones básicas
if ($cantidad === '' || !is_numeric($cantidad) || $cantidad <= 0) {
    header("Location: prestamos_form.php?id={$id}&msg=" . urlencode("Cantidad inválida."));
    exit;
}
if ($descripcion_bien === '') {
    header("Location: prestamos_form.php?id={$id}&msg=" . urlencode("Debe indicar la descripción del bien."));
    exit;
}
if ($fecha_necesita_desde === '') {
    header("Location: prestamos_form.php?id={$id}&msg=" . urlencode("Debe indicar la fecha en que se necesita el bien."));
    exit;
}

if ($id > 0) {
    // EDITAR: solo solicitante o Admin, y estado pendiente
    $sql = "SELECT usuario_solicitante_id, estado FROM prestamos_bienes WHERE id = ? AND activo = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$row = $res->fetch_assoc()) {
        die("Solicitud no encontrada.");
    }
    $stmt->close();

    if (!$isAdmin && $row['usuario_solicitante_id'] != $user_id) {
        die("No tiene permiso para editar esta solicitud.");
    }
    if (!$isAdmin && $row['estado'] !== 'pendiente') {
        die("Solo se pueden editar solicitudes pendientes.");
    }

    $sql = "UPDATE prestamos_bienes
            SET cantidad = ?, descripcion_bien = ?, fecha_necesita_desde = ?, tiempo_tipo = ?, fecha_estimada_devolucion = ?, observaciones = ?, updated_at = NOW()
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "dsssssi",
        $cantidad,
        $descripcion_bien,
        $fecha_necesita_desde,
        $tiempo_tipo,
        $fecha_estimada_devolucion,
        $observaciones,
        $id
    );
    $stmt->execute();
    $stmt->close();

    header("Location: prestamos_listado.php?msg=" . urlencode("Solicitud actualizada correctamente."));
    exit;

} else {
    // NUEVO: generar nro_prestamo
    $pdo->begin_transaction();

   try {
        $sqlMax = "SELECT MAX(nro_prestamo) AS max_nro FROM prestamos_bienes";
        $resMax = $pdo->query($sqlMax);
        $maxRow = $resMax->fetch_assoc();
        $nextNro = $maxRow['max_nro'] ? ($maxRow['max_nro'] + 1) : 1;

        $sql = "INSERT INTO prestamos_bienes
                (nro_prestamo, usuario_solicitante_id, fecha_solicitud, cantidad, descripcion_bien, fecha_necesita_desde,
                 tiempo_tipo, fecha_estimada_devolucion, estado, devuelto_pendiente_aprobacion, observaciones, activo, created_at, updated_at)
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, 'pendiente', 0, ?, 1, NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        // tipos: i = int, d = double, s = string
        $stmt->bind_param(
            "iidsssss",
            $nextNro,
            $user_id,
            $cantidad,
            $descripcion_bien,
            $fecha_necesita_desde,
            $tiempo_tipo,
            $fecha_estimada_devolucion,
            $observaciones
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        header("Location: prestamos_listado.php?msg=" . urlencode("Solicitud creada correctamente. Nº " . $nextNro));
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die("Error al guardar: " . $e->getMessage());
    }
}
