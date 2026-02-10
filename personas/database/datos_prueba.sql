-- =====================================================
-- DATOS DE PRUEBA - Módulo Redes y Células
-- Ejecutar DESPUÉS de redes_celulas.sql
-- Vincula al usuario con ID más bajo como Pastor Principal
-- =====================================================

-- =====================================================
-- 1. Asignar módulo 'Personas' al primer usuario Admin
-- =====================================================
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'Personas'
FROM usuarios u
WHERE u.activo = 1
ORDER BY u.id ASC;

-- =====================================================
-- 2. BARRIOS DE EJEMPLO
-- =====================================================
INSERT INTO redes_barrios (ciudad_id, nombre) VALUES
-- La Plata (ciudad_id = 1)
(1, 'Centro'), (1, 'Los Hornos'), (1, 'City Bell'), (1, 'Gonnet'),
-- Córdoba (ciudad_id = 4)
(4, 'Nueva Córdoba'), (4, 'Alberdi'), (4, 'General Paz'),
-- San Miguel de Tucumán (ciudad_id = 7)
(7, 'Centro'), (7, 'Yerba Buena'), (7, 'Barrio Norte'),
-- Santiago del Estero (ciudad_id = 9)
(9, 'Centro'), (9, 'Borges'), (9, 'Autonomía'),
-- CABA (ciudad_id = 14)
(14, 'Palermo'), (14, 'Belgrano'), (14, 'Caballito');

-- =====================================================
-- 3. PERSONAS (30 personas de prueba)
-- La primera se vincula al usuario Admin del sistema
-- =====================================================

-- Pastor Principal (vinculado al primer usuario del sistema)
INSERT INTO redes_personas (usuario_id, dni, nombre, apellido, direccion, provincia_id, ciudad_id, barrio_id, celular, email, oficio_profesion, estado, rol_iglesia, fecha_nacimiento, fecha_conversion, bautizado, fecha_bautismo, observaciones)
SELECT u.id, '20345678', 'Carlos', 'Mendoza', 'Av. Siempre Viva 742', 1, 1, 1, '1155001001', 'carlos.mendoza@iglesia.com', 'Pastor', 'Miembro', 'Pastor Principal', '1975-03-15', '1995-06-20', TRUE, '1996-01-15', 'Pastor principal de la congregación'
FROM usuarios u WHERE u.activo = 1 ORDER BY u.id ASC LIMIT 1;

-- Pastor Ayudante
INSERT INTO redes_personas (dni, nombre, apellido, direccion, provincia_id, ciudad_id, barrio_id, celular, email, oficio_profesion, estado, rol_iglesia, fecha_nacimiento, fecha_conversion, bautizado, fecha_bautismo) VALUES
('21456789', 'María', 'González', 'Calle 7 N° 1234', 1, 1, 2, '1155002002', 'maria.gonzalez@iglesia.com', 'Docente', 'Miembro', 'Pastor Ayudante', '1980-07-22', '1998-03-10', TRUE, '1999-01-20');

-- Líderes de Red (3)
INSERT INTO redes_personas (dni, nombre, apellido, direccion, provincia_id, ciudad_id, barrio_id, celular, email, oficio_profesion, estado, rol_iglesia, fecha_nacimiento, fecha_conversion, bautizado, fecha_bautismo) VALUES
('22567890', 'Roberto', 'Fernández', 'Diagonal 73 N° 456', 1, 1, 3, '1155003003', 'roberto.fernandez@iglesia.com', 'Contador', 'Miembro', 'Lider de Red', '1982-11-05', '2001-08-15', TRUE, '2002-06-10'),
('23678901', 'Laura', 'Martínez', 'Calle 50 N° 789', 1, 1, 4, '1155004004', 'laura.martinez@iglesia.com', 'Psicóloga', 'Miembro', 'Lider de Red', '1985-02-18', '2003-05-22', TRUE, '2004-03-15'),
('24789012', 'Daniel', 'López', 'Av. 1 N° 321', 1, 1, 1, '1155005005', 'daniel.lopez@iglesia.com', 'Ingeniero', 'Miembro', 'Lider de Red', '1978-09-30', '1999-12-01', TRUE, '2000-08-20');

