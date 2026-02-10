<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$rol_sistema = $_SESSION['user_role'] ?? '';
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
$es_admin = ($rol_sistema === 'Admin' || in_array($rol_iglesia, ['Pastor Principal', 'Pastor Ayudante']));

// Obtener novedades activas
$stmt = $pdo->query("
    SELECT n.*, u.nombre as autor
    FROM redes_novedades n
    JOIN usuarios u ON n.publicado_por = u.id
    WHERE n.activo = TRUE AND (n.fecha_expiracion IS NULL OR n.fecha_expiracion > NOW())
    ORDER BY n.tipo = 'Urgente' DESC, n.fecha_publicacion DESC
");
$novedades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generar_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novedades - Redes y Células</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #667eea; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .top-bar { background: linear-gradient(135deg, var(--primary), #764ba2); color: #fff; padding: .75rem 2rem; }
        .top-bar a { color: rgba(255,255,255,.85); text-decoration: none; margin-left: 1rem; }
        .top-bar a:hover { color: #fff; }
        .main-wrap { max-width: 900px; margin: 0 auto; padding: 1.5rem; }
        .card-s { background: #fff; border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,.08); margin-bottom: 1.5rem; }
        .novedad-card { border-left: 5px solid var(--primary); }
        .novedad-card.urgente { border-left-color: #dc3545; }
        .novedad-card.evento { border-left-color: #28a745; }
        .novedad-card.novedad { border-left-color: #ffc107; }
        .tipo-badge { font-size: .75rem; padding: .2rem .5rem; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div><strong><i class="bi bi-newspaper me-2"></i>Novedades</strong></div>
        <div>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="personas.php"><i class="bi bi-people"></i> Personas</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <div class="main-wrap">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Novedades y Anuncios</h4>
            <?php if ($es_admin): ?>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovedad">
                <i class="bi bi-plus-circle me-1"></i>Nueva Novedad
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($novedades)): ?>
        <div class="card-s text-center py-5 text-muted">
            <i class="bi bi-newspaper" style="font-size:3rem"></i>
            <p class="mt-2">No hay novedades publicadas.</p>
        </div>
        <?php else: ?>
            <?php foreach ($novedades as $n): ?>
            <div class="card-s novedad-card <?= strtolower($n['tipo']) ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="mb-0"><?= htmlspecialchars($n['titulo']) ?></h5>
                    <span class="badge bg-<?= $n['tipo']==='Urgente'?'danger':($n['tipo']==='Evento'?'success':($n['tipo']==='Novedad'?'warning':'primary')) ?> tipo-badge"><?= $n['tipo'] ?></span>
                </div>
                <p class="mb-2"><?= nl2br(htmlspecialchars($n['contenido'])) ?></p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="bi bi-person me-1"></i><?= htmlspecialchars($n['autor']) ?>
                        · <i class="bi bi-calendar me-1"></i><?= redes_formato_fecha($n['fecha_publicacion']) ?>
                        · <span class="badge bg-light text-dark"><?= $n['destinatarios'] ?></span>
                    </small>
                    <?php if ($es_admin): ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="desactivarNovedad(<?= $n['id'] ?>)"><i class="bi bi-trash"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-outline-primary me-2"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="../menu_moderno.php" class="btn btn-primary"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <!-- Modal Nueva Novedad -->
    <?php if ($es_admin): ?>
    <div class="modal fade" id="modalNovedad" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Nueva Novedad</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="formNovedad">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <div class="mb-3"><label class="form-label">Título *</label><input type="text" class="form-control" name="titulo" required></div>
                        <div class="mb-3"><label class="form-label">Contenido *</label><textarea class="form-control" name="contenido" rows="4" required></textarea></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="tipo">
                                    <option value="Anuncio">Anuncio</option><option value="Evento">Evento</option>
                                    <option value="Novedad">Novedad</option><option value="Urgente">Urgente</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Destinatarios</label>
                                <select class="form-select" name="destinatarios">
                                    <option value="Todos">Todos</option><option value="Líderes">Líderes</option>
                                    <option value="Miembros">Miembros</option><option value="Pastores">Pastores</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Fecha Expiración (opcional)</label>
                                <input type="datetime-local" class="form-control" name="fecha_expiracion">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarNovedad()">Publicar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function guardarNovedad() {
        $.post('api_novedades.php?action=guardar', $('#formNovedad').serialize(), function(r) {
            if (r.success) location.reload();
            else alert('Error: ' + r.message);
        }, 'json').fail(() => alert('Error de conexión'));
    }

    function desactivarNovedad(id) {
        if (!confirm('¿Desactivar esta novedad?')) return;
        $.post('api_novedades.php?action=desactivar', { id: id, csrf_token: '<?= $csrf_token ?>' }, function(r) {
            if (r.success) location.reload();
            else alert('Error: ' + r.message);
        }, 'json');
    }
    </script>
</body>
</html>
