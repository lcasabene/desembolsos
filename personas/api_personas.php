<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'guardar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
            if (!verificar_token_csrf($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF inválido'); }

            $nombre = sanitizar_entrada($_POST['nombre'] ?? '');
            $apellido = sanitizar_entrada($_POST['apellido'] ?? '');
            if (!$nombre || !$apellido) { throw new Exception('Nombre y apellido son obligatorios'); }

            $id = (int)($_POST['id'] ?? 0);
            $campos = [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'dni' => sanitizar_entrada($_POST['dni'] ?? ''),
                'cuit' => sanitizar_entrada($_POST['cuit'] ?? ''),
                'celular' => sanitizar_entrada($_POST['celular'] ?? ''),
                'telefono' => sanitizar_entrada($_POST['telefono'] ?? ''),
                'email' => sanitizar_entrada($_POST['email'] ?? ''),
                'direccion' => sanitizar_entrada($_POST['direccion'] ?? ''),
                'provincia_id' => ($_POST['provincia_id'] ?? '') ?: null,
                'ciudad_id' => ($_POST['ciudad_id'] ?? '') ?: null,
                'barrio_id' => ($_POST['barrio_id'] ?? '') ?: null,
                'oficio_profesion' => sanitizar_entrada($_POST['oficio_profesion'] ?? ''),
                'estado' => sanitizar_entrada($_POST['estado'] ?? 'Visitante'),
                'rol_iglesia' => sanitizar_entrada($_POST['rol_iglesia'] ?? 'Miembro'),
                'fecha_nacimiento' => ($_POST['fecha_nacimiento'] ?? '') ?: null,
                'fecha_conversion' => ($_POST['fecha_conversion'] ?? '') ?: null,
                'bautizado' => isset($_POST['bautizado']) ? 1 : 0,
                'fecha_bautismo' => ($_POST['fecha_bautismo'] ?? '') ?: null,
                'observaciones' => sanitizar_entrada($_POST['observaciones'] ?? ''),
            ];

            if ($id > 0) {
                // Actualizar
                $sets = [];
                $params = [];
                foreach ($campos as $col => $val) {
                    $sets[] = "$col = ?";
                    $params[] = $val;
                }
                $params[] = $id;
                $sql = "UPDATE redes_personas SET " . implode(', ', $sets) . " WHERE id = ?";
                $pdo->prepare($sql)->execute($params);
                echo json_encode(['success' => true, 'message' => 'Persona actualizada', 'id' => $id]);
            } else {
                // Insertar
                $cols = implode(', ', array_keys($campos));
                $placeholders = implode(', ', array_fill(0, count($campos), '?'));
                $sql = "INSERT INTO redes_personas ($cols) VALUES ($placeholders)";
                $pdo->prepare($sql)->execute(array_values($campos));
                $nuevo_id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Persona creada', 'id' => $nuevo_id]);
            }
            break;

        case 'eliminar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
            if (!verificar_token_csrf($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF inválido'); }

            $usuario_id = $_SESSION['user_id'];
            $rol_sistema = $_SESSION['user_role'] ?? '';
            $rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
            if ($rol_sistema !== 'Admin' && $rol_iglesia !== 'Pastor Principal') {
                throw new Exception('No tenés permisos para eliminar personas');
            }

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { throw new Exception('ID inválido'); }

            // Cambiar estado a Inactivo en vez de borrar
            $pdo->prepare("UPDATE redes_personas SET estado = 'Inactivo' WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Persona marcada como inactiva']);
            break;

        case 'obtener':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { throw new Exception('ID inválido'); }

            $stmt = $pdo->prepare("
                SELECT p.*,
                    prov.nombre as provincia_nombre,
                    ciu.nombre as ciudad_nombre,
                    bar.nombre as barrio_nombre,
                    c.nombre as celula_nombre,
                    mc.rol_en_celula
                FROM redes_personas p
                LEFT JOIN redes_provincias prov ON p.provincia_id = prov.id
                LEFT JOIN redes_ciudades ciu ON p.ciudad_id = ciu.id
                LEFT JOIN redes_barrios bar ON p.barrio_id = bar.id
                LEFT JOIN redes_miembros_celula mc ON p.id = mc.persona_id AND mc.estado = 'Activo'
                LEFT JOIN redes_celulas c ON mc.celula_id = c.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $persona = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$persona) { throw new Exception('Persona no encontrada'); }
            echo json_encode(['success' => true, 'data' => $persona]);
            break;

        case 'buscar_oficio':
            $oficio = sanitizar_entrada($_GET['q'] ?? '');
            if (strlen($oficio) < 2) { echo json_encode([]); break; }
            $stmt = $pdo->prepare("
                SELECT id, nombre, apellido, celular, email, oficio_profesion
                FROM redes_personas
                WHERE oficio_profesion LIKE ? AND estado != 'Inactivo'
                ORDER BY apellido, nombre
                LIMIT 50
            ");
            $stmt->execute(["%$oficio%"]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
