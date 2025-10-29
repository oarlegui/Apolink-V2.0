<?php
// archivo: event_detail.php
// Muestra el detalle completo de un evento. Accesible para todos los usuarios autenticados.

session_start();
require '../include/conexion.php';
require '../include/seguridad.php';

require_login();

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ID de evento no válido.";
    exit;
}

$stmt = $conn->prepare("SELECT e.*, c.nombre AS curso, u.nombre AS creador
                        FROM eventos e
                        JOIN cursos c ON e.curso_id = c.id
                        JOIN users u ON e.creado_por = u.id
                        WHERE e.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();

if (!$evento) {
    echo "Evento no encontrado.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Detalle del Evento</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .detalle-evento { max-width: 800px; margin: auto; }
  </style>
</head>
<body>
<div class="container mt-4 detalle-evento">
  <h3><?= htmlspecialchars($evento['titulo']) ?></h3>
  <p class="text-muted">
    <strong>Curso:</strong> <?= htmlspecialchars($evento['curso']) ?><br>
    <strong>Fecha:</strong> <?= date('d-m-Y', strtotime($evento['fecha'])) ?><br>
    <strong>Creado por:</strong> <?= htmlspecialchars($evento['creador']) ?>
  </p>
  <div class="card p-3 shadow-sm">
    <p><?= nl2br(htmlspecialchars($evento['descripcion'])) ?></p>
  </div>

  <!-- Aquí podría ir una galería de imágenes en el futuro -->
</div>
</body>
</html>
