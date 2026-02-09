<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

// Verificar si el usuario tiene acceso al módulo y es admin
$modulos = $_SESSION['modulos'] ?? [];
if (!in_array('Instalaciones', $modulos) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../acceso_denegado.php");
    exit;
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'crear':
                $stmt = $pdo->prepare("INSERT INTO salones (nombre, numero, capacidad, descripcion, estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['nombre'],
                    $_POST['numero'],
                    $_POST['capacidad'] ?? 0,
                    $_POST['descripcion'] ?? '',
                    $_POST['estado'] ?? 'activo'
                ]);
                $mensaje = "Salón creado exitosamente";
                break;
                
            case 'editar':
                $stmt = $pdo->prepare("UPDATE salones SET nombre = ?, numero = ?, capacidad = ?, descripcion = ?, estado = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['nombre'],
                    $_POST['numero'],
                    $_POST['capacidad'] ?? 0,
                    $_POST['descripcion'] ?? '',
                    $_POST['estado'] ?? 'activo',
                    $_POST['id']
                ]);
                $mensaje = "Salón actualizado exitosamente";
                break;
                
            case 'eliminar':
                $stmt = $pdo->prepare("DELETE FROM salones WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $mensaje = "Salón eliminado exitosamente";
                break;
        }
    }
}

// Obtener lista de salones
$stmt = $pdo->query("SELECT * FROM salones ORDER BY numero");
$salones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Salones - Sistema de Instalaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../menu_instalaciones.php">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-door-open"></i> Gestión de Salones</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSalon" onclick="limpiarFormulario()">
                <i class="bi bi-plus-circle"></i> Nuevo Salón
            </button>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <table id="tablaSalones" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Nombre</th>
                            <th>Capacidad</th>
                            <th>Estado</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salones as $salon): ?>
                            <tr>
                                <td><?= htmlspecialchars($salon['numero']) ?></td>
                                <td><?= htmlspecialchars($salon['nombre']) ?></td>
                                <td><?= htmlspecialchars($salon['capacidad']) ?></td>
                                <td>
                                    <?php
                                    $estado_class = match($salon['estado']) {
                                        'activo' => 'success',
                                        'mantenimiento' => 'warning',
                                        'inactivo' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $estado_class ?>">
                                        <?= htmlspecialchars($salon['estado']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(substr($salon['descripcion'], 0, 50)) ?><?= strlen($salon['descripcion']) > 50 ? '...' : '' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editarSalon(<?= htmlspecialchars(json_encode($salon)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarSalon(<?= $salon['id'] ?>, '<?= htmlspecialchars($salon['nombre']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para crear/editar salón -->
    <div class="modal fade" id="modalSalon" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Salón</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formSalon" method="POST">
                    <input type="hidden" name="action" id="formAction" value="crear">
                    <input type="hidden" name="id" id="salonId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="numero" class="form-label">Número *</label>
                            <input type="text" class="form-control" id="numero" name="numero" required>
                        </div>
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="capacidad" class="form-label">Capacidad</label>
                            <input type="number" class="form-control" id="capacidad" name="capacidad" min="0">
                        </div>
                        <div class="mb-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="activo">Activo</option>
                                <option value="mantenimiento">Mantenimiento</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tablaSalones').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                responsive: true
            });
        });

        function limpiarFormulario() {
            document.getElementById('formSalon').reset();
            document.getElementById('formAction').value = 'crear';
            document.getElementById('salonId').value = '';
            document.getElementById('modalTitle').textContent = 'Nuevo Salón';
        }

        function editarSalon(salon) {
            document.getElementById('formAction').value = 'editar';
            document.getElementById('salonId').value = salon.id;
            document.getElementById('numero').value = salon.numero;
            document.getElementById('nombre').value = salon.nombre;
            document.getElementById('capacidad').value = salon.capacidad;
            document.getElementById('estado').value = salon.estado;
            document.getElementById('descripcion').value = salon.descripcion;
            document.getElementById('modalTitle').textContent = 'Editar Salón';
            
            new bootstrap.Modal(document.getElementById('modalSalon')).show();
        }

        function eliminarSalon(id, nombre) {
            if (confirm(`¿Estás seguro de eliminar el salón "${nombre}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
