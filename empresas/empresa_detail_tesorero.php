<?php
// archivo: empresa_detail.php
// Muestra la información de una empresa con imágenes y clics contables en email/teléfono.

// DEBE IR OBLIGATORIAMENTE EN TODOS LOS ARCHIVOS

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../include/conexion.php';
require '../include/seguridad.php';
require '../include/get_assigned_courses.php';

$alumno_id = $_SESSION['alumno_id'] ?? null; // Usar el alumno seleccionado en la sesión
$usuario_id = $_SESSION['usuario_id'];
$tesorero_id = $_SESSION['usuario_id'];
$colegio_id = $_SESSION['colegio_id'];
$rol = $_SESSION['rol'];

require_login();

// CIERRE DE DEBE IR OBLIGATORIAMENTE EN TODOS LOS ARCHIVOS

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ID no válido.";
    exit;
}

// Incrementar contador si clic en contacto
if (isset($_GET['click']) && in_array($_GET['click'], ['tel', 'email'])) {
    $campo = $_GET['click'] === 'tel' ? 'clicks_tel' : 'clicks_email';
    $stmt = $conn->prepare("UPDATE empresas SET $campo = $campo + 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: empresa_detail.php?id=" . $id);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$empresa = $stmt->get_result()->fetch_assoc();

if (!$empresa) {
    echo "Empresa no encontrada.";
    exit;
}

$imagenes = array_filter([$empresa['galeria1'], $empresa['galeria2'], $empresa['galeria3']]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($empresa['nombre']) ?> - Detalle</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../include/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    .galeria img { height: 150px; object-fit: cover; border-radius: 10px; }
  </style>
</head>
<body>
    
     <!-- SIDERBAR -->    
            <?php include '../include/sidebar_dashboard_tesorero.php'; ?>
<!-- CIERRA SIDEBAR -->
        
<div class="container mt-4">
    
<!-- TOPBAR -->  
    <?php 
    
// Obtener los cursos asignados al tesorero
$sql_courses = "
    SELECT 
        c.id AS course_id,
        c.nombre AS course_name,
        col.nombre AS school_name,
        col.logo_colegio AS school_logo
    FROM cursos c
    JOIN colegios col ON c.colegio_id = col.id
    JOIN tesorero_curso tc ON c.id = tc.curso_id
    WHERE tc.tesorero_id = ?
";

$stmt_courses = $conn->prepare($sql_courses);
if (!$stmt_courses) {
    die("Error en la preparación de la consulta: " . $conn->error);
}

$stmt_courses->bind_param("i", $tesorero_id);
$stmt_courses->execute();
$result_courses = $stmt_courses->get_result();

// Verificar si el tesorero tiene cursos asignados
$cursos = $result_courses->fetch_all(MYSQLI_ASSOC);

if (count($cursos) === 0) {
    // Si no hay cursos asignados, mostrar valores predeterminados
    $curso_actual = [
        'course_name' => 'No disponible',
        'school_name' => 'No disponible',
        'school_logo' => 'https://cdn-icons-png.flaticon.com/512/1930/1930026.png', // Logo genérico
    ];
} else {
    // Obtener el curso seleccionado actualmente (por defecto, el primero)
    $curso_id_seleccionado = $_POST['curso_seleccionado'] ?? $cursos[0]['course_id'];
    $curso_actual = array_filter($cursos, function($curso) use ($curso_id_seleccionado) {
        return $curso['course_id'] == $curso_id_seleccionado;
    });
    $curso_actual = reset($curso_actual); // Obtener el primer elemento del array filtrado
}

// 2. Totales pagos y gastos
$stmt2 = $conn->prepare("
SELECT
    (SELECT IFNULL(SUM(monto), 0) FROM pagos WHERE curso_id = ?) AS total_pagos,
    (SELECT IFNULL(SUM(monto), 0) FROM gastos WHERE curso_id = ?) AS total_gastos
");
$stmt2->bind_param("ii", $curso_id_seleccionado, $curso_id_seleccionado);
$stmt2->execute();
$totales = $stmt2->get_result()->fetch_assoc();
$total_pagos = $totales['total_pagos'];
$total_gastos = $totales['total_gastos'];
$saldo = $total_pagos - $total_gastos;


// Asignar valores a las variables
$curso_nombre = htmlspecialchars($curso_actual['course_name'], ENT_QUOTES, 'UTF-8');
$colegio_nombre = htmlspecialchars($curso_actual['school_name'], ENT_QUOTES, 'UTF-8');
$logo_colegio = htmlspecialchars($curso_actual['school_logo'], ENT_QUOTES, 'UTF-8');
$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');

// 3. Datos mensuales pagos para el curso seleccionado
$pagos_mes = array_fill(1, 12, 0); // Inicializar un array para todos los meses

// Consulta SQL para obtener los pagos mensuales del curso seleccionado
$stmt_pagos_curso = $conn->prepare("
    SELECT MONTH(fecha_pago) AS mes, SUM(monto) AS total
    FROM pagos
    WHERE curso_id = ? AND YEAR(fecha_pago) = YEAR(NOW())
    GROUP BY mes
");

// Pasar el curso seleccionado como parámetro
$stmt_pagos_curso->bind_param("i", $curso_id_seleccionado);
$stmt_pagos_curso->execute();
$result3 = $stmt_pagos_curso->get_result();

// Procesar los resultados de la consulta
while ($row = $result3->fetch_assoc()) {
    $pagos_mes[(int)$row['mes']] = (int)$row['total'];
}
$stmt_pagos_curso->close();

// 4. Datos mensuales gastos
$gastos_mes = array_fill(1, 12, 0);
// Consulta de gastos para el curso seleccionado
$stmt_gastos_curso = $conn->prepare("
    SELECT MONTH(fecha) AS mes, SUM(monto) AS total
    FROM gastos
    WHERE curso_id = ? AND YEAR(fecha) = YEAR(NOW())
    GROUP BY mes
");

// Pasar el curso seleccionado como parámetro
$stmt_gastos_curso->bind_param("i", $curso_id_seleccionado);
$stmt_gastos_curso->execute();
$result3 = $stmt_gastos_curso->get_result();

// Procesar los resultados de la consulta
while ($row = $result3->fetch_assoc()) {
    $gastos_mes[(int)$row['mes']] = (int)$row['total'];
}
$stmt_gastos_curso->close();

?>

<div class="card bg-light shadow-sm mb-4 p-3 d-flex flex-row">
    <div class="d-flex align-items-center">
        <!-- Logo del colegio -->
        <div class="me-3">
            <img 
                src="../<?= !empty($logo_colegio) ? $logo_colegio : 'https://cdn-icons-png.flaticon.com/512/1930/1930026.png' ?>" 
                alt="Logo Colegio" 
                class="rounded-circle" 
                style="width: 60px; height: 60px; object-fit: cover;">
        </div>
        <!-- Información del usuario -->
        <div>
            <h4 class="mb-1">👋 Bienvenido, <span class="text-primary"><?= $nombre_usuario ?></span></h4>
            <p class="text-muted mb-2">
                Colegio: <strong><?= $colegio_nombre ?></strong>
            </p>
            <p class="text-muted mb-2">
                Curso: <strong id="curso-actual-text"><?= $curso_nombre ?></strong>
                <button class="btn btn-link p-0 ms-2" data-bs-toggle="modal" data-bs-target="#cambiarCursoModal" style="font-size: 0.9rem;" hidden>Cambiar curso</button>
            </p>
        </div> 
    </div>
</div>

    <!-- Modal para cambiar curso -->
<div class="modal fade" id="cambiarCursoModal" tabindex="-1" aria-labelledby="cambiarCursoModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" >
        <h5 class="modal-title" id="cambiarCursoModalLabel" >Cambiar Curso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="cursoForm">
        <div class="modal-body">
          <div class="mb-3">
            <label for="curso_seleccionado" class="form-label">Selecciona un curso:</label>
            <select name="curso_seleccionado" id="curso_seleccionado" class="form-select">
                <?php foreach ($cursos as $curso): ?>
                    <option value="<?= $curso['course_id'] ?>" <?= $curso['course_id'] == $curso_id_seleccionado ? 'selected' : '' ?>>
                        <?= htmlspecialchars($curso['course_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- CIERRA TOPBAR -->


    <br/>
  <div class="row mb-3">
  <!-- Columna Izquierda: Información de la Empresa -->
  <div class="col-md-6">
    <h3><?= htmlspecialchars($empresa['nombre']) ?></h3>
    <p class="text-muted"><?= htmlspecialchars($empresa['direccion']) ?>, <?= htmlspecialchars($empresa['comuna']) ?>, <?= htmlspecialchars($empresa['region']) ?></p>
    <p><?= nl2br(htmlspecialchars($empresa['descripcion'])) ?></p>
  </div>
  
  <!-- Columna Derecha: Información de Contacto -->
  <div class="col-md-6">
    <h5>Contacto</h5>
    <div class="mb-3">
      <a href="tel:<?= htmlspecialchars($empresa['telefono']) ?>" class="btn btn-sm btn-outline-primary" title="Llamar">
                  📞 Teléfono
                </a>
      <a href="mailto:<?= htmlspecialchars($empresa['email']) ?>" class="btn btn-sm btn-outline-success" title="Enviar Email">
                  📧 Email
                </a>
    </div>
    <small class="text-muted">Clics Tel: <?= htmlspecialchars($empresa['clics_telefono']) ?> — Email: <?= htmlspecialchars($empresa['clics_email']) ?></small>
  </div>
</div>
<hr/>
  <div class="row mb-3">
  <!-- Columna Izquierda: Imagen Principal -->
  <div class="col-md-6">
    <img 
      src="../uploads/empresas/<?= htmlspecialchars($empresa['imagen_principal']) ?>" 
      class="img-fluid rounded shadow-sm w-100" 
      style="height: 300px; object-fit: cover;" 
      alt="Imagen principal">
  </div>
  
  <!-- Columna Derecha: Mapa de Google Maps -->
  <div class="col-md-6">
    <iframe 
      src="https://www.google.com/maps?q=<?= urlencode($empresa['direccion'] . ', ' . $empresa['comuna']) ?>&output=embed" 
      width="100%" 
      height="300" 
      frameborder="1" 
      style="border:1;" 
      allowfullscreen="">
    </iframe>
  </div>
</div>

  <h5>Galería</h5>
<div class="row galeria mb-3">
  <?php foreach ($imagenes as $index => $img): ?>
    <div class="col-md-4 mb-2">
      <img 
        src="../uploads/empresas/<?= $img ?>" 
        class="img-fluid shadow-sm w-100 h-100" 
        data-bs-toggle="modal" 
        data-bs-target="#galleryModal" 
        data-bs-slide-to="<?= $index ?>" 
        alt="Imagen <?= $index + 1 ?>"
      >
    </div>
  <?php endforeach; ?>
  <?php if (empty($imagenes)): ?>
    <p class="text-muted">Sin imágenes adicionales.</p>
  <?php endif; ?>
</div>

<!-- Modal Slider -->
<div class="modal fade" id="galleryModal" tabindex="-1" aria-labelledby="galleryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header d-flex flex-column align-items-start">
        <!-- Primera fila: Nombre de la empresa -->
        <h3 class="modal-title" id="galleryModalLabel">
          <?= htmlspecialchars($empresa['nombre']) ?>
        </h3>
        <!-- Segunda fila: Región y Comuna -->
        <p class="mb-1 text-muted">
          <?= htmlspecialchars($empresa['direccion']) ?>, <?= htmlspecialchars($empresa['comuna']) ?>, <?= htmlspecialchars($empresa['region']) ?>
        </p>
        <!-- Tercera fila: Descripción -->
        <p class="mb-0">
          <?= nl2br(htmlspecialchars($empresa['descripcion'])) ?>
        </p>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="carouselGallery" class="carousel slide" data-bs-ride="carousel">
          <div class="carousel-inner">
            <?php foreach ($imagenes as $index => $img): ?>
              <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                <img 
                  src="../uploads/empresas/<?= $img ?>" 
                  class="d-block w-100" 
                  alt="Imagen <?= $index + 1 ?>"
                >
              </div>
            <?php endforeach; ?>
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#carouselGallery" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#carouselGallery" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

 <?php include '../include/footer.php'; ?>
</div>
</body>
</html>
