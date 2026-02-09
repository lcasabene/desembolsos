<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ID inválido.";
    exit;
}

// Obtener solicitud
$stmt = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ?");
$stmt->execute([$id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    echo "Solicitud no encontrada.";
    exit;
}

// Obtener departamentos
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre")->fetchAll();

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departamento_id = $_POST['departamento_id'];
    $monto = str_replace(',', '.', $_POST['monto']);
    $fecha_disponibilidad = $_POST['fecha_disponibilidad'];
    $modalidad = $_POST['modalidad'];
    $alias_cbu = $_POST['alias_cbu'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
    $cantidad_comprobantes = null;
    $archivo_reintegro = $solicitud['archivo_reintegro'];

    if ($modalidad === 'Reintegro') {
        $cantidad_comprobantes = (int)($_POST['cantidad_comprobantes'] ?? 0);

        if (isset($_FILES['archivo_reintegro']) && $_FILES['archivo_reintegro']['error'] === UPLOAD_ERR_OK) {
            $archivo_original = $_FILES['archivo_reintegro']['name'];
            $archivo_sanitizado = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($archivo_original));
            $nombre_final = time() . '_' . $archivo_sanitizado;
            $ruta_destino = 'uploads_reintegros/' . $nombre_final;

            if (move_uploaded_file($_FILES['archivo_reintegro']['tmp_name'], $ruta_destino)) {
                $archivo_reintegro = $nombre_final;
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE solicitudes SET departamento_id=?, monto=?, fecha_disponibilidad=?, modalidad=?, alias_cbu=?, observaciones=?, archivo_reintegro=?, cantidad_comprobantes=? WHERE id=?");
    $stmt->execute([$departamento_id, $monto, $fecha_disponibilidad, $modalidad, $alias_cbu, $observaciones, $archivo_reintegro, $cantidad_comprobantes, $id]);

    header("Location: listado_solicitudes.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Solicitud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.6.0/dist/autoNumeric.min.js"></script>
</head>
<body class="container mt-5">
    <h2>Editar Solicitud</h2>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="departamento_id" class="form-label">Departamento</label>
            <select name="departamento_id" id="departamento_id" class="form-select" required>
                <?php foreach ($departamentos as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $solicitud['departamento_id'] == $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="monto" class="form-label">Monto</label>
            <input type="text" name="monto" id="monto" class="form-control" required value="<?= number_format($solicitud['monto'], 2, ',', '.') ?>">
        </div>

        <div class="mb-3">
            <label for="fecha_disponibilidad" class="form-label">Fecha disponibilidad</label>
            <input type="date" name="fecha_disponibilidad" id="fecha_disponibilidad" class="form-control" required value="<?= $solicitud['fecha_disponibilidad'] ?>">
        </div>

        <div class="mb-3">
            <label for="modalidad" class="form-label">Modalidad</label>
            <select name="modalidad" id="modalidad" class="form-select" required onchange="mostrarOpciones()">
                <option value="Efectivo" <?= $solicitud['modalidad'] === 'Efectivo' ? 'selected' : '' ?>>Efectivo</option>
                <option value="Transferencia" <?= $solicitud['modalidad'] === 'Transferencia' ? 'selected' : '' ?>>Transferencia</option>
                <option value="Reintegro" <?= $solicitud['modalidad'] === 'Reintegro' ? 'selected' : '' ?>>Reintegro</option>
            </select>
        </div>

        <div id="alias-cbu-seccion" style="display:none">
            <div class="mb-3">
                <label for="alias_cbu" class="form-label">Alias o CBU</label>
                <input type="text" name="alias_cbu" id="alias_cbu" class="form-control" value="<?= htmlspecialchars($solicitud['alias_cbu']) ?>">
            </div>
        </div>

        <div id="reintegro-opciones" style="display:none">
            <div class="mb-3">
                <label for="archivo_reintegro" class="form-label">Archivo PDF</label>
                <input type="file" name="archivo_reintegro" id="archivo_reintegro" class="form-control">
                <?php if ($solicitud['archivo_reintegro']): ?>
                    <a href="uploads_reintegros/<?= rawurlencode($solicitud['archivo_reintegro']) ?>" target="_blank">Archivo actual</a>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label for="cantidad_comprobantes" class="form-label">Cantidad Comprobantes</label>
                <input type="number" name="cantidad_comprobantes" id="cantidad_comprobantes" class="form-control" value="<?= $solicitud['cantidad_comprobantes'] ?>">
            </div>
        </div>

        <div class="mb-3">
            <label for="observaciones" class="form-label">Observaciones</label>
            <textarea name="observaciones" id="observaciones" class="form-control"><?= htmlspecialchars($solicitud['observaciones']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        <a href="listado_solicitudes.php" class="btn btn-secondary ms-2">
    <i class="bi bi-arrow-left"></i> Volver
</a>


    </form>

    <script>
    function mostrarOpciones() {
        var modalidad = document.getElementById('modalidad').value;
        var seccionReintegro = document.getElementById('reintegro-opciones');
        var seccionAlias = document.getElementById('alias-cbu-seccion');

        if (modalidad === 'Reintegro') {
            seccionReintegro.style.display = 'block';
            seccionAlias.style.display = 'block';
        } else if (modalidad === 'Transferencia') {
            seccionReintegro.style.display = 'none';
            seccionAlias.style.display = 'block';
        } else {
            seccionReintegro.style.display = 'none';
            seccionAlias.style.display = 'none';
        }
    }

    mostrarOpciones(); // Ejecutar al cargar

    const montoInput = new AutoNumeric('#monto', {
        decimalCharacter: ',',
        digitGroupSeparator: '.',
        decimalPlaces: 2
    });

    document.querySelector('form').addEventListener('submit', function () {
        const rawValue = montoInput.getNumber();
        document.getElementById('monto').value = rawValue;
    });
    </script>
</body>
</html>
