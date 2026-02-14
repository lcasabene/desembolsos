<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion();

$modulos = $_SESSION['modulos'] ?? [];
$nombre = $_SESSION['user_name'] ?? 'Usuario';
$rol = $_SESSION['user_role'] ?? 'User';
$avatar = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel Principal - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            --card-shadow: 0 10px 40px rgba(0,0,0,0.1);
            --hover-transform: translateY(-10px);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,138.7C960,139,1056,117,1152,96C1248,75,1344,53,1392,42.7L1440,32L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
        }

        /* Modern Sidebar */
        .modern-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: var(--card-shadow);
            z-index: 1000;
            transition: var(--transition);
        }

        .sidebar-header {
            padding: 2rem;
            background: var(--primary-gradient);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: #667eea;
            font-weight: bold;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            position: relative;
            z-index: 1;
        }

        .user-info h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .user-info p {
            margin: 0.25rem 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            padding: 1.5rem 0;
        }

        .menu-section {
            margin-bottom: 2rem;
        }

        .menu-title {
            padding: 0 2rem;
            margin-bottom: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #6c757d;
            letter-spacing: 1px;
        }

        .menu-item {
            display: block;
            padding: 1rem 2rem;
            color: #495057;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--primary-gradient);
            transform: translateX(-100%);
            transition: var(--transition);
        }

        .menu-item:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding-left: 2.5rem;
        }

        .menu-item:hover::before {
            transform: translateX(0);
        }

        .menu-item i {
            width: 20px;
            margin-right: 1rem;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }

        .content-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .content-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: var(--primary-gradient);
            border-radius: 50%;
            transform: translate(50%, -50%);
            opacity: 0.1;
        }

        .header-content h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .header-content p {
            color: #6c757d;
            font-size: 1.1rem;
            margin: 0;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: var(--primary-gradient);
            border-radius: 50%;
            transform: translate(30%, -30%);
            opacity: 0.1;
        }

        .stat-card:hover {
            transform: var(--hover-transform);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: white;
        }

        .stat-icon.primary { background: var(--primary-gradient); }
        .stat-icon.success { background: var(--success-gradient); }
        .stat-icon.warning { background: var(--warning-gradient); }
        .stat-icon.info { background: var(--info-gradient); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .module-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
        }

        .module-card:hover {
            transform: var(--hover-transform);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        .module-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
        }

        .module-icon::after {
            content: '';
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: #28a745;
            border-radius: 50%;
            border: 3px solid white;
        }

        .module-icon.anticipos { background: var(--primary-gradient); }
        .module-icon.instalaciones { background: var(--success-gradient); }
        .module-icon.colaboradores { background: var(--warning-gradient); }
        .module-icon.bienes { background: var(--info-gradient); }
        .module-icon.admin { background: var(--dark-gradient); }

        .module-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .module-description {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .module-action {
            display: inline-flex;
            align-items: center;
            color: #667eea;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .module-action:hover {
            color: #764ba2;
            transform: translateX(5px);
        }

        .module-action i {
            margin-left: 0.5rem;
            transition: var(--transition);
        }

        .module-action:hover i {
            transform: translateX(3px);
        }

        /* Mobile Responsive */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: var(--card-shadow);
        }

        @media (max-width: 768px) {
            .modern-sidebar {
                transform: translateX(-100%);
            }

            .modern-sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .mobile-toggle {
                display: block;
            }

            .content-header h1 {
                font-size: 2rem;
            }

            .modules-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation"></div>

    <!-- Mobile Toggle -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <!-- Modern Sidebar -->
    <aside class="modern-sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="user-avatar">
                <?= strtoupper(substr($nombre, 0, 2)) ?>
            </div>
            <div class="user-info">
                <h5><?= htmlspecialchars($nombre) ?></h5>
                <p><?= htmlspecialchars($rol) ?></p>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-title">Principal</div>
                <a href="menu_moderno.php" class="menu-item">
                    <i class="bi bi-house-door"></i>
                    Dashboard
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="bi bi-box-arrow-right"></i>
                    Cerrar Sesión
                </a>
            </div>

            <?php if ($rol === 'Admin'): ?>
            <div class="menu-section">
                <div class="menu-title">Administración</div>
                <a href="usuarios.php" class="menu-item">
                    <i class="bi bi-people"></i>
                    Usuarios
                </a>
                <a href="modulos.php" class="menu-item">
                    <i class="bi bi-grid"></i>
                    Módulos
                </a>
                <a href="departamentos.php" class="menu-item">
                    <i class="bi bi-building"></i>
                    Departamentos
                </a>
            </div>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($rol === 'Admin'): ?>
        <!-- Header (solo admin) -->
        <div class="content-header">
            <div class="header-content">
                <h1>Bienvenido, <?= htmlspecialchars($nombre) ?></h1>
                <p>Gestiona todos tus módulos desde un solo lugar</p>
            </div>
        </div>

        <!-- Statistics (solo admin) -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-grid-3x3-gap"></i>
                </div>
                <div class="stat-value"><?= count($modulos) ?></div>
                <div class="stat-label">Módulos Activos</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-value">Online</div>
                <div class="stat-label">Estado del Sistema</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="stat-value"><?= date('H:i') ?></div>
                <div class="stat-label">Hora Actual</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="bi bi-calendar"></i>
                </div>
                <div class="stat-value"><?= date('d/m') ?></div>
                <div class="stat-label">Fecha Actual</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Modules Grid -->
        <div class="modules-grid">
            <?php if (in_array('Anticipos', $modulos)): ?>
            <a href="menu_anticipos.php" class="module-card">
                <div class="module-icon anticipos">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <h3 class="module-title">Anticipos y Rendiciones</h3>
                <p class="module-description">Gestiona solicitudes de anticipos y rendiciones de gastos de manera eficiente y controlada.</p>
                <div class="module-action">
                    Acceder al módulo <i class="bi bi-arrow-right"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array('Instalaciones', $modulos)): ?>
            <a href="menu_instalaciones_moderno.php" class="module-card">
                <div class="module-icon instalaciones">
                    <i class="bi bi-building"></i>
                </div>
                <h3 class="module-title">Uso de Instalaciones</h3>
                <p class="module-description">Reserva y gestiona los salones e instalaciones disponibles para eventos y actividades.</p>
                <div class="module-action">
                    Acceder al módulo <i class="bi bi-arrow-right"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($rol === 'Portero' || ($rol === 'Admin' && in_array('Instalaciones', $modulos))): ?>
            <a href="porteros.php" class="module-card">
                <div class="module-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h3 class="module-title">Panel de Porteros</h3>
                <p class="module-description">Visualiza todas las actividades y reservas de las instalaciones para estar al tanto de lo que sucede.</p>
                <div class="module-action">
                    Acceder al panel <i class="bi bi-arrow-right"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array('Colaboradores', $modulos)): ?>
            <a href="registro_asistencia/index.php" class="module-card">
                <div class="module-icon colaboradores">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h3 class="module-title">Registro de Asistencia</h3>
                <p class="module-description">Registra tu entrada y salida diaria, edita registros y gestiona tu tiempo de trabajo.</p>
                <div class="module-action">
                    Acceder al módulo <i class="bi bi-arrow-right"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array('Bienes', $modulos)): ?>
            <a href="prestamos_listado.php" class="module-card">
                <div class="module-icon bienes">
                    <i class="bi bi-box"></i>
                </div>
                <h3 class="module-title">Administración de Bienes</h3>
                <p class="module-description">Controla el préstamo y devolución de bienes y equipos del sistema.</p>
                <div class="module-action">
                    Acceder al módulo <i class="bi bi-arrow-right"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array('Personas', $modulos)): ?>
            <a href="personas/index.php" class="module-card">
                <div class="module-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h3 class="module-title">Redes y Células</h3>
                <p class="module-description">Gestión de personas, células, asistencia, organigrama y mapa de la iglesia.</p>
                <div class="module-action">
                    Acceder al módulo <i class="bi bi-arrow-right"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if (in_array('Presupuesto', $modulos)): ?>
            <a href="presupuesto/index.php" class="module-card">
                <div class="module-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="bi bi-calculator"></i>
                </div>
                <h3 class="module-title">Presupuesto Anual</h3>
                <p class="module-description">Planificación y gestión presupuestaria anual con objetivos estratégicos y seguimiento de ejecución.</p>
                <div class="module-action">
                    Acceder al módulo <i class="bi bi-arrow-right"></i>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($rol === 'Admin'): ?>
            <a href="usuarios.php" class="module-card">
                <div class="module-icon admin">
                    <i class="bi bi-gear"></i>
                </div>
                <h3 class="module-title">Administración</h3>
                <p class="module-description">Configuración avanzada del sistema, gestión de usuarios y parámetros generales.</p>
                <div class="module-action">
                    Acceder al módulo <i class="bi bi-arrow-right"></i>
                </div>
            </a>

            <?php if (in_array('Colaboradores', $modulos)): ?>
            <a href="registro_asistencia/admin_aprobaciones.php" class="module-card">
                <div class="module-icon admin">
                    <i class="bi bi-check-square"></i>
                </div>
                <h3 class="module-title">Aprobaciones de Asistencia</h3>
                <p class="module-description">Revisa y aprueba los registros de asistencia editados por los colaboradores.</p>
                <div class="module-action">
                    Acceder al módulo <i class="bi bi-arrow-right"></i>
                </div>
            </a>

            <a href="registro_asistencia/reportes_simple.php" class="module-card">
                <div class="module-icon admin">
                    <i class="bi bi-file-earmark-pdf"></i>
                </div>
                <h3 class="module-title">Reportes de Asistencia</h3>
                <p class="module-description">Genera reportes de horas trabajadas por colaborador y exporta en PDF.</p>
                <div class="module-action">
                    Ver Reportes <i class="bi bi-arrow-right"></i>
                </div>
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Animate elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all cards
        document.querySelectorAll('.stat-card, .module-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Dynamic time update
        function updateTime() {
            const timeElement = document.querySelector('.stat-value');
            if (timeElement && timeElement.textContent.includes(':')) {
                timeElement.textContent = new Date().toLocaleTimeString('es-AR', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
            }
        }

        setInterval(updateTime, 60000); // Update every minute
    </script>
</body>
</html>
