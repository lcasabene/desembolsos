<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$rol_sistema = $_SESSION['user_role'] ?? '';
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);

// Obtener células visibles según jerarquía
$celulas_vis = redes_celulas_visibles($pdo, $usuario_id);

// Obtener todas las células con su jerarquía
$celulas = [];
if (!empty($celulas_vis)) {
    $ph = str_repeat('?,', count($celulas_vis) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            p.nombre as lider_nombre,
            p.apellido as lider_apellido,
            p.celular as lider_celular,
            p.email as lider_email,
            p.foto_url as lider_foto,
            parent.nombre as red_nombre,
            parent.tipo_celula as red_tipo,
            (SELECT COUNT(*) FROM redes_celulas c2 WHERE c2.parent_id = c.id AND c2.estado = 'Activa') as subcelulas_count,
            (SELECT COUNT(*) FROM redes_miembros_celula mc WHERE mc.celula_id = c.id AND mc.estado = 'Activo') as miembros_count,
            (SELECT MAX(a.fecha_reunion) FROM redes_asistencia a WHERE a.celula_id = c.id) as ultima_reunion,
            (SELECT COALESCE(AVG(a.total_asistencia),0) FROM redes_asistencia a WHERE a.celula_id = c.id) as promedio_asistencia
        FROM redes_celulas c
        LEFT JOIN redes_personas p ON c.lider_id = p.id
        LEFT JOIN redes_celulas parent ON c.parent_id = parent.id
        WHERE c.estado = 'Activa' AND c.id IN ($ph)
        ORDER BY c.parent_id IS NULL DESC, c.parent_id, c.nombre
    ");
    $stmt->execute($celulas_vis);
    $celulas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Construir estructura jerárquica
function buildTree($celulas, $parent_id = null) {
    $tree = [];
    foreach ($celulas as $celula) {
        if ($celula['parent_id'] == $parent_id) {
            $celula['children'] = buildTree($celulas, $celula['id']);
            $tree[] = $celula;
        }
    }
    return $tree;
}

$celulas_tree = buildTree($celulas);

// Estadísticas generales
$estadisticas = ['total_celulas' => 0, 'redes_count' => 0, 'lideres_count' => 0, 'total_miembros' => 0];
if (!empty($celulas_vis)) {
    $ph = str_repeat('?,', count($celulas_vis) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_celulas,
            COUNT(CASE WHEN parent_id IS NULL THEN 1 END) as redes_count,
            COUNT(DISTINCT lider_id) as lideres_count,
            (SELECT COUNT(*) FROM redes_miembros_celula WHERE estado = 'Activo' AND celula_id IN ($ph)) as total_miembros
        FROM redes_celulas 
        WHERE estado = 'Activa' AND id IN ($ph)
    ");
    $params = array_merge($celulas_vis, $celulas_vis);
    $stmt->execute($params);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Pastores para el nodo Iglesia
$pastores = $pdo->query("
    SELECT p.nombre, p.apellido, p.rol_iglesia,
        (SELECT GROUP_CONCAT(c.nombre SEPARATOR ', ') FROM redes_celulas c WHERE c.lider_id = p.id AND c.estado = 'Activa') as redes_a_cargo
    FROM redes_personas p 
    WHERE p.rol_iglesia IN ('Pastor Principal','Pastor Ayudante') AND p.estado = 'Miembro'
    ORDER BY FIELD(p.rol_iglesia, 'Pastor Principal', 'Pastor Ayudante'), p.apellido
")->fetchAll(PDO::FETCH_ASSOC);

// Colores por tipo de célula
function tipoColor($tipo) {
    $colores = [
        'Juvenil' => '#ffc107', 'Jóvenes' => '#17a2b8', 'Matrimonios' => '#e83e8c',
        'Hombres' => '#6f42c1', 'Mujeres' => '#fd7e14', 'Niños' => '#20c997', 'Mixta' => '#6c757d'
    ];
    return $colores[$tipo] ?? '#667eea';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organigrama - Redes y Células</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #667eea; --primary-dark: #764ba2; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }

        .top-bar { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; padding: .75rem 2rem; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
        .top-bar a { color: rgba(255,255,255,.85); text-decoration: none; margin-left: 1rem; font-size: .9rem; }
        .top-bar a:hover { color: #fff; }

        .main-wrap { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        .card-s { background: #fff; border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,.08); margin-bottom: 1.5rem; }

        /* ===== TABS ===== */
        .view-tabs .nav-link { color: #6c757d; font-weight: 500; border: none; padding: .6rem 1.2rem; }
        .view-tabs .nav-link.active { color: var(--primary); border-bottom: 3px solid var(--primary); background: transparent; font-weight: 600; }
        .view-tabs .nav-link i { margin-right: .4rem; }

        /* ===== STATS ===== */
        .stat-box { text-align: center; padding: 1rem; }
        .stat-box .icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto .5rem; font-size: 1.3rem; }
        .stat-box h3 { margin: 0; font-weight: 700; }
        .stat-box p { margin: 0; font-size: .85rem; color: #6c757d; }

        /* ===== TREE CSS (vista gráfica) ===== */
        .tree-container { overflow-x: auto; padding: 2rem 1rem; }
        .tree { display: flex; justify-content: center; }
        .tree ul { position: relative; padding-top: 30px; display: flex; justify-content: center; list-style: none; margin: 0; padding-left: 0; }
        .tree li { position: relative; display: flex; flex-direction: column; align-items: center; padding: 30px 12px 0; }
        /* Líneas verticales y horizontales */
        .tree li::before, .tree li::after {
            content: ''; position: absolute; top: 0; width: 50%; height: 30px;
            border-top: 2px solid #b8c6db;
        }
        .tree li::before { right: 50%; border-right: 2px solid #b8c6db; }
        .tree li::after { left: 50%; border-left: 2px solid #b8c6db; }
        .tree li:only-child::before, .tree li:only-child::after { border-top: none; }
        .tree li:only-child::before { border-right: 2px solid #b8c6db; }
        .tree li:first-child::before { border: none; }
        .tree li:last-child::after { border: none; }
        .tree li:first-child::after { border-radius: 8px 0 0 0; }
        .tree li:last-child::before { border-radius: 0 8px 0 0; }
        .tree ul > li:only-child { padding-top: 30px; }
        .tree ul > li:only-child::before { height: 30px; border-right: 2px solid #b8c6db; }
        /* Línea vertical del padre al conector horizontal */
        .tree > ul > li { padding-top: 0; }
        .tree > ul > li::before, .tree > ul > li::after { border: none; }

        /* Nodo del árbol */
        .tree-node {
            background: #fff; border-radius: 14px; padding: 14px 18px; min-width: 200px; max-width: 260px;
            box-shadow: 0 6px 20px rgba(0,0,0,.1); border: 2px solid #e0e6ed; cursor: pointer;
            transition: all .25s ease; text-align: center; position: relative; z-index: 1;
        }
        .tree-node:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,.15); }
        .tree-node.is-red { border-width: 3px; }
        .tree-node .node-name { font-weight: 700; font-size: .95rem; color: #2c3e50; margin-bottom: 4px; }
        .tree-node .node-type-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: .7rem; font-weight: 600; color: #fff; margin-bottom: 6px; }
        .tree-node .node-leader { font-size: .8rem; color: #6c757d; }
        .tree-node .node-leader i { color: var(--primary); }
        .tree-node .node-stats-mini { display: flex; justify-content: center; gap: 12px; margin-top: 6px; font-size: .75rem; color: #6c757d; }
        .tree-node .node-stats-mini strong { color: var(--primary); font-size: .9rem; }
        .tree-node .node-actions { margin-top: 8px; display: flex; gap: 4px; justify-content: center; }
        .tree-node .node-actions .btn { padding: 2px 8px; font-size: .72rem; }

        /* ===== TOOLTIP ===== */
        .node-tooltip {
            display: none; position: absolute; bottom: calc(100% + 12px); left: 50%; transform: translateX(-50%);
            background: #2c3e50; color: #fff; border-radius: 10px; padding: 12px 16px; min-width: 240px; max-width: 300px;
            box-shadow: 0 8px 25px rgba(0,0,0,.25); z-index: 200; text-align: left; font-size: .8rem; line-height: 1.5;
            pointer-events: none;
        }
        .node-tooltip::after {
            content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%);
            border: 8px solid transparent; border-top-color: #2c3e50;
        }
        .tree-node:hover .node-tooltip { display: block; }
        .node-tooltip .tt-title { font-weight: 700; font-size: .85rem; margin-bottom: 6px; color: #fff; border-bottom: 1px solid rgba(255,255,255,.15); padding-bottom: 5px; }
        .node-tooltip .tt-row { margin-bottom: 3px; }
        .node-tooltip .tt-row i { width: 16px; margin-right: 5px; opacity: .7; }
        .node-tooltip .tt-label { opacity: .6; font-size: .7rem; }

        /* ===== LISTA (vista tarjetas) ===== */
        .red-section { margin-bottom: 2rem; }
        .red-header { display: flex; align-items: center; gap: 1rem; padding: .75rem 1rem; border-radius: 12px; margin-bottom: 1rem; color: #fff; font-weight: 600; font-size: 1.1rem; }
        .celula-card { background: #fff; border-radius: 12px; padding: 1rem 1.2rem; box-shadow: 0 4px 15px rgba(0,0,0,.06); border-left: 4px solid var(--primary); transition: all .2s; }
        .celula-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,.12); transform: translateY(-2px); }
        .celula-card .card-title { font-weight: 600; font-size: 1rem; color: #2c3e50; }
        .celula-card .card-meta { font-size: .85rem; color: #6c757d; }
        .celula-card .card-meta i { color: var(--primary); width: 18px; }

        /* ===== ZOOM ===== */
        .zoom-controls { position: fixed; bottom: 20px; right: 20px; z-index: 100; display: flex; flex-direction: column; gap: 4px; }
        .zoom-controls .btn { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,.15); }

        @media print {
            .top-bar, .zoom-controls, .view-tabs, .node-actions { display: none !important; }
            .tree-container { overflow: visible; }
            body { background: #fff; }
        }
        @media (max-width: 768px) {
            .tree-node { min-width: 150px; max-width: 180px; padding: 10px; }
            .tree-node .node-name { font-size: .8rem; }
            .tree li { padding: 20px 4px 0; }
        }
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div><strong><i class="bi bi-diagram-3 me-2"></i>Organigrama de Redes y Células</strong></div>
        <div>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="personas.php"><i class="bi bi-people"></i> Personas</a>
            <a href="celulas.php"><i class="bi bi-grid-3x3-gap"></i> Células</a>
            <a href="mapa_celulas.php"><i class="bi bi-geo-alt"></i> Mapa</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú</a>
        </div>
    </div>

    <div class="main-wrap">
        <!-- Estadísticas -->
        <div class="card-s">
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stat-box">
                        <div class="icon bg-primary text-white"><i class="bi bi-diagram-3"></i></div>
                        <h3><?= $estadisticas['total_celulas'] ?></h3>
                        <p>Total Células</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-box">
                        <div class="icon bg-success text-white"><i class="bi bi-share"></i></div>
                        <h3><?= $estadisticas['redes_count'] ?></h3>
                        <p>Redes</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-box">
                        <div class="icon bg-info text-white"><i class="bi bi-person-badge"></i></div>
                        <h3><?= $estadisticas['lideres_count'] ?></h3>
                        <p>Líderes</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-box">
                        <div class="icon bg-warning text-white"><i class="bi bi-people-fill"></i></div>
                        <h3><?= $estadisticas['total_miembros'] ?></h3>
                        <p>Miembros</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs de vista -->
        <ul class="nav view-tabs mb-3" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#vistaGrafico"><i class="bi bi-diagram-3-fill"></i>Gráfico</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#vistaLista"><i class="bi bi-list-ul"></i>Lista</a></li>
        </ul>

        <div class="tab-content">
            <!-- ===== VISTA GRÁFICO (ÁRBOL) ===== -->
            <div class="tab-pane fade show active" id="vistaGrafico">
                <div class="card-s">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Hacé clic en una célula para ver su detalle</small>
                        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
                    </div>
                    <div class="tree-container" id="treeContainer">
                        <div class="tree" id="treeGraph" style="transform-origin: top center;">
                            <ul>
                                <!-- Nodo raíz: Iglesia -->
                                <li>
                                    <div class="tree-node is-red" style="border-color: var(--primary-dark); background: linear-gradient(135deg, rgba(102,126,234,.12), rgba(118,75,162,.12)); cursor:pointer;" onclick="location.href='organigrama_nodo.php?tipo=iglesia'">
                                        <div class="node-tooltip">
                                            <div class="tt-title"><i class="bi bi-building me-1"></i>Iglesia - Encargados</div>
                                            <?php if (!empty($pastores)): foreach ($pastores as $pas): ?>
                                            <div class="tt-row"><i class="bi bi-person-badge"></i><strong><?= htmlspecialchars($pas['nombre'].' '.$pas['apellido']) ?></strong></div>
                                            <div class="tt-row"><i class="bi bi-award"></i><?= $pas['rol_iglesia'] ?></div>
                                            <?php if ($pas['redes_a_cargo']): ?><div class="tt-row"><i class="bi bi-share"></i>Redes: <?= htmlspecialchars($pas['redes_a_cargo']) ?></div><?php endif; ?>
                                            <div style="border-bottom:1px solid rgba(255,255,255,.1);margin:4px 0"></div>
                                            <?php endforeach; else: ?>
                                            <div class="tt-row" style="opacity:.6">Sin pastores asignados</div>
                                            <?php endif; ?>
                                            <div class="tt-row tt-label">Clic para ver detalle completo</div>
                                        </div>
                                        <div class="node-name" style="font-size:1.05rem;"><i class="bi bi-building me-1"></i>Iglesia</div>
                                        <?php if (!empty($pastores)): ?>
                                        <div style="margin:6px 0;font-size:.75rem;color:#6c757d;">
                                            <?php foreach ($pastores as $pas): ?>
                                            <div><i class="bi bi-person-badge me-1" style="color:#764ba2"></i><strong><?= htmlspecialchars($pas['nombre'].' '.$pas['apellido']) ?></strong> <span style="font-size:.65rem;opacity:.8">(<?= $pas['rol_iglesia'] ?>)</span><?php if ($pas['redes_a_cargo']): ?> <span style="font-size:.65rem">→ <?= htmlspecialchars($pas['redes_a_cargo']) ?></span><?php endif; ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="node-stats-mini">
                                            <span><strong><?= $estadisticas['redes_count'] ?></strong> redes</span>
                                            <span><strong><?= $estadisticas['total_celulas'] ?></strong> células</span>
                                            <span><strong><?= $estadisticas['total_miembros'] ?></strong> miembros</span>
                                        </div>
                                    </div>
                                    <?php if (!empty($celulas_tree)): ?>
                                    <ul>
                                        <?php foreach ($celulas_tree as $red): ?>
                                        <li>
                                            <?php
                                            $color = tipoColor($red['tipo_celula']);
                                            $lider = $red['lider_nombre'] ? htmlspecialchars($red['lider_nombre'].' '.$red['lider_apellido']) : 'Sin líder';
                                            ?>
                                            <div class="tree-node is-red" style="border-color: <?= $color ?>;" onclick="location.href='organigrama_nodo.php?tipo=celula&id=<?= $red['id'] ?>'">
                                                <div class="node-tooltip">
                                                    <div class="tt-title" style="color:<?= $color ?>"><?= htmlspecialchars($red['nombre']) ?></div>
                                                    <div class="tt-row"><i class="bi bi-person-badge"></i><strong><?= $lider ?></strong></div>
                                                    <?php if ($red['lider_celular']): ?><div class="tt-row"><i class="bi bi-telephone"></i><?= htmlspecialchars($red['lider_celular']) ?></div><?php endif; ?>
                                                    <?php if ($red['lider_email']): ?><div class="tt-row"><i class="bi bi-envelope"></i><?= htmlspecialchars($red['lider_email']) ?></div><?php endif; ?>
                                                    <?php if ($red['dia_reunion']): ?><div class="tt-row"><i class="bi bi-calendar3"></i><?= $red['dia_reunion'] ?> <?= $red['hora_reunion'] ? substr($red['hora_reunion'],0,5) : '' ?> (<?= $red['frecuencia'] ?? 'Semanal' ?>)</div><?php endif; ?>
                                                    <?php if ($red['direccion']): ?><div class="tt-row"><i class="bi bi-geo-alt"></i><?= htmlspecialchars($red['direccion']) ?></div><?php endif; ?>
                                                    <div class="tt-row"><i class="bi bi-people"></i><?= $red['miembros_count'] ?> miembros · <?= $red['subcelulas_count'] ?> subcélulas</div>
                                                    <?php if ($red['ultima_reunion']): ?><div class="tt-row"><i class="bi bi-clock-history"></i>Última reunión: <?= redes_formato_fecha($red['ultima_reunion']) ?></div><?php endif; ?>
                                                    <div class="tt-row tt-label">Clic para ver dependencias</div>
                                                </div>
                                                <div class="node-type-badge" style="background:<?= $color ?>"><?= $red['tipo_celula'] ?></div>
                                                <div class="node-name"><?= htmlspecialchars($red['nombre']) ?></div>
                                                <div class="node-leader"><i class="bi bi-person-circle me-1"></i><?= $lider ?></div>
                                                <div class="node-stats-mini">
                                                    <span><strong><?= $red['miembros_count'] ?></strong> miembros</span>
                                                    <span><strong><?= $red['subcelulas_count'] ?></strong> células</span>
                                                </div>
                                                <div class="node-actions" onclick="event.stopPropagation()">
                                                    <a href="celula_detalle.php?id=<?= $red['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye"></i></a>
                                                    <a href="celula_miembros.php?id=<?= $red['id'] ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-people"></i></a>
                                                    <a href="asistencia.php?celula_id=<?= $red['id'] ?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-calendar-check"></i></a>
                                                </div>
                                            </div>
                                            <?php if (!empty($red['children'])): ?>
                                            <ul>
                                                <?php foreach ($red['children'] as $child): ?>
                                                <li>
                                                    <?php
                                                    $ccolor = tipoColor($child['tipo_celula']);
                                                    $clider = $child['lider_nombre'] ? htmlspecialchars($child['lider_nombre'].' '.$child['lider_apellido']) : 'Sin líder';
                                                    ?>
                                                    <div class="tree-node" style="border-color: <?= $ccolor ?>;" onclick="location.href='organigrama_nodo.php?tipo=celula&id=<?= $child['id'] ?>'">
                                                        <div class="node-tooltip">
                                                            <div class="tt-title" style="color:<?= $ccolor ?>"><?= htmlspecialchars($child['nombre']) ?></div>
                                                            <div class="tt-row"><i class="bi bi-person-badge"></i><strong><?= $clider ?></strong></div>
                                                            <?php if ($child['lider_celular']): ?><div class="tt-row"><i class="bi bi-telephone"></i><?= htmlspecialchars($child['lider_celular']) ?></div><?php endif; ?>
                                                            <?php if ($child['lider_email']): ?><div class="tt-row"><i class="bi bi-envelope"></i><?= htmlspecialchars($child['lider_email']) ?></div><?php endif; ?>
                                                            <?php if ($child['dia_reunion']): ?><div class="tt-row"><i class="bi bi-calendar3"></i><?= $child['dia_reunion'] ?> <?= $child['hora_reunion'] ? substr($child['hora_reunion'],0,5) : '' ?></div><?php endif; ?>
                                                            <?php if ($child['direccion']): ?><div class="tt-row"><i class="bi bi-geo-alt"></i><?= htmlspecialchars($child['direccion']) ?></div><?php endif; ?>
                                                            <div class="tt-row"><i class="bi bi-people"></i><?= $child['miembros_count'] ?> miembros</div>
                                                            <?php if ($child['ultima_reunion']): ?><div class="tt-row"><i class="bi bi-clock-history"></i>Última: <?= redes_formato_fecha($child['ultima_reunion']) ?></div><?php endif; ?>
                                                            <div class="tt-row tt-label">Clic para ver dependencias</div>
                                                        </div>
                                                        <div class="node-type-badge" style="background:<?= $ccolor ?>"><?= $child['tipo_celula'] ?></div>
                                                        <div class="node-name"><?= htmlspecialchars($child['nombre']) ?></div>
                                                        <div class="node-leader"><i class="bi bi-person-circle me-1"></i><?= $clider ?></div>
                                                        <div class="node-stats-mini">
                                                            <span><strong><?= $child['miembros_count'] ?></strong> miembros</span>
                                                            <?php if ($child['dia_reunion']): ?>
                                                            <span><i class="bi bi-clock"></i> <?= substr($child['dia_reunion'],0,3) ?> <?= $child['hora_reunion'] ? substr($child['hora_reunion'],0,5) : '' ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="node-actions" onclick="event.stopPropagation()">
                                                            <a href="celula_detalle.php?id=<?= $child['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye"></i></a>
                                                            <a href="celula_miembros.php?id=<?= $child['id'] ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-people"></i></a>
                                                            <a href="asistencia.php?celula_id=<?= $child['id'] ?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-calendar-check"></i></a>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($child['children'])): ?>
                                                    <ul>
                                                        <?php foreach ($child['children'] as $sub): ?>
                                                        <li>
                                                            <?php $sldr = $sub['lider_nombre'] ? htmlspecialchars($sub['lider_nombre'].' '.$sub['lider_apellido']) : 'Sin líder'; ?>
                                                            <?php $scolor = tipoColor($sub['tipo_celula']); ?>
                                                            <div class="tree-node" style="border-color: <?= $scolor ?>;" onclick="location.href='organigrama_nodo.php?tipo=celula&id=<?= $sub['id'] ?>'">
                                                                <div class="node-tooltip">
                                                                    <div class="tt-title" style="color:<?= $scolor ?>"><?= htmlspecialchars($sub['nombre']) ?></div>
                                                                    <div class="tt-row"><i class="bi bi-person-badge"></i><strong><?= $sldr ?></strong></div>
                                                                    <?php if ($sub['lider_celular']): ?><div class="tt-row"><i class="bi bi-telephone"></i><?= htmlspecialchars($sub['lider_celular']) ?></div><?php endif; ?>
                                                                    <?php if ($sub['dia_reunion']): ?><div class="tt-row"><i class="bi bi-calendar3"></i><?= $sub['dia_reunion'] ?> <?= $sub['hora_reunion'] ? substr($sub['hora_reunion'],0,5) : '' ?></div><?php endif; ?>
                                                                    <?php if ($sub['direccion']): ?><div class="tt-row"><i class="bi bi-geo-alt"></i><?= htmlspecialchars($sub['direccion']) ?></div><?php endif; ?>
                                                                    <div class="tt-row"><i class="bi bi-people"></i><?= $sub['miembros_count'] ?> miembros</div>
                                                                    <div class="tt-row tt-label">Clic para ver dependencias</div>
                                                                </div>
                                                                <div class="node-type-badge" style="background:<?= $scolor ?>"><?= $sub['tipo_celula'] ?></div>
                                                                <div class="node-name"><?= htmlspecialchars($sub['nombre']) ?></div>
                                                                <div class="node-leader"><i class="bi bi-person-circle me-1"></i><?= $sldr ?></div>
                                                                <div class="node-stats-mini"><span><strong><?= $sub['miembros_count'] ?></strong> miembros</span></div>
                                                            </div>
                                                        </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                    <?php endif; ?>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== VISTA LISTA ===== -->
            <div class="tab-pane fade" id="vistaLista">
                <?php foreach ($celulas_tree as $red): ?>
                <div class="red-section">
                    <div class="red-header" style="background: <?= tipoColor($red['tipo_celula']) ?>;">
                        <i class="bi bi-share-fill" style="font-size:1.3rem"></i>
                        <?= htmlspecialchars($red['nombre']) ?>
                        <span class="badge bg-light text-dark ms-auto"><?= $red['miembros_count'] ?> miembros · <?= $red['subcelulas_count'] ?> células</span>
                    </div>
                    <div class="row g-3">
                        <!-- Tarjeta de la red -->
                        <div class="col-md-6 col-lg-4">
                            <div class="celula-card" style="border-left-color: <?= tipoColor($red['tipo_celula']) ?>;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="card-title"><?= htmlspecialchars($red['nombre']) ?></div>
                                    <span class="badge" style="background:<?= tipoColor($red['tipo_celula']) ?>;color:#fff;font-size:.7rem"><?= $red['tipo_celula'] ?></span>
                                </div>
                                <?php if ($red['lider_nombre']): ?>
                                <div class="card-meta mb-1"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($red['lider_nombre'].' '.$red['lider_apellido']) ?></div>
                                <?php endif; ?>
                                <?php if ($red['dia_reunion']): ?>
                                <div class="card-meta mb-1"><i class="bi bi-calendar3"></i> <?= $red['dia_reunion'] ?> <?= $red['hora_reunion'] ? substr($red['hora_reunion'],0,5) : '' ?></div>
                                <?php endif; ?>
                                <?php if ($red['direccion']): ?>
                                <div class="card-meta mb-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($red['direccion']) ?></div>
                                <?php endif; ?>
                                <div class="d-flex gap-1 mt-2">
                                    <a href="celula_detalle.php?id=<?= $red['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Ver</a>
                                    <a href="celula_miembros.php?id=<?= $red['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-people me-1"></i>Miembros</a>
                                </div>
                            </div>
                        </div>
                        <!-- Subcélulas -->
                        <?php if (!empty($red['children'])): foreach ($red['children'] as $child): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="celula-card" style="border-left-color: <?= tipoColor($child['tipo_celula']) ?>;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="card-title"><?= htmlspecialchars($child['nombre']) ?></div>
                                    <span class="badge" style="background:<?= tipoColor($child['tipo_celula']) ?>;color:#fff;font-size:.7rem"><?= $child['tipo_celula'] ?></span>
                                </div>
                                <?php if ($child['lider_nombre']): ?>
                                <div class="card-meta mb-1"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($child['lider_nombre'].' '.$child['lider_apellido']) ?></div>
                                <?php endif; ?>
                                <?php if ($child['dia_reunion']): ?>
                                <div class="card-meta mb-1"><i class="bi bi-calendar3"></i> <?= $child['dia_reunion'] ?> <?= $child['hora_reunion'] ? substr($child['hora_reunion'],0,5) : '' ?></div>
                                <?php endif; ?>
                                <div class="card-meta mb-1"><i class="bi bi-people"></i> <?= $child['miembros_count'] ?> miembros</div>
                                <?php if ($child['direccion']): ?>
                                <div class="card-meta mb-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($child['direccion']) ?></div>
                                <?php endif; ?>
                                <div class="d-flex gap-1 mt-2">
                                    <a href="celula_detalle.php?id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Ver</a>
                                    <a href="celula_miembros.php?id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-people me-1"></i>Miembros</a>
                                    <a href="asistencia.php?celula_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-calendar-check me-1"></i>Asistencia</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($celulas_tree)): ?>
                <div class="card-s text-center py-5 text-muted">
                    <i class="bi bi-diagram-3" style="font-size:3rem"></i>
                    <p class="mt-2">No hay células registradas o no tenés permisos para verlas.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Zoom controls -->
    <div class="zoom-controls">
        <button class="btn btn-light" onclick="zoomIn()" title="Acercar"><i class="bi bi-zoom-in"></i></button>
        <button class="btn btn-light" onclick="zoomOut()" title="Alejar"><i class="bi bi-zoom-out"></i></button>
        <button class="btn btn-light" onclick="zoomReset()" title="Restablecer"><i class="bi bi-arrows-angle-expand"></i></button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let currentZoom = 1;
    const treeGraph = document.getElementById('treeGraph');

    function zoomIn() {
        currentZoom = Math.min(currentZoom + 0.1, 2);
        treeGraph.style.transform = `scale(${currentZoom})`;
    }
    function zoomOut() {
        currentZoom = Math.max(currentZoom - 0.1, 0.3);
        treeGraph.style.transform = `scale(${currentZoom})`;
    }
    function zoomReset() {
        currentZoom = 1;
        treeGraph.style.transform = `scale(1)`;
    }
    </script>
</body>
</html>
