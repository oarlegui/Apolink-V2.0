<?php

//DEBE IR OBLIGATORIAMENTE EN TODOS LOS ARCHIVOS

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'include/conexion.php';
require 'include/seguridad_dashboard.php';
require 'include/get_assigned_courses.php';
require 'include/get_modulos.php';

$alumno_id = $_SESSION['alumno_id']; // Usar el alumno seleccionado en la sesi贸n
$apoderado_id = $_SESSION['usuario_id'];
$colegio_id = $_SESSION['colegio_id'];
$rol = $_SESSION['rol'];
$usuario_id = $_SESSION['usuario_id'];
// Obtener la fecha actual
$fecha_actual = date("Y-m-d");
// Obtener el día y mes actual
$dia_mes_actual = date("m-d");

//CIERRE DE DEBE IR OBLIGATORIAMENTE EN TODOS LOS ARCHIVOS

require_login();
require_rol(['apoderado']);

$modulos['eventos-evaluaciones'] = !empty($modulos['eventos-evaluaciones']) ? $modulos['eventos-evaluaciones'] : 0;
$modulos['gira-de-estudio'] = !empty($modulos['gira-de-estudio']) ? $modulos['gira-de-estudio'] : 0;
$modulos['gastos'] = !empty($modulos['gastos']) ? $modulos['gastos'] : 0;
$modulos['galeria-eventos'] = !empty($modulos['galeria-eventos']) ? $modulos['galeria-eventos'] : 0;



if (!in_array($rol, ['admin', 'admin_curso', 'tesorero', 'apoderado'])) {
    die("Acceso denegado.");
    header("Location: index.php");
}

// Validar que el alumno está seleccionado
/*if (!isset($_SESSION['alumno_id'])) {
    header("Location: index2.php");
    exit;
}*/


// Obtener el curso_id del alumno
           // $alumno_id = $_GET['alumno_id'] ?? null; // Asegúrate de que el ID del alumno esté disponible
            $curso_id = null;
        
            if ($alumno_id) {
                $query_curso = "SELECT curso_id FROM students WHERE id = ?";
                $stmt_curso = $conn->prepare($query_curso);
                $stmt_curso->bind_param("i", $alumno_id);
                $stmt_curso->execute();
                $result_curso = $stmt_curso->get_result();
                $curso_id = $result_curso->fetch_assoc()['curso_id'] ?? null;
            }



$stmt = $conn->prepare("
    SELECT 
    s.id AS alumno_id, 
    s.nombre AS alumno, 
    s.cuota_total, 
    s.cuota_gira,
    c.nombre AS curso, 
    col.nombre AS colegio, 
    col.logo_colegio AS logo_colegio,
    IFNULL((SELECT SUM(p.monto) 
            FROM pagos p 
            WHERE p.alumno_id = s.id AND p.estado = 'aprobado' AND p.es_cuota_curso = 1 ), 0) AS pagado
FROM 
    students s
JOIN 
    cursos c ON s.curso_id = c.id
JOIN 
    colegios col ON s.colegio_id = col.id
WHERE 
    s.id = ?; ");
    
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();

$cuota_total = (int) $alumno['cuota_total'];
$pagado = (int) $alumno['pagado'];
$pendiente = $cuota_total - $pagado;
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

// Consulta SQL para obtener los aportes

$query = "
    SELECT 
        s.id AS alumno_id,
        s.nombre AS alumno,
        IFNULL(SUM(CASE WHEN MONTH(a.fecha) = MONTH(CURDATE()) AND YEAR(a.fecha) = YEAR(CURDATE()) THEN a.cantidad ELSE 0 END), 0) AS aporte_mensual,
        IFNULL(SUM(CASE WHEN YEAR(a.fecha) = YEAR(CURDATE()) THEN a.cantidad ELSE 0 END), 0) AS aporte_anual
    FROM students s
    LEFT JOIN aportes a ON s.id = a.alumno_id AND a.estado = 'aprobado'
    WHERE s.id = ?
    GROUP BY s.id
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error en la preparación de la consulta: " . $conn->error);
}

$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$result = $stmt->get_result();

$data = $result->fetch_all(MYSQLI_ASSOC); // Obtener los resultados como un arreglo asociativo

// Validar si hay datos
if (empty($data)) {
    echo "Estimado Usuario, aún no tiene un alumno asignado a tu ID: " . $apoderado_id;
    exit;
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Apoderado - Apolink</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
    .card-img-overlay {
      background: linear-gradient(0deg, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.2));
      color: white;
      display: flex;
      align-items: flex-end;
      justify-content: center;
    }
    .card_box {
      max-width: 300px;
      height: 300px;
    }
  </style>
</head>
<body>

<!-- SIDERBAR -->    
            <?php include 'include/sidebar_dashboard_apoderado.php'; ?>
<!-- CIERRA SIDEBAR -->

