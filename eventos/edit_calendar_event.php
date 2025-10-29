<?php
// archivo: edit_calendar_event.php
// Permite editar un evento del calendario. Solo accesible por admin y admin_curso.

session_start();
require '../include/conexion.php';
require '../include/log_auditoria.php';
require '../include/get_assigned_courses.php';
require '../include/seguridad.php';

require_login();
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$id = $_GET['id'] ?? null;

if (!$id || !in_array($rol, ['admin', 'admin_curso'])) {
    die("Acceso denegado.");
}

$stmt = $conn->prepare("SELECT * FROM eventos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();

if (!$evento) {
    echo "Evento no encontrado.";
    exit;
}

// Cursos asignados
$cursos = get_assigned_courses($conn, $usuario_id, $rol);
$query = "SELECT id, nombre FROM cursos WHERE id IN (" . implode(',', $cursos) . ") ORDER BY nombre";
$result = $conn->query($query);
$cursos_disponibles = $result->fetch_all(MYSQLI_ASSOC);

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $curso_id = $_POST['curso_id'];
    $titulo = $_POST['titulo'];
    $fecha = $_POST['fecha'];
    $descripcion = $_POST['descripcion'];

    $stmt = $conn->prepare("UPDATE eventos SET curso_id=?, titulo=?, fecha=?, descripcion=? WHERE id=?");
    $stmt->bind_param("isssi", $curso_id, $titulo, $fecha, $descripcion, $id);
    $stmt->execute();

    log_accion($conn, $usuario_id, 'editar', 'eventos', "Editó evento ID $id: $titulo");

    header("Location: calendar.php?editado=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Evento</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Editar Evento</h4>
  <form method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Curso</label>
      <select name="curso_id" class="form-select" required>
        <?php foreach ($cursos_disponibles as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $evento['curso_id'] == $c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Título</label>
      <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($evento['titulo']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Fecha</label>
      <input type="date" name="fecha" class="form-control" value="<?= $evento['fecha'] ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Descripción</label>
      <textarea name="descripcion" class="form-control" rows="4"><?= htmlspecialchars($evento['descripcion']) ?></textarea>
    </div>
    <button class="btn btn-primary">Actualizar Evento</button>
  </form>
</div>
</body>
</html>
