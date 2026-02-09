<?php
/**
 * Integración con WhatsApp para notificaciones del sistema de instalaciones
 * 
 * Requisitos:
 * 1. Cuenta de WhatsApp Business API
 * 2. Token de acceso permanente
 * 3. Número de teléfono verificado
 */

class WhatsAppIntegration {
    private $apiUrl;
    private $token;
    private $phoneNumberId;
    
    public function __construct($token, $phoneNumberId) {
        $this->apiUrl = 'https://graph.facebook.com/v18.0';
        $this->token = $token;
        $this->phoneNumberId = $phoneNumberId;
    }
    
    /**
     * Enviar mensaje de texto
     */
    public function sendTextMessage($to, $message) {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        return $this->makeRequest($url, $data);
    }
    
    /**
     * Enviar notificación de reserva aprobada
     */
    public function notifyReservationApproved($userPhone, $userName, $salonName, $date, $time) {
        $message = "🎉 *RESERVA APROBADA* 🎉\n\n";
        $message .= "Hola {$userName},\n\n";
        $message .= "Tu reserva ha sido aprobada:\n";
        $message .= "📍 Salón: {$salonName}\n";
        $message .= "📅 Fecha: {$date}\n";
        $message .= "🕐 Hora: {$time}\n\n";
        $message .= "¡Gracias por usar nuestro sistema de reservas!";
        
        return $this->sendTextMessage($userPhone, $message);
    }
    
    /**
     * Enviar notificación de reserva rechazada
     */
    public function notifyReservationRejected($userPhone, $userName, $salonName, $date, $time, $reason = '') {
        $message = "❌ *RESERVA RECHAZADA* ❌\n\n";
        $message .= "Hola {$userName},\n\n";
        $message .= "Lamentamos informarte que tu reserva ha sido rechazada:\n";
        $message .= "📍 Salón: {$salonName}\n";
        $message .= "📅 Fecha: {$date}\n";
        $message .= "🕐 Hora: {$time}\n";
        
        if ($reason) {
            $message .= "\n📝 Motivo: {$reason}";
        }
        
        $message .= "\n\nPor favor, contacta al administrador para más información.";
        
        return $this->sendTextMessage($userPhone, $message);
    }
    
    /**
     * Enviar recordatorio de reserva
     */
    public function sendReservationReminder($userPhone, $userName, $salonName, $date, $time) {
        $message = "⏰ *RECORDATORIO DE RESERVA* ⏰\n\n";
        $message .= "Hola {$userName},\n\n";
        $message .= "Te recordamos tu reserva para hoy:\n";
        $message .= "📍 Salón: {$salonName}\n";
        $message .= "🕐 Hora: {$time}\n\n";
        $message .= "¡No olvides asistir! 🙏";
        
        return $this->sendTextMessage($userPhone, $message);
    }
    
    /**
     * Enviar notificación de nueva reserva (para administradores)
     */
    public function notifyNewReservation($adminPhones, $userName, $salonName, $date, $time, $motivo) {
        $message = "📋 *NUEVA RESERVA PENDIENTE* 📋\n\n";
        $message .= "Usuario: {$userName}\n";
        $message .= "📍 Salón: {$salonName}\n";
        $message .= "📅 Fecha: {$date}\n";
        $message .= "🕐 Hora: {$time}\n";
        $message .= "📝 Motivo: {$motivo}\n\n";
        $message .= "Por favor, revisa el panel de aprobaciones.";
        
        $results = [];
        foreach ($adminPhones as $phone) {
            $results[] = $this->sendTextMessage($phone, $message);
        }
        
        return $results;
    }
    
    /**
     * Realizar la petición a la API
     */
    private function makeRequest($url, $data) {
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $httpCode === 200,
            'response' => json_decode($response, true),
            'http_code' => $httpCode
        ];
    }
}

/**
 * Función para configurar y usar la integración
 */
