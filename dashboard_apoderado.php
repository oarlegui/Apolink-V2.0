<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'include/conexion.php';
require 'include/seguridad.php';
require_login();
require_rol(['apoderado']);


$rol = $_SESSION['rol'];
if ($rol !== 'apoderado') {
  die("Acceso restringido");
}

$apoderado_id = $_SESSION['usuario_id'];

// Obtener info de pagos del apoderado
$stmt = $conn->prepare("
  SELECT s.cuota_total, 
         IFNULL(SUM(p.monto), 0) AS pagado
  FROM students s
  JOIN alumno_apoderado aa ON s.id = aa.alumno_id
  LEFT JOIN pagos p ON p.alumno_id = s.id
  WHERE aa.apoderado_id = ?
");
$stmt->bind_param("i", $apoderado_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

$cuota_total = (int) $data['cuota_total'];
$pagado = (int) $data['pagado'];
$pendiente = max($cuota_total - $pagado, 0);
$porcentaje = $cuota_total > 0 ? round(($pagado / $cuota_total) * 100) : 0;

// Gastos y pagos totales del curso
$stmt = $conn->prepare("
  SELECT
    (SELECT IFNULL(SUM(monto),0) FROM pagos WHERE curso_id = s.curso_id) AS total_pagos,
    (SELECT IFNULL(SUM(monto),0) FROM gastos WHERE curso_id = s.curso_id) AS total_gastos
  FROM students s
  JOIN alumno_apoderado aa ON aa.alumno_id = s.id
  WHERE aa.apoderado_id = ?
  LIMIT 1
");
$stmt->bind_param("i", $apoderado_id);
$stmt->execute();
$curso_data = $stmt->get_result()->fetch_assoc();

// Año anterior vs actual
$current_year = date('Y');
$last_year = $current_year - 1;

$stmt = $conn->prepare("
  SELECT
    (SELECT IFNULL(SUM(monto),0) FROM pagos WHERE curso_id = s.curso_id AND YEAR(fecha_pago) = ?) AS pagos_anterior,
    (SELECT IFNULL(SUM(monto),0) FROM pagos WHERE curso_id = s.curso_id AND YEAR(fecha_pago) = ?) AS pagos_actual
  FROM students s
  JOIN alumno_apoderado aa ON aa.alumno_id = s.id
  WHERE aa.apoderado_id = ?
  LIMIT 1
");
$stmt->bind_param("iii", $last_year, $current_year, $apoderado_id);
$stmt->execute();
$anio_data = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mi Panel - Apolink</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-4">
  <h4 class="mb-3">📊 Mi Panel Apoderado</h4>

  <!-- Botones rápidos -->
  <div class="d-flex flex-wrap gap-2 mb-4">
    <a href="pagos.php" class="btn btn-success">💳 Ver mis pagos</a>
    <a href="eventos/calendar.php" class="btn btn-primary">📅 Ver eventos</a>
    <a href="empresas/empresas.php" class="btn btn-warning">🏪 Servicios útiles</a>
  </div>

  <!-- Estadísticas -->
  <div class="row mb-4">
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6 class="text-muted">Total Cuota</h6><strong>$<?= number_format($cuota_total, 0, ',', '.') ?></strong>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6 class="text-muted">Pagado</h6><strong>$<?= number_format($pagado, 0, ',', '.') ?></strong>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6 class="text-muted">Pendiente</h6><strong>$<?= number_format($pendiente, 0, ',', '.') ?></strong>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6 class="text-muted">Progreso</h6><strong><?= $porcentaje ?>%</strong>
    </div></div></div>
  </div>

  <!-- Gráfico circular estado de pago -->
  <div class="card shadow-sm p-4 mb-4">
    <h6>Estado de tu cuota del curso</h6>
    <canvas id="graficoCuota" height="150"></canvas>
  </div>

  <!-- Gráfico gastos vs pagos -->
  <div class="card shadow-sm p-4 mb-4">
    <h6>Gastos vs Pagos del curso</h6>
    <canvas id="graficoCurso"></canvas>
  </div>

  <!-- Gráfico comparativo año anterior vs actual -->
  <div class="card shadow-sm p-4 mb-4">
    <h6>Comparación de pagos: <?= $last_year ?> vs <?= $current_year ?></h6>
    <canvas id="graficoComparativo"></canvas>
  </div>
</div>

<script>
const grafico1 = new Chart(document.getElementById('graficoCuota').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels: ['Pagado', 'Pendiente'],
    datasets: [{
      data: [<?= $pagado ?>, <?= $pendiente ?>],
      backgroundColor: ['#198754', '#DC3545']
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom' } }
  }
});

const grafico2 = new Chart(document.getElementById('graficoCurso').getContext('2d'), {
  type: 'bar',
  data: {
    labels: ['Pagos recibidos', 'Gastos realizados'],
    datasets: [{
      label: 'Monto ($)',
      data: [<?= $curso_data['total_pagos'] ?>, <?= $curso_data['total_gastos'] ?>],
      backgroundColor: ['#198754', '#DC3545']
    }]
  }
});

const grafico3 = new Chart(document.getElementById('graficoComparativo').getContext('2d'), {
  type: 'bar',
  data: {
    labels: ['<?= $last_year ?>', '<?= $current_year ?>'],
    datasets: [{
      label: 'Pagos por año',
      data: [<?= $anio_data['pagos_anterior'] ?>, <?= $anio_data['pagos_actual'] ?>],
      backgroundColor: ['#6c757d', '#0d6efd']
    }]
  }
});
</script>
</body>
</html>
