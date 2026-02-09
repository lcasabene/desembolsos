-- Base de datos actualizada para el módulo de Instalaciones
-- Ejecutar este script para actualizar tu base de datos existente

-- 1. Primero, eliminar la tabla reservas antigua si existe
DROP TABLE IF EXISTS reservas;

-- 2. Crear la nueva tabla de reservas con soporte para horas personalizadas y recurrentes
CREATE TABLE IF NOT EXISTS reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT NOT NULL,
    usuario_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    motivo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    estado ENUM('pendiente', 'aprobada', 'rechazada', 'cancelada') DEFAULT 'pendiente',
    es_recurrente ENUM('no', 'semanal', 'mensual', 'anual') DEFAULT 'no',
    fecha_fin_recurrente DATE NULL, -- Fecha hasta cuando es recurrente
    dias_semana JSON NULL, -- Para recurrente semanal: [1,2,3,4,5,6,7] donde 1=Lunes, 7=Domingo
    aprobado_por INT NULL,
    fecha_aprobacion DATETIME NULL,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (salon_id) REFERENCES salones(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- 3. Crear índices para mejor rendimiento
CREATE INDEX idx_reservas_fecha ON reservas(fecha);
CREATE INDEX idx_reservas_estado ON reservas(estado);
CREATE INDEX idx_reservas_usuario ON reservas(usuario_id);
CREATE INDEX idx_recurrente ON reservas(es_recurrente, fecha, fecha_fin_recurrente);
CREATE INDEX idx_reservas_salon_fecha ON reservas(salon_id, fecha);

-- 4. Insertar rangos horarios (como referencia, ya no se usan directamente en reservas)
CREATE TABLE IF NOT EXISTS rangos_horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    dias_semana JSON NOT NULL, -- [1,2,3,4,5] para lunes a viernes
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO rangos_horarios (nombre, hora_inicio, hora_fin, dias_semana) VALUES
('Mañana', '08:00:00', '12:00:00', '[1,2,3,4,5,6]'),
('Tarde', '14:00:00', '18:00:00', '[1,2,3,4,5,6]'),
('Noche', '19:00:00', '22:00:00', '[1,2,3,4,5,6]'),
('Domingo Mañana', '09:00:00', '13:00:00', '[7]'),
('Domingo Tarde', '15:00:00', '19:00:00', '[7]');

-- 5. Tabla de feriados/días no disponibles
CREATE TABLE IF NOT EXISTS feriados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    fecha DATE NOT NULL,
    descripcion TEXT,
    recurrente ENUM('anual', 'unico') DEFAULT 'unico',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_fecha (fecha)
);

-- 6. Tabla de configuración general con nuevos parámetros
CREATE TABLE IF NOT EXISTS configuracion_instalaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parametro VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT NOT NULL,
    descripcion TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO configuracion_instalaciones (parametro, valor, descripcion) VALUES
('max_dias_anticipacion', '30', 'Máximo de días de anticipación para reservar'),
('min_horas_anticipacion', '2', 'Mínimo de horas de anticipación para reservar'),
('max_duracion_reserva', '4', 'Duración máxima en horas por reserva'),
('max_horas_diarias', '8', 'Máximo de horas totales que un usuario puede reservar por día'),
('max_reservas_semana', '5', 'Máximo de reservas que un usuario puede hacer por semana'),
('requiere_aprobacion', 'true', 'Las reservas requieren aprobación automática'),
('whatsapp_enabled', 'false', 'Activar notificaciones por WhatsApp'),
('whatsapp_token', '', 'Token de acceso a WhatsApp API'),
('whatsapp_phone_id', '', 'ID del número de teléfono de WhatsApp'),
('whatsapp_admin_phones', '[]', 'Números de teléfono de administradores para notificaciones');

-- 7. Crear índice para feriados
CREATE INDEX idx_feriados_fecha ON feriados(fecha);

-- 8. Agregar campo de teléfono a la tabla usuarios si no existe
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS telefono VARCHAR(20) AFTER email;

-- 9. Notas de instalación
/*
Este script actualiza completamente tu sistema de instalaciones con:

✅ NUEVAS CARACTERÍSTICAS:
- Reservas con horas personalizadas (inicio y fin)
- Sistema de reservas recurrentes (semanal, mensual, anual)
- Configuración avanzada de límites y restricciones
- Preparación para integración con WhatsApp

📋 PASOS DESPUÉS DE EJECUTAR ESTE SQL:
1. Actualizar los enlaces en tu sistema:
   - Cambiar "nueva_reserva.php" por "nueva_reserva_v2.php"
   - Agregar acceso a "configuracion.php" en el menú

2. Configurar WhatsApp (opcional):
   - Obtener token de Meta for Developers
   - Configurar en whatsapp_integration.php

3. Configurar recordatorios automáticos (opcional):
   - Crear cron job para ejecutar "recordatorios.php" diariamente

⚠️ IMPORTANTE:
- Este script elimina la tabla reservas antigua
- Si tienes datos importantes, haz un backup primero
- Los rangos horarios ahora son solo referencia
- Las reservas ahora usan horas personalizadas
*/
