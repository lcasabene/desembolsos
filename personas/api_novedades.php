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
                throw new Exception('No tenés permisos para publicar novedades');
            }

            $titulo = sanitizar_entrada($_POST['titulo'] ?? '');
            $contenido = sanitizar_entrada($_POST['contenido'] ?? '');
            if (!$titulo || !$contenido) { throw new Exception('Título y contenido son obligatorios'); }

            $stmt = $pdo->prepare("
                INSERT INTO redes_novedades (titulo, contenido, tipo, destinatarios, publicado_por, fecha_expiracion)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $titulo, $contenido,
                sanitizar_entrada($_POST['tipo'] ?? 'Anuncio'),
                sanitizar_entrada($_POST['destinatarios'] ?? 'Todos'),
                $usuario_id,
                ($_POST['fecha_expiracion'] ?? '') ?: null
            ]);
            echo json_encode(['success' => true, 'message' => 'Novedad publicada']);
            break;

        case 'desactivar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
            if (!verificar_token_csrf($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF inválido'); }

            $usuario_id = $_SESSION['user_id'];
            $rol_sistema = $_SESSION['user_role'] ?? '';
            $rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
            if ($rol_sistema !== 'Admin' && !in_array($rol_iglesia, ['Pastor Principal', 'Pastor Ayudante'])) {
                throw new Exception('No tenés permisos');
            }

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { throw new Exception('ID inválido'); }
            $pdo->prepare("UPDATE redes_novedades SET activo = FALSE WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Novedad desactivada']);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
