<?php
// archivo: edit_student.php
// Permite editar los datos de un alumno. Solo accesible para admin y admin_curso.

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

// Obtener alumno
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();

if (!$alumno) {
    echo "Alumno no encontrado.";
    exit;
}

// Verificar si el usuario tiene permiso sobre el curso del alumno
$cursos_asignados = get_assigned_courses($conn, $usuario_id, $rol);
if (!in_array($alumno['curso_id'], $cursos_asignados) && $rol !== 'admin') {
    echo "No autorizado.";
    exit;
}

$query = ($rol === 'admin') ?
    "SELECT c.id, c.nombre, col.nombre AS colegio FROM cursos c JOIN colegios col ON c.colegio_id = col.id ORDER BY col.nombre, c.nombre"
  : "SELECT c.id, c.nombre, col.nombre AS colegio FROM cursos c JOIN colegios col ON c.colegio_id = col.id WHERE c.id IN (" . implode(',', $cursos_asignados) . ") ORDER BY col.nombre, c.nombre";

$cursos = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $rut = $_POST['rut'];
    $fecha_nac = $_POST['fecha_nac'];
    $curso_id = $_POST['curso_id'];
    $colegio_id = $_POST['colegio_id'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    $stmt = $conn->prepare("UPDATE students SET nombre=?, rut=?, fecha_nac=?, curso_id=?, colegio_id=?, telefono=?, email=? WHERE id=?");
    $stmt->bind_param("sssiiisi", $nombre, $rut, $fecha_nac, $curso_id, $colegio_id, $telefono, $email, $id);
    $stmt->execute();

    log_accion($conn, $usuario_id, 'editar', 'alumnos', "Editó alumno ID $id: $nombre");

    header("Location: students.php?editado=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Alumno</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Editar Alumno</h4>
  <form method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label>Nombre Completo</label>
      <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($alumno['nombre']) ?>" required>
    </div>
    <div class="mb-3">
      <label>RUT</label>
      <input type="text" name="rut" class="form-control" value="<?= htmlspecialchars($alumno['rut']) ?>" required>
    </div>
    <div class="mb-3">
      <label>Fecha de Nacimiento</label>
      <input type="date" name="fecha_nac" class="form-control" value="<?= $alumno['fecha_nac'] ?>" required>
    </div>
    <div class="mb-3">
      <label>Curso</label>
      <select name="curso_id" class="form-select" required>
        <?php foreach ($cursos as $c): ?>
          <option value="<?= $c[0] ?>" <?= $alumno['curso_id'] == $c[0] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c[2]) ?> - <?= htmlspecialchars($c[1]) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label>ID Colegio</label>
      <input type="number" name="colegio_id" class="form-control" value="<?= $alumno['colegio_id'] ?>" required>
    </div>
    <div class="mb-3">
      <label>Teléfono</label>
      <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($alumno['telefono']) ?>">
    </div>
    <div class="mb-3">
      <label>Email</label>
      <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($alumno['email']) ?>">
    </div>
    <button class="btn btn-primary">Actualizar Alumno</button>
  </form>
</div>
</body>
</html>