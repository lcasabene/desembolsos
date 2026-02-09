<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

include 'config/database.php';

// 1. Solicitudes por estado
$stmt = $pdo->query("SELECT estado, COUNT(*) as cantidad FROM solicitudes GROUP BY estado");
$estados = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Montos por mes (últimos 6 meses)
$stmt = $pdo->query("
    SELECT DATE_FORMAT(fecha_solicitud, '%Y-%m') AS mes, SUM(monto) AS total 
    FROM solicitudes 
    GROUP BY mes 
    ORDER BY mes DESC 
    LIMIT 6
");
$montos_por_mes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Totales aprobados y rendidos
$total_aprobado = $pdo->query("SELECT SUM(monto) FROM solicitudes WHERE estado = 'Aprobado'")->fetchColumn() ?: 0;
$total_rendido = $pdo->query("SELECT SUM(monto) FROM solicitudes WHERE estado IN ('Rendido', 'Finalizado')")->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Estadístico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="container mt-5">
    <h2>Dashboard Estadístico</h2>
    <a href="menu.php" class="btn btn-secondary mb-3">Volver al Menú</a>

    <div class="row">
        <div class="col-md-6">
            <canvas id="estadoChart"></canvas>
        </div>
        <div class="col-md-6">
            <canvas id="montoMesChart"></canvas>
        </div>
    </div>

    <div class="mt-5">
        <h4>Total Aprobado: $<?= number_format($total_aprobado, 2) ?></h4>
        <h4>Total Rendido: $<?= number_format($total_rendido, 2) ?></h4>
    </div>

    <script>
        // Chart de estados
        const estadoCtx = document.getElementById('estadoChart').getContext('2d');
        const estadoChart = new Chart(estadoCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_keys($estados)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($estados)) ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ]
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Solicitudes por Estado'
                    }
                }
            }
        });

        // Chart de montos por mes
        const montoCtx = document.getElementById('montoMesChart').getContext('2d');
        const montoChart = new Chart(montoCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($montos_por_mes)) ?>,
                datasets: [{
                    label: 'Monto Solicitado',
                    data: <?= json_encode(array_values($montos_por_mes)) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)'
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Montos Solicitados por Mes'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
