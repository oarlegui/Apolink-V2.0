<?php
// archivo: aportes/agregar_aporte_gira.php
session_start();
require '../include/conexion.php';
require '../include/seguridad.php';

require_login();
require_rol(['tesorero']);

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alumno_id = $_POST['alumno_id'];
    $monto = $_POST['monto'];

    $stmt = $conn->prepare("INSERT INTO aportes_gira (alumno_id, monto) VALUES (?, ?)");
    $stmt->bind_param("ii", $alumno_id, $monto);
    $stmt->execute();

    $mensaje = "Aporte registrado correctamente.";
}

// Obtener alumnos del tesorero
$alumnos = $conn->query("
  SELECT s.id, s.nombre, c.nombre AS curso
  FROM students s
  JOIN cursos c ON s.curso_id = c.id
  WHERE s.curso_id IN (
    SELECT curso_id FROM user_curso WHERE user_id = {$_SESSION['usuario_id']}
  )
  ORDER BY s.nombre
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registrar Aporte de Gira</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Registrar Aporte de Gira</h4>

  <?php if ($mensaje): ?>
    <div class="alert alert-success"><?= $mensaje ?></div>
  <?php endif; ?>

  <form method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Alumno</label>
      <select name="alumno_id" class="form-select" required>
        <option value="">Selecciona un alumno</option>
        <?php while ($a = $alumnos->fetch_assoc()): ?>
          <option value="<?= $a['id'] ?>"><?= $a['nombre'] ?> (<?= $a['curso'] ?>)</option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Monto del aporte</label>
      <input type="number" name="monto" class="form-control" required min="1">
    </div>
    <button class="btn btn-success">Registrar Aporte</button>
  </form>
</div>
</body>
</html>
