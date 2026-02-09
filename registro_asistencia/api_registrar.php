<?php
// Habilitar visualización de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/database.php';

// Verificar autenticación y rol
verificar_autenticacion('Colaboradores');

// Solo aceptar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verificar_token_csrf($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$usuario_id = $_SESSION['user_id'];
$accion = $_POST['accion'] ?? '';
$hoy = date('Y-m-d');
$hora_actual = date('H:i:s');

// Verificar que la acción sea válida
if (!in_array($accion, ['entrada', 'salida'])) {
    echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $accion]);
    exit;
}

try {
    // Obtener el último registro del usuario para hoy
    $stmt = $pdo->prepare("
        SELECT * FROM asistencia 
        WHERE usuario_id = ? AND fecha = ? 
        ORDER BY secuencia DESC, created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id, $hoy]);
    $ultimo_registro = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener la siguiente secuencia
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(secuencia), 0) + 1 as siguiente_secuencia
        FROM asistencia 
        WHERE usuario_id = ? AND fecha = ?
    ");
    $stmt->execute([$usuario_id, $hoy]);
    $siguiente_secuencia = $stmt->fetch(PDO::FETCH_ASSOC)['siguiente_secuencia'];

    if ($accion === 'entrada') {
        // Verificar si el último registro está esperando salida
        if ($ultimo_registro && $ultimo_registro['hora_entrada'] && !$ultimo_registro['hora_salida']) {
            echo json_encode(['success' => false, 'message' => 'Ya tienes una entrada registrada sin salida. Por favor registra tu salida primero.']);
            exit;
        }

        // Crear nuevo registro de entrada
        $stmt = $pdo->prepare("
            INSERT INTO asistencia (usuario_id, fecha, secuencia, hora_entrada, ubicacion_entrada) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([$usuario_id, $hoy, $siguiente_secuencia, $hora_actual, 'IP: ' . $_SERVER['REMOTE_ADDR']]);
        
        if (!$result) {
            throw new Exception('Error al crear nuevo registro de entrada');
        }
        
        $asistencia_id = $pdo->lastInsertId();

        // Registrar en auditoría
        try {
            $stmt = $pdo->prepare("
                INSERT INTO asistencia_auditoria (asistencia_id, usuario_id, accion, valor_nuevo, ip_address, user_agent) 
                VALUES (?, ?, 'crear', ?, ?, ?)
            ");
            $stmt->execute([
                $asistencia_id, 
                $usuario_id, 
                json_encode([
                    'secuencia' => $siguiente_secuencia,
                    'hora_entrada' => $hora_actual, 
                    'ubicacion' => 'IP: ' . $_SERVER['REMOTE_ADDR']
                ]),
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch (Exception $auditError) {
            error_log("Error en auditoría: " . $auditError->getMessage());
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Entrada #' . $siguiente_secuencia . ' registrada correctamente',
            'secuencia' => $siguiente_secuencia
        ]);

    } elseif ($accion === 'salida') {
        // Verificar que haya una entrada pendiente
        if (!$ultimo_registro || !$ultimo_registro['hora_entrada'] || $ultimo_registro['hora_salida']) {
            echo json_encode(['success' => false, 'message' => 'No tienes ninguna entrada pendiente de registrar salida.']);
            exit;
        }

        // Actualizar el último registro con la salida
        $stmt = $pdo->prepare("
            UPDATE asistencia 
            SET hora_salida = ?, ubicacion_salida = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $result = $stmt->execute([$hora_actual, 'IP: ' . $_SERVER['REMOTE_ADDR'], $ultimo_registro['id']]);
        
        if (!$result) {
            throw new Exception('Error al actualizar registro de salida');
        }

        // Calcular horas trabajadas
        $minutos_trabajados = (strtotime($hora_actual) - strtotime($ultimo_registro['hora_entrada'])) / 60;
        $horas_formateadas = number_format($minutos_trabajados / 60, 2);

        // Registrar en auditoría
        try {
            $stmt = $pdo->prepare("
                INSERT INTO asistencia_auditoria (asistencia_id, usuario_id, accion, valor_nuevo, ip_address, user_agent) 
                VALUES (?, ?, 'crear', ?, ?, ?)
            ");
            $stmt->execute([
                $ultimo_registro['id'], 
                $usuario_id, 
                json_encode([
                    'hora_salida' => $hora_actual, 
                    'ubicacion' => 'IP: ' . $_SERVER['REMOTE_ADDR'],
                    'horas_trabajadas' => $horas_formateadas
                ]),
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch (Exception $auditError) {
            error_log("Error en auditoría: " . $auditError->getMessage());
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Salida registrada correctamente. Trabajaste ' . $horas_formateadas . ' horas',
            'secuencia' => $ultimo_registro['secuencia'],
            'horas_trabajadas' => $horas_formateadas
        ]);
    }

} catch (PDOException $e) {
    error_log("Error PDO en API de asistencia: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error general en API de asistencia: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
