<?php
require_once __DIR__ . '/config/seguridad.php';

// Establecer headers de seguridad
headers_seguridad();

// Obtener información del usuario si está logueado
$usuario_logueado = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'No identificado';
$rol_usuario = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Desconocido';
$modulos_usuario = isset($_SESSION['modulos']) ? implode(', ', $_SESSION['modulos']) : 'Ninguno';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso Denegado - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .access-denied-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        .access-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        .icon-lock {
            font-size: 5rem;
            color: #fff;
            opacity: 0.8;
        }
        .btn-home {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 12px 30px;
        }
        .btn-home:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
        }
    </style>
</head>
<body class="access-denied-container d-flex align-items-center">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-6">
            <div class="card access-card">
                <div class="card-body p-5 text-center">
                    <div class="mb-4">
                        <i class="bi bi-shield-exclamation icon-lock"></i>
                    </div>
                    
                    <h1 class="text-danger mb-3">Acceso Denegado</h1>
                    <p class="lead text-muted mb-4">
                        No tenés permisos para acceder a esta sección del sistema.
                    </p>

                    <div class="alert alert-warning text-start">
                        <h5 class="alert-heading">
                            <i class="bi bi-info-circle"></i> Información de Acceso
                        </h5>
                        <hr>
                        <p class="mb-2"><strong>Usuario:</strong> <?= htmlspecialchars($usuario_logueado) ?></p>
                        <p class="mb-2"><strong>Rol:</strong> <?= htmlspecialchars($rol_usuario) ?></p>
                        <p class="mb-0"><strong>Módulos disponibles:</strong> <?= htmlspecialchars($modulos_usuario) ?></p>
                    </div>

                    <div class="alert alert-info text-start">
                        <h5 class="alert-heading">
                            <i class="bi bi-question-circle"></i> ¿Qué hacer?
                        </h5>
                        <hr>
                        <ul class="mb-0">
                            <li>Comunicate con el administrador del sistema si necesitás acceso a este módulo.</li>
                            <li>Verificá que estás usando la cuenta de usuario correcta.</li>
                            <li>Asegurate de tener los permisos necesarios para esta función.</li>
                        </ul>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <a href="menu.php" class="btn btn-home">
                            <i class="bi bi-house-door"></i> Volver al Menú Principal
                        </a>
                        <a href="logout_mejorado.php" class="btn btn-outline-danger">
                            <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                        </a>
                    </div>

                    <div class="mt-4">
                        <small class="text-muted">
                            <i class="bi bi-shield-check"></i>
                            Este intento de acceso ha sido registrado para fines de seguridad.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Prevenir back button
    history.pushState(null, null, location.href);
    window.onpopstate = function () {
        history.go(1);
    };
</script>
</body>
</html>
