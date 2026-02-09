<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion('Anticipos');

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
    <title>Anticipos y Rendiciones - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            -webkit-text-fill-color: #667eea;
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
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
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
            border-color: rgba(102, 126, 234, 0.3);
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

        .feature-icon.nuevo-anticipo { background: var(--primary-gradient); }
        .feature-icon.mis-anticipos { background: var(--success-gradient); }
        .feature-icon.rendiciones { background: var(--warning-gradient); }
        .feature-icon.aprobaciones { background: var(--secondary-gradient); }
        .feature-icon.reportes { background: var(--info-gradient); }
        .feature-icon.admin { background: var(--dark-gradient); }

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
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.95rem;
        }

        .feature-action:hover {
            color: #764ba2;
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
            border: 2px solid rgba(102, 126, 234, 0.3);
            border-radius: 50%;
            border-top-color: #667eea;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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
                <i class="bi bi-cash-stack"></i>
                Anticipos
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
                <i class="bi bi-cash-stack"></i> Anticipos y Rendiciones
            </h1>
            <p class="hero-subtitle">
                Gestiona solicitudes de anticipos y rendiciones de manera eficiente y controlada
            </p>
        </div>
    </section>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-cash"></i>
            </div>
            <div class="stat-value" id="totalAnticipos">-</div>
            <div class="stat-label">Anticipos Activos</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-receipt"></i>
            </div>
            <div class="stat-value" id="totalRendiciones">-</div>
            <div class="stat-label">Rendiciones Pendientes</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value" id="montoTotal">-</div>
            <div class="stat-label">Monto Total Mes</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="stat-value" id="pendientes">-</div>
            <div class="stat-label">Por Aprobar</div>
        </div>
    </div>

    <!-- Features Grid -->
    <div class="features-grid">
        <!-- Nuevo Anticipo -->
        <a href="nueva_solicitud.php" class="feature-card">
            <div class="feature-content">
                <div class="feature-icon nuevo-anticipo">
                    <i class="bi bi-plus-circle"></i>
                </div>
                <h3 class="feature-title">Nuevo Anticipo</h3>
                <p class="feature-description">
                    Solicita un nuevo anticipo de forma rápida y sencilla con validación automática de montos y fechas.
                </p>
                <div class="feature-action">
                    Crear Solicitud <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </a>

        <!-- Mis Anticipos -->
        <a href="listado_solicitudes.php" class="feature-card">
            <div class="feature-content">
                <div class="feature-icon mis-anticipos">
                    <i class="bi bi-list-check"></i>
                </div>
                <h3 class="feature-title">Mis Anticipos</h3>
                <p class="feature-description">
                    Consulta y gestiona todas tus solicitudes de anticipos con estados y fechas de vencimiento.
                </p>
                <div class="feature-action">
                    Ver Mis Anticipos <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </a>

        <!-- Rendiciones -->
        <a href="rendiciones_moderno.php" class="feature-card">
            <div class="feature-content">
                <div class="feature-icon rendiciones">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <h3 class="feature-title">Rendiciones</h3>
                <p class="feature-description">
                    Presenta rendiciones de gastos con comprobantes y seguimiento de saldos pendientes.
                </p>
                <div class="feature-action">
                    Gestionar Rendiciones <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </a>

        <!-- Sección Admin -->
        <?php if ($rol === 'Admin'): ?>
        <!-- Aprobaciones -->
        <a href="aprobaciones.php" class="feature-card" style="position: relative;">
            <div class="admin-badge">
                <i class="bi bi-shield-check"></i> Admin
            </div>
            <div class="feature-content">
                <div class="feature-icon aprobaciones">
                    <i class="bi bi-clipboard-check"></i>
                </div>
                <h3 class="feature-title">Aprobaciones</h3>
                <p class="feature-description">
                    Revisa y aprueba solicitudes de anticipos con filtros por monto, departamento y fechas.
                </p>
                <div class="feature-action">
                    Gestionar Aprobaciones <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </a>

        <!-- Revisión de Rendiciones -->
        <a href="revisar_rendiciones.php" class="feature-card" style="position: relative;">
            <div class="admin-badge">
                <i class="bi bi-shield-check"></i> Admin
            </div>
            <div class="feature-content">
                <div class="feature-icon reportes">
                    <i class="bi bi-graph-up"></i>
                </div>
                <h3 class="feature-title">Revisión de Rendiciones</h3>
                <p class="feature-description">
                    Revisa y aprueba rendiciones de gastos con validación de comprobantes y montos.
                </p>
                <div class="feature-action">
                    Revisar Rendiciones <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </a>

        <!-- Dashboard -->
        <a href="dashboard_estadistico.php" class="feature-card" style="position: relative;">
            <div class="admin-badge">
                <i class="bi bi-shield-check"></i> Admin
            </div>
            <div class="feature-content">
                <div class="feature-icon admin">
                    <i class="bi bi-gear"></i>
                </div>
                <h3 class="feature-title">Dashboard Estadístico</h3>
                <p class="feature-description">
                    Visualiza estadísticas detalladas, gráficos y análisis de anticipos y rendiciones.
                </p>
                <div class="feature-action">
                    Ver Dashboard <i class="bi bi-arrow-right"></i>
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
                document.getElementById('totalAnticipos').textContent = '15';
                document.getElementById('totalRendiciones').textContent = '8';
                document.getElementById('montoTotal').textContent = '$45.2K';
                document.getElementById('pendientes').textContent = '3';
            }, 1000);

            // Cargar datos reales si existe la API
            fetch('api_estadisticas_anticipos.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('totalAnticipos').textContent = data.totalAnticipos || '15';
                    document.getElementById('totalRendiciones').textContent = data.totalRendiciones || '8';
                    document.getElementById('montoTotal').textContent = data.montoTotal || '$45.2K';
                    document.getElementById('pendientes').textContent = data.pendientes || '3';
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
