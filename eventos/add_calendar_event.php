<?php
// archivo: add_calendar_event.php
// Formulario para agregar eventos. Solo accesible por admin y admin_curso.

session_start();
require '../include/conexion.php';
require '../include/get_assigned_courses.php';
require '../include/log_auditoria.php';
require '../include/seguridad.php';

require_login();
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

if (!in_array($rol, ['admin', 'admin_curso'])) {
    die("Acceso denegado.");
}

$cursos = get_assigned_courses($conn, $usuario_id, $rol);
$query = "SELECT id, nombre FROM cursos WHERE id IN (" . implode(',', $cursos) . ") ORDER BY nombre";
$result = $conn->query($query);
$cursos_disponibles = $result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha = $_POST['fecha'];
    $curso_id = $_POST['curso_id'];

    $stmt = $conn->prepare("INSERT INTO eventos (curso_id, titulo, descripcion, fecha, creado_por) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $curso_id, $titulo, $descripcion, $fecha, $usuario_id);
    $stmt->execute();

    log_accion($conn, $usuario_id, 'crear', 'eventos', "Agregó evento '$titulo' al curso ID $curso_id");

    header("Location: calendar.php?ok=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Evento</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Agregar Evento</h4>
  <form method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Curso</label>
      <select name="curso_id" class="form-select" required>
        <option value="">Seleccione curso</option>
        <?php foreach ($cursos_disponibles as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Título</label>
      <input type="text" name="titulo" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Fecha</label>
      <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Descripción</label>
      <textarea name="descripcion" class="form-control" rows="4"></textarea>
    </div>
    <button class="btn btn-success">Guardar Evento</button>
  </form>
</div>
</body>
</html>
