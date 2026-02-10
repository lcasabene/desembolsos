<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$rol_sistema = $_SESSION['user_role'] ?? '';
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
$es_admin = ($rol_sistema === 'Admin' || $rol_iglesia === 'Pastor Principal');

// Filtros
$busqueda = $_GET['busqueda'] ?? '';
$estado = $_GET['estado'] ?? '';
$oficio = $_GET['oficio'] ?? '';
$celula_id = $_GET['celula_id'] ?? '';

// Construir consulta con tablas redes_
$where = ["1=1"];
$params = [];

if ($busqueda !== '') {
    $like = "%$busqueda%";
    $where[] = "(p.nombre LIKE ? OR p.apellido LIKE ? OR p.dni LIKE ? OR p.email LIKE ? OR p.celular LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($estado !== '') {
    $where[] = "p.estado = ?";
    $params[] = $estado;
}
if ($oficio !== '') {
    $where[] = "p.oficio_profesion LIKE ?";
    $params[] = "%$oficio%";
}
if ($celula_id !== '') {
    $where[] = "mc.celula_id = ?";
    $params[] = $celula_id;
}

// Visibilidad según jerarquía
if (!$es_admin && !redes_tiene_permiso($pdo, $usuario_id, 'Ver Todo')) {
    $vis = redes_celulas_visibles($pdo, $usuario_id);
    if (!empty($vis)) {
        $ph = str_repeat('?,', count($vis) - 1) . '?';
        $where[] = "(mc.celula_id IN ($ph) OR mc.celula_id IS NULL)";
        $params = array_merge($params, $vis);
    }
}

$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT
        p.*,
        prov.nombre as provincia_nombre,
        ciu.nombre as ciudad_nombre,
        bar.nombre as barrio_nombre,
        c.nombre as celula_nombre,
        mc.rol_en_celula,
        TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) as edad
    FROM redes_personas p
    LEFT JOIN redes_provincias prov ON p.provincia_id = prov.id
    LEFT JOIN redes_ciudades ciu ON p.ciudad_id = ciu.id
    LEFT JOIN redes_barrios bar ON p.barrio_id = bar.id
    LEFT JOIN redes_miembros_celula mc ON p.id = mc.persona_id AND mc.estado = 'Activo'
    LEFT JOIN redes_celulas c ON mc.celula_id = c.id
    WHERE $where_sql
    ORDER BY p.apellido, p.nombre
