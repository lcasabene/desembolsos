-- =====================================================
-- Módulo de Redes y Células - Iglesia
-- Se integra con el sistema existente (desembolsos_db)
-- USA la tabla 'usuarios' existente del sistema
-- Todas las tablas nuevas llevan prefijo 'redes_'
-- =====================================================

-- Limpiar tablas del módulo si existen (orden por dependencias)
DROP VIEW IF EXISTS vw_redes_organigrama;
DROP VIEW IF EXISTS vw_redes_estadisticas;
DROP VIEW IF EXISTS vw_redes_personas_celulas;
DROP TABLE IF EXISTS redes_detalle_asistencia;
DROP TABLE IF EXISTS redes_invitados;
DROP TABLE IF EXISTS redes_asistencia;
DROP TABLE IF EXISTS redes_miembros_celula;
DROP TABLE IF EXISTS redes_celulas;
DROP TABLE IF EXISTS redes_personas;
DROP TABLE IF EXISTS redes_permisos;
DROP TABLE IF EXISTS redes_novedades;
DROP TABLE IF EXISTS redes_barrios;
DROP TABLE IF EXISTS redes_ciudades;
DROP TABLE IF EXISTS redes_provincias;

-- =====================================================
-- TABLAS DE UBICACIÓN GEOGRÁFICA
-- =====================================================

