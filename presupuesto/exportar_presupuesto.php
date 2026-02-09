<?php
require_once __DIR__ . '/../config/seguridad.php';
verificar_autenticacion();

require_once __DIR__ . '/../config/database.php';

// Verificar si el usuario tiene acceso al módulo de presupuesto
$modulos = $_SESSION['modulos'] ?? [];
if (!in_array('Presupuesto', $modulos) && $_SESSION['user_role'] !== 'Admin') {
    header('Location: acceso_denegado.php');
    exit;
}

$presupuesto_id = $_GET['id'] ?? 0;

try {
    // Obtener información del presupuesto
    $stmt = $pdo->prepare("SELECT * FROM vista_resumen_presupuesto_anual WHERE id = ?");
    $stmt->execute([$presupuesto_id]);
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$presupuesto) {
        header('Location: index.php?error=not_found');
        exit;
    }
    
    // Obtener detalles mensuales
    $stmt = $pdo->prepare("
        SELECT pmd.*, 
               COALESCE(SUM(ep.monto_ejecutado), 0) as monto_ejecutado,
               (pmd.presupuesto_estimado - COALESCE(SUM(ep.monto_ejecutado), 0)) as saldo_disponible,
               COALESCE(d.nombre, 'Sin Departamento') as departamento_nombre
        FROM presupuesto_mensual_detalle pmd
        LEFT JOIN ejecucion_presupuestaria ep ON pmd.id = ep.presupuesto_detalle_id
        LEFT JOIN departamentos d ON pmd.departamento_id = d.id
        WHERE pmd.presupuesto_anual_id = ?
        GROUP BY pmd.id
        ORDER BY FIELD(pmd.mes, 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'), pmd.fecha_inicio
    ");
    $stmt->execute([$presupuesto_id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por mes para resumen
    $resumen_mensual = [];
    foreach ($detalles as $detalle) {
        if (!isset($resumen_mensual[$detalle['mes']])) {
            $resumen_mensual[$detalle['mes']] = [
                'total_presupuesto' => 0,
                'total_ejecutado' => 0,
                'total_saldo' => 0,
                'actividades' => []
            ];
        }
        $resumen_mensual[$detalle['mes']]['total_presupuesto'] += $detalle['presupuesto_estimado'];
        $resumen_mensual[$detalle['mes']]['total_ejecutado'] += $detalle['monto_ejecutado'];
        $resumen_mensual[$detalle['mes']]['total_saldo'] += $detalle['saldo_disponible'];
        $resumen_mensual[$detalle['mes']]['actividades'][] = $detalle;
    }
    
} catch (PDOException $e) {
    die("Error al exportar el presupuesto: " . $e->getMessage());
}

// Configurar headers para descarga de Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="presupuesto_' . $presupuesto['anio'] . '.xlsx"');
header('Cache-Control: max-age=0');

// Usar PHPExcel (si está disponible) o generar CSV simple
// Para este ejemplo, generaremos un CSV bien formateado que Excel puede abrir
$output = fopen('php://output', 'w');

// Agregar BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezado general
fputcsv($output, ['PRESUPUESTO ANUAL - ' . $presupuesto['anio']]);
fputcsv($output, []);

// Información del presupuesto
fputcsv($output, ['INFORMACIÓN GENERAL']);
fputcsv($output, ['Año:', $presupuesto['anio']]);
fputcsv($output, ['Estado:', ucfirst(str_replace('_', ' ', $presupuesto['estado']))]);
fputcsv($output, ['Fecha Creación:', date('d/m/Y', strtotime($presupuesto['fecha_creacion']))]);
if ($presupuesto['fecha_aprobacion']) {
    fputcsv($output, ['Fecha Aprobación:', date('d/m/Y', strtotime($presupuesto['fecha_aprobacion']))]);
}
fputcsv($output, ['Creado por:', $presupuesto['creado_por_nombre']]);
fputcsv($output, []);

// Objetivos estratégicos
fputcsv($output, ['OBJETIVOS ESTRATÉGICOS']);
$objetivos_lineas = explode("\n", $presupuesto['objetivos_estrategicos'] ?? '');
foreach ($objetivos_lineas as $linea) {
    fputcsv($output, [trim($linea)]);
}
fputcsv($output, []);

// Resumen general
fputcsv($output, ['RESUMEN GENERAL']);
fputcsv($output, ['Total Presupuestado:', '$' . number_format($presupuesto['total_presupuestado'], 2, ',', '.')]);
fputcsv($output, ['Total Ejecutado:', '$' . number_format($presupuesto['total_ejecutado_anual'], 2, ',', '.')]);
fputcsv($output, ['Saldo Disponible:', '$' . number_format($presupuesto['saldo_anual_disponible'], 2, ',', '.')]);
fputcsv($output, ['Total Actividades:', $presupuesto['total_actividades_anual']]);
fputcsv($output, []);

// Resumen mensual
fputcsv($output, ['RESUMEN MENSUAL']);
fputcsv($output, ['Mes', 'Total Presupuesto', 'Total Ejecutado', 'Saldo Disponible', '% Ejecución']);

$meses_orden = [
    'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
];

foreach ($meses_orden as $mes) {
    if (isset($resumen_mensual[$mes])) {
        $datos = $resumen_mensual[$mes];
        $porcentaje = $datos['total_presupuesto'] > 0 ? 
            ($datos['total_ejecutado'] / $datos['total_presupuesto']) * 100 : 0;
        
        fputcsv($output, [
            ucfirst($mes),
            '$' . number_format($datos['total_presupuesto'], 2, ',', '.'),
            '$' . number_format($datos['total_ejecutado'], 2, ',', '.'),
            '$' . number_format($datos['total_saldo'], 2, ',', '.'),
            number_format($porcentaje, 1) . '%'
        ]);
    }
}
fputcsv($output, []);

// Detalle completo de actividades
fputcsv($output, ['DETALLE COMPLETO DE ACTIVIDADES']);
fputcsv($output, [
    'Mes',
    'Actividad',
    'Departamento',
    'Fecha Inicio',
    'Fecha Fin',
    'Presupuesto Estimado',
    'Monto Ejecutado',
    'Saldo Disponible',
    '% Ejecución',
    'Descripción'
]);

foreach ($meses_orden as $mes) {
    if (isset($resumen_mensual[$mes])) {
        foreach ($resumen_mensual[$mes]['actividades'] as $actividad) {
            $porcentaje = $actividad['presupuesto_estimado'] > 0 ? 
                ($actividad['monto_ejecutado'] / $actividad['presupuesto_estimado']) * 100 : 0;
            
            fputcsv($output, [
                ucfirst($actividad['mes']),
                $actividad['nombre_actividad'],
                $actividad['departamento_nombre'],
                $actividad['fecha_inicio'] ? date('d/m/Y', strtotime($actividad['fecha_inicio'])) : '',
                $actividad['fecha_fin'] ? date('d/m/Y', strtotime($actividad['fecha_fin'])) : '',
                '$' . number_format($actividad['presupuesto_estimado'], 2, ',', '.'),
                '$' . number_format($actividad['monto_ejecutado'], 2, ',', '.'),
                '$' . number_format($actividad['saldo_disponible'], 2, ',', '.'),
                number_format($porcentaje, 1) . '%',
                $actividad['descripcion'] ?? ''
            ]);
        }
    }
}

fputcsv($output, []);

// Información de exportación
fputcsv($output, ['INFORMACIÓN DE EXPORTACIÓN']);
fputcsv($output, ['Fecha de Exportación:', date('d/m/Y H:i:s')]);
fputcsv($output, ['Exportado por:', $_SESSION['user_name'] ?? 'Sistema']);
fputcsv($output, ['Sistema:', 'Sistema de Gestión Presupuestaria']);

fclose($output);
exit;
?>
