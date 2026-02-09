<?php
session_start();
require_once '../config/database.php';
require_once 'includes/header.php';

$usuario_id = $_SESSION['user_id'] ?? null;
if (!$usuario_id) {
    echo "<div class='alert alert-danger'>Sesión no iniciada.</div>";
    exit;
}

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$stmt = $pdo->prepare("SELECT fecha, hora_entrada, hora_salida FROM asistencias WHERE usuario_id = ? AND fecha BETWEEN ? AND ? ORDER BY fecha ASC");
$stmt->execute([$usuario_id, $desde, $hasta]);

$registros = [];
$total_segundos = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $entrada = $row['hora_entrada'];
    $salida = $row['hora_salida'];
    $fecha = $row['fecha'];

    $horas = '';
    if ($entrada && $salida) {
        $t1 = strtotime($entrada);
        $t2 = strtotime($salida);
        $segundos = $t2 - $t1;
        $total_segundos += $segundos;
        $horas = gmdate('H:i:s', $segundos);
    }

    $registros[] = [
        'fecha' => $fecha,
        'entrada' => $entrada,
        'salida' => $salida,
        'horas' => $horas
    ];
}
?>

<div class="container mt-5">
    <h4>Resumen de Asistencias</h4>
    <form class="form-inline mb-3" method="get">
        <label for="desde">Desde:</label>
        <input type="date" name="desde" value="<?= $desde ?>" class="form-control mx-2">
        <label for="hasta">Hasta:</label>
        <input type="date" name="hasta" value="<?= $hasta ?>" class="form-control mx-2">
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </form>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Horas Trabajadas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($registros as $r): ?>
            <tr>
                <td><?= $r['fecha'] ?></td>
                <td><?= $r['entrada'] ?></td>
                <td><?= $r['salida'] ?></td>
                <td><?= $r['horas'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h5>Total de horas trabajadas: <?= gmdate('H:i:s', $total_segundos) ?></h5>
</div>

<?php require_once 'includes/footer.php'; ?>
