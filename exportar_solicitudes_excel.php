<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config/database.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=solicitudes_" . date('Ymd_His') . ".xls");

echo "<table border='1'>";
echo "<tr>
    <th>ID</th>
    <th>Fecha</th>
    <th>Departamento</th>
    <th>Monto</th>
    <th>Fecha Disponibilidad</th>
    <th>Modalidad</th>
    <th>Alias/CBU</th>
    <th>Observaciones</th>
    <th>Estado</th>
</tr>";

$sql = "SELECT s.*, d.nombre AS departamento FROM solicitudes s
        LEFT JOIN departamentos d ON s.departamento_id = d.id
        ORDER BY s.fecha_solicitud DESC";
$stmt = $pdo->query($sql);

while ($row = $stmt->fetch()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['fecha_solicitud']}</td>";
    echo "<td>" . htmlspecialchars($row['departamento']) . "</td>";
    echo "<td style='text-align:right'>" . number_format($row['monto'], 2, ',', '.') . "</td>";
    echo "<td>{$row['fecha_disponibilidad']}</td>";
    echo "<td>{$row['modalidad']}</td>";
    echo "<td>{$row['alias_cbu']}</td>";
    echo "<td>" . htmlspecialchars($row['observaciones']) . "</td>";
    echo "<td>{$row['estado']}</td>";
    echo "</tr>";
}
echo "</table>";