-- Líderes de Célula (6)
INSERT INTO redes_personas (dni, nombre, apellido, direccion, provincia_id, ciudad_id, barrio_id, celular, email, oficio_profesion, estado, rol_iglesia, fecha_nacimiento, fecha_conversion, bautizado, fecha_bautismo) VALUES
('25890123', 'Ana', 'Rodríguez', 'Calle 12 N° 567', 1, 1, 2, '1155006006', 'ana.rodriguez@email.com', 'Enfermera', 'Miembro', 'Lider de Célula', '1990-04-12', '2010-09-18', TRUE, '2011-05-22'),
('26901234', 'Pedro', 'Sánchez', 'Calle 45 N° 890', 1, 1, 3, '1155007007', 'pedro.sanchez@email.com', 'Electricista', 'Miembro', 'Lider de Célula', '1988-06-25', '2008-11-30', TRUE, '2009-07-14'),
('27012345', 'Lucía', 'Ramírez', 'Av. 44 N° 1122', 1, 1, 4, '1155008008', 'lucia.ramirez@email.com', 'Abogada', 'Miembro', 'Lider de Célula', '1992-01-08', '2012-04-05', TRUE, '2013-02-10'),
('28123456', 'Marcos', 'Torres', 'Calle 8 N° 334', 1, 1, 1, '1155009009', 'marcos.torres@email.com', 'Plomero', 'Miembro', 'Lider de Célula', '1987-08-19', '2007-06-12', TRUE, '2008-04-18'),
('29234567', 'Sofía', 'Díaz', 'Diagonal 80 N° 556', 1, 1, 2, '1155010010', 'sofia.diaz@email.com', 'Diseñadora Gráfica', 'Miembro', 'Lider de Célula', '1993-12-03', '2013-08-25', TRUE, '2014-06-20'),
('30345678', 'Gabriel', 'Herrera', 'Calle 60 N° 778', 1, 1, 3, '1155011011', 'gabriel.herrera@email.com', 'Carpintero', 'Miembro', 'Lider de Célula', '1986-05-14', '2006-02-28', TRUE, '2007-01-15');

-- Servidores (4)
INSERT INTO redes_personas (dni, nombre, apellido, direccion, provincia_id, ciudad_id, barrio_id, celular, email, oficio_profesion, estado, rol_iglesia, fecha_nacimiento, fecha_conversion, bautizado, fecha_bautismo) VALUES
('31456789', 'Valentina', 'Acosta', 'Calle 3 N° 990', 1, 1, 4, '1155012012', 'valentina.acosta@email.com', 'Contadora', 'Miembro', 'Servidor', '1995-07-20', '2015-03-10', TRUE, '2016-01-22'),
('32567890', 'Nicolás', 'Moreno', 'Av. 13 N° 112', 1, 1, 1, '1155013013', 'nicolas.moreno@email.com', 'Programador', 'Miembro', 'Servidor', '1994-10-11', '2014-07-05', TRUE, '2015-05-18'),
('33678901', 'Camila', 'Romero', 'Calle 22 N° 334', 1, 1, 2, '1155014014', 'camila.romero@email.com', 'Médica', 'Miembro', 'Servidor', '1991-03-28', '2011-10-15', TRUE, '2012-08-20'),
('34789012', 'Matías', 'Suárez', 'Calle 17 N° 556', 1, 1, 3, '1155015015', 'matias.suarez@email.com', 'Mecánico', 'Miembro', 'Servidor', '1989-11-06', '2009-04-22', TRUE, '2010-02-14');

