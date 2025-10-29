<?php
// archivo: calendar_historial.php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../include/conexion.php';
require '../include/seguridad.php';
require '../include/get_assigned_courses.php';

$alumno_id = $_SESSION['alumno_id'];
$usuario_id = $_SESSION['usuario_id'];
$apoderado_id = $_SESSION['usuario_id'];
$colegio_id = $_SESSION['colegio_id'];
$rol = $_SESSION['rol'];

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$curso_id = isset($_GET['curso_id']) ? intval($_GET['curso_id']) : null;

// PAGINACIÓN
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Cursos asignados
$cursos = get_assigned_courses($conn, $usuario_id, $rol);
$curso_list = implode(",", $cursos);

// SQL base y conteo total
if ($rol === 'admin') {
    $sql_count = "SELECT COUNT(*) as total FROM eventos e JOIN cursos c ON e.curso_id = c.id";
    $sql_eventos = "SELECT e.*, c.nombre AS curso FROM eventos e JOIN cursos c ON e.curso_id = c.id ORDER BY e.fecha ASC LIMIT $per_page OFFSET $offset";
} else {
    $sql_count = "SELECT COUNT(*) as total FROM eventos e JOIN cursos c ON e.curso_id = c.id WHERE e.curso_id = ?";
    $sql_eventos = "SELECT e.*, c.nombre AS curso FROM eventos e JOIN cursos c ON e.curso_id = c.id WHERE e.curso_id = ? ORDER BY e.fecha DESC LIMIT $per_page OFFSET $offset";
}

// Total de eventos para paginación
if ($rol === 'admin') {
    $result_count = $conn->query($sql_count);
} else {
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("i", $curso_id);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
}
$total_eventos = ($result_count && $result_count->num_rows > 0) ? $result_count->fetch_assoc()['total'] : 0;
$total_paginas = max(1, ceil($total_eventos / $per_page));

// Obtener eventos paginados
if ($rol === 'admin') {
    $eventos = $conn->query($sql_eventos)->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare($sql_eventos);
    $stmt->bind_param("i", $curso_id);
    $stmt->execute();
    $eventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Calendario de Eventos</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
        <link rel="stylesheet" href="../include/css/styles.css">
    </head>
    <body>
        <!-- SIDERBAR -->    
            <?php include '../include/sidebar.php'; ?>
        <!-- CIERRA SIDEBAR -->
        <!-- Contenido -->
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
        .news-bar-container {
          width: 100%;
          overflow: hidden;
          background-color: #f8f9fa;
          border: 1px solid #dee2e6;
          padding: 10px 0;
          position: relative;
          box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .news-bar {
          display: flex;
          gap: 50px;
          animation: scroll-left 15s linear infinite;
          white-space: nowrap;
        }
        .news-item {
          display: inline-block;
          font-size: 1rem;
          color: #212529;
          font-weight: bold;
          cursor: pointer;
        }
        @keyframes scroll-left {
          from { transform: translateX(100%);}
          to   { transform: translateX(-100%);}
        }
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
          height: 150px;
        }
        .empresa-image {
          flex: 1;
          height: 100%;
          overflow: hidden;
        }
        .empresa-image img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }
        .empresa-info {
          flex: 1;
          display: flex;
          flex-direction: column;
          justify-content: center;
        }
    </style>
        <div class="news-bar-container">
          <div class="news-bar">
            <?php 
            $sql_evaluaciones = "
                SELECT titulo, tipo, fecha, descip_corta, descripcion 
                FROM eventos 
                WHERE tipo = ? AND curso_id = ? AND fecha >= CURDATE()
                ORDER BY fecha ASC 
                LIMIT 5
            ";
            $stmt = $conn->prepare($sql_evaluaciones);
            $tipo = 'evaluacion';
            $curso_id = $alumno['course_id'] ?? null;
            if ($curso_id) {
                $stmt->bind_param("si", $tipo, $curso_id);
                $stmt->execute();
                $result_evaluaciones = $stmt->get_result();
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
                echo '<span class="news-item">Error: No se ha definido un curso válido.</span>';
            }
            ?>
          </div>
        </div>
        <!-- Modal para mostrar la descripci璐竛 completa -->
        <div class="modal fade" id="newsModal" tabindex="-1" aria-labelledby="newsModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="newsModalLabel">Título</h5>
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
        document.querySelectorAll('.news-item').forEach(item => {
          item.addEventListener('click', function() {
            const titulo = this.getAttribute('data-titulo');
            const descip_corta = this.getAttribute('data-descip_corta');
            const fecha = this.getAttribute('data-fecha');
            const descripcion = this.getAttribute('data-descripcion');
            document.getElementById('newsModalLabel').innerText = titulo;
            document.getElementById('newsModaldescip_corta').innerText = descip_corta;
            document.getElementById('newsModalFecha').innerText = fecha;
            document.getElementById('newsModalDescripcion').innerText = descripcion;
            const modal = new bootstrap.Modal(document.getElementById('newsModal'));
            modal.show();
          });
        });
        </script>
<!-- CIERRA TOPBAR -->
            <div class="container mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Historial de Eventos/Evaluaciones</h4>
                        <?php if (in_array($rol, ['admin', 'admin_curso'])): ?>
                            <a href="add_calendar_event.php" class="btn btn-primary">+ Agregar Evento</a>
                        <?php endif; ?>
                </div>
                <?php if (empty($eventos)): ?>
                    <div class="alert alert-info">No hay eventos registrados.</div>
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
                <!-- PAGINACIÓN -->
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
    </body>
</html>