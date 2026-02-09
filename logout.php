<?php
require_once __DIR__ . '/config/seguridad.php';

// Iniciar sesión para poder destruirla
session_segura();

// Registrar logout en log de seguridad
if (isset($_SESSION['user_id'])) {
    $log_data = [
        'user_id' => $_SESSION['user_id'],
        'user_email' => $_SESSION['user_email'] ?? 'unknown',
        'action' => 'logout',
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'timestamp' => date('Y-m-d H:i:s'),
        'session_duration' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : 0
    ];
    
    // Aquí podrías agregar log a base de datos si lo deseas
    error_log('LOGOUT: ' . json_encode($log_data));
}

// Destruir sesión de forma segura
session_destruir();

// Establecer headers de seguridad
headers_seguridad();

// Redirigir con mensaje
header('Location: login.php?logout=1');
exit;
?>
