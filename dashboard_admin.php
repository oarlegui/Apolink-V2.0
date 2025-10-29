<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'include/conexion.php';
require 'include/seguridad.php';
require_login();
require_rol(['admin']);


// Conteos generales
$resumen = $conn->query("
  SELECT 
    (SELECT COUNT(*) FROM colegios) AS total_colegios,
    (SELECT COUNT(*) FROM cursos) AS total_cursos,
    (SELECT COUNT(*) FROM students) AS total_alumnos,
    (SELECT COUNT(*) FROM users) AS total_usuarios
")->fetch_assoc();

// Módulos activos por curso
$modulos = ['alumnos','apoderados','pagos','gastos','eventos','reportes','empresas'];
$modulo_data = [];

foreach ($modulos as $m) {
  $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM curso_modulo WHERE modulo = ? AND activo = 1");
  $stmt->bind_param("s", $m);
  $stmt->execute();
  $modulo_data[$m] = $stmt->get_result()->fetch_assoc()['total'];
}

// Alumnos registrados por mes
$alumnos_mes = array_fill(1, 12, 0);
$res = $conn->query("SELECT MONTH(fecha_nac) as mes, COUNT(*) as total FROM students GROUP BY mes");
while ($row = $res->fetch_assoc()) {
  $alumnos_mes[(int)$row['mes']] = (int)$row['total'];
}

// Últimas acciones de auditoría
$audi = $conn->query("
  SELECT a.fecha, u.nombre AS usuario, a.accion, a.modulo, a.detalle
  FROM auditoria a 
  JOIN users u ON a.usuario_id = u.id
  ORDER BY a.fecha DESC
  LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Admin | Apolink</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-4">
  <h4>🧭 Panel de Control Global - Admin</h4>

  <!-- Tarjetas resumen -->
  <div class="row mt-4 mb-4">
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6>Colegios</h6><strong><?= $resumen['total_colegios'] ?></strong>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6>Cursos</h6><strong><?= $resumen['total_cursos'] ?></strong>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6>Alumnos</h6><strong><?= $resumen['total_alumnos'] ?></strong>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center shadow-sm"><div class="card-body">
      <h6>Usuarios</h6><strong><?= $resumen['total_usuarios'] ?></strong>
    </div></div></div>
  </div>

  <!-- Gráfico de módulos activos -->
  <div class="card shadow-sm p-4 mb-4">
    <h6>🧩 Módulos activos por curso</h6>
    <canvas id="graficoModulos"></canvas>
  </div>

  <!-- Gráfico alumnos por mes -->
  <div class="card shadow-sm p-4 mb-4">
    <h6>📈 Alumnos registrados por mes</h6>
    <canvas id="graficoAlumnos"></canvas>
  </div>

  <!-- Auditoría -->
  <div class="card shadow-sm p-4 mb-5">
    <h6>📋 Últimas acciones registradas</h6>
    <div class="table-responsive">
      <table class="table table-bordered table-hover mt-3">
        <thead class="table-light">
          <tr>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Acción</th>
            <th>Módulo</th>
            <th>Detalle</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $audi->fetch_assoc()): ?>
            <tr>
              <td><?= $row['fecha'] ?></td>
              <td><?= htmlspecialchars($row['usuario']) ?></td>
              <td><?= $row['accion'] ?></td>
              <td><?= $row['modulo'] ?></td>
              <td><?= htmlspecialchars($row['detalle']) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Accesos rápidos -->
  <div class="card shadow-sm p-4 mb-5">
    <h6>🚀 Accesos rápidos</h6>
    <div class="d-flex flex-wrap gap-2">
      <a href="colegios.php" class="btn btn-outline-primary">🏫 Crear Colegio</a>
      <a href="cursos.php" class="btn btn-outline-primary">🏷️ Crear Curso</a>
      <a href="usuarios.php" class="btn btn-outline-primary">👤 Crear Usuario</a>
      <a href="modulos.php" class="btn btn-outline-secondary">🧩 Módulos por curso</a>
      <a href="auditoria.php" class="btn btn-outline-secondary">📋 Ver Auditoría</a>
      <a href="ips_bloqueadas.php" class="btn btn-outline-danger">🚫 IPs Bloqueadas</a>
    </div>
  </div>
</div>

<script>
new Chart(document.getElementById('graficoModulos').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map('ucfirst', array_keys($modulo_data))) ?>,
    datasets: [{
      label: 'Cursos con módulo activo',
      data: <?= json_encode(array_values($modulo_data)) ?>,
      backgroundColor: '#0d6efd'
    }]
  }
});

new Chart(document.getElementById('graficoAlumnos').getContext('2d'), {
  type: 'line',
  data: {
    labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
    datasets: [{
      label: 'Alumnos registrados',
      data: <?= json_encode(array_values($alumnos_mes)) ?>,
      fill: true,
      backgroundColor: 'rgba(13,110,253,0.1)',
      borderColor: '#0d6efd'
    }]
  }
});
</script>
</body>
</html>
