<?php
/**
 * Script para activar el sistema de seguridad mejorado
 */

echo "🔒 Activando Sistema de Seguridad Mejorado...\n\n";

// Archivos a renombrar
$archivos = [
    'login.php' => 'login_original_backup.php',
    'logout.php' => 'logout_original_backup.php', 
    'acceso_denegado.php' => 'acceso_denegado_original_backup.php'
];

// Archivos mejorados a activar
$mejorados = [
    'login_mejorado.php' => 'login.php',
    'logout_mejorado.php' => 'logout.php',
    'acceso_denegado_mejorado.php' => 'acceso_denegado.php'
];

// Paso 1: Hacer backup de archivos originales
echo "📦 Paso 1: Creando backups de archivos originales...\n";
foreach ($archivos as $actual => $backup) {
    if (file_exists($actual)) {
        if (rename($actual, $backup)) {
            echo "✅ $actual → $backup\n";
        } else {
            echo "❌ Error renombrando $actual\n";
        }
    } else {
        echo "⚠️  $actual no existe\n";
    }
}

echo "\n🚀 Paso 2: Activando archivos mejorados...\n";
foreach ($mejorados as $mejorado => $final) {
    if (file_exists($mejorado)) {
        if (rename($mejorado, $final)) {
            echo "✅ $mejorado → $final\n";
        } else {
            echo "❌ Error activando $mejorado\n";
        }
    } else {
        echo "❌ $mejorado no encontrado\n";
    }
}

echo "\n🔧 Paso 3: Verificando instalación...\n";

// Verificar archivos clave
$archivos_clave = [
    'config/seguridad.php',
    'config/verificar_sesion.php',
    'login.php',
    'logout.php',
    'acceso_denegado.php'
];

foreach ($archivos_clave as $archivo) {
    if (file_exists($archivo)) {
        echo "✅ $archivo - OK\n";
    } else {
        echo "❌ $archivo - FALTANTE\n";
    }
}

echo "\n🎉 Sistema de seguridad activado!\n";
echo "📝 Prueba el nuevo sistema accediendo a login.php\n";
echo "🔍 Características activadas:\n";
echo "   - ✅ Protección CSRF\n";
echo "   - ✅ Límite de intentos de login\n";
echo "   - ✅ Timeout de sesión (2 horas)\n";
echo "   - ✅ Verificación de IP y User Agent\n";
echo "   - ✅ Headers de seguridad\n";
echo "   - ✅ Registro de auditoría\n";
?>
