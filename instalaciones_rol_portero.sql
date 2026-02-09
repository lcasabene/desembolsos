-- Script para agregar el rol 'Portero' al sistema de instalaciones
-- Ejecutar este script en tu base de datos existente

-- 1. Insertar el nuevo rol 'Portero' en la tabla de usuarios (si existe campo rol)
-- Nota: Asumiendo que la tabla usuarios ya tiene un campo 'rol' tipo ENUM o VARCHAR

-- 2. Insertar el módulo 'Instalaciones' si no existe
INSERT IGNORE INTO modulos (nombre) VALUES ('Instalaciones');

-- 3. Insertar el módulo 'Porteros' si no existe
INSERT IGNORE INTO modulos (nombre) VALUES ('Porteros');

-- 3. Insertar el nuevo parámetro de configuración para porteros
INSERT INTO configuracion_instalaciones (parametro, valor, descripcion) VALUES
('permiso_admin_duracion', 'false', 'Permite a administradores extender la duración de reservas existentes')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- 4. Crear tabla de permisos específicos para porteros (si no existe)
CREATE TABLE IF NOT EXISTS instalaciones_permisos_especiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    modulo VARCHAR(50) NOT NULL DEFAULT 'Instalaciones',
    permiso VARCHAR(50) NOT NULL, -- 'ver_actividades', 'modificar_duracion', etc.
    concedido_por INT NOT NULL,
    fecha_concesion DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'revocado') DEFAULT 'activo',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (concedido_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_permiso (usuario_id, modulo, permiso)
);

-- 5. Insertar permisos por defecto para el rol Portero
-- Nota: Esto requiere que los usuarios con rol 'Portero' ya existan
-- El administrador podrá asignar estos permisos desde la interfaz

-- 6. Actualizar la tabla usuarios para asegurar que el campo rol permita 'Portero'
-- Si el campo rol es un ENUM, necesitarás modificarlo:
-- ALTER TABLE usuarios MODIFY COLUMN rol ENUM('Solicitante', 'Aprobador', 'Admin', 'Portero') DEFAULT 'Solicitante';

-- 7. Vista para consultar usuarios con permisos de instalaciones
CREATE OR REPLACE VIEW vista_instalaciones_permisos AS
SELECT 
    u.id,
    u.nombre,
    u.email,
    u.rol,
    CASE 
        WHEN u.rol = 'Admin' THEN 'completo'
        WHEN u.rol = 'Portero' THEN 'limitado'
        WHEN EXISTS (
            SELECT 1 FROM usuario_modulos um 
            WHERE um.usuario_id = u.id AND um.modulo = 'Instalaciones'
        ) THEN 'basico'
        ELSE 'ninguno'
    END AS nivel_acceso_instalaciones,
    GROUP_CONCAT(um.modulo ORDER BY um.modulo SEPARATOR ', ') AS modulos_asignados
FROM usuarios u
LEFT JOIN usuario_modulos um ON u.id = um.usuario_id
WHERE u.activo = 1
GROUP BY u.id, u.nombre, u.email, u.rol;

-- Notas de instalación:
/*
1. Si tu tabla usuarios usa ENUM para el campo rol, ejecuta:
   ALTER TABLE usuarios MODIFY COLUMN rol ENUM('Solicitante', 'Aprobador', 'Admin', 'Portero') DEFAULT 'Solicitante';

2. Para asignar el rol Portero a un usuario existente:
   UPDATE usuarios SET rol = 'Portero' WHERE id = [ID_USUARIO];

3. Los porteros tendrán acceso limitado:
   - Podrán ver todas las actividades/reservas
   - No podrán aprobar/rechazar reservas (solo Admin)
   - Podrán ver calendario y reportes
   - No podrán modificar configuración (solo Admin)

4. La configuración de permisos se gestionará desde:
   - usuarios.php (asignar rol)
   - configuracion.php (configurar límites y permisos especiales)
*/
