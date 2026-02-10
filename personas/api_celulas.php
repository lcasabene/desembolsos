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

            $usuario_id = $_SESSION['user_id'];
            $rol_sistema = $_SESSION['user_role'] ?? '';
            $rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
            if ($rol_sistema !== 'Admin' && !in_array($rol_iglesia, ['Pastor Principal', 'Pastor Ayudante'])) {
                throw new Exception('No tenés permisos para gestionar células');
            }

            $nombre = sanitizar_entrada($_POST['nombre'] ?? '');
            if (!$nombre) { throw new Exception('El nombre es obligatorio'); }

            $id = (int)($_POST['id'] ?? 0);
            $campos = [
                'nombre' => $nombre,
                'descripcion' => sanitizar_entrada($_POST['descripcion'] ?? ''),
                'lider_id' => ($_POST['lider_id'] ?? '') ?: null,
                'parent_id' => ($_POST['parent_id'] ?? '') ?: null,
                'direccion' => sanitizar_entrada($_POST['direccion'] ?? ''),
                'dia_reunion' => ($_POST['dia_reunion'] ?? '') ?: null,
                'hora_reunion' => ($_POST['hora_reunion'] ?? '') ?: null,
                'frecuencia' => sanitizar_entrada($_POST['frecuencia'] ?? 'Semanal'),
                'tipo_celula' => sanitizar_entrada($_POST['tipo_celula'] ?? 'Mixta'),
                'estado' => sanitizar_entrada($_POST['estado'] ?? 'Activa'),
                'latitud' => ($_POST['latitud'] ?? '') ?: null,
                'longitud' => ($_POST['longitud'] ?? '') ?: null,
            ];

            if ($id > 0) {
                $sets = [];
                $params = [];
                foreach ($campos as $col => $val) {
                    $sets[] = "$col = ?";
                    $params[] = $val;
                }
                $params[] = $id;
                $pdo->prepare("UPDATE redes_celulas SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
                echo json_encode(['success' => true, 'message' => 'Célula actualizada', 'id' => $id]);
            } else {
                $cols = implode(', ', array_keys($campos));
                $placeholders = implode(', ', array_fill(0, count($campos), '?'));
                $pdo->prepare("INSERT INTO redes_celulas ($cols) VALUES ($placeholders)")->execute(array_values($campos));
                echo json_encode(['success' => true, 'message' => 'Célula creada', 'id' => $pdo->lastInsertId()]);
            }
            break;

        case 'obtener':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { throw new Exception('ID inválido'); }
            $stmt = $pdo->prepare("SELECT * FROM redes_celulas WHERE id = ?");
            $stmt->execute([$id]);
            $celula = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$celula) { throw new Exception('Célula no encontrada'); }
            echo json_encode(['success' => true, 'data' => $celula]);
            break;

        case 'miembros':
            $celula_id = (int)($_GET['celula_id'] ?? 0);
            if (!$celula_id) { throw new Exception('ID de célula inválido'); }
            $stmt = $pdo->prepare("
                SELECT mc.*, p.nombre, p.apellido, p.celular, p.email, p.estado as estado_persona
                FROM redes_miembros_celula mc
                JOIN redes_personas p ON mc.persona_id = p.id
                WHERE mc.celula_id = ? AND mc.estado = 'Activo'
                ORDER BY mc.rol_en_celula, p.apellido, p.nombre
            ");
            $stmt->execute([$celula_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'agregar_miembro':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
            if (!verificar_token_csrf($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF inválido'); }
            $celula_id = (int)($_POST['celula_id'] ?? 0);
            $persona_id = (int)($_POST['persona_id'] ?? 0);
            $rol = sanitizar_entrada($_POST['rol_en_celula'] ?? 'Miembro');
            if (!$celula_id || !$persona_id) { throw new Exception('Datos incompletos'); }

            $stmt = $pdo->prepare("SELECT id FROM redes_miembros_celula WHERE celula_id = ? AND persona_id = ? AND estado = 'Activo'");
            $stmt->execute([$celula_id, $persona_id]);
            if ($stmt->fetch()) { throw new Exception('La persona ya es miembro de esta célula'); }

            $pdo->prepare("INSERT INTO redes_miembros_celula (celula_id, persona_id, rol_en_celula) VALUES (?, ?, ?)")
                ->execute([$celula_id, $persona_id, $rol]);
            echo json_encode(['success' => true, 'message' => 'Miembro agregado']);
            break;

        case 'quitar_miembro':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
            if (!verificar_token_csrf($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF inválido'); }
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { throw new Exception('ID inválido'); }
            $pdo->prepare("UPDATE redes_miembros_celula SET estado = 'Inactivo' WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Miembro removido']);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
