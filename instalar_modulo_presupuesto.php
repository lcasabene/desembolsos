<?php
/**
 * Script para instalar el módulo de Presupuesto en el sistema
 * Este script agrega el módulo a la base de datos y lo asigna a los usuarios administradores
 */

require_once 'config/database.php';

echo "<h1>Instalación del Módulo de Presupuesto</h1>";

try {
    // 1. Verificar si la tabla modulos existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'modulos'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: orange;'>⚠️ La tabla 'modulos' no existe. Creándola...</p>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS modulos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL UNIQUE,
                activo TINYINT(1) DEFAULT 1,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p style='color: green;'>✅ Tabla 'modulos' creada correctamente</p>";
    }

    // 2. Insertar el módulo Presupuesto si no existe
    $stmt = $pdo->prepare("SELECT id FROM modulos WHERE nombre = 'Presupuesto'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $stmt = $pdo->prepare("INSERT INTO modulos (nombre, activo) VALUES ('Presupuesto', 1)");
        $stmt->execute();
        echo "<p style='color: green;'>✅ Módulo 'Presupuesto' agregado correctamente</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ El módulo 'Presupuesto' ya existe</p>";
    }

    // 3. Obtener el ID del módulo Presupuesto
    $stmt = $pdo->prepare("SELECT id FROM modulos WHERE nombre = 'Presupuesto'");
    $stmt->execute();
    $modulo_presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);
    $modulo_id = $modulo_presupuesto['id'];

    // 4. Verificar si la tabla usuario_modulos existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuario_modulos'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: orange;'>⚠️ La tabla 'usuario_modulos' no existe. Creándola...</p>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usuario_modulos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                modulo_id INT NOT NULL,
                fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_usuario_modulo (usuario_id, modulo_id),
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p style='color: green;'>✅ Tabla 'usuario_modulos' creada correctamente</p>";
    }

    // 5. Asignar el módulo a todos los usuarios administradores
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO usuario_modulos (usuario_id, modulo_id) 
        SELECT u.id, ? 
        FROM usuarios u 
        WHERE u.rol = 'Admin' AND u.activo = 1
    ");
    $stmt->execute([$modulo_id]);
    
    $afectados = $stmt->rowCount();
    if ($afectados > 0) {
        echo "<p style='color: green;'>✅ Módulo asignado a {$afectados} administrador(es)</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Los administradores ya tenían el módulo asignado</p>";
    }

    // 6. Verificar instalación
    echo "<h2>📋 Verificación de Instalación</h2>";
    
    // Verificar módulos disponibles
    $stmt = $pdo->query("SELECT * FROM modulos ORDER BY nombre");
    $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Módulos Disponibles:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Activo</th></tr>";
    foreach ($modulos as $modulo) {
        $estado = $modulo['activo'] ? '✅ Sí' : '❌ No';
        echo "<tr><td>{$modulo['id']}</td><td>{$modulo['nombre']}</td><td>{$estado}</td></tr>";
    }
    echo "</table>";

    // Verificar asignaciones
    $stmt = $pdo->prepare("
        SELECT 
            u.nombre as usuario,
            u.rol,
            m.nombre as modulo,
            um.fecha_asignacion
        FROM usuario_modulos um
        JOIN usuarios u ON um.usuario_id = u.id
        JOIN modulos m ON um.modulo_id = m.id
        WHERE m.nombre = 'Presupuesto'
        ORDER BY u.nombre
    ");
    $stmt->execute();
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Usuarios con Módulo Presupuesto:</h3>";
    if (count($asignaciones) > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Usuario</th><th>Rol</th><th>Módulo</th><th>Fecha Asignación</th></tr>";
        foreach ($asignaciones as $asignacion) {
            echo "<tr>";
            echo "<td>{$asignacion['usuario']}</td>";
            echo "<td>{$asignacion['rol']}</td>";
            echo "<td>{$asignacion['modulo']}</td>";
            echo "<td>{$asignacion['fecha_asignacion']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ No hay usuarios con el módulo Presupuesto asignado</p>";
    }

    echo "<h2>🎉 Instalación Completada</h2>";
    echo "<p>El módulo de Presupuesto está listo para usarse. Los usuarios administradores deberían verlo en el menú principal.</p>";
    echo "<p><a href='menu_moderno.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Menú Principal</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error durante la instalación: " . $e->getMessage() . "</p>";
    echo "<p>Verifique la conexión a la base de datos y los permisos.</p>";
}
?>
