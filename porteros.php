<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

// Verificar si el usuario es portero o admin
$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['Portero', 'Admin'])) {
    header("Location: acceso_denegado.php");
    exit;
}

// Obtener fecha actual para filtros
$fecha_actual = date('Y-m-d');
$mes_actual = date('n');
$anio_actual = date('Y');

// Obtener parámetros de filtrado
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$salon_id = $_GET['salon_id'] ?? '0';
$estado = $_GET['estado'] ?? 'todos';

// Obtener salones para filtros
$stmt = $pdo->query("SELECT * FROM salones WHERE estado = 'activo' ORDER BY numero");
$salones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir cláusula WHERE
$where_clauses = ["1=1"];
$params = [];

if ($salon_id !== '0') {
    $where_clauses[] = "r.salon_id = ?";
    $params[] = $salon_id;
}

if ($estado !== 'todos') {
    $where_clauses[] = "r.estado = ?";
    $params[] = $estado;
}

$where_clause = implode(" AND ", $where_clauses);

// Estadísticas generales
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reservas,
        COUNT(CASE WHEN r.estado = 'aprobada' THEN 1 END) as aprobadas,
        COUNT(CASE WHEN r.estado = 'pendiente' THEN 1 END) as pendientes,
        COUNT(CASE WHEN r.estado = 'rechazada' THEN 1 END) as rechazadas,
        COUNT(CASE WHEN r.estado = 'cancelada' THEN 1 END) as canceladas,
        COUNT(CASE WHEN r.fecha = CURDATE() THEN 1 END) as hoy,
        COUNT(CASE WHEN r.fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as esta_semana
    FROM reservas r
    WHERE r.fecha BETWEEN ? AND ? AND $where_clause
");
$stmt->execute([$fecha_inicio, $fecha_fin, ...$params]);
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener reservas recientes
$stmt = $pdo->prepare("
    SELECT r.*, s.nombre as salon_nombre, s.numero as salon_numero, 
           u.nombre as usuario_nombre, u.email as usuario_email
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.fecha BETWEEN ? AND ? AND $where_clause
    ORDER BY r.fecha DESC, r.hora_inicio DESC
    LIMIT 50
");
$stmt->execute([$fecha_inicio, $fecha_fin, ...$params]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener actividades de hoy
$stmt_hoy = $pdo->prepare("
    SELECT r.*, s.nombre as salon_nombre, s.numero as salon_numero, 
           u.nombre as usuario_nombre
    FROM reservas r
    JOIN salones s ON r.salon_id = s.id
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.fecha = CURDATE() AND r.estado IN ('aprobada', 'pendiente')
    ORDER BY r.hora_inicio ASC
");
$stmt_hoy->execute([]);
$actividades_hoy = $stmt_hoy->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Porteros</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Panel de control para porteros del sistema de instalaciones">
    <meta name="theme-color" content="#0d6efd">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Icons for PWA -->
    <link rel="apple-touch-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTkyIiBoZWlnaHQ9IjE5MiIgdmlld0JveD0iMCAwIDE5MiAxOTIiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxOTIiIGhlaWdodD0iMTkyIiByeD0iMjQiIGZpbGw9IiMwZDZlZmQiLz4KPHN2ZyB3aWR0aD0iOTYiIGhlaWdodD0iOTYiIHZpZXdCb3g9IjAgMCA5NiA5NiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTQ4IDhDNjUuNjcgOCA4MCAxNi4zMyA4MCAyOFY0OEM4MCA2NS42NyA2NS42NyA3MiA0OCA3MkMzMC4zMyA3MiAyNCA2NS42NyAyNCA0OFYyOEMyNCAxNi4zMyAzMC4zMyA4IDQ4IDhaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMzYgNDBWNTZDNDYgNjEuMDQ2IDQ3Ljk1NCA2NCA1MCA2NEM1Mi4wNDYgNjQgNTQgNjEuMDQ2IDU0IDU2VjQwQzU0IDM4Ljk1NCA1Mi4wNDYgMzYgNTAgMzZDNDcuOTU0IDM2IDQ2IDM4Ljk1NCA0NiA0MFY0MFYzOC45NTRDNDYgMzguOTU0IDQ3Ljk1NCAzNiA1MCAzNloiIGZpbGw9IiMwZDZlZmQiLz4KPHN2ZyB3aWR0aD0iOTYiIGhlaWdodD0iOTYiIHZpZXdCb3g9IjAgMCA5NiA5NiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTYwIDQwQzYwIDM4Ljk1NCA2Mi4wNDYgMzYgNjQgMzZDNjUuOTU0IDM2IDY4IDM4Ljk1NCA2OCA0MFY2MEM2OCA2MS4wNDYgNjUuOTU0IDY0IDY0IDY0QzYyLjA0NiA2NCA2MCA2MS4wNDYgNjAgNjBaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4KPC9zdmc+">
    
    <!-- PWA meta tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Porteros">
    <meta name="application-name" content="Panel de Porteros">
    <meta name="msapplication-TileColor" content="#0d6efd">
    <meta name="msapplication-config" content="/browserconfig.xml">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        /* Estilos para PWA */
        .pwa-install-prompt {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 50px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 9999;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-weight: 500;
        }
        
        .pwa-install-prompt:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.3);
        }
        
        .pwa-install-prompt.hidden {
            display: none;
        }
        
        /* Indicador de conexión */
        .connection-status {
            position: fixed;
            top: 10px;
            left: 10px;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .connection-status.online {
            background: #28a745;
            color: white;
        }
        
        .connection-status.offline {
            background: #dc3545;
            color: white;
        }
        
        /* Animación de carga */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Mejoras para móvil */
        @media (max-width: 768px) {
            .pwa-install-prompt {
                bottom: 10px;
                right: 10px;
                padding: 10px 16px;
                font-size: 14px;
            }
            
            .connection-status {
                top: 5px;
                left: 5px;
                padding: 6px 10px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Indicador de conexión -->
    <div id="connectionStatus" class="connection-status online">
        <i class="bi bi-wifi"></i> En línea
    </div>
    
    <!-- Botón de instalación PWA -->
    <button id="installBtn" class="pwa-install-prompt hidden">
        <i class="bi bi-download"></i> Instalar App
    </button>
    
    <!-- Overlay de carga -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="menu.php">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">
                    <i class="bi bi-shield-check"></i> Panel de Porteros
                </span>
                <span class="navbar-text ms-3">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-shield-check"></i> Panel de Porteros</h2>
            <div class="text-muted">
                <small><i class="bi bi-calendar3"></i> <?= date('d/m/Y H:i') ?></small>
            </div>
        </div>

        <!-- Alerta informativa -->
        <div class="alert alert-info">
            <h6><i class="bi bi-info-circle"></i> Bienvenido al Panel de Porteros</h6>
            <p class="mb-0">
                Desde este panel puedes visualizar todas las actividades y reservas de las instalaciones 
                para estar al tanto de lo que sucede. Tienes acceso de solo lectura a la información.
            </p>
        </div>

        <!-- Tarjetas de estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h5 class="card-title text-primary"><?= $estadisticas['total_reservas'] ?></h5>
                        <p class="card-text">Total Reservas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h5 class="card-title text-success"><?= $estadisticas['aprobadas'] ?></h5>
                        <p class="card-text">Aprobadas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h5 class="card-title text-warning"><?= $estadisticas['pendientes'] ?></h5>
                        <p class="card-text">Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h5 class="card-title text-info"><?= $estadisticas['hoy'] ?></h5>
                        <p class="card-text">Actividades Hoy</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actividades de Hoy -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-day"></i> Actividades de Hoy</h5>
            </div>
            <div class="card-body">
                <?php if (empty($actividades_hoy)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-calendar-x"></i> No hay actividades programadas para hoy
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Salón</th>
                                    <th>Usuario</th>
                                    <th>Motivo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($actividades_hoy as $actividad): ?>
                                    <tr>
                                        <td>
                                            <strong><?= date('H:i', strtotime($actividad['hora_inicio'])) ?></strong> - 
                                            <?= date('H:i', strtotime($actividad['hora_fin'])) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($actividad['salon_numero']) ?>
                                            </span>
                                            <?= htmlspecialchars($actividad['salon_nombre']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($actividad['usuario_nombre']) ?></td>
                                        <td><?= htmlspecialchars($actividad['motivo']) ?></td>
                                        <td>
                                            <span class="badge 
                                                <?= $actividad['estado'] === 'aprobada' ? 'bg-success' : 'bg-warning' ?>">
                                                <?= htmlspecialchars($actividad['estado']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtros y tabla de reservas -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul"></i> 
                    Todas las Reservas
                    <?php if ($fecha_inicio === $fecha_actual && $fecha_fin === $fecha_actual): ?>
                        <span class="badge bg-primary ms-2">Filtrando por hoy</span>
                    <?php endif; ?>
                </h5>
                <div>
                    <button type="button" class="btn btn-sm 
                        <?= ($fecha_inicio === $fecha_actual && $fecha_fin === $fecha_actual) ? 'btn-primary' : 'btn-outline-primary' ?>" 
                        onclick="filtrarHoy()">
                        <i class="bi bi-calendar-day"></i> Solo Hoy
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limpiarFiltros()">
                        <i class="bi bi-arrow-clockwise"></i> Limpiar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" 
                               value="<?= htmlspecialchars($fecha_inicio) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" 
                               value="<?= htmlspecialchars($fecha_fin) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Salón</label>
                        <select name="salon_id" class="form-select">
                            <option value="0">Todos los salones</option>
                            <?php foreach ($salones as $salon): ?>
                                <option value="<?= $salon['id'] ?>" 
                                    <?= $salon_id == $salon['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($salon['numero']) ?> - <?= htmlspecialchars($salon['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="todos" <?= $estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="aprobada" <?= $estado === 'aprobada' ? 'selected' : '' ?>>Aprobadas</option>
                            <option value="rechazada" <?= $estado === 'rechazada' ? 'selected' : '' ?>>Rechazadas</option>
                            <option value="cancelada" <?= $estado === 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <a href="porteros.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Limpiar
                        </a>
                    </div>
                </form>

                <!-- Tabla de resultados -->
                <div class="table-responsive">
                    <table id="tabla-reservas" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Salón</th>
                                <th>Usuario</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas as $reserva): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($reserva['fecha'])) ?></td>
                                    <td>
                                        <?= date('H:i', strtotime($reserva['hora_inicio'])) ?> - 
                                        <?= date('H:i', strtotime($reserva['hora_fin'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($reserva['salon_numero']) ?>
                                        </span>
                                        <?= htmlspecialchars($reserva['salon_nombre']) ?>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($reserva['usuario_nombre']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($reserva['usuario_email']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($reserva['motivo']) ?></td>
                                    <td>
                                        <span class="badge 
                                            <?= $reserva['estado'] === 'aprobada' ? 'bg-success' : 
                                               ($reserva['estado'] === 'pendiente' ? 'bg-warning' : 
                                               ($reserva['estado'] === 'rechazada' ? 'bg-danger' : 'bg-secondary')) ?>">
                                            <?= htmlspecialchars($reserva['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="verDetalles(<?= $reserva['id'] ?>)">
                                            <i class="bi bi-eye"></i> Ver
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de detalles -->
    <div class="modal fade" id="modalDetalles" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles de Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detallesContenido">
                    <!-- Contenido cargado dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tabla-reservas').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });

        function verDetalles(reservaId) {
            // Aquí podrías hacer una llamada AJAX para obtener detalles completos
            // Por ahora mostramos un mensaje simple
            $('#detallesContenido').html(`
                <div class="text-center">
                    <i class="bi bi-info-circle" style="font-size: 3rem; color: #0d6efd;"></i>
                    <h5 class="mt-3">Reserva #${reservaId}</h5>
                    <p class="text-muted">Para ver más detalles, consulta el calendario de instalaciones.</p>
                </div>
            `);
            new bootstrap.Modal(document.getElementById('modalDetalles')).show();
        }

        function filtrarHoy() {
            const hoy = new Date().toISOString().split('T')[0];
            const url = new URL(window.location);
            url.searchParams.set('fecha_inicio', hoy);
            url.searchParams.set('fecha_fin', hoy);
            url.searchParams.set('salon_id', '0');
            url.searchParams.set('estado', 'todos');
            window.location.href = url.toString();
        }

        function limpiarFiltros() {
            const url = new URL(window.location);
            url.searchParams.delete('fecha_inicio');
            url.searchParams.delete('fecha_fin');
            url.searchParams.delete('salon_id');
            url.searchParams.delete('estado');
            window.location.href = url.toString();
        }

        // Funcionalidades PWA
        let deferredPrompt;
        let installBtn = document.getElementById('installBtn');
        let connectionStatus = document.getElementById('connectionStatus');
        let loadingOverlay = document.getElementById('loadingOverlay');

        // Detectar instalación de PWA
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installBtn.classList.remove('hidden');
        });

        // Botón de instalación
        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response to the install prompt: ${outcome}`);
                deferredPrompt = null;
                installBtn.classList.add('hidden');
            }
        });

        // Ocultar botón si ya está instalado
        window.addEventListener('appinstalled', () => {
            installBtn.classList.add('hidden');
            console.log('PWA was installed');
        });

        // Monitorear estado de conexión
        function updateConnectionStatus() {
            if (navigator.onLine) {
                connectionStatus.className = 'connection-status online';
                connectionStatus.innerHTML = '<i class="bi bi-wifi"></i> En línea';
            } else {
                connectionStatus.className = 'connection-status offline';
                connectionStatus.innerHTML = '<i class="bi bi-wifi-off"></i> Sin conexión';
            }
        }

        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);

        // Service Worker registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => {
                        console.log('SW registered: ', registration);
                        
                        // Check for updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // Show update notification
                                    if (confirm('Nueva versión disponible. ¿Desea actualizar?')) {
                                        newWorker.postMessage({ action: 'skipWaiting' });
                                        window.location.reload();
                                    }
                                }
                            });
                        });
                    })
                    .catch(registrationError => {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }

        // Función para mostrar/ocultar carga
        function showLoading() {
            loadingOverlay.classList.add('show');
        }

        function hideLoading() {
            loadingOverlay.classList.remove('show');
        }

        // Sincronización en segundo plano
        async function syncData() {
            if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
                try {
                    const registration = await navigator.serviceWorker.ready;
                    await registration.sync.register('sync-reservas');
                    console.log('Sync registered');
                } catch (error) {
                    console.error('Sync registration failed:', error);
                }
            }
        }

        // Request notification permission
        if ('Notification' in navigator && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                console.log('Notification permission:', permission);
            });
        }

        // Actualizar datos periódicamente (cada 5 minutos)
        setInterval(() => {
            if (navigator.onLine) {
                // Aquí podrías hacer una llamada AJAX para actualizar datos
                console.log('Actualizando datos...');
            }
        }, 300000);

        // Inicializar
        updateConnectionStatus();
    </script>
</body>
</html>
