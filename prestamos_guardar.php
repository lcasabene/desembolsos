<?php
// prestamos_guardar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php'; // Aquí se define $pdo

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'Usuario';
$isAdmin   = ($user_role === 'Admin');

// Valores del formulario
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$cantidad                   = trim($_POST['cantidad'] ?? '');
$descripcion_bien          = trim($_POST['descripcion_bien'] ?? '');
$fecha_necesita_desde      = trim($_POST['fecha_necesita_desde'] ?? '');
$tiempo_tipo               = $_POST['tiempo_tipo'] ?? 'indeterminado';
$fecha_estimada_devolucion = trim($_POST['fecha_estimada_devolucion'] ?? null);
$observaciones             = trim($_POST['observaciones'] ?? null);

if ($tiempo_tipo !== 'determinado') {
    $tiempo_tipo = 'indeterminado';
    $fecha_estimada_devolucion = null;
}

// Validaciones
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

//////////////////////////////////////////////////////
//                 EDITAR PRÉSTAMO
//////////////////////////////////////////////////////
if ($id > 0) {
    try {
        // Verificar existencia y permisos
        $sql = "SELECT usuario_solicitante_id, estado 
                FROM prestamos_bienes 
                WHERE id = :id AND activo = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            die("Solicitud no encontrada.");
        }

        if (!$isAdmin && (int)$row['usuario_solicitante_id'] !== $user_id) {
            die("No tiene permiso para editar esta solicitud.");
        }

        if (!$isAdmin && $row['estado'] !== 'pendiente') {
            die("Solo se pueden editar solicitudes pendientes.");
        }

        // Actualizar
        $sql = "UPDATE prestamos_bienes
                SET cantidad = :cantidad,
                    descripcion_bien = :descripcion_bien,
                    fecha_necesita_desde = :fecha_necesita_desde,
                    tiempo_tipo = :tiempo_tipo,
                    fecha_estimada_devolucion = :fecha_estimada_devolucion,
                    observaciones = :observaciones,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cantidad'                 => $cantidad,
            ':descripcion_bien'         => $descripcion_bien,
            ':fecha_necesita_desde'     => $fecha_necesita_desde,
            ':tiempo_tipo'              => $tiempo_tipo,
            ':fecha_estimada_devolucion'=> $fecha_estimada_devolucion,
            ':observaciones'            => $observaciones,
            ':id'                       => $id
        ]);

        header("Location: prestamos_listado.php?msg=" . urlencode("Solicitud actualizada correctamente."));
        exit;

    } catch (Exception $e) {
        die("Error al actualizar: " . $e->getMessage());
    }
}

//////////////////////////////////////////////////////
//               CREAR NUEVA SOLICITUD
//////////////////////////////////////////////////////

try {
    $pdo->beginTransaction();

    // Obtener siguiente número correlativo
    $sqlMax = "SELECT MAX(nro_prestamo) AS max_nro FROM prestamos_bienes";
    $resMax = $pdo->query($sqlMax);
    $maxRow = $resMax->fetch(PDO::FETCH_ASSOC);

    $nextNro = ($maxRow && $maxRow['max_nro']) ? ((int)$maxRow['max_nro'] + 1) : 1;

    // Insertar
    $sql = "INSERT INTO prestamos_bienes
            (nro_prestamo, usuario_solicitante_id, fecha_solicitud, cantidad, descripcion_bien, fecha_necesita_desde,
             tiempo_tipo, fecha_estimada_devolucion, estado, devuelto_pendiente_aprobacion, observaciones, activo, created_at, updated_at)
            VALUES
            (:nro_prestamo, :usuario_solicitante_id, NOW(), :cantidad, :descripcion_bien, :fecha_necesita_desde,
             :tiempo_tipo, :fecha_estimada_devolucion, 'pendiente', 0, :observaciones, 1, NOW(), NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nro_prestamo'            => $nextNro,
        ':usuario_solicitante_id'  => $user_id,
        ':cantidad'                => $cantidad,
        ':descripcion_bien'        => $descripcion_bien,
        ':fecha_necesita_desde'    => $fecha_necesita_desde,
        ':tiempo_tipo'             => $tiempo_tipo,
        ':fecha_estimada_devolucion'=> $fecha_estimada_devolucion,
        ':observaciones'           => $observaciones
    ]);

    $nuevoId = $pdo->lastInsertId();

    $pdo->commit();

    header("Location: prestamos_listado.php?msg=" . urlencode("Solicitud creada correctamente. Nº " . $nextNro));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error al guardar: " . $e->getMessage());
}
