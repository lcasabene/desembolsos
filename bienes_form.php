<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion('Bienes');

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Usuario';
$isAdmin = ($user_role === 'Admin');

$editing = false;
$prestamo = null;

if (isset($_GET['id'])) {
    $editing = true;
    $stmt = $pdo->prepare("SELECT * FROM prestamos_bienes WHERE id = ? AND usuario_solicitante_id = ?");
    $stmt->execute([$_GET['id'], $user_id]);
    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prestamo) {
        header("Location: bienes_listado.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = $_POST['descripcion'] ?? '';
    $cantidad = floatval($_POST['cantidad'] ?? 0);
    $fecha_necesita = $_POST['fecha_necesita'] ?? '';
    $tiempo_tipo = $_POST['tiempo_tipo'] ?? '';
    $fecha_devolucion = $_POST['fecha_devolucion'] ?? '';
    
    if ($descripcion && $cantidad > 0 && $fecha_necesita) {
        try {
            if ($editing) {
                $sql = "UPDATE prestamos_bienes SET 
                        descripcion_bien = ?, cantidad = ?, fecha_necesita_desde = ?, 
                        tiempo_tipo = ?, fecha_estimada_devolucion = ?, updated_at = NOW()
                        WHERE id = ? AND usuario_solicitante_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$descripcion, $cantidad, $fecha_necesita, $tiempo_tipo, $fecha_devolucion, $prestamo['id'], $user_id]);
                $msg = "Préstamo actualizado correctamente";
            } else {
                // Generar número de préstamo
                $nro_prestamo = 'P' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $sql = "INSERT INTO prestamos_bienes 
                        (nro_prestamo, usuario_solicitante_id, descripcion_bien, cantidad, 
                         fecha_solicitud, fecha_necesita_desde, tiempo_tipo, fecha_estimada_devolucion, estado, activo, updated_at)
                        VALUES (?, ?, ?, ?, CURRENT_DATE, ?, ?, ?, 'pendiente', 1, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nro_prestamo, $user_id, $descripcion, $cantidad, $fecha_necesita, $tiempo_tipo, $fecha_devolucion]);
                $msg = "Préstamo creado correctamente";
            }
            
            header("Location: bienes_listado.php?msg=" . urlencode($msg));
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Por favor complete todos los campos obligatorios";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $editing ? 'Editar' : 'Nuevo' ?> Préstamo - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --card-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            min-height: 100vh;
        }

        .modern-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid rgba(168, 237, 234, 0.3);
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #a8edea;
            box-shadow: 0 0 0 0.2rem rgba(168, 237, 234, 0.25);
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

        .form-label {
            font-weight: 600;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-modern sticky-top">
        <div class="container">
            <a class="navbar-brand brand-text" href="menu_bienes_moderno.php">
                <i class="bi bi-box"></i> Bienes
            </a>
            <div class="navbar-nav ms-auto">
                <a href="bienes_listado.php" class="btn btn-outline-secondary btn-modern">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="modern-card p-4">
            <h2 class="mb-4">
                <i class="bi bi-<?= $editing ? 'pencil' : 'plus-circle' ?>"></i> 
                <?= $editing ? 'Editar' : 'Nuevo' ?> Préstamo
            </h2>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="descripcion" class="form-label">
                            <i class="bi bi-box"></i> Descripción del Bien
                        </label>
                        <input type="text" class="form-control" id="descripcion" name="descripcion" 
                               value="<?= htmlspecialchars($prestamo['descripcion_bien'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="cantidad" class="form-label">
                            <i class="bi bi-cash"></i> Monto/Valor
                        </label>
                        <input type="number" class="form-control" id="cantidad" name="cantidad" 
                               value="<?= htmlspecialchars($prestamo['cantidad'] ?? '') ?>" 
                               step="0.01" min="0.01" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="fecha_necesita" class="form-label">
                            <i class="bi bi-calendar"></i> Fecha Necesaria
                        </label>
                        <input type="date" class="form-control" id="fecha_necesita" name="fecha_necesita" 
                               value="<?= htmlspecialchars($prestamo['fecha_necesita_desde'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="tiempo_tipo" class="form-label">
                            <i class="bi bi-clock"></i> Tipo de Tiempo
                        </label>
                        <select class="form-select" id="tiempo_tipo" name="tiempo_tipo" required>
                            <option value="">Seleccionar...</option>
                            <option value="determinado" <?= ($prestamo['tiempo_tipo'] ?? '') === 'determinado' ? 'selected' : '' ?>>
                                Determinado
                            </option>
                            <option value="indeterminado" <?= ($prestamo['tiempo_tipo'] ?? '') === 'indeterminado' ? 'selected' : '' ?>>
                                Indeterminado
                            </option>
                        </select>
                    </div>
                </div>

                <div class="row" id="fecha_devolucion_row">
                    <div class="col-md-6 mb-3">
                        <label for="fecha_devolucion" class="form-label">
                            <i class="bi bi-calendar-check"></i> Fecha Estimada de Devolución
                        </label>
                        <input type="date" class="form-control" id="fecha_devolucion" name="fecha_devolucion" 
                               value="<?= htmlspecialchars($prestamo['fecha_estimada_devolucion'] ?? '') ?>">
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="bienes_listado.php" class="btn btn-secondary btn-modern">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success btn-modern">
                        <i class="bi bi-check-circle"></i> 
                        <?= $editing ? 'Actualizar' : 'Crear' ?> Préstamo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/ocultar fecha de devolución según tipo de tiempo
        document.getElementById('tiempo_tipo').addEventListener('change', function() {
            const fechaDevolucionRow = document.getElementById('fecha_devolucion_row');
            const fechaDevolucion = document.getElementById('fecha_devolucion');
            
            if (this.value === 'determinado') {
                fechaDevolucionRow.style.display = 'block';
                fechaDevolucion.required = true;
            } else {
                fechaDevolucionRow.style.display = 'none';
                fechaDevolucion.required = false;
                fechaDevolucion.value = '';
            }
        });

        // Ejecutar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const tipoTiempo = document.getElementById('tiempo_tipo');
            if (tipoTiempo.value === 'indeterminado') {
                document.getElementById('fecha_devolucion_row').style.display = 'none';
                document.getElementById('fecha_devolucion').required = false;
            }
        });
    </script>
</body>
</html>
