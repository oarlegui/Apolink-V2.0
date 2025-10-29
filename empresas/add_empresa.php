<?php
// archivo: add_empresa.php
// Formulario para registrar una empresa. Solo accesible para administradores.

session_start();
require '../include/conexion.php';
require '../include/log_auditoria.php';
require '../include/seguridad.php';

require_login();
if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $region = $_POST['region'];
    $comuna = $_POST['comuna'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    // Subir imagen principal
    $imagen = $_FILES['imagen_principal']['name'];
    $tmp = $_FILES['imagen_principal']['tmp_name'];
    move_uploaded_file($tmp, "../uploads/empresas/" . $imagen);

    // Subir galería
    $imagenes_galeria = [];
    for ($i = 1; $i <= 3; $i++) {
        $campo = "imagen_$i";
        if ($_FILES[$campo]['name']) {
            $img = $_FILES[$campo]['name'];
            move_uploaded_file($_FILES[$campo]['tmp_name'], "../uploads/empresas/" . $img);
            $imagenes_galeria[] = $img;
        } else {
            $imagenes_galeria[] = null;
        }
    }

    $stmt = $conn->prepare("INSERT INTO empresas (nombre, descripcion, region, comuna, telefono, email, imagen_principal, imagen1, imagen2, imagen3, clicks_tel, clicks_email)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
    $stmt->bind_param("ssssssssss", $nombre, $descripcion, $region, $comuna, $telefono, $email, $imagen, $imagenes_galeria[0], $imagenes_galeria[1], $imagenes_galeria[2]);
    $stmt->execute();

    log_accion($conn, $_SESSION['usuario_id'], 'crear', 'empresas', "Registró empresa: $nombre");

    header("Location: empresas.php?ok=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Empresa</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Agregar Empresa</h4>
  <form method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label>Nombre</label>
      <input type="text" name="nombre" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Descripción</label>
      <textarea name="descripcion" class="form-control" rows="4" required></textarea>
    </div>
    <div class="mb-3 row">
      <div class="col-md-6">
        <label>Región</label>
        <input type="text" name="region" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label>Comuna</label>
        <input type="text" name="comuna" class="form-control" required>
      </div>
    </div>
    <div class="mb-3 row">
      <div class="col-md-6">
        <label>Teléfono</label>
        <input type="text" name="telefono" class="form-control">
      </div>
      <div class="col-md-6">
        <label>Email</label>
        <input type="email" name="email" class="form-control">
      </div>
    </div>
    <div class="mb-3">
      <label>Imagen Principal</label>
      <input type="file" name="imagen_principal" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Galería (máx. 3 imágenes)</label>
      <input type="file" name="imagen_1" class="form-control mb-2">
      <input type="file" name="imagen_2" class="form-control mb-2">
      <input type="file" name="imagen_3" class="form-control">
    </div>
    <button class="btn btn-success">Guardar Empresa</button>
  </form>
</div>
</body>
</html>
