<?php
require_once __DIR__ . '/../config/seguridad.php';
verificar_autenticacion();

require_once __DIR__ . '/../config/database.php';

$modulos = $_SESSION['modulos'] ?? [];
if (!in_array('Presupuesto', $modulos) && $_SESSION['user_role'] !== 'Admin') {
    header('Location: acceso_denegado.php');
    exit;
}

$presupuesto_id = $_GET['id'] ?? 0;
$usuario_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM presupuestos_anuales WHERE id = ?");
    $stmt->execute([$presupuesto_id]);
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$presupuesto) {
        header('Location: index.php?error=not_found');
        exit;
    }
    
    if ($presupuesto['estado'] === 'aprobado' && $_SESSION['user_role'] !== 'Admin') {
        header('Location: index.php?error=approved');
        exit;
    }
    
    $stmt_deptos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre");
    $departamentos = $stmt_deptos->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM presupuesto_mensual_detalle WHERE presupuesto_anual_id = ? ORDER BY FIELD(mes, 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre')");
    $stmt->execute([$presupuesto_id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE presupuestos_anuales SET objetivos_estrategicos = ?, estado = ?, actualizado_por = ?, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$_POST['objetivos_estrategicos'], $_POST['estado'], $usuario_id, $presupuesto_id]);
        
        $stmt_delete = $pdo->prepare("DELETE FROM presupuesto_mensual_detalle WHERE presupuesto_anual_id = ?");
        $stmt_delete->execute([$presupuesto_id]);
        
        if (isset($_POST['actividades'])) {
            $stmt_insert = $pdo->prepare("INSERT INTO presupuesto_mensual_detalle (presupuesto_anual_id, mes, nombre_actividad, fecha_inicio, fecha_fin, presupuesto_estimado, descripcion, departamento_id, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($_POST['actividades'] as $mes => $actividades_mes) {
                foreach ($actividades_mes as $actividad) {
                    if (!empty($actividad['nombre'])) {
                        $stmt_insert->execute([
                            $presupuesto_id,
                            $mes,
                            $actividad['nombre'],
                            $actividad['fecha_inicio'] ?: null,
                            $actividad['fecha_fin'] ?: null,
                            floatval(str_replace(['$', ','], '', $actividad['presupuesto'])),
                            $actividad['descripcion'] ?? '',
                            !empty($actividad['departamento_id']) ? intval($actividad['departamento_id']) : null,
                            $usuario_id
                        ]);
                    }
                }
            }
        }
        
        $stmt_total = $pdo->prepare("UPDATE presupuestos_anuales SET total_anual = (SELECT COALESCE(SUM(presupuesto_estimado), 0) FROM presupuesto_mensual_detalle WHERE presupuesto_anual_id = ?) WHERE id = ?");
        $stmt_total->execute([$presupuesto_id, $presupuesto_id]);
        
        $pdo->commit();
        header('Location: index.php?success=updated');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al actualizar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Presupuesto <?= htmlspecialchars($presupuesto['anio']) ?> - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        body { font-family: 'Inter', sans-serif; background: var(--primary-gradient); min-height: 100vh; }
        .main-container { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .header-title { font-size: 2.5rem; font-weight: 700; background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .form-section { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .section-title { font-size: 1.5rem; font-weight: 600; color: #2c3e50; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #667eea; }
        .meses-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .mes-card { background: #f8f9fa; border-radius: 15px; padding: 1.5rem; border: 2px solid #e9ecef; }
        .mes-title { font-weight: 600; color: #495057; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; }
        .mes-total { background: var(--primary-gradient); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.9rem; font-weight: 600; }
        .actividad-item { background: white; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; border: 1px solid #dee2e6; position: relative; }
        .remove-actividad { position: absolute; top: 0.5rem; right: 0.5rem; background: #dc3545; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer; }
        .add-actividad-btn { background: #28a745; color: white; border: none; border-radius: 10px; padding: 0.75rem; width: 100%; }
        .total-section { background: var(--primary-gradient); color: white; border-radius: 20px; padding: 2rem; margin-top: 2rem; text-align: center; }
        .total-anual { font-size: 3rem; font-weight: 700; margin-bottom: 0.5rem; }
        .form-control, .form-select { border-radius: 10px; border: 2px solid #e9ecef; }
        .btn-primary { background: var(--primary-gradient); border: none; border-radius: 10px; padding: 0.75rem 2rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="header-title">Editar Presupuesto <?= htmlspecialchars($presupuesto['anio']) ?></h1>
                    <p class="text-muted mb-0">Modificación de planificación presupuestaria</p>
                </div>
                <div>
                    <a href="ver_presupuesto.php?id=<?= $presupuesto_id ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="presupuestoForm">
            <div class="form-section">
                <h2 class="section-title"><i class="bi bi-bullseye"></i> Objetivos Estratégicos</h2>
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Año</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($presupuesto['anio']) ?>" readonly>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Objetivos Estratégicos</label>
                        <textarea class="form-control" name="objetivos_estrategicos" rows="4" required><?= htmlspecialchars($presupuesto['objetivos_estrategicos']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="section-title"><i class="bi bi-calendar-month"></i> Presupuesto Mensual</h2>
                <div class="meses-grid" id="mesesGrid">
                    <?php
                    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                    foreach ($meses as $mes):
                        $actividades_mes = array_filter($detalles, fn($d) => $d['mes'] === $mes);
                    ?>
                        <div class="mes-card" data-mes="<?= $mes ?>">
                            <div class="mes-title">
                                <span><?= ucfirst($mes) ?></span>
                                <span class="mes-total" data-mes-total="<?= $mes ?>">$0.00</span>
                            </div>
                            <div class="actividades-container" data-mes-actividades="<?= $mes ?>">
                                <?php foreach ($actividades_mes as $index => $actividad): ?>
                                    <div class="actividad-item" id="actividad_<?= $mes ?>_<?= $index ?>">
                                        <button type="button" class="remove-actividad" onclick="removeActividad('actividad_<?= $mes ?>_<?= $index ?>', '<?= $mes ?>')">
                                            <i class="bi bi-x"></i>
                                        </button>
                                        <div class="row g-2">
                                            <div class="col-12">
                                                <input type="text" class="form-control" name="actividades[<?= $mes ?>][<?= $index ?>][nombre]" value="<?= htmlspecialchars($actividad['nombre_actividad']) ?>" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Inicio</label>
                                                <input type="date" class="form-control" name="actividades[<?= $mes ?>][<?= $index ?>][fecha_inicio]" value="<?= $actividad['fecha_inicio'] ?>">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Fin</label>
                                                <input type="date" class="form-control" name="actividades[<?= $mes ?>][<?= $index ?>][fecha_fin]" value="<?= $actividad['fecha_fin'] ?>">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Departamento</label>
                                                <select class="form-select" name="actividades[<?= $mes ?>][<?= $index ?>][departamento_id]">
                                                    <option value="">Seleccionar departamento</option>
                                                    <?php foreach ($departamentos as $depto): ?>
                                                        <option value="<?= $depto['id'] ?>" <?= $actividad['departamento_id'] == $depto['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($depto['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Presupuesto</label>
                                                <input type="text" class="form-control presupuesto-input" name="actividades[<?= $mes ?>][<?= $index ?>][presupuesto]" value="$<?= number_format($actividad['presupuesto_estimado'], 2, ',', '.') ?>" data-mes="<?= $mes ?>" oninput="calcularTotales(); formatCurrency(this);">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Mes</label>
                                                <input type="hidden" name="actividades[<?= $mes ?>][<?= $index ?>][mes]" value="<?= $mes ?>">
                                                <input type="text" class="form-control" value="<?= ucfirst($mes) ?>" readonly>
                                            </div>
                                            <div class="col-12">
                                                <textarea class="form-control" name="actividades[<?= $mes ?>][<?= $index ?>][descripcion]" rows="2"><?= htmlspecialchars($actividad['descripcion']) ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="add-actividad-btn" onclick="addActividad('<?= $mes ?>')">
                                <i class="bi bi-plus-circle"></i> Agregar Actividad
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="total-section">
                <h3>Total Presupuesto Anual</h3>
                <div class="total-anual" id="totalAnual">$0.00</div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="form-label text-white">Estado</label>
                        <select class="form-select" name="estado">
                            <option value="borrador" <?= $presupuesto['estado'] === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                            <option value="enviado_aprobacion" <?= $presupuesto['estado'] === 'enviado_aprobacion' ? 'selected' : '' ?>>Enviado para Aprobación</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-light btn-lg w-100">
                            <i class="bi bi-save"></i> Actualizar Presupuesto
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let actividadCounter = 1000;

        function addActividad(mes) {
            actividadCounter++;
            const container = document.querySelector(`[data-mes-actividades="${mes}"]`);
            const actividadId = `actividad_${mes}_${actividadCounter}`;
            
            const actividadHtml = `
                <div class="actividad-item" id="${actividadId}">
                    <button type="button" class="remove-actividad" onclick="removeActividad('${actividadId}', '${mes}')">
                        <i class="bi bi-x"></i>
                    </button>
                    <div class="row g-2">
                        <div class="col-12">
                            <input type="text" class="form-control" name="actividades[${mes}][${actividadCounter}][nombre]" placeholder="Nombre de la actividad" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Departamento</label>
                            <select class="form-select" name="actividades[${mes}][${actividadCounter}][departamento_id]">
                                <option value="">Seleccionar departamento</option>
                                <?php foreach ($departamentos as $depto): ?>
                                    <option value="<?= $depto['id'] ?>"><?= htmlspecialchars($depto['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Presupuesto Estimado</label>
                            <input type="text" class="form-control presupuesto-input" name="actividades[${mes}][${actividadCounter}][presupuesto]" placeholder="$0.00" data-mes="${mes}" oninput="calcularTotales(); formatCurrency(this);">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Fecha Inicio</label>
                            <input type="date" class="form-control" name="actividades[${mes}][${actividadCounter}][fecha_inicio]">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Fecha Fin</label>
                            <input type="date" class="form-control" name="actividades[${mes}][${actividadCounter}][fecha_fin]">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Mes</label>
                            <input type="hidden" name="actividades[${mes}][${actividadCounter}][mes]" value="${mes}">
                            <input type="text" class="form-control" value="${mes.charAt(0).toUpperCase() + mes.slice(1)}" readonly>
                        </div>
                        <div class="col-12">
                            <textarea class="form-control" name="actividades[${mes}][${actividadCounter}][descripcion]" placeholder="Breve descripción..." rows="2"></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', actividadHtml);
            calcularTotales();
        }

        function removeActividad(actividadId, mes) {
            document.getElementById(actividadId).remove();
            calcularTotales();
        }

        function formatCurrency(input) {
            let value = input.value.replace(/[^\d.]/g, '');
            let parts = value.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (parts.length > 1) {
                parts[1] = parts[1].substring(0, 2);
            }
            input.value = '$' + parts.join('.');
        }

        function calcularTotales() {
            const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            let totalAnual = 0;

            meses.forEach(mes => {
                const inputs = document.querySelectorAll(`[data-mes="${mes}"].presupuesto-input`);
                let totalMes = 0;

                inputs.forEach(input => {
                    let value = input.value.replace(/[^\d.]/g, '');
                    totalMes += parseFloat(value) || 0;
                });

                const totalMesElement = document.querySelector(`[data-mes-total="${mes}"]`);
                if (totalMesElement) {
                    totalMesElement.textContent = '$' + totalMes.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                totalAnual += totalMes;
            });

            document.getElementById('totalAnual').textContent = '$' + totalAnual.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        document.addEventListener('DOMContentLoaded', calcularTotales);
    </script>
</body>
</html>