<div class="main">  
<!-- TOPBAR -->  
    <?php 
        // Consultar los datos del estudiante
        $sql_student = "
            SELECT 
                s.id AS student_id, 
                s.nombre AS student_name, 
                s.primer_apellido AS primero_apellido_alumno,
                s.segundo_apellido AS segundo_apellido_alumno,
                c.nombre AS course,
                s.curso_id AS course_id,
                col.nombre AS school_name, 
                col.logo_colegio AS school_logo
            FROM students s
            JOIN colegios col ON s.colegio_id = col.id
            JOIN cursos c ON s.curso_id = c.id
            JOIN alumno_apoderado aa ON s.id = aa.alumno_id
            WHERE aa.alumno_id = ?
            LIMIT 1
        ";
        $stmt_student = $conn->prepare($sql_student);
        $stmt_student->bind_param("i", $alumno_id);
        $stmt_student->execute();
        $result_student = $stmt_student->get_result();
        
        // Verificar y asignar los datos del alumno
        if ($result_student->num_rows > 0) {
            $alumno = $result_student->fetch_assoc();
        } else {
            $alumno = [
                'student_id' => 'N/A',
                'student_name' => 'No disponible',
                'primero_apellido_alumno' => 'No disponible',
                'segundo_apellido_alumno' => 'No disponible',
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
                src="<?= !empty($alumno['school_logo']) ? $alumno['school_logo'] : 'https://cdn-icons-png.flaticon.com/512/1930/1930026.png' ?>" 
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
                      s.curso_id AS curso_id,
                      s.primer_apellido AS alumno_apellido1,
                      s.segundo_apellido AS alumno_apellido2
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
            $alumno_apellido1 = isset($alumno['primero_apellido_alumno']) ? htmlspecialchars($alumno['primero_apellido_alumno'], ENT_QUOTES, 'UTF-8') : 'No disponible';
            $alumno_apellido2 = isset($alumno['segundo_apellido_alumno']) ? htmlspecialchars($alumno['segundo_apellido_alumno'], ENT_QUOTES, 'UTF-8') : 'No disponible';
            $curso_nombre = isset($alumno['course']) ? htmlspecialchars($alumno['course'], ENT_QUOTES, 'UTF-8') : 'No asignado';
            $colegio_nombre = isset($alumno['school_name']) ? htmlspecialchars($alumno['school_name'], ENT_QUOTES, 'UTF-8') : 'No especificado';
            ?>
            
            <p class="text-muted mb-0">
                Alumno: <strong><?= $alumno_nombre.' '.$alumno_apellido1.' '.$alumno_apellido2 ?></strong>
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
                                    <?= htmlspecialchars($alumno_item['alumno']) ?> <?= htmlspecialchars($alumno_item['alumno_apellido1']) ?> <?= htmlspecialchars($alumno_item['alumno_apellido2']) ?>
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
                  <span class="news-item">No hay evaluaciones programadas próximamente.</span>
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



  

   <!-- Estado de pago -->
<div class="row mt-4 mb-4 g-3">
  <!-- Total Cuota Anual -->
  
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap Icons (opcional, solo si usas iconos extra) -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        
        <div class="col-md-2">
          <div 
            class="card text-white bg-primary shadow-sm" 
            data-bs-toggle="tooltip" 
            data-bs-placement="bottom"
            title="¡Aquí tienes el gran total anual de tu cuota de curso! Este monto representa el aporte completo para el año, ideal para tener claridad y planificar sin sorpresas. 😉📅">
            <div class="card-body text-center">
              <h6 class="mb-2">Total Cuota Anual</h6>
              <h5 class="fw-bold">$<?= number_format($cuota_total, 0, ',', '.') ?>.-</h5>
            </div>
          </div>
        </div>

        <script>
          // Inicializar tooltips de Bootstrap
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
          tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
          })
        </script>
  
  

    <!-- Pagado -->
        <div class="col-md-2">
          <div 
            class="card text-white bg-success shadow-sm" 
            data-bs-toggle="tooltip" 
            data-bs-placement="bottom"
            title="¡Bien hecho! Este monto es lo que ya has pagado de la cuota anual del curso. Cada aporte suma y nos acerca más a lograr todas las actividades y metas del año. 🥳💪">
            <div class="card-body text-center">
              <h6 class="mb-2">Abonos a Cuota Curso</h6>
              <h5 class="fw-bold">$<?= number_format($pagado, 0, ',', '.') ?>.-</h5>
            </div>
          </div>
        </div>

        <script>
          // Inicializar tooltips de Bootstrap
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
          tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
          })
        </script>
    
    <!-- Pendiente o A favor -->
   
 
        
        <div class="col-md-2">
          <?php if ($pendiente < 0): ?>
            <div 
              class="card text-white bg-info shadow-sm"
              data-bs-toggle="tooltip"
              data-bs-placement="bottom"
              title="¡Felicidades! Tienes saldo a favor. Puedes relajarte y disfrutar de las actividades sin preocupaciones. 😎✨">
              <div class="card-body text-center">
                <h6 class="mb-2">A favor</h6>
                <h5 class="fw-bold">$<?= number_format(abs($pendiente), 0, ',', '.') ?>.-</h5>
              </div>
            </div>
          <?php elseif ($pendiente > 0): ?>
            <div 
              class="card text-white bg-danger shadow-sm"
              data-bs-toggle="tooltip"
              data-bs-placement="bottom"
              title="¡Atención! Tienes un saldo pendiente. Aporta para ponerte al día y no perderte ninguna de las actividades del curso. 💡🔔">
              <div class="card-body text-center">
                <h6 class="mb-2">Pendiente a Cuota Curso</h6>
                <h5 class="fw-bold">$<?= number_format($pendiente, 0, ',', '.') ?>.-</h5>
              </div>
            </div>
          <?php else: ?>
            <div 
              class="card text-white bg-secondary shadow-sm"
              data-bs-toggle="tooltip"
              data-bs-placement="bottom"
              title="¡Estás al día! No tienes deudas ni saldo a favor en la cuota del curso. 🎉">
              <div class="card-body text-center">
                <h6 class="mb-2">Sin saldo pendiente</h6>
                <h5 class="fw-bold">$0.-</h5>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <script>
          // Inicializar tooltips de Bootstrap
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
          tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
          })
        </script>
  
    <!-- Avance -->
  
 
        
        <div class="col-md-2">
          <div 
            class="card text-white bg-warning shadow-sm"
            data-bs-toggle="tooltip"
            data-bs-placement="bottom"
            title="¡Vamos avanzando! Este porcentaje refleja cuánto hemos completado de la meta del curso. ¡Cada granito de arena cuenta para llegar a la cima! 🚀📈">
            <div class="card-body text-center">
              <h6 class="mb-2">Avance</h6>
              <h5 class="fw-bold"><?= $porcentaje ?>%</h5>
            </div>
          </div>
        </div>

        <script>
          // Inicializar tooltips de Bootstrap
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
          tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
          })
        </script>

