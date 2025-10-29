<?php
// archivo: empresas.php
// Muestra un listado público de empresas con buscador y paginación.

//DEBE IR OBLIGATORIAMENTE EN TODOS LOS ARCHIVOS

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../include/conexion.php';
require '../include/seguridad.php';
require '../include/get_assigned_courses.php';

$alumno_id = $_SESSION['alumno_id'] ?? null; // Usar el alumno seleccionado en la sesión
$tesorero_id = $_SESSION['usuario_id'];
$colegio_id = $_SESSION['colegio_id'];
$rol = $_SESSION['rol'];

//CIERRE DE DEBE IR OBLIGATORIAMENTE EN TODOS LOS ARCHIVOS

if (!in_array($rol, ['admin', 'admin_curso', 'tesorero'])) {
    // Mostrar el mensaje con Bootstrap, un ícono de alarma, y el logo de Apolink
    echo '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Denegado</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script>
            // Redirigir al index después de 3 segundos
            setTimeout(function() {
                window.location.href = "../index.php";
            }, 3000);
        </script>
        <style>
            .alert-container {
                text-align: center;
                margin-top: 10%;
            }
           .apolink-logo {
                max-width: 150px;
                margin: 0 auto 20px;
                display: block;
            }
        </style>
    </head>
    <body>
        <div class="alert-container">
            <!-- Logo de Apolink -->
            <img src="https://www.arlegui-it.cl/sistema-gestion-curso/includes/assets/images/logo_white.png" alt="Apolink Logo" class="apolink-logo">
            
            <!-- Aviso de Alarma -->
            <div class="alert alert-danger d-inline-block" role="alert" style="max-width: 400px;">
                <h4 class="alert-heading">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Acceso Denegado
                </h4>
                <p>No tienes permiso para acceder a esta página.</p>
                <hr>
                <p class="mb-0">Serás redirigido al inicio en 3 segundos...</p>
            </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    </body>
    </html>';
    exit;
}



$buscar = $_GET['buscar'] ?? '';
$page = $_GET['page'] ?? 1;
$pageSize = 5;
$offset = ($page - 1) * $pageSize;

$condiciones = [];
$parametros = [];
$tipos = '';

if ($buscar) {
    $condiciones[] = "(nombre LIKE ? OR descripcion LIKE ?)";
    $parametros[] = "%$buscar%";
    $parametros[] = "%$buscar%";
    $tipos .= "ss";
}

$where = $condiciones ? "WHERE " . implode(" AND ", $condiciones) : "";

$stmt_total = $conn->prepare("SELECT COUNT(*) as total FROM empresas $where");
if ($parametros) $stmt_total->bind_param($tipos, ...$parametros);
$stmt_total->execute();
$total = $stmt_total->get_result()->fetch_assoc()['total'];
$totalPages = ceil($total / $pageSize);

$query = "SELECT * FROM empresas $where ORDER BY nombre LIMIT $offset, $pageSize";
$stmt = $conn->prepare($query);
if ($parametros) $stmt->bind_param($tipos, ...$parametros);
$stmt->execute();
$empresas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Empresas y Servicios</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../include/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  

  <style>
    .empresa-card { border-left: 4px solid #0d6efd; padding: 10px; margin-bottom: 15px; background-color: #f8f9fa; }
    .empresa-card:hover { background-color: #e9ecef; cursor: pointer; }
    .empresa-card {
          display: flex;
          flex-direction: row;
          align-items: center;
          gap: 20px;
          border: 1px solid #e0e0e0;
          margin-bottom: 20px;
          height: 150px; /* Altura fija para las tarjetas */
        }
        
        .empresa-image {
          flex: 1; /* Ocupa la mitad del contenedor */
          height: 100%; /* Asegura que el contenedor de la imagen llene la tarjeta */
          overflow: hidden; /* Oculta cualquier parte de la imagen que sobresalga */
        }
        
        .empresa-image img {
          width: 100%; /* La imagen llena el ancho del contenedor */
          height: 100%; /* La imagen llena el alto del contenedor */
          object-fit: cover; /* Recorta la imagen para que llene el espacio sin distorsi��n */
        }
        
        .empresa-info {
          flex: 1; /* Ocupa la mitad del contenedor */
          display: flex;
          flex-direction: column;
          justify-content: center; /* Centra verticalmente el texto */
        }
  </style>
</head>
<body>

    
   <!-- SIDERBAR -->    
            <?php include '../include/sidebar_dashboard_tesorero.php'; ?>
<!-- CIERRA SIDEBAR -->
    
<div class="main">
    
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

           <hr/>
  <h4>Empresas que ofrecen servicios útiles para el curso</h4>
  <p>Empresas que ofrecen servicios variados orientados a colegios y cursos, desde tecnología educativa hasta actividades extracurriculares.
Apoyan el aprendizaje, la organización escolar y el bienestar de los estudiantes en el entorno académico.</p>
  <form class="mb-4" method="GET">
    <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre o descripción" value="<?= htmlspecialchars($buscar) ?>">
  </form>

  <?php foreach ($empresas as $e): ?>
  <a href="empresa_detail_tesorero.php?id=<?= $e['id'] ?>" class="text-decoration-none text-dark">
    <div class="empresa-card shadow-sm rounded p-3 d-flex align-items-center">
      <!-- Contenedor del texto -->
      <div class="empresa-info flex-grow-1">
        <h5 class="mb-0"><?= htmlspecialchars($e['nombre']) ?></h5>
        <small class="text-muted"><?= $e['region'] ?> - <?= $e['comuna'] ?></small>
        <p class="mb-0 mt-2"><?= substr(htmlspecialchars($e['descripcion']), 0, 120) ?>...</p>
      </div>
      <!-- Contenedor de la imagen -->
      <div class="empresa-image">
        <img src="../uploads/empresas/<?= $e['imagen_secundaria'] ?>" class="img-fluid" title="<?= htmlspecialchars($e['nombre']) ?> Imagen">
      </div>
    </div>
  </a>
<?php endforeach; ?>

  <!-- Paginación -->
  <?php if ($totalPages > 1): ?>
    <nav>
      <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="?buscar=<?= urlencode($buscar) ?>&page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
  
   <?php include '../include/footer.php'; ?>
</div>
</body>
</html>
