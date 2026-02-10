<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$rol_sistema = $_SESSION['user_role'] ?? '';
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
$es_admin = ($rol_sistema === 'Admin' || in_array($rol_iglesia, ['Pastor Principal', 'Pastor Ayudante', 'Lider de Red']));

$celula_id = (int)($_GET['id'] ?? 0);
if (!$celula_id) { header('Location: celulas.php'); exit; }

// Obtener datos de la célula
$stmt = $pdo->prepare("
    SELECT c.*, p.nombre as lider_nombre, p.apellido as lider_apellido
    FROM redes_celulas c
    LEFT JOIN redes_personas p ON c.lider_id = p.id
    WHERE c.id = ?
");
$stmt->execute([$celula_id]);
$celula = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$celula) { header('Location: celulas.php'); exit; }

// Obtener miembros actuales
$stmt = $pdo->prepare("
    SELECT mc.*, p.nombre, p.apellido, p.celular, p.email, p.estado as estado_persona, p.rol_iglesia
    FROM redes_miembros_celula mc
    JOIN redes_personas p ON mc.persona_id = p.id
    WHERE mc.celula_id = ? AND mc.estado = 'Activo'
    ORDER BY FIELD(mc.rol_en_celula, 'Líder', 'Anfitrión', 'Colaborador', 'Miembro'), p.apellido, p.nombre
");
$stmt->execute([$celula_id]);
$miembros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Personas disponibles para agregar (no están en esta célula)
$stmt = $pdo->prepare("
    SELECT p.id, p.nombre, p.apellido, p.celular
    FROM redes_personas p
    WHERE p.estado != 'Inactivo'
      AND p.id NOT IN (SELECT persona_id FROM redes_miembros_celula WHERE celula_id = ? AND estado = 'Activo')
    ORDER BY p.apellido, p.nombre
");
$stmt->execute([$celula_id]);
$disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generar_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Miembros - <?= htmlspecialchars($celula['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #667eea; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .top-bar { background: linear-gradient(135deg, var(--primary), #764ba2); color: #fff; padding: .75rem 2rem; }
        .top-bar a { color: rgba(255,255,255,.85); text-decoration: none; margin-left: 1rem; }
        .top-bar a:hover { color: #fff; }
        .main-wrap { max-width: 1000px; margin: 0 auto; padding: 1.5rem; }
        .card-s { background: #fff; border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,.08); margin-bottom: 1.5rem; }
        .miembro-card { display: flex; align-items: center; padding: .75rem; border-radius: 10px; margin-bottom: .5rem; background: #f8f9fa; transition: background .2s; }
        .miembro-card:hover { background: #e9ecef; }
        .miembro-avatar { width: 40px; height: 40px; border-radius: 50%; margin-right: 1rem; object-fit: cover; }
        .rol-badge { padding: .2rem .5rem; border-radius: 8px; font-size: .7rem; font-weight: 600; }
        .rol-lider { background: #667eea; color: #fff; }
        .rol-anfitrion { background: #28a745; color: #fff; }
        .rol-colaborador { background: #ffc107; color: #212529; }
        .rol-miembro { background: #e9ecef; color: #495057; }
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <strong><i class="bi bi-people-fill me-2"></i><?= htmlspecialchars($celula['nombre']) ?></strong>
            <small class="ms-2 opacity-75">Gestión de Miembros</small>
        </div>
        <div>
            <a href="celulas.php"><i class="bi bi-grid-3x3-gap"></i> Células</a>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <div class="main-wrap">
        <!-- Info de la célula -->
        <div class="card-s">
            <div class="row">
                <div class="col-md-4"><strong>Tipo:</strong> <?= $celula['tipo_celula'] ?></div>
                <div class="col-md-4"><strong>Líder:</strong> <?= $celula['lider_nombre'] ? htmlspecialchars($celula['lider_nombre'].' '.$celula['lider_apellido']) : 'Sin asignar' ?></div>
                <div class="col-md-4"><strong>Reunión:</strong> <?= $celula['dia_reunion'] ?? '-' ?> <?= $celula['hora_reunion'] ? substr($celula['hora_reunion'],0,5) : '' ?></div>
            </div>
        </div>

        <!-- Miembros -->
        <div class="card-s">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Miembros (<?= count($miembros) ?>)</h5>
                <?php if ($es_admin): ?>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    <i class="bi bi-person-plus me-1"></i>Agregar Miembro
                </button>
                <?php endif; ?>
            </div>

            <?php if (empty($miembros)): ?>
            <p class="text-muted text-center py-3">No hay miembros en esta célula.</p>
            <?php else: ?>
                <?php foreach ($miembros as $m): ?>
                <div class="miembro-card">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($m['nombre'].' '.$m['apellido']) ?>&background=667eea&color=fff&size=40" class="miembro-avatar">
                    <div class="flex-grow-1">
                        <strong><?= htmlspecialchars($m['apellido'] . ', ' . $m['nombre']) ?></strong>
                        <span class="rol-badge rol-<?= strtolower(str_replace(['í','ó'], ['i','o'], $m['rol_en_celula'])) ?>"><?= $m['rol_en_celula'] ?></span>
                        <div>
                            <?php if ($m['celular']): ?><small><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($m['celular']) ?></small><?php endif; ?>
                            <?php if ($m['email']): ?><small class="ms-2"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($m['email']) ?></small><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($es_admin): ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="quitarMiembro(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nombre'].' '.$m['apellido']) ?>')" title="Quitar"><i class="bi bi-x-lg"></i></button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="text-center">
            <a href="celulas.php" class="btn btn-outline-primary me-2"><i class="bi bi-arrow-left"></i> Células</a>
            <a href="asistencia.php?celula_id=<?= $celula_id ?>" class="btn btn-outline-warning"><i class="bi bi-calendar-check"></i> Asistencia</a>
        </div>
    </div>

    <!-- Modal Agregar Miembro -->
    <?php if ($es_admin): ?>
    <div class="modal fade" id="modalAgregar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Agregar Miembro</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="formAgregar">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="celula_id" value="<?= $celula_id ?>">
                        <div class="mb-3">
                            <label class="form-label">Persona</label>
                            <select class="form-select" name="persona_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($disponibles as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['apellido'].', '.$d['nombre']) ?> <?= $d['celular'] ? '('.$d['celular'].')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rol en Célula</label>
                            <select class="form-select" name="rol_en_celula">
                                <option value="Miembro">Miembro</option>
                                <option value="Colaborador">Colaborador</option>
                                <option value="Anfitrión">Anfitrión</option>
                                <option value="Líder">Líder</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="agregarMiembro()">Agregar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function agregarMiembro() {
        $.post('api_celulas.php?action=agregar_miembro', $('#formAgregar').serialize(), function(r) {
            if (r.success) location.reload();
            else alert('Error: ' + r.message);
        }, 'json').fail(() => alert('Error de conexión'));
    }

    function quitarMiembro(id, nombre) {
        if (!confirm('¿Quitar a ' + nombre + ' de esta célula?')) return;
        $.post('api_celulas.php?action=quitar_miembro', { id: id, csrf_token: '<?= $csrf_token ?>' }, function(r) {
            if (r.success) location.reload();
            else alert('Error: ' + r.message);
        }, 'json');
    }
    </script>
</body>
</html>