<!-- Tarjeta de Aporte Extra Mensual -->
<?php if (!empty($data)): ?>
    <?php foreach ($data as $row): ?>
        
        <div class="col-md-2">
          <a 
            href="aportes_extras/aportes_extras.php?alumno_id=<?= htmlspecialchars($alumno_id) ?>" 
            class="text-decoration-none"
            data-bs-toggle="tooltip"
            data-bs-placement="bottom"
            title="¡Gracias por tu aporte extra! Este monto ayuda a potenciar aún más las actividades del curso. Cada granito de arena hace la diferencia. 🌟🎁">
            <div class="card text-white bg-secondary shadow-sm">
              <div class="card-body text-center">
                <h6 class="mb-2">Aporte Extra Mensual</h6>
                <h5 class="fw-bold">$<?= number_format($row['aporte_mensual'], 0, ',', '.') ?>.-</h5>
              </div>
            </div>
          </a>
        </div>
        
        <script>
          // Inicializar tooltips de Bootstrap
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
          tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
          })
        </script>
        
        <div class="col-md-2">
          <div 
            class="card text-white bg-secondary shadow-sm"
            data-bs-toggle="tooltip"
            data-bs-placement="bottom"
            title="¡Gracias por tu aporte extra anual! Este gesto ayuda a hacer posible nuevas experiencias y sorpresas durante el año. Cada aporte suma, y tu generosidad marca la diferencia. 🎉🎁">
            <div class="card-body text-center">
              <h6 class="mb-2">Aporte Extra Anual</h6>
              <h5 class="fw-bold">$<?= number_format($row['aporte_anual'], 0, ',', '.') ?>.-</h5>
            </div>
          </div>
        </div>
        
        <script>
          // Inicializar tooltips de Bootstrap
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
          tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
          })
        </script>
    <?php endforeach; ?>
<?php else: ?>
    <p class="text-muted">No se encontraron aportes para mostrar.</p>
<?php endif; ?>
</div>


 <?php



// Verificar conexión
/*if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$sql = "SELECT activo FROM curso_modulo WHERE curso_id = ? AND colegio_id = ? AND modulo = 'gira-de-estudio'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $curso_id, $colegio_id);
$stmt->execute();
$result = $stmt->get_result();
$gira_estado = $result->fetch_assoc()['activo'] ?? 0; // Por defecto, 0 si no se encuentra el módulo

$stmt->close();*/

?>

<div class="row">
  <!-- Gráfico Estado de Pago -->
  <div class="col-md-6">
    <div class="card shadow-sm p-4 mb-4">
     <h6 class="mb-3">
  
  📊 Tu Estado de Pago Actual

<!-- Icono de información con tooltip -->
  <span 
    data-bs-toggle="tooltip" 
    data-bs-placement="right"
    title="¡Descubre tu progreso! Aquí puedes ver cuánto has aportado a la cuota del curso y cuál es tu saldo pendiente. ¿Vas al día, te falta poco, o tienes superávit? ¡Revisa tu estado y ayúdanos a llegar juntos a la meta! 📈">
    <i class="bi bi-info-circle-fill me-2 text-primary" style="font-size: 1.2em;"></i>
  </span>
</h6>
<!-- Bootstrap JS (con Popper incluido) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Inicializar tooltips de Bootstrap
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  })
</script>
      <canvas id="graficoEstadoPago" height="200"></canvas>
    </div>
  </div>

  <!-- Gráfico Gira de Estudios -->
  <div class="col-md-6">
    <div class="card shadow-sm p-4 mb-4 <?php if ($modulos['gira-de-estudio'] == 0) echo 'bg-light'; ?>">
      <?php if ($modulos['gira-de-estudio'] == 1): ?>
        <h6 class="mb-3">
  
 <!-- Icono de mochila animado -->
  <span class="icon-bounce1 ms-2">🎒</span> Avance Gira de Estudios Individual
  - Meta: $ <?= number_format($meta_gira, 0, ',', '.') ?>.-
  <!-- Icono de información con tooltip -->
  <span 
    data-bs-toggle="tooltip" 
    data-bs-placement="right" 
    title="¡Este es el avance de nuestra Gira de Estudios! Aquí puedes ver cuánto hemos juntado y cuánto falta para alcanzar la meta. Cada aporte nos acerca más a la aventura soñada de la generación 🎓🚍🌄">
    <i class="bi bi-info-circle-fill me-2 text-primary" style="font-size: 1.2em;"></i>
  </span>
</h6>

