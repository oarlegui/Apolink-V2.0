<?php
// archivo: edit_empresa.php
// Permite editar una empresa. Solo accesible por admin o el dueño de la empresa (si tiene login).

session_start();
require '../include/conexion.php';
require '../include/log_auditoria.php';
require '../include/seguridad.php';

require_login();
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID no válido.");
}

// Obtener datos de la empresa
$stmt = $conn->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$empresa = $stmt->get_result()->fetch_assoc();

if (!$empresa) {
    echo "Empresa no encontrada.";
    exit;
}

// Solo admin o dueño (si futuro login empresa)
if ($rol !== 'admin') {
    die("Acceso restringido.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $region = $_POST['region'];
    $comuna = $_POST['comuna'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    $imagen = $empresa['imagen_principal'];
    if ($_FILES['imagen_principal']['name']) {
        $imagen = $_FILES['imagen_principal']['name'];
        move_uploaded_file($_FILES['imagen_principal']['tmp_name'], "../uploads/empresas/" . $imagen);
    }

    // Galería
    for ($i = 1; $i <= 3; $i++) {
        $campo = "imagen_$i";
        if ($_FILES[$campo]['name']) {
            $img = $_FILES[$campo]['name'];
            move_uploaded_file($_FILES[$campo]['tmp_name'], "../uploads/empresas/" . $img);
            $empresa["imagen$i"] = $img;
        }
    }

    $stmt = $conn->prepare("UPDATE empresas SET nombre=?, descripcion=?, region=?, comuna=?, telefono=?, email=?, imagen_principal=?, imagen1=?, imagen2=?, imagen3=? WHERE id=?");
    $stmt->bind_param(
        "ssssssssssi",
        $nombre,
        $descripcion,
        $region,
        $comuna,
        $telefono,
        $email,
        $imagen,
        $empresa['imagen1'],
        $empresa['imagen2'],
        $empresa['imagen3'],
        $id
    );
    $stmt->execute();

    log_accion($conn, $usuario_id, 'editar', 'empresas', "Editó empresa ID $id");

    header("Location: empresas.php?editado=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Empresa</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h4>Editar Empresa</h4>
  <form method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label>Nombre</label>
      <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($empresa['nombre']) ?>" required>
    </div>
    <div class="mb-3">
      <label>Descripción</label>
      <textarea name="descripcion" class="form-control" rows="4"><?= htmlspecialchars($empresa['descripcion']) ?></textarea>
    </div>
    <div class="mb-3 row">
      <div class="col-md-6">
        <label>Región</label>
        <input type="text" name="region" class="form-control" value="<?= htmlspecialchars($empresa['region']) ?>" required>
      </div>
      <div class="col-md-6">
        <label>Comuna</label>
        <input type="text" name="comuna" class="form-control" value="<?= htmlspecialchars($empresa['comuna']) ?>" required>
      </div>
    </div>
    <div class="mb-3 row">
      <div class="col-md-6">
        <label>Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($empresa['telefono']) ?>">
      </div>
      <div class="col-md-6">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($empresa['email']) ?>">
      </div>
    </div>
    <div class="mb-3">
      <label>Imagen Principal (actual: <?= $empresa['imagen_principal'] ?>)</label>
      <input type="file" name="imagen_principal" class="form-control">
    </div>
    <div class="mb-3">
      <label>Galería</label>
      <?php for ($i = 1; $i <= 3; $i++): ?>
        <input type="file" name="imagen_<?= $i ?>" class="form-control mb-2">
      <?php endfor; ?>
    </div>
    <button class="btn btn-primary">Actualizar Empresa</button>
  </form>
</div>
</body>
</html>
