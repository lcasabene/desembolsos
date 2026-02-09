<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';
require_once 'form_process.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['user_id'];
    $fecha_solicitud = date('Y-m-d');
    $monto = floatval(str_replace(['.', ','], ['', '.'], $_POST['monto']));
    $fecha_disponibilidad = $_POST['fecha_disponibilidad'];
    $modalidad = $_POST['modalidad'];
    $cbu = $_POST['cbu'] ?? null;
    $alias = $_POST['alias'] ?? null;
    $concepto = $_POST['concepto'] ?? '';
    $departamento_id = $_POST['departamento_id'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO solicitudes (usuario_id, fecha_solicitud, monto, fecha_disponibilidad, modalidad, cbu, alias, concepto, departamento_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([
        $usuario_id,
        $fecha_solicitud,
        $monto,
        $fecha_disponibilidad,
        $modalidad,
        $cbu,
        $alias,
        $concepto,
        $departamento_id
    ]);

    if ($result) {
        // Obtener nombre e email del solicitante
        $stmtUser = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
        $stmtUser->execute([$usuario_id]);
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

        // Preparar email
        $_POST['custom_U958'] = $usuario['nombre'];
        $_POST['Email'] = $usuario['email'] ?? 'informes@jesusmirefugio.org';

        $form = array(
            'subject' => 'Nueva solicitud registrada',
            'email' => array(
                'from' => 'informes@jesusmirefugio.org',
                'to' => 'informes@jesusmirefugio.org'
            )
        );

        process_form($form);

        header("Location: listado_solicitudes.php?exito=1");
        exit;
    } else {
        $error = "Error al registrar la solicitud.";
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

    <form method="POST">
        <div class="mb-3">
            <label for="fecha_disponibilidad" class="form-label">Fecha requerida</label>
            <input type="date" name="fecha_disponibilidad" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="monto" class="form-label">Monto</label>
            <input type="text" name="monto" class="form-control" required onblur="formatearMonto(this)">
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
                <label for="cbu" class="form-label">CBU</label>
                <input type="text" name="cbu" class="form-control">
            </div>
            <div class="mb-3">
                <label for="alias" class="form-label">Alias</label>
                <input type="text" name="alias" class="form-control">
            </div>
        </div>

        <div class="mb-3">
            <label for="concepto" class="form-label">Concepto</label>
            <textarea name="concepto" class="form-control" rows="2"></textarea>
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

        <button type="submit" class="btn btn-success">Enviar Solicitud</button>
        <a href="menu.php" class="btn btn-secondary">Volver</a>
    </form>
</body>
</html>
