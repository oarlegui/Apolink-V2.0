<?php
// archivo: apoderados.php
// Listado de apoderados con filtros por nombre y RUT, accesible para admin y admin_curso.

session_start();
require '../include/conexion.php';
require '../include/get_assigned_courses.php';
require '../include/seguridad.php';

require_login();
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

if (!in_array($rol, ['admin', 'admin_curso','tesorero'])) {
    die("Acceso denegado");
}

$condiciones = ["u.rol = 'apoderado'"];
$parametros = [];
$tipos = '';

if ($rol === 'admin_curso') {
    $cursos = get_assigned_courses($conn, $usuario_id, $rol);
    if ($cursos) {
        $condiciones[] = "EXISTS (
            SELECT 1 FROM alumno_apoderado aa
            JOIN students s ON s.id = aa.alumno_id
            WHERE aa.apoderado_id = u.id AND s.curso_id IN (" . implode(',', $cursos) . ")
        )";
    } else {
        $condiciones[] = "0"; // No muestra nada
    }
}

$buscar = $_GET['buscar'] ?? '';
if ($buscar) {
    $condiciones[] = "(u.nombre LIKE ? OR u.rut LIKE ?)";
    $parametros[] = "%$buscar%";
    $parametros[] = "%$buscar%";
    $tipos .= 'ss';
}

$where = "WHERE " . implode(" AND ", $condiciones);
$query = "SELECT u.* FROM users u $where ORDER BY u.nombre ASC";

$stmt = $conn->prepare($query);
if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}
$stmt->execute();
$apoderados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Apoderados</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Listado de Apoderados</h4>
    <a href="add_apoderado.php" class="btn btn-success">+ Agregar Apoderado</a>
  </div>

  <form method="GET" class="mb-3">
    <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre o RUT" value="<?= htmlspecialchars($buscar) ?>">
  </form>

  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>Nombre</th>
        <th>RUT</th>
        <th>Email</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($apoderados as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['nombre']) ?></td>
          <td><?= htmlspecialchars($a['rut']) ?></td>
          <td><?= htmlspecialchars($a['email']) ?></td>
          <td>
            <a href="edit_apoderado.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
            <a href="asignar_alumno.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-secondary">Asignar Alumnos</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($apoderados)): ?>
        <tr>
          <td colspan="4" class="text-center text-muted">No hay apoderados disponibles.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
