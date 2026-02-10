<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$nombre = $_SESSION['user_name'] ?? 'Usuario';
$rol_sistema = $_SESSION['user_role'] ?? '';
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
$persona = redes_obtener_persona_usuario($pdo, $usuario_id);
$es_lider = in_array($rol_iglesia, ['Pastor Principal','Pastor Ayudante','Lider de Red','Lider de Célula']) || $rol_sistema === 'Admin';

// Estadísticas
$stats = ['celulas' => 0, 'personas' => 0, 'miembros' => 0, 'visitantes' => 0, 'reuniones_mes' => 0];
try {
    $celulas_vis = redes_celulas_visibles($pdo, $usuario_id);
    $stats['celulas'] = count($celulas_vis);

    if (!empty($celulas_vis)) {
        $ph = str_repeat('?,', count($celulas_vis) - 1) . '?';

        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT p.id) as personas,
                COUNT(DISTINCT CASE WHEN p.estado='Miembro' THEN p.id END) as miembros,
                COUNT(DISTINCT CASE WHEN p.estado='Visitante' THEN p.id END) as visitantes
            FROM redes_miembros_celula mc
            JOIN redes_personas p ON mc.persona_id = p.id
            WHERE mc.celula_id IN ($ph) AND mc.estado = 'Activo'
        ");
        $stmt->execute($celulas_vis);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['personas'] = $r['personas'];
        $stats['miembros'] = $r['miembros'];
        $stats['visitantes'] = $r['visitantes'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt FROM redes_asistencia
            WHERE celula_id IN ($ph) AND fecha_reunion >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute($celulas_vis);
        $stats['reuniones_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    }
} catch (Exception $e) {
    // silenciar si las tablas aún no existen
}

// Últimas novedades
$novedades = [];
try {
    $stmt = $pdo->query("
        SELECT n.*, u.nombre as autor
        FROM redes_novedades n
        JOIN usuarios u ON n.publicado_por = u.id
        WHERE n.activo = TRUE AND (n.fecha_expiracion IS NULL OR n.fecha_expiracion > NOW())
        ORDER BY n.fecha_publicacion DESC LIMIT 5
    ");
    $novedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Últimas reuniones
$reuniones = [];
try {
    if (!empty($celulas_vis)) {
        $ph = str_repeat('?,', count($celulas_vis) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT a.*, c.nombre as celula_nombre, p.nombre as lider_nombre, p.apellido as lider_apellido
            FROM redes_asistencia a
            JOIN redes_celulas c ON a.celula_id = c.id
            JOIN redes_personas p ON a.lider_id = p.id
            WHERE a.celula_id IN ($ph)
            ORDER BY a.fecha_reunion DESC LIMIT 10
        ");
        $stmt->execute($celulas_vis);
        $reuniones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

$csrf_token = generar_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redes y Células - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .top-bar { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; padding: 1rem 2rem; }
        .top-bar a { color: rgba(255,255,255,.85); text-decoration: none; }
        .top-bar a:hover { color: #fff; }
        .main-wrap { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .stat-card { background: #fff; border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,.08); transition: transform .2s; }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #fff; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #2c3e50; }
        .stat-label { color: #6c757d; font-size: .9rem; }
        .card-section { background: #fff; border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,.08); margin-bottom: 1.5rem; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
        .menu-item { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 1.5rem 1rem; background: #fff; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,.06); text-decoration: none; color: #2c3e50; transition: transform .2s, box-shadow .2s; }
        .menu-item:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,.12); color: #2c3e50; }
        .menu-icon { width: 64px; height: 64px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; color: #fff; margin-bottom: .75rem; }
        .novedad-item { border-left: 4px solid var(--primary); padding: .75rem 1rem; margin-bottom: .75rem; background: #f8f9fa; border-radius: 0 8px 8px 0; }
        .novedad-item.urgente { border-left-color: #dc3545; }
        .novedad-item.evento { border-left-color: #28a745; }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Redes y Células</h5>
            <small class="opacity-75"><?= htmlspecialchars($nombre) ?> — <?= $rol_iglesia ?></small>
        </div>
        <div class="d-flex gap-3">
            <a href="../menu_moderno.php"><i class="bi bi-house-door me-1"></i>Menú Principal</a>
            <a href="../login.php?logout=1"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>
        </div>
    </div>

    <div class="main-wrap">
        <?php if (isset($_GET['error']) && $_GET['error'] === 'sin_permiso'): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i>No tenés permisos para acceder a esa sección.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon mb-2" style="background:var(--primary)"><i class="bi bi-grid-3x3-gap"></i></div>
                    <div class="stat-value"><?= $stats['celulas'] ?></div>
                    <div class="stat-label">Células</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon mb-2" style="background:#28a745"><i class="bi bi-people"></i></div>
                    <div class="stat-value"><?= $stats['personas'] ?></div>
                    <div class="stat-label">Personas</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon mb-2" style="background:#17a2b8"><i class="bi bi-person-check"></i></div>
                    <div class="stat-value"><?= $stats['miembros'] ?></div>
                    <div class="stat-label">Miembros</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon mb-2" style="background:#ffc107"><i class="bi bi-calendar-check"></i></div>
                    <div class="stat-value"><?= $stats['reuniones_mes'] ?></div>
                    <div class="stat-label">Reuniones (mes)</div>
                </div>
            </div>
        </div>

        <!-- Menú de Módulos -->
        <div class="card-section mb-4">
            <h5 class="mb-3"><i class="bi bi-grid me-2"></i>Módulos</h5>
            <div class="menu-grid">
                <a href="personas.php" class="menu-item">
                    <div class="menu-icon" style="background:linear-gradient(135deg,#667eea,#764ba2)">
                        <i class="bi bi-people"></i>
                    </div>
                    <strong>Personas</strong>
                    <small class="text-muted">Directorio de miembros y visitantes</small>
                </a>

                <a href="celulas.php" class="menu-item">
                    <div class="menu-icon" style="background:linear-gradient(135deg,#28a745,#20c997)">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </div>
                    <strong>Células</strong>
                    <small class="text-muted">Gestión de células y redes</small>
                </a>

                <?php if ($es_lider): ?>
                <a href="asistencia.php" class="menu-item">
                    <div class="menu-icon" style="background:linear-gradient(135deg,#fd7e14,#ffc107)">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <strong>Asistencia</strong>
                    <small class="text-muted">Informes de reunión</small>
                </a>
                <?php endif; ?>

                <?php if ($es_lider): ?>
                <a href="organigrama.php" class="menu-item">
                    <div class="menu-icon" style="background:linear-gradient(135deg,#6f42c1,#e83e8c)">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                    <strong>Organigrama</strong>
                    <small class="text-muted">Estructura jerárquica</small>
                </a>
                <?php endif; ?>

                <a href="mapa_celulas.php" class="menu-item">
                    <div class="menu-icon" style="background:linear-gradient(135deg,#17a2b8,#4facfe)">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <strong>Mapa</strong>
                    <small class="text-muted">Ubicación de células</small>
                </a>

                <a href="novedades.php" class="menu-item">
                    <div class="menu-icon" style="background:linear-gradient(135deg,#dc3545,#fd7e14)">
                        <i class="bi bi-newspaper"></i>
                    </div>
                    <strong>Novedades</strong>
                    <small class="text-muted">Anuncios y actividades</small>
                </a>

                <?php if ($es_lider): ?>
                <a href="buscador_oficios.php" class="menu-item">
                    <div class="menu-icon" style="background:linear-gradient(135deg,#6c757d,#495057)">
                        <i class="bi bi-search"></i>
                    </div>
                    <strong>Buscador Oficios</strong>
                    <small class="text-muted">Buscar por profesión</small>
                </a>
                <?php endif; ?>

                <?php if ($rol_sistema === 'Admin' || $rol_iglesia === 'Pastor Principal'): ?>
                <a href="reportes.php" class="menu-item">
                    <div class="menu-icon" style="background:linear-gradient(135deg,#2c3e50,#34495e)">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <strong>Reportes</strong>
                    <small class="text-muted">Estadísticas y exportación</small>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4">
            <!-- Novedades -->
            <div class="col-md-6">
                <div class="card-section">
                    <h5 class="mb-3"><i class="bi bi-newspaper me-2"></i>Últimas Novedades</h5>
                    <?php if (empty($novedades)): ?>
                        <p class="text-muted text-center py-3"><i class="bi bi-info-circle me-1"></i>No hay novedades recientes.</p>
                    <?php else: ?>
                        <?php foreach ($novedades as $n): ?>
                        <div class="novedad-item <?= strtolower($n['tipo']) ?>">
                            <div class="d-flex justify-content-between">
                                <strong><?= htmlspecialchars($n['titulo']) ?></strong>
                                <span class="badge bg-<?= $n['tipo']==='Urgente'?'danger':($n['tipo']==='Evento'?'success':'primary') ?>"><?= $n['tipo'] ?></span>
                            </div>
                            <p class="mb-1 small text-muted"><?= htmlspecialchars(substr($n['contenido'], 0, 120)) ?>...</p>
                            <small class="text-muted"><?= htmlspecialchars($n['autor']) ?> · <?= redes_formato_fecha($n['fecha_publicacion']) ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reuniones Recientes -->
            <div class="col-md-6">
                <div class="card-section">
                    <h5 class="mb-3"><i class="bi bi-calendar-check me-2"></i>Reuniones Recientes</h5>
                    <?php if (empty($reuniones)): ?>
                        <p class="text-muted text-center py-3"><i class="bi bi-info-circle me-1"></i>No hay reuniones registradas.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                        <?php foreach ($reuniones as $r): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($r['celula_nombre']) ?></strong>
                                    <small><?= redes_formato_fecha($r['fecha_reunion']) ?></small>
                                </div>
                                <small>
                                    <i class="bi bi-people me-1"></i><?= $r['total_asistencia'] ?> asistentes
                                    <?php if ($r['total_invitados'] > 0): ?>
                                    · <i class="bi bi-person-plus me-1"></i><?= $r['total_invitados'] ?> invitados
                                    <?php endif; ?>
                                    · Líder: <?= htmlspecialchars($r['lider_nombre'] . ' ' . $r['lider_apellido']) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4 mb-3">
            <a href="../menu_moderno.php" class="btn btn-primary">
                <i class="bi bi-house-door me-1"></i>Volver al Menú Principal
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
