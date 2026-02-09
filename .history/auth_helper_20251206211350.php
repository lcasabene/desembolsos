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
// VALIDACIÓN DE ACCESO
// =========================
function verificar_acceso_modulo($modulo)
{
    iniciar_sesion_segura();

    // Usuario no logueado → redirigir
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    // Si el sistema usa un array de permisos por usuario:
    if (!isset($_SESSION['permisos']) || !is_array($_SESSION['permisos'])) {
        header("Location: sin_acceso.php?error=permisos");
        exit;
    }

    // Si no está autorizado al módulo → sin acceso
    if (!in_array($modulo, $_SESSION['permisos'])) {
        header("Location: sin_acceso.php?error=modulo");
        exit;
    }
}

// =========================
// FUNCIÓN OPCIONAL: verificar rol Admin
// =========================
function es_admin()
{
    iniciar_sesion_segura();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
}
?>