<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$rol_sistema = $_SESSION['user_role'] ?? '';
$rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
$persona = redes_obtener_persona_usuario($pdo, $usuario_id);
$es_lider = in_array($rol_iglesia, ['Pastor Principal','Pastor Ayudante','Lider de Red','Lider de Célula']) || $rol_sistema === 'Admin';
$puede_revisar = in_array($rol_iglesia, ['Pastor Principal','Pastor Ayudante','Lider de Red']) || $rol_sistema === 'Admin';

if (!$es_lider) {
    header('Location: index.php?error=sin_permiso');
    exit;
}

$celulas_vis = redes_celulas_visibles($pdo, $usuario_id);
$celula_id_filtro = $_GET['celula_id'] ?? '';

// Obtener células para el filtro
$celulas_filtro = [];
if (!empty($celulas_vis)) {
    $ph = str_repeat('?,', count($celulas_vis) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, nombre FROM redes_celulas WHERE id IN ($ph) AND estado='Activa' ORDER BY nombre");
    $stmt->execute($celulas_vis);
    $celulas_filtro = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener informes de asistencia
$informes = [];
if (!empty($celulas_vis)) {
    $ph = str_repeat('?,', count($celulas_vis) - 1) . '?';
    $params = $celulas_vis;

    $where_extra = '';
    if ($celula_id_filtro !== '') {
        $where_extra = ' AND a.celula_id = ?';
        $params[] = (int)$celula_id_filtro;
    }

    $stmt = $pdo->prepare("
        SELECT a.*, c.nombre as celula_nombre, c.tipo_celula,
            p.nombre as lider_nombre, p.apellido as lider_apellido
        FROM redes_asistencia a
        JOIN redes_celulas c ON a.celula_id = c.id
        JOIN redes_personas p ON a.lider_id = p.id
        WHERE a.celula_id IN ($ph) $where_extra
        ORDER BY a.fecha_reunion DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $informes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$csrf_token = generar_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistencia - Redes y Células</title>
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
        .estado-badge { padding: .25rem .6rem; border-radius: 15px; font-size: .75rem; font-weight: 500; }
        .estado-borrador { background: #fff3cd; color: #856404; }
        .estado-enviado { background: #cce5ff; color: #004085; }
        .estado-revisado { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <strong><i class="bi bi-calendar-check-fill me-2"></i>Asistencia</strong>
            <small class="ms-2 opacity-75">Informes de reunión de célula</small>
        </div>
        <div>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="celulas.php"><i class="bi bi-grid-3x3-gap"></i> Células</a>
            <a href="personas.php"><i class="bi bi-people"></i> Personas</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <div class="main-wrap">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="mb-0">Informes de Asistencia</h4>
            <div class="d-flex gap-2">
                <form method="GET" class="d-flex gap-2">
                    <select class="form-select form-select-sm" name="celula_id" style="width:200px" onchange="this.form.submit()">
                        <option value="">Todas las células</option>
                        <?php foreach ($celulas_filtro as $cel): ?>
                        <option value="<?= $cel['id'] ?>" <?= $celula_id_filtro==$cel['id']?'selected':'' ?>><?= htmlspecialchars($cel['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalInforme">
                    <i class="bi bi-plus-circle me-1"></i>Nuevo Informe
                </button>
            </div>
        </div>

        <div class="card-s">
            <div class="table-responsive">
                <table class="table table-hover table-sm" id="tablaInformes">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Célula</th>
                            <th>Líder</th>
                            <th>Miembros</th>
                            <th>Invitados</th>
                            <th>Total</th>
                            <th>Ofrenda</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($informes)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No hay informes registrados.</td></tr>
                    <?php else: foreach ($informes as $inf): ?>
                        <tr>
                            <td><?= redes_formato_fecha($inf['fecha_reunion']) ?></td>
                            <td><strong><?= htmlspecialchars($inf['celula_nombre']) ?></strong></td>
                            <td><small><?= htmlspecialchars($inf['lider_nombre'] . ' ' . $inf['lider_apellido']) ?></small></td>
                            <td class="text-center"><?= $inf['total_miembros_asistentes'] ?></td>
                            <td class="text-center"><?= $inf['total_invitados'] ?></td>
                            <td class="text-center"><strong><?= $inf['total_asistencia'] ?></strong></td>
                            <td>$<?= number_format($inf['ofrenda'], 2) ?></td>
                            <td><span class="estado-badge estado-<?= strtolower($inf['estado']) ?>"><?= $inf['estado'] ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="verInforme(<?= $inf['id'] ?>)" title="Ver detalle"><i class="bi bi-eye"></i></button>
                                <?php if ($inf['estado'] === 'Borrador'): ?>
                                <button class="btn btn-sm btn-outline-success" onclick="enviarInforme(<?= $inf['id'] ?>)" title="Enviar"><i class="bi bi-send"></i></button>
                                <?php endif; ?>
                                <?php if ($inf['estado'] === 'Enviado' && $puede_revisar): ?>
                                <button class="btn btn-sm btn-outline-success" onclick="revisarInforme(<?= $inf['id'] ?>)" title="Aprobar / Marcar como revisado"><i class="bi bi-check-circle"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="rechazarInforme(<?= $inf['id'] ?>)" title="Rechazar (devolver a borrador)"><i class="bi bi-x-circle"></i></button>
                                <?php endif; ?>
                                <?php if ($inf['estado'] === 'Revisado'): ?>
                                <span class="text-success" title="Revisado"><i class="bi bi-patch-check-fill"></i></span>
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

    <!-- Modal Nuevo Informe -->
    <div class="modal fade" id="modalInforme" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Nuevo Informe de Reunión</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="formInforme">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Célula *</label>
                                <select class="form-select" name="celula_id" id="inf_celula" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($celulas_filtro as $cel): ?>
                                    <option value="<?= $cel['id'] ?>"><?= htmlspecialchars($cel['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha *</label>
                                <input type="date" class="form-control" name="fecha_reunion" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ofrenda</label>
                                <input type="number" class="form-control" name="ofrenda" step="0.01" value="0">
                            </div>

                            <div class="col-12">
                                <h6>Asistencia de Miembros</h6>
                                <div id="listaMiembros" class="border rounded p-3">
                                    <p class="text-muted mb-0">Seleccioná una célula para ver sus miembros.</p>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Total Invitados</label>
                                <input type="number" class="form-control" name="total_invitados" value="0" min="0">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Mensaje Compartido</label>
                                <textarea class="form-control" name="mensaje_compartido" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Pedidos de Oración</label>
                                <textarea class="form-control" name="pedidos_oracion" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observaciones</label>
                                <textarea class="form-control" name="observaciones" rows="2"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-outline-primary" onclick="guardarInforme('Borrador')">Guardar Borrador</button>
                    <button type="button" class="btn btn-success" onclick="guardarInforme('Enviado')">Guardar y Enviar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ver Informe -->
    <div class="modal fade" id="modalVerInforme" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Detalle del Informe</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" id="detalleInforme">Cargando...</div>
                <div class="modal-footer" id="footerVerInforme"></div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
    const csrfToken = '<?= $csrf_token ?>';

    $(document).ready(function() {
        $('#tablaInformes').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            pageLength: 25, order: [[0, 'desc']]
        });
    });

    // Cargar miembros al seleccionar célula
    $('#inf_celula').change(function() {
        const celId = $(this).val();
        if (!celId) { $('#listaMiembros').html('<p class="text-muted mb-0">Seleccioná una célula.</p>'); return; }

        $.getJSON('api_celulas.php?action=miembros&celula_id=' + celId, function(data) {
            if (!data.length) { $('#listaMiembros').html('<p class="text-muted mb-0">No hay miembros en esta célula.</p>'); return; }
            let html = '<div class="row g-2">';
            data.forEach(m => {
                html += `<div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input miembro-check" type="checkbox" name="miembros_presentes[]" value="${m.persona_id}" id="m${m.persona_id}" checked>
                        <label class="form-check-label" for="m${m.persona_id}">
                            <strong>${m.apellido}, ${m.nombre}</strong>
                            <small class="text-muted d-block">${m.rol_en_celula} ${m.celular ? '· ' + m.celular : ''}</small>
                        </label>
                    </div>
                </div>`;
            });
            html += '</div>';
            html += `<div class="mt-2 text-end"><small class="text-muted">Presentes: <span id="contPresentes">${data.length}</span> / ${data.length}</small></div>`;
            $('#listaMiembros').html(html);

            $('.miembro-check').change(function() {
                $('#contPresentes').text($('.miembro-check:checked').length);
            });
        });
    });

    function guardarInforme(estado) {
        const formData = $('#formInforme').serialize() + '&estado=' + estado;
        $.post('api_asistencia.php?action=guardar', formData, function(r) {
            if (r.success) location.reload();
            else alert('Error: ' + r.message);
        }, 'json').fail(() => alert('Error de conexión'));
    }

    const puedeRevisar = <?= json_encode($puede_revisar) ?>;

    function verInforme(id) {
        $.getJSON('api_asistencia.php?action=obtener&id=' + id, function(r) {
            if (!r.success) { alert(r.message); return; }
            const a = r.data;

            // Badge de estado con color
            let estadoBadge = '';
            if (a.estado === 'Borrador') estadoBadge = '<span class="estado-badge estado-borrador">Borrador</span>';
            else if (a.estado === 'Enviado') estadoBadge = '<span class="estado-badge estado-enviado">Enviado - Pendiente de revisión</span>';
            else estadoBadge = '<span class="estado-badge estado-revisado"><i class="bi bi-patch-check-fill me-1"></i>Revisado</span>';

            let html = `
                <div class="row g-3">
                    <div class="col-md-6"><strong>Célula:</strong> ${a.celula_nombre}</div>
                    <div class="col-md-3"><strong>Fecha:</strong> ${a.fecha_reunion}</div>
                    <div class="col-md-3"><strong>Estado:</strong> ${estadoBadge}</div>
                    <div class="col-md-4"><strong>Miembros:</strong> ${a.total_miembros_asistentes}</div>
                    <div class="col-md-4"><strong>Invitados:</strong> ${a.total_invitados}</div>
                    <div class="col-md-4"><strong>Total:</strong> ${a.total_asistencia}</div>
                    <div class="col-md-6"><strong>Ofrenda:</strong> $${parseFloat(a.ofrenda).toFixed(2)}</div>
                    <div class="col-md-6"><strong>Líder:</strong> ${a.lider_nombre} ${a.lider_apellido}</div>
                </div>`;
            if (a.mensaje_compartido) html += `<hr><strong>Mensaje:</strong><p>${a.mensaje_compartido}</p>`;
            if (a.pedidos_oracion) html += `<strong>Pedidos de Oración:</strong><p>${a.pedidos_oracion}</p>`;
            if (a.observaciones) html += `<strong>Observaciones:</strong><p>${a.observaciones}</p>`;

            if (r.detalle && r.detalle.length) {
                const presentes = r.detalle.filter(d => d.presente == 1).length;
                const ausentes = r.detalle.length - presentes;
                html += `<hr><strong>Asistencia detallada:</strong> <small class="text-muted">(${presentes} presentes, ${ausentes} ausentes)</small><ul class="list-group list-group-flush mt-2">`;
                r.detalle.forEach(d => {
                    const icon = d.presente == 1 ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>';
                    html += `<li class="list-group-item py-1">${icon} ${d.nombre} ${d.apellido}</li>`;
                });
                html += '</ul>';
            }
            $('#detalleInforme').html(html);

            // Footer con botones de acción según estado
            let footer = '';
            if (a.estado === 'Borrador') {
                footer = `<button class="btn btn-outline-success" onclick="enviarInforme(${a.id})"><i class="bi bi-send me-1"></i>Enviar Informe</button>`;
            } else if (a.estado === 'Enviado' && puedeRevisar) {
                footer = `<button class="btn btn-outline-danger" onclick="rechazarInforme(${a.id})"><i class="bi bi-x-circle me-1"></i>Rechazar</button>`;
                footer += `<button class="btn btn-success" onclick="revisarInforme(${a.id})"><i class="bi bi-check-circle me-1"></i>Aprobar y Marcar como Revisado</button>`;
            } else if (a.estado === 'Revisado') {
                let revisorInfo = 'Este informe ya fue revisado y aprobado.';
                if (a.revisor_nombre) revisorInfo += ` Por: <strong>${a.revisor_nombre}</strong>`;
                if (a.fecha_revision) revisorInfo += ` el ${a.fecha_revision}`;
                footer = `<span class="text-success"><i class="bi bi-patch-check-fill me-1"></i>${revisorInfo}</span>`;
            }
            $('#footerVerInforme').html(footer);

            new bootstrap.Modal('#modalVerInforme').show();
        });
    }

    function enviarInforme(id) {
        if (!confirm('¿Enviar este informe? Una vez enviado quedará pendiente de revisión.')) return;
        $.post('api_asistencia.php?action=enviar', { id: id, csrf_token: csrfToken }, function(r) {
            if (r.success) location.reload();
            else alert('Error: ' + r.message);
        }, 'json');
    }

    function revisarInforme(id) {
        if (!confirm('¿Aprobar y marcar este informe como revisado?')) return;
        $.post('api_asistencia.php?action=revisar', { id: id, csrf_token: csrfToken }, function(r) {
            if (r.success) location.reload();
            else alert('Error: ' + r.message);
        }, 'json');
    }

    function rechazarInforme(id) {
        if (!confirm('¿Rechazar este informe? Volverá al estado Borrador para que el líder lo corrija.')) return;
        $.post('api_asistencia.php?action=rechazar', { id: id, csrf_token: csrfToken }, function(r) {
            if (r.success) location.reload();
            else alert('Error: ' + r.message);
        }, 'json');
    }
    </script>
</body>
</html>