-- Miembros regulares (10)
INSERT INTO redes_personas (dni, nombre, apellido, direccion, provincia_id, ciudad_id, barrio_id, celular, email, oficio_profesion, estado, rol_iglesia, fecha_nacimiento, fecha_conversion, bautizado, fecha_bautismo) VALUES
('35890123', 'Florencia', 'Paz', 'Calle 9 N° 778', 1, 1, 4, '1155016016', 'florencia.paz@email.com', 'Maestra', 'Miembro', 'Miembro', '1996-08-15', '2016-05-20', TRUE, '2017-03-12'),
('36901234', 'Tomás', 'Aguirre', 'Av. 7 N° 990', 1, 1, 1, '1155017017', 'tomas.aguirre@email.com', 'Albañil', 'Miembro', 'Miembro', '1993-02-22', '2013-11-08', TRUE, '2014-09-15'),
('37012345', 'Julieta', 'Castro', 'Calle 14 N° 112', 1, 1, 2, '1155018018', 'julieta.castro@email.com', 'Peluquera', 'Miembro', 'Miembro', '1997-06-30', '2017-02-14', TRUE, '2018-01-20'),
('38123456', 'Agustín', 'Medina', 'Calle 28 N° 334', 1, 1, 3, '1155019019', 'agustin.medina@email.com', 'Electricista', 'Miembro', 'Miembro', '1990-12-18', '2010-07-25', TRUE, '2011-05-30'),
('39234567', 'Martina', 'Ríos', 'Diagonal 74 N° 556', 1, 1, 4, '1155020020', 'martina.rios@email.com', 'Kinesiologa', 'Miembro', 'Miembro', '1998-04-05', '2018-09-12', TRUE, '2019-07-18'),
('40345678', 'Joaquín', 'Vargas', 'Calle 35 N° 778', 1, 1, 1, '1155021021', 'joaquin.vargas@email.com', 'Gasista', 'Miembro', 'Miembro', '1992-09-27', '2012-04-30', TRUE, '2013-03-10'),
('41456789', 'Catalina', 'Molina', 'Av. 19 N° 990', 1, 1, 2, '1155022022', 'catalina.molina@email.com', 'Veterinaria', 'Miembro', 'Miembro', '1994-01-14', '2014-08-20', FALSE, NULL),
('42567890', 'Santiago', 'Pereyra', 'Calle 41 N° 112', 1, 1, 3, '1155023023', 'santiago.pereyra@email.com', 'Pintor', 'Miembro', 'Miembro', '1991-07-08', '2011-03-15', TRUE, '2012-01-22'),
('43678901', 'Isabella', 'Gutiérrez', 'Calle 55 N° 334', 1, 1, 4, '1155024024', 'isabella.gutierrez@email.com', 'Arquitecta', 'Miembro', 'Miembro', '1999-11-20', '2019-06-10', FALSE, NULL),
('44789012', 'Benjamín', 'Flores', 'Av. 25 N° 556', 1, 1, 1, '1155025025', 'benjamin.flores@email.com', 'Chef', 'Miembro', 'Miembro', '1995-05-03', '2015-12-18', TRUE, '2016-10-22');

-- Visitantes (5)
INSERT INTO redes_personas (nombre, apellido, celular, email, oficio_profesion, estado, rol_iglesia, observaciones) VALUES
('Emilia', 'Navarro', '1155026026', 'emilia.navarro@email.com', 'Estudiante', 'Visitante', 'Miembro', 'Llegó por invitación de Florencia Paz'),
('Lucas', 'Cabrera', '1155027027', 'lucas.cabrera@email.com', 'Comerciante', 'Visitante', 'Miembro', 'Interesado en la célula de jóvenes'),
('Renata', 'Ortiz', '1155028028', NULL, 'Ama de casa', 'Visitante', 'Miembro', 'Vecina del barrio, primera vez'),
('Franco', 'Giménez', '1155029029', 'franco.gimenez@email.com', 'Técnico en PC', 'Visitante', 'Miembro', 'Amigo de Nicolás Moreno'),
('Alma', 'Domínguez', '1155030030', NULL, 'Jubilada', 'Visitante', 'Miembro', 'Viene con su nieta Martina Ríos');

-- =====================================================
-- 4. CÉLULAS (3 redes principales + 6 subcélulas)
-- =====================================================

-- Redes principales (parent_id = NULL)
INSERT INTO redes_celulas (nombre, descripcion, lider_id, parent_id, direccion, provincia_id, ciudad_id, barrio_id, latitud, longitud, dia_reunion, hora_reunion, frecuencia, tipo_celula, estado, fecha_inicio) VALUES
('Red de Jóvenes', 'Red principal de jóvenes de 18 a 35 años', 3, NULL, 'Calle 7 N° 1234, La Plata', 1, 1, 2, -34.9214, -57.9544, 'Viernes', '20:00:00', 'Semanal', 'Jóvenes', 'Activa', '2020-03-01'),
('Red de Matrimonios', 'Red principal de matrimonios y parejas', 4, NULL, 'Calle 50 N° 789, La Plata', 1, 1, 4, -34.9180, -57.9510, 'Sábado', '19:30:00', 'Semanal', 'Matrimonios', 'Activa', '2019-06-15'),
('Red de Mujeres', 'Red principal de mujeres de todas las edades', 5, NULL, 'Av. 1 N° 321, La Plata', 1, 1, 1, -34.9200, -57.9530, 'Miércoles', '10:00:00', 'Semanal', 'Mujeres', 'Activa', '2021-01-10');

