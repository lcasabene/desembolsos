<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

$usuario_id = $_SESSION['user_id'];
$celulas_vis = redes_celulas_visibles($pdo, $usuario_id);

// Obtener células con coordenadas
$celulas = [];
if (!empty($celulas_vis)) {
    $ph = str_repeat('?,', count($celulas_vis) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT c.*, p.nombre as lider_nombre, p.apellido as lider_apellido, p.celular as lider_celular,
            (SELECT COUNT(*) FROM redes_miembros_celula mc WHERE mc.celula_id = c.id AND mc.estado = 'Activo') as miembros_count
        FROM redes_celulas c
        LEFT JOIN redes_personas p ON c.lider_id = p.id
        WHERE c.id IN ($ph) AND c.estado = 'Activa'
        ORDER BY c.nombre
    ");
    $stmt->execute($celulas_vis);
    $celulas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$celulas_json = json_encode(array_filter($celulas, fn($c) => $c['latitud'] && $c['longitud']));

function tipoColor($tipo) {
    $colors = [
        'Juvenil' => '#ffc107', 'Jóvenes' => '#17a2b8', 'Matrimonios' => '#e83e8c',
        'Hombres' => '#6f42c1', 'Mujeres' => '#fd7e14', 'Niños' => '#20c997', 'Mixta' => '#6c757d'
    ];
    return $colors[$tipo] ?? '#667eea';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Células - Redes y Células</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        :root { --primary: #667eea; }
        body { background: #f5f7fa; min-height: 100vh; }
        .top-bar { background: linear-gradient(135deg, var(--primary), #764ba2); color: #fff; padding: .75rem 2rem; }
        .top-bar a { color: rgba(255,255,255,.85); text-decoration: none; margin-left: 1rem; }
        .top-bar a:hover { color: #fff; }
        #map { height: calc(100vh - 160px); border-radius: 15px; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
        .map-wrap { padding: 1.5rem; max-width: 1400px; margin: 0 auto; }
        .celula-list { max-height: calc(100vh - 200px); overflow-y: auto; }
        .celula-item { padding: .75rem; border-radius: 10px; margin-bottom: .5rem; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.05); cursor: pointer; transition: transform .2s; }
        .celula-item:hover { transform: translateX(5px); background: #f0f4ff; }
        .tipo-badge { padding: .2rem .5rem; border-radius: 10px; font-size: .7rem; font-weight: 600; color: #fff; }
    </style>
</head>
<body>
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div><strong><i class="bi bi-geo-alt-fill me-2"></i>Mapa de Células</strong></div>
        <div>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="celulas.php"><i class="bi bi-grid-3x3-gap"></i> Células</a>
            <a href="personas.php"><i class="bi bi-people"></i> Personas</a>
            <a href="../menu_moderno.php"><i class="bi bi-house-door"></i> Menú Principal</a>
        </div>
    </div>

    <div class="map-wrap">
        <div class="row g-3">
            <div class="col-md-9">
                <div id="map"></div>
            </div>
            <div class="col-md-3">
                <div class="card-s p-3 bg-white rounded-4 shadow-sm">
                    <h6 class="mb-3"><i class="bi bi-list-ul me-2"></i>Células (<?= count($celulas) ?>)</h6>
                    <div class="celula-list">
                        <?php foreach ($celulas as $c): ?>
                        <div class="celula-item" onclick="focusCelula(<?= $c['id'] ?>)" data-id="<?= $c['id'] ?>">
                            <strong class="d-block"><?= htmlspecialchars($c['nombre']) ?></strong>
                            <small class="text-muted"><?= htmlspecialchars($c['direccion'] ?? 'Sin dirección') ?></small><br>
                            <span class="tipo-badge" style="background:<?= tipoColor($c['tipo_celula']) ?>"><?= $c['tipo_celula'] ?></span>
                            <small class="ms-1 text-muted"><?= $c['miembros_count'] ?> miembros</small>
                            <?php if (!$c['latitud'] || !$c['longitud']): ?>
                            <br><small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Sin coordenadas</small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($celulas)): ?>
                        <p class="text-muted text-center py-3">No hay células registradas.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const celulas = <?= $celulas_json ?>;
    const markers = {};

    // Inicializar mapa centrado en Argentina
    const map = L.map('map').setView([-34.6037, -58.3816], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Colores por tipo
    function getColor(tipo) {
        const colors = {
            'Juvenil': '#ffc107', 'Jóvenes': '#17a2b8', 'Matrimonios': '#e83e8c',
            'Hombres': '#6f42c1', 'Mujeres': '#fd7e14', 'Niños': '#20c997', 'Mixta': '#6c757d'
        };
        return colors[tipo] || '#667eea';
    }

    // Agregar marcadores
    const bounds = [];
    celulas.forEach(c => {
        if (!c.latitud || !c.longitud) return;
        const color = getColor(c.tipo_celula);
        const icon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background:${color};width:30px;height:30px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700">${c.miembros_count}</div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });

        const marker = L.marker([c.latitud, c.longitud], { icon }).addTo(map);
        marker.bindPopup(`
            <strong>${c.nombre}</strong><br>
            <small>${c.tipo_celula}</small><br>
            <i class="bi bi-geo-alt"></i> ${c.direccion || 'Sin dirección'}<br>
            <i class="bi bi-people"></i> ${c.miembros_count} miembros<br>
            <i class="bi bi-person"></i> Líder: ${c.lider_nombre || ''} ${c.lider_apellido || ''}<br>
            ${c.dia_reunion ? '<i class="bi bi-calendar"></i> ' + c.dia_reunion + ' ' + (c.hora_reunion || '') + '<br>' : ''}
            ${c.lider_celular ? '<i class="bi bi-telephone"></i> ' + c.lider_celular : ''}
        `);
        markers[c.id] = marker;
        bounds.push([c.latitud, c.longitud]);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [50, 50] });
    }

    function focusCelula(id) {
        if (markers[id]) {
            map.setView(markers[id].getLatLng(), 15);
            markers[id].openPopup();
        }
    }
    </script>
</body>
</html>
