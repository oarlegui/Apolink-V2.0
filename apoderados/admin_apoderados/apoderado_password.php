<?php
session_start();
require '../include/conexion.php';
require '../include/seguridad.php';
require_login();
require_rol(['admin', 'admin_curso', 'tesorero']);
$id = intval($_GET['id'] ?? 0);
$msg = '';
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['password'])){
    $pass = $_POST['password'];
    if(strlen($pass)<6) $msg = '<div class="alert alert-danger">Mínimo 6 caracteres.</div>';
    else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=? AND rol='apoderado'");
        $stmt->bind_param("si", $hash, $id);
        $stmt->execute();
        $msg = '<div class="alert alert-success">Contraseña actualizada.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar Password Apoderado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h3>Cambiar contraseña apoderado</h3>
    <?= $msg ?>
    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <label for="password" class="form-label">Nueva contraseña</label>
            <input type="password" class="form-control" id="password" name="password" required minlength="6">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="apoderado_list.php" class="btn btn-secondary">Volver</a>
        </div>
    </form>
</div>
</body>
</html>