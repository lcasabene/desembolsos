<?php
require_once __DIR__ . '/../config/seguridad.php';
verificar_autenticacion();

require_once __DIR__ . '/../config/database.php';

// Verificar si el usuario tiene acceso al módulo de presupuesto
$modulos = $_SESSION['modulos'] ?? [];
if (!in_array('Presupuesto', $modulos) && $_SESSION['user_role'] !== 'Admin') {
    header('Location: acceso_denegado.php');
    exit;
}

$nombre = $_SESSION['user_name'] ?? 'Usuario';
$rol = $_SESSION['user_role'] ?? 'User';
$usuario_id = $_SESSION['user_id'] ?? 0;

// Obtener departamentos disponibles
try {
    $stmt_deptos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre");
    $departamentos = $stmt_deptos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departamentos = [];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $anio = $_POST['anio'] ?? date('Y');
        $objetivos = $_POST['objetivos_estrategicos'] ?? '';
        $estado = $_POST['estado'] ?? 'borrador';
        
        // Insertar presupuesto anual
        $stmt = $pdo->prepare("INSERT INTO presupuestos_anuales (anio, objetivos_estrategicos, estado, creado_por) VALUES (?, ?, ?, ?)");
        $stmt->execute([$anio, $objetivos, $estado, $usuario_id]);
        $presupuesto_id = $pdo->lastInsertId();
        
        // Insertar detalles mensuales
        if (isset($_POST['actividades']) && is_array($_POST['actividades'])) {
            $stmt_detalle = $pdo->prepare("INSERT INTO presupuesto_mensual_detalle (presupuesto_anual_id, mes, nombre_actividad, fecha_inicio, fecha_fin, presupuesto_estimado, descripcion, departamento_id, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($_POST['actividades'] as $actividad) {
                if (!empty($actividad['nombre']) && !empty($actividad['mes'])) {
                    $stmt_detalle->execute([
                        $presupuesto_id,
                        $actividad['mes'],
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
        
        // Calcular y actualizar total anual
        $stmt_total = $pdo->prepare("UPDATE presupuestos_anuales SET total_anual = (SELECT COALESCE(SUM(presupuesto_estimado), 0) FROM presupuesto_mensual_detalle WHERE presupuesto_anual_id = ?) WHERE id = ?");
        $stmt_total->execute([$presupuesto_id, $presupuesto_id]);
        
        $pdo->commit();
        
        header('Location: index.php?success=1');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al guardar el presupuesto: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo Presupuesto Anual - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .main-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .form-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }

        .meses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .mes-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .mes-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.1);
        }

        .mes-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .mes-total {
            background: var(--primary-gradient);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .actividad-item {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            position: relative;
        }

        .remove-actividad {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-actividad:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        .add-actividad-btn {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            width: 100%;
            transition: all 0.3s ease;
        }

        .add-actividad-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .total-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
            text-align: center;
        }

        .total-anual {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .objetives-textarea {
            min-height: 150px;
            resize: vertical;
        }

        .month-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            margin-left: 0.5rem;
        }

        .month-indicator.empty {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="header-title">Nuevo Presupuesto Anual</h1>
                    <p class="text-muted mb-0">Planificación estratégica y asignación de recursos</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="presupuestoForm">
            <!-- Sección de Objetivos Estratégicos -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-bullseye"></i> Objetivos Estratégicos del Año
                </h2>
                
                <div class="row">
                    <div class="col-md-3">
                        <label for="anio" class="form-label">Año Presupuestario</label>
                        <select class="form-select" id="anio" name="anio" required>
                            <?php for ($year = date('Y') - 1; $year <= date('Y') + 5; $year++): ?>
                                <option value="<?= $year ?>" <?= $year == date('Y') ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label for="objetivos" class="form-label">Objetivos Estratégicos</label>
                        <textarea class="form-control objetives-textarea" id="objetivos" name="objetivos_estrategicos" 
                                  placeholder="Describe los objetivos estratégicos y metas principales para este año presupuestario..." required></textarea>
                    </div>
                </div>
            </div>

            <!-- Sección de Presupuesto Mensual -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-calendar-month"></i> Presupuesto Mensual Detallado
                </h2>
                
                <div class="meses-grid" id="mesesGrid">
                    <?php
                    $meses = [
                        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
                    ];
                    
                    foreach ($meses as $index => $mes):
                    ?>
                        <div class="mes-card" data-mes="<?= $mes ?>">
                            <div class="mes-title">
                                <span><?= ucfirst($mes) ?></span>
                                <div>
                                    <span class="mes-total" data-mes-total="<?= $mes ?>">$0.00</span>
                                    <span class="month-indicator empty" data-indicator="<?= $mes ?>"></span>
                                </div>
                            </div>
                            <div class="actividades-container" data-mes-actividades="<?= $mes ?>">
                                <!-- Las actividades se agregarán dinámicamente -->
                            </div>
                            <button type="button" class="add-actividad-btn" onclick="addActividad('<?= $mes ?>')">
                                <i class="bi bi-plus-circle"></i> Agregar Actividad
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Resumen Total -->
            <div class="total-section">
                <h3>Total Presupuesto Anual</h3>
                <div class="total-anual" id="totalAnual">$0.00</div>
                <p class="mb-3">Sumatoria de todos los meses</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="estado" class="form-label text-white">Estado del Presupuesto</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="borrador">Borrador</option>
                            <option value="enviado_aprobacion">Enviado para Aprobación</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-light btn-lg w-100">
                            <i class="bi bi-save"></i> Guardar Presupuesto
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let actividadCounter = 0;

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
                            <input type="text" class="form-control" name="actividades[${mes}][${actividadCounter}][nombre]" 
                                   placeholder="Nombre de la actividad" required>
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
                            <input type="text" class="form-control presupuesto-input" 
                                   name="actividades[${mes}][${actividadCounter}][presupuesto]" 
                                   placeholder="$0.00" 
                                   data-mes="${mes}"
                                   oninput="calcularTotales(); formatCurrency(this);">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Mes</label>
                            <input type="hidden" name="actividades[${mes}][${actividadCounter}][mes]" value="${mes}">
                            <input type="text" class="form-control" value="${mes.charAt(0).toUpperCase() + mes.slice(1)}" readonly>
                        </div>
                        <div class="col-12">
                            <textarea class="form-control" name="actividades[${mes}][${actividadCounter}][descripcion]" 
                                      placeholder="Breve descripción de la actividad..." rows="2"></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', actividadHtml);
            updateMonthIndicator(mes);
            calcularTotales();
        }

        function removeActividad(actividadId, mes) {
            const actividad = document.getElementById(actividadId);
            if (actividad) {
                actividad.remove();
                updateMonthIndicator(mes);
                calcularTotales();
            }
        }

        function updateMonthIndicator(mes) {
            const container = document.querySelector(`[data-mes-actividades="${mes}"]`);
            const indicator = document.querySelector(`[data-indicator="${mes}"]`);
            const actividades = container.querySelectorAll('.actividad-item');
            
            if (actividades.length > 0) {
                indicator.classList.remove('empty');
            } else {
                indicator.classList.add('empty');
            }
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

            const totalAnualElement = document.getElementById('totalAnual');
            if (totalAnualElement) {
                totalAnualElement.textContent = '$' + totalAnual.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        // Agregar una actividad por defecto a cada mes
        document.addEventListener('DOMContentLoaded', function() {
            const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            meses.forEach(mes => {
                addActividad(mes);
            });
        });

        // Validar fechas
        document.getElementById('presupuestoForm').addEventListener('submit', function(e) {
            const actividades = document.querySelectorAll('.actividad-item');
            let tieneActividades = false;

            actividades.forEach(actividad => {
                const nombre = actividad.querySelector('input[name*="[nombre]"]').value;
                if (nombre.trim() !== '') {
                    tieneActividades = true;
                }
            });

            if (!tieneActividades) {
                e.preventDefault();
                alert('Debe agregar al menos una actividad al presupuesto.');
                return;
            }

            // Validar que las fechas de fin sean posteriores a las de inicio
            const fechaInicioInputs = document.querySelectorAll('input[name*="[fecha_inicio]"]');
            const fechaFinInputs = document.querySelectorAll('input[name*="[fecha_fin]"]');

            for (let i = 0; i < fechaInicioInputs.length; i++) {
                const inicio = fechaInicioInputs[i].value;
                const fin = fechaFinInputs[i].value;

                if (inicio && fin && new Date(fin) < new Date(inicio)) {
                    e.preventDefault();
                    alert('La fecha de fin debe ser posterior a la fecha de inicio.');
                    return;
                }
            }
        });
    </script>
</body>
</html>
