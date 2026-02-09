<?php
session_start();
$user_id = $_SESSION['user_id']; // Asegurate que esté seteado

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Corrección</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">

    <h3>Solicitud de Corrección de Asistencia</h3>

    <form action="guardar_solicitud.php" method="post" class="mt-3">

        <input type="hidden" name="usuario_id" value="<?= $user_id ?>">

        <div class="mb-3">
            <label for="fecha" class="form-label">Fecha</label>
            <input type="date" name="fecha" id="fecha" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="hora_entrada" class="form-label">Hora de Entrada</label>
            <input type="time" name="hora_entrada" id="hora_entrada" class="form-control">
        </div>

        <div class="mb-3">
            <label for="hora_salida" class="form-label">Hora de Salida</label>
            <input type="time" name="hora_salida" id="hora_salida" class="form-control">
        </div>

        <div class="mb-3">
            <label for="comentario" class="form-label">Comentario</label>
            <textarea name="comentario" id="comentario" class="form-control" rows="3" required></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Enviar Solicitud</button>
    </form>

</body>
</html>
