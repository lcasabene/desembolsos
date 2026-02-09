-- Base de datos para el módulo de Registro de Asistencia
-- Ejecutar este script en tu base de datos existente

-- Tabla de registros de asistencia
CREATE TABLE IF NOT EXISTS asistencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_entrada TIME NULL,
    hora_salida TIME NULL,
    ubicacion_entrada VARCHAR(255) NULL, -- Para futuro GPS
    ubicacion_salida VARCHAR(255) NULL,  -- Para futuro GPS
    estado ENUM('aprobado', 'pendiente_aprobacion', 'rechazado') DEFAULT 'aprobado',
    observaciones TEXT NULL,
    editado_por INT NULL, -- ID del admin que editó/aprobó
    fecha_edicion TIMESTAMP NULL, -- Cuándo se editó
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para mejor rendimiento
    INDEX idx_usuario_fecha (usuario_id, fecha),
    INDEX idx_fecha (fecha),
    INDEX idx_estado (estado),
    
    -- Constraint para evitar duplicados del mismo día
    UNIQUE KEY unique_usuario_fecha (usuario_id, fecha)
);

-- Tabla para auditoría de cambios
CREATE TABLE IF NOT EXISTS asistencia_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asistencia_id INT NOT NULL,
    usuario_id INT NOT NULL, -- Quién hizo el cambio
    accion ENUM('crear', 'editar_entrada', 'editar_salida', 'aprobar', 'rechazar') NOT NULL,
    valor_anterior TEXT NULL,
    valor_nuevo TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_asistencia_id (asistencia_id),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_accion (accion)
);

-- Insertar algunos datos de ejemplo (opcional)
INSERT IGNORE INTO asistencia (usuario_id, fecha, hora_entrada, hora_salida, estado) VALUES
(1, CURDATE(), '09:00:00', '17:00:00', 'aprobado'),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:45:00', '17:15:00', 'aprobado'),
(1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:15:00', '17:30:00', 'aprobado');
