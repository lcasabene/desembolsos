<?php
require_once __DIR__ . '/seguridad.php';

// Configurar headers para respuesta JSON
header('Content-Type: application/json');
headers_seguridad();

try {
    // Verificar si la sesión es válida
    $valid = sesion_activa();
    
    // Respuesta JSON
    echo json_encode([
        'valid' => $valid,
        'timestamp' => time(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null
    ]);
    
} catch (Exception $e) {
    // En caso de error, responder que la sesión no es válida
    echo json_encode([
        'valid' => false,
        'error' => $e->getMessage(),
        'timestamp' => time()
    ]);
}
?>
