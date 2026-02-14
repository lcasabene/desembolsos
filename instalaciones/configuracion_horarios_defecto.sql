-- Configuración de horarios permitidos para el sistema de instalaciones
-- Esta tabla reemplaza a rangos_horarios para mayor flexibilidad

-- Crear tabla si no existe (para compatibilidad)
CREATE TABLE IF NOT EXISTS configuracion_horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    dias_semana JSON NOT NULL, -- [1,2,3,4,5] para lunes a viernes
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Limpiar configuración existente (opcional)
-- DELETE FROM configuracion_horarios;

-- Insertar configuración de horarios permitidos
INSERT INTO configuracion_horarios (nombre, hora_inicio, hora_fin, dias_semana, estado) VALUES
('Turno Mañana', '06:30:00', '12:00:00', '[1,2,3,4,5]', 'activo'),
('Turno Tarde', '16:00:00', '22:00:00', '[1,2,3,4,5]', 'activo'),
('Turno Fin de Semana', '08:00:00', '22:00:00', '[6,7]', 'activo');

-- Nota: 
-- - Los usuarios pueden seleccionar cualquier hora DENTRO de estos rangos
-- - El sistema validará que el horario seleccionado esté dentro de los rangos permitidos
-- - Los días de semana: 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado, 7=Domingo