-- Subcélulas de Red de Jóvenes (parent_id = 1)
INSERT INTO redes_celulas (nombre, descripcion, lider_id, parent_id, direccion, provincia_id, ciudad_id, barrio_id, latitud, longitud, dia_reunion, hora_reunion, frecuencia, tipo_celula, estado, fecha_inicio) VALUES
('Célula Fuego Joven', 'Célula de jóvenes zona centro', 6, 1, 'Calle 12 N° 567, La Plata', 1, 1, 2, -34.9225, -57.9555, 'Viernes', '20:30:00', 'Semanal', 'Jóvenes', 'Activa', '2020-04-01'),
('Célula Roca Firme', 'Célula de jóvenes zona norte', 7, 1, 'Calle 45 N° 890, City Bell', 1, 1, 3, -34.8700, -58.0200, 'Viernes', '21:00:00', 'Semanal', 'Jóvenes', 'Activa', '2020-05-15');

-- Subcélulas de Red de Matrimonios (parent_id = 2)
INSERT INTO redes_celulas (nombre, descripcion, lider_id, parent_id, direccion, provincia_id, ciudad_id, barrio_id, latitud, longitud, dia_reunion, hora_reunion, frecuencia, tipo_celula, estado, fecha_inicio) VALUES
('Célula Familias Unidas', 'Matrimonios jóvenes', 8, 2, 'Av. 44 N° 1122, Gonnet', 1, 1, 4, -34.8800, -58.0100, 'Sábado', '20:00:00', 'Semanal', 'Matrimonios', 'Activa', '2019-08-01'),
('Célula Pacto de Amor', 'Matrimonios consolidados', 9, 2, 'Calle 8 N° 334, La Plata', 1, 1, 1, -34.9190, -57.9520, 'Sábado', '19:00:00', 'Quincenal', 'Matrimonios', 'Activa', '2020-01-10');

-- Subcélulas de Red de Mujeres (parent_id = 3)
INSERT INTO redes_celulas (nombre, descripcion, lider_id, parent_id, direccion, provincia_id, ciudad_id, barrio_id, latitud, longitud, dia_reunion, hora_reunion, frecuencia, tipo_celula, estado, fecha_inicio) VALUES
('Célula Guerreras de Fe', 'Mujeres jóvenes profesionales', 10, 3, 'Diagonal 80 N° 556, La Plata', 1, 1, 2, -34.9240, -57.9560, 'Miércoles', '19:00:00', 'Semanal', 'Mujeres', 'Activa', '2021-03-01'),
('Célula Mujeres de Valor', 'Mujeres mayores y madres', 11, 3, 'Calle 60 N° 778, City Bell', 1, 1, 3, -34.8750, -58.0150, 'Jueves', '10:00:00', 'Semanal', 'Mujeres', 'Activa', '2021-04-15');

-- =====================================================
-- 5. MIEMBROS DE CÉLULA
-- =====================================================

-- Célula Fuego Joven (célula 4) - Líder: Ana Rodríguez (persona 6)
INSERT INTO redes_miembros_celula (celula_id, persona_id, rol_en_celula, estado) VALUES
(4, 6, 'Líder', 'Activo'),
(4, 12, 'Colaborador', 'Activo'),   -- Valentina Acosta
(4, 16, 'Miembro', 'Activo'),       -- Florencia Paz
(4, 18, 'Miembro', 'Activo'),       -- Julieta Castro
(4, 22, 'Miembro', 'Activo'),       -- Catalina Molina
(4, 25, 'Miembro', 'Activo');       -- Benjamín Flores

