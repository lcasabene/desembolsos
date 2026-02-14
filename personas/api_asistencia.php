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
            $persona = redes_obtener_persona_usuario($pdo, $usuario_id);
            if (!$persona) { throw new Exception('No tenés un perfil de persona vinculado'); }

            $celula_id = (int)($_POST['celula_id'] ?? 0);
            $fecha = $_POST['fecha_reunion'] ?? '';
            if (!$celula_id || !$fecha) { throw new Exception('Célula y fecha son obligatorios'); }

            $miembros_presentes = $_POST['miembros_presentes'] ?? [];
            $total_invitados = (int)($_POST['total_invitados'] ?? 0);
            $total_miembros = count($miembros_presentes);
            $total_asistencia = $total_miembros + $total_invitados;
            $estado = sanitizar_entrada($_POST['estado'] ?? 'Borrador');

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO redes_asistencia
                    (celula_id, fecha_reunion, lider_id, total_miembros_asistentes, total_invitados, total_asistencia,
                     pedidos_oracion, mensaje_compartido, observaciones, ofrenda, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $celula_id, $fecha, $persona['id'],
                $total_miembros, $total_invitados, $total_asistencia,
                sanitizar_entrada($_POST['pedidos_oracion'] ?? ''),
                sanitizar_entrada($_POST['mensaje_compartido'] ?? ''),
                sanitizar_entrada($_POST['observaciones'] ?? ''),
                (float)($_POST['ofrenda'] ?? 0),
                $estado
            ]);
            $asistencia_id = $pdo->lastInsertId();

            // Obtener todos los miembros activos de la célula
            $stmt = $pdo->prepare("SELECT persona_id FROM redes_miembros_celula WHERE celula_id = ? AND estado = 'Activo'");
            $stmt->execute([$celula_id]);
            $todos_miembros = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Insertar detalle de asistencia
            $stmt = $pdo->prepare("INSERT INTO redes_detalle_asistencia (asistencia_id, persona_id, presente) VALUES (?, ?, ?)");
            foreach ($todos_miembros as $pid) {
                $presente = in_array($pid, $miembros_presentes) ? 1 : 0;
                $stmt->execute([$asistencia_id, $pid, $presente]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Informe guardado', 'id' => $asistencia_id]);
            break;

        case 'obtener':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { throw new Exception('ID inválido'); }

            $stmt = $pdo->prepare("
                SELECT a.*, c.nombre as celula_nombre, c.tipo_celula,
                    p.nombre as lider_nombre, p.apellido as lider_apellido,
                    u.nombre as revisor_nombre
                FROM redes_asistencia a
                JOIN redes_celulas c ON a.celula_id = c.id
                JOIN redes_personas p ON a.lider_id = p.id
                LEFT JOIN usuarios u ON a.revisado_por = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            $informe = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$informe) { throw new Exception('Informe no encontrado'); }

            // Detalle de asistencia
            $stmt = $pdo->prepare("
                SELECT da.*, p.nombre, p.apellido
                FROM redes_detalle_asistencia da
                JOIN redes_personas p ON da.persona_id = p.id
                WHERE da.asistencia_id = ?
                ORDER BY p.apellido, p.nombre
            ");
            $stmt->execute([$id]);
            $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $informe, 'detalle' => $detalle]);
            break;

        case 'enviar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
            if (!verificar_token_csrf($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF inválido'); }
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { throw new Exception('ID inválido'); }
            $pdo->prepare("UPDATE redes_asistencia SET estado = 'Enviado' WHERE id = ? AND estado = 'Borrador'")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Informe enviado']);
            break;

        case 'revisar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
            if (!verificar_token_csrf($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF inválido'); }

            $rol_sistema = $_SESSION['user_role'] ?? '';
            $rol_iglesia = redes_obtener_rol_iglesia($pdo, $_SESSION['user_id']);
            if ($rol_sistema !== 'Admin' && !in_array($rol_iglesia, ['Pastor Principal', 'Pastor Ayudante', 'Lider de Red'])) {
                throw new Exception('No tenés permisos para revisar informes');
            }

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { throw new Exception('ID inválido'); }
            $pdo->prepare("UPDATE redes_asistencia SET estado = 'Revisado', revisado_por = ?, fecha_revision = NOW() WHERE id = ? AND estado = 'Enviado'")->execute([$_SESSION['user_id'], $id]);
            echo json_encode(['success' => true, 'message' => 'Informe aprobado y marcado como revisado']);
            break;

        case 'rechazar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
            if (!verificar_token_csrf($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF inválido'); }

            $rol_sistema = $_SESSION['user_role'] ?? '';
            $rol_iglesia = redes_obtener_rol_iglesia($pdo, $_SESSION['user_id']);
            if ($rol_sistema !== 'Admin' && !in_array($rol_iglesia, ['Pastor Principal', 'Pastor Ayudante', 'Lider de Red'])) {
                throw new Exception('No tenés permisos para rechazar informes');
            }

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { throw new Exception('ID inválido'); }
            $pdo->prepare("UPDATE redes_asistencia SET estado = 'Borrador' WHERE id = ? AND estado = 'Enviado'")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Informe rechazado y devuelto a borrador']);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
