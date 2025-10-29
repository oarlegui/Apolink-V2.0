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

$alumno_id = $_SESSION['alumno_id']; // Usar el alumno seleccionado en la sesión
$usuario_id = $_SESSION['usuario_id'];
$apoderado_id = $_SESSION['usuario_id'];
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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
        <link rel="stylesheet" href="../include/css/styles.css">
  <style>
    .galeria img { height: 150px; object-fit: cover; border-radius: 10px; }
  </style>
</head>
<body>
    
    <!-- SIDERBAR -->    
            <?php include '../include/sidebar.php'; ?>
        <!-- CIERRA SIDEBAR -->
        
<div class="container mt-4">
    
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
          animation: scroll-left 15s linear infinite; /* Animaci璐竛 de desplazamiento */
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
        
        /* Animaci璐竛: desplazamiento de derecha a izquierda */
        @keyframes scroll-left {
          from {
            transform: translateX(100%);
          }
          to {
            transform: translateX(-100%);
          }
        }
        
        /* Pausar animaci璐竛 al pasar el mouse */
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
          object-fit: cover; /* Recorta la imagen para que llene el espacio sin distorsión */
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
            // Consulta para obtener las evaluaciones con descripci贸n corta y completa
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
            $curso_id = $alumno['course_id'] ?? null; // Validar si $curso_id est谩 disponible
        
            if ($curso_id) {
                // Enlazar par谩metros y ejecutar consulta
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
                  <span class="news-item">No hay evaluaciones programadas proximamente.</span>
                <?php endif;
        
            } else {
                // Manejar caso en que $curso_id no est茅 definido
                echo '<span class="news-item">Error: No se ha definido un curso v谩lido.</span>';
            }
            ?>
          </div>
        </div>
        
        <!-- Modal para mostrar la descripci璐竛 completa -->
        <div class="modal fade" id="newsModal" tabindex="-1" aria-labelledby="newsModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="newsModalLabel">T閾唗ulo</h5>
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
