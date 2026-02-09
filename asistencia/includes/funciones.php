<?php
function tieneAccesoModulo($modulo) {
    return isset($_SESSION['modulos']) && in_array($modulo, $_SESSION['modulos']);
}

function obtenerAsistenciaDelDia($pdo, $usuario_id, $fecha) {
    $stmt = $pdo->prepare("SELECT * FROM asistencias WHERE usuario_id = ? AND fecha = ?");
    $stmt->execute([$usuario_id, $fecha]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
