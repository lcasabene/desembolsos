-- Script para agregar el módulo de Presupuesto al sistema
-- Ejecutar este script si el módulo no aparece en el menú

-- Verificar si el módulo Presupuesto ya existe
-- Si no existe, insertarlo
INSERT IGNORE INTO modulos (nombre, activo) VALUES ('Presupuesto', 1);

-- Verificar módulos existentes
SELECT * FROM modulos ORDER BY nombre;

-- Para asignar el módulo a un usuario específico, ejecutar:
-- (Reemplazar 'ID_USUARIO' con el ID real del usuario)
-- INSERT IGNORE INTO usuario_modulos (usuario_id, modulo_id) 
-- SELECT ID_USUARIO, id FROM modulos WHERE nombre = 'Presupuesto';

-- Para asignar el módulo a todos los usuarios administradores:
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo_id)
SELECT u.id, m.id 
FROM usuarios u 
CROSS JOIN modulos m 
WHERE m.nombre = 'Presupuesto' 
AND u.rol = 'Admin';

-- Verificar asignaciones
SELECT 
    u.nombre as usuario,
    u.rol,
    m.nombre as modulo
FROM usuario_modulos um
JOIN usuarios u ON um.usuario_id = u.id
JOIN modulos m ON um.modulo_id = m.id
WHERE m.nombre = 'Presupuesto'
ORDER BY u.nombre;
