<?php
// =========================
// INICIO DE SESIÓN SEGURO (vía función)
// =========================
function iniciar_sesion_segura()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// =========================
// VALIDACIÓN DE ACCESO A MÓDULOS
// =========================
function verificar_acceso_modulo($modulo)
{
    iniciar_sesion_segura();

    // 1) Usuario no logueado → redirigir a login
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    // 2) Si NO existe $_SESSION['permisos'], NO bloqueamos.
    //    Esto deja compatibilidad con tu sistema actual.
    if (!isset($_SESSION['permisos']) || !is_array($_SESSION['permisos'])) {
        // No hay esquema de permisos cargado, dejamos pasar.
        return;
    }

    // 3) Si el usuario es Admin, dejamos pasar aunque no tenga el módulo en el array
    if (es_admin()) {
        return;
    }

    // 4) Si existe esquema de permisos y el módulo NO está habilitado → sin acceso
    if (!in_array($modulo, $_SESSION['permisos'], true)) {
        header("Location: sin_acceso.php?error=modulo");
        exit;
    }
}

// =========================
// FUNCIÓN: verificar rol Admin
// =========================
function es_admin()
{
    iniciar_sesion_segura();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
}
