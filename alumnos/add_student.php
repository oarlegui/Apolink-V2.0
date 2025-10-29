<?php
// archivo: add_student.php
// Formulario para registrar un nuevo alumno. Solo accesible para admin y admin_curso.

session_start();
require '../include/conexion.php';
require '../include/log_auditoria.php';
require '../include/get_assigned_courses.php';
require '../include/seguridad.php';

require_login();
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

if (!in_array($rol, ['admin', 'admin_curso'])) {
    die("Acceso denegado.");
}

$cursos_asignados = get_assigned_courses($conn, $usuario_id, $rol);
$curso_list = implode(",", $cursos_asignados);

$query = ($rol === 'admin') ?
    "SELECT c.id, c.nombre, col.nombre AS colegio FROM cursos c JOIN colegios col ON c.colegio_id = col.id ORDER BY col.nombre, c.nombre"
  : "SELECT c.id, c.nombre, col.nombre AS colegio FROM cursos c JOIN colegios col ON c.colegio_id = col.id WHERE c.id IN ($curso_list) ORDER BY col.nombre, c.nombre";

$cursos = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $rut = $_POST['rut'];
    $fecha_nac = $_POST['fecha_nac'];
    $curso_id = $_POST['curso_id'];
    $colegio_id = $_POST['colegio_id'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    $stmt = $conn->prepare("INSERT INTO students (nombre, rut, fecha_nac, curso_id, colegio_id, telefono, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiiis", $nombre, $rut, $fecha_nac, $curso_id, $colegio_id, $telefono, $email);
    $stmt->execute();

    $alumno_id = $stmt->insert_id;
    log_accion($conn, $usuario_id, 'crear', 'alumnos', "Agregó alumno ID $alumno_id: $nombre");

    header("Location: students.php?ok=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Alumno</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Registrar Alumno</h4>
  <form method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label>Nombre Completo</label>
      <input type="text" name="nombre" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>RUT</label>
      <input type="text" name="rut" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Fecha de Nacimiento</label>
      <input type="date" name="fecha_nac" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Curso</label>
      <select name="curso_id" class="form-select" required>
        <option value="">Seleccione curso</option>
        <?php foreach ($cursos as $c): ?>
        <option value="<?= $c[0] ?>"><?= $c[2] ?> - <?= $c[1] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label>ID Colegio</label>
      <input type="number" name="colegio_id" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Teléfono</label>
      <input type="text" name="telefono" class="form-control">
    </div>
    <div class="mb-3">
      <label>Email</label>
      <input type="email" name="email" class="form-control">
    </div>
    <button class="btn btn-success">Guardar Alumno</button>
  </form>
</div>
</body>
</html>