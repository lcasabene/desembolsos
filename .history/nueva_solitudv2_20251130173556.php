<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

$error = '';
$success = '';

// =========================================================================
// INTEGRACIÓN CON BREVO (Sendinblue)
// =========================================================================

// Configuración de la API de Brevo
function send_email_brevo($recipient_email, $recipient_name, $monto, $fecha_disponibilidad, $concepto, $usuario_id) {
    // 1. OBTÉN TU CLAVE API V3 DE BREVO Y PÉGALA AQUÍ
    $brevo_api_key = 'TU_CLAVE_API_V3_AQUI'; 

    if ($brevo_api_key === 'TU_CLAVE_API_V3_AQUI') {
        error_log("ADVERTENCIA: Clave API de Brevo no configurada. Email no enviado.");
        return false;
    }

    $url = 'https://api.sendinblue.com/v3/smtp/email';
    
    // Formatear el monto para el email
    $monto_formateado = "$" . number_format($monto, 2, ',', '.');
    
    // Contenido del Email
    $subject = 'CONFIRMACIÓN: Nueva solicitud registrada #' . $usuario_id;
    $html_content = "
        <h3>Hola {$recipient_name}, tu solicitud ha sido registrada correctamente.</h3>
        <p>A continuación, los detalles de tu solicitud:</p>
        <hr>
        <p><strong>Monto:</strong> {$monto_formateado}</p>
        <p><strong>Fecha de disponibilidad requerida:</strong> {$fecha_disponibilidad}</p>
        <p><strong>Concepto:</strong> {$concepto}</p>
        <p><small>ID de Usuario: {$usuario_id}</small></p>
        <hr>
        <p>Gracias por usar el sistema.</p>
    ";

    $data = array(
        "sender" => array(
            "name" => "Sistema de Solicitudes",
            "email" => "informes@jesusmirefugio.org" // Debe ser un email VERIFICADO en Brevo
        ),
        "to" => array(
            array(
                "email" => $recipient_email,
                "name" => $recipient_name
            )
        ),
        "subject" => $subject,
        "htmlContent" => $html_content
    );

    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'api-key: ' . $brevo_api_key
    );

    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 201) { // 201 Created es el código de éxito de Brevo
        return true;
    } else {
        error_log("Error al enviar email con Brevo. Código HTTP: {$http_code}. Respuesta: {$response}");
        return false;
    }
}
// =========================================================================
// FIN INTEGRACIÓN CON BREVO
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['user_id'];
    $fecha_solicitud = date('Y-m-d');
    // La función floatval con str_replace ya maneja el formato de monto con puntos/comas
    $monto = floatval(str_replace(['.', ','], ['', '.'], $_POST['monto'])); 
    $fecha_disponibilidad = $_POST['fecha_disponibilidad'];
    $modalidad = $_POST['modalidad'];
    $alias_cbu = $_POST['alias_cbu'] ?? null;
    $archivo_nombre = null;

    // Lógica de subida de archivo para reintegro
    if ($modalidad === 'reintegro' && isset($_FILES['archivo_reintegro']) && $_FILES['archivo_reintegro']['error'] === UPLOAD_ERR_OK) {
        $nombre_temporal = $_FILES['archivo_reintegro']['tmp_name'];
        $nombre_original = basename($_FILES['archivo_reintegro']['name']);
        $nombre_limpio = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', pathinfo($nombre_original, PATHINFO_FILENAME));
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $nombre_final = uniqid() . '_' . $nombre_limpio . '.' . $extension;
        $archivo_nombre = 'uploads_reintegros/' . $nombre_final;
        
        // Asumiendo que 'uploads_reintegros' tiene permisos de escritura
        if (!move_uploaded_file($nombre_temporal, $archivo_nombre)) {
            $error = "Error al mover el archivo de reintegro.";
        }
    }
    
    $concepto = $_POST['concepto'] ?? '';
    $departamento_id = $_POST['departamento_id'] ?? null;

    // --- 1. Guardar en la Base de Datos ---
    $stmt = $pdo->prepare("INSERT INTO solicitudes (usuario_id, fecha_solicitud, monto, fecha_disponibilidad, modalidad, alias_cbu, observaciones, departamento_id, archivo_reintegro) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?,?)");
    $result = $stmt->execute([
        $usuario_id,
        $fecha_solicitud,
        $monto,
        $fecha_disponibilidad,
        $modalidad,
        $alias_cbu,
        $concepto,
        $departamento_id,
        $archivo_nombre
    ]);

    if ($result) {
        // --- 2. Obtener datos del solicitante para el email ---
        $stmtUser = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
        $stmtUser->execute([$usuario_id]);
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $nombre = $usuario['nombre'];
        $emailDestino = $usuario['email'] ?? null; // Si no hay email, no se puede enviar

        if ($emailDestino) {
             // --- 3. Enviar email con Brevo ---
            if (send_email_brevo($emailDestino, $nombre, $monto, $fecha_disponibilidad, $concepto, $usuario_id)) {
                // Éxito en el envío (solo para log)
                // Opcionalmente puedes agregar aquí un email a los administradores
            } else {
                 // Fallo en el envío (el error se registra en el log del servidor)
                error_log("Fallo al enviar el correo de confirmación de solicitud a {$emailDestino} usando Brevo.");
            }
        }
        
        // Redirección final
        echo '<script>window.location.href = "listado_solicitudes.php?exito=1";</script>';
        exit;
    } else {
        $error = "Error al registrar la solicitud en la base de datos.";
    }
}

