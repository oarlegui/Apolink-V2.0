<?php
// archivo: sesiones.php
// Listado de sesiones activas / logins registrados. Solo para admin.
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'include/conexion.php';
require 'include/seguridad.php';

require_login();
require_rol(['admin']);

$query = "SELECT sa.*, u.nombre AS usuario
          FROM sesiones_activas sa
          JOIN users u ON sa.usuario_id = u.id
          ORDER BY sa.fecha_login DESC";

$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Sesiones activas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Registro de Sesiones</h4>
  <table class="table table-bordered table-hover mt-3">
    <thead class="table-light">
      <tr>
        <th>Usuario</th>
        <th>IP</th>
        <th>Fecha de Login</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($s = $result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($s['usuario']) ?></td>
          <td><?= $s['ip'] ?></td>
          <td><?= $s['fecha_login'] ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
