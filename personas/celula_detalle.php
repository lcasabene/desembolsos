<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$rol_sistema = $_SESSION['user_role'] ?? '';
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
$es_admin = ($rol_sistema === 'Admin' || in_array($rol_iglesia, ['Pastor Principal', 'Pastor Ayudante', 'Lider de Red']));

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: celulas.php'); exit; }

// Modo edición
$editando = isset($_GET['editar']) && $es_admin;
$mensaje = '';
$error = '';

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $es_admin && verificar_token_csrf($_POST['csrf_token'] ?? '')) {
    try {
        $campos = [
            'nombre' => sanitizar_entrada($_POST['nombre'] ?? ''),
            'descripcion' => sanitizar_entrada($_POST['descripcion'] ?? ''),
            'lider_id' => ($_POST['lider_id'] ?? '') ?: null,
            'parent_id' => ($_POST['parent_id'] ?? '') ?: null,
            'direccion' => sanitizar_entrada($_POST['direccion'] ?? ''),
            'provincia_id' => ($_POST['provincia_id'] ?? '') ?: null,
            'ciudad_id' => ($_POST['ciudad_id'] ?? '') ?: null,
            'barrio_id' => ($_POST['barrio_id'] ?? '') ?: null,
            'latitud' => ($_POST['latitud'] ?? '') ?: null,
            'longitud' => ($_POST['longitud'] ?? '') ?: null,
            'telefono' => sanitizar_entrada($_POST['telefono'] ?? ''),
            'email' => sanitizar_entrada($_POST['email'] ?? ''),
            'dia_reunion' => sanitizar_entrada($_POST['dia_reunion'] ?? ''),
            'hora_reunion' => ($_POST['hora_reunion'] ?? '') ?: null,
            'frecuencia' => sanitizar_entrada($_POST['frecuencia'] ?? 'Semanal'),
            'tipo_celula' => sanitizar_entrada($_POST['tipo_celula'] ?? 'Mixta'),
            'estado' => sanitizar_entrada($_POST['estado'] ?? 'Activa'),
            'fecha_inicio' => ($_POST['fecha_inicio'] ?? '') ?: null,
            'observaciones' => sanitizar_entrada($_POST['observaciones'] ?? ''),
        ];
        if (!$campos['nombre']) { throw new Exception('El nombre es obligatorio'); }

        $sets = [];
        $params = [];
        foreach ($campos as $col => $val) {
            $sets[] = "$col = ?";
            $params[] = $val;
        }
        $params[] = $id;
        $pdo->prepare("UPDATE redes_celulas SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        $mensaje = 'Célula actualizada correctamente';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener datos de la célula
$stmt = $pdo->prepare("
    SELECT c.*,
        p.nombre as lider_nombre, p.apellido as lider_apellido, p.celular as lider_celular,
        p.email as lider_email, p.foto_url as lider_foto, p.rol_iglesia as lider_rol,
        par.nombre as red_padre_nombre, par.tipo_celula as red_padre_tipo,
        prov.nombre as provincia_nombre, ciu.nombre as ciudad_nombre, bar.nombre as barrio_nombre
    FROM redes_celulas c
    LEFT JOIN redes_personas p ON c.lider_id = p.id
    LEFT JOIN redes_celulas par ON c.parent_id = par.id
    LEFT JOIN redes_provincias prov ON c.provincia_id = prov.id
    LEFT JOIN redes_ciudades ciu ON c.ciudad_id = ciu.id
    LEFT JOIN redes_barrios bar ON c.barrio_id = bar.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$celula = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$celula) { header('Location: celulas.php'); exit; }

// Miembros
$stmt = $pdo->prepare("
    SELECT mc.*, p.nombre, p.apellido, p.celular, p.email, p.foto_url, p.rol_iglesia
    FROM redes_miembros_celula mc
    JOIN redes_personas p ON mc.persona_id = p.id
    WHERE mc.celula_id = ? AND mc.estado = 'Activo'
    ORDER BY FIELD(mc.rol_en_celula, 'Líder', 'Anfitrión', 'Colaborador', 'Miembro'), p.apellido
");
$stmt->execute([$id]);
$miembros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Subcélulas
$stmt = $pdo->prepare("
    SELECT c.*, p.nombre as lider_nombre, p.apellido as lider_apellido,
        (SELECT COUNT(*) FROM redes_miembros_celula mc WHERE mc.celula_id = c.id AND mc.estado = 'Activo') as miembros_count
    FROM redes_celulas c
    LEFT JOIN redes_personas p ON c.lider_id = p.id
    WHERE c.parent_id = ? AND c.estado = 'Activa'
    ORDER BY c.nombre
");
$stmt->execute([$id]);
$subcelulas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Últimas reuniones
$stmt = $pdo->prepare("
    SELECT a.*, p.nombre as lider_nombre, p.apellido as lider_apellido
    FROM redes_asistencia a
    LEFT JOIN redes_personas p ON a.lider_id = p.id
    WHERE a.celula_id = ?
    ORDER BY a.fecha_reunion DESC LIMIT 5
");
$stmt->execute([$id]);
$reuniones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas de asistencia
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_reuniones,
        COALESCE(AVG(total_asistencia), 0) as promedio,
        COALESCE(MAX(total_asistencia), 0) as max_asistencia,
        COALESCE(SUM(ofrenda), 0) as total_ofrenda,
        MAX(fecha_reunion) as ultima_reunion
    FROM redes_asistencia WHERE celula_id = ?
");
$stmt->execute([$id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Líderes disponibles (para edición)
$lideres = [];
if ($editando) {
    $lideres = $pdo->query("SELECT id, nombre, apellido, rol_iglesia FROM redes_personas WHERE estado = 'Miembro' ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC);
}

// Células padre disponibles (para edición)
$celulas_padre = [];
if ($editando) {
    $stmt = $pdo->prepare("SELECT id, nombre FROM redes_celulas WHERE id != ? AND estado = 'Activa' ORDER BY nombre");
    $stmt->execute([$id]);
    $celulas_padre = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$csrf_token = generar_token_csrf();

function tipoColorDetalle($tipo) {
    $colores = [
        'Juvenil' => '#ffc107', 'Jóvenes' => '#17a2b8', 'Matrimonios' => '#e83e8c',
        'Hombres' => '#6f42c1', 'Mujeres' => '#fd7e14', 'Niños' => '#20c997', 'Mixta' => '#6c757d'
    ];
    return $colores[$tipo] ?? '#667eea';
}
$color = tipoColorDetalle($celula['tipo_celula']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($celula['nombre']) ?> - Detalle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #667eea; --accent: <?= $color ?>; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .top-bar { background: linear-gradient(135deg, var(--primary), #764ba2); color: #fff; padding: .75rem 2rem; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
        .top-bar a { color: rgba(255,255,255,.85); text-decoration: none; margin-left: 1rem; font-size: .9rem; }
        .top-bar a:hover { color: #fff; }
        .main-wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
        .card-s { background: #fff; border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,.08); margin-bottom: 1.5rem; }

        .hero-celula { background: linear-gradient(135deg, <?= $color ?>22, <?= $color ?>08); border: 2px solid <?= $color ?>44; border-radius: 18px; padding: 2rem; margin-bottom: 1.5rem; }
        .hero-celula .tipo-badge { background: var(--accent); color: #fff; padding: 4px 14px; border-radius: 20px; font-size: .8rem; font-weight: 600; }
        .hero-celula h2 { font-weight: 700; color: #2c3e50; margin: .5rem 0; }
        .hero-celula .meta-item { font-size: .9rem; color: #6c757d; margin-right: 1.5rem; }
        .hero-celula .meta-item i { color: var(--accent); margin-right: .3rem; }

        .stat-mini { text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 12px; }
        .stat-mini .num { font-size: 1.8rem; font-weight: 700; color: var(--accent); }
        .stat-mini .lbl { font-size: .8rem; color: #6c757d; }

        .miembro-row { display: flex; align-items: center; padding: .6rem .8rem; border-radius: 10px; margin-bottom: .4rem; transition: background .15s; }
        .miembro-row:hover { background: #f0f2f5; }
        .miembro-avatar { width: 36px; height: 36px; border-radius: 50%; margin-right: .75rem; object-fit: cover; }
        .rol-pill { padding: 2px 8px; border-radius: 8px; font-size: .7rem; font-weight: 600; }
        .rol-lider { background: var(--primary); color: #fff; }
        .rol-anfitrion { background: #28a745; color: #fff; }
        .rol-colaborador { background: #ffc107; color: #212529; }
        .rol-miembro { background: #e9ecef; color: #495057; }

        .reunion-row { padding: .6rem 0; border-bottom: 1px solid #f0f2f5; }
        .reunion-row:last-child { border-bottom: none; }

        .subcelula-card { border-left: 4px solid var(--accent); padding: .75rem 1rem; border-radius: 0 10px 10px 0; background: #f8f9fa; margin-bottom: .5rem; transition: all .2s; }
        .subcelula-card:hover { background: #e9ecef; transform: translateX(4px); }
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div><strong><i class="bi bi-grid-3x3-gap-fill me-2"></i>Detalle de Célula</strong></div>
        <div>
            <a href="organigrama.php"><i class="bi bi-diagram-3"></i> Organigrama</a>
            <a href="celulas.php"><i class="bi bi-grid-3x3-gap"></i> Células</a>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú</a>
        </div>
    </div>

    <div class="main-wrap">
        <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (!$editando): ?>
        <!-- ===== MODO VER ===== -->

        <!-- Hero -->
        <div class="hero-celula">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <span class="tipo-badge"><?= $celula['tipo_celula'] ?></span>
                    <?php if ($celula['red_padre_nombre']): ?>
                    <span class="badge bg-light text-dark ms-1"><i class="bi bi-share me-1"></i><?= htmlspecialchars($celula['red_padre_nombre']) ?></span>
                    <?php else: ?>
                    <span class="badge bg-dark ms-1">Red Principal</span>
                    <?php endif; ?>
                    <h2><?= htmlspecialchars($celula['nombre']) ?></h2>
                    <?php if ($celula['descripcion']): ?>
                    <p class="text-muted mb-2"><?= htmlspecialchars($celula['descripcion']) ?></p>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap">
                        <?php if ($celula['dia_reunion']): ?>
                        <span class="meta-item"><i class="bi bi-calendar3"></i><?= $celula['dia_reunion'] ?> <?= $celula['hora_reunion'] ? substr($celula['hora_reunion'],0,5) : '' ?> (<?= $celula['frecuencia'] ?>)</span>
                        <?php endif; ?>
                        <?php if ($celula['direccion']): ?>
                        <span class="meta-item"><i class="bi bi-geo-alt"></i><?= htmlspecialchars($celula['direccion']) ?></span>
                        <?php endif; ?>
                        <?php if ($celula['telefono']): ?>
                        <span class="meta-item"><i class="bi bi-telephone"></i><?= htmlspecialchars($celula['telefono']) ?></span>
                        <?php endif; ?>
                        <?php if ($celula['fecha_inicio']): ?>
                        <span class="meta-item"><i class="bi bi-flag"></i>Desde <?= redes_formato_fecha($celula['fecha_inicio']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($es_admin): ?>
                <div class="d-flex gap-2 mt-2">
                    <a href="?id=<?= $id ?>&editar=1" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Editar</a>
                    <a href="celula_miembros.php?id=<?= $id ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-people me-1"></i>Miembros</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Líder -->
            <?php if ($celula['lider_nombre']): ?>
            <div class="d-flex align-items-center mt-3 p-2 rounded" style="background: rgba(255,255,255,.6);">
                <img src="<?= $celula['lider_foto'] ?: 'https://ui-avatars.com/api/?name='.urlencode($celula['lider_nombre'].' '.$celula['lider_apellido']).'&background=667eea&color=fff&size=44' ?>" class="rounded-circle me-2" width="44" height="44">
                <div>
                    <strong><?= htmlspecialchars($celula['lider_nombre'].' '.$celula['lider_apellido']) ?></strong>
                    <span class="badge bg-primary ms-1"><?= $celula['lider_rol'] ?></span>
                    <div class="text-muted" style="font-size:.85rem">
                        <?php if ($celula['lider_celular']): ?><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($celula['lider_celular']) ?><?php endif; ?>
                        <?php if ($celula['lider_email']): ?><i class="bi bi-envelope ms-2 me-1"></i><?= htmlspecialchars($celula['lider_email']) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3"><div class="stat-mini"><div class="num"><?= count($miembros) ?></div><div class="lbl">Miembros</div></div></div>
            <div class="col-6 col-md-3"><div class="stat-mini"><div class="num"><?= count($subcelulas) ?></div><div class="lbl">Subcélulas</div></div></div>
            <div class="col-6 col-md-3"><div class="stat-mini"><div class="num"><?= $stats['total_reuniones'] ?></div><div class="lbl">Reuniones</div></div></div>
            <div class="col-6 col-md-3"><div class="stat-mini"><div class="num"><?= number_format($stats['promedio'], 1) ?></div><div class="lbl">Prom. Asistencia</div></div></div>
        </div>

        <div class="row g-3">
            <!-- Miembros -->
            <div class="col-lg-6">
                <div class="card-s">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-people me-2" style="color:var(--accent)"></i>Miembros (<?= count($miembros) ?>)</h5>
                        <?php if ($es_admin): ?>
                        <a href="celula_miembros.php?id=<?= $id ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-person-plus"></i></a>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($miembros)): ?>
                    <p class="text-muted text-center py-3">No hay miembros asignados.</p>
                    <?php else: ?>
                        <?php foreach ($miembros as $m):
                            $rolClass = 'rol-' . strtolower(str_replace(['í','ó'], ['i','o'], $m['rol_en_celula']));
                        ?>
                        <div class="miembro-row">
                            <img src="<?= $m['foto_url'] ?: 'https://ui-avatars.com/api/?name='.urlencode($m['nombre'].' '.$m['apellido']).'&background=667eea&color=fff&size=36' ?>" class="miembro-avatar">
                            <div class="flex-grow-1">
                                <strong style="font-size:.9rem"><?= htmlspecialchars($m['apellido'].', '.$m['nombre']) ?></strong>
                                <span class="rol-pill <?= $rolClass ?>"><?= $m['rol_en_celula'] ?></span>
                                <?php if ($m['celular']): ?><br><small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($m['celular']) ?></small><?php endif; ?>
                            </div>
                            <a href="persona_editar.php?id=<?= $m['persona_id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reuniones + Subcélulas -->
            <div class="col-lg-6">
                <!-- Últimas reuniones -->
                <div class="card-s">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-calendar-check me-2" style="color:var(--accent)"></i>Últimas Reuniones</h5>
                        <a href="asistencia.php?celula_id=<?= $id ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-plus-circle"></i></a>
                    </div>
                    <?php if (empty($reuniones)): ?>
                    <p class="text-muted text-center py-3">No hay reuniones registradas.</p>
                    <?php else: ?>
                        <?php foreach ($reuniones as $r): ?>
                        <div class="reunion-row d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= redes_formato_fecha($r['fecha_reunion']) ?></strong>
                                <span class="badge bg-<?= $r['estado']==='Revisado'?'success':($r['estado']==='Enviado'?'primary':'secondary') ?> ms-1" style="font-size:.65rem"><?= $r['estado'] ?></span>
                                <br><small class="text-muted"><?= $r['total_asistencia'] ?> asistentes · $<?= number_format($r['ofrenda'],0,',','.') ?> ofrenda</small>
                            </div>
                            <div>
                                <small class="text-muted"><?= $r['total_miembros_asistentes'] ?> miembros + <?= $r['total_invitados'] ?> invitados</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Subcélulas -->
                <?php if (!empty($subcelulas)): ?>
                <div class="card-s">
                    <h5 class="mb-3"><i class="bi bi-diagram-2 me-2" style="color:var(--accent)"></i>Subcélulas (<?= count($subcelulas) ?>)</h5>
                    <?php foreach ($subcelulas as $sc): ?>
                    <a href="celula_detalle.php?id=<?= $sc['id'] ?>" class="text-decoration-none">
                        <div class="subcelula-card">
                            <div class="d-flex justify-content-between">
                                <strong class="text-dark"><?= htmlspecialchars($sc['nombre']) ?></strong>
                                <span class="badge" style="background:<?= tipoColorDetalle($sc['tipo_celula']) ?>;color:#fff;font-size:.65rem"><?= $sc['tipo_celula'] ?></span>
                            </div>
                            <small class="text-muted">
                                <?php if ($sc['lider_nombre']): ?><i class="bi bi-person me-1"></i><?= htmlspecialchars($sc['lider_nombre'].' '.$sc['lider_apellido']) ?> · <?php endif; ?>
                                <i class="bi bi-people me-1"></i><?= $sc['miembros_count'] ?> miembros
                                <?php if ($sc['dia_reunion']): ?> · <i class="bi bi-calendar3 me-1"></i><?= $sc['dia_reunion'] ?><?php endif; ?>
                            </small>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Info adicional -->
                <?php if ($celula['observaciones'] || $celula['latitud']): ?>
                <div class="card-s">
                    <h5 class="mb-3"><i class="bi bi-info-circle me-2" style="color:var(--accent)"></i>Información Adicional</h5>
                    <?php if ($celula['provincia_nombre'] || $celula['ciudad_nombre']): ?>
                    <p class="mb-1"><strong>Ubicación:</strong> <?= implode(', ', array_filter([$celula['barrio_nombre'], $celula['ciudad_nombre'], $celula['provincia_nombre']])) ?></p>
                    <?php endif; ?>
                    <?php if ($celula['email']): ?>
                    <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($celula['email']) ?></p>
                    <?php endif; ?>
                    <?php if ($celula['observaciones']): ?>
                    <p class="mb-1"><strong>Observaciones:</strong> <?= nl2br(htmlspecialchars($celula['observaciones'])) ?></p>
                    <?php endif; ?>
                    <?php if ($celula['latitud'] && $celula['longitud']): ?>
                    <a href="mapa_celulas.php" class="btn btn-sm btn-outline-info mt-2"><i class="bi bi-geo-alt me-1"></i>Ver en Mapa</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Acciones -->
        <div class="text-center mt-2">
            <a href="organigrama.php" class="btn btn-outline-primary me-2"><i class="bi bi-diagram-3 me-1"></i>Organigrama</a>
            <a href="celulas.php" class="btn btn-outline-secondary me-2"><i class="bi bi-grid-3x3-gap me-1"></i>Células</a>
            <a href="asistencia.php?celula_id=<?= $id ?>" class="btn btn-outline-warning"><i class="bi bi-calendar-check me-1"></i>Asistencia</a>
        </div>

        <?php else: ?>
        <!-- ===== MODO EDITAR ===== -->
        <div class="card-s">
            <h4 class="mb-3"><i class="bi bi-pencil me-2" style="color:var(--accent)"></i>Editar: <?= htmlspecialchars($celula['nombre']) ?></h4>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($celula['nombre']) ?>" required></div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="tipo_celula">
                            <?php foreach (['Juvenil','Jóvenes','Matrimonios','Hombres','Mujeres','Niños','Mixta'] as $t): ?>
                            <option value="<?= $t ?>" <?= $celula['tipo_celula']===$t?'selected':'' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="2"><?= htmlspecialchars($celula['descripcion'] ?? '') ?></textarea></div>

                    <div class="col-md-6">
                        <label class="form-label">Líder</label>
                        <select class="form-select" name="lider_id">
                            <option value="">Sin asignar</option>
                            <?php foreach ($lideres as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $celula['lider_id']==$l['id']?'selected':'' ?>><?= htmlspecialchars($l['apellido'].', '.$l['nombre']) ?> (<?= $l['rol_iglesia'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Red Padre</label>
                        <select class="form-select" name="parent_id">
                            <option value="">Ninguna (es red principal)</option>
                            <?php foreach ($celulas_padre as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $celula['parent_id']==$cp['id']?'selected':'' ?>><?= htmlspecialchars($cp['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Día Reunión</label>
                        <select class="form-select" name="dia_reunion">
                            <option value="">Seleccionar...</option>
                            <?php foreach (['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'] as $d): ?>
                            <option value="<?= $d ?>" <?= $celula['dia_reunion']===$d?'selected':'' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Hora</label><input type="time" class="form-control" name="hora_reunion" value="<?= $celula['hora_reunion'] ?? '' ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label">Frecuencia</label>
                        <select class="form-select" name="frecuencia">
                            <?php foreach (['Semanal','Quincenal','Mensual'] as $f): ?>
                            <option value="<?= $f ?>" <?= $celula['frecuencia']===$f?'selected':'' ?>><?= $f ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-8"><label class="form-label">Dirección</label><input type="text" class="form-control" name="direccion" value="<?= htmlspecialchars($celula['direccion'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Teléfono</label><input type="tel" class="form-control" name="telefono" value="<?= htmlspecialchars($celula['telefono'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($celula['email'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Fecha Inicio</label><input type="date" class="form-control" name="fecha_inicio" value="<?= $celula['fecha_inicio'] ?? '' ?>"></div>

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

                    <div class="col-md-4"><label class="form-label">Latitud</label><input type="number" step="any" class="form-control" name="latitud" value="<?= $celula['latitud'] ?? '' ?>"></div>
                    <div class="col-md-4"><label class="form-label">Longitud</label><input type="number" step="any" class="form-control" name="longitud" value="<?= $celula['longitud'] ?? '' ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado">
                            <?php foreach (['Activa','Inactiva','En Formación'] as $e): ?>
                            <option value="<?= $e ?>" <?= $celula['estado']===$e?'selected':'' ?>><?= $e ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="3"><?= htmlspecialchars($celula['observaciones'] ?? '') ?></textarea></div>
                </div>

                <div class="mt-4 d-flex justify-content-between">
                    <a href="celula_detalle.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($editando): ?>
    <script>
    const provActual = <?= json_encode($celula['provincia_id']) ?>;
    const ciuActual = <?= json_encode($celula['ciudad_id']) ?>;
    const barActual = <?= json_encode($celula['barrio_id']) ?>;

    $(document).ready(function() {
        $.getJSON('api_ubicaciones.php?action=provincias', function(data) {
            let opts = '<option value="">Seleccionar...</option>';
            data.forEach(p => opts += `<option value="${p.id}" ${p.id==provActual?'selected':''}>${p.nombre}</option>`);
            $('#provinciaSelect').html(opts);
            if (provActual) $('#provinciaSelect').trigger('change');
        });
    });

    $('#provinciaSelect').change(function() {
        let id = $(this).val();
        $('#ciudadSelect, #barrioSelect').html('<option value="">Seleccionar...</option>');
        if (!id) return;
        $.getJSON('api_ubicaciones.php?action=ciudades&provincia_id=' + id, function(data) {
            let opts = '<option value="">Seleccionar...</option>';
            data.forEach(c => opts += `<option value="${c.id}" ${c.id==ciuActual?'selected':''}>${c.nombre}</option>`);
            $('#ciudadSelect').html(opts);
            if (ciuActual) $('#ciudadSelect').trigger('change');
        });
    });

    $('#ciudadSelect').change(function() {
        let id = $(this).val();
        $('#barrioSelect').html('<option value="">Seleccionar...</option>');
        if (!id) return;
        $.getJSON('api_ubicaciones.php?action=barrios&ciudad_id=' + id, function(data) {
            let opts = '<option value="">Seleccionar...</option>';
            data.forEach(b => opts += `<option value="${b.id}" ${b.id==barActual?'selected':''}>${b.nombre}</option>`);
            $('#barrioSelect').html(opts);
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>
