<?php
require_once 'auth_helper.php';
verificar_acceso_modulo('Anticipos');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config/database.php'; // Debe definir $pdo (PDO)

// --------- Filtros (Estado + Rol) ---------
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : 'Todos';

// Lista blanca de estados (ajustá si tenés más)
$estados_permitidos = ['Todos', 'Pendiente', 'Aprobado', 'Rechazado', 'Rendido'];
if (!in_array($estado, $estados_permitidos, true)) {
    $estado = 'Todos';
}

$user_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;

// --------- SQL base + condiciones ---------
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
        LEFT JOIN usuarios u ON s.usuario_id = u.id WHERE s.activo=1";

$condiciones = [];
$params      = [];

if ($estado !== 'Todos') {
    $condiciones[] = "s.estado = ?";
    $params[] = $estado;
}

if ($user_role !== 'Admin') {
    $condiciones[] = "s.usuario_id = ?";
    $params[] = $user_id;
}

if (!empty($condiciones)) {
    $sql .= " WHERE " . implode(' AND ', $condiciones);
}

$sql .= " ORDER BY s.fecha_solicitud DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Solicitudes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- DataTables + Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css">
    <!-- DataTables Buttons + Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.bootstrap5.min.css">

</head>
<body class="bg-light">
<div class="container py-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Listado de Solicitudes</h1>
        <div class="d-flex gap-2">
            <a href="menu.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left-circle"></i> Volver al menú
            </a>
            <a href="nueva_solicitud.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Nueva Solicitud
            </a>
        </div>
    </div>

    <form method="GET" class="row g-3 align-items-end mb-3">
        <div class="col-auto">
            <label for="estado" class="form-label mb-1">Filtrar por estado</label>
            <select name="estado" id="estado" class="form-select">
                <?php foreach ($estados_permitidos as $op): ?>
                    <option value="<?= htmlspecialchars($op) ?>" <?= $estado === $op ? 'selected' : '' ?>>
                        <?= htmlspecialchars($op) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary mt-3">
                <i class="bi bi-funnel"></i> Aplicar
            </button>
            <a href="listado_solicitudes.php" class="btn btn-outline-secondary mt-3">
                <i class="bi bi-arrow-counterclockwise"></i> Limpiar
            </a>
        </div>
        <div class="col-auto ms-auto">
            <span class="badge text-bg-info mt-3">
                Rol: <?= htmlspecialchars($user_role ?? 'Usuario') ?>
            </span>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tabla-solicitudes" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th style="width: 120px;">Fecha</th>
                        <th style="width: 180px;">Departamento</th>
                        <th style="width: 140px;" class="text-end">Monto</th>
                        <th style="width: 140px;">Modalidad</th>
                        <th style="width: 220px;">Alias/CBU</th>
                        <th style="width: 120px;">Estado</th>
                        <th style="width: 160px;" class="text-center">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($solicitudes)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox"></i> No se encontraron solicitudes con el filtro aplicado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($solicitudes as $s): ?>
                            <tr>
                                <td><?= (int)$s['id'] ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($s['fecha_solicitud']))) ?></td>
                                <td><?= htmlspecialchars($s['departamento'] ?? '—') ?></td>
                                <td class="text-end"><?= number_format((float)$s['monto'], 2, ',', '.') ?></td>
                                <td><?= htmlspecialchars($s['modalidad'] ?? '—') ?></td>
                                <td>
                                    <?= htmlspecialchars($s['alias_cbu'] ?? '—') ?>
                                    <?php if (!empty($s['alias_cbu'])): ?>
                                        <button class="btn btn-sm btn-outline-secondary ms-1" type="button"
                                                onclick="copiarCBU('<?= htmlspecialchars($s['alias_cbu']) ?>')"
                                                title="Copiar">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badge = match ($s['estado']) {
                                        'Pendiente' => 'warning',
                                        'Aprobado'  => 'success',
                                        'Rechazado' => 'danger',
                                        'Rendido'   => 'primary',
                                        default     => 'secondary',
                                    };
                                    ?>
                                    <span class="badge text-bg-<?= $badge ?>">
                                        <?= htmlspecialchars($s['estado']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a class="btn btn-outline-primary" href="ver_solicitud.php?id=<?= (int)$s['id'] ?>" title="Ver">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a class="btn btn-outline-secondary" href="editar_solicitud.php?id=<?= (int)$s['id'] ?>" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a class="btn btn-outline-danger" href="eliminar_solicitud.php?id=<?= (int)$s['id'] ?>"
                                           onclick="return confirm('¿Eliminar la solicitud #<?= (int)$s['id'] ?>?');"
                                           title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between small text-muted">
            <span>Total: <?= count($solicitudes) ?> registro(s)</span>
            <?php if ($user_role !== 'Admin'): ?>
                <span>Mostrando solo tus solicitudes</span>
            <?php else: ?>
                <span>Mostrando todas las solicitudes</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast para copias -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
        <div id="toastCopia" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    Texto copiado al portapapeles.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        </div>
    </div>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<!-- DataTables Buttons + export dependencies -->
<script src="https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.10/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.10/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.colVis.min.js"></script>
<script>
function copiarCBU(texto) {
    navigator.clipboard.writeText(texto).then(() => {
        const toast = new bootstrap.Toast(document.getElementById('toastCopia'));
        toast.show();
    });
}

$(function () {
    $('#tabla-solicitudes').DataTable({
        deferRender: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'desc']], // ordena por ID desc o ajustá si preferís otra cosa
        language: {
            url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/es-ES.json'
        },
        layout: {
            topStart: 'buttons',
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging'
        },
        buttons: [
            { extend: 'copyHtml5', text: 'Copiar' },
            { extend: 'excelHtml5', text: 'Excel' },
            { extend: 'csvHtml5', text: 'CSV' },
            {
                extend: 'pdfHtml5',
                text: 'PDF',
                orientation: 'landscape',
                pageSize: 'A4'
            },
            { extend: 'print', text: 'Imprimir' },
            { extend: 'colvis', text: 'Columnas' }
        ],
        columnDefs: [
            { targets: 0, type: 'num' },      // ID
            { targets: 3, type: 'num' },      // Monto
            { targets: 7, orderable: false }  // Acciones
        ]
    });
});
</script>
</body>
</html>
