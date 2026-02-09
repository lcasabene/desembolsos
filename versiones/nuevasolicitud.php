<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['user_id'];
    $fecha_solicitud = date('Y-m-d');
    $monto = $_POST['monto'];
    $fecha_disponibilidad = $_POST['fecha_disponibilidad'];

    $stmt = $pdo->prepare("INSERT INTO solicitudes (usuario_id, fecha_solicitud, monto, fecha_disponibilidad) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$usuario_id, $fecha_solicitud, $monto, $fecha_disponibilidad])) {
        header("Location: listado_solicitudes.php");
    } else {
        $error = "Error al registrar la solicitud.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Nueva Solicitud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>Registrar Nueva Solicitud</h2>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label>Monto Solicitado</label>
            <input type="number" name="monto" class="form-control" required step="0.01" min="0">
        </div>
        <div class="mb-3">
            <label>Fecha de Disponibilidad Requerida</label>
            <input type="date" name="fecha_disponibilidad" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Registrar Solicitud</button>
        <a href="dashboard.php" class="btn btn-link">Volver al Dashboard</a>
    </form>
</body>
</html>
