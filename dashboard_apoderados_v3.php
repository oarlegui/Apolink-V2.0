<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'include/conexion.php';
require 'include/seguridad.php';

require_login();
require_rol(['apoderado']);

$apoderado_id = $_SESSION['usuario_id'];
$colegio_id = $_SESSION['colegio_id'];

// Obtener datos del alumno
$stmt = $conn->prepare("
  SELECT s.id AS alumno_id, s.nombre AS alumno, s.cuota_total,
         c.nombre AS curso, col.nombre AS colegio,
         IFNULL(SUM(p.monto), 0) AS pagado
  FROM students s
  JOIN cursos c ON s.curso_id = c.id
  JOIN colegios col ON s.colegio_id = col.id
  JOIN alumno_apoderado aa ON aa.alumno_id = s.id
  LEFT JOIN pagos p ON p.alumno_id = s.id
  WHERE aa.apoderado_id = ? AND s.colegio_id = ?
  GROUP BY s.id
  LIMIT 1
");
$stmt->bind_param("ii", $apoderado_id, $colegio_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();
$stmt->close();

$cuota_total = (int) $alumno['cuota_total'];
$pagado = (int) $alumno['pagado'];
$pendiente = max($cuota_total - $pagado, 0);
$porcentaje = $cuota_total > 0 ? round(($pagado / $cuota_total) * 100) : 0;

// Obtener avances individuales de gira
$alumno_id = $alumno['alumno_id'];

$stmt_gira = $conn->prepare("
  SELECT IFNULL(SUM(monto), 0) AS total_aporte
  FROM aportes_gira
  WHERE alumno_id = ?
");
$stmt_gira->bind_param("i", $alumno_id);
$stmt_gira->execute();
$aporte = $stmt_gira->get_result()->fetch_assoc()['total_aporte'];
$stmt_gira->close();

$meta_gira = $alumno['cuota_gira'] ?? 0;
$pendiente_gira = max($meta_gira - $aporte, 0);

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Apoderado - Apolink</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
  <style>
    body { display: flex; }
    .sidebar {
      width: 250px;
      min-height: 100vh;
      background-color: #f8f9fa;
      padding: 20px;
      border-right: 1px solid #dee2e6;
    }
    .main {
      flex: 1;
      padding: 30px;
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar text-center">
  <img src="https://www.arlegui-it.cl/sistema-gestion-curso/includes/assets/images/logo_white.png" alt="Apolink" class="img-fluid mb-4" style="max-height: 50px;">
  <p class="text-muted">Perfil: <strong><?= ucfirst($_SESSION['rol']) ?></strong></p>
  <a href="dashboard_apoderado_v2.php" class="btn btn-outline-primary w-100 mb-2">🏠 Inicio</a>
  <a href="pagos.php" class="btn btn-outline-success w-100 mb-2">💳 Subir Pago</a>
  <a href="pagos_historial.php" class="btn btn-outline-primary w-100 mb-2">📃 Historial</a>
  <a href="eventos/calendar.php" class="btn btn-outline-secondary w-100 mb-2">📅 Calendario</a>
  <a href="empresas/empresas.php" class="btn btn-outline-warning w-100 mb-2">🏪 Servicios</a>
  <a href="logout.php" class="btn btn-outline-danger w-100 mt-4">🚪 Cerrar sesión</a>
</div>

<!-- Contenido -->
<div class="main">
  <h4 class="mb-2">👋 Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></h4>
  <p class="text-muted">Alumno: <strong><?= htmlspecialchars($alumno['alumno']) ?></strong> | Curso: <strong><?= htmlspecialchars($alumno['curso']) ?></strong> | Colegio: <strong><?= htmlspecialchars($alumno['colegio']) ?></strong></p>
  
  <!-- Estado de pago -->
  <div class="row mt-4 mb-4">
    <div class="col-md-3">
      <div class="card text-center shadow-sm">
        <div class="card-body">
          <h6>Total Cuota</h6>
          <strong>$<?= number_format($cuota_total, 0, ',', '.') ?></strong>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center shadow-sm">
        <div class="card-body">
          <h6>Pagado</h6>
          <strong class="text-success">$<?= number_format($pagado, 0, ',', '.') ?></strong>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center shadow-sm">
        <div class="card-body">
          <h6>Pendiente</h6>
          <strong class="text-danger">$<?= number_format($pendiente, 0, ',', '.') ?></strong>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center shadow-sm">
        <div class="card-body">
          <h6>Avance</h6>
          <strong><?= $porcentaje ?>%</strong>
        </div>
      </div>
    </div>
  </div>

  <!-- Galería de eventos -->
  <div class="card shadow-sm p-4 mb-5">
    <h6 class="mb-3">🖼️ Galería de eventos recientes</h6>
    <div class="row">
      <?php
      // Consulta para obtener eventos y una imagen aleatoria de cada uno
      $stmt = $conn->query("
        SELECT e.id AS evento_id, e.titulo, e.fecha, e.descripcion, MIN(i.ruta) AS imagen_random
        FROM eventos e
        JOIN imagenes_eventos i ON e.id = i.evento_id
        WHERE e.curso_id IN (
          SELECT s.curso_id FROM students s
          JOIN alumno_apoderado aa ON s.id = aa.alumno_id
          WHERE aa.apoderado_id = $apoderado_id
        )
        GROUP BY e.id
        ORDER BY e.fecha DESC
      ");
      while ($evento = $stmt->fetch_assoc()):
      ?>
        <div class="col-md-4 mb-4">
          <div class="card shadow-sm">
            <img src="uploads/eventos/<?= htmlspecialchars($evento['imagen_random']) ?>" class="card-img-top" alt="<?= htmlspecialchars($evento['titulo']) ?>">
            <div class="card-body">
              <h5 class="card-title"><?= htmlspecialchars($evento['titulo']) ?></h5>
              <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEvento<?= $evento['evento_id'] ?>">Ver Más</button>
            </div>
          </div>
        </div>

        <!-- Modal del evento -->
        <div class="modal fade" id="modalEvento<?= $evento['evento_id'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= $evento['evento_id'] ?>" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="modalLabel<?= $evento['evento_id'] ?>"><?= htmlspecialchars($evento['titulo']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p><strong>Fecha:</strong> <?= date('d M Y', strtotime($evento['fecha'])) ?></p>
                <p><strong>Descripción:</strong> <?= htmlspecialchars($evento['descripcion']) ?></p>
                <div class="row">
                  <?php
                  // Obtener imágenes del evento
                  $stmt_imgs = $conn->prepare("SELECT ruta FROM imagenes_eventos WHERE evento_id = ?");
                  $stmt_imgs->bind_param("i", $evento['evento_id']);
                  $stmt_imgs->execute();
                  $imagenes = $stmt_imgs->get_result();
                  while ($img = $imagenes->fetch_assoc()):
                  ?>
                    <div class="col-md-4 mb-3">
                      <a href="uploads/eventos/<?= htmlspecialchars($img['ruta']) ?>" class="glightbox" data-gallery="galeriaEvento<?= $evento['evento_id'] ?>">
                        <img src="uploads/eventos/<?= htmlspecialchars($img['ruta']) ?>" class="img-fluid rounded shadow-sm" alt="Imagen del evento">
                      </a>
                    </div>
                  <?php endwhile; ?>
                  <?php $stmt_imgs->close(); ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>

  <!-- Inicializar GLightbox -->
  <script>
    const lightbox = GLightbox({ selector: '.glightbox' });
  </script>
</div>
</body>
</html>