<!-- Bootstrap JS (con Popper incluido) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Inicializar tooltips de Bootstrap
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  })
</script>
<style>
/* Animación para el icono 🎒 */
@keyframes bounce {
  0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
  40% {transform: translateY(-10px);}
  60% {transform: translateY(-5px);}
}
.icon-bounce {
  display: inline-block;
  animation: bounce 2s infinite;
  color: #1e7e34;
}
</style>
        <?php if ($meta_gira > 0): ?>
          <canvas id="graficoGiraAlumno" height="200"></canvas>
        <?php else: ?>
          <p class="text-muted">Aún no se ha definido la meta de gira para este alumno.</p>
        <?php endif; ?>
      <?php else: ?>
        <h6 class="mb-3 text-muted">🎒 Avance Gira de Estudios</h6>
        <p class="text-muted">
          <strong>Módulo no disponible</strong>
        </p>
        <p class="text-muted">Este módulo está deshabilitado actualmente, contacta al Administrador del Curso.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  new Chart(document.getElementById('graficoEstadoPago').getContext('2d'), {
    type: 'bar',
    data: {
      labels: ['Pagado', 'Pendiente'],
      datasets: [{
        label: 'Cuota Curso',
        data: [<?= $pagado ?>, <?= $pendiente ?>],
        backgroundColor: ['#198754', '#DC3545']
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  <?php if ($meta_gira > 0): ?>
  new Chart(document.getElementById('graficoGiraAlumno').getContext('2d'), {
    type: 'bar',
    data: {
      labels: ['Pagado', 'Pendiente'],
      datasets: [{
        label: 'Gira Estudio',
        data: [<?= $aporte ?>, <?= $pendiente_gira ?>],
        backgroundColor: ['#0d6efd', '#adb5bd']
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
  <?php endif; ?>
</script>


<script>
  // Estado de pago actual
  new Chart(document.getElementById('graficoEstadoPago').getContext('2d'), {
    type: 'bar',
    data: {
      labels: ['Pagado', 'Pendiente'],
      datasets: [{
        label: 'Estado de cuota',
        data: [<?= $pagado ?>, <?= $pendiente ?>],
        backgroundColor: ['#198754', '#DC3545']
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  // Gira de estudios (simulado)
 <?php if ($gira_activa): ?>
  new Chart(document.getElementById('graficoGira').getContext('2d'), {
    type: 'bar',
    data: {
      labels: ['Guardado', 'Meta'],
      datasets: [{
        label: 'Fondo Gira',
        data: [<?= $monto_actual ?>, <?= $monto_meta ?>],
        backgroundColor: ['#0d6efd', '#adb5bd']
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
<?php endif; ?>



  <!-- Espacio reservado para segundo gráfico -->
  <div class="card shadow-sm p-4 mb-4">
    <h6 class="mb-3">📈 Historial mensual de mis pagos</h6>
    <p class="text-muted">(próximamente)</p>

    <a href="pagos_historial.php" class="btn btn-sm btn-outline-secondary mt-3">Ver más</a>
  </div>

  <!-- Script gráfico -->
  <script>
    const ctx = document.getElementById('graficoEstadoPago').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Pagado', 'Pendiente'],
        datasets: [{
          label: 'Estado de cuota',
          data: [<?= $pagado ?>, <?= $pendiente ?>],
          backgroundColor: ['#198754', '#DC3545']
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  </script>
<?php 
//MODULO DE GASTOS  
  
  
// Obtener el año y mes actual
$mes_actual = date("m");
$anio_actual = date("Y");

// Consulta SQL para obtener los gastos del mes actual
$query_gastos = "
    SELECT g.id, g.descripcion, g.monto, g.fecha
    FROM gastos g
    WHERE MONTH(g.fecha) = ? AND YEAR(g.fecha) = ? AND g.curso_id = ?
    ORDER BY g.fecha DESC
    LIMIT 3
";
$stmt_gastos = $conn->prepare($query_gastos);
$stmt_gastos->bind_param("iii", $mes_actual, $anio_actual,$curso_id);
$stmt_gastos->execute();
$result_gastos = $stmt_gastos->get_result();

// Verificar si hay resultados
$gastos_mes = $result_gastos->fetch_all(MYSQLI_ASSOC);
$total_gastos = 0;
foreach ($gastos_mes as $gasto) {
    $total_gastos += $gasto['monto'];
}
$stmt_gastos->close();



///////////////////////////////////////////////////////////////////
// Consulta para obtener el total de pagos aprobados del curso
$sql_pagos = "
    SELECT 
        SUM(monto) AS total_pagos
    FROM 
        pagos
    WHERE 
        estado = 'aprobado' 
        AND curso_id = ?
";

// Preparar y ejecutar la consulta
$stmt_pagos = $conn->prepare($sql_pagos);
$stmt_pagos->bind_param("i", $curso_id);
$stmt_pagos->execute();
$result_pagos = $stmt_pagos->get_result();
$total_pagos = $result_pagos->fetch_assoc()['total_pagos'] ?? 0; // Si no hay resultados, total_pagos será 0

// Consulta para obtener el total de gastos del curso
$sql_gastos = "
    SELECT 
        SUM(monto) AS total_gastos
    FROM 
        gastos
    WHERE 
        curso_id = ?
   
";

// Preparar y ejecutar la consulta
$stmt_gastos = $conn->prepare($sql_gastos);
$stmt_gastos->bind_param("i", $curso_id);
$stmt_gastos->execute();
$result_gastos = $stmt_gastos->get_result();
$total_gastos = $result_gastos->fetch_assoc()['total_gastos'] ?? 0; // Si no hay resultados, total_gastos será 0

// Cerrar conexiones
$stmt_pagos->close();
$stmt_gastos->close();
//$conn->close();
?>




<div class="row">
    <!-- Módulo de Gastos del Mes -->
    <div class="col-md-6">
        <?php if ($modulos['gastos'] == 1): ?>
            <div class="card shadow-sm p-4 mb-4">
                <h6 class="mb-3 text-muted">
                  <span style="color:#28a745; animation: bounce 2s infinite;">💸</span>
                  Gasto Mensual del Curso (últimos 3)
                  <span data-bs-toggle="tooltip" data-bs-placement="right" 
                    title="El Gasto Mensual del Curso representa el monto total que se ha utilizado este mes para cubrir todas las necesidades y actividades del curso: desde materiales, celebraciones, hasta pequeñas sorpresas para los alumnos. Es una forma transparente y sencilla de ver en qué se invierte el fondo común del curso cada mes. ¡Así todos podemos aportar y estar informados sobre el bienestar de nuestros hijos!">
                    <i class="bi bi-info-circle-fill me-2 text-primary" style="font-size: 1.2em;"></i>
                  </span>
                </h6>
                <script>
              var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
              tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
              })
                </script>
                <style>
                @keyframes bounce {
                  0%, 20%, 50%, 80%, 100% {transform: translateY(0);}  
                  40% {transform: translateY(-10px);}
                  60% {transform: translateY(-5px);}
                }
                </style>
                <?php if (!empty($gastos_mes)): ?>
                    <ul class="list-group">
                        <?php foreach ($gastos_mes as $gasto): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($gasto['descripcion'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <br />
                                    <small class="text-muted"><?= date("d-m-Y", strtotime($gasto['fecha'])) ?></small>
                                </div>
                                <span class="badge bg-danger">$<?= number_format($gasto['monto'], 0, ',', '.') ?></span>
                            </li>
                        <?php endforeach; ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Total</strong>
                            <span class="badge bg-primary">$<?= number_format($total_gastos, 0, ',', '.') ?></span>
                        </li>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No hay gastos registrados para este mes.</p>
                <?php endif; ?>

                <!-- Botón para ver todos los gastos -->
                <a href="gastos/gastos.php" class="btn btn-primary mt-3" hidden>Ver Todos los Gastos</a>
            </div>
        <?php else: ?>
            <div class="card shadow-sm p-4 mb-4 bg-light">
                <h6 class="mb-3 text-muted">
  <span style="color:#28a745; animation: bounce 2s infinite;">💸</span>
  Gasto Mensual del Curssso 
  <span data-bs-toggle="tooltip" data-bs-placement="right" 
    title="Aquí ves el total de gastos realizados en el mes por el curso, para que estés siempre informado.">
    <i class="bi bi-info-circle ms-1"></i>
  </span>
</h6>
<style>
@keyframes bounce {
  0%, 20%, 50%, 80%, 100% {transform: translateY(0);}  
  40% {transform: translateY(-10px);}
  60% {transform: translateY(-5px);}
}
</style>
                <p class="text-muted"><strong>Módulo no disponible</strong></p>
                <p class="text-muted">Este módulo está deshabilitado actualmente, contacta al Administrador del Curso.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Gráfico Comparativo -->
    <div class="col-md-6">
        <?php if ($modulos['gastos'] == 1): ?>
            <div class="card shadow-sm p-4 mb-4">
                <h6 class="mb-3">
                   📊 Comparativo Curso: Gastos vs Pagos
                  
                  <span 
                    data-bs-toggle="tooltip" 
                    data-bs-placement="right"
                    title="¡Descubre cómo va la balanza! Aquí puedes comparar los gastos realizados por el curso con los pagos recibidos. Así sabrás si vamos viento en popa o si necesitamos reforzar el cofre. ⚖️💰">
                    <i class="bi bi-info-circle-fill me-2 text-primary" style="font-size: 1.2em;"></i>
                  </span>
                </h6>
                
                <!-- Bootstrap JS (con Popper incluido) -->
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
                <script>
                  // Inicializar tooltips de Bootstrap
                  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                  tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl)
                  })
                </script>
                <div class="chart-container">
                    <canvas id="gastosPagosChart"></canvas>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm p-4 mb-4 bg-light">
                <h6 class="mb-3 text-muted">📊 Comparativo Curso: Gastos vs Pagos</h6>
                <p class="text-muted"><strong>Módulo no disponible</strong></p>
                <p class="text-muted">Este módulo está deshabilitado actualmente, contacta al Administrador del Curso.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Datos para el gráfico
    const gastos = <?= $total_gastos ?? 0 ?>; // Total de gastos
    const pagos = <?= $total_pagos ?? 0 ?>; // Total de pagos (asegúrate de definir esta variable en PHP)

    // Configuración del gráfico
    const ctx = document.getElementById('gastosPagosChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Gastos', 'Pagos'],
            datasets: [{
                label: 'Monto ($)',
                data: [gastos, pagos],
                backgroundColor: ['#dc3545', '#28a745'], // Colores rojo y verde
                borderColor: ['#dc3545', '#28a745'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true, // Asegura que el gráfico mantenga sus proporciones
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Monto ($)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Categoría'
                    }
                }
            }
        }
    });
</script>

<style>
    .chart-container {
        width: 100%; /* Ancho completo */
        max-width: 600px; /* Ancho máximo */
        height: auto; /* Altura automática */
        max-height: 300px; /* Altura máxima */
        overflow: hidden; /* Evita desbordamiento */
        margin: auto; /* Centra el gráfico */
    }
    .d-flex {
        gap: 15px; /* Espaciado entre los elementos de las columnas */
    }
</style>
  
  
  
  
 <?php if ($modulos['eventos-evaluaciones'] == 1): ?>
  <!-- MODULO PRÓXIMOS EVENTOS Y EVALUACIONES -->
  <div class="card shadow-sm p-4 mb-4">
    <h6 class="mb-3">📅 Próximos eventos y evaluaciones
        <span 
        data-bs-toggle="tooltip" 
        data-bs-placement="right"
        title="¡No te pierdas nada! Aquí verás los próximos eventos y evaluaciones del curso para que estés siempre preparado y puedas planificarte como un pro. 🚀📚">
        <i class="bi bi-info-circle-fill me-2 text-primary" style="font-size: 1.2em;"></i>
        </span>
    </h6>
    
        <script>
              // Inicializar tooltips de Bootstrap
              var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
              tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
              })
        </script>

    <div class="list-group">
      <?php
      // Establecer la configuración regional para que los meses se muestren en español
      setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'spanish');

      // Verificar si la extensión intl está habilitada
      if (class_exists('IntlDateFormatter')) {
          // Crear formateador de fechas con localización en español
          $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
          $formatter->setPattern('d MMM'); // Formato: "22 abr"
      } else {
          $formatter = null; // Si intl no está disponible, dejamos como null
      }

      $stmt = $conn->prepare("
        SELECT titulo, fecha, descip_corta, tipo, descripcion
        FROM eventos
        WHERE curso_id IN (
          SELECT s.curso_id FROM students s
          JOIN alumno_apoderado aa ON s.id = aa.alumno_id
          WHERE s.id = ?
        )
        AND fecha >= CURDATE()
        ORDER BY fecha ASC
        LIMIT 5
      ");
      $stmt->bind_param("i", $alumno_id);
      $stmt->execute();
      $eventos = $stmt->get_result();

      if ($eventos->num_rows > 0):
        while ($e = $eventos->fetch_assoc()): 
          // Asignar colores según el tipo
          $badgeColor = '';
          $icon = '';
          switch ($e['tipo']) {
            case 'evento':
              $badgeColor = 'bg-primary'; // Azul
              $icon = '📅'; // Icono para eventos
              break;
            case 'evaluación':
              $badgeColor = 'bg-danger'; // Rojo
              $icon = '✏️'; // Icono para evaluaciones
              break;
            case 'otro':
              $badgeColor = 'bg-secondary'; // Gris
              $icon = '🔖'; // Icono para otros
              break;
            default:
              $badgeColor = 'bg-light text-dark'; // Color por defecto
              $icon = '❓'; // Icono por defecto
              break;
          }

          // Convertir la fecha al formato deseado
          $fecha = new DateTime($e['fecha']);
          $fecha_formateada = $formatter ? $formatter->format($fecha) : $fecha->format('d M'); // Fallback si intl no está disponible
      ?>
        <!-- Cada evento como una tarjeta de Bootstrap -->
        <div 
          class="list-group-item list-group-item-action d-flex justify-content-between align-items-start border rounded mb-2 shadow-sm" 
          data-bs-toggle="tooltip" 
          title="<?= htmlspecialchars($e['descripcion'] ?? 'Sin descripción') ?>"
        >
          <!-- Icono y detalles del evento -->
          <div class="d-flex">
            <span class="me-3 fs-3"><?= $icon ?></span>
            <div>
              <h6 class="mb-1"><strong><?= htmlspecialchars($e['descip_corta']) ?></strong></h6>
              <p class="mb-0 text-muted"><?= htmlspecialchars($fecha_formateada) ?></p>
            </div>
          </div>
          <!-- Tipo del evento con badge -->
          <span class="badge <?= $badgeColor ?> text-white ms-auto align-self-center"><?= ucfirst($e['tipo']) ?></span>
        </div>
      <?php endwhile; else: ?>
        <div class="alert alert-warning text-center" role="alert">
          No hay eventos próximos registrados.
        </div>
      <?php endif;
      $stmt->close();
      ?>
    </div>
    <a href="eventos/calendar.php"  class="btn btn-primary mt-3">Ver más</a>
  </div>
<?php else: ?>
  <!-- Módulo deshabilitado -->
  <div class="card shadow-sm p-4 mb-4 bg-light">
    <h6 class="mb-3 text-muted">📅 Próximos eventos y evaluaciones</h6>
    <p class="text-muted"><strong>Módulo no disponible</strong></p>
    <p class="text-muted">Este módulo está deshabilitado actualmente, contacta al Administrador del Curso.</p>
  </div>
<?php endif; ?>


  <!-- MODULO DE SERVICIOS ÚTILES DESTACADOS -->
  
<div class="card shadow-sm p-4 mb-5">
  <h6 class="mb-3">🏪 Servicios útiles recomendados
    <span 
        data-bs-toggle="tooltip" 
        data-bs-placement="right"
        title="Aquí encontrarás servicios y recursos recomendados especialmente para el curso. ¡Aprovecha estas sugerencias para hacer tu vida más fácil! 🏪✨">
        <i class="bi bi-info-circle-fill me-2 text-primary" style="font-size: 1.2em;"></i>
    </span>
  </h6>
  
    <script>
      // Inicializar tooltips de Bootstrap
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
      tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
      })
    </script>

  <div class="row">
    <?php
    // Consulta para obtener las empresas patrocinadas
    $stmt = $conn->prepare("
      SELECT id, nombre, clics_telefono, clics_email, descripcion, imagen_principal, telefono, email
      FROM empresas
      WHERE patrocinada = 1
      ORDER BY RAND()
      LIMIT 4
    ");
    $stmt->execute();
    $empresas = $stmt->get_result();

    if ($empresas->num_rows > 0):
      while ($empresa = $empresas->fetch_assoc()): ?>
        <div class="col-md-3 mb-4">
          <div class="card shadow-sm">
            <!-- Imagen principal de la empresa -->
            <img 
              src="uploads/empresas/<?= htmlspecialchars($empresa['imagen_principal']) ?>" 
              class="card-img-top img-fluid" 
              alt="<?= htmlspecialchars($empresa['nombre']) ?>" 
              style="max-height: 200px; object-fit: cover;"
            >
            <div class="card-body text-center">
              <!-- Nombre de la empresa -->
              <h5 class="card-title"><?= htmlspecialchars($empresa['nombre']) ?></h5>
              <!-- Descripción corta -->
              <p class="card-text text-muted"><?= htmlspecialchars($empresa['descripcion']) ?></p>
              <p class="card-text text-muted">📞 <?= htmlspecialchars($empresa['clics_telefono']) ?> clics - 📧 <?= htmlspecialchars($empresa['clics_email']) ?> clics</p>
              <!-- Botones de acción -->
              <div class="d-flex justify-content-between mt-3">
                <a href="tel:<?= htmlspecialchars($empresa['telefono']) ?>" class="btn btn-sm btn-outline-primary" title="Llamar">
                  📞 Teléfono
                </a>
                <a href="mailto:<?= htmlspecialchars($empresa['email']) ?>" class="btn btn-sm btn-outline-success" title="Enviar Email">
                  📧 Email
                </a>
                <a href="empresas/empresa_detail.php?id=<?= htmlspecialchars($empresa['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Ver más">
                  👁️ Ver más
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; 
    else: ?>
      <div class="col-12">
        <p class="text-muted text-center">No hay empresas patrocinadas disponibles en este momento.</p>
      </div>
    <?php endif;
    $stmt->close();
    ?>
  </div>
  <a href="empresas/empresas.php" class="btn btn-primary mt-3">Ver Todas las Empresas</a>
</div>

<?php if ($modulos['galeria-eventos'] == 1): ?>
 <!-- MODULO DE GALERÍA DE EVENTOS -->
        <div class="card shadow-sm p-4 mb-5">
            <h6 class="mb-3">🖼️ Galería de eventos recientes
                <span 
                    data-bs-toggle="tooltip" 
                    data-bs-placement="right"
                    title="¡Revive los mejores momentos del curso! En esta galería podrás ver fotos de eventos recientes: paseos, salidas, kermesse y muchas aventuras más. ¡Cada imagen es un recuerdo para celebrar juntos! 📸🎉">
                    <i class="bi bi-info-circle-fill me-2 text-primary" style="font-size: 1.2em;"></i>
                </span>
            </h6>
          
            <script>
              // Inicializar tooltips de Bootstrap
              var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
              tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
              })
            </script>
          <div class="row">
            <?php
            // Verificar si se obtuvo el curso_id
            if ($curso_id) {
                // Consulta para obtener las galerías según el curso del alumno
                $query = "
                    SELECT g.id AS galeria_id, g.titulo, g.fecha_creacion, g.descripcion, MIN(ig.ruta_imagen) AS imagen_random
                    FROM galerias g
                    JOIN imagenes_galeria ig ON g.id = ig.galeria_id
                    WHERE g.curso_id = ?
                    GROUP BY g.id
                    ORDER BY g.fecha_creacion DESC
                    LIMIT 3
                ";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $curso_id);
                $stmt->execute();
                $galerias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
                // Verificar si hay resultados
                if ($galerias && count($galerias) > 0):
                  foreach ($galerias as $evento): ?>
                    <div class="col-md-4 mb-4">
                      <div class="card shadow-sm">
                        <div class="position-relative">
                          <img src="<?= htmlspecialchars($evento['imagen_random']) ?>" class="card-img-top" alt="<?= htmlspecialchars($evento['titulo']) ?>" data-bs-toggle="modal" data-bs-target="#modalEvento<?= $evento['galeria_id'] ?>">
                          <div class="overlay-text position-absolute bottom-0 start-0 w-100 text-white text-center p-2">
                            <h5 class="mb-1"><?= htmlspecialchars($evento['titulo']) ?></h5>
                            <p class="mb-0"><?= date('d M Y', strtotime($evento['fecha_creacion'])) ?></p>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach;
                else: ?>
                <div class="alert alert-warning text-center" role="alert">
                  No hay galerías creadas para este curso.
                  </div>
                <?php endif;
            } else {
                echo '<p class="text-muted">No se pudo determinar el curso del alumno.</p>';
            }
            ?>
          </div>
      <!--  </div>-->

               <style>
          /* Estilo para el modal */
          .modal-header-custom {
            background: linear-gradient(45deg, #007bff, #6610f2);
            color: white;
            border-bottom: none;
          }
          .modal-header-custom .btn-close {
            filter: brightness(0.8);
            transition: filter 0.3s ease;
          }
          .modal-header-custom .btn-close:hover {
            filter: brightness(1.2);
          }
          .modal-body-custom {
            padding: 20px;
            background-color: #f8f9fa;
          }
          .carousel-item img {
            border-radius: 10px;
            max-height: 500px; /* Ajustar altura máxima */
            object-fit: cover;
            width: 100%; /* Asegura que ocupe todo el ancho */
          }
          .modal-footer-custom {
            border-top: none;
            background-color: #f1f1f1;
            padding: 15px;
          }
          .modal-footer-custom .btn {
            transition: background-color 0.3s ease, color 0.3s ease;
          }
          .modal-footer-custom .btn:hover {
            background-color: #6610f2;
            color: white;
          }
        
          /* Estilo para las flechas del carrusel */
          .carousel-control-prev-icon,
          .carousel-control-next-icon {
            filter: brightness(0) invert(1); /* Hacerlas blancas */
          }
        
          .carousel-control-prev-icon:hover,
          .carousel-control-next-icon:hover {
            filter: brightness(1.5) invert(1); /* Más visibles al pasar el ratón */
          }
        
          .carousel-control-prev,
          .carousel-control-next {
            background-color: rgba(0, 0, 0, 0.5); /* Fondo semitransparente */
            border-radius: 50%;
            width: 50px;
            height: 50px;
          }
        
          .carousel-control-prev:hover,
          .carousel-control-next:hover {
            background-color: rgba(0, 0, 0, 0.8); /* Fondo más oscuro al pasar el ratón */
          }
        </style>

<!-- Modal -->
<div class="modal fade" id="modalEvento<?= $evento['galeria_id'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= $evento['galeria_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-xl"> <!-- Modal amplio -->
    <div class="modal-content">
      <!-- Encabezado del modal -->
      <div class="modal-header modal-header-custom">
        <h5 class="modal-title" id="modalLabel<?= $evento['galeria_id'] ?>"><?= htmlspecialchars($evento['titulo']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Cuerpo del modal -->
      <div class="modal-body modal-body-custom">
        <p><strong>Fecha el evento:</strong> <?= date('d M Y', strtotime($evento['fecha_creacion'])) ?></p>
        <label><strong></strong> <?= $evento['descripcion'] ?></label>
        <hr/>
        <!-- Slider de imágenes -->
        <div id="carouselEvento<?= $evento['galeria_id'] ?>" class="carousel slide" data-bs-ride="carousel">
          <div class="carousel-inner">
            <?php
            // Obtener imágenes del evento, máximo 10
            $stmt_imgs = $conn->prepare("SELECT ruta_imagen FROM imagenes_galeria WHERE galeria_id = ? LIMIT 10");
            $stmt_imgs->bind_param("i", $evento['galeria_id']);
            $stmt_imgs->execute();
            $imagenes = $stmt_imgs->get_result();
            $active = true; // Para establecer la primera imagen como activa
            while ($img = $imagenes->fetch_assoc()):
            ?>
              <div class="carousel-item <?= $active ? 'active' : '' ?>">
                <img src="<?= htmlspecialchars($img['ruta_imagen']) ?>" class="d-block w-100" alt="Imagen del evento">
              </div>
              <?php $active = false; // Solo la primera imagen será activa ?>
            <?php endwhile; ?>
            <?php $stmt_imgs->close(); ?>
          </div>
          
          <!-- Controles del slider -->
          <button class="carousel-control-prev" type="button" data-bs-target="#carouselEvento<?= $evento['galeria_id'] ?>" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#carouselEvento<?= $evento['galeria_id'] ?>" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
          </button>
        </div>
      </div>
      
      <!-- Pie del modal -->
      <div class="modal-footer modal-footer-custom">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <a href="galeria/event_detail.php?galeria_id=<?= $evento['galeria_id'] ?>" class="btn btn-primary">Ver Galería Completa</a>
      </div>
    </div>
  

      <div class="col-12">
        <p class="text-muted">No se encontraron galerías recientes.</p>
      </div>
   
  </div>
  
</div>
<a href="galeria/gallery.php?curso_id=<?= $curso_id ?>" class="btn btn-primary mt-3">Ver Galería Completa</a>
</div>
<?php else: ?>
    <!-- MÓDULO DESHABILITADO -->
    <div class="card shadow-sm p-4 mb-5 bg-light">
        <h6 class="mb-3 text-muted">🖼️ Galería de eventos recientes</h6>
        <p class="text-muted"><strong>Módulo no disponible</strong></p>
        <p class="text-muted">Este módulo está deshabilitado actualmente, contacta al Administrador del Curso.</p>
    </div>
<?php endif; ?>

<style>
  /* Estilos para la superposición de texto */
  .overlay-text {
    background: rgba(0, 0, 0, 0.6); /* Fondo negro semitransparente */
    color: white;
    text-shadow: 1px 1px 5px rgba(0, 0, 0, 0.8); /* Sombras para mayor legibilidad */
  }

  .card-img-top {
    object-fit: cover;
    height: 200px;
  }
</style>
 <?php include 'include/footer.php'; ?>
    </div>
    
  </div>

  <!-- Inicializar GLightbox -->
  <script>
    const lightbox = GLightbox({ selector: '.glightbox' });
  </script>
  
  <script>
  // Inicializar todos los tooltips en la página
  document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });
</script>
</div>

</body>
</html>

