<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$rol_sistema = $_SESSION['user_role'] ?? '';
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
$es_admin = ($rol_sistema === 'Admin' || $rol_iglesia === 'Pastor Principal');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: personas.php'); exit; }

// Obtener persona
$stmt = $pdo->prepare("
    SELECT p.*,
        prov.nombre as provincia_nombre,
        ciu.nombre as ciudad_nombre,
        bar.nombre as barrio_nombre
    FROM redes_personas p
    LEFT JOIN redes_provincias prov ON p.provincia_id = prov.id
    LEFT JOIN redes_ciudades ciu ON p.ciudad_id = ciu.id
    LEFT JOIN redes_barrios bar ON p.barrio_id = bar.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$persona = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$persona) { header('Location: personas.php'); exit; }

// Células donde participa
$stmt = $pdo->prepare("
    SELECT mc.*, c.nombre as celula_nombre, c.tipo_celula
    FROM redes_miembros_celula mc
    JOIN redes_celulas c ON mc.celula_id = c.id
    WHERE mc.persona_id = ? AND mc.estado = 'Activo'
");
$stmt->execute([$id]);
$celulas_persona = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
$mensaje = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_token_csrf($_POST['csrf_token'] ?? '')) {
    try {
        $campos = [
            'nombre' => sanitizar_entrada($_POST['nombre'] ?? ''),
            'apellido' => sanitizar_entrada($_POST['apellido'] ?? ''),
            'dni' => sanitizar_entrada($_POST['dni'] ?? ''),
            'cuit' => sanitizar_entrada($_POST['cuit'] ?? ''),
            'celular' => sanitizar_entrada($_POST['celular'] ?? ''),
            'telefono' => sanitizar_entrada($_POST['telefono'] ?? ''),
            'email' => sanitizar_entrada($_POST['email'] ?? ''),
            'direccion' => sanitizar_entrada($_POST['direccion'] ?? ''),
            'provincia_id' => ($_POST['provincia_id'] ?? '') ?: null,
            'ciudad_id' => ($_POST['ciudad_id'] ?? '') ?: null,
            'barrio_id' => ($_POST['barrio_id'] ?? '') ?: null,
            'oficio_profesion' => sanitizar_entrada($_POST['oficio_profesion'] ?? ''),
            'estado' => sanitizar_entrada($_POST['estado'] ?? 'Visitante'),
            'rol_iglesia' => sanitizar_entrada($_POST['rol_iglesia'] ?? 'Miembro'),
            'fecha_nacimiento' => ($_POST['fecha_nacimiento'] ?? '') ?: null,
            'fecha_conversion' => ($_POST['fecha_conversion'] ?? '') ?: null,
            'bautizado' => isset($_POST['bautizado']) ? 1 : 0,
            'fecha_bautismo' => ($_POST['fecha_bautismo'] ?? '') ?: null,
            'observaciones' => sanitizar_entrada($_POST['observaciones'] ?? ''),
        ];

        if (!$campos['nombre'] || !$campos['apellido']) { throw new Exception('Nombre y apellido son obligatorios'); }

        $sets = [];
        $params = [];
        foreach ($campos as $col => $val) {
            $sets[] = "$col = ?";
            $params[] = $val;
        }
        $params[] = $id;
        $pdo->prepare("UPDATE redes_personas SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

        $mensaje = 'Persona actualizada correctamente';
        // Recargar datos
        $stmt = $pdo->prepare("SELECT p.*, prov.nombre as provincia_nombre, ciu.nombre as ciudad_nombre, bar.nombre as barrio_nombre FROM redes_personas p LEFT JOIN redes_provincias prov ON p.provincia_id = prov.id LEFT JOIN redes_ciudades ciu ON p.ciudad_id = ciu.id LEFT JOIN redes_barrios bar ON p.barrio_id = bar.id WHERE p.id = ?");
        $stmt->execute([$id]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$csrf_token = generar_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Persona - Redes y Células</title>
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
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div><strong><i class="bi bi-person-gear me-2"></i>Editar Persona</strong></div>
        <div>
            <a href="personas.php"><i class="bi bi-people"></i> Personas</a>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <div class="main-wrap">
        <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="card-s">
            <div class="d-flex align-items-center mb-3">
                <img src="<?= $persona['foto_url'] ?: 'https://ui-avatars.com/api/?name='.urlencode($persona['nombre'].' '.$persona['apellido']).'&background=667eea&color=fff&size=60' ?>" class="rounded-circle me-3" width="60" height="60">
                <div>
                    <h4 class="mb-0"><?= htmlspecialchars($persona['nombre'] . ' ' . $persona['apellido']) ?></h4>
                    <span class="badge bg-primary"><?= $persona['rol_iglesia'] ?></span>
                    <span class="badge bg-<?= $persona['estado']==='Miembro'?'success':($persona['estado']==='Visitante'?'warning':'secondary') ?>"><?= $persona['estado'] ?></span>
                </div>
            </div>

            <?php if (!empty($celulas_persona)): ?>
            <div class="mb-3">
                <small class="text-muted">Células:</small>
                <?php foreach ($celulas_persona as $cp): ?>
                <span class="badge bg-light text-dark"><?= htmlspecialchars($cp['celula_nombre']) ?> (<?= $cp['rol_en_celula'] ?>)</span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <form method="POST" class="card-s">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <h5 class="mb-3"><i class="bi bi-pencil me-2"></i>Datos Personales</h5>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($persona['nombre']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Apellido *</label><input type="text" class="form-control" name="apellido" value="<?= htmlspecialchars($persona['apellido']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">DNI</label><input type="text" class="form-control" name="dni" value="<?= htmlspecialchars($persona['dni'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">CUIT</label><input type="text" class="form-control" name="cuit" value="<?= htmlspecialchars($persona['cuit'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Fecha Nacimiento</label><input type="date" class="form-control" name="fecha_nacimiento" value="<?= $persona['fecha_nacimiento'] ?? '' ?>"></div>

                <div class="col-md-6"><label class="form-label">Celular</label><input type="tel" class="form-control" name="celular" value="<?= htmlspecialchars($persona['celular'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Teléfono</label><input type="tel" class="form-control" name="telefono" value="<?= htmlspecialchars($persona['telefono'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($persona['email'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Oficio/Profesión</label><input type="text" class="form-control" name="oficio_profesion" value="<?= htmlspecialchars($persona['oficio_profesion'] ?? '') ?>"></div>

                <div class="col-12"><label class="form-label">Dirección</label><input type="text" class="form-control" name="direccion" value="<?= htmlspecialchars($persona['direccion'] ?? '') ?>"></div>

                <div class="col-md-4">
                    <label class="form-label">Provincia</label>
                    <select class="form-select" name="provincia_id" id="provinciaSelect">
                        <option value="">Seleccionar...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ciudad</label>
                    <select class="form-select" name="ciudad_id" id="ciudadSelect">
                        <option value="">Seleccionar...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Barrio</label>
                    <select class="form-select" name="barrio_id" id="barrioSelect">
                        <option value="">Seleccionar...</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <?php foreach (['Miembro','Visitante','Inactivo'] as $e): ?>
                        <option value="<?= $e ?>" <?= $persona['estado']===$e?'selected':'' ?>><?= $e ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rol Iglesia</label>
                    <select class="form-select" name="rol_iglesia">
                        <?php foreach (['Miembro','Servidor','Lider de Célula','Lider de Red','Pastor Ayudante','Pastor Principal'] as $r): ?>
                        <option value="<?= $r ?>" <?= $persona['rol_iglesia']===$r?'selected':'' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha Conversión</label>
                    <input type="date" class="form-control" name="fecha_conversion" value="<?= $persona['fecha_conversion'] ?? '' ?>">
                </div>

                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="bautizado" id="bautizado" <?= $persona['bautizado'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="bautizado">Bautizado</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha Bautismo</label>
                    <input type="date" class="form-control" name="fecha_bautismo" value="<?= $persona['fecha_bautismo'] ?? '' ?>">
                </div>

                <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="3"><?= htmlspecialchars($persona['observaciones'] ?? '') ?></textarea></div>
            </div>

            <div class="mt-4 d-flex justify-content-between">
                <a href="personas.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const provActual = <?= json_encode($persona['provincia_id']) ?>;
    const ciuActual = <?= json_encode($persona['ciudad_id']) ?>;
    const barActual = <?= json_encode($persona['barrio_id']) ?>;

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
</body>
</html>
