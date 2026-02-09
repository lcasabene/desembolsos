<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Aprobador' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: login.php");
    exit;
}

include 'config/database.php';

// Mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$aprobador_id = $_SESSION['user_id'];
$monto_maximo = $_SESSION['monto_aprobacion'] ?? 0;
$solicitudes = [];

try {
    if ($_SESSION['user_role'] === 'Admin') {
        $stmt = $pdo->query("
            SELECT s.*, u.nombre 
            FROM solicitudes s 
            JOIN usuarios u ON s.usuario_id = u.id 
            WHERE s.estado = 'Pendiente'
            ORDER BY s.fecha_solicitud DESC
        ");
        $solicitudes = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT s.*, u.nombre 
            FROM solicitudes s 
            JOIN usuarios u ON s.usuario_id = u.id 
            WHERE s.estado = 'Pendiente' AND s.monto <= ?
            ORDER BY s.fecha_solicitud DESC
        ");
        $stmt->execute([$monto_maximo]);
        $solicitudes = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

// Procesar formulario de aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $solicitud_id = $_POST['solicitud_id'];
    $accion = $_POST['accion']; // 'Aprobado' o 'Rechazado'
    $observaciones = $_POST['observaciones'] ?? '';

    try {
        $update = $pdo->prepare("UPDATE solicitudes SET estado = ?, observaciones = ? WHERE id = ?");
        $update->execute([$accion, $observaciones, $solicitud_id]);

        $insert = $pdo->prepare("
            INSERT INTO aprobaciones (solicitud_id, aprobador_id, fecha_aprobacion, estado, observaciones)
            VALUES (?, ?, NOW(), ?, ?)
        ");
        $insert->execute([$solicitud_id, $aprobador_id, $accion, $observaciones]);

        header("Location: aprobaciones.php");
        exit;
    } catch (PDOException $e) {
        die("Error al procesar la solicitud: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aprobaciones - Sistema de Gestión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .modern-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .navbar-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 5px 30px rgba(0,0,0,0.1);
        }

        .brand-text {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }

        .btn-modern {
            border-radius: 10px;
            font-weight: 500;
            padding: 0.6rem 1.2rem;
            transition: all 0.3s ease;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid rgba(102, 126, 234, 0.3);
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .table-modern {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            overflow: hidden;
        }

        .table-modern thead {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }

        .table-modern tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .solicitud-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .solicitud-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }

        .badge-modern {
            border-radius: 20px;
            padding: 0.4rem 0.8rem;
            font-weight: 500;
        }

        .action-buttons .btn {
            border-radius: 8px;
            margin: 0 2px;
            transition: all 0.3s ease;
        }

        .action-buttons .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-modern sticky-top">
        <div class="container">
            <a class="navbar-brand brand-text" href="menu_anticipos_moderno.php">
                <i class="bi bi-cash-stack"></i> Anticipos
            </a>
            <div class="navbar-nav ms-auto">
                <a href="menu_anticipos_moderno.php" class="btn btn-outline-secondary btn-modern">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="modern-card p-4">
            <h2 class="mb-4">
                <i class="bi bi-check-circle"></i> Solicitudes Pendientes de Aprobación
            </h2>

            <?php if (empty($solicitudes)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="bi bi-check2-square" style="font-size: 3rem; color: #28a745;"></i>
                    </div>
                    <h4 class="text-muted">No hay solicitudes pendientes</h4>
                    <p class="text-muted">
                        <?= ($_SESSION['user_role'] === 'Aprobador') ? 'No hay solicitudes pendientes dentro de tu rango de aprobación' : 'No hay solicitudes pendientes de aprobación' ?>.
                    </p>
                </div>
            <?php else: ?>
                <div class="stats-card mb-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-0 text-muted">Solicitudes Pendientes</h6>
                            <h4 class="mb-0"><?= count($solicitudes) ?></h4>
                        </div>
                        <div class="text-warning">
                            <i class="bi bi-clock-history" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-modern">
                        <thead>
                            <tr>
                                <th><i class="bi bi-hash"></i> ID</th>
                                <th><i class="bi bi-person"></i> Solicitante</th>
                                <th class="text-end"><i class="bi bi-currency-dollar"></i> Monto</th>
                                <th><i class="bi bi-calendar"></i> Fecha Solicitud</th>
                                <th><i class="bi bi-wallet2"></i> Medio de Pago</th>
                                <th><i class="bi bi-credit-card"></i> Alias/CBU</th>
                                <th><i class="bi bi-chat-text"></i> Observaciones</th>
                                <th class="text-center"><i class="bi bi-gear"></i> Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes as $s): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary badge-modern">
                                            <?= $s['id'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-circle me-2 text-muted"></i>
                                            <?= htmlspecialchars($s['nombre']) ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success">$<?= number_format($s['monto'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-calendar-event me-2 text-muted"></i>
                                            <?= date('d/m/Y', strtotime($s['fecha_solicitud'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info badge-modern">
                                            <?= htmlspecialchars($s['medio_pago'] ?? 'No especificado') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($s['alias_cbu'])): ?>
                                                <small class="text-muted me-2"><?= htmlspecialchars($s['alias_cbu']) ?></small>
                                                <button 
                                                    class="btn btn-outline-secondary btn-sm"
                                                    onclick="copiarAlPortapapeles(this, '<?= htmlspecialchars($s['alias_cbu']) ?>')"
                                                    title="Copiar Alias/CBU"
                                                >
                                                    <i class="bi bi-clipboard"></i>
                                                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($s['observaciones'] ?? 'Sin observaciones') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <form method="POST" class="action-buttons">
                                            <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                            <div class="mb-2">
                                                <textarea name="observaciones" placeholder="Observaciones (opcional)" class="form-control form-control-sm" rows="2"></textarea>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <button type="submit" name="accion" value="Aprobado" class="btn btn-success btn-sm btn-modern">
                                                    <i class="bi bi-check-circle"></i> Aprobar
                                                </button>
                                                <button type="submit" name="accion" value="Rechazado" class="btn btn-danger btn-sm btn-modern">
                                                    <i class="bi bi-x-circle"></i> Rechazar
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Toast de Bootstrap -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="toast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-clipboard-check"></i> Alias/CBU copiado al portapapeles.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copiarAlPortapapeles(button, texto) {
            const spinner = button.querySelector('.spinner-border');
            const icono = button.querySelector('.bi');

            icono.classList.add('d-none');
            spinner.classList.remove('d-none');

            navigator.clipboard.writeText(texto).then(() => {
                spinner.classList.add('d-none');
                icono.classList.remove('d-none');
                const toast = new bootstrap.Toast(document.getElementById('toast'));
                toast.show();
            }).catch(err => {
                spinner.classList.add('d-none');
                icono.classList.remove('d-none');
                alert('Error al copiar al portapapeles: ' + err);
            });
        }
    </script>
</body>
</html>

