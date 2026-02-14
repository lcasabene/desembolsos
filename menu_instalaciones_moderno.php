<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion('Instalaciones');

require_once 'config/database.php';

$nombre = $_SESSION['user_name'] ?? 'Usuario';
$rol = $_SESSION['user_role'] ?? 'User';
$avatar = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalaciones - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --secondary-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --danger-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            --card-shadow: 0 15px 35px rgba(0,0,0,0.1);
            --hover-transform: translateY(-8px) scale(1.02);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-pattern {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            opacity: 0.1;
            background-image: 
                radial-gradient(circle at 20% 80%, white 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, white 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, white 0%, transparent 50%);
        }

        /* Modern Navbar */
        .modern-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 5px 30px rgba(0,0,0,0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand i {
            font-size: 1.8rem;
            -webkit-text-fill-color: #4facfe;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            box-shadow: 0 3px 10px rgba(79, 172, 254, 0.3);
        }

        /* Hero Section */
        .hero-section {
            padding: 4rem 0;
            text-align: center;
            position: relative;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 1rem;
            text-shadow: 0 5px 20px rgba(0,0,0,0.2);
            animation: fadeInUp 1s ease;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            animation: fadeInUp 1s ease 0.2s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 3rem 0;
            padding: 0 1rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
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
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
            color: white;
        }

        .stat-icon.primary { background: var(--primary-gradient); }
        .stat-icon.success { background: var(--success-gradient); }
        .stat-icon.warning { background: var(--warning-gradient); }
        .stat-icon.danger { background: var(--danger-gradient); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            padding: 2rem 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 2.5rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            border: 2px solid transparent;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary-gradient);
            opacity: 0;
            transition: var(--transition);
            z-index: 0;
        }

        .feature-card:hover::before {
            opacity: 0.05;
        }

        .feature-card:hover {
            transform: var(--hover-transform);
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
            border-color: rgba(79, 172, 254, 0.3);
        }

        .feature-content {
            position: relative;
            z-index: 1;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .feature-icon::after {
            content: '';
            position: absolute;
            top: -3px;
            right: -3px;
            width: 16px;
            height: 16px;
            background: #28a745;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.4);
        }

        .feature-icon.disponibilidad { background: var(--primary-gradient); }
        .feature-icon.reserva { background: var(--success-gradient); }
        .feature-icon.calendario { background: var(--info-gradient); }
        .feature-icon.mis-reservas { background: var(--warning-gradient); }
        .feature-icon.salones { background: var(--danger-gradient); }
        .feature-icon.aprobaciones { background: var(--secondary-gradient); }
        .feature-icon.feriados { background: var(--dark-gradient); }
        .feature-icon.reportes { background: var(--info-gradient); }
        .feature-icon.configuracion { background: var(--primary-gradient); }

        .feature-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .feature-description {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .feature-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #4facfe;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.95rem;
        }

        .feature-action:hover {
            color: #00f2fe;
            transform: translateX(5px);
        }

        .feature-action i {
            transition: var(--transition);
        }

        .feature-action:hover i {
            transform: translateX(3px);
        }

        /* Admin Badge */
        .admin-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--danger-gradient);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 3px 10px rgba(240, 147, 251, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
                padding: 1rem;
            }

            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
                padding: 0 1rem;
            }

            .feature-card {
                padding: 2rem;
            }
        }

        /* Loading Animation */
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(79, 172, 254, 0.3);
            border-radius: 50%;
            border-top-color: #4facfe;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Pulse Animation */
        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <!-- Background Pattern -->
    <div class="bg-pattern"></div>

    <!-- Modern Navbar -->
    <nav class="navbar navbar-expand-lg modern-navbar">
        <div class="container">
            <a class="navbar-brand" href="menu_moderno.php">
                <i class="bi bi-building"></i>
                Instalaciones
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="user-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($nombre, 0, 2)) ?>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <span class="text-dark fw-semibold"><?= htmlspecialchars($nombre) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="menu_moderno.php">
                                <i class="bi bi-house-door"></i> Inicio
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">
                <i class="bi bi-building"></i> Uso de Instalaciones
            </h1>
            <p class="hero-subtitle">
                Gestiona la reserva y uso de los salones de manera eficiente y moderna
            </p>
        </div>
    </section>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-door-open"></i>
            </div>
            <div class="stat-value" id="totalSalones">-</div>
            <div class="stat-label">Salones Disponibles</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-value" id="reservasHoy">-</div>
            <div class="stat-label">Reservas Hoy</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value" id="pendientes">-</div>
            <div class="stat-label">Pendientes</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="bi bi-list-check"></i>
            </div>
            <div class="stat-value" id="misReservas">-</div>
            <div class="stat-label">Mis Reservas</div>
        </div>
    </div>

    <!-- Features Grid -->
    <div class="features-grid">
        <!-- Disponibilidad -->
        <a href="instalaciones/disponibilidad_simple.php" class="feature-card">
            <div class="feature-content">
                <div class="feature-icon disponibilidad">
                    <i class="bi bi-grid-3x3-gap"></i>
                </div>
                <h3 class="feature-title">Disponibilidad</h3>
                <p class="feature-description">
                    Vista completa y en tiempo real de la disponibilidad de todos los salones con navegación intuitiva por fechas.
                </p>
                <div class="feature-action">
                    Ver Disponibilidad <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </a>

        <!-- Nueva Reserva -->
        <a href="instalaciones/nueva_reserva.php" class="feature-card">
            <div class="feature-content">
                <div class="feature-icon reserva">
                    <i class="bi bi-calendar-plus"></i>
                </div>
                <h3 class="feature-title">Nueva Reserva</h3>
                <p class="feature-description">
                    Crea solicitudes de reserva de forma rápida y sencilla con validación automática de disponibilidad.
                </p>
                <div class="feature-action">
                    Crear Reserva <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </a>

        <!-- Calendario -->
        <a href="instalaciones/calendario.php" class="feature-card">
            <div class="feature-content">
                <div class="feature-icon calendario">
                    <i class="bi bi-calendar-week"></i>
                </div>
                <h3 class="feature-title">Calendario</h3>
                <p class="feature-description">
                    Vista calendario mensual con todas las reservas programadas y herramientas de filtrado avanzadas.
                </p>
                <div class="feature-action">
                    Ver Calendario <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </a>

        <!-- Mis Reservas -->
        <a href="instalaciones/mis_reservas.php" class="feature-card">
            <div class="feature-content">
                <div class="feature-icon mis-reservas">
                    <i class="bi bi-list-check"></i>
                </div>
                <h3 class="feature-title">Mis Reservas</h3>
                <p class="feature-description">
                    Gestiona y seguimiento de todas tus solicitudes de reserva con estados y notificaciones.
                </p>
                <div class="feature-action">
                    Ver Mis Reservas <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </a>

        <!-- Sección Admin -->
        <?php if ($rol === 'Admin'): ?>
            <!-- Salones -->
            <a href="instalaciones/salones.php" class="feature-card" style="position: relative;">
                <div class="admin-badge">
                    <i class="bi bi-shield-check"></i> Admin
                </div>
                <div class="feature-content">
                    <div class="feature-icon salones">
                        <i class="bi bi-door-open"></i>
                    </div>
                    <h3 class="feature-title">Salones</h3>
                    <p class="feature-description">
                        Administra los salones disponibles, configura capacidades y establece horarios de operación.
                    </p>
                    <div class="feature-action">
                        Gestionar Salones <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </a>

            <!-- Aprobaciones -->
            <a href="instalaciones/aprobaciones.php" class="feature-card" style="position: relative;">
                <div class="admin-badge">
                    <i class="bi bi-shield-check"></i> Admin
                </div>
                <div class="feature-content">
                    <div class="feature-icon aprobaciones">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <h3 class="feature-title">Aprobaciones</h3>
                    <p class="feature-description">
                        Revisa y aprueba las solicitudes de reserva pendientes con filtros y herramientas de gestión.
                    </p>
                    <div class="feature-action">
                        Gestionar Aprobaciones <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </a>

            <!-- Feriados -->
            <a href="instalaciones/feriados.php" class="feature-card" style="position: relative;">
                <div class="admin-badge">
                    <i class="bi bi-shield-check"></i> Admin
                </div>
                <div class="feature-content">
                    <div class="feature-icon feriados">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h3 class="feature-title">Feriados</h3>
                    <p class="feature-description">
                        Configura días no disponibles, feriados y períodos de cierre de instalaciones.
                    </p>
                    <div class="feature-action">
                        Gestionar Feriados <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </a>

            <!-- Reportes -->
            <a href="instalaciones/reportes.php" class="feature-card" style="position: relative;">
                <div class="admin-badge">
                    <i class="bi bi-shield-check"></i> Admin
                </div>
                <div class="feature-content">
                    <div class="feature-icon reportes">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h3 class="feature-title">Reportes</h3>
                    <p class="feature-description">
                        Genera reportes estadísticos, análisis de uso y exportación de datos del sistema.
                    </p>
                    <div class="feature-action">
                        Ver Reportes <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </a>

            <!-- Configuración -->
            <a href="instalaciones/configuracion.php" class="feature-card" style="position: relative;">
                <div class="admin-badge">
                    <i class="bi bi-shield-check"></i> Admin
                </div>
                <div class="feature-content">
                    <div class="feature-icon configuracion">
                        <i class="bi bi-gear"></i>
                    </div>
                    <h3 class="feature-title">Configuración</h3>
                    <p class="feature-description">
                        Ajusta parámetros del sistema, límites de reserva, horarios, feriados y gestión de porteros.
                    </p>
                    <div class="feature-action">
                        Configurar Sistema <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cargar estadísticas
        document.addEventListener('DOMContentLoaded', function() {
            // Simular carga de datos (reemplazar con API real)
            setTimeout(() => {
                document.getElementById('totalSalones').textContent = '6';
                document.getElementById('reservasHoy').textContent = '12';
                document.getElementById('pendientes').textContent = '3';
                document.getElementById('misReservas').textContent = '2';
            }, 1000);

            // Cargar datos reales si existe la API
            fetch('instalaciones/api_estadisticas.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('totalSalones').textContent = data.totalSalones || '6';
                    document.getElementById('reservasHoy').textContent = data.reservasHoy || '12';
                    document.getElementById('pendientes').textContent = data.pendientes || '3';
                    document.getElementById('misReservas').textContent = data.misReservas || '2';
                })
                .catch(error => {
                    console.log('Usando datos de demostración');
                });
        });

        // Animar elementos al hacer scroll
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

        // Observar tarjetas
        document.querySelectorAll('.stat-card, .feature-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            observer.observe(card);
        });

        // Efecto hover en tarjetas de características
        document.querySelectorAll('.feature-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Navegación suave
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>
