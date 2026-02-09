<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion('Instalaciones');

// Redirigir al menú moderno
header('Location: menu_instalaciones_moderno.php');
exit;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Uso de Instalaciones - Sistema de Gestión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .feature-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .icon-large {
            font-size: 3rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="menu.php">
                <i class="bi bi-house-door"></i> Inicio
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Instalaciones</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($nombre) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 class="display-4 text-primary">
                <i class="bi bi-building"></i> Uso de Instalaciones
            </h1>
            <p class="lead">Gestiona la reserva y uso de los salones de la iglesia</p>
        </div>

        <div class="row g-4">
            <!-- Disponibilidad Mejorada -->
            <div class="col-md-6 col-lg-4">
                <div class="card feature-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-info mb-3">
                            <i class="bi bi-grid-3x3-gap icon-large"></i>
                        </div>
                        <h5 class="card-title">Disponibilidad</h5>
                        <p class="card-text">Vista mejorada de horarios disponibles</p>
                        <a href="instalaciones/disponibilidad_simple.php" class="btn btn-info">
                            <i class="bi bi-eye"></i> Ver Disponibilidad
                        </a>
                    </div>
                </div>
            </div>

            <!-- Módulo de Reservas -->
            <div class="col-md-6 col-lg-4">
                <div class="card feature-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-primary mb-3">
                            <i class="bi bi-calendar-plus icon-large"></i>
                        </div>
                        <h5 class="card-title">Nueva Reserva</h5>
                        <p class="card-text">Solicita el uso de un salón para tu evento o actividad</p>
                        <a href="instalaciones/nueva_reserva_v2.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Crear Reserva
                        </a>
                    </div>
                </div>
            </div>

            <!-- Calendario de Reservas -->
            <div class="col-md-6 col-lg-4">
                <div class="card feature-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-success mb-3">
                            <i class="bi bi-calendar-week icon-large"></i>
                        </div>
                        <h5 class="card-title">Calendario</h5>
                        <p class="card-text">Visualiza todas las reservas y disponibilidad</p>
                        <a href="instalaciones/calendario.php" class="btn btn-success">
                            <i class="bi bi-calendar3"></i> Ver Calendario
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mis Reservas -->
            <div class="col-md-6 col-lg-4">
                <div class="card feature-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-info mb-3">
                            <i class="bi bi-list-check icon-large"></i>
                        </div>
                        <h5 class="card-title">Mis Reservas</h5>
                        <p class="card-text">Gestiona tus solicitudes de reserva</p>
                        <a href="instalaciones/mis_reservas.php" class="btn btn-info">
                            <i class="bi bi-eye"></i> Ver Mis Reservas
                        </a>
                    </div>
                </div>
            </div>

            <!-- Gestión de Salones (Solo Admin) -->
            <?php if ($rol === 'Admin'): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card feature-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-warning mb-3">
                            <i class="bi bi-door-open icon-large"></i>
                        </div>
                        <h5 class="card-title">Salones</h5>
                        <p class="card-text">Administra los salones disponibles</p>
                        <a href="instalaciones/salones.php" class="btn btn-warning">
                            <i class="bi bi-gear"></i> Gestionar Salones
                        </a>
                    </div>
                </div>
            </div>

            <!-- Aprobación de Reservas (Solo Admin) -->
            <div class="col-md-6 col-lg-4">
                <div class="card feature-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-danger mb-3">
                            <i class="bi bi-clipboard-check icon-large"></i>
                        </div>
                        <h5 class="card-title">Aprobaciones</h5>
                        <p class="card-text">Revisa y aprueba las solicitudes pendientes</p>
                        <a href="instalaciones/aprobaciones.php" class="btn btn-danger">
                            <i class="bi bi-check-square"></i> Gestionar Aprobaciones
                        </a>
                    </div>
                </div>
            </div>

            <!-- Feriados (Solo Admin) -->
            <div class="col-md-6 col-lg-4">
                <div class="card feature-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-secondary mb-3">
                            <i class="bi bi-calendar-x icon-large"></i>
                        </div>
                        <h5 class="card-title">Feriados</h5>
                        <p class="card-text">Configura días no disponibles</p>
                        <a href="instalaciones/feriados.php" class="btn btn-secondary">
                            <i class="bi bi-calendar2-x"></i> Gestionar Feriados
                        </a>
                    </div>
                </div>
            </div>

            <!-- Reportes (Solo Admin) -->
            <div class="col-md-6 col-lg-4">
                <div class="card feature-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-dark mb-3">
                            <i class="bi bi-graph-up icon-large"></i>
                        </div>
                        <h5 class="card-title">Reportes</h5>
                        <p class="card-text">Estadísticas y reportes de uso</p>
                        <a href="instalaciones/reportes.php" class="btn btn-dark">
                            <i class="bi bi-bar-chart"></i> Ver Reportes
                        </a>
                    </div>
                </div>
            </div>

            <!-- Configuración (Solo Admin) -->
            <div class="col-md-6 col-lg-4">
                <div class="card feature-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-primary mb-3">
                            <i class="bi bi-gear icon-large"></i>
                        </div>
                        <h5 class="card-title">Configuración</h5>
                        <p class="card-text">Parámetros del sistema de reservas</p>
                        <a href="instalaciones/configuracion.php" class="btn btn-primary">
                            <i class="bi bi-sliders"></i> Configurar Sistema
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Resumen Rápido -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Resumen Rápido</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h3 class="text-primary" id="totalSalones">-</h3>
                                <p class="mb-0">Salones Disponibles</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-success" id="reservasHoy">-</h3>
                                <p class="mb-0">Reservas Hoy</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-warning" id="pendientes">-</h3>
                                <p class="mb-0">Pendientes de Aprobación</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-info" id="misReservasCount">-</h3>
                                <p class="mb-0">Mis Reservas Activas</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cargar estadísticas rápidas
        document.addEventListener('DOMContentLoaded', function() {
            fetch('instalaciones/api_estadisticas.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('totalSalones').textContent = data.totalSalones || 0;
                    document.getElementById('reservasHoy').textContent = data.reservasHoy || 0;
                    document.getElementById('pendientes').textContent = data.pendientes || 0;
                    document.getElementById('misReservasCount').textContent = data.misReservas || 0;
                })
                .catch(error => console.error('Error:', error));
        });
    </script>
</body>
</html>