");
$stmt->execute($params);
$personas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Oficios únicos para filtro
$oficios = $pdo->query("
    SELECT DISTINCT oficio_profesion FROM redes_personas
    WHERE oficio_profesion IS NOT NULL AND oficio_profesion != ''
    ORDER BY oficio_profesion
")->fetchAll(PDO::FETCH_COLUMN);

// Células visibles para filtro
$celulas_filtro = [];
$vis_ids = redes_celulas_visibles($pdo, $usuario_id);
if (!empty($vis_ids)) {
    $ph = str_repeat('?,', count($vis_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, nombre FROM redes_celulas WHERE id IN ($ph) AND estado='Activa' ORDER BY nombre");
    $stmt->execute($vis_ids);
    $celulas_filtro = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Contadores
$total = count($personas);
$cnt_miembros = count(array_filter($personas, fn($p) => $p['estado'] === 'Miembro'));
$cnt_visitantes = count(array_filter($personas, fn($p) => $p['estado'] === 'Visitante'));
$cnt_con_celula = count(array_filter($personas, fn($p) => !empty($p['celula_nombre'])));

$csrf_token = generar_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personas - Redes y Células</title>
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
        .stat-n { font-size: 1.8rem; font-weight: 700; color: var(--primary); }
        .stat-l { color: #6c757d; font-size: .85rem; }
        .badge-estado { padding: .3rem .6rem; border-radius: 20px; font-size: .8rem; font-weight: 500; }
        .badge-miembro { background: #d4edda; color: #155724; }
        .badge-visitante { background: #fff3cd; color: #856404; }
        .badge-inactivo { background: #f8d7da; color: #721c24; }
        .badge-rol { background: #e9ecef; color: #495057; padding: .2rem .5rem; border-radius: 8px; font-size: .75rem; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .btn-acc { padding: .2rem .4rem; font-size: .78rem; }
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <strong><i class="bi bi-people-fill me-2"></i>Personas</strong>
            <small class="ms-2 opacity-75">Directorio de miembros y visitantes</small>
        </div>
        <div>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="celulas.php"><i class="bi bi-grid-3x3-gap"></i> Células</a>
            <a href="organigrama.php"><i class="bi bi-diagram-3"></i> Organigrama</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <div class="main-wrap">
        <!-- Estadísticas -->
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="card-s text-center"><div class="stat-n"><?= $total ?></div><div class="stat-l">Total</div></div></div>
            <div class="col-6 col-md-3"><div class="card-s text-center"><div class="stat-n"><?= $cnt_miembros ?></div><div class="stat-l">Miembros</div></div></div>
            <div class="col-6 col-md-3"><div class="card-s text-center"><div class="stat-n"><?= $cnt_visitantes ?></div><div class="stat-l">Visitantes</div></div></div>
            <div class="col-6 col-md-3"><div class="card-s text-center"><div class="stat-n"><?= $cnt_con_celula ?></div><div class="stat-l">Con Célula</div></div></div>
        </div>

        <!-- Filtros + Acciones -->
        <div class="card-s">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Buscar</label>
                    <input type="text" class="form-control form-control-sm" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Nombre, DNI, email, celular...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Estado</label>
                    <select class="form-select form-select-sm" name="estado">
                        <option value="">Todos</option>
                        <option value="Miembro" <?= $estado==='Miembro'?'selected':'' ?>>Miembro</option>
                        <option value="Visitante" <?= $estado==='Visitante'?'selected':'' ?>>Visitante</option>
                        <option value="Inactivo" <?= $estado==='Inactivo'?'selected':'' ?>>Inactivo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Oficio</label>
                    <select class="form-select form-select-sm" name="oficio">
                        <option value="">Todos</option>
                        <?php foreach ($oficios as $of): ?>
                        <option value="<?= htmlspecialchars($of) ?>" <?= $oficio===$of?'selected':'' ?>><?= htmlspecialchars($of) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Célula</label>
                    <select class="form-select form-select-sm" name="celula_id">
                        <option value="">Todas</option>
                        <?php foreach ($celulas_filtro as $cel): ?>
                        <option value="<?= $cel['id'] ?>" <?= $celula_id==$cel['id']?'selected':'' ?>><?= htmlspecialchars($cel['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Filtrar</button>
                    <a href="personas.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevaPersona"><i class="bi bi-person-plus"></i> Nueva</button>
                </div>
            </form>
        </div>

        <!-- Tabla -->
        <div class="card-s">
            <div class="table-responsive">
                <table class="table table-hover table-sm" id="tablaPersonas">
                    <thead class="table-light">
                        <tr>
                            <th></th>
                            <th>Nombre</th>
                            <th>DNI</th>
                            <th>Contacto</th>
                            <th>Ubicación</th>
                            <th>Oficio</th>
                            <th>Estado</th>
                            <th>Célula</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($personas)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted"><i class="bi bi-search" style="font-size:2rem"></i><br>No se encontraron personas.</td></tr>
                    <?php else: foreach ($personas as $p): ?>
                        <tr>
                            <td><img src="<?= $p['foto_url'] ?: 'https://ui-avatars.com/api/?name='.urlencode($p['nombre'].' '.$p['apellido']).'&background=667eea&color=fff&size=36' ?>" class="avatar"></td>
                            <td>
                                <strong><?= htmlspecialchars($p['apellido'].', '.$p['nombre']) ?></strong>
                                <?php if ($p['edad']): ?><br><small class="text-muted"><?= $p['edad'] ?> años</small><?php endif; ?>
                                <?php if ($p['rol_iglesia'] !== 'Miembro'): ?><br><span class="badge-rol"><?= $p['rol_iglesia'] ?></span><?php endif; ?>
                            </td>
                            <td><small><?= htmlspecialchars($p['dni'] ?? '-') ?></small></td>
                            <td>
                                <?php if ($p['celular']): ?><small><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($p['celular']) ?></small><br><?php endif; ?>
                                <?php if ($p['email']): ?><small><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($p['email']) ?></small><?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars(implode(', ', array_filter([$p['barrio_nombre'],$p['ciudad_nombre']]))) ?: '-' ?></small></td>
                            <td><small><?= htmlspecialchars($p['oficio_profesion'] ?: '-') ?></small></td>
                            <td><span class="badge-estado badge-<?= strtolower($p['estado']) ?>"><?= $p['estado'] ?></span></td>
                            <td>
                                <?php if ($p['celula_nombre']): ?>
                                <small><?= htmlspecialchars($p['celula_nombre']) ?></small>
                                <?php if ($p['rol_en_celula']): ?><br><span class="badge-rol"><?= $p['rol_en_celula'] ?></span><?php endif; ?>
                                <?php else: ?><small class="text-muted">-</small><?php endif; ?>
                            </td>
                            <td>
                                <a href="persona_editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary btn-acc" title="Editar"><i class="bi bi-pencil"></i></a>
                                <?php if ($es_admin): ?>
                                <button class="btn btn-sm btn-outline-danger btn-acc" onclick="eliminarPersona(<?= $p['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
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

    <!-- Modal Nueva Persona -->
    <div class="modal fade" id="modalNuevaPersona" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Nueva Persona</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="formNuevaPersona">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" required></div>
                            <div class="col-md-6"><label class="form-label">Apellido *</label><input type="text" class="form-control" name="apellido" required></div>
                            <div class="col-md-4"><label class="form-label">DNI</label><input type="text" class="form-control" name="dni"></div>
                            <div class="col-md-4"><label class="form-label">CUIT</label><input type="text" class="form-control" name="cuit"></div>
                            <div class="col-md-4"><label class="form-label">Fecha Nacimiento</label><input type="date" class="form-control" name="fecha_nacimiento"></div>
                            <div class="col-md-6"><label class="form-label">Celular</label><input type="tel" class="form-control" name="celular"></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
                            <div class="col-12"><label class="form-label">Dirección</label><input type="text" class="form-control" name="direccion"></div>
                            <div class="col-md-4">
                                <label class="form-label">Provincia</label>
                                <select class="form-select" name="provincia_id" id="provinciaSelect"><option value="">Seleccionar...</option></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ciudad</label>
                                <select class="form-select" name="ciudad_id" id="ciudadSelect"><option value="">Seleccionar...</option></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Barrio</label>
                                <select class="form-select" name="barrio_id" id="barrioSelect"><option value="">Seleccionar...</option></select>
                            </div>
                            <div class="col-md-4"><label class="form-label">Oficio/Profesión</label><input type="text" class="form-control" name="oficio_profesion"></div>
                            <div class="col-md-4">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estado">
                                    <option value="Visitante">Visitante</option><option value="Miembro">Miembro</option><option value="Inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Rol Iglesia</label>
                                <select class="form-select" name="rol_iglesia">
                                    <option value="Miembro">Miembro</option><option value="Servidor">Servidor</option>
                                    <option value="Lider de Célula">Líder de Célula</option><option value="Lider de Red">Líder de Red</option>
                                    <option value="Pastor Ayudante">Pastor Ayudante</option><option value="Pastor Principal">Pastor Principal</option>
                                </select>
                            </div>
                            <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="2"></textarea></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarPersona()">Guardar</button>
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
        $('#tablaPersonas').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            pageLength: 25,
            order: [[1, 'asc']]
        });
        cargarProvincias();
    });

    function cargarProvincias() {
        $.getJSON('api_ubicaciones.php?action=provincias', function(data) {
            let opts = '<option value="">Seleccionar...</option>';
            data.forEach(p => opts += `<option value="${p.id}">${p.nombre}</option>`);
            $('#provinciaSelect').html(opts);
        });
    }

    $('#provinciaSelect').change(function() {
        let id = $(this).val();
        $('#ciudadSelect, #barrioSelect').html('<option value="">Seleccionar...</option>');
        if (!id) return;
        $.getJSON('api_ubicaciones.php?action=ciudades&provincia_id=' + id, function(data) {
            let opts = '<option value="">Seleccionar...</option>';
            data.forEach(c => opts += `<option value="${c.id}">${c.nombre}</option>`);
            $('#ciudadSelect').html(opts);
        });
    });

    $('#ciudadSelect').change(function() {
        let id = $(this).val();
        $('#barrioSelect').html('<option value="">Seleccionar...</option>');
        if (!id) return;
        $.getJSON('api_ubicaciones.php?action=barrios&ciudad_id=' + id, function(data) {
            let opts = '<option value="">Seleccionar...</option>';
            data.forEach(b => opts += `<option value="${b.id}">${b.nombre}</option>`);
            $('#barrioSelect').html(opts);
        });
    });

    function guardarPersona() {
        $.post('api_personas.php?action=guardar', $('#formNuevaPersona').serialize(), function(r) {
            if (r.success) { location.reload(); }
            else { alert('Error: ' + r.message); }
        }, 'json').fail(() => alert('Error de conexión'));
    }

    function eliminarPersona(id) {
        if (!confirm('¿Eliminar esta persona?')) return;
        $.post('api_personas.php?action=eliminar', { id: id, csrf_token: '<?= $csrf_token ?>' }, function(r) {
            if (r.success) location.reload();
            else alert('Error: ' + r.message);
        }, 'json').fail(() => alert('Error de conexión'));
    }
    </script>
</body>
</html>
