<?php
// index.php - Punto de entrada para el módulo Anticipos

session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Incluir menú de navegación
require_once __DIR__ . '/menu.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo Anticipos</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Gestión de Anticipos</h1>
        <!-- Aquí va el contenido principal del módulo -->
        <p>Bienvenido/a, <?php echo htmlspecialchars($_SESSION['user_name']); ?>. Seleccioná una opción del menú.</p>
        <!-- Ejemplo de tabla de anticipos -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <!-- Aquí recorrerías los anticipos desde la base de datos -->
                <tr>
                    <td>1</td>
                    <td>2025-06-06</td>
                    <td>Juan Pérez</td>
                    <td>$10.000</td>
                    <td>Pendiente</td>
                    <td>
                        <a href="ver.php?id=1" class="btn btn-sm btn-primary">Ver</a>
                        <a href="editar.php?id=1" class="btn btn-sm btn-warning">Editar</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
