<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$rol_sistema = $_SESSION['user_role'] ?? '';
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
$es_admin = ($rol_sistema === 'Admin' || in_array($rol_iglesia, ['Pastor Principal', 'Pastor Ayudante']));

$celulas_vis = redes_celulas_visibles($pdo, $usuario_id);

// Obtener células
$celulas = [];
if (!empty($celulas_vis)) {
    $ph = str_repeat('?,', count($celulas_vis) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT c.*, p.nombre as lider_nombre, p.apellido as lider_apellido,
            par.nombre as red_padre,
            (SELECT COUNT(*) FROM redes_miembros_celula mc WHERE mc.celula_id = c.id AND mc.estado = 'Activo') as miembros_count,
            (SELECT COUNT(*) FROM redes_celulas c2 WHERE c2.parent_id = c.id AND c2.estado = 'Activa') as subcelulas_count
        FROM redes_celulas c
        LEFT JOIN redes_personas p ON c.lider_id = p.id
        LEFT JOIN redes_celulas par ON c.parent_id = par.id
        WHERE c.id IN ($ph) AND c.estado = 'Activa'
        ORDER BY c.parent_id IS NULL DESC, c.nombre
    ");
    $stmt->execute($celulas_vis);
    $celulas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Líderes disponibles para select
$lideres = $pdo->query("
    SELECT id, nombre, apellido, rol_iglesia FROM redes_personas
    WHERE rol_iglesia IN ('Pastor Principal','Pastor Ayudante','Lider de Red','Lider de Célula')
    AND estado != 'Inactivo'
    ORDER BY apellido, nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Redes padre para select
$redes_padre = $pdo->query("
    SELECT id, nombre FROM redes_celulas WHERE estado = 'Activa' ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generar_token_csrf();

function tipoColor($tipo) {
    $colors = ['Juvenil'=>'#ffc107','Jóvenes'=>'#17a2b8','Matrimonios'=>'#e83e8c','Hombres'=>'#6f42c1','Mujeres'=>'#fd7e14','Niños'=>'#20c997','Mixta'=>'#6c757d'];
    return $colors[$tipo] ?? '#667eea';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Células - Redes y Células</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --primary: #667eea; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .top-bar { background: linear-gradient(135deg, var(--primary), #764ba2); color: #fff; padding: .75rem 2rem; }
        .top-bar a { color: rgba(255,255,255,.85); text-decoration: none; margin-left: 1rem; }
        .top-bar a:hover { color: #fff; }
        .main-wrap { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        .card-s { background: #fff; border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,.08); margin-bottom: 1.5rem; }
        .tipo-badge { padding: .25rem .6rem; border-radius: 15px; font-size: .75rem; font-weight: 600; color: #fff; display: inline-block; }
        .estado-badge { padding: .25rem .6rem; border-radius: 15px; font-size: .75rem; font-weight: 500; }
        .estado-activa { background: #d4edda; color: #155724; }
        .estado-inactiva { background: #f8d7da; color: #721c24; }
        .btn-acc { padding: .2rem .4rem; font-size: .78rem; }
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <strong><i class="bi bi-grid-3x3-gap-fill me-2"></i>Células</strong>
            <small class="ms-2 opacity-75">Gestión de células y redes</small>
        </div>
        <div>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="personas.php"><i class="bi bi-people"></i> Personas</a>
            <a href="organigrama.php"><i class="bi bi-diagram-3"></i> Organigrama</a>
            <a href="mapa_celulas.php"><i class="bi bi-geo-alt"></i> Mapa</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <div class="main-wrap">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Listado de Células</h4>
            <?php if ($es_admin): ?>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCelula">
                <i class="bi bi-plus-circle me-1"></i>Nueva Célula
            </button>
            <?php endif; ?>
        </div>

        <div class="card-s">
            <div class="table-responsive">
                <table class="table table-hover table-sm" id="tablaCelulas">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Líder</th>
                            <th>Red Padre</th>
                            <th>Día/Hora</th>
                            <th>Dirección</th>
                            <th>Miembros</th>
                            <th>Subcélulas</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($celulas)): ?>
                        <tr><td colspan="10" class="text-center py-4 text-muted">No hay células registradas.</td></tr>
                    <?php else: foreach ($celulas as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                            <td><span class="tipo-badge" style="background:<?= tipoColor($c['tipo_celula']) ?>"><?= $c['tipo_celula'] ?></span></td>
                            <td><?= $c['lider_nombre'] ? htmlspecialchars($c['lider_nombre'] . ' ' . $c['lider_apellido']) : '<span class="text-muted">-</span>' ?></td>
                            <td><small><?= $c['red_padre'] ? htmlspecialchars($c['red_padre']) : '<span class="text-muted">Raíz</span>' ?></small></td>
                            <td><small><?= $c['dia_reunion'] ? $c['dia_reunion'] . ' ' . ($c['hora_reunion'] ? substr($c['hora_reunion'],0,5) : '') : '-' ?></small></td>
                            <td><small class="text-muted"><?= htmlspecialchars($c['direccion'] ?: '-') ?></small></td>
                            <td class="text-center"><span class="badge bg-primary"><?= $c['miembros_count'] ?></span></td>
                            <td class="text-center"><span class="badge bg-secondary"><?= $c['subcelulas_count'] ?></span></td>
                            <td><span class="estado-badge estado-<?= strtolower($c['estado']) ?>"><?= $c['estado'] ?></span></td>
                            <td>
                                <a href="celula_miembros.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-info btn-acc" title="Miembros"><i class="bi bi-people"></i></a>
                                <a href="asistencia.php?celula_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning btn-acc" title="Asistencia"><i class="bi bi-calendar-check"></i></a>
                                <?php if ($es_admin): ?>
                                <button class="btn btn-sm btn-outline-primary btn-acc" onclick="editarCelula(<?= $c['id'] ?>)" title="Editar"><i class="bi bi-pencil"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-center">
            <a href="index.php" class="btn btn-outline-primary me-2"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="../menu_moderno.php" class="btn btn-primary"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <!-- Modal Nueva/Editar Célula -->
    <div class="modal fade" id="modalCelula" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="modalCelulaTitle">Nueva Célula</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="formCelula">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="id" id="celula_id" value="">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" id="cel_nombre" required></div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="tipo_celula" id="cel_tipo">
                                    <option value="Mixta">Mixta</option><option value="Juvenil">Juvenil</option>
                                    <option value="Jóvenes">Jóvenes</option><option value="Matrimonios">Matrimonios</option>
                                    <option value="Hombres">Hombres</option><option value="Mujeres">Mujeres</option>
                                    <option value="Niños">Niños</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estado" id="cel_estado">
                                    <option value="Activa">Activa</option><option value="Inactiva">Inactiva</option>
                                    <option value="En Formación">En Formación</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Líder</label>
                                <select class="form-select" name="lider_id" id="cel_lider">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($lideres as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['apellido'] . ', ' . $l['nombre']) ?> (<?= $l['rol_iglesia'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Red Padre</label>
                                <select class="form-select" name="parent_id" id="cel_parent">
                                    <option value="">Ninguna (Red Principal)</option>
                                    <?php foreach ($redes_padre as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" id="cel_desc" rows="2"></textarea></div>
                            <div class="col-md-8"><label class="form-label">Dirección</label><input type="text" class="form-control" name="direccion" id="cel_dir"></div>
                            <div class="col-md-4">
                                <label class="form-label">Día de Reunión</label>
                                <select class="form-select" name="dia_reunion" id="cel_dia">
                                    <option value="">Seleccionar...</option>
                                    <option>Lunes</option><option>Martes</option><option>Miércoles</option>
                                    <option>Jueves</option><option>Viernes</option><option>Sábado</option><option>Domingo</option>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Hora</label><input type="time" class="form-control" name="hora_reunion" id="cel_hora"></div>
                            <div class="col-md-3">
                                <label class="form-label">Frecuencia</label>
                                <select class="form-select" name="frecuencia" id="cel_frec">
                                    <option value="Semanal">Semanal</option><option value="Quincenal">Quincenal</option><option value="Mensual">Mensual</option>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Latitud</label><input type="text" class="form-control" name="latitud" id="cel_lat" placeholder="-34.6037"></div>
                            <div class="col-md-3"><label class="form-label">Longitud</label><input type="text" class="form-control" name="longitud" id="cel_lng" placeholder="-58.3816"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCelula()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#tablaCelulas').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            pageLength: 25, order: [[0, 'asc']]
        });
    });

    function guardarCelula() {
        $.post('api_celulas.php?action=guardar', $('#formCelula').serialize(), function(r) {
            if (r.success) location.reload();
            else alert('Error: ' + r.message);
        }, 'json').fail(() => alert('Error de conexión'));
    }

    function editarCelula(id) {
        $.getJSON('api_celulas.php?action=obtener&id=' + id, function(r) {
            if (!r.success) { alert(r.message); return; }
            const c = r.data;
            $('#celula_id').val(c.id);
            $('#cel_nombre').val(c.nombre);
            $('#cel_tipo').val(c.tipo_celula);
            $('#cel_estado').val(c.estado);
            $('#cel_lider').val(c.lider_id || '');
            $('#cel_parent').val(c.parent_id || '');
            $('#cel_desc').val(c.descripcion);
            $('#cel_dir').val(c.direccion);
            $('#cel_dia').val(c.dia_reunion || '');
            $('#cel_hora').val(c.hora_reunion || '');
            $('#cel_frec').val(c.frecuencia);
            $('#cel_lat').val(c.latitud);
            $('#cel_lng').val(c.longitud);
            $('#modalCelulaTitle').text('Editar Célula');
            new bootstrap.Modal('#modalCelula').show();
        });
    }
    </script>
</body>
</html>
