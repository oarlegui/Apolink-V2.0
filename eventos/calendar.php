<?php
// archivo: calendar.php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../include/conexion.php';
require '../include/seguridad.php';
require '../include/get_assigned_courses.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$apoderado_id = $_SESSION['usuario_id'];
$alumno_id = $_SESSION['alumno_id'] ?? null;

// === FILTROS ===
$filter = $_GET['filter'] ?? 'mes'; // mes, semana, dia
$page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$per_page = 10;
$today = date('Y-m-d');

// Obtener año y mes correctamente cuando filtro es 'mes'
if ($filter == 'mes' && isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    list($year, $month) = explode('-', $_GET['month']);
    $year = intval($year);
    $month = intval($month);
} else {
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $month = isset($_GET['month']) && is_numeric($_GET['month']) ? intval($_GET['month']) : date('m');
}

// Rango fechas
switch($filter) {
    case 'semana':
        // Semana seleccionada o actual
        if (isset($_GET['from']) && isset($_GET['to'])) {
            $from = $_GET['from'];
            $to = $_GET['to'];
        } else {
            // Semana actual
            $from = date('Y-m-d', strtotime('monday this week'));
            $to = date('Y-m-d', strtotime('sunday this week'));
        }
        break;
    case 'dia':
        $from = $_GET['date'] ?? $today;
        $to = $from;
        break;
    case 'mes':
    default:
        $from = date('Y-m-01', strtotime("$year-$month-01"));
        $to = date('Y-m-t', strtotime("$year-$month-01"));
        break;
}

// Cursos asignados
$cursos = get_assigned_courses($conn, $usuario_id, $rol);
$curso_list = implode(",", $cursos);

$curso_id = isset($_GET['curso_id']) ? intval($_GET['curso_id']) : null;

// === CONDICIONES SQL ===
$where = "e.fecha BETWEEN ? AND ?";
$params = [$from, $to];
$types = "ss";

if ($rol === "admin" && $curso_id) {
    $where .= " AND e.curso_id = ?";
    $params[] = $curso_id;
    $types .= "i";
} else {
    $where .= " AND e.curso_id IN ($curso_list)";
}

// Total eventos
$sql_count = "SELECT COUNT(*) as total FROM eventos e WHERE $where";
$stmt_c = $conn->prepare($sql_count);
$stmt_c->bind_param($types, ...$params);
$stmt_c->execute();
$total_eventos = $stmt_c->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_c->close();

$total_paginas = max(1, ceil($total_eventos / $per_page));
$offset = ($page-1) * $per_page;

// Eventos
$sql = "SELECT e.*, c.nombre AS curso 
        FROM eventos e 
        JOIN cursos c ON e.curso_id = c.id 
        WHERE $where 
        ORDER BY e.fecha ASC 
        LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$eventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Cursos para filtro (admin)
$all_cursos = [];
if ($rol === 'admin') {
    $all_cursos = $conn->query("SELECT id, nombre FROM cursos ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calendario de Eventos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../include/css/styles.css">
    <style>
        .event-box { margin-bottom: 1.5rem; padding: 1rem; border-radius: 0.5rem; }
    </style>
</head>
<body>
<?php include '../include/sidebar.php'; ?>
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
                <!-- <?php if (count($alumnos) > 1): ?>
                    <a href="#" class="text-primary ms-2" data-bs-toggle="modal" data-bs-target="#seleccionarAlumnoModal">
                        Cambiar Alumno
                    </a>
                <?php endif; ?>-->
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
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Calendario de Eventos/Evaluaciones</h4>
            <?php if (in_array($rol, ['admin', 'admin_curso'])): ?>
                <a href="add_calendar_event.php" class="btn btn-primary">+ Agregar Evento</a>
            <?php endif; ?>
        </div>
        <!-- Filtros -->
        <form class="row g-2 align-items-end mb-4" method="get" id="filtrosForm">
            <div class="col-auto">
                <label for="filter" class="form-label mb-0">Vista:</label>
                <select name="filter" id="filter" class="form-select">
                    <option value="mes" <?= $filter == 'mes' ? 'selected' : '' ?>>Mes</option>
                    <option value="semana" <?= $filter == 'semana' ? 'selected' : '' ?>>Semana</option>
                    <option value="dia" <?= $filter == 'dia' ? 'selected' : '' ?>>Día</option>
                </select>
            </div>
            <?php if ($filter == 'mes'): ?>
                <div class="col-auto">
                    <label for="month" class="form-label mb-0">Mes:</label>
                    <input type="month" class="form-control" name="month" id="month"
                        value="<?= "$year-".str_pad($month,2,'0',STR_PAD_LEFT) ?>">
                </div>
            <?php elseif ($filter == 'semana'): ?>
                <div class="col-auto">
                    <label for="from" class="form-label mb-0">Semana:</label>
                    <input type="date" class="form-control" name="from" id="from" value="<?= $from ?>">
                    <input type="date" class="form-control" name="to" id="to" value="<?= $to ?>">
                </div>
            <?php elseif ($filter == 'dia'): ?>
                <div class="col-auto">
                    <label for="date" class="form-label mb-0">Día:</label>
                    <input type="date" class="form-control" name="date" id="date" value="<?= $from ?>">
                </div>
            <?php endif; ?>
            <?php if ($rol === 'admin'): ?>
                <div class="col-auto">
                    <label for="curso_id" class="form-label mb-0">Curso:</label>
                    <select name="curso_id" id="curso_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($all_cursos as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $curso_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary">Filtrar</button>
            </div>
        </form>
        <!-- /Filtros -->

        <?php if (empty($eventos)): ?>
            <div class="alert alert-info">No hay eventos registrados para este período.</div>
        <?php endif; ?>

        <?php foreach ($eventos as $e): ?>
            <div class="event-box bg-light shadow-sm">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong><?= date('d-m-Y', strtotime($e['fecha'])) ?></strong> — <?= htmlspecialchars($e['titulo']) ?><br>
                        <small class="text-muted"><?= $e['curso'] ?></small>
                    </div>
                    <?php if (in_array($rol, ['admin', 'admin_curso'])): ?>
                        <a href="edit_calendar_event.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                    <?php endif; ?>
                </div>
                <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($e['descripcion'] ?? 'Descripción no disponible')) ?></p>
            </div>
        <?php endforeach; ?>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item<?= $i == $page ? ' active' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?' . http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    <?php include '../include/footer.php'; ?>
</div>
<script>
document.getElementById('filter').addEventListener('change', function() {
    // Cambiar inputs del filtro al cambiar la vista
    document.getElementById('filtrosForm').submit();
});
</script>
</body>
</html>