-- Célula Roca Firme (célula 5) - Líder: Pedro Sánchez (persona 7)
INSERT INTO redes_miembros_celula (celula_id, persona_id, rol_en_celula, estado) VALUES
(5, 7, 'Líder', 'Activo'),
(5, 13, 'Colaborador', 'Activo'),   -- Nicolás Moreno
(5, 17, 'Miembro', 'Activo'),       -- Tomás Aguirre
(5, 19, 'Miembro', 'Activo'),       -- Agustín Medina
(5, 23, 'Miembro', 'Activo');       -- Santiago Pereyra

-- Célula Familias Unidas (célula 6) - Líder: Lucía Ramírez (persona 8)
INSERT INTO redes_miembros_celula (celula_id, persona_id, rol_en_celula, estado) VALUES
(6, 8, 'Líder', 'Activo'),
(6, 14, 'Anfitrión', 'Activo'),     -- Camila Romero
(6, 20, 'Miembro', 'Activo'),       -- Martina Ríos
(6, 24, 'Miembro', 'Activo'),       -- Isabella Gutiérrez
(6, 21, 'Miembro', 'Activo');       -- Joaquín Vargas

-- Célula Pacto de Amor (célula 7) - Líder: Marcos Torres (persona 9)
INSERT INTO redes_miembros_celula (celula_id, persona_id, rol_en_celula, estado) VALUES
(7, 9, 'Líder', 'Activo'),
(7, 15, 'Colaborador', 'Activo'),   -- Matías Suárez
(7, 1, 'Miembro', 'Activo'),        -- Carlos Mendoza (Pastor)
(7, 2, 'Miembro', 'Activo');        -- María González

-- Célula Guerreras de Fe (célula 8) - Líder: Sofía Díaz (persona 10)
INSERT INTO redes_miembros_celula (celula_id, persona_id, rol_en_celula, estado) VALUES
(8, 10, 'Líder', 'Activo'),
(8, 4, 'Miembro', 'Activo'),        -- Laura Martínez
(8, 16, 'Miembro', 'Activo'),       -- Florencia Paz (también en Fuego Joven)
(8, 22, 'Miembro', 'Activo');       -- Catalina Molina

-- Célula Mujeres de Valor (célula 9) - Líder: Gabriel Herrera (persona 11)
INSERT INTO redes_miembros_celula (celula_id, persona_id, rol_en_celula, estado) VALUES
(9, 11, 'Líder', 'Activo'),
(9, 2, 'Miembro', 'Activo'),        -- María González
(9, 14, 'Miembro', 'Activo'),       -- Camila Romero
(9, 18, 'Miembro', 'Activo');       -- Julieta Castro

-- =====================================================
-- 6. INFORMES DE ASISTENCIA (últimas 4 semanas)
-- =====================================================

-- Célula Fuego Joven - 4 reuniones
INSERT INTO redes_asistencia (celula_id, fecha_reunion, lider_id, total_miembros_asistentes, total_invitados, total_asistencia, pedidos_oracion, mensaje_compartido, observaciones, ofrenda, estado) VALUES
(4, DATE_SUB(CURDATE(), INTERVAL 21 DAY), 6, 5, 2, 7, 'Oración por la familia de Florencia', 'Filipenses 4:13 - Todo lo puedo en Cristo', 'Excelente reunión, muy participativa', 1500.00, 'Revisado'),
(4, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 6, 6, 1, 7, 'Oración por exámenes de la universidad', 'Romanos 8:28 - Todas las cosas ayudan a bien', 'Se sumó Benjamín por primera vez', 2000.00, 'Revisado'),
(4, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 6, 5, 3, 8, 'Oración por salud de la mamá de Julieta', 'Salmo 23 - El Señor es mi pastor', 'Vinieron 3 invitados nuevos, muy buen ambiente', 1800.00, 'Enviado'),
(4, CURDATE(), 6, 4, 1, 5, 'Oración por trabajo para Catalina', 'Mateo 6:33 - Buscad primeramente el reino', NULL, 1200.00, 'Borrador');

-- Célula Roca Firme - 3 reuniones
INSERT INTO redes_asistencia (celula_id, fecha_reunion, lider_id, total_miembros_asistentes, total_invitados, total_asistencia, pedidos_oracion, mensaje_compartido, ofrenda, estado) VALUES
(5, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 7, 4, 0, 4, 'Oración por el trabajo de Tomás', 'Juan 15:5 - Yo soy la vid', 1000.00, 'Revisado'),
(5, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 7, 5, 1, 6, 'Oración por la familia de Agustín', 'Proverbios 3:5-6 - Confía en el Señor', 1500.00, 'Enviado'),
(5, CURDATE(), 7, 3, 2, 5, 'Oración por sanidad de Santiago', 'Isaías 41:10 - No temas', 800.00, 'Borrador');

