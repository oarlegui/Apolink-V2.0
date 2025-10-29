<?php
// archivo: add_apoderado.php
// Registro de nuevo apoderado. Solo accesible para admin y admin_curso.

session_start();
require '../include/conexion.php';
require '../include/log_auditoria.php';
require '../include/seguridad.php';

require_login();
$rol = $_SESSION['rol'];
$usuario_id = $_SESSION['usuario_id'];

if (!in_array($rol, ['admin', 'admin_curso'])) {
    die("Acceso denegado");
}

$colegios = [];
if ($rol === 'admin') {
    $result = $conn->query("SELECT id, nombre FROM colegios ORDER BY nombre");
    $colegios = $result->fetch_all(MYSQLI_ASSOC);
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $rut = $_POST['rut'];
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $colegio_id = $_POST['colegio_id'] ?? null;

    if ($password !== $confirm) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE rut = ?");
        $stmt->bind_param("s", $rut);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "El RUT ya está registrado.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (nombre, rut, email, telefono, password, rol) VALUES (?, ?, ?, ?, ?, 'apoderado')");
            $stmt->bind_param("sssss", $nombre, $rut, $email, $telefono, $hash);
            $stmt->execute();
            $apoderado_id = $stmt->insert_id;
            log_accion($conn, $usuario_id, 'crear', 'apoderados', "Agregó apoderado ID $apoderado_id: $nombre");

            if ($rol === 'admin' && $colegio_id) {
                $stmt = $conn->prepare("INSERT INTO user_colegio (user_id, colegio_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $apoderado_id, $colegio_id);
                $stmt->execute();
            }

            header("Location: apoderados.php?ok=1");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Apoderado</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Registrar Apoderado</h4>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
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
      <label>Correo Electrónico</label>
      <input type="email" name="email" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Teléfono</label>
      <input type="text" name="telefono" class="form-control">
    </div>
    <div class="mb-3">
      <label>Contraseña</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Confirmar Contraseña</label>
      <input type="password" name="confirm" class="form-control" required>
    </div>
    <?php if ($rol === 'admin'): ?>
    <div class="mb-3">
      <label>Colegio</label>
      <select name="colegio_id" class="form-select">
        <option value="">Seleccione colegio</option>
        <?php foreach ($colegios as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button class="btn btn-success">Guardar Apoderado</button>
  </form>
</div>
</body>
</html>