// Traer departamentos
$stmtDeps = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre");
$departamentos = $stmtDeps->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Solicitud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function mostrarDatosTransferencia() {
            const modalidad = document.getElementById('modalidad').value;
            document.getElementById('datosTransferencia').style.display = (modalidad === 'transferencia' || modalidad === 'reintegro') ? 'block' : 'none';
        }

        // Esta función ya no es necesaria con el script al final, pero se mantiene para claridad
        function formatearMonto(input) {
             // Lógica de formateo se realiza al final
        }
    </script>
</head>
<body class="container py-5">
    <h2>Registrar Nueva Solicitud</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form enctype="multipart/form-data" method="POST">
        <div class="mb-3">
            <label for="fecha_disponibilidad" class="form-label">Fecha requerida</label>
            <input type="date" name="fecha_disponibilidad" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="monto" class="form-label">Monto</label>
            <input
                type="text"
                id="monto"
                name="monto"
                class="form-control monto-autonum"
                autocomplete="off"
                inputmode="numeric"
                value="<?= htmlspecialchars($monto ?? '') ?>"
                required>
        </div>

        <div class="mb-3">
            <label for="modalidad" class="form-label">Modalidad</label>
            <select name="modalidad" id="modalidad" class="form-select" onchange="mostrarDatosTransferencia()" required>
                <option value="">Seleccione</option>
                <option value="efectivo">Efectivo</option>
                <option value="transferencia">Transferencia</option>
                <option value="reintegro">Reintegro</option>
            </select>
        </div>

        <div id="datosTransferencia" style="display: none;">
            <div class="mb-3">
                <label for="alias_cbu" class="form-label">Alias o cbu</label>
                <input type="text" name="alias_cbu" class="form-control">
            </div>
           
        </div>

        <div class="mb-3">
            <label for="concepto" class="form-label">Concepto</label>
            <textarea name="concepto" class="form-control" rows="2" required></textarea>
        </div>

        <div class="mb-3">
            <label for="departamento_id" class="form-label">Departamento</label>
            <select name="departamento_id" class="form-select" required>
                <option value="">Seleccione</option>
                <?php foreach ($departamentos as $dep): ?>
                    <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="campo_archivo" style="display:none;">
            <label for="archivo_reintegro">Adjuntar comprobante (PDF, JPG, etc.):</label>
            <input type="file" name="archivo_reintegro" id="archivo_reintegro" accept=".pdf,.jpg,.jpeg,.png,.pdf">
        </div>
        
        <button type="submit" class="btn btn-success">Enviar Solicitud</button>
        <a href="menu.php" class="btn btn-secondary">Volver</a> 
    </form>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const modalidad = document.getElementById("modalidad");
        const campoArchivo = document.getElementById("campo_archivo");
        if (modalidad) {
            modalidad.addEventListener("change", function () {
                campoArchivo.style.display = this.value === "reintegro" ? "block" : "none";
                mostrarDatosTransferencia(); // Llama a la función Bootstrap
            });
            // Mostrar al cargar si ya está seleccionado
            campoArchivo.style.display = modalidad.value === "reintegro" ? "block" : "none";
            mostrarDatosTransferencia();
        }
    });
    </script>

    <!-- Script de formateo de monto -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        function formatearMontoInput(input) {
            let valor = input.value;

            // 1) Dejar solo dígitos (elimina puntos, comas, letras, etc.)
            valor = valor.replace(/\D/g, '');

            if (!valor) {
                input.value = '';
                return;
            }

            // 2) Eliminar ceros a la izquierda (pero dejar uno si queda todo vacío)
            valor = valor.replace(/^0+/, '');
            if (valor === '') {
                valor = '0';
            }

            // 3) Separar parte entera y centavos (últimos dos dígitos = centavos)
            let enteros, centavos;
            if (valor.length === 1) {
                enteros = '0';
                centavos = '0' + valor;        // "5" -> "05" => 0,05
            } else if (valor.length === 2) {
                enteros = '0';
                centavos = valor;              // "50" -> "50" => 0,50
            } else {
                enteros = valor.slice(0, -2);  // todo menos los últimos 2
                centavos = valor.slice(-2);    // últimos 2 dígitos
            }

            // 4) Formatear miles con puntos
            enteros = enteros.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

            // 5) Armar valor final con coma decimal
            input.value = enteros + ',' + centavos;
        }

        document.querySelectorAll('.monto-autonum').forEach(function (input) {
            input.addEventListener('input', function () {
                formatearMontoInput(input);
            });
            // Formatear valor inicial si existe
            if(input.value) formatearMontoInput(input);
        });
    });
    </script>
</body>
</html>