-- Célula Familias Unidas - 3 reuniones
INSERT INTO redes_asistencia (celula_id, fecha_reunion, lider_id, total_miembros_asistentes, total_invitados, total_asistencia, pedidos_oracion, mensaje_compartido, ofrenda, estado) VALUES
(6, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 8, 5, 1, 6, 'Oración por los hijos', 'Deuteronomio 6:6-7 - Enseña a tus hijos', 2500.00, 'Revisado'),
(6, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 8, 4, 0, 4, 'Oración por la economía familiar', 'Malaquías 3:10 - Traed los diezmos', 2000.00, 'Enviado'),
(6, CURDATE(), 8, 5, 2, 7, 'Oración por unidad matrimonial', 'Efesios 5:25 - Amad a vuestras esposas', 3000.00, 'Borrador');

-- Célula Pacto de Amor - 2 reuniones (quincenal)
INSERT INTO redes_asistencia (celula_id, fecha_reunion, lider_id, total_miembros_asistentes, total_invitados, total_asistencia, pedidos_oracion, mensaje_compartido, ofrenda, estado) VALUES
(7, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 9, 4, 0, 4, 'Oración por el ministerio pastoral', '1 Timoteo 3:1 - Si alguno anhela obispado', 3500.00, 'Revisado'),
(7, CURDATE(), 9, 3, 1, 4, 'Oración por la iglesia', 'Hechos 2:42 - Perseveraban en la doctrina', 2800.00, 'Borrador');

-- Célula Guerreras de Fe - 3 reuniones
INSERT INTO redes_asistencia (celula_id, fecha_reunion, lider_id, total_miembros_asistentes, total_invitados, total_asistencia, pedidos_oracion, mensaje_compartido, ofrenda, estado) VALUES
(8, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 10, 4, 1, 5, 'Oración por las mujeres solas', 'Rut 1:16 - Tu pueblo será mi pueblo', 1200.00, 'Revisado'),
(8, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 10, 3, 0, 3, 'Oración por sanidad emocional', 'Salmo 147:3 - Sana a los quebrantados', 900.00, 'Enviado'),
(8, CURDATE(), 10, 4, 2, 6, 'Oración por las madres', 'Proverbios 31:10 - Mujer virtuosa', 1500.00, 'Borrador');

-- Célula Mujeres de Valor - 2 reuniones
INSERT INTO redes_asistencia (celula_id, fecha_reunion, lider_id, total_miembros_asistentes, total_invitados, total_asistencia, pedidos_oracion, mensaje_compartido, ofrenda, estado) VALUES
(9, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 11, 4, 1, 5, 'Oración por las familias', 'Josué 24:15 - Yo y mi casa serviremos', 1100.00, 'Enviado'),
(9, CURDATE(), 11, 3, 0, 3, 'Oración por la comunidad', 'Gálatas 6:2 - Sobrellevad los unos las cargas', 800.00, 'Borrador');

-- =====================================================
-- 7. DETALLE DE ASISTENCIA (para las últimas reuniones)
-- =====================================================

-- Fuego Joven - última reunión (asistencia_id = 4)
INSERT INTO redes_detalle_asistencia (asistencia_id, persona_id, presente) VALUES
(4, 6, TRUE), (4, 12, TRUE), (4, 16, TRUE), (4, 18, FALSE), (4, 22, TRUE), (4, 25, FALSE);

-- Roca Firme - última reunión (asistencia_id = 7)
INSERT INTO redes_detalle_asistencia (asistencia_id, persona_id, presente) VALUES
(7, 7, TRUE), (7, 13, TRUE), (7, 17, FALSE), (7, 19, TRUE), (7, 23, FALSE);

-- Familias Unidas - última reunión (asistencia_id = 10)
INSERT INTO redes_detalle_asistencia (asistencia_id, persona_id, presente) VALUES
(10, 8, TRUE), (10, 14, TRUE), (10, 20, TRUE), (10, 24, TRUE), (10, 21, TRUE);

