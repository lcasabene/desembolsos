<?php
require_once __DIR__ . '/config/database.php';
redes_verificar_acceso();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'provincias':
            $stmt = $pdo->query("SELECT id, nombre FROM redes_provincias ORDER BY nombre");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'ciudades':
            $provincia_id = (int)($_GET['provincia_id'] ?? 0);
            if (!$provincia_id) { echo json_encode([]); break; }
            $stmt = $pdo->prepare("SELECT id, nombre FROM redes_ciudades WHERE provincia_id = ? ORDER BY nombre");
            $stmt->execute([$provincia_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'barrios':
            $ciudad_id = (int)($_GET['ciudad_id'] ?? 0);
            if (!$ciudad_id) { echo json_encode([]); break; }
            $stmt = $pdo->prepare("SELECT id, nombre FROM redes_barrios WHERE ciudad_id = ? ORDER BY nombre");
            $stmt->execute([$ciudad_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'agregar_ciudad':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
            if (!verificar_token_csrf($_POST['csrf_token'] ?? '')) { throw new Exception('Token inválido'); }
            $provincia_id = (int)($_POST['provincia_id'] ?? 0);
            $nombre = sanitizar_entrada($_POST['nombre'] ?? '');
            if (!$provincia_id || !$nombre) { throw new Exception('Datos incompletos'); }
            $stmt = $pdo->prepare("INSERT INTO redes_ciudades (provincia_id, nombre) VALUES (?, ?)");
            $stmt->execute([$provincia_id, $nombre]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'nombre' => $nombre]);
            break;

        case 'agregar_barrio':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
            if (!verificar_token_csrf($_POST['csrf_token'] ?? '')) { throw new Exception('Token inválido'); }
            $ciudad_id = (int)($_POST['ciudad_id'] ?? 0);
            $nombre = sanitizar_entrada($_POST['nombre'] ?? '');
            if (!$ciudad_id || !$nombre) { throw new Exception('Datos incompletos'); }
            $stmt = $pdo->prepare("INSERT INTO redes_barrios (ciudad_id, nombre) VALUES (?, ?)");
            $stmt->execute([$ciudad_id, $nombre]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'nombre' => $nombre]);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
