<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion('Anticipos');

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Usuario';

// Obtener solicitudes aprobadas y no rendidas
try {
    $stmt = $pdo->prepare("
        SELECT id, monto, observaciones, fecha_solicitud
        FROM solicitudes 
        WHERE usuario_id = ? AND estado = 'Aprobado'
        ORDER BY fecha_solicitud DESC
    ");
    $stmt->execute([$user_id]);
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al consultar las solicitudes: " . $e->getMessage();
}

// Procesar formulario de rendición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Crear directorio uploads si no existe
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }

        $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
        $observaciones = trim($_POST['observaciones'] ?? '');
        $reintegro = $_POST['reintegro'] ?? 'No';
        $monto_reintegro = floatval($_POST['monto_reintegro'] ?? 0);

        if ($solicitud_id > 0) {
            // Verificar que la solicitud exists y pertenece al usuario
            $stmt = $pdo->prepare("SELECT id, monto FROM solicitudes WHERE id = ? AND usuario_id = ? AND estado = 'Aprobado'");
            $stmt->execute([$solicitud_id, $user_id]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$solicitud) {
                $error = "Solicitud no válida";
            } else {
                // Insertar rendición principal
                $stmt = $pdo->prepare("
                    INSERT INTO rendiciones (solicitud_id, usuario_id, fecha_rendicion, observaciones, estado_rendicion)
                    VALUES (?, ?, CURRENT_DATE, ?, 'Completo')
                ");
                $stmt->execute([$solicitud_id, $user_id, $observaciones]);
                $rendicion_id = $pdo->lastInsertId();

                // Insertar comprobantes (hasta 10)
                for ($i = 0; $i < 10; $i++) {
                    if (!empty($_FILES['archivo']['name'][$i])) {
                        $concepto = trim($_POST['concepto'][$i] ?? '');
                        $monto = floatval($_POST['monto_comprobante'][$i] ?? 0);
                        $archivo_nombre = basename($_FILES['archivo']['name'][$i]);
                        $destino = 'uploads/' . uniqid() . '_' . $archivo_nombre;

                        if (move_uploaded_file($_FILES['archivo']['tmp_name'][$i], $destino)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO comprobantes (rendicion_id, tipo_comprobante, nro_comprobante, importe, archivo)
                                VALUES (?, ?, '', ?, ?)
                            ");
                            $stmt->execute([$rendicion_id, $concepto, $monto, $destino]);
                        } else {
                            $error = "Error al subir el archivo: " . $archivo_nombre;
                        }
                    }
                }

                // Insertar reintegro si corresponde
                if ($reintegro === 'Si' && !empty($_FILES['archivo_reintegro']['name'])) {
                    $archivo_reintegro = basename($_FILES['archivo_reintegro']['name']);
                    $destino_reintegro = 'uploads/' . uniqid() . '_' . $archivo_reintegro;

                    if (move_uploaded_file($_FILES['archivo_reintegro']['tmp_name'], $destino_reintegro)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO comprobantes (rendicion_id, tipo_comprobante, nro_comprobante, importe, archivo)
                            VALUES (?, 'Reintegro', '', ?, ?)
                        ");
                        $stmt->execute([$rendicion_id, $monto_reintegro, $destino_reintegro]);
                    } else {
                        $error = "Error al subir el archivo de reintegro";
                    }
                }

                // Validar montos y actualizar estado
                $stmt = $pdo->prepare("SELECT SUM(importe) FROM comprobantes WHERE rendicion_id = ?");
                $stmt->execute([$rendicion_id]);
                $total_rendido = $stmt->fetchColumn() ?: 0;

                $estado_rendicion = 'Completo';
                if ($total_rendido > $solicitud['monto']) {
                    $estado_rendicion = 'Sobrante';
                } elseif ($total_rendido < $solicitud['monto']) {
                    $estado_rendicion = 'Faltante';
                }

                // Actualizar estado de rendición
                $stmt = $pdo->prepare("UPDATE rendiciones SET estado_rendicion = ? WHERE id = ?");
                $stmt->execute([$estado_rendicion, $rendicion_id]);

                // Cambiar estado de solicitud a 'Rendido'
                $stmt = $pdo->prepare("UPDATE solicitudes SET estado = 'Rendido' WHERE id = ?");
                $stmt->execute([$solicitud_id]);

                $success = "Rendición registrada correctamente. Estado: $estado_rendicion";
                
                // Recargar solicitudes
                $stmt = $pdo->prepare("
                    SELECT id, monto, observaciones, fecha_solicitud
                    FROM solicitudes 
                    WHERE usuario_id = ? AND estado = 'Aprobado'
                    ORDER BY fecha_solicitud DESC
                ");
                $stmt->execute([$user_id]);
                $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $error = "Por favor seleccione una solicitud válida";
        }
    } catch (PDOException $e) {
        $error = "Error al registrar la rendición: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rendiciones - Sistema de Gestión</title>
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

        .solicitud-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .solicitud-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .comprobante-row {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.8rem;
            border: 1px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }

        .comprobante-row:hover {
            background: rgba(255, 255, 255, 0.95);
            border-color: rgba(102, 126, 234, 0.4);
        }

        .reintegro-section {
            background: linear-gradient(135deg, rgba(250, 112, 154, 0.1) 0%, rgba(254, 225, 64, 0.1) 100%);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(250, 112, 154, 0.3);
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: block;
            padding: 0.6rem 1rem;
            background: rgba(102, 126, 234, 0.1);
            border: 2px dashed rgba(102, 126, 234, 0.5);
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-input-label:hover {
            background: rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.8);
        }

        .section-title {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(102, 126, 234, 0.3);
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
                <i class="bi bi-receipt-cutoff"></i> Rendición de Comprobantes
            </h2>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($solicitudes)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    No tienes solicitudes aprobadas pendientes de rendición.
                    <a href="listado_solicitudes.php" class="alert-link">Ver tus solicitudes</a>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="solicitud_id" class="form-label">
                            <i class="bi bi-list-check"></i> Solicitud Asociada
                        </label>
                        <select class="form-select" id="solicitud_id" name="solicitud_id" required>
                            <option value="">Seleccionar una solicitud...</option>
                            <?php foreach ($solicitudes as $solicitud): ?>
                                <option value="<?= $solicitud['id'] ?>">
                                    #<?= $solicitud['id'] ?> - $<?= number_format($solicitud['monto'], 2, ',', '.') ?> 
                                    - <?= htmlspecialchars($solicitud['observaciones']) ?>
                                    (<?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <h3 class="section-title">
                        <i class="bi bi-file-earmark-text"></i> Comprobantes de Gastos (hasta 10)
                    </h3>

                    <?php for ($i = 0; $i < 10; $i++): ?>
                        <div class="comprobante-row">
                            <div class="row align-items-center">
                                <div class="col-md-1 text-center">
                                    <span class="badge bg-primary"><?= $i + 1 ?></span>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Concepto</label>
                                    <input type="text" name="concepto[]" class="form-control form-control-sm" 
                                           placeholder="Ej: Viáticos, Hospedaje">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Monto</label>
                                    <input type="text" name="monto_comprobante[]" 
                                           class="form-control form-control-sm monto-autonum" 
                                           placeholder="0,00"
                                           autocomplete="off"
                                           inputmode="numeric">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Comprobante</label>
                                    <div class="file-input-wrapper">
                                        <input type="file" name="archivo[]" accept=".pdf,.jpg,.jpeg,.png" id="file_<?= $i ?>">
                                        <label for="file_<?= $i ?>" class="file-input-label">
                                            <i class="bi bi-cloud-upload"></i> 
                                            <span class="file-name">Seleccionar archivo</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>

                    <div class="reintegro-section mt-4">
                        <h3 class="section-title">
                            <i class="bi bi-arrow-repeat"></i> Reintegro
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="reintegro" class="form-label">
                                    <i class="bi bi-question-circle"></i> ¿Corresponde reintegro?
                                </label>
                                <select class="form-select" id="reintegro" name="reintegro">
                                    <option value="No">No</option>
                                    <option value="Si">Sí</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="monto_reintegro" class="form-label">
                                    <i class="bi bi-cash"></i> Monto de Reintegro
                                </label>
                                <input type="text" id="monto_reintegro" name="monto_reintegro" 
                                       class="form-control monto-autonum" placeholder="0,00"
                                       autocomplete="off"
                                       inputmode="numeric">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">
                                <i class="bi bi-file-earmark-pdf"></i> Comprobante de Reintegro
                            </label>
                            <div class="file-input-wrapper">
                                <input type="file" name="archivo_reintegro" accept=".pdf,.jpg,.jpeg,.png" id="file_reintegro">
                                <label for="file_reintegro" class="file-input-label">
                                    <i class="bi bi-cloud-upload"></i> 
                                    <span class="file-name">Seleccionar comprobante de reintegro</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="observaciones" class="form-label">
                            <i class="bi bi-chat-text"></i> Observaciones Generales
                        </label>
                        <textarea id="observaciones" name="observaciones" class="form-control" 
                                  rows="3" placeholder="Observaciones adicionales sobre la rendición..."></textarea>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="menu_anticipos_moderno.php" class="btn btn-secondary btn-modern">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-success btn-modern">
                            <i class="bi bi-check-circle"></i> Registrar Rendición
                        </button>
                    </div>
                </form>

                <hr class="my-4">

                <h4 class="mb-3">
                    <i class="bi bi-list-ul"></i> Solicitudes Disponibles para Rendir
                </h4>

                <?php foreach ($solicitudes as $solicitud): ?>
                    <div class="solicitud-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">Solicitud #<?= $solicitud['id'] ?></h6>
                                <p class="mb-1 text-muted"><?= htmlspecialchars($solicitud['observaciones']) ?></p>
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])) ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <h5 class="mb-0 text-success">$<?= number_format($solicitud['monto'], 2, ',', '.') ?></h5>
                                <small class="badge bg-success">Aprobado</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manejo de nombres de archivos
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const fileName = e.target.files[0]?.name || 'Seleccionar archivo';
                const label = this.nextElementSibling;
                const fileNameSpan = label.querySelector('.file-name');
                if (fileNameSpan) {
                    fileNameSpan.textContent = fileName;
                }
            });
        });

        // Mostrar/ocultar campos de reintegro
        document.getElementById('reintegro').addEventListener('change', function() {
            const montoReintegro = document.getElementById('monto_reintegro');
            const fileReintegro = document.getElementById('file_reintegro');
            
            if (this.value === 'Si') {
                montoReintegro.required = true;
                fileReintegro.required = true;
                montoReintegro.closest('.col-md-6').style.display = 'block';
                fileReintegro.closest('.mt-3').style.display = 'block';
            } else {
                montoReintegro.required = false;
                fileReintegro.required = false;
                montoReintegro.value = '';
                fileReintegro.value = '';
            }
        });

        // Validación básica antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            const solicitudId = document.getElementById('solicitud_id').value;
            
            if (!solicitudId) {
                e.preventDefault();
                alert('Por favor seleccione una solicitud');
                return;
            }

            // Verificar que al menos un comprobante tenga archivo
            const archivos = document.querySelectorAll('input[name="archivo[]"]');
            const tieneArchivo = Array.from(archivos).some(input => input.files.length > 0);
            
            if (!tieneArchivo) {
                e.preventDefault();
                alert('Por favor adjunte al menos un comprobante');
                return;
            }
        });

        // Formateo de montos automático
        function formatearMontoInput(input) {
            let valor = input.value;

            // 1) Dejar solo dígitos (elimina puntos, comas, letras, etc.)
            valor = valor.replace(/\D/g, '');

            // Si está vacío, no mostramos nada
            if (!valor) {
                input.value = '';
                return;
            }

            // 2) Eliminar ceros a la izquierda (pero dejar uno si queda todo vacío)
            valor = valor.replace(/^0+/, '');
            if (valor === '') {
                valor = '0';
            }

            // 3) Separar parte entera y centavos (últimos dos dígitos = centavos)
            let enteros, centavos;
            if (valor.length === 1) {
                enteros = '0';
                centavos = '0' + valor;        // "5" -> "05" => 0,05
            } else if (valor.length === 2) {
                enteros = '0';
                centavos = valor;              // "50" -> "50" => 0,50
            } else {
                enteros = valor.slice(0, -2);  // todo menos los últimos 2
                centavos = valor.slice(-2);    // últimos 2 dígitos
            }

            // 4) Formatear miles con puntos
            enteros = enteros.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

            // 5) Armar valor final con coma decimal
            input.value = enteros + ',' + centavos;
        }

        // Aplicar a todos los inputs con la clase .monto-autonum
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.monto-autonum').forEach(function (input) {
                input.addEventListener('input', function () {
                    formatearMontoInput(input);
                });
            });
        });
    </script>
</body>
</html>