function setupWhatsAppIntegration() {
    // Configuración - debes obtener estos valores de Meta for Developers
    $config = [
        'token' => 'TU_ACCESS_TOKEN_PERMANENTE', // Reemplazar con tu token real
        'phone_number_id' => 'TU_PHONE_NUMBER_ID', // Reemplazar con tu ID
        'admin_phones' => [
            '549111111111', // Números de administradores
            '549222222222'
        ],
        'enabled' => true // Para activar/desactivar fácilmente
    ];
    
    return $config;
}

/**
 * Ejemplo de uso en el sistema de aprobaciones
 */
function sendWhatsAppNotification($type, $data) {
    $config = setupWhatsAppIntegration();
    
    if (!$config['enabled']) {
        return ['success' => false, 'message' => 'WhatsApp integration disabled'];
    }
    
    $whatsapp = new WhatsAppIntegration($config['token'], $config['phone_number_id']);
    
    switch ($type) {
        case 'reservation_approved':
            return $whatsapp->notifyReservationApproved(
                $data['user_phone'],
                $data['user_name'],
                $data['salon_name'],
                $data['date'],
                $data['time']
            );
            
        case 'reservation_rejected':
            return $whatsapp->notifyReservationRejected(
                $data['user_phone'],
                $data['user_name'],
                $data['salon_name'],
                $data['date'],
                $data['time'],
                $data['reason'] ?? ''
            );
            
        case 'new_reservation':
            return $whatsapp->notifyNewReservation(
                $config['admin_phones'],
                $data['user_name'],
                $data['salon_name'],
                $data['date'],
                $data['time'],
                $data['motivo']
            );
            
        case 'reservation_reminder':
            return $whatsapp->sendReservationReminder(
                $data['user_phone'],
                $data['user_name'],
                $data['salon_name'],
                $data['date'],
                $data['time']
            );
            
        default:
            return ['success' => false, 'message' => 'Unknown notification type'];
    }
}

/**
 * Ejemplo de cómo integrar en el proceso de aprobación
 */
/*
// En el archivo aprobaciones.php, después de aprobar una reserva:

if ($_POST['action'] === 'aprobar') {
    // ... código existente de aprobación ...
    
    // Obtener datos del usuario
    $stmt = $pdo->prepare("SELECT u.nombre, u.email, u.telefono FROM usuarios u JOIN reservas r ON u.id = r.usuario_id WHERE r.id = ?");
    $stmt->execute([$reserva_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Enviar notificación por WhatsApp
    if ($usuario && !empty($usuario['telefono'])) {
        $resultado = sendWhatsAppNotification('reservation_approved', [
            'user_phone' => $usuario['telefono'],
            'user_name' => $usuario['nombre'],
            'salon_name' => $reserva['salon_nombre'],
            'date' => date('d/m/Y', strtotime($reserva['fecha'])),
            'time' => $reserva['hora_inicio'] . ' - ' . $reserva['hora_fin']
        ]);
        
        if (!$resultado['success']) {
            error_log("Error enviando WhatsApp: " . json_encode($resultado));
        }
    }
}
*/

/**
 * Ejemplo de cron job para recordatorios automáticos
 */
/*
// Crear un archivo recordatorios.php y ejecutarlo diariamente con cron

require_once 'config/database.php';
require_once 'whatsapp_integration.php';

// Obtener reservas para mañana
$mañana = date('Y-m-d', strtotime('+1 day'));
$stmt = $pdo->prepare("
    SELECT r.*, u.nombre, u.telefono, s.nombre as salon_nombre 
    FROM reservas r 
    JOIN usuarios u ON r.usuario_id = u.id 
    JOIN salones s ON r.salon_id = s.id 
    WHERE r.fecha = ? AND r.estado = 'aprobada'
    AND u.telefono IS NOT NULL AND u.telefono != ''
");
$stmt->execute([$mañana]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($reservas as $reserva) {
    sendWhatsAppNotification('reservation_reminder', [
        'user_phone' => $reserva['telefono'],
        'user_name' => $reserva['nombre'],
        'salon_name' => $reserva['salon_nombre'],
        'date' => date('d/m/Y', strtotime($reserva['fecha'])),
        'time' => $reserva['hora_inicio'] . ' - ' . $reserva['hora_fin']
    ]);
}
*/
?>
