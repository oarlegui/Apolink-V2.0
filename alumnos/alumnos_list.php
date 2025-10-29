<?php
// archivo: list_students.php
// Este archivo lista todos los alumnos de un curso específico.


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
$tesorero_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$usuario_id = $_SESSION['usuario_id'];
// Obtener la fecha actual
$fecha_actual = date("Y-m-d");
// Obtener el d��a y mes actual
$dia_mes_actual = date("m-d");

//CIERRE DE DEBE IR OBLIGATORIAMENTE EN TODOS LOS ARCHIVOS

// Verificar si el usuario está autenticado
require_login();

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



// Obtener los cursos asignados al usuario
$cursos = get_assigned_courses($conn, $usuario_id, $rol);

// Verificar si hay un curso seleccionado
$curso_id = $_GET['curso_id'] ?? null;


// Consultar los alumnos del curso
$query = "SELECT * FROM students WHERE curso_id = ? ORDER By primer_apellido";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $curso_id);
$stmt->execute();
$result = $stmt->get_result();
$alumnos = $result->fetch_all(MYSQLI_ASSOC);

if (!$stmt) {
    die("Error al preparar la consulta: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Alumnos </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../include/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>
    
<!-- CONTENIDO APODERADO  -->
  <?php if ($rol == "apoderado") { ?>
  
   <!-- SIDEBAR APODERADOS -->
    <?php include '../include/sidebar.php'; ?>

    <!-- Contenido principal -->
    <div class="main">
        <!-- TOPBAR APODERADOS-->  
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
                    <form id="seleccionarAlumnoForm" method="POST" action="../seleccionar_alumno.php">
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
               /* Ocultar todo excepto la tabla al imprimir */
        @media print {
            body * {
                display: none; /* Oculta todo */
            }
            .printable-area {
                display: block; /* Muestra solo el ��rea imprimible */
            }
            .printable-area table {
                width: 100%; /* Asegurarse de que la tabla use el ancho completo al imprimir */
                border-collapse: collapse;
            }
            .printable-area table th, .printable-area table td {
                border: 1px solid black; /* Asegura bordes visibles al imprimir */
                padding: 8px;
                text-align: left;
            }
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
<!-- CIERRA TOPBAR APODERADOS -->
<!-- CIERRA CONTENIDO APODERADO -->

<!--  //////////////////////////////////////////////////////////-->


 <!-- CONTENIDO ADMINISTRADOR  -->
 <?php 
 } elseif ($rol == 'admin' || $rol == 'admin_curso' || $rol == 'tesorero') { 
 ?>
 
  <!-- SENTENCIAS TESORERO  -->
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
if ($result_courses->num_rows === 0) {
    die("No tiene cursos asignados");
}

// Convertir el resultado de la consulta a un array asociativo
$cursos = $result_courses->fetch_all(MYSQLI_ASSOC);

// Extraer los IDs de los cursos asignados al tesorero
$curso_ids = array_column($cursos, 'course_id');

// Convertir los IDs en una cadena separada por comas para usar en las consultas SQL
$curso_list = implode(',', $curso_ids);

?>
 
 <!-- CIERRE SENTENCIAS TESORERO  -->
 
 
<!-- TOPBAR TOPBAR ADMINISTRADORES -->
   
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
      <div class="modal-header">
        <h5 class="modal-title" id="cambiarCursoModalLabel">Cambiar Curso</h5>
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
<!-- CIERRA TOPBAR ADMINISTRADORES-->


<?php 
} 
?>

 <!-- CIERRE CONTENIDO ADMINISTRADORES -->
 
        <?php 
        
        
           // if (!$curso_id || !in_array($curso_id, $cursos)) {
             //   die("Acceso denegado o curso no encontrado.");
            //}
            
         
            // Consultar los alumnos del curso
            $query = "SELECT 
                        s.id AS alumno_id,
                        s.rut,
                        s.nombre AS alumno_nombre,
                        s.primer_apellido AS alumno_primer_apellido,
                        s.segundo_apellido AS alumno_segundo_apellido,
                        s.fecha_nac,
                        s.email,
                        s.telefono,
                        GROUP_CONCAT(CONCAT(u.nombre) SEPARATOR '\n ') AS apoderados_nombre,
                        GROUP_CONCAT(CONCAT(u.email) SEPARATOR '\n ') AS apoderado_email,
                        GROUP_CONCAT(CONCAT(u.telefono) SEPARATOR '\n ') AS apoderado_telefono
                    FROM 
                        students s
                    LEFT JOIN 
                        alumno_apoderado aa ON s.id = aa.alumno_id
                    LEFT JOIN 
                        users u ON aa.apoderado_id = u.id
                    WHERE 
                        s.curso_id = ?
                    
                    GROUP BY 
                        s.id, s.rut, s.nombre, s.primer_apellido, s.segundo_apellido, s.fecha_nac, s.email";
                        
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $curso_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $alumnos = $result->fetch_all(MYSQLI_ASSOC);
            
            if (!$stmt) {
                die("Error al preparar la consulta: " . $conn->error);
            }
        ?>
        
        <div class="container mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Lista de Alumnos del Curso - <?= $curso_nombre ?> </h4>
                <!-- Bot��n para imprimir la lista -->
                <button class="btn btn-outline-secondary" onclick="window.print()">Imprimir Lista</button>
            </div>
            <?php if (empty($alumnos)): ?>
                <div class="alert alert-info">No hay alumnos registrados en este curso.</div>
                
            <?php else: ?>
            <div class="printable-table">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Rut</th>
                            <th>Nombre</th>
                            <th style="max-width: 100px;">Fecha Nacimiento</th>
                            <th style="border-right: 3px solid #ffc107;">Email</th>
                            <th>Apoderado(s)</th>
                            <th>Telefono</th>
                            <th>Email</th>
                            <?php if (in_array($rol, ['admin', 'admin_curso'])): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumnos as $alumno): ?>
                            <tr>
                                <td><?= !empty($alumno['rut']) ? htmlspecialchars($alumno['rut']) : 'No disponible'; ?></td>
                                <td><?= !empty($alumno['alumno_nombre']) ? htmlspecialchars($alumno['alumno_nombre']) : 'No disponible'; ?> <?= !empty($alumno['alumno_primer_apellido']) ? htmlspecialchars($alumno['alumno_primer_apellido']) : ''; ?> <?= !empty($alumno['alumno_segundo_apellido']) ? htmlspecialchars($alumno['alumno_segundo_apellido']) : ''; ?></td>
                                <td style="max-width: 50px;"> 
                                    <?php 
                                    if (!empty($alumno['fecha_nac'])) {
                                        // Formatear la fecha de nacimiento
                                        $fecha_formateada = date("d-m-Y", strtotime($alumno['fecha_nac']));
                                        $dia_mes_nac = date("m-d", strtotime($alumno['fecha_nac']));
                                        
                                        // Verificar si el d��a y mes coinciden con la fecha actual
                                        if ($dia_mes_nac === $dia_mes_actual): ?>
                                            <span style="color: green; font-weight: bold;"><i class="fas fa-balloon"></i> <?= $fecha_formateada; ?> <i class="fas fa-gift"></i></span>
                                        <?php else: ?>
                                            <?= $fecha_formateada; ?>
                                        <?php endif; ?>
                                    <?php } else { ?>
                                        No disponible
                                    <?php } ?>
                                </td>
                                <td style="border-right: 3px solid #ffc107;"><?= !empty($alumno['email']) ? htmlspecialchars($alumno['email']) : 'No disponible'; ?></td>
                                <td><?= !empty($alumno['apoderados_nombre']) ? nl2br(htmlspecialchars($alumno['apoderados_nombre'])) : 'No asignado'; ?></td>
                                <td><?= !empty($alumno['apoderado_telefono']) ? nl2br(htmlspecialchars($alumno['apoderado_telefono'])) : 'No disponible'; ?></td>
                                <td><?= !empty($alumno['apoderado_email']) ? nl2br(htmlspecialchars($alumno['apoderado_email'])) : 'No disponible'; ?></td>
                                <?php if (in_array($rol, ['admin', 'admin_curso'])): ?>
                                    <td>
                                        <a href="edit_student.php?id=<?= $alumno['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        </div>
        <?php include '../include/footer.php'; ?>
    </div>
</body>
</html>