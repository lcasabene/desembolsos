-- Rangos horarios por defecto para el sistema de instalaciones
-- Ejecutar este script para configurar los horarios básicos

-- Limpiar rangos existentes (opcional)
-- DELETE FROM rangos_horarios;

-- Insertar rangos horarios disponibles
INSERT INTO rangos_horarios (nombre, hora_inicio, hora_fin, dias_semana, estado) VALUES
('Turno Mañana', '06:30:00', '12:00:00', '[1,2,3,4,5]', 'activo'),
('Turno Tarde', '16:00:00', '22:00:00', '[1,2,3,4,5]', 'activo'),
('Turno Fin de Semana', '08:00:00', '22:00:00', '[6,7]', 'activo');

-- Insertar algunos feriados comunes (ejemplo para Argentina)
INSERT INTO feriados (nombre, fecha, descripcion, recurrente) VALUES
('Año Nuevo', '2024-01-01', 'Año Nuevo', 'anual'),
('Día de la Independencia', '2024-07-09', 'Día de la Independencia Nacional', 'anual'),
('Navidad', '2024-12-25', 'Natividad del Señor', 'anual'),
('Viernes Santo', '2024-03-29', 'Viernes Santo', 'unico'),
('Día del Trabajador', '2024-05-01', 'Día del Trabajador', 'anual'),
('Día de la Revolución de Mayo', '2024-05-25', 'Día de la Revolución de Mayo', 'anual'),
('Día de la Soberanía Nacional', '2024-11-20', 'Día de la Soberanía Nacional', 'anual');

-- Nota: Los feriados anuales se repetirán automáticamente cada año
-- Los feriados únicos solo aplican para el año especificado
