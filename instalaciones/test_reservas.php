<?php
// Script de prueba para verificar y crear reservas de prueba
require_once '../config/database.php';

echo "<h1>🔍 Diagnóstico de Reservas</h1>";

// 1. Verificar si hay salones
echo "<h2>📍 Salones en la base de datos:</h2>";
$stmt = $pdo->query("SELECT * FROM salones");
$salones = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($salones)) {
    echo "<p style='color: red;'>❌ No hay salones en la base de datos. Debes crear al menos uno.</p>";
    
    // Crear un salón de prueba
    echo "<p>🔧 Creando salón de prueba...</p>";
    $stmt = $pdo->prepare("INSERT INTO salones (numero, nombre, capacidad, descripcion, estado) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['1', 'Salón Principal', 100, 'Salón principal para eventos', 'activo']);
    echo "<p style='color: green;'>✅ Salón de prueba creado</p>";
    
    // Volver a consultar
    $stmt = $pdo->query("SELECT * FROM salones");
    $salones = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($salones as $salon) {
    echo "<p>✅ Salón {$salon['numero']}: {$salon['nombre']} (Capacidad: {$salon['capacidad']})</p>";
}

// 2. Verificar si hay usuarios
echo "<h2>👥 Usuarios en la base de datos:</h2>";
$stmt = $pdo->query("SELECT id, nombre, email, role FROM usuarios LIMIT 5");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($usuarios as $usuario) {
    echo "<p>✅ Usuario: {$usuario['nombre']} ({$usuario['email']}) - Rol: {$usuario['role']}</p>";
}

// 3. Verificar reservas existentes
echo "<h2>📅 Reservas en la base de datos:</h2>";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM reservas");
$total = $stmt->fetchColumn();
echo "<p>Total de reservas: <strong>$total</strong></p>";

if ($total > 0) {
    $stmt = $pdo->query("
        SELECT r.*, s.numero as salon_numero, u.nombre as usuario_nombre 
        FROM reservas r 
        JOIN salones s ON r.salon_id = s.id 
        JOIN usuarios u ON r.usuario_id = u.id 
        ORDER BY r.created_at DESC 
        LIMIT 10
    ");
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Salón</th><th>Usuario</th><th>Fecha</th><th>Hora</th><th>Estado</th><th>Motivo</th></tr>";
    
    foreach ($reservas as $reserva) {
        echo "<tr>";
        echo "<td>{$reserva['id']}</td>";
        echo "<td>{$reserva['salon_numero']}</td>";
        echo "<td>{$reserva['usuario_nombre']}</td>";
        echo "<td>{$reserva['fecha']}</td>";
        echo "<td>{$reserva['hora_inicio']} - {$reserva['hora_fin']}</td>";
        echo "<td>{$reserva['estado']}</td>";
        echo "<td>{$reserva['motivo']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No hay reservas en la base de datos</p>";
}

// 4. Crear una reserva de prueba si no hay
if ($total == 0 && !empty($salones) && !empty($usuarios)) {
    echo "<h2>🔧 Creando reserva de prueba...</h2>";
    
    $salon = $salones[0];
    $usuario = $usuarios[0];
    $fecha_manana = date('Y-m-d', strtotime('+1 day'));
    
    $stmt = $pdo->prepare("
        INSERT INTO reservas (salon_id, usuario_id, fecha, hora_inicio, hora_fin, motivo, descripcion, estado) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $resultado = $stmt->execute([
        $salon['id'],
        $usuario['id'],
        $fecha_manana,
        '10:00:00',
        '12:00:00',
        'Reserva de prueba para diagnóstico',
        'Esta es una reserva creada automáticamente para probar el sistema',
        'pendiente'
    ]);
    
    if ($resultado) {
        echo "<p style='color: green;'>✅ Reserva de prueba creada exitosamente</p>";
        echo "<p>📅 Fecha: $fecha_manana</p>";
        echo "<p>🕐 Hora: 10:00 - 12:00</p>";
        echo "<p>📍 Salón: {$salon['numero']} - {$salon['nombre']}</p>";
        echo "<p>👤 Usuario: {$usuario['nombre']}</p>";
        echo "<p>📝 Estado: pendiente</p>";
    } else {
        echo "<p style='color: red;'>❌ Error al crear reserva de prueba</p>";
    }
}

// 5. Verificar configuración
echo "<h2>⚙️ Configuración del sistema:</h2>";
$stmt = $pdo->query("SELECT parametro, valor FROM configuracion_instalaciones");
$config = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($config as $param) {
    echo "<p>📋 {$param['parametro']}: <strong>{$param['valor']}</strong></p>";
}

// 6. Verificar tabla de usuarios tiene campo telefono
echo "<h2>📱 Estructura de tabla usuarios:</h2>";
$stmt = $pdo->query("DESCRIBE usuarios");
$columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tiene_telefono = false;
foreach ($columnas as $columna) {
    if ($columna['Field'] === 'telefono') {
        $tiene_telefono = true;
        break;
    }
}

if ($tiene_telefono) {
    echo "<p style='color: green;'>✅ La tabla usuarios tiene el campo 'telefono'</p>";
} else {
    echo "<p style='color: orange;'>⚠️ La tabla usuarios no tiene el campo 'telefono'</p>";
}

echo "<hr>";
echo "<p><a href='aprobaciones.php'>🔄 Ir a Aprobaciones</a></p>";
echo "<p><a href='mis_reservas.php'>📋 Ir a Mis Reservas</a></p>";
echo "<p><a href='nueva_reserva_v2.php'>➕ Crear Nueva Reserva</a></p>";
?>
