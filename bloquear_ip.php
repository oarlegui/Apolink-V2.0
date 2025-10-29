<?php
// archivo: bloquear_ip.php
// Permite al admin registrar IPs bloqueadas manualmente

session_start();
require 'include/conexion.php';
require 'include/seguridad.php';

require_login();
require_rol(['admin']);

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_POST['ip'];
    $motivo = $_POST['motivo'];

    $stmt = $conn->prepare("INSERT INTO bloqueos_ip (ip, motivo) VALUES (?, ?)");
    $stmt->bind_param("ss", $ip, $motivo);
    $stmt->execute();

    $mensaje = "IP $ip bloqueada correctamente.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Bloquear IP</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Bloquear IP manualmente</h4>
  <?php if ($mensaje): ?>
    <div class="alert alert-success"><?= $mensaje ?></div>
  <?php endif; ?>
  <form method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">IP a bloquear</label>
      <input type="text" name="ip" class="form-control" required placeholder="Ej: 123.45.67.89">
    </div>
    <div class="mb-3">
      <label class="form-label">Motivo</label>
      <textarea name="motivo" class="form-control" required></textarea>
    </div>
    <button class="btn btn-danger">Bloquear IP</button>
  </form>
</div>
</body>
</html>