-- =====================================================
-- 8. INVITADOS DE EJEMPLO
-- =====================================================

INSERT INTO redes_invitados (asistencia_id, nombre, celular, edad, interes, observaciones) VALUES
(3, 'Diego Romero', '1155031031', 22, 'Primera Vez', 'Amigo de Valentina'),
(3, 'Camila Sosa', '1155032032', 20, 'Primera Vez', 'Compañera de facultad'),
(3, 'Martín Ruiz', '1155033033', 25, 'Interesado', 'Ya vino una vez antes'),
(6, 'Paola Vega', '1155034034', 28, 'Primera Vez', 'Vecina de Pedro'),
(10, 'Sergio Luna', '1155035035', 35, 'Interesado', 'Esposo de una compañera de trabajo de Lucía'),
(10, 'Andrea Luna', '1155036036', 33, 'Interesado', 'Compañera de trabajo de Lucía');

-- =====================================================
-- 9. NOVEDADES DE EJEMPLO
-- =====================================================

INSERT INTO redes_novedades (titulo, contenido, tipo, destinatarios, publicado_por, fecha_expiracion, activo)
SELECT
    'Retiro de Líderes 2026',
    'Se confirma el retiro anual de líderes para el mes de marzo. Lugar: Complejo San José, Tandil. Fecha: 14-16 de marzo. Inscripción abierta hasta el 28 de febrero. Costo: $25.000 por persona (incluye alojamiento y comidas). Consultar con su líder de red para más información.',
    'Evento', 'Líderes', u.id,
    DATE_ADD(NOW(), INTERVAL 30 DAY), TRUE
FROM usuarios u WHERE u.activo = 1 ORDER BY u.id ASC LIMIT 1;

INSERT INTO redes_novedades (titulo, contenido, tipo, destinatarios, publicado_por, activo)
SELECT
    'Campaña de Oración - Febrero',
    'Durante todo el mes de febrero estaremos en cadena de oración por las familias de nuestra iglesia. Cada célula dedicará los primeros 15 minutos de su reunión a orar específicamente por las familias. Pedimos a todos los líderes que envíen los pedidos de oración al grupo de WhatsApp.',
    'Anuncio', 'Todos', u.id, TRUE
FROM usuarios u WHERE u.activo = 1 ORDER BY u.id ASC LIMIT 1;

INSERT INTO redes_novedades (titulo, contenido, tipo, destinatarios, publicado_por, activo)
SELECT
    'Nuevo Material de Estudio',
    'Ya está disponible el nuevo material de estudio para las células: "Creciendo en Fe". Pueden descargarlo desde el grupo de WhatsApp de líderes o solicitarlo en la oficina de la iglesia. El material incluye 12 lecciones para los próximos 3 meses.',
    'Novedad', 'Líderes', u.id, TRUE
FROM usuarios u WHERE u.activo = 1 ORDER BY u.id ASC LIMIT 1;

INSERT INTO redes_novedades (titulo, contenido, tipo, destinatarios, publicado_por, activo)
SELECT
    'URGENTE: Cambio de horario culto dominical',
    'Por refacciones en el templo, el culto del próximo domingo se realizará en el salón de eventos del Club Social (Calle 48 entre 7 y 8). Horario: 10:30 hs. Por favor difundir en todas las células.',
    'Urgente', 'Todos', u.id, TRUE
FROM usuarios u WHERE u.activo = 1 ORDER BY u.id ASC LIMIT 1;

-- =====================================================
-- 10. PERMISOS ESPECIALES
-- =====================================================

-- Dar permiso "Ver Todo" al segundo usuario (si existe)
INSERT IGNORE INTO redes_permisos (usuario_id, permiso, concedido_por, activo)
SELECT u2.id, 'Ver Todo', u1.id, TRUE
FROM usuarios u1
JOIN usuarios u2 ON u2.id != u1.id AND u2.activo = 1
WHERE u1.activo = 1
ORDER BY u1.id ASC, u2.id ASC
LIMIT 1;

-- =====================================================
-- FIN DE DATOS DE PRUEBA
-- =====================================================
SELECT 'Datos de prueba insertados correctamente' AS resultado;
