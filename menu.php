<?php
require_once __DIR__ . '/config/seguridad.php';
verificar_autenticacion();

// Redirigir al menú moderno
header('Location: menu_moderno.php');
exit;
?>
