<?php
// archivo: student_detail.php
// Muestra información ampliada de un alumno (historial y apoderados).

session_start();
require '../include/conexion.php';
require '../include/get_assigned_courses.php';
require '../include/seguridad.php';

require_login();
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$id = $_GET['id'] ?? null;

if (!$id) {
    echo "ID no válido.";
    exit;
}

$stmt = $conn->prepare("SELECT s.*, c.nombre AS curso_nombre, col.nombre AS colegio_nombre
                        FROM students s
                        JOIN cursos c ON s.curso_id = c.id
                        JOIN colegios col ON s.colegio_id = col.id
                        WHERE s.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();

if (!$alumno) {
    echo "Alumno no encontrado.";
    exit;
}

$permitido = false;
if ($rol === 'admin') {
    $permitido = true;
} elseif ($rol === 'apoderado') {
    $stmt = $conn->prepare("SELECT 1 FROM alumno_apoderado WHERE alumno_id = ? AND apoderado_id = ?");
    $stmt->bind_param("ii", $id, $usuario_id);
    $stmt->execute();
    $permitido = $stmt->get_result()->num_rows > 0;
} elseif (in_array($rol, ['admin_curso', 'tesorero'])) {
    $cursos = get_assigned_courses($conn, $usuario_id, $rol);
    $permitido = in_array($alumno['curso_id'], $cursos);
}

if (!$permitido) {
    echo "No tienes permiso para ver este alumno.";
    exit;
}

$stmt = $conn->prepare("SELECT h.anio, c.nombre AS curso FROM historial_alumno h
                        JOIN cursos c ON h.curso_id = c.id
                        WHERE h.alumno_id = ?
                        ORDER BY h.anio DESC");
$stmt->bind_param("i", $id);
$stmt->execute();
$historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT u.nombre, u.rut FROM users u
                        JOIN alumno_apoderado aa ON u.id = aa.apoderado_id
                        WHERE aa.alumno_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$apoderados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Detalle del Alumno</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Ficha del Alumno</h4>
  <div class="card mb-4">
    <div class="card-body">
      <p><strong>Nombre:</strong> <?= htmlspecialchars($alumno['nombre']) ?></p>
      <p><strong>RUT:</strong> <?= htmlspecialchars($alumno['rut']) ?></p>
      <p><strong>Colegio:</strong> <?= htmlspecialchars($alumno['colegio_nombre']) ?></p>
      <p><strong>Curso actual:</strong> <?= htmlspecialchars($alumno['curso_nombre']) ?></p>
    </div>
  </div>

  <h5>Historial Académico</h5>
  <ul class="list-group mb-4">
    <?php foreach ($historial as $h): ?>
      <li class="list-group-item"><?= $h['anio'] ?> - <?= htmlspecialchars($h['curso']) ?></li>
    <?php endforeach; ?>
    <?php if (empty($historial)): ?>
      <li class="list-group-item text-muted">Sin historial disponible.</li>
    <?php endif; ?>
  </ul>

  <h5>Apoderados Asignados</h5>
  <ul class="list-group">
    <?php foreach ($apoderados as $a): ?>
      <li class="list-group-item"><?= htmlspecialchars($a['nombre']) ?> (<?= $a['rut'] ?>)</li>
    <?php endforeach; ?>
    <?php if (empty($apoderados)): ?>
      <li class="list-group-item text-muted">Sin apoderados asignados.</li>
    <?php endif; ?>
  </ul>
</div>
</body>
</html>