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
                $stmt = $pdo->prepare("INSERT INTO feriados (nombre, fecha, descripcion, recurrente) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['nombre'],
                    $_POST['fecha'],
                    $_POST['descripcion'] ?? '',
                    $_POST['recurrente'] ?? 'unico'
                ]);
                $mensaje = "Feriado creado exitosamente";
                break;
                
            case 'editar':
                $stmt = $pdo->prepare("UPDATE feriados SET nombre = ?, fecha = ?, descripcion = ?, recurrente = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['nombre'],
                    $_POST['fecha'],
                    $_POST['descripcion'] ?? '',
                    $_POST['recurrente'] ?? 'unico',
                    $_POST['id']
                ]);
                $mensaje = "Feriado actualizado exitosamente";
                break;
                
            case 'eliminar':
                $stmt = $pdo->prepare("DELETE FROM feriados WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $mensaje = "Feriado eliminado exitosamente";
                break;
        }
    }
}

// Obtener lista de feriados
$stmt = $pdo->query("SELECT * FROM feriados ORDER BY fecha");
$feriados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Feriados - Sistema de Instalaciones</title>
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
            <h2><i class="bi bi-calendar-x"></i> Gestión de Feriados</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFeriado" onclick="limpiarFormulario()">
                <i class="bi bi-plus-circle"></i> Nuevo Feriado
            </button>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Información importante -->
        <div class="alert alert-info">
            <h6><i class="bi bi-info-circle"></i> Información Importante</h6>
            <p class="mb-0">
                Los feriados bloquean automáticamente la posibilidad de hacer reservas en esas fechas. 
                Los feriados anuales se repetirán automáticamente cada año en la misma fecha.
            </p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <table id="tablaFeriados" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feriados as $feriado): ?>
                            <tr>
                                <td><?= htmlspecialchars($feriado['nombre']) ?></td>
                                <td><?= date('d/m/Y', strtotime($feriado['fecha'])) ?></td>
                                <td>
                                    <?php if ($feriado['recurrente'] === 'anual'): ?>
                                        <span class="badge bg-warning">Anual</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Único</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(substr($feriado['descripcion'], 0, 50)) ?><?= strlen($feriado['descripcion']) > 50 ? '...' : '' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editarFeriado(<?= htmlspecialchars(json_encode($feriado)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarFeriado(<?= $feriado['id'] ?>, '<?= htmlspecialchars($feriado['nombre']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Feriados próximos -->
        <div class="card mt-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Próximos Feriados</h5>
            </div>
            <div class="card-body">
                <?php
                // Obtener feriados de los próximos 90 días
                $stmt = $pdo->prepare("
                    SELECT * FROM feriados 
                    WHERE fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                       OR (recurrente = 'anual' AND 
                           DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(fecha), '-', DAY(fecha))) 
                           BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY))
                    ORDER BY fecha
                    LIMIT 10
                ");
                $stmt->execute();
                $proximos_feriados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($proximos_feriados) > 0):
                ?>
                    <div class="row">
                        <?php foreach ($proximos_feriados as $feriado): ?>
                            <div class="col-md-6 col-lg-4 mb-2">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar-x text-warning me-2"></i>
                                    <div>
                                        <strong><?= htmlspecialchars($feriado['nombre']) ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($feriado['fecha'])) ?>
                                            <?= $feriado['recurrente'] === 'anual' ? '(Anual)' : '' ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No hay feriados programados para los próximos 90 días.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para crear/editar feriado -->
    <div class="modal fade" id="modalFeriado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Feriado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formFeriado" method="POST">
                    <input type="hidden" name="action" id="formAction" value="crear">
                    <input type="hidden" name="id" id="feriadoId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del Feriado *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required
                                   placeholder="Ej: Navidad, Año Nuevo, etc.">
                        </div>
                        <div class="mb-3">
                            <label for="fecha" class="form-label">Fecha *</label>
                            <input type="date" class="form-control" id="fecha" name="fecha" required>
                        </div>
                        <div class="mb-3">
                            <label for="recurrente" class="form-label">Tipo de Feriado</label>
                            <select class="form-select" id="recurrente" name="recurrente">
                                <option value="unico">Feriado único (solo este año)</option>
                                <option value="anual">Feriado anual (se repite cada año)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                                      placeholder="Información adicional sobre el feriado..."></textarea>
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
            $('#tablaFeriados').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                responsive: true,
                order: [[1, 'asc']] // Ordenar por fecha
            });
        });

        function limpiarFormulario() {
            document.getElementById('formFeriado').reset();
            document.getElementById('formAction').value = 'crear';
            document.getElementById('feriadoId').value = '';
            document.getElementById('modalTitle').textContent = 'Nuevo Feriado';
        }

        function editarFeriado(feriado) {
            document.getElementById('formAction').value = 'editar';
            document.getElementById('feriadoId').value = feriado.id;
            document.getElementById('nombre').value = feriado.nombre;
            document.getElementById('fecha').value = feriado.fecha;
            document.getElementById('recurrente').value = feriado.recurrente;
            document.getElementById('descripcion').value = feriado.descripcion;
            document.getElementById('modalTitle').textContent = 'Editar Feriado';
            
            new bootstrap.Modal(document.getElementById('modalFeriado')).show();
        }

        function eliminarFeriado(id, nombre) {
            if (confirm(`¿Estás seguro de eliminar el feriado "${nombre}"?`)) {
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
