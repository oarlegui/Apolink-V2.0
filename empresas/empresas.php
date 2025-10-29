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

$alumno_id = $_SESSION['alumno_id']; // Usar el alumno seleccionado en la sesión
$apoderado_id = $_SESSION['usuario_id'];
$colegio_id = $_SESSION['colegio_id'];
$rol = $_SESSION['rol'];

//CIERRE DE DEBE IR OBLIGATORIAMENTE EN TODOS LOS ARCHIVOS

if (!in_array($rol, ['admin', 'admin_curso', 'tesorero', 'apoderado'])) {
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
  

  <style>
    .empresa-card { border-left: 4px solid #0d6efd; padding: 10px; margin-bottom: 15px; background-color: #f8f9fa; }
    .empresa-card:hover { background-color: #e9ecef; cursor: pointer; }
  </style>
</head>
<body>

    
<!-- SIDERBAR -->    
            <?php include '../include/sidebar.php'; ?>
<!-- CIERRA SIDEBAR -->
    
<div class="main">
    
<!-- TOPBAR -->  
    <?php 
        // Consultar los datos del estudiante
        $sql_student = "
            SELECT 
                s.id AS student_id, 
                s.nombre AS student_name, 
                c.nombre AS course,
                s.curso_id AS course_id,
                col.nombre AS school_name, 
                col.logo_colegio AS school_logo
            FROM students s
            JOIN colegios col ON s.colegio_id = col.id
            JOIN cursos c ON s.curso_id = c.id
            JOIN alumno_apoderado aa ON s.id = aa.alumno_id
            WHERE aa.apoderado_id = ?
            LIMIT 1
        ";
        $stmt_student = $conn->prepare($sql_student);
        $stmt_student->bind_param("i", $apoderado_id);
        $stmt_student->execute();
        $result_student = $stmt_student->get_result();
        
        // Verificar y asignar los datos del alumno
        if ($result_student->num_rows > 0) {
            $alumno = $result_student->fetch_assoc();
        } else {
            $alumno = [
                'student_id' => 'N/A',
                'student_name' => 'No disponible',
                'course' => 'No disponible',
                'course_id' => 'No disponible',
                'school_name' => 'No disponible',
                'school_logo' => 'https://cdn-icons-png.flaticon.com/512/1930/1930026.png', // Imagen por defecto
            ];
        }
        
        $cursos = get_assigned_courses($conn, $apoderado_id, $rol);
        $curso_list = implode(",", $cursos);
    
    ?>
<div class="card bg-light shadow-sm mb-4 p-3 d-flex flex-row">
        <div class="d-flex align-items-center">
            <div class="me-3">
              <img 
                src="../<?= !empty($alumno['school_logo']) ? $alumno['school_logo'] : 'https://cdn-icons-png.flaticon.com/512/1930/1930026.png' ?>" 
                alt="Logo Colegio" 
                class="rounded-circle" 
                style="width: 60px; height: 60px; object-fit: cover;">
            </div>
            <div>
                
                <?php
                // Obtener lista de alumnos asociados al apoderado
                $stmt_alumnos = $conn->prepare("
                  SELECT 
                      s.id AS alumno_id,
                      s.nombre AS alumno,
                      s.curso_id AS curso_id
                  FROM students s
                  JOIN alumno_apoderado aa ON aa.alumno_id = s.id
                  WHERE aa.apoderado_id = ?
                ");
                $stmt_alumnos->bind_param("i", $apoderado_id);
                $stmt_alumnos->execute();
                $alumnos = $stmt_alumnos->get_result()->fetch_all(MYSQLI_ASSOC);
                
                ?>
              <h4 class="mb-1">👋 Bienvenido, <span class="text-primary"><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></span></h4>
              
                
            
            
              <?php
            // Verificar y asignar valores predeterminados
            $alumno_nombre = isset($alumno['student_name']) ? htmlspecialchars($alumno['student_name'], ENT_QUOTES, 'UTF-8') : 'No disponible';
            $curso_nombre = isset($alumno['course']) ? htmlspecialchars($alumno['course'], ENT_QUOTES, 'UTF-8') : 'No asignado';
            $colegio_nombre = isset($alumno['school_name']) ? htmlspecialchars($alumno['school_name'], ENT_QUOTES, 'UTF-8') : 'No especificado';
            ?>
            
            <p class="text-muted mb-0">
                Alumno: <strong><?= $alumno_nombre ?></strong>
                <?php if (count($alumnos) > 1): ?>
                    <a href="#" class="text-primary ms-2" data-bs-toggle="modal" data-bs-target="#seleccionarAlumnoModal">
                        Cambiar Alumno
                    </a>
                <?php endif; ?>
                <br>
                Curso: <strong><?= $curso_nombre ?></strong> | 
                Colegio: <strong><?= $colegio_nombre ?></strong>
            </p>
            </div> 
      </div>
                
         
            <!-- Modal para cambiar de alumno -->
    <div class="modal fade" id="seleccionarAlumnoModal" tabindex="-1" aria-labelledby="seleccionarAlumnoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="seleccionarAlumnoModalLabel">Seleccionar Alumno</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="seleccionarAlumnoForm" method="POST" action="seleccionar_alumno.php">
                        <?php foreach ($alumnos as $alumno_item): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="alumno_id" id="alumno-<?= $alumno_item['alumno_id'] ?>" value="<?= $alumno_item['alumno_id'] ?>" <?= ($alumno_item['alumno_id'] == $alumno_id) ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="alumno-<?= $alumno_item['alumno_id'] ?>">
                                    <?= htmlspecialchars($alumno_item['alumno']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary mt-3">Continuar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
    
    
    <!-- SLIDER DE NOTICIAS --> 
    
    <style>
          /* Contenedor principal de la barra de noticias */
        .news-bar-container {
          width: 100%;
          overflow: hidden;
          background-color: #f8f9fa;
          border: 1px solid #dee2e6;
          padding: 10px 0;
          position: relative;
          box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Barra de noticias que se desplaza */
        .news-bar {
          display: flex;
          gap: 50px; /* Espaciado entre noticias */
          animation: scroll-left 15s linear infinite; /* Animaci贸n de desplazamiento */
          white-space: nowrap;
        }
        
        /* Estilo de cada noticia */
        .news-item {
          display: inline-block;
          font-size: 1rem;
          color: #212529;
          font-weight: bold;
          cursor: pointer;
        }
        
        /* Animaci贸n: desplazamiento de derecha a izquierda */
        @keyframes scroll-left {
          from {
            transform: translateX(100%);
          }
          to {
            transform: translateX(-100%);
          }
        }
        
        /* Pausar animaci贸n al pasar el mouse */
        .news-bar:hover {
          animation-play-state: paused;
        }
        
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
        <div class="news-bar-container">
          <div class="news-bar">
            <?php 
            // Consulta para obtener las evaluaciones con descripción corta y completa
            $sql_evaluaciones = "
                SELECT titulo, tipo, fecha, descip_corta, descripcion 
                FROM eventos 
                WHERE tipo = ? AND curso_id = ? AND fecha >= CURDATE()
                ORDER BY fecha ASC 
                LIMIT 5
            ";
        
            // Preparar la consulta
            $stmt = $conn->prepare($sql_evaluaciones);
            $tipo = 'evaluacion';
            $curso_id = $alumno['course_id'] ?? null; // Validar si $curso_id está disponible
        
            if ($curso_id) {
                // Enlazar parámetros y ejecutar consulta
                $stmt->bind_param("si", $tipo, $curso_id);
                $stmt->execute();
                $result_evaluaciones = $stmt->get_result();
        
                // Mostrar resultados si los hay
                if ($result_evaluaciones && $result_evaluaciones->num_rows > 0):
                    while ($evaluacion = $result_evaluaciones->fetch_assoc()): ?>
                      <span 
                        class="news-item" 
                        data-titulo="<?= htmlspecialchars($evaluacion['titulo']) ?>" 
                        data-descip_corta="<?= htmlspecialchars($evaluacion['descip_corta']) ?>" 
                        data-fecha="<?= date('d-m-Y', strtotime($evaluacion['fecha'])) ?>" 
                        data-descripcion="<?= htmlspecialchars($evaluacion['descripcion']) ?>">
                        <strong><?= htmlspecialchars($evaluacion['titulo']) ?></strong>: 
                        <?= htmlspecialchars($evaluacion['descip_corta']) ?>
                      </span> ||
                    <?php endwhile;
                else: ?>
                  <span class="news-item">No hay evaluaciones programadas próximamente.</span>
                <?php endif;
        
            } else {
                // Manejar caso en que $curso_id no esté definido
                echo '<span class="news-item">Error: No se ha definido un curso válido.</span>';
            }
            ?>
          </div>
        </div>
        
        <!-- Modal para mostrar la descripci贸n completa -->
        <div class="modal fade" id="newsModal" tabindex="-1" aria-labelledby="newsModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="newsModalLabel">T铆tulo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                    <h5><strong><span id="newsModaldescip_corta"></span></strong></h5>
                    <p><strong>Fecha:</strong> <span id="newsModalFecha"></span></p>
                    <p id="newsModalDescripcion"></p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
              </div>
            </div>
          </div>
        </div>
        
       
        
        <script>
        // Manejar clic en una noticia
        document.querySelectorAll('.news-item').forEach(item => {
          item.addEventListener('click', function() {
            const titulo = this.getAttribute('data-titulo');
            const descip_corta = this.getAttribute('data-descip_corta');
            const fecha = this.getAttribute('data-fecha');
            const descripcion = this.getAttribute('data-descripcion');
        
            // Rellenar el modal con los datos de la noticia
            document.getElementById('newsModalLabel').innerText = titulo;
            document.getElementById('newsModaldescip_corta').innerText = descip_corta;
            document.getElementById('newsModalFecha').innerText = fecha;
            document.getElementById('newsModalDescripcion').innerText = descripcion;
        
            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('newsModal'));
            modal.show();
          });
        });
        </script>
<!-- CIERRA TOPBAR -->
     
      
      <hr/>
  <h4>Empresas que ofrecen servicios útiles para el curso</h4>
  <p>Empresas que ofrecen servicios variados orientados a colegios y cursos, desde tecnología educativa hasta actividades extracurriculares.
Apoyan el aprendizaje, la organización escolar y el bienestar de los estudiantes en el entorno académico.</p>
  <form class="mb-4" method="GET">
    <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre o descripción" value="<?= htmlspecialchars($buscar) ?>">
  </form>

  <?php foreach ($empresas as $e): ?>
  <a href="empresa_detail.php?id=<?= $e['id'] ?>" class="text-decoration-none text-dark">
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