CREATE TABLE IF NOT EXISTS redes_provincias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo_pais CHAR(2) DEFAULT 'AR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rp_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS redes_ciudades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provincia_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    codigo_postal VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provincia_id) REFERENCES redes_provincias(id) ON DELETE CASCADE,
    INDEX idx_rc_nombre (nombre),
    INDEX idx_rc_provincia (provincia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS redes_barrios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ciudad_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ciudad_id) REFERENCES redes_ciudades(id) ON DELETE CASCADE,
    INDEX idx_rb_nombre (nombre),
    INDEX idx_rb_ciudad (ciudad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLA DE PERSONAS (miembros de la iglesia)
-- Separada de 'usuarios' del sistema: una persona
-- puede o no tener un usuario del sistema vinculado
-- =====================================================

CREATE TABLE IF NOT EXISTS redes_personas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,                -- FK opcional a usuarios del sistema
    dni VARCHAR(20),
    cuit VARCHAR(20),
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    direccion VARCHAR(200),
    barrio_id INT,
    ciudad_id INT,
    provincia_id INT,
    celular VARCHAR(20),
    telefono VARCHAR(20),
    email VARCHAR(150),
    oficio_profesion VARCHAR(100),
    estado ENUM('Miembro','Visitante','Inactivo') DEFAULT 'Visitante',
    rol_iglesia ENUM('Pastor Principal','Pastor Ayudante','Lider de Red','Lider de Célula','Miembro','Servidor') DEFAULT 'Miembro',
    fecha_nacimiento DATE,
    fecha_conversion DATE,
    bautizado BOOLEAN DEFAULT FALSE,
    fecha_bautismo DATE,
    observaciones TEXT,
    foto_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (provincia_id) REFERENCES redes_provincias(id) ON DELETE SET NULL,
    FOREIGN KEY (ciudad_id) REFERENCES redes_ciudades(id) ON DELETE SET NULL,
    FOREIGN KEY (barrio_id) REFERENCES redes_barrios(id) ON DELETE SET NULL,
    INDEX idx_rpe_nombre (nombre, apellido),
    INDEX idx_rpe_dni (dni),
    INDEX idx_rpe_estado (estado),
    INDEX idx_rpe_rol (rol_iglesia),
    INDEX idx_rpe_oficio (oficio_profesion),
    INDEX idx_rpe_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLA DE CÉLULAS (estructura jerárquica con parent_id)
-- =====================================================

CREATE TABLE IF NOT EXISTS redes_celulas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    lider_id INT,                       -- FK a redes_personas
    parent_id INT NULL,                 -- Jerarquía: NULL = red principal
    direccion VARCHAR(200),
    barrio_id INT,
    ciudad_id INT,
    provincia_id INT,
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),
    telefono VARCHAR(20),
    email VARCHAR(150),
    dia_reunion ENUM('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'),
    hora_reunion TIME,
    frecuencia ENUM('Semanal','Quincenal','Mensual') DEFAULT 'Semanal',
    tipo_celula ENUM('Juvenil','Jóvenes','Matrimonios','Hombres','Mujeres','Niños','Mixta') DEFAULT 'Mixta',
    estado ENUM('Activa','Inactiva','En Formación') DEFAULT 'Activa',
    fecha_inicio DATE,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lider_id) REFERENCES redes_personas(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES redes_celulas(id) ON DELETE SET NULL,
    FOREIGN KEY (provincia_id) REFERENCES redes_provincias(id) ON DELETE SET NULL,
    FOREIGN KEY (ciudad_id) REFERENCES redes_ciudades(id) ON DELETE SET NULL,
    FOREIGN KEY (barrio_id) REFERENCES redes_barrios(id) ON DELETE SET NULL,
    INDEX idx_rcel_nombre (nombre),
    INDEX idx_rcel_lider (lider_id),
    INDEX idx_rcel_parent (parent_id),
    INDEX idx_rcel_estado (estado),
    INDEX idx_rcel_tipo (tipo_celula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MIEMBROS DE CÉLULA
-- =====================================================

CREATE TABLE IF NOT EXISTS redes_miembros_celula (
    id INT AUTO_INCREMENT PRIMARY KEY,
    celula_id INT NOT NULL,
    persona_id INT NOT NULL,
    rol_en_celula ENUM('Líder','Anfitrión','Miembro','Colaborador') DEFAULT 'Miembro',
    fecha_asignacion DATE DEFAULT (CURRENT_DATE),
    estado ENUM('Activo','Inactivo','Transferido') DEFAULT 'Activo',
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (celula_id) REFERENCES redes_celulas(id) ON DELETE CASCADE,
    FOREIGN KEY (persona_id) REFERENCES redes_personas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_rmc (celula_id, persona_id, estado),
    INDEX idx_rmc_celula (celula_id),
    INDEX idx_rmc_persona (persona_id),
    INDEX idx_rmc_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- INFORMES / ASISTENCIA DE CÉLULA
-- =====================================================

CREATE TABLE IF NOT EXISTS redes_asistencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    celula_id INT NOT NULL,
    fecha_reunion DATE NOT NULL,
    lider_id INT NOT NULL,              -- FK a redes_personas
    total_miembros_asistentes INT DEFAULT 0,
    total_invitados INT DEFAULT 0,
    total_asistencia INT DEFAULT 0,
    pedidos_oracion TEXT,
    mensaje_compartido TEXT,
    observaciones TEXT,
    ofrenda DECIMAL(10,2) DEFAULT 0,
    estado ENUM('Borrador','Enviado','Revisado') DEFAULT 'Borrador',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (celula_id) REFERENCES redes_celulas(id) ON DELETE CASCADE,
    FOREIGN KEY (lider_id) REFERENCES redes_personas(id) ON DELETE CASCADE,
    INDEX idx_ra_celula (celula_id),
    INDEX idx_ra_fecha (fecha_reunion),
    INDEX idx_ra_lider (lider_id),
    INDEX idx_ra_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Detalle: checkbox de asistencia por miembro
CREATE TABLE IF NOT EXISTS redes_detalle_asistencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asistencia_id INT NOT NULL,
    persona_id INT NOT NULL,
    presente BOOLEAN DEFAULT FALSE,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asistencia_id) REFERENCES redes_asistencia(id) ON DELETE CASCADE,
    FOREIGN KEY (persona_id) REFERENCES redes_personas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_rda (asistencia_id, persona_id),
    INDEX idx_rda_asistencia (asistencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invitados: registro rápido (nombre + celular) o solo cantidad
CREATE TABLE IF NOT EXISTS redes_invitados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asistencia_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    celular VARCHAR(20),
    email VARCHAR(150),
    edad INT,
    direccion VARCHAR(200),
    interes ENUM('Primera Vez','Interesado','Ocasional') DEFAULT 'Primera Vez',
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asistencia_id) REFERENCES redes_asistencia(id) ON DELETE CASCADE,
    INDEX idx_ri_asistencia (asistencia_id),
    INDEX idx_ri_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- PERMISOS ESPECIALES DEL MÓDULO
-- Referencia a usuarios del sistema existente
-- =====================================================

CREATE TABLE IF NOT EXISTS redes_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,            -- FK a usuarios del sistema
    permiso ENUM('Ver Todo','Editar Red','Exportar Datos','Administrar Personas') NOT NULL,
    concedido_por INT NOT NULL,         -- FK a usuarios del sistema
    fecha_concesion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP NULL,
    activo BOOLEAN DEFAULT TRUE,
    observaciones TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (concedido_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_rperm (usuario_id, permiso),
    INDEX idx_rperm_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- NOVEDADES Y ACTIVIDADES
-- =====================================================

CREATE TABLE IF NOT EXISTS redes_novedades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    contenido TEXT NOT NULL,
    tipo ENUM('Anuncio','Evento','Novedad','Urgente') DEFAULT 'Anuncio',
    destinatarios ENUM('Todos','Líderes','Miembros','Pastores') DEFAULT 'Todos',
    fecha_publicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP NULL,
    publicado_por INT NOT NULL,         -- FK a usuarios del sistema
    activo BOOLEAN DEFAULT TRUE,
    imagen_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (publicado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_rn_tipo (tipo),
    INDEX idx_rn_dest (destinatarios),
    INDEX idx_rn_fecha (fecha_publicacion),
    INDEX idx_rn_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- DATOS INICIALES
-- =====================================================

-- Provincias de Argentina
INSERT INTO redes_provincias (nombre) VALUES
('Buenos Aires'),('Catamarca'),('Chaco'),('Chubut'),('Córdoba'),
('Corrientes'),('Entre Ríos'),('Formosa'),('Jujuy'),('La Pampa'),
('La Rioja'),('Mendoza'),('Misiones'),('Neuquén'),('Río Negro'),
('Salta'),('San Juan'),('San Luis'),('Santa Cruz'),('Santa Fe'),
('Santiago del Estero'),('Tierra del Fuego'),('Tucumán'),('CABA');

-- Ciudades principales de ejemplo
INSERT INTO redes_ciudades (provincia_id, nombre) VALUES
(1,'La Plata'),(1,'Mar del Plata'),(1,'Bahía Blanca'),
(5,'Córdoba'),(5,'Villa María'),(5,'Río Cuarto'),
(23,'San Miguel de Tucumán'),(23,'Tafí Viejo'),
(21,'Santiago del Estero'),(21,'La Banda'),
(20,'Santa Fe'),(20,'Rosario'),(20,'Venado Tuerto'),
(24,'CABA');

-- Registrar el módulo 'Personas' en la tabla de módulos del sistema
INSERT IGNORE INTO modulos (nombre) VALUES ('Personas');

-- =====================================================
-- VISTAS ÚTILES
-- =====================================================

-- Organigrama jerárquico de células
CREATE VIEW vw_redes_organigrama AS
SELECT
    c.id,
    c.nombre,
    c.descripcion,
    c.parent_id,
    c.tipo_celula,
    c.estado,
    c.dia_reunion,
    c.hora_reunion,
    c.direccion,
    c.latitud,
    c.longitud,
    pl.nombre AS lider_nombre,
    pl.apellido AS lider_apellido,
    pl.celular AS lider_celular,
    pl.rol_iglesia AS lider_rol,
    par.nombre AS red_padre_nombre,
    (SELECT COUNT(*) FROM redes_celulas c2 WHERE c2.parent_id = c.id AND c2.estado = 'Activa') AS subcelulas_count,
    (SELECT COUNT(*) FROM redes_miembros_celula mc WHERE mc.celula_id = c.id AND mc.estado = 'Activo') AS miembros_count
FROM redes_celulas c
LEFT JOIN redes_personas pl ON c.lider_id = pl.id
LEFT JOIN redes_celulas par ON c.parent_id = par.id
WHERE c.estado = 'Activa'
ORDER BY c.parent_id, c.nombre;

-- Estadísticas por célula
CREATE VIEW vw_redes_estadisticas AS
SELECT
    c.id AS celula_id,
    c.nombre AS celula_nombre,
    c.tipo_celula,
    COUNT(DISTINCT mc.id) AS total_miembros,
    COUNT(DISTINCT CASE WHEN mc.estado='Activo' THEN mc.id END) AS miembros_activos,
    COUNT(DISTINCT a.id) AS total_reuniones,
    COUNT(DISTINCT CASE WHEN a.fecha_reunion >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN a.id END) AS reuniones_ultimo_mes,
    COALESCE(AVG(a.total_asistencia),0) AS promedio_asistencia,
    MAX(a.fecha_reunion) AS ultima_reunion
FROM redes_celulas c
LEFT JOIN redes_miembros_celula mc ON c.id = mc.celula_id
LEFT JOIN redes_asistencia a ON c.id = a.celula_id
WHERE c.estado = 'Activa'
GROUP BY c.id, c.nombre, c.tipo_celula;

-- Personas con su célula asignada
CREATE VIEW vw_redes_personas_celulas AS
SELECT
    p.id AS persona_id,
    p.usuario_id,
    p.nombre,
    p.apellido,
    p.dni,
    p.celular,
    p.email,
    p.oficio_profesion,
    p.estado AS estado_persona,
    p.rol_iglesia,
    c.nombre AS celula_nombre,
    c.id AS celula_id,
    mc.rol_en_celula,
    mc.estado AS estado_miembro
FROM redes_personas p
LEFT JOIN redes_miembros_celula mc ON p.id = mc.persona_id AND mc.estado = 'Activo'
LEFT JOIN redes_celulas c ON mc.celula_id = c.id
ORDER BY p.apellido, p.nombre;
