<?php
// archivo: edit_apoderado.php
// Permite editar un apoderado. Accesible para admin y admin_curso.

session_start();
require '../include/conexion.php';
require '../include/log_auditoria.php';
require '../include/seguridad.php';

require_login();
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$id = $_GET['id'] ?? null;

if (!$id || !in_array($rol, ['admin', 'admin_curso'])) {
    die("Acceso denegado.");
}

// Obtener datos del apoderado
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND rol = 'apoderado'");
$stmt->bind_param("i", $id);
$stmt->execute();
$apoderado = $stmt->get_result()->fetch_assoc();

if (!$apoderado) {
    echo "Apoderado no encontrado.";
    exit;
}

$colegios = [];
$colegio_actual = null;
if ($rol === 'admin') {
    $colegios = $conn->query("SELECT id, nombre FROM colegios ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
    $stmt = $conn->prepare("SELECT colegio_id FROM user_colegio WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $colegio_actual = $stmt->get_result()->fetch_assoc()['colegio_id'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $rut = $_POST['rut'];
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];
    $password = $_POST['password'] ?? '';
    $colegio_id = $_POST['colegio_id'] ?? null;

    $stmt = $conn->prepare("UPDATE users SET nombre=?, rut=?, email=?, telefono=? WHERE id=?");
    $stmt->bind_param("ssssi", $nombre, $rut, $email, $telefono, $id);
    $stmt->execute();

    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $id);
        $stmt->execute();
    }

    if ($rol === 'admin') {
        $conn->query("DELETE FROM user_colegio WHERE user_id = $id");
        if ($colegio_id) {
            $stmt = $conn->prepare("INSERT INTO user_colegio (user_id, colegio_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $id, $colegio_id);
            $stmt->execute();
        }
    }

    log_accion($conn, $usuario_id, 'editar', 'apoderados', "Editó apoderado ID $id: $nombre");

    header("Location: apoderados.php?editado=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Apoderado</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Editar Apoderado</h4>
  <form method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Nombre</label>
      <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($apoderado['nombre']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">RUT</label>
      <input type="text" name="rut" class="form-control" value="<?= htmlspecialchars($apoderado['rut']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Correo</label>
      <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($apoderado['email']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Teléfono</label>
      <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($apoderado['telefono']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Contraseña (opcional)</label>
      <input type="password" name="password" class="form-control">
    </div>
    <?php if ($rol === 'admin'): ?>
    <div class="mb-3">
      <label class="form-label">Colegio</label>
      <select name="colegio_id" class="form-select">
        <option value="">Seleccione colegio</option>
        <?php foreach ($colegios as $c): ?>
          <option value="<?= $c[0] ?>" <?= $colegio_actual == $c[0] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c[1]) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button class="btn btn-primary">Actualizar Apoderado</button>
  </form>
</div>
</body>
</html>
