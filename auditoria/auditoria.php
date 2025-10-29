<?php
// archivo: auditoria.php
// Registro de acciones en el sistema. Solo accesible para administradores.

session_start();
require '../include/conexion.php';

if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

$usuario = $_GET['usuario'] ?? '';
$modulo = $_GET['modulo'] ?? '';
$accion = $_GET['accion'] ?? '';
$fecha_ini = $_GET['fecha_ini'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

$condiciones = [];
$parametros = [];
$tipos = '';

if ($usuario) {
    $condiciones[] = "a.usuario_id = ?";
    $parametros[] = $usuario;
    $tipos .= 'i';
}
if ($modulo) {
    $condiciones[] = "a.modulo = ?";
    $parametros[] = $modulo;
    $tipos .= 's';
}
if ($accion) {
    $condiciones[] = "a.accion = ?";
    $parametros[] = $accion;
    $tipos .= 's';
}
if ($fecha_ini && $fecha_fin) {
    $condiciones[] = "a.fecha BETWEEN ? AND ?";
    $parametros[] = $fecha_ini;
    $parametros[] = $fecha_fin;
    $tipos .= 'ss';
}

$where = $condiciones ? "WHERE " . implode(" AND ", $condiciones) : "";

$query = "SELECT a.*, u.nombre AS usuario FROM auditoria a JOIN users u ON a.usuario_id = u.id $where ORDER BY a.fecha DESC";

$stmt = $conn->prepare($query);
if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener lista de usuarios
$usuarios = $conn->query("SELECT id, nombre FROM users ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Auditoría del Sistema</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Registro de Auditoría</h4>
  <form method="GET" class="row g-2 mb-4">
    <div class="col-md-3">
      <select name="usuario" class="form-select">
        <option value="">Todos los usuarios</option>
        <?php foreach ($usuarios as $u): ?>
          <option value="<?= $u[0] ?>" <?= $usuario == $u[0] ? 'selected' : '' ?>><?= htmlspecialchars($u[1]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="text" name="modulo" class="form-control" placeholder="Módulo" value="<?= htmlspecialchars($modulo) ?>">
    </div>
    <div class="col-md-2">
      <select name="accion" class="form-select">
        <option value="">Acción</option>
        <option value="crear" <?= $accion == 'crear' ? 'selected' : '' ?>>Crear</option>
        <option value="editar" <?= $accion == 'editar' ? 'selected' : '' ?>>Editar</option>
        <option value="eliminar" <?= $accion == 'eliminar' ? 'selected' : '' ?>>Eliminar</option>
      </select>
    </div>
    <div class="col-md-2">
      <input type="date" name="fecha_ini" class="form-control" value="<?= htmlspecialchars($fecha_ini) ?>">
    </div>
    <div class="col-md-2">
      <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>">
    </div>
    <div class="col-md-1">
      <button class="btn btn-primary w-100">Filtrar</button>
    </div>
  </form>

  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>Fecha</th>
        <th>Usuario</th>
        <th>Módulo</th>
        <th>Acción</th>
        <th>Detalle</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= $log['fecha'] ?></td>
          <td><?= htmlspecialchars($log['usuario']) ?></td>
          <td><?= htmlspecialchars($log['modulo']) ?></td>
          <td><?= htmlspecialchars($log['accion']) ?></td>
          <td><?= htmlspecialchars($log['detalle']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?>
        <tr>
          <td colspan="5" class="text-center text-muted">Sin registros.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
