<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
$es_lider = in_array($rol_iglesia, ['Pastor Principal','Pastor Ayudante','Lider de Red','Lider de Célula']) || ($_SESSION['user_role'] ?? '') === 'Admin';

if (!$es_lider) { header('Location: index.php?error=sin_permiso'); exit; }

$q = sanitizar_entrada($_GET['q'] ?? '');
$resultados = [];

if (strlen($q) >= 2) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.nombre, p.apellido, p.celular, p.email, p.oficio_profesion, p.direccion,
            p.estado, p.rol_iglesia,
            c.nombre as celula_nombre, mc.rol_en_celula
        FROM redes_personas p
        LEFT JOIN redes_miembros_celula mc ON p.id = mc.persona_id AND mc.estado = 'Activo'
        LEFT JOIN redes_celulas c ON mc.celula_id = c.id
        WHERE p.oficio_profesion LIKE ? AND p.estado != 'Inactivo'
        ORDER BY p.oficio_profesion, p.apellido, p.nombre
    ");
    $stmt->execute(["%$q%"]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Oficios disponibles para sugerencias
$oficios = $pdo->query("
    SELECT DISTINCT oficio_profesion, COUNT(*) as cnt
    FROM redes_personas
    WHERE oficio_profesion IS NOT NULL AND oficio_profesion != '' AND estado != 'Inactivo'
    GROUP BY oficio_profesion
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador de Oficios - Redes y Células</title>
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
        .oficio-tag { display: inline-block; padding: .3rem .7rem; margin: .2rem; border-radius: 20px; background: #e9ecef; color: #495057; font-size: .85rem; text-decoration: none; transition: background .2s; }
        .oficio-tag:hover { background: var(--primary); color: #fff; }
        .result-card { border-left: 4px solid var(--primary); padding: .75rem 1rem; margin-bottom: .5rem; background: #f8f9fa; border-radius: 0 10px 10px 0; }
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div><strong><i class="bi bi-search me-2"></i>Buscador de Oficios</strong></div>
        <div>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="personas.php"><i class="bi bi-people"></i> Personas</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <div class="main-wrap">
        <div class="card-s">
            <h5 class="mb-3"><i class="bi bi-search me-2"></i>Buscar por Oficio o Profesión</h5>
            <form method="GET" class="d-flex gap-2 mb-3">
                <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ej: electricista, plomero, abogado..." autofocus>
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <?php if ($q): ?><a href="buscador_oficios.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a><?php endif; ?>
            </form>

            <?php if (!empty($oficios)): ?>
            <div class="mb-2"><small class="text-muted">Oficios registrados:</small></div>
            <div>
                <?php foreach ($oficios as $o): ?>
                <a href="?q=<?= urlencode($o['oficio_profesion']) ?>" class="oficio-tag"><?= htmlspecialchars($o['oficio_profesion']) ?> <span class="badge bg-secondary"><?= $o['cnt'] ?></span></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($q): ?>
        <div class="card-s">
            <h5 class="mb-3">Resultados para "<?= htmlspecialchars($q) ?>" <span class="badge bg-primary"><?= count($resultados) ?></span></h5>
            <?php if (empty($resultados)): ?>
                <p class="text-muted text-center py-3"><i class="bi bi-info-circle me-1"></i>No se encontraron personas con ese oficio.</p>
            <?php else: ?>
                <?php foreach ($resultados as $r): ?>
                <div class="result-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= htmlspecialchars($r['apellido'] . ', ' . $r['nombre']) ?></strong>
                            <span class="badge bg-info ms-1"><?= htmlspecialchars($r['oficio_profesion']) ?></span>
                            <?php if ($r['celula_nombre']): ?>
                            <span class="badge bg-light text-dark ms-1"><?= htmlspecialchars($r['celula_nombre']) ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="persona_editar.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                    </div>
                    <div class="mt-1">
                        <?php if ($r['celular']): ?><small><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($r['celular']) ?></small><?php endif; ?>
                        <?php if ($r['email']): ?><small class="ms-2"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($r['email']) ?></small><?php endif; ?>
                        <?php if ($r['direccion']): ?><small class="ms-2"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($r['direccion']) ?></small><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="text-center">
            <a href="index.php" class="btn btn-outline-primary me-2"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="../menu_moderno.php" class="btn btn-primary"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
