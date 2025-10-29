<?php
// archivo: agregar_aporte_gira.php
session_start();
require 'include/conexion.php';
require 'include/seguridad.php';

require_login();
require_rol(['tesorero']);

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $alumno_id = $_POST['alumno_id'];
  $monto = $_POST['monto'];

  $stmt = $conn->prepare("INSERT INTO aportes_gira (alumno_id, monto) VALUES (?, ?)");
  $stmt->bind_param("ii", $alumno_id, $monto);
  $stmt->execute();
  $mensaje = "✅ Aporte registrado correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Aporte Gira - Apolink</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>🎒 Registrar Aporte para Gira de Estudios</h4>

  <?php if ($mensaje): ?>
    <div class="alert alert-success"><?= $mensaje ?></div>
  <?php endif; ?>

  <form method="POST" class="card p-4 shadow-sm mt-3">
    <div class="mb-3">
      <label class="form-label">Alumno</label>
      <select name="alumno_id" class="form-select" required>
        <option value="">Seleccionar alumno</option>
        <?php
        $res = $conn->query("
          SELECT s.id, s.nombre, c.nombre AS curso
          FROM students s
          JOIN cursos c ON s.curso_id = c.id
          ORDER BY c.nombre, s.nombre
        ");
        while ($row = $res->fetch_assoc()):
        ?>
          <option value="<?= $row['id'] ?>">
            <?= $row['nombre'] ?> (<?= $row['curso'] ?>)
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Monto</label>
      <input type="number" name="monto" class="form-control" required>
    </div>
    <button class="btn btn-primary">Registrar Aporte</button>
  </form>
</div>
</body>
</html>
