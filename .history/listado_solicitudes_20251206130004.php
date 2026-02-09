<?php
// listado_solicitudes.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_helper.php';
verificar_acceso_modulo('Anticipos');

require_once 'config/database.php'; // Debe definir $pdo (PDO)

// Datos de sesión
$user_id   = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? 'Usuario';
$isAdmin   = ($user_role === 'Admin');

// Filtro de estado (por GET). "Todos" = sin filtro.
$estado = $_GET['estado'] ?? 'Todos';

// 1) Armamos el SQL base
$sql = "SELECT  
            s.id,
            s.fecha_solicitud,
            s.monto,
            s.modalidad,
            s.alias_cbu,
            s.estado,
            s.usuario_id,
            s.departamento_id,
            d.nombre AS departamento,
            u.nombre AS usuario_nombre
        FROM solicitudes s
        LEFT JOIN departamentos d ON s.departamento_id = d.id
        LEFT JOIN usuarios u ON s.usuario_id = u.id";

$condiciones = [];
$params      = [];

// Siempre filtramos las activas
$condiciones[] = "s.activo = 1";

// Filtro por estado (si no es "Todos")
if ($estado !== 'Todos') {
    $condiciones[] = "s.estado = ?";
    $params[] = $estado;
}

// Si NO es admin, solo ve sus propias solicitudes
if (!$isAdmin) {
    $condiciones[] = "s.usuario_id = ?";
    $params[] = $user_id;
}

// Si hay condiciones, agregamos el WHERE
if (!empty($condiciones)) {
    $sql .= " WHERE " . implode(' AND ', $condiciones);
}

// Orden
$sql .= " ORDER BY s.fecha_solicitud DESC";

// Ejecutar consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Para mostrar el nombre del usuario logueado (si tenés el dato)
$nombre_usuario = $_SESSION['username'] ?? $_SESSION['nombre'] ?? 'usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Anticipos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
</head>
<body>

<?php
// Si tenés un menú general tipo menu.php, lo incluís acá.
// Si no, podés comentar esta línea.
// include 'menu.php';
?>

<div class="container mt-4">

    <h2 class="mb-3">Gestión de Anticipos</h2>
    <p>Bienvenido/a, <?php echo htmlspecialchars($nombre_usuario); ?>. Seleccioná una opción del menú.</p>

    <!-- Mensaje de resultado (por ej. de eliminar_solicitud.php) -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtro por estado -->
    <form class="row g-3 mb-3" method="get" action="listado_solicitudes.php">
        <div class="col-auto">
            <label for="estado" class="col-form-label">Estado:</label>
        </div>
        <div class="col-auto">
            <select name="estado" id="estado" class="form-select">
                <?php
                $estadosPosibles = ['Todos', 'Pendiente', 'Aprobado', 'Rechazado', 'Rendido'];
                foreach ($estadosPosibles as $e) {
                    $sel = ($estado === $e) ? 'selected' : '';
                    echo "<option value=\"{$e}\" {$sel}>{$e}</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
    </form>

    <table id="tabla-solicitudes" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th>          <!-- Columna 0 -->
                <th>Fecha</th>       <!-- Columna 1 -->
                <th>Usuario</th>     <!-- Columna 2 -->
                <th>Monto</th>       <!-- Columna 3 -->
                <th>Estado</th>      <!-- Columna 4 -->
                <th>Acciones</th>    <!-- Columna 5 -->
            </tr>
        </thead>
        <tbody>
            <?php foreach ($solicitudes as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['id']); ?></td>
                    <td><?php echo htmlspecialchars($s['fecha_solicitud']); ?></td>
                    <td><?php echo htmlspecialchars($s['usuario_nombre']); ?></td>
                    <td><?php echo '$ ' . number_format($s['monto'], 2, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($s['estado']); ?></td>
                    <td>
                        <a href="ver_solicitud.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-primary">Ver</a>

                        <?php
                        // Solo permitir editar si es Pendiente y:
                        // - Admin o
                        // - Usuario dueño de la solicitud
                        $puedeEditar = (
                            $s['estado'] === 'Pendiente' &&
                            ($isAdmin || (int)$s['usuario_id'] === $user_id)
                        );

                        if ($puedeEditar): ?>
                            <a href="editar_solicitud.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                        <?php endif; ?>

                        <?php
                        // Eliminar (baja lógica): reglas:
                        // - Admin: puede eliminar cualquier
                        // - No admin: solo sus pendientes (igual que editar)
                        $puedeEliminar = $isAdmin || $puedeEditar;

                        if ($puedeEliminar): ?>
                            <a href="eliminar_solicitud.php?id=<?php echo (int)$s['id']; ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('¿Seguro que deseas eliminar esta solicitud?');">
                                Eliminar
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    $('#tabla-solicitudes').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        },
        order: [[1, 'desc']] // orden por fecha
    });
});
</script>

</body>
</html>
