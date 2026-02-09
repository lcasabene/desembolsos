<?php
/**
 * Sistema de Seguridad Centralizado
 * Implementa medidas de seguridad estándar para toda la aplicación
 */

// Configuración de seguridad
define('SESSION_TIMEOUT', 7200); // 2 horas en segundos
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

/**
 * Inicializar sesión segura
 */
function session_segura() {
    // Configurar parámetros de sesión seguros
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
    
    // Regenerar ID de sesión para prevenir fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    }
    
    // Verificar timeout de sesión
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destruir();
        header('Location: login.php?timeout=1');
        exit;
    }
    
    // Verificar IP y User Agent para prevenir hijacking
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_destruir();
        header('Location: login.php?security=1');
        exit;
    }
    
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destruir();
        header('Location: login.php?security=1');
        exit;
    }
    
    // Actualizar última actividad
    $_SESSION['last_activity'] = time();
}

/**
 * Verificar autenticación y módulo
 */
function verificar_autenticacion($modulo_requerido = null) {
    session_segura();
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    
    if ($modulo_requerido) {
        $modulos = $_SESSION['modulos'] ?? [];
        if (!in_array($modulo_requerido, $modulos)) {
            header('Location: acceso_denegado.php');
            exit;
        }
    }
    
    return true;
}

/**
 * Destruir sesión de forma segura
 */
function session_destruir() {
    $_SESSION = array();
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    
    session_destroy();
}

/**
 * Generar token CSRF
 */
function generar_token_csrf() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verificar_token_csrf($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Token expira después de 1 hora
    if (time() - $_SESSION['csrf_token_time'] > 3600) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validar intentos de login
 */
function validar_intentos_login($email) {
    // Limpiar intentos expirados
    if (isset($_SESSION['login_attempts'][$email])) {
        $_SESSION['login_attempts'][$email] = array_filter(
            $_SESSION['login_attempts'][$email],
            function($timestamp) {
                return $timestamp > (time() - LOGIN_LOCKOUT_TIME);
            }
        );
        
        if (count($_SESSION['login_attempts'][$email]) >= MAX_LOGIN_ATTEMPTS) {
            return false;
        }
    }
    
    return true;
}

/**
 * Registrar intento de login fallido
 */
function registrar_intento_fallido($email) {
    if (!isset($_SESSION['login_attempts'][$email])) {
        $_SESSION['login_attempts'][$email] = array();
    }
    
    $_SESSION['login_attempts'][$email][] = time();
}

/**
 * Limpiar intentos de login exitoso
 */
function limpiar_intentos_login($email) {
    unset($_SESSION['login_attempts'][$email]);
}

/**
 * Establecer headers de seguridad
 */
function headers_seguridad() {
    // Prevenir clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevenir MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Habilitar XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Política de contenido de seguridad
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;");
    
    // Forzar HTTPS si está disponible
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Sanitizar entrada
 */
function sanitizar_entrada($data) {
    if (is_array($data)) {
        return array_map('sanitizar_entrada', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validar sesión activa
 */
function sesion_activa() {
    session_segura();
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['last_activity']) && 
           (time() - $_SESSION['last_activity'] <= SESSION_TIMEOUT);
}
?>
