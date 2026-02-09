<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Aprobador')) {
    header("Location: login.php");
    exit;
}

include 'config/database.php';

$stmt = $pdo->query("
    SELECT r.id, r.fecha_rendicion, s.id AS solicitud_id, u.nombre AS solicitante
    FROM rendiciones r
    JOIN solicitudes s ON r.solicitud_id = s.id
    JOIN usuarios u ON s.usuario_id = u.id
    ORDER BY r.fecha_rendicion DESC
");
$rendiciones = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisión de Rendiciones - Sistema de Gestión</title>
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

        .rendicion-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .rendicion-card:hover {
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
                <i class="bi bi-clipboard-check"></i> Revisión de Rendiciones
            </h2>

            <?php if (empty($rendiciones)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
                    </div>
                    <h4 class="text-muted">No hay rendiciones registradas</h4>
                    <p class="text-muted">No se encontraron rendiciones para revisar.</p>
                </div>
            <?php else: ?>
                <div class="stats-card mb-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-0 text-muted">Total de Rendiciones</h6>
                            <h4 class="mb-0"><?= count($rendiciones) ?></h4>
                        </div>
                        <div class="text-primary">
                            <i class="bi bi-file-earmark-check" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-modern">
                        <thead>
                            <tr>
                                <th><i class="bi bi-hash"></i> ID Rendición</th>
                                <th><i class="bi bi-link-45deg"></i> Solicitud Asociada</th>
                                <th><i class="bi bi-person"></i> Solicitante</th>
                                <th><i class="bi bi-calendar"></i> Fecha de Rendición</th>
                                <th class="text-center"><i class="bi bi-gear"></i> Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rendiciones as $r): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary badge-modern">
                                            <?= $r['id'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info badge-modern">
                                            <i class="bi bi-link-45deg"></i> #<?= $r['solicitud_id'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-circle me-2 text-muted"></i>
                                            <?= htmlspecialchars($r['solicitante']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-calendar-event me-2 text-muted"></i>
                                            <?= date('d/m/Y', strtotime($r['fecha_rendicion'])) ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <a href="detalle_rendicion.php?id=<?= $r['id'] ?>" class="btn btn-info btn-modern btn-sm">
                                            <i class="bi bi-eye"></i> Ver Detalle
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
