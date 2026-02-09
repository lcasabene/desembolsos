-- Base de datos para el módulo de Presupuesto y Planificación Anual
-- Sistema de Gestión de la Iglesia

-- Tabla principal de presupuestos anuales
CREATE TABLE IF NOT EXISTS presupuestos_anuales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anio INT NOT NULL,
    objetivos_estrategicos TEXT,
    estado ENUM('borrador', 'enviado_aprobacion', 'aprobado') DEFAULT 'borrador',
    total_anual DECIMAL(15,2) DEFAULT 0.00,
    creado_por INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_por INT,
    fecha_actualizacion TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    aprobado_por INT NULL,
    fecha_aprobacion TIMESTAMP NULL,
    UNIQUE KEY unique_anio (anio),
    INDEX idx_estado (estado),
    INDEX idx_creado_por (creado_por),
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de detalles de presupuesto mensual
CREATE TABLE IF NOT EXISTS presupuesto_mensual_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    presupuesto_anual_id INT NOT NULL,
    mes ENUM('enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre') NOT NULL,
    nombre_actividad VARCHAR(255) NOT NULL,
    fecha_inicio DATE,
    fecha_fin DATE,
    presupuesto_estimado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    descripcion TEXT,
    creado_por INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_presupuesto_anual (presupuesto_anual_id),
    INDEX idx_mes (mes),
    INDEX idx_actividad (nombre_actividad),
    FOREIGN KEY (presupuesto_anual_id) REFERENCES presupuestos_anuales(id) ON DELETE CASCADE,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de seguimiento de ejecución presupuestaria
CREATE TABLE IF NOT EXISTS ejecucion_presupuestaria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    presupuesto_detalle_id INT NOT NULL,
    monto_ejecutado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    fecha_ejecucion DATE NOT NULL,
    descripcion_ejecucion TEXT,
    comprobante VARCHAR(255),
    creado_por INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_presupuesto_detalle (presupuesto_detalle_id),
    INDEX idx_fecha_ejecucion (fecha_ejecucion),
    FOREIGN KEY (presupuesto_detalle_id) REFERENCES presupuesto_mensual_detalle(id) ON DELETE CASCADE,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista para resumen mensual de presupuestos
CREATE OR REPLACE VIEW vista_resumen_presupuesto_mensual AS
SELECT 
    pa.id as presupuesto_id,
    pa.anio,
    pa.estado,
    pmd.mes,
    COUNT(pmd.id) as total_actividades,
    SUM(pmd.presupuesto_estimado) as total_mes,
    SUM(COALESCE(ep.monto_ejecutado, 0)) as total_ejecutado,
    (SUM(pmd.presupuesto_estimado) - SUM(COALESCE(ep.monto_ejecutado, 0))) as saldo_disponible
FROM presupuestos_anuales pa
LEFT JOIN presupuesto_mensual_detalle pmd ON pa.id = pmd.presupuesto_anual_id
LEFT JOIN ejecucion_presupuestaria ep ON pmd.id = ep.presupuesto_detalle_id
GROUP BY pa.id, pa.anio, pa.estado, pmd.mes
ORDER BY pa.anio, FIELD(pmd.mes, 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');

-- Vista para resumen anual de presupuestos
CREATE OR REPLACE VIEW vista_resumen_presupuesto_anual AS
SELECT 
    pa.id,
    pa.anio,
    pa.objetivos_estrategicos,
    pa.estado,
    pa.total_anual,
    pa.fecha_creacion,
    pa.fecha_aprobacion,
    COUNT(DISTINCT pmd.id) as total_actividades_anual,
    SUM(COALESCE(pmd.presupuesto_estimado, 0)) as total_presupuestado,
    SUM(COALESCE(ep.monto_ejecutado, 0)) as total_ejecutado_anual,
    (SUM(COALESCE(pmd.presupuesto_estimado, 0)) - SUM(COALESCE(ep.monto_ejecutado, 0))) as saldo_anual_disponible,
    uc.nombre as creado_por_nombre,
    ua.nombre as actualizado_por_nombre,
    uap.nombre as aprobado_por_nombre
FROM presupuestos_anuales pa
LEFT JOIN presupuesto_mensual_detalle pmd ON pa.id = pmd.presupuesto_anual_id
LEFT JOIN ejecucion_presupuestaria ep ON pmd.id = ep.presupuesto_detalle_id
LEFT JOIN usuarios uc ON pa.creado_por = uc.id
LEFT JOIN usuarios ua ON pa.actualizado_por = ua.id
LEFT JOIN usuarios uap ON pa.aprobado_por = uap.id
GROUP BY pa.id, pa.anio, pa.objetivos_estrategicos, pa.estado, pa.total_anual, pa.fecha_creacion, pa.fecha_aprobacion, uc.nombre, ua.nombre, uap.nombre
ORDER BY pa.anio DESC;
