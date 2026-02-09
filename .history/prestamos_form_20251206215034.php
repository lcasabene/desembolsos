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

$cantidad = '';
$descripcion_bien = '';
$fecha_necesita_desde = '';
$tiempo_tipo = 'indeterminado';
$fecha_estimada_devolucion = '';
$observaciones = '';

$editando = false;

if ($id > 0) {
    $sql = "SELECT * FROM prestamos_bienes WHERE id = ? AND activo = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        // Validar permisos: solo Admin o el solicitante mientras está pendiente
        if (!$isAdmin && $row['usuario_solicitante_id'] != $user_id) {
            die("No tiene permiso para editar esta solicitud.");
        }
        if (!$isAdmin && $row['estado'] !== 'pendiente') {
            die("Solo se pueden editar solicitudes pendientes.");
        }

        $editando = true;
        $cantidad = $row['cantidad'];
        $descripcion_bien = $row['descripcion_bien'];
        $fecha_necesita_desde = $row['fecha_necesita_desde'];
        $tiempo_tipo = $row['tiempo_tipo'];
        $fecha_estimada_devolucion = $row['fecha_estimada_devolucion'];
        $observaciones = $row['observaciones'];
    } else {
        die("Solicitud no encontrada.");
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $editando ? 'Editar' : 'Nueva'; ?> solicitud de préstamo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'menu_instalaciones.php'; ?>

<div class="container mt-4">
    <h3><?php echo $editando ? 'Editar' : 'Nueva'; ?> solicitud de préstamo</h3>

    <form action="prestamos_guardar.php" method="post">
        <input type="hidden" name="id" value="<?php echo $editando ? (int)$id : 0; ?>">

        <div class="mb-3">
            <label for="cantidad" class="form-label">Cantidad</label>
            <input type="number" step="0.01" min="0" name="cantidad" id="cantidad" class="form-control" required
                   value="<?php echo htmlspecialchars($cantidad); ?>">
        </div>

        <div class="mb-3">
            <label for="descripcion_bien" class="form-label">Descripción del bien</label>
            <textarea name="descripcion_bien" id="descripcion_bien" class="form-control" rows="3" required><?php echo htmlspecialchars($descripcion_bien); ?></textarea>
        </div>

        <div class="mb-3">
            <label for="fecha_necesita_desde" class="form-label">Fecha en que necesita estar disponible</label>
            <input type="date" name="fecha_necesita_desde" id="fecha_necesita_desde" class="form-control" required
                   value="<?php echo htmlspecialchars($fecha_necesita_desde); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Tiempo</label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="tiempo_tipo" id="tiempo_ind" value="indeterminado"
                    <?php echo ($tiempo_tipo === 'indeterminado') ? 'checked' : ''; ?>>
                <label class="form-check-label" for="tiempo_ind">
                    Indeterminado
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="tiempo_tipo" id="tiempo_det" value="determinado"
                    <?php echo ($tiempo_tipo === 'determinado') ? 'checked' : ''; ?>>
                <label class="form-check-label" for="tiempo_det">
                    Determinado
                </label>
            </div>
        </div>

        <div class="mb-3" id="grupo_fecha_devolucion">
            <label for="fecha_estimada_devolucion" class="form-label">Fecha estimada de devolución</label>
            <input type="date" name="fecha_estimada_devolucion" id="fecha_estimada_devolucion" class="form-control"
                   value="<?php echo htmlspecialchars($fecha_estimada_devolucion); ?>">
            <div class="form-text">Opcional si el tiempo es indeterminado.</div>
        </div>

        <div class="mb-3">
            <label for="observaciones" class="form-label">Observaciones</label>
            <textarea name="observaciones" id="observaciones" class="form-control" rows="3"><?php echo htmlspecialchars($observaciones); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="prestamos_listado.php" class="btn btn-secondary">Volver</a>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
function toggleFechaDevolucion() {
    const tipo = document.querySelector('input[name="tiempo_tipo"]:checked').value;
    const grupo = document.getElementById('grupo_fecha_devolucion');
    if (tipo === 'indeterminado') {
        grupo.style.display = 'none';
    } else {
        grupo.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleFechaDevolucion();
    document.querySelectorAll('input[name="tiempo_tipo"]').forEach(function(radio) {
        radio.addEventListener('change', toggleFechaDevolucion);
    });
});
</script>
</body>
</html>
