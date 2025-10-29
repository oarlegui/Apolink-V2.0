<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../include/conexion.php';
require '../include/seguridad.php';
require_login();
require_rol(['admin', 'admin_curso', 'tesorero']);

$user_id = intval($_GET['id']);
$success = '';
$error = '';

$stmt = $conn->prepare("SELECT nombre, rut, email, telefono FROM users WHERE id = ? AND rol = 'apoderado'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($nombre, $rut, $email, $telefono);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $rut = trim($_POST['rut']);
    $email = strtolower(trim($_POST['email']));
    $telefono = trim($_POST['telefono']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El email no es válido.";
    } elseif (!preg_match('/^\d{7,8}-[kK\d]{1}$/', $rut)) {
        $error = "El RUT debe tener el formato 12345678-9 o 12345678-K.";
    } elseif (!preg_match('/^\+\d{1,3}\d{7,}$/', $telefono)) {
        $error = "El teléfono debe incluir el código de país, por ejemplo +56912345678";
    } else {
        $stmt = $conn->prepare("UPDATE users SET nombre=?, rut=?, email=?, telefono=? WHERE id=?");
        $stmt->bind_param("ssssi", $nombre, $rut, $email, $telefono, $user_id);
        if ($stmt->execute()) {
            $success = "Datos actualizados correctamente.";
        } else {
            $error = "Error al actualizar.";
        }
        $stmt->close();
    }
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
    <h3>Editar Apoderado</h3>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <label for="nombre" class="form-label">Nombre completo</label>
            <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($nombre ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label for="rut" class="form-label">RUT</label>
            <input type="text" class="form-control" id="rut" name="rut" required maxlength="10" value="<?= htmlspecialchars($rut ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="text" class="form-control" id="telefono" name="telefono" required placeholder="+56912345678" value="<?= htmlspecialchars($telefono ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            <a href="apoderado_list.php" class="btn btn-secondary">Volver</a>
        </div>
    </form>
</div>
</body>
</html>