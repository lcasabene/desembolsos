<?php
/**
 * Helper del Módulo Redes y Células
 * Usa la conexión $pdo y seguridad del sistema principal
 */

// Incluir config y seguridad del sistema principal
require_once __DIR__ . '/../../config/seguridad.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Obtener la persona vinculada al usuario logueado del sistema
 */
function redes_obtener_persona_usuario($pdo, $usuario_id) {
    $stmt = $pdo->prepare("SELECT * FROM redes_personas WHERE usuario_id = ? LIMIT 1");
    $stmt->execute([$usuario_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtener el rol de iglesia de la persona vinculada al usuario
 */
function redes_obtener_rol_iglesia($pdo, $usuario_id) {
    $persona = redes_obtener_persona_usuario($pdo, $usuario_id);
    return $persona['rol_iglesia'] ?? 'Miembro';
}

/**
 * Verificar si el usuario tiene un permiso especial en el módulo
 */
function redes_tiene_permiso($pdo, $usuario_id, $permiso) {
    // Admin del sistema siempre tiene todos los permisos
    $rol_sistema = $_SESSION['user_role'] ?? '';
    if ($rol_sistema === 'Admin') {
        return true;
    }

    // Verificar rol de iglesia
    $rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
    if (in_array($rol_iglesia, ['Pastor Principal'])) {
        return true;
    }

    // Verificar permisos especiales en la tabla redes_permisos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as tiene
        FROM redes_permisos
        WHERE usuario_id = ? AND permiso = ? AND activo = TRUE
          AND (fecha_expiracion IS NULL OR fecha_expiracion > NOW())
    ");
    $stmt->execute([$usuario_id, $permiso]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['tiene'] > 0;
}

/**
 * Obtener IDs de células visibles según la jerarquía del usuario
 */
function redes_celulas_visibles($pdo, $usuario_id) {
    $rol_sistema = $_SESSION['user_role'] ?? '';
    $rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);

    // Admin del sistema o Pastor Principal ven todo
    if ($rol_sistema === 'Admin' || $rol_iglesia === 'Pastor Principal' || redes_tiene_permiso($pdo, $usuario_id, 'Ver Todo')) {
        $stmt = $pdo->query("SELECT id FROM redes_celulas WHERE estado = 'Activa'");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $persona = redes_obtener_persona_usuario($pdo, $usuario_id);
    if (!$persona) return [];

    $pid = $persona['id'];

    switch ($rol_iglesia) {
        case 'Pastor Ayudante':
            $stmt = $pdo->query("SELECT id FROM redes_celulas WHERE estado = 'Activa'");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);

        case 'Lider de Red':
            // Su red + todas las células hijas recursivas
            $stmt = $pdo->prepare("
                SELECT id FROM redes_celulas
                WHERE (lider_id = ? OR parent_id IN (SELECT id FROM redes_celulas WHERE lider_id = ?))
                  AND estado = 'Activa'
            ");
            $stmt->execute([$pid, $pid]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);

        case 'Lider de Célula':
            $stmt = $pdo->prepare("SELECT id FROM redes_celulas WHERE lider_id = ? AND estado = 'Activa'");
            $stmt->execute([$pid]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);

        default: // Miembro, Servidor
            $stmt = $pdo->prepare("
                SELECT c.id FROM redes_celulas c
                JOIN redes_miembros_celula mc ON c.id = mc.celula_id
                WHERE mc.persona_id = ? AND mc.estado = 'Activo' AND c.estado = 'Activa'
            ");
            $stmt->execute([$pid]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

/**
 * Verificar que el usuario tiene acceso al módulo Personas
 */
function redes_verificar_acceso() {
    verificar_autenticacion('Personas');
}

/**
 * Verificar que el usuario es Admin del sistema o tiene rol de liderazgo en la iglesia
 */
function redes_verificar_liderazgo($pdo) {
    $usuario_id = $_SESSION['user_id'];
    $rol_sistema = $_SESSION['user_role'] ?? '';

    if ($rol_sistema === 'Admin') return true;

    $rol_iglesia = redes_obtener_rol_iglesia($pdo, $usuario_id);
    $roles_liderazgo = ['Pastor Principal', 'Pastor Ayudante', 'Lider de Red', 'Lider de Célula'];

    if (!in_array($rol_iglesia, $roles_liderazgo)) {
        header('Location: index.php?error=sin_permiso');
        exit;
    }
    return true;
}

// -- Helpers de formato --

function redes_formato_fecha($fecha, $formato = 'd/m/Y') {
    if (!$fecha) return '';
    return date($formato, strtotime($fecha));
}

function redes_formato_hora($hora) {
    if (!$hora) return '';
    return date('H:i', strtotime($hora));
}

function redes_calcular_edad($fecha_nacimiento) {
    if (!$fecha_nacimiento) return '';
    return (new DateTime($fecha_nacimiento))->diff(new DateTime())->y;
}
?>
