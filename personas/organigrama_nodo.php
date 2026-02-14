<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$rol_sistema = $_SESSION['user_role'] ?? '';
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);

// Tipo de nodo: 'iglesia' (raíz) o 'celula' (red/célula específica)
$tipo_nodo = $_GET['tipo'] ?? 'celula';
$id = (int)($_GET['id'] ?? 0);

function tipoColorNodo($tipo) {
    $colores = [
        'Juvenil' => '#ffc107', 'Jóvenes' => '#17a2b8', 'Matrimonios' => '#e83e8c',
        'Hombres' => '#6f42c1', 'Mujeres' => '#fd7e14', 'Niños' => '#20c997', 'Mixta' => '#6c757d'
    ];
    return $colores[$tipo] ?? '#667eea';
}

// ============================================================
// NODO IGLESIA (raíz)
// ============================================================
if ($tipo_nodo === 'iglesia') {
    // Pastores
    $pastores = $pdo->query("
        SELECT p.*, 
            (SELECT GROUP_CONCAT(c.nombre SEPARATOR ', ') FROM redes_celulas c WHERE c.lider_id = p.id AND c.estado = 'Activa') as redes_a_cargo
        FROM redes_personas p 
        WHERE p.rol_iglesia IN ('Pastor Principal','Pastor Ayudante') AND p.estado = 'Miembro'
        ORDER BY FIELD(p.rol_iglesia, 'Pastor Principal', 'Pastor Ayudante'), p.apellido
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Redes principales (parent_id IS NULL)
    $redes = $pdo->query("
        SELECT c.*, 
            p.nombre as lider_nombre, p.apellido as lider_apellido, p.rol_iglesia as lider_rol,
            p.celular as lider_celular, p.email as lider_email, p.foto_url as lider_foto,
            (SELECT COUNT(*) FROM redes_celulas c2 WHERE c2.parent_id = c.id AND c2.estado = 'Activa') as subcelulas_count,
            (SELECT COUNT(*) FROM redes_miembros_celula mc WHERE mc.celula_id = c.id AND mc.estado = 'Activo') as miembros_count
        FROM redes_celulas c
        LEFT JOIN redes_personas p ON c.lider_id = p.id
        WHERE c.parent_id IS NULL AND c.estado = 'Activa'
        ORDER BY c.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas globales
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM redes_celulas WHERE estado = 'Activa') as total_celulas,
            (SELECT COUNT(*) FROM redes_celulas WHERE parent_id IS NULL AND estado = 'Activa') as total_redes,
            (SELECT COUNT(*) FROM redes_personas WHERE estado = 'Miembro') as total_personas,
            (SELECT COUNT(*) FROM redes_miembros_celula WHERE estado = 'Activo') as total_miembros,
            (SELECT COUNT(*) FROM redes_personas WHERE rol_iglesia IN ('Pastor Principal','Pastor Ayudante') AND estado = 'Miembro') as total_pastores,
            (SELECT COUNT(*) FROM redes_personas WHERE rol_iglesia = 'Lider de Red' AND estado = 'Miembro') as total_lideres_red,
            (SELECT COUNT(*) FROM redes_personas WHERE rol_iglesia = 'Lider de Célula' AND estado = 'Miembro') as total_lideres_celula,
            (SELECT COUNT(*) FROM redes_personas WHERE estado = 'Visitante') as total_visitantes,
            (SELECT COALESCE(AVG(a.total_asistencia),0) FROM redes_asistencia a) as promedio_asistencia,
            (SELECT COUNT(*) FROM redes_asistencia) as total_reuniones
    ")->fetch(PDO::FETCH_ASSOC);

    $titulo = 'Iglesia';
    $color_nodo = '#764ba2';

// ============================================================
// NODO CÉLULA / RED
// ============================================================
} else {
    if (!$id) { header('Location: organigrama.php'); exit; }

    // Datos de la célula
    $stmt = $pdo->prepare("
        SELECT c.*,
            p.nombre as lider_nombre, p.apellido as lider_apellido, p.celular as lider_celular,
            p.email as lider_email, p.foto_url as lider_foto, p.rol_iglesia as lider_rol,
            par.nombre as padre_nombre, par.id as padre_id
        FROM redes_celulas c
        LEFT JOIN redes_personas p ON c.lider_id = p.id
        LEFT JOIN redes_celulas par ON c.parent_id = par.id
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $celula = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$celula) { header('Location: organigrama.php'); exit; }

    $titulo = $celula['nombre'];
    $color_nodo = tipoColorNodo($celula['tipo_celula']);

    // Función recursiva para obtener TODAS las dependencias (subcélulas en profundidad)
    function obtenerDependenciasRecursivas($pdo, $parent_id, $nivel = 0) {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                p.nombre as lider_nombre, p.apellido as lider_apellido, p.rol_iglesia as lider_rol,
                p.celular as lider_celular, p.foto_url as lider_foto,
                (SELECT COUNT(*) FROM redes_miembros_celula mc WHERE mc.celula_id = c.id AND mc.estado = 'Activo') as miembros_count,
                (SELECT COUNT(*) FROM redes_celulas c2 WHERE c2.parent_id = c.id AND c2.estado = 'Activa') as subcelulas_count
            FROM redes_celulas c
            LEFT JOIN redes_personas p ON c.lider_id = p.id
            WHERE c.parent_id = ? AND c.estado = 'Activa'
            ORDER BY c.nombre
        ");
        $stmt->execute([$parent_id]);
        $hijos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = [];
        foreach ($hijos as $h) {
            $h['nivel'] = $nivel;
            $h['hijos'] = obtenerDependenciasRecursivas($pdo, $h['id'], $nivel + 1);
            $resultado[] = $h;
        }
        return $resultado;
    }

    $dependencias = obtenerDependenciasRecursivas($pdo, $id);

    // Miembros directos
    $stmt = $pdo->prepare("
        SELECT mc.*, p.nombre, p.apellido, p.celular, p.email, p.foto_url, p.rol_iglesia
        FROM redes_miembros_celula mc
        JOIN redes_personas p ON mc.persona_id = p.id
        WHERE mc.celula_id = ? AND mc.estado = 'Activo'
        ORDER BY FIELD(mc.rol_en_celula, 'Líder', 'Anfitrión', 'Colaborador', 'Miembro'), p.apellido
    ");
    $stmt->execute([$id]);
    $miembros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar totales recursivos
    function contarRecursivo($deps) {
        $total_celulas = count($deps);
        $total_miembros = 0;
        foreach ($deps as $d) {
            $total_miembros += $d['miembros_count'];
            $sub = contarRecursivo($d['hijos']);
            $total_celulas += $sub['celulas'];
            $total_miembros += $sub['miembros'];
        }
        return ['celulas' => $total_celulas, 'miembros' => $total_miembros];
    }
    $totales_dep = contarRecursivo($dependencias);

    // Últimas reuniones
    $stmt = $pdo->prepare("
        SELECT a.*, p.nombre as lider_nombre, p.apellido as lider_apellido
        FROM redes_asistencia a LEFT JOIN redes_personas p ON a.lider_id = p.id
        WHERE a.celula_id = ? ORDER BY a.fecha_reunion DESC LIMIT 5
    ");
    $stmt->execute([$id]);
    $reuniones = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> - Organigrama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #667eea; --accent: <?= $tipo_nodo === 'iglesia' ? '#764ba2' : $color_nodo ?>; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .top-bar { background: linear-gradient(135deg, var(--primary), #764ba2); color: #fff; padding: .75rem 2rem; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
        .top-bar a { color: rgba(255,255,255,.85); text-decoration: none; margin-left: 1rem; font-size: .9rem; }
        .top-bar a:hover { color: #fff; }
        .main-wrap { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        .card-s { background: #fff; border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,.08); margin-bottom: 1.5rem; }

        /* Hero */
        .hero { border-radius: 18px; padding: 2rem; margin-bottom: 1.5rem; border: 2px solid; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: 0; right: 0; width: 200px; height: 200px; border-radius: 50%; opacity: .08; transform: translate(30%, -30%); }
        .hero h2 { font-weight: 700; color: #2c3e50; margin: .3rem 0; }
        .hero .badge-tipo { padding: 4px 14px; border-radius: 20px; font-size: .8rem; font-weight: 600; color: #fff; }

        /* Stats */
        .stat-mini { text-align: center; padding: .8rem; background: #f8f9fa; border-radius: 12px; }
        .stat-mini .num { font-size: 1.6rem; font-weight: 700; color: var(--accent); }
        .stat-mini .lbl { font-size: .75rem; color: #6c757d; }

        /* Pastor card */
        .pastor-card { display: flex; align-items: center; padding: 1rem; border-radius: 12px; border: 2px solid #e0e6ed; margin-bottom: .75rem; transition: all .2s; }
        .pastor-card:hover { border-color: var(--accent); box-shadow: 0 4px 15px rgba(0,0,0,.08); transform: translateX(4px); }
        .pastor-card img { width: 56px; height: 56px; border-radius: 50%; margin-right: 1rem; object-fit: cover; }
        .pastor-card .name { font-weight: 600; font-size: 1rem; color: #2c3e50; }
        .pastor-card .role { font-size: .8rem; padding: 2px 10px; border-radius: 10px; font-weight: 600; }
        .pastor-card .redes-cargo { font-size: .8rem; color: #6c757d; }

        /* Dependencia tree */
        .dep-item { display: flex; align-items: center; padding: .6rem .8rem; border-radius: 10px; margin-bottom: .4rem; transition: all .15s; cursor: pointer; text-decoration: none; color: inherit; }
        .dep-item:hover { background: #f0f2f5; transform: translateX(4px); color: inherit; }
        .dep-bar { width: 4px; border-radius: 2px; min-height: 40px; margin-right: .75rem; flex-shrink: 0; }
        .dep-item .dep-name { font-weight: 600; font-size: .9rem; color: #2c3e50; }
        .dep-item .dep-meta { font-size: .78rem; color: #6c757d; }
        .dep-indent { margin-left: 24px; border-left: 2px dashed #d0d7de; padding-left: 12px; }

        /* Miembro row */
        .miembro-row { display: flex; align-items: center; padding: .5rem .6rem; border-radius: 8px; margin-bottom: .3rem; transition: background .15s; }
        .miembro-row:hover { background: #f0f2f5; }
        .miembro-avatar { width: 34px; height: 34px; border-radius: 50%; margin-right: .6rem; object-fit: cover; }
        .rol-pill { padding: 2px 8px; border-radius: 8px; font-size: .68rem; font-weight: 600; }
        .rol-lider { background: var(--primary); color: #fff; }
        .rol-anfitrion { background: #28a745; color: #fff; }
        .rol-colaborador { background: #ffc107; color: #212529; }
        .rol-miembro { background: #e9ecef; color: #495057; }

        /* Red card */
        .red-card { border-radius: 14px; border: 2px solid #e0e6ed; padding: 1.2rem; margin-bottom: .75rem; transition: all .2s; display: block; text-decoration: none; color: inherit; }
        .red-card:hover { border-color: var(--accent); box-shadow: 0 6px 20px rgba(0,0,0,.1); transform: translateY(-3px); color: inherit; }
        .red-card .rc-name { font-weight: 700; font-size: 1rem; color: #2c3e50; }
        .red-card .rc-badge { padding: 3px 10px; border-radius: 12px; font-size: .7rem; font-weight: 600; color: #fff; }
        .red-card .rc-stats { display: flex; gap: 1rem; margin-top: .5rem; font-size: .8rem; color: #6c757d; }
        .red-card .rc-stats strong { color: var(--primary); }

        /* Breadcrumb */
        .bread { font-size: .85rem; margin-bottom: 1rem; }
        .bread a { color: var(--primary); text-decoration: none; }
        .bread a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div><strong><i class="bi bi-diagram-3 me-2"></i>Organigrama - Detalle</strong></div>
        <div>
            <a href="organigrama.php"><i class="bi bi-diagram-3"></i> Organigrama</a>
            <a href="celulas.php"><i class="bi bi-grid-3x3-gap"></i> Células</a>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú</a>
        </div>
    </div>

    <div class="main-wrap">

<?php if ($tipo_nodo === 'iglesia'): ?>
    <!-- ============================================================ -->
    <!-- VISTA: NODO IGLESIA -->
    <!-- ============================================================ -->

    <div class="bread"><a href="organigrama.php">Organigrama</a> <i class="bi bi-chevron-right mx-1"></i> <strong>Iglesia</strong></div>

    <div class="hero" style="border-color: #764ba244; background: linear-gradient(135deg, #764ba212, #667eea08);">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <span class="badge-tipo" style="background:#764ba2"><i class="bi bi-building me-1"></i>Iglesia</span>
                <h2><i class="bi bi-building me-2" style="color:#764ba2"></i>Vista General de la Iglesia</h2>
                <p class="text-muted mb-0">Estructura completa: pastores, redes y células</p>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2"><div class="stat-mini"><div class="num"><?= $stats['total_pastores'] ?></div><div class="lbl">Pastores</div></div></div>
        <div class="col-6 col-md-2"><div class="stat-mini"><div class="num"><?= $stats['total_redes'] ?></div><div class="lbl">Redes</div></div></div>
        <div class="col-6 col-md-2"><div class="stat-mini"><div class="num"><?= $stats['total_celulas'] ?></div><div class="lbl">Células</div></div></div>
        <div class="col-6 col-md-2"><div class="stat-mini"><div class="num"><?= $stats['total_personas'] ?></div><div class="lbl">Personas</div></div></div>
        <div class="col-6 col-md-2"><div class="stat-mini"><div class="num"><?= $stats['total_reuniones'] ?></div><div class="lbl">Reuniones</div></div></div>
        <div class="col-6 col-md-2"><div class="stat-mini"><div class="num"><?= $stats['total_visitantes'] ?></div><div class="lbl">Visitantes</div></div></div>
    </div>

    <div class="row g-3">
        <!-- Pastores -->
        <div class="col-lg-5">
            <div class="card-s">
                <h5 class="mb-3"><i class="bi bi-person-badge me-2" style="color:#764ba2"></i>Pastores</h5>
                <?php if (empty($pastores)): ?>
                <p class="text-muted text-center py-3">No hay pastores registrados.</p>
                <?php else: ?>
                    <?php foreach ($pastores as $p):
                        $avatar = $p['foto_url'] ?: 'https://ui-avatars.com/api/?name='.urlencode($p['nombre'].' '.$p['apellido']).'&background=764ba2&color=fff&size=56';
                        $rolColor = $p['rol_iglesia'] === 'Pastor Principal' ? '#764ba2' : '#667eea';
                    ?>
                    <div class="pastor-card">
                        <img src="<?= $avatar ?>" alt="">
                        <div class="flex-grow-1">
                            <div class="name"><?= htmlspecialchars($p['nombre'].' '.$p['apellido']) ?></div>
                            <span class="role" style="background:<?= $rolColor ?>;color:#fff"><?= $p['rol_iglesia'] ?></span>
                            <?php if ($p['redes_a_cargo']): ?>
                            <div class="redes-cargo mt-1"><i class="bi bi-share me-1"></i>A cargo de: <strong><?= htmlspecialchars($p['redes_a_cargo']) ?></strong></div>
                            <?php endif; ?>
                            <div class="redes-cargo">
                                <?php if ($p['celular']): ?><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($p['celular']) ?><?php endif; ?>
                                <?php if ($p['email']): ?><i class="bi bi-envelope ms-2 me-1"></i><?= htmlspecialchars($p['email']) ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Líderes de Red -->
            <div class="card-s">
                <h5 class="mb-3"><i class="bi bi-person-check me-2" style="color:var(--primary)"></i>Líderes de Red (<?= $stats['total_lideres_red'] ?>)</h5>
                <?php
                $lideres_red = $pdo->query("
                    SELECT p.*, 
                        (SELECT GROUP_CONCAT(c.nombre SEPARATOR ', ') FROM redes_celulas c WHERE c.lider_id = p.id AND c.estado = 'Activa') as redes_a_cargo
                    FROM redes_personas p 
                    WHERE p.rol_iglesia = 'Lider de Red' AND p.estado = 'Miembro'
                    ORDER BY p.apellido
                ")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($lideres_red as $lr):
                    $avatar = $lr['foto_url'] ?: 'https://ui-avatars.com/api/?name='.urlencode($lr['nombre'].' '.$lr['apellido']).'&background=667eea&color=fff&size=40';
                ?>
                <div class="miembro-row">
                    <img src="<?= $avatar ?>" class="miembro-avatar">
                    <div class="flex-grow-1">
                        <strong style="font-size:.88rem"><?= htmlspecialchars($lr['apellido'].', '.$lr['nombre']) ?></strong>
                        <span class="rol-pill rol-lider">Líder de Red</span>
                        <?php if ($lr['redes_a_cargo']): ?>
                        <br><small class="text-muted"><i class="bi bi-share me-1"></i><?= htmlspecialchars($lr['redes_a_cargo']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Redes (dependencias directas de Iglesia) -->
        <div class="col-lg-7">
            <div class="card-s">
                <h5 class="mb-3"><i class="bi bi-share-fill me-2" style="color:var(--accent)"></i>Redes (<?= count($redes) ?>)</h5>
                <?php foreach ($redes as $r):
                    $rc = tipoColorNodo($r['tipo_celula']);
                    $lider = $r['lider_nombre'] ? htmlspecialchars($r['lider_nombre'].' '.$r['lider_apellido']) : 'Sin líder';
                ?>
                <a href="organigrama_nodo.php?tipo=celula&id=<?= $r['id'] ?>" class="red-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="rc-badge" style="background:<?= $rc ?>"><?= $r['tipo_celula'] ?></span>
                            <div class="rc-name mt-1"><?= htmlspecialchars($r['nombre']) ?></div>
                        </div>
                        <i class="bi bi-chevron-right text-muted" style="font-size:1.2rem"></i>
                    </div>
                    <div class="mt-1" style="font-size:.85rem;color:#6c757d">
                        <i class="bi bi-person-circle me-1" style="color:<?= $rc ?>"></i><?= $lider ?>
                        <?php if ($r['lider_rol']): ?><span class="badge bg-light text-dark ms-1" style="font-size:.65rem"><?= $r['lider_rol'] ?></span><?php endif; ?>
                    </div>
                    <div class="rc-stats">
                        <span><strong><?= $r['miembros_count'] ?></strong> miembros</span>
                        <span><strong><?= $r['subcelulas_count'] ?></strong> subcélulas</span>
                        <?php if ($r['dia_reunion']): ?><span><i class="bi bi-calendar3 me-1"></i><?= $r['dia_reunion'] ?></span><?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>

                <?php if (empty($redes)): ?>
                <p class="text-muted text-center py-3">No hay redes registradas.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ============================================================ -->
    <!-- VISTA: NODO CÉLULA / RED -->
    <!-- ============================================================ -->

    <!-- Breadcrumb -->
    <div class="bread">
        <a href="organigrama.php">Organigrama</a> <i class="bi bi-chevron-right mx-1"></i>
        <a href="organigrama_nodo.php?tipo=iglesia">Iglesia</a> <i class="bi bi-chevron-right mx-1"></i>
        <?php if ($celula['padre_nombre']): ?>
        <a href="organigrama_nodo.php?tipo=celula&id=<?= $celula['padre_id'] ?>"><?= htmlspecialchars($celula['padre_nombre']) ?></a> <i class="bi bi-chevron-right mx-1"></i>
        <?php endif; ?>
        <strong><?= htmlspecialchars($celula['nombre']) ?></strong>
    </div>

    <!-- Hero -->
    <div class="hero" style="border-color: <?= $color_nodo ?>44; background: linear-gradient(135deg, <?= $color_nodo ?>12, <?= $color_nodo ?>05);">
        <div class="hero::before" style="background:<?= $color_nodo ?>"></div>
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <span class="badge-tipo" style="background:<?= $color_nodo ?>"><?= $celula['tipo_celula'] ?></span>
                <?php if (!$celula['parent_id']): ?>
                <span class="badge bg-dark ms-1">Red Principal</span>
                <?php endif; ?>
                <h2><?= htmlspecialchars($celula['nombre']) ?></h2>
                <?php if ($celula['descripcion']): ?>
                <p class="text-muted mb-1"><?= htmlspecialchars($celula['descripcion']) ?></p>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-3" style="font-size:.88rem;color:#6c757d">
                    <?php if ($celula['dia_reunion']): ?>
                    <span><i class="bi bi-calendar3 me-1" style="color:<?= $color_nodo ?>"></i><?= $celula['dia_reunion'] ?> <?= $celula['hora_reunion'] ? substr($celula['hora_reunion'],0,5) : '' ?></span>
                    <?php endif; ?>
                    <?php if ($celula['direccion']): ?>
                    <span><i class="bi bi-geo-alt me-1" style="color:<?= $color_nodo ?>"></i><?= htmlspecialchars($celula['direccion']) ?></span>
                    <?php endif; ?>
                    <?php if ($celula['fecha_inicio']): ?>
                    <span><i class="bi bi-flag me-1" style="color:<?= $color_nodo ?>"></i>Desde <?= redes_formato_fecha($celula['fecha_inicio']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-2">
                <a href="celula_detalle.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Detalle/Editar</a>
                <a href="celula_miembros.php?id=<?= $id ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-people me-1"></i>Miembros</a>
                <a href="asistencia.php?celula_id=<?= $id ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-calendar-check me-1"></i>Asistencia</a>
            </div>
        </div>

        <!-- Líder -->
        <?php if ($celula['lider_nombre']): ?>
        <div class="d-flex align-items-center mt-3 p-2 rounded" style="background: rgba(255,255,255,.6);">
            <img src="<?= $celula['lider_foto'] ?: 'https://ui-avatars.com/api/?name='.urlencode($celula['lider_nombre'].' '.$celula['lider_apellido']).'&background=667eea&color=fff&size=48' ?>" class="rounded-circle me-2" width="48" height="48" style="object-fit:cover">
            <div>
                <strong><?= htmlspecialchars($celula['lider_nombre'].' '.$celula['lider_apellido']) ?></strong>
                <span class="badge ms-1" style="background:<?= $color_nodo ?>;color:#fff;font-size:.7rem"><?= $celula['lider_rol'] ?></span>
                <div style="font-size:.82rem;color:#6c757d">
                    <?php if ($celula['lider_celular']): ?><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($celula['lider_celular']) ?><?php endif; ?>
                    <?php if ($celula['lider_email']): ?><i class="bi bi-envelope ms-2 me-1"></i><?= htmlspecialchars($celula['lider_email']) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-mini"><div class="num"><?= count($miembros) ?></div><div class="lbl">Miembros Directos</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-mini"><div class="num"><?= count($dependencias) ?></div><div class="lbl">Subcélulas Directas</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-mini"><div class="num"><?= $totales_dep['celulas'] ?></div><div class="lbl">Total Dependencias</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-mini"><div class="num"><?= count($miembros) + $totales_dep['miembros'] ?></div><div class="lbl">Total Personas</div></div></div>
    </div>

    <div class="row g-3">
        <!-- Dependencias (subcélulas recursivas) -->
        <div class="col-lg-7">
            <div class="card-s">
                <h5 class="mb-3"><i class="bi bi-diagram-2 me-2" style="color:<?= $color_nodo ?>"></i>Dependencias (<?= $totales_dep['celulas'] ?> células)</h5>

                <?php if (empty($dependencias)): ?>
                <p class="text-muted text-center py-3">Esta célula no tiene subcélulas.</p>
                <?php else: ?>
                    <?php
                    function renderDependencias($deps, $nivel = 0) {
                        foreach ($deps as $d) {
                            $c = tipoColorNodo($d['tipo_celula']);
                            $ldr = $d['lider_nombre'] ? htmlspecialchars($d['lider_nombre'].' '.$d['lider_apellido']) : 'Sin líder';
                            $indent = $nivel > 0 ? ' dep-indent' : '';
                            echo '<div class="'.$indent.'">';
                            echo '<a href="organigrama_nodo.php?tipo=celula&id='.$d['id'].'" class="dep-item">';
                            echo '<div class="dep-bar" style="background:'.$c.'"></div>';
                            echo '<div class="flex-grow-1">';
                            echo '<div class="dep-name">'.htmlspecialchars($d['nombre']).' <span class="badge" style="background:'.$c.';color:#fff;font-size:.6rem">'.$d['tipo_celula'].'</span></div>';
                            echo '<div class="dep-meta"><i class="bi bi-person-circle me-1"></i>'.$ldr.' · <i class="bi bi-people me-1"></i>'.$d['miembros_count'].' miembros';
                            if ($d['subcelulas_count'] > 0) echo ' · <i class="bi bi-diagram-2 me-1"></i>'.$d['subcelulas_count'].' subcélulas';
                            echo '</div>';
                            echo '</div>';
                            echo '<i class="bi bi-chevron-right text-muted"></i>';
                            echo '</a>';
                            if (!empty($d['hijos'])) {
                                renderDependencias($d['hijos'], $nivel + 1);
                            }
                            echo '</div>';
                        }
                    }
                    renderDependencias($dependencias);
                    ?>
                <?php endif; ?>
            </div>

            <!-- Últimas reuniones -->
            <?php if (!empty($reuniones)): ?>
            <div class="card-s">
                <h5 class="mb-3"><i class="bi bi-calendar-check me-2" style="color:<?= $color_nodo ?>"></i>Últimas Reuniones</h5>
                <?php foreach ($reuniones as $r): ?>
                <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid #f0f2f5">
                    <div>
                        <strong><?= redes_formato_fecha($r['fecha_reunion']) ?></strong>
                        <span class="badge bg-<?= $r['estado']==='Revisado'?'success':($r['estado']==='Enviado'?'primary':'secondary') ?> ms-1" style="font-size:.6rem"><?= $r['estado'] ?></span>
                        <br><small class="text-muted"><?= $r['total_asistencia'] ?> asistentes · $<?= number_format($r['ofrenda'],0,',','.') ?></small>
                    </div>
                    <small class="text-muted"><?= $r['total_miembros_asistentes'] ?>m + <?= $r['total_invitados'] ?>i</small>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Miembros directos -->
        <div class="col-lg-5">
            <div class="card-s">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-people me-2" style="color:<?= $color_nodo ?>"></i>Miembros (<?= count($miembros) ?>)</h5>
                    <a href="celula_miembros.php?id=<?= $id ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-person-plus"></i></a>
                </div>
                <?php if (empty($miembros)): ?>
                <p class="text-muted text-center py-3">No hay miembros asignados.</p>
                <?php else: ?>
                    <?php foreach ($miembros as $m):
                        $rolClass = 'rol-' . strtolower(str_replace(['í','ó','Í'], ['i','o','i'], $m['rol_en_celula']));
                    ?>
                    <div class="miembro-row">
                        <img src="<?= $m['foto_url'] ?: 'https://ui-avatars.com/api/?name='.urlencode($m['nombre'].' '.$m['apellido']).'&background=667eea&color=fff&size=34' ?>" class="miembro-avatar">
                        <div class="flex-grow-1">
                            <strong style="font-size:.85rem"><?= htmlspecialchars($m['apellido'].', '.$m['nombre']) ?></strong>
                            <span class="rol-pill <?= $rolClass ?>"><?= $m['rol_en_celula'] ?></span>
                            <?php if ($m['celular']): ?><br><small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($m['celular']) ?></small><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php endif; ?>

    <!-- Volver -->
    <div class="text-center mt-2 mb-4">
        <a href="organigrama.php" class="btn btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Volver al Organigrama</a>
    </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
