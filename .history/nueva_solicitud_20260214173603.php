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
        $id_solicitud = $pdo->lastInsertId();

        // Obtener datos del usuario
        $stmtUser = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
        $stmtUser->execute([$usuario_id]);
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $nombre = $usuario['nombre'];
        $emailDestino = $usuario['email'] ?? 'informes@jesusmirefugio.org';

        // Enviar email con PHPMailer
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp-relay.brevo.com';
            $mail->SMTPAuth = true;
            $mail->Username = '9c8646001@smtp-brevo.com';
            $mail->Password = 'gyxm9v7F3G0aL6XS';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('finanzas@jesusmirefugio.org', 'Sistema de Solicitudes');
            $mail->addAddress($emailDestino, $nombre);

            // Copias visibles
            $mail->addCC('informes@jesusmirefugio.org', 'Tesorería');
            $mail->addCC('finanzas@jesusmirefugio.org', 'Administración');
            $mail->addCC('lcasabene@jesusmirefugio.org', 'Administración');

            $mail->isHTML(true);
            $mail->Subject = 'NUEVA SOLICITUD DE FONDOS #' . $id_solicitud;
            $mail->Body = "
                <h3>Solicitud registrada correctamente</h3>
                <p><strong>Solicitud N°:</strong> {$id_solicitud}</p>
                <p><strong>Nombre:</strong> {$nombre}</p>
                <p><strong>Monto:</strong> $" . number_format($monto, 2, ',', '.') . "</p>
                <p><strong>Fecha disponibilidad:</strong> {$fecha_disponibilidad}</p>
                <p><strong>Concepto:</strong> {$concepto}</p>
                <p><strong>Modalidad:</strong> {$modalidad}</p>
            ";

            $mail->send();
        } catch (Exception $e) {
            error_log('Error al enviar email solicitud #' . $id_solicitud . ': ' . $mail->ErrorInfo);
        }

        header('Location: listado_solicitudes.php?exito=1');
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
    <title>Nueva Solicitud - Sistema de Gestión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .modern-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid rgba(102, 126, 234, 0.3);
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-modern {
            border-radius: 10px;
            font-weight: 500;
            padding: 0.6rem 1.2rem;
            transition: all 0.3s ease;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .navbar-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 5px 30px rgba(0,0,0,0.1);
        }

        .brand-text {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .section-title {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(102, 126, 234, 0.3);
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: block;
            padding: 0.6rem 1rem;
            background: rgba(102, 126, 234, 0.1);
            border: 2px dashed rgba(102, 126, 234, 0.5);
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-input-label:hover {
            background: rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.8);
        }

        .transfer-section {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manejo de nombres de archivos
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const fileName = e.target.files[0]?.name || 'Seleccionar archivo';
                const label = this.nextElementSibling;
                const fileNameSpan = label.querySelector('.file-name');
                if (fileNameSpan) {
                    fileNameSpan.textContent = fileName;
                }
            });
        });

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
<body>
    <nav class="navbar navbar-expand-lg navbar-modern sticky-top">
        <div class="container">
            <a class="navbar-brand brand-text" href="menu_anticipos_moderno.php">
                <i class="bi bi-cash-stack"></i> Anticipos
            </a>
            <div class="navbar-nav ms-auto">
                <a href="menu_anticipos_moderno.php" class="btn btn-outline-secondary btn-modern">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="modern-card p-4">
            <h2 class="mb-4">
                <i class="bi bi-plus-circle"></i> Registrar Nueva Solicitud
            </h2>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

        <form enctype="multipart/form-data" method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-4">
                        <label for="fecha_disponibilidad" class="form-label">
                            <i class="bi bi-calendar-event"></i> Fecha requerida
                        </label>
                        <input type="date" name="fecha_disponibilidad" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-4">
                        <label for="monto" class="form-label">
                            <i class="bi bi-currency-dollar"></i> Monto
                        </label>
                        <input
                            type="text"
                            id="monto"
                            name="monto"
                            class="form-control monto-autonum"
                            autocomplete="off"
                            inputmode="numeric"
                            placeholder="0,00"
                            value="<?= htmlspecialchars($monto ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-4">
                        <label for="modalidad" class="form-label">
                            <i class="bi bi-wallet2"></i> Modalidad
                        </label>
                        <select name="modalidad" id="modalidad" class="form-select" onchange="mostrarDatosTransferencia()" required>
                            <option value="">Seleccione una modalidad</option>
                            <option value="efectivo">
                                <i class="bi bi-cash"></i> Efectivo
                            </option>
                            <option value="transferencia">
                                <i class="bi bi-bank"></i> Transferencia
                            </option>
                            <option value="reintegro">
                                <i class="bi bi-arrow-repeat"></i> Reintegro
                            </option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-4">
                        <label for="departamento_id" class="form-label">
                            <i class="bi bi-building"></i> Departamento
                        </label>
                        <select name="departamento_id" class="form-select" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($departamentos as $dep): ?>
                                <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div id="datosTransferencia" style="display: none;" class="transfer-section mb-4">
                <h3 class="section-title h5">
                    <i class="bi bi-bank2"></i> Datos de Transferencia
                </h3>
                <div class="mb-3">
                    <label for="alias_cbu" class="form-label">
                        <i class="bi bi-credit-card"></i> Alias o CBU
                    </label>
                    <input type="text" name="alias_cbu" class="form-control" placeholder="Ingrese alias o CBU">
                </div>
            </div>

            <div class="mb-4">
                <label for="concepto" class="form-label">
                    <i class="bi bi-chat-text"></i> Concepto
                </label>
                <textarea name="concepto" class="form-control" rows="3" required placeholder="Describa el motivo de la solicitud..."></textarea>
            </div>

            <div id="campo_archivo" style="display:none;" class="mb-4">
                <label class="form-label">
                    <i class="bi bi-file-earmark-pdf"></i> Adjuntar comprobante (PDF, JPG, etc.)
                </label>
                <div class="file-input-wrapper">
                    <input type="file" name="archivo_reintegro" id="archivo_reintegro" accept=".pdf,.jpg,.jpeg,.png">
                    <label for="archivo_reintegro" class="file-input-label">
                        <i class="bi bi-cloud-upload"></i> 
                        <span class="file-name">Seleccionar archivo</span>
                    </label>
                </div>
            </div>
            <div class="d-flex justify-content-between mt-4">
                <a href="menu_anticipos_moderno.php" class="btn btn-secondary btn-modern">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-success btn-modern">
                    <i class="bi bi-check-circle"></i> Enviar Solicitud
                </button>
            </div> 
    

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


        </div>
    </div>
</body>
</html>