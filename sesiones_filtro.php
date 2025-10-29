<?php
// archivo: sesiones_filtro.php
session_start();
require '../include/conexion.php';
require '../include/seguridad.php';

require_login();
require_rol(['admin']);

$busqueda = $_GET['buscar'] ?? '';

$stmt = $conn->prepare("
  SELECT s.*, u.nombre AS usuario
  FROM sesiones_activas s
  JOIN users u ON s.usuario_id = u.id
  WHERE u.nombre LIKE ? OR s.ip LIKE ?
  ORDER BY s.fecha_login DESC
");

$term = "%$busqueda%";
$stmt->bind_param("ss", $term, $term);
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Buscar Sesiones Activas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>🔍 Buscar Sesiones Activas</h4>

  <form method="GET" class="mb-3">
    <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>" class="form-control" placeholder="Buscar por nombre de usuario o IP">
  </form>

  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>Usuario</th>
        <th>IP</th>
        <th>Fecha de Login</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['usuario']) ?></td>
          <td><?= $row['ip'] ?></td>
          <td><?= $row['fecha_login'] ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
