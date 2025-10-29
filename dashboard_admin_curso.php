<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'include/conexion.php';
require 'include/seguridad.php';

require_login();
require_rol(['admin_curso', 'tesorero']);

$usuario_id = $_SESSION['usuario_id'];

// Obtener cursos asignados
$stmt1 = $conn->prepare("SELECT curso_id FROM user_curso WHERE user_id = ?");
$stmt1->bind_param("i", $usuario_id);
$stmt1->execute();
$curso_ids = array_column($stmt1->get_result()->fetch_all(MYSQLI_NUM), 0);
$stmt1->close();

if (empty($curso_ids)) {
    die("No tiene cursos asignados");
}
$curso_list = implode(',', $curso_ids);

// Resumen general
$resumen = $conn->query("
  SELECT
    (SELECT COUNT(*) FROM students WHERE curso_id IN ($curso_list)) AS total_alumnos,
    (SELECT COUNT(DISTINCT aa.apoderado_id)
     FROM alumno_apoderado aa
     JOIN students s ON s.id = aa.alumno_id
     WHERE s.curso_id IN ($curso_list)) AS total_apoderados,
    (SELECT IFNULL(SUM(monto),0) FROM pagos WHERE curso_id IN ($curso_list)) AS total_pagos,
    (SELECT IFNULL(SUM(monto),0) FROM gastos WHERE curso_id IN ($curso_list)) AS total_gastos
")->fetch_assoc();

$saldo = $resumen['total_pagos'] - $resumen['total_gastos'];

// Datos mensuales
function getDataMensual($conn, $tabla, $campo_fecha, $curso_list) {
    $query = "
      SELECT MONTH($campo_fecha) AS mes, SUM(monto) AS total
      FROM $tabla
      WHERE curso_id IN ($curso_list) AND YEAR($campo_fecha) = YEAR(NOW())
      GROUP BY mes
    ";
    $result = $conn->query($query);
    $data = array_fill(1, 12, 0);
    while ($row = $result->fetch_assoc()) {
        $data[(int)$row['mes']] = (int)$row['total'];
    }
    return $data;
}

$pagos_mes = getDataMensual($conn, 'pagos', 'fecha_pago', $curso_list);
$gastos_mes = getDataMensual($conn, 'gastos', 'fecha', $curso_list);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Admin Curso - Apolink</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { display: flex; }
    .sidebar {
      width: 250px;
      min-height: 100vh;
      background-color: #f8f9fa;
      padding: 20px;
      border-right: 1px solid #dee2e6;
    }
    .main {
      flex: 1;
      padding: 30px;
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <h5 class="mb-4">📘 Apolink</h5>
  <a href="dashboard_admin_curso.php" class="btn btn-outline-primary w-100 mb-2">🏠 Inicio</a>
  <a href="alumnos/students.php" class="btn btn-outline-primary w-100 mb-2">👩‍🎓 Alumnos</a>
  <a href="apoderados/apoderados.php" class="btn btn-outline-primary w-100 mb-2">👨‍👧 Apoderados</a>
  <a href="eventos/calendar.php" class="btn btn-outline-primary w-100 mb-2">📅 Eventos</a>
  <a href="empresas/empresas.php" class="btn btn-outline-primary w-100 mb-2">🏪 Servicios útiles</a>
  <a href="logout.php" class="btn btn-outline-danger w-100 mt-3">🚪 Cerrar sesión</a>
</div>

<!-- Contenido -->
<div class="main">
  <h4>📚 Panel Administrador de Curso</h4>

  <!-- Tarjetas -->
  <div class="row mt-4 mb-4">
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6>Alumnos</h6><strong><?= $resumen['total_alumnos'] ?></strong>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6>Apoderados</h6><strong><?= $resumen['total_apoderados'] ?></strong>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6>Pagos</h6><strong>$<?= number_format($resumen['total_pagos'], 0, ',', '.') ?></strong>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6>Saldo</h6><strong>$<?= number_format($saldo, 0, ',', '.') ?></strong>
    </div></div></div>
  </div>

  <!-- Gráfico mensual -->
  <div class="card shadow-sm p-4 mb-4">
    <h6>📈 Ingresos vs Egresos Mensuales</h6>
    <canvas id="graficoMensual"></canvas>
  </div>

  <!-- Tabla alumnos y apoderados -->
  <div class="card shadow-sm p-4 mb-5">
    <h6>👨‍🎓 Alumnos y sus Apoderados</h6>
    <div class="table-responsive">
      <table class="table table-bordered table-hover mt-3">
        <thead class="table-light">
          <tr>
            <th>Alumno</th>
            <th>Apoderado(s)</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $query = "
          SELECT s.id AS alumno_id, s.nombre AS alumno,
            GROUP_CONCAT(u.nombre SEPARATOR ', ') AS apoderados
          FROM students s
          JOIN alumno_apoderado aa ON s.id = aa.alumno_id
          JOIN users u ON aa.apoderado_id = u.id
          WHERE s.curso_id IN ($curso_list)
          GROUP BY s.id
          ORDER BY s.nombre
        ";
        $result = $conn->query($query);
        while ($row = $result->fetch_assoc()):
        ?>
          <tr>
            <td><?= htmlspecialchars($row['alumno']) ?></td>
            <td><?= htmlspecialchars($row['apoderados']) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const ctx = document.getElementById('graficoMensual').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
    datasets: [
      {
        label: 'Pagos',
        data: <?= json_encode(array_values($pagos_mes)) ?>,
        backgroundColor: '#198754'
      },
      {
        label: 'Gastos',
        data: <?= json_encode(array_values($gastos_mes)) ?>,
        backgroundColor: '#DC3545'
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom' }
    }
  }
});
</script>
</body>
</html>
