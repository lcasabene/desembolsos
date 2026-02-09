-- Actualización de la base de datos para incluir departamentos en el módulo de presupuesto
-- Ejecutar este script para actualizar la estructura existente

-- Agregar campo de departamento a la tabla de detalles de presupuesto mensual
ALTER TABLE presupuesto_mensual_detalle 
ADD COLUMN departamento_id INT NULL AFTER descripcion,
ADD INDEX idx_departamento (departamento_id);

-- Agregar relación con la tabla de departamentos
ALTER TABLE presupuesto_mensual_detalle 
ADD CONSTRAINT fk_presupuesto_detalle_departamento 
FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE SET NULL;

-- Actualizar vistas para incluir información de departamentos
DROP VIEW IF EXISTS vista_resumen_presupuesto_mensual;

CREATE OR REPLACE VIEW vista_resumen_presupuesto_mensual AS
SELECT 
    pa.id as presupuesto_id,
    pa.anio,
    pa.estado,
    pmd.mes,
    pmd.departamento_id,
    COALESCE(d.nombre, 'Sin Departamento') as departamento_nombre,
    COUNT(pmd.id) as total_actividades,
    SUM(pmd.presupuesto_estimado) as total_mes,
    SUM(COALESCE(ep.monto_ejecutado, 0)) as total_ejecutado,
    (SUM(pmd.presupuesto_estimado) - SUM(COALESCE(ep.monto_ejecutado, 0))) as saldo_disponible
FROM presupuestos_anuales pa
LEFT JOIN presupuesto_mensual_detalle pmd ON pa.id = pmd.presupuesto_anual_id
LEFT JOIN ejecucion_presupuestaria ep ON pmd.id = ep.presupuesto_detalle_id
LEFT JOIN departamentos d ON pmd.departamento_id = d.id
GROUP BY pa.id, pa.anio, pa.estado, pmd.mes, pmd.departamento_id, d.nombre
ORDER BY pa.anio, FIELD(pmd.mes, 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');

DROP VIEW IF EXISTS vista_resumen_presupuesto_anual;

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
    COUNT(DISTINCT pmd.departamento_id) as total_departamentos,
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

-- Nueva vista para resumen por departamento
CREATE OR REPLACE VIEW vista_resumen_presupuesto_departamento AS
SELECT 
    pa.id as presupuesto_id,
    pa.anio,
    pa.estado,
    d.id as departamento_id,
    d.nombre as departamento_nombre,
    COUNT(pmd.id) as total_actividades,
    SUM(pmd.presupuesto_estimado) as total_presupuesto_departamento,
    SUM(COALESCE(ep.monto_ejecutado, 0)) as total_ejecutado_departamento,
    (SUM(pmd.presupuesto_estimado) - SUM(COALESCE(ep.monto_ejecutado, 0))) as saldo_disponible_departamento,
    CASE 
        WHEN SUM(pmd.presupuesto_estimado) > 0 
        THEN (SUM(COALESCE(ep.monto_ejecutado, 0)) / SUM(pmd.presupuesto_estimado)) * 100 
        ELSE 0 
    END as porcentaje_ejecucion
FROM presupuestos_anuales pa
LEFT JOIN presupuesto_mensual_detalle pmd ON pa.id = pmd.presupuesto_anual_id
LEFT JOIN ejecucion_presupuestaria ep ON pmd.id = ep.presupuesto_detalle_id
LEFT JOIN departamentos d ON pmd.departamento_id = d.id
GROUP BY pa.id, pa.anio, pa.estado, d.id, d.nombre
ORDER BY pa.anio, d.nombre;
