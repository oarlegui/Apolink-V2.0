<?php
// archivo: reporte_modulos_activos.php
// Muestra el estado de cada módulo por colegio y curso (solo admin)

session_start();
require '../include/conexion.php';
require '../include/seguridad.php';

require_login();
require_rol(['admin']);

$query = "
  SELECT 
    col.nombre AS colegio,
    cur.nombre AS curso,
    cm.modulo,
    cm.activo
  FROM curso_modulo cm
  JOIN cursos cur ON cm.curso_id = cur.id
  JOIN colegios col ON cm.colegio_id = col.id
  ORDER BY col.nombre, cur.nombre, cm.modulo
";

$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de Módulos por Curso</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>📋 Reporte de Módulos Activos por Curso</h4>
  <p class="text-muted">Visualización completa por colegio y curso</p>

  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>Colegio</th>
        <th>Curso</th>
        <th>Módulo</th>
        <th>Estado</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($m = $result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($m['colegio']) ?></td>
          <td><?= htmlspecialchars($m['curso']) ?></td>
          <td><?= ucfirst($m['modulo']) ?></td>
          <td>
            <?= $m['activo'] ? '✅ Activo' : '❌ Inactivo' ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
