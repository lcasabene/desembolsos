<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';
// Eliminado: require_once 'scripts/form_process.php';
// Incluir PHPMailer (sin Composer)

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';
require 'scripts/form_process.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['user_id'];
    $fecha_solicitud = date('Y-m-d');
    $monto = floatval(str_replace(['.', ','], ['', '.'], $_POST['monto']));
    $fecha_disponibilidad = $_POST['fecha_disponibilidad'];
    $modalidad = $_POST['modalidad'];
    $alias_cbu = $_POST['alias_cbu'] ?? null;
$archivo_nombre = null;

    if ($modalidad === 'reintegro' && isset($_FILES['archivo_reintegro']) && $_FILES['archivo_reintegro']['error'] === UPLOAD_ERR_OK) {
        $nombre_temporal = $_FILES['archivo_reintegro']['tmp_name'];
        $nombre_original = basename($_FILES['archivo_reintegro']['name']);
        $nombre_limpio = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', pathinfo($nombre_original, PATHINFO_FILENAME));
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $nombre_final = uniqid() . '_' . $nombre_limpio . '.' . $extension;
        $archivo_nombre = 'uploads_reintegros/' . $nombre_final;
        move_uploaded_file($nombre_temporal, $archivo_nombre);
    }
    $concepto = $_POST['concepto'] ?? '';
    $departamento_id = $_POST['departamento_id'] ?? null;

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
        // Obtener nombre e email del solicitante
        $id_solicitud = $pdo->lastInsertId();
        $stmtUser = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
        $stmtUser->execute([$usuario_id]);
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

        // Enviar email con PHPMailer
        
        // Obtener datos del usuario
        $stmtUser = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
        $stmtUser->execute([$usuario_id]);
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $nombre = $usuario['nombre'];
        $emailDestino = $usuario['email'] ?? 'informes@jesusmirefugio.org';

$mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
                $mail->Host = 'smtp-relay.brevo.com';
                $mail->SMTPAuth = true;
                $mail->Username = '9c8646001@smtp-brevo.com';
                $mail->Password = 'gyxm9v7F3G0aL6XS'; // poné la contraseña real del correo
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
            //Remitente
            $mail->setFrom('finanzas@jesusmirefugio.org', 'Sistema de Solicitudes');
            $mail->addAddress($emailDestino, $nombre);

           // Copias visibles
            $mail->addCC('informes@jesusmirefugio.org', 'Tesorería');
            $mail->addCC('finanzas@jesusmirefugio.org', 'Administración');

            // Copias ocultas
            // $mail->addBCC('arielcasabene@gmail.com', 'Ariel');
            //$mail->addBCC('controlinterno@jesusmirefugio.org');

            $mail->isHTML(true);
            $mail->Subject = 'NUEVA SOLICITUD DE FONDOS #'.$id_solicitud;
            $mail->Body = "
                <h3>Solicitud registrada correctamente</h3>
                <p><strong>Nombre:</strong> {$nombre}</p>
                <p><strong>Monto:</strong> $" . number_format($monto, 2, ',', '.') . "</p>
                <p><strong>Fecha disponibilidad:</strong> {$fecha_disponibilidad}</p>
                <p><strong>Concepto:</strong> {$concepto}</p>
                <p><strong>Modalidad:</strong> {$modalidad}</p>
                
            ";

            $mail->send();
           
            echo 'Correo enviado correctamente';
            } catch (Exception $e) {
                echo 'Error al enviar el correo: ' . $mail->ErrorInfo;
            }

        echo '<script>window.location.href = "listado_solicitudes.php?exito=1";</script>';
        //sleep(15);
        exit;
    } else {
        $error = "Error al registrar la solicitud.";
         //sleep(15);
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

        function formatearMonto(input) {
            let valor = input.value.replace(/\./g, '').replace(',', '.');
            let numero = parseFloat(valor);
            if (!isNaN(numero)) {
                input.value = numero.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
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
                value="<?= htmlspecialchars($monto ?? '') ?>">
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
    

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const modalidad = document.getElementById("modalidad");
        const campoArchivo = document.getElementById("campo_archivo");
        if (modalidad) {
            modalidad.addEventListener("change", function () {
                campoArchivo.style.display = this.value === "reintegro" ? "block" : "none";
            });
            // Mostrar al cargar si ya está seleccionado
            campoArchivo.style.display = modalidad.value === "reintegro" ? "block" : "none";
        }
    });
    </script>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {

    function formatearMontoInput(input) {
        let valor = input.value;

        // 1) Dejar solo dígitos (elimina puntos, comas, letras, etc.)
        valor = valor.replace(/\D/g, '');

        // Si está vacío, no mostramos nada
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

    // Aplicar a todos los inputs con la clase .monto-autonum
    document.querySelectorAll('.monto-autonum').forEach(function (input) {
        input.addEventListener('input', function () {
            formatearMontoInput(input);
        });
    });
});
</script>


</body>
</html>