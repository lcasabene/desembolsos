<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php'; // Debe definir $pdo (PDO)

$errores = [];

// --- Alta rápida / creación de usuario desde este listado ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_usuario'])) {
    $nombre   = trim($_POST['nombre'] ?? '');
    $dni      = trim($_POST['dni'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $rol      = trim($_POST['rol'] ?? 'Solicitante');
    $monto_aprobacion = isset($_POST['monto_aprobacion']) ? str_replace(['.', ','], ['', '.'], $_POST['monto_aprobacion']) : '0';
    $monto_aprobacion = is_numeric($monto_aprobacion) ? (float)$monto_aprobacion : 0.0;
    $modulos = isset($_POST['modulos']) && is_array($_POST['modulos']) ? $_POST['modulos'] : [];

    if ($nombre === '')   $errores[] = 'El nombre es obligatorio.';
    if ($email === '')    $errores[] = 'El usuario/email es obligatorio.';
    if ($password === '') $errores[] = 'La contraseña es obligatoria.';
    if (!in_array($rol, ['Solicitante', 'Aprobador', 'Admin'], true)) {
        $errores[] = 'Rol inválido.';
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nombre, dni, email, password, rol, monto_aprobacion, activo)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$nombre, $dni, $email, $hash, $rol, $monto_aprobacion]);
            $nuevoId = (int)$pdo->lastInsertId();

            if (!empty($modulos)) {
                $ins = $pdo->prepare("INSERT INTO usuario_modulos (usuario_id, modulo) VALUES (?, ?)");
                foreach ($modulos as $modulo) {
                    $ins->execute([$nuevoId, $modulo]);
                }
            }

            $pdo->commit();
            header("Location: usuarios.php");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = 'Error al crear usuario: ' . $e->getMessage();
        }
    }
}

// --- Acciones de activar / desactivar (eliminar lógico) ---
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: usuarios.php");
    exit;
}

if (isset($_GET['activar'])) {
    $id = (int)$_GET['activar'];
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: usuarios.php");
    exit;
}

// --- Obtener lista de módulos disponibles desde la tabla modulos ---
try {
    $stmt_mod = $pdo->query("SELECT nombre FROM modulos ORDER BY nombre");
    $modulos_disponibles = $stmt_mod->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $modulos_disponibles = ['Anticipos', 'Rendiciones', 'Reportes', 'Administración'];
}

// --- Obtener lista de usuarios con sus módulos (GROUP_CONCAT) ---
$sqlUsuarios = "
    SELECT 
        u.*,
        GROUP_CONCAT(um.modulo ORDER BY um.modulo SEPARATOR ', ') AS modulos
    FROM usuarios u
    LEFT JOIN usuario_modulos um ON u.id = um.usuario_id
    GROUP BY u.id
    ORDER BY u.nombre
";
$stmt = $pdo->query($sqlUsuarios);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables + Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css">
    <!-- DataTables Buttons + Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.bootstrap5.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Administración de Usuarios</h1>
        <a href="menu.php" class="btn btn-secondary">Volver al menú</a>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errores as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <strong>Nuevo usuario</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">DNI</label>
                    <input type="text" name="dni" class="form-control" value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Usuario / Email</label>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Rol</label>
                    <select name="rol" class="form-select" required>
                        <?php
                        $rolSel = $_POST['rol'] ?? 'Solicitante';
                        foreach (['Solicitante','Aprobador','Admin'] as $rolOpt):
                            $sel = ($rolSel === $rolOpt) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($rolOpt) ?>" <?= $sel ?>><?= htmlspecialchars($rolOpt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Monto de aprobación</label>
                    <input type="text" name="monto_aprobacion" class="form-control" placeholder="0,00"
                           value="<?= htmlspecialchars($_POST['monto_aprobacion'] ?? '') ?>">
                    <div class="form-text">Aplica para roles Aprobador/Admin.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Módulos habilitados</label>
                    <div>
                        <?php foreach ($modulos_disponibles as $modulo): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="modulos[]"
                                       value="<?= htmlspecialchars($modulo) ?>">
                                <label class="form-check-label">
                                    <?= htmlspecialchars(str_replace('_',' ',$modulo)) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" name="crear_usuario" class="btn btn-success">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <h4>Usuarios existentes</h4>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tabla-usuarios" class="table table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>DNI</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th class="text-end">Monto aprobación</th>
                            <th>Estado</th>
                            <th>Módulos</th>
                            <th style="width: 220px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">No hay usuarios cargados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['nombre']) ?></td>
                                <td><?= htmlspecialchars($u['dni']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['rol']) ?></td>
                                <td class="text-end">
                                    <?= number_format((float)$u['monto_aprobacion'], 2, ',', '.') ?>
                                </td>
                                <td><?= !empty($u['activo']) ? 'Activo' : 'Inactivo' ?></td>
                                <td><?= htmlspecialchars($u['modulos']) ?></td>
                                <td>
                                    <a href="editar_usuario.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                    <?php if (!empty($u['activo'])): ?>
                                        <a href="usuarios.php?eliminar=<?= (int)$u['id'] ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('¿Está seguro de desactivar este usuario?');">
                                            Desactivar
                                        </a>
                                    <?php else: ?>
                                        <a href="usuarios.php?activar=<?= (int)$u['id'] ?>"
                                           class="btn btn-sm btn-success">
                                            Reactivar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

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
$(function () {
    $('#tabla-usuarios').DataTable({
        deferRender: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'asc']], // ordena por Nombre
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
            { targets: 4, type: 'num' },      // Monto aprobación
            { targets: 7, orderable: false }  // Acciones
        ]
    });
});
</script>
</body>
</html>
