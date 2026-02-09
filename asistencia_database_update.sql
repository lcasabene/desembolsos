-- Base de datos actualizada para múltiples registros por día
-- Ejecutar este script para actualizar la estructura existente

-- Eliminar el constraint único que impide múltiples registros por día
ALTER TABLE asistencia DROP INDEX unique_usuario_fecha;

-- Agregar campo para identificar cada registro del día
ALTER TABLE asistencia ADD COLUMN secuencia INT DEFAULT 1;

-- Crear nuevo índice compuesto para mantener orden
ALTER TABLE asistencia ADD INDEX idx_usuario_fecha_secuencia (usuario_id, fecha, secuencia);

-- Procedimiento para obtener la siguiente secuencia del día
DELIMITER //
CREATE PROCEDURE obtener_proxima_secuencia(IN p_usuario_id INT, IN p_fecha DATE, OUT p_secuencia INT)
BEGIN
    DECLARE max_seq INT DEFAULT 0;
    
    SELECT MAX(secuencia) INTO max_seq 
    FROM asistencia 
    WHERE usuario_id = p_usuario_id AND fecha = p_fecha;
    
    SET p_secuencia = IFNULL(max_seq, 0) + 1;
END //
DELIMITER ;

-- Vista para facilitar consultas
CREATE OR REPLACE VIEW v_resumen_asistencia_diaria AS
SELECT 
    usuario_id,
    fecha,
    COUNT(*) as total_registros,
    SUM(CASE 
        WHEN hora_entrada IS NOT NULL AND hora_salida IS NOT NULL 
        THEN TIMESTAMPDIFF(MINUTE, hora_entrada, hora_salida) / 60 
        ELSE 0 
    END) as total_horas,
    MIN(hora_entrada) as primera_entrada,
    MAX(hora_salida) as ultima_salida,
    MAX(CASE WHEN estado = 'pendiente_aprobacion' THEN 1 ELSE 0 END) as tiene_pendientes
FROM asistencia 
GROUP BY usuario_id, fecha;
