<?php
// archivo: ips_bloqueadas.php
// Lista y permite eliminar IPs bloqueadas. Solo para admin.

session_start();
require 'include/conexion.php';
require 'include/seguridad.php';

require_login();
require_rol(['admin']);

if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $conn->prepare("DELETE FROM bloqueos_ip WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: ips_bloqueadas.php?ok=1");
    exit;
}

$result = $conn->query("SELECT * FROM bloqueos_ip ORDER BY fecha_bloqueo DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>IPs bloqueadas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>IPs Bloqueadas</h4>

  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">IP desbloqueada correctamente.</div>
  <?php endif; ?>

  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>IP</th>
        <th>Motivo</th>
        <th>Fecha</th>
        <th>Acción</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($ip = $result->fetch_assoc()): ?>
        <tr>
          <td><?= $ip['ip'] ?></td>
          <td><?= htmlspecialchars($ip['motivo']) ?></td>
          <td><?= $ip['fecha_bloqueo'] ?></td>
          <td>
            <a href="ips_bloqueadas.php?eliminar=<?= $ip['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar esta IP?')">Eliminar</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
