<?php
// archivo: students.php
// Muestra el listado de alumnos con filtros, permisos y acciones según rol.

session_start();
require '../include/conexion.php';
require '../include/get_assigned_courses.php';
require '../include/seguridad.php';
require '../include/funciones.php';

require_login();
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

$condiciones = [];
$parametros = [];
$tipos = '';

if ($rol === 'admin_curso') {
    $cursos = get_assigned_courses($conn, $usuario_id, $rol);
    $condiciones[] = "s.curso_id IN (" . implode(",", $cursos) . ")";
}

$buscar = $_GET['buscar'] ?? '';
if ($buscar) {
    $condiciones[] = "(s.nombre LIKE ? OR s.rut LIKE ?)";
    $parametros[] = "%$buscar%";
    $parametros[] = "%$buscar%";
    $tipos .= 'ss';
}

$where = $condiciones ? "WHERE " . implode(" AND ", $condiciones) : "";

$query = "SELECT s.*, c.nombre AS curso_nombre, col.nombre AS colegio_nombre
          FROM students s
          JOIN cursos c ON s.curso_id = c.id
          JOIN colegios col ON s.colegio_id = col.id
          $where
          ORDER BY s.nombre ASC";

$stmt = $conn->prepare($query);
if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}
$stmt->execute();
$alumnos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Listado de Alumnos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Listado de Alumnos</h4>
    <?php if (in_array($rol, ['admin', 'admin_curso'])): ?>
      <a href="add_student.php" class="btn btn-success">+ Agregar Alumno</a>
    <?php endif; ?>
  </div>

  <form method="GET" class="mb-3">
    <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre o RUT" value="<?= htmlspecialchars($buscar) ?>">
  </form>

  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>Nombre</th>
        <th>RUT</th>
        <th>Colegio</th>
        <th>Curso</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($alumnos as $al): ?>
        <tr>
          <td><?= htmlspecialchars($al['nombre']) ?></td>
          <td><?= htmlspecialchars($al['rut']) ?></td>
          <td><?= htmlspecialchars($al['colegio_nombre']) ?></td>
          <td><?= htmlspecialchars($al['curso_nombre']) ?></td>
          <td>
            <a href="student_detail.php?id=<?= $al['id'] ?>" class="btn btn-sm btn-info">Ver</a>
            <?php if (in_array($rol, ['admin', 'admin_curso'])): ?>
              <a href="edit_student.php?id=<?= $al['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($alumnos)): ?>
        <tr>
          <td colspan="5" class="text-center text-muted">No se encontraron alumnos.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>