<?php
/**
 * Script para enviar recordatorios automáticos de reservas
 * Este archivo debe ejecutarse diariamente via cron job
 * 
 * Ejemplo de configuración en cPanel/Cron:
 * 0 8 * * * /usr/bin/php /ruta/al/archivo/recordatorios.php
 * 
 * O para desarrollo local:
 * php recordatorios.php
 */

require_once '../config/database.php';
require_once 'whatsapp_integration.php';

echo "=== INICIANDO RECORDATORIOS AUTOMÁTICOS ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Recordatorios para reservas de mañana
    echo "1. Buscando reservas para mañana...\n";
    $mañana = date('Y-m-d', strtotime('+1 day'));
    
    $stmt = $pdo->prepare("
        SELECT r.*, u.nombre, u.email, u.telefono, s.nombre as salon_nombre, s.numero as salon_numero
        FROM reservas r 
        JOIN usuarios u ON r.usuario_id = u.id 
        JOIN salones s ON r.salon_id = s.id 
        WHERE r.fecha = ? AND r.estado = 'aprobada'
        AND u.telefono IS NOT NULL AND u.telefono != ''
        ORDER BY r.hora_inicio
    ");
    $stmt->execute([$mañana]);
    $reservas_manana = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Encontradas " . count($reservas_manana) . " reservas para mañana\n";
    
    foreach ($reservas_manana as $reserva) {
        echo "   - Enviando recordatorio a: {$reserva['nombre']} ({$reserva['telefono']})\n";
        
        $resultado = sendWhatsAppNotification('reservation_reminder', [
            'user_phone' => $reserva['telefono'],
            'user_name' => $reserva['nombre'],
            'salon_name' => $reserva['salon_numero'] . ' - ' . $reserva['salon_nombre'],
            'date' => date('d/m/Y', strtotime($reserva['fecha'])),
            'time' => $reserva['hora_inicio'] . ' - ' . $reserva['hora_fin']
        ]);
        
        if ($resultado['success']) {
            echo "     ✅ Recordatorio enviado exitosamente\n";
        } else {
            echo "     ❌ Error: " . ($resultado['message'] ?? 'Error desconocido') . "\n";
        }
        
        // Pequeña pausa para no sobrecargar la API
        sleep(1);
    }
    
    // 2. Recordatorios para reservas de hoy (en la mañana)
    $hora_actual = (int)date('H');
    if ($hora_actual >= 7 && $hora_actual <= 9) { // Entre 7 AM y 9 AM
        echo "\n2. Buscando reservas para hoy (recordatorio matutino)...\n";
        $hoy = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT r.*, u.nombre, u.email, u.telefono, s.nombre as salon_nombre, s.numero as salon_numero
            FROM reservas r 
            JOIN usuarios u ON r.usuario_id = u.id 
            JOIN salones s ON r.salon_id = s.id 
            WHERE r.fecha = ? AND r.estado = 'aprobada'
            AND u.telefono IS NOT NULL AND u.telefono != ''
            AND r.hora_inicio > TIME(NOW())
            ORDER BY r.hora_inicio
        ");
        $stmt->execute([$hoy]);
        $reservas_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Encontradas " . count($reservas_hoy) . " reservas para hoy\n";
        
        foreach ($reservas_hoy as $reserva) {
            echo "   - Enviando recordatorio matutino a: {$reserva['nombre']} ({$reserva['telefono']})\n";
            
            $resultado = sendWhatsAppNotification('reservation_reminder', [
                'user_phone' => $reserva['telefono'],
                'user_name' => $reserva['nombre'],
                'salon_name' => $reserva['salon_numero'] . ' - ' . $reserva['salon_nombre'],
                'date' => 'HOY',
                'time' => $reserva['hora_inicio'] . ' - ' . $reserva['hora_fin']
            ]);
            
            if ($resultado['success']) {
                echo "     ✅ Recordatorio matutino enviado exitosamente\n";
            } else {
                echo "     ❌ Error: " . ($resultado['message'] ?? 'Error desconocido') . "\n";
            }
            
            sleep(1);
        }
    }
    
    // 3. Notificar a administradores sobre reservas pendientes de más de 24h
    echo "\n3. Buscando reservas pendientes antiguas...\n";
    $hace_24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
    
    $stmt = $pdo->prepare("
        SELECT r.*, u.nombre as usuario_nombre, s.nombre as salon_nombre, s.numero as salon_numero
        FROM reservas r 
        JOIN usuarios u ON r.usuario_id = u.id 
        JOIN salones s ON r.salon_id = s.id 
        WHERE r.estado = 'pendiente' 
        AND r.created_at < ?
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$hace_24h]);
    $reservas_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($reservas_pendientes) > 0) {
        echo "   Encontradas " . count($reservas_pendientes) . " reservas pendientes por más de 24h\n";
        
        foreach ($reservas_pendientes as $reserva) {
            echo "   - Notificando reserva pendiente: {$reserva['motivo']}\n";
            
            $resultado = sendWhatsAppNotification('new_reservation', [
                'user_name' => $reserva['usuario_nombre'],
                'salon_name' => $reserva['salon_numero'] . ' - ' . $reserva['salon_nombre'],
                'date' => date('d/m/Y', strtotime($reserva['fecha'])),
                'time' => $reserva['hora_inicio'] . ' - ' . $reserva['hora_fin'],
                'motivo' => $reserva['motivo']
            ]);
            
            if ($resultado['success']) {
                echo "     ✅ Notificación a administradores enviada\n";
            } else {
                echo "     ❌ Error: " . ($resultado['message'] ?? 'Error desconocido') . "\n";
            }
            
            sleep(1);
        }
    } else {
        echo "   No hay reservas pendientes antiguas\n";
    }
    
    // 4. Limpiar reservas recurrentes expiradas
    echo "\n4. Limpiando reservas recurrentes expiradas...\n";
    $hoy = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        UPDATE reservas 
        SET estado = 'cancelada', 
            observaciones = CONCAT(IFNULL(observaciones, ''), '\nCancelada automáticamente: Recurrencia expirada el ', CURDATE())
        WHERE es_recurrente != 'no' 
        AND fecha_fin_recurrente IS NOT NULL 
        AND fecha_fin_recurrente < ?
    ");
    $stmt->execute([$hoy]);
    $afectadas = $stmt->rowCount();
    
    echo "   {$afectadas} reservas recurrentes canceladas por expiración\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== RECORDATORIOS COMPLETADOS ===\n";
echo "Fecha fin: " . date('Y-m-d H:i:s') . "\n";

// Registrar en log
$log_message = "[" . date('Y-m-d H:i:s') . "] Recordatorios automáticos ejecutados. Reservas mañana: " . count($reservas_manana ?? []) . ", Reservas hoy: " . count($reservas_hoy ?? []) . ", Pendientes antiguas: " . count($reservas_pendientes ?? []) . "\n";
file_put_contents('recordatorios.log', $log_message, FILE_APPEND | LOCK_EX);
?>
