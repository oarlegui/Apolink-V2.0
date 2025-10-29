<?php

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'include/conexion.php';
require 'include/seguridad_dashboard.php';

$tesorero_id = $_SESSION['usuario_id'];
$colegio_id = $_SESSION['colegio_id'];
$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$alumno_id = $_POST['alumno_id'] ?? null;

require_login();
require_rol(['tesorero']);

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
$stmt_courses->bind_param("i", $tesorero_id);
$stmt_courses->execute();
$result_courses = $stmt_courses->get_result();

if ($result_courses->num_rows === 0) {
    die("No tiene cursos asignados");
}
$cursos = $result_courses->fetch_all(MYSQLI_ASSOC);

// Selección de curso
if (isset($_POST['curso_id'])) {
    $curso_id_seleccionado = intval($_POST['curso_id']);
} elseif (isset($_POST['curso_seleccionado'])) {
    $curso_id_seleccionado = intval($_POST['curso_seleccionado']);
} elseif (!empty($cursos)) {
    $curso_id_seleccionado = $cursos[0]['course_id'];
} else {
    $curso_id_seleccionado = 0;
}

// AJAX: total por categoría (antes de HTML)
if (isset($_POST['ajax_categoria']) && isset($_POST['curso_id'])) {
    $cat = $_POST['ajax_categoria'];
    $curso = intval($_POST['curso_id']);
    $stmt = $conn->prepare("SELECT IFNULL(SUM(monto),0) AS total FROM gastos WHERE curso_id = ? AND categoria = ?");
    $stmt->bind_param("is", $curso, $cat);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    echo number_format($total ?? 0, 0, ',', '.');
    exit;
}

// Obtener categorías únicas del curso seleccionado
$stmt_cats = $conn->prepare("SELECT DISTINCT categoria FROM gastos WHERE curso_id = ? AND categoria IS NOT NULL AND categoria != ''");
$stmt_cats->bind_param("i", $curso_id_seleccionado);
$stmt_cats->execute();
$res_cats = $stmt_cats->get_result();
$categorias_unicas = [];
while ($row = $res_cats->fetch_assoc()) {
    $categorias_unicas[] = $row['categoria'];
}
$stmt_cats->close();

// Totales pagos y gastos
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

// Datos mensuales pagos para el curso seleccionado
$pagos_mes = array_fill(1, 12, 0);
$stmt_pagos_curso = $conn->prepare("
    SELECT MONTH(fecha_pago) AS mes, SUM(monto) AS total
    FROM pagos
    WHERE curso_id = ? AND YEAR(fecha_pago) = YEAR(NOW())
    GROUP BY mes
");
$stmt_pagos_curso->bind_param("i", $curso_id_seleccionado);
$stmt_pagos_curso->execute();
$result3 = $stmt_pagos_curso->get_result();
while ($row = $result3->fetch_assoc()) {
    $pagos_mes[(int)$row['mes']] = (int)$row['total'];
}
$stmt_pagos_curso->close();

// Datos mensuales gastos
$gastos_mes = array_fill(1, 12, 0);
$stmt_gastos_curso = $conn->prepare("
    SELECT MONTH(fecha) AS mes, SUM(monto) AS total
    FROM gastos
    WHERE curso_id = ? AND YEAR(fecha) = YEAR(NOW())
    GROUP BY mes
");
$stmt_gastos_curso->bind_param("i", $curso_id_seleccionado);
$stmt_gastos_curso->execute();
$result3 = $stmt_gastos_curso->get_result();
while ($row = $result3->fetch_assoc()) {
    $gastos_mes[(int)$row['mes']] = (int)$row['total'];
}
$stmt_gastos_curso->close();

$curso_actual = array_filter($cursos, function($curso) use ($curso_id_seleccionado) {
    return $curso['course_id'] == $curso_id_seleccionado;
});
$curso_actual = reset($curso_actual);

$curso_nombre = htmlspecialchars($curso_actual['course_name'] ?? 'No disponible', ENT_QUOTES, 'UTF-8');
$colegio_nombre = htmlspecialchars($curso_actual['school_name'] ?? 'No disponible', ENT_QUOTES, 'UTF-8');
$logo_colegio = htmlspecialchars($curso_actual['school_logo'] ?? 'https://cdn-icons-png.flaticon.com/512/1930/1930026.png', ENT_QUOTES, 'UTF-8');

// Para gráfico de gastos por categoría y mes
$anio_actual = date('Y');
$stmt_cats = $conn->prepare("SELECT DISTINCT categoria FROM gastos WHERE curso_id = ?");
$stmt_cats->bind_param("i", $curso_id_seleccionado);
$stmt_cats->execute();
$res_cats = $stmt_cats->get_result();
$categorias = [];
while ($row = $res_cats->fetch_assoc()) {
    $categorias[] = $row['categoria'];
}
$stmt_cats->close();

$data_categorias = [];
foreach ($categorias as $cat) {
    for ($mes = 1; $mes <= 12; $mes++) {
        $data_categorias[$cat][$mes] = 0;
    }
}
$stmt = $conn->prepare("
    SELECT categoria, MONTH(fecha) AS mes, SUM(monto) AS total
    FROM gastos
    WHERE curso_id = ? AND YEAR(fecha) = ?
    GROUP BY categoria, mes
    ORDER BY categoria, mes
");
$stmt->bind_param("ii", $curso_id_seleccionado, $anio_actual);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $cat = $row['categoria'];
    $mes = intval($row['mes']);
    $total = floatval($row['total']);
    $data_categorias[$cat][$mes] = $total;
}
$nombres_meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Tesorero - Apolink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <link rel="stylesheet" href="include/css/styles.css">
    <style>
        .fade-out { opacity: 1; transition: opacity 1s ease-out; }
        .fade-out.hidden { opacity: 0; }
        #gastosBarStacked { max-height: 180px; }
    </style>
</head>
<body>
<?php
if (!isset($base_url)) { $base_url = '/apolink/apolink_v2/ApolinkV2'; }
include 'include/sidebar_dashboard_tesorero.php';
?>
<div class="main">
    <div class="card bg-light shadow-sm mb-4 p-3 d-flex flex-row">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <img src="<?= $logo_colegio ?>" alt="Logo Colegio" class="rounded-circle" style="width: 60px; height: 60px; object-fit: cover;">
            </div>
            <div>
                <h4 class="mb-1">👋 Bienvenido, <span class="text-primary"><?= $nombre_usuario ?></span></h4>
                <p class="text-muted mb-2">Colegio: <strong><?= $colegio_nombre ?></strong></p>
                <p class="text-muted mb-2">
                    Curso: <strong id="curso-actual-text"><?= $curso_nombre ?></strong>
                    <button class="btn btn-link p-0 ms-2" data-bs-toggle="modal" data-bs-target="#cambiarCursoModal" style="font-size: 0.9rem;">Cambiar curso</button>
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
    <?php if (isset($_SESSION['mensaje'])): ?>
        <?= $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
    <?php endif; ?>

    <div class="card shadow-sm p-3 mb-4">
        <h5>📊 Pagos y Gastos del Año en Curso</h5>
        <div style="width: 100%; height: 200px;">
            <canvas id="graficoPagosGastos"></canvas>
        </div>
    </div>
    <script>
    const ctx1 = document.getElementById('graficoPagosGastos').getContext('2d');
    const pagosData = <?= json_encode(array_values($pagos_mes)) ?>;
    const gastosData = <?= json_encode(array_values($gastos_mes)) ?>;
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
            datasets: [
                {
                    label: 'Pagos',
                    data: pagosData,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.2)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Gastos',
                    data: gastosData,
                    borderColor: '#DC3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.2)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { display: true }, beginAtZero: true }
            }
        }
    });
    </script>

    <h4>💼 Panel del Tesorero</h4>
    <div class="row my-4">
        <div class="row g-4">
            <div class="col-md-2">
                <div class="card text-white bg-success mb-3" style="max-width: 18rem;">
                    <div class="card-header">Pagos Registrados</div>
                    <div class="card-body">
                        <h5 class="card-title">Total Disponible</h5>
                        <p class="card-text">$<?= number_format($total_pagos, 0, ',', '.') ?>.-</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-danger mb-4" style="max-width: 18rem;">
                    <div class="card-header">Gastos Registrados</div>
                    <div class="card-body">
                        <h5 class="card-title">Gastos Realizados</h5>
                        <p class="card-text">$<?= number_format($total_gastos, 0, ',', '.') ?>.-</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-success mb-4" style="max-width: 18rem;">
                    <div class="card-header">Saldo Disponible</div>
                    <div class="card-body">
                        <h5 class="card-title">Total Disponible</h5>
                        <p class="card-text">$<?= number_format($saldo, 0, ',', '.') ?>.-</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-warning mb-3" style="max-width: 18rem;">
                    <div class="card-header">Pagos Cuotas Pendientes</div>
                    <div class="card-body">
                        <h5 class="card-title">Total Pendiente</h5>
                        <?php
                        // Total pendiente por cuotas de curso (acumulado)
                        $query = "
                            SELECT s.cuota_total, IFNULL(SUM(p.monto), 0) AS pagado
                            FROM students s
                            LEFT JOIN pagos p ON p.alumno_id = s.id AND p.estado='aprobado' AND p.es_cuota_curso=1
                            WHERE s.curso_id = $curso_id_seleccionado AND s.activo = 1
                            GROUP BY s.id
                        ";
                        $resultado = $conn->query($query);
                        $total_pendiente_acumulado = 0;
                        while ($fila = $resultado->fetch_assoc()) {
                            $pendiente = $fila['cuota_total'] - $fila['pagado'];
                            $total_pendiente_acumulado += $pendiente;
                        }
                        ?>
                        <p class="card-text">$<?= number_format($total_pendiente_acumulado ?? 0, 0, ',', '.') ?>.-</p>
                    </div>
                </div>
            </div>
            <!-- Primer select de categoría -->
            <div class="col-md-2">
                <div class="card text-white bg-primary mb-4" style="max-width: 18rem;">
                    <div class="card-header">Total por Categoría</div>
                    <div class="card-body text-center">
                      <!--  <label for="select-categoria1" class="form-label">Categoría:</label>-->
                        <select id="select-categoria1" class="form-select form-select-sm" style="width:auto;display:inline-block;">
                            <?php foreach ($categorias_unicas as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="card-text mt-2" id="total-categoria1">$0.-</p>
                    </div>
                </div>
            </div>
            <!-- Segundo select de categoría (Donde decía Pronto...) -->
            <div class="col-md-2">
                <div class="card text-white bg-info mb-4" style="max-width: 18rem;">
                    <div class="card-header">Otra Categoría</div>
                    <div class="card-body text-center">
                      <!--  <label for="select-categoria2" class="form-label">Categoría:</label>-->
                        <select id="select-categoria2" class="form-select form-select-sm" style="width:auto;display:inline-block;">
                            <?php foreach ($categorias_unicas as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="card-text mt-2" id="total-categoria2">$0.-</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de gastos mensuales por categoría -->
    <div class="card mt-4 mb-4">
        <div class="card-header">Gastos Mensuales por Categoría (<?= $anio_actual ?>)</div>
        <div class="card-body">
            <canvas id="gastosBarStacked" height="80"></canvas>
        </div>
    </div>

    <!-- TABLA: Últimos 10 gastos -->
    <?php
    $stmt_g = $conn->prepare("
        SELECT g.id, g.quien_hizo, g.curso_id, g.estado, g.metodo, g.registrado_por, g.monto, g.fecha, g.categoria, g.descripcion, g.numero_comprobante, g.archivo_comprobante, 
               c.nombre AS curso_nombre
        FROM gastos g
        LEFT JOIN cursos c ON g.curso_id = c.id
        WHERE g.curso_id = ?
        ORDER BY g.fecha DESC
        LIMIT 10
    ");
    $stmt_g->bind_param("i", $curso_id_seleccionado);
    $stmt_g->execute();
    $result = $stmt_g->get_result();
    $gastos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_g->close();
    ?>
    <div class="card shadow-sm p-4 mb-4">
        <h4>💼 Últimos 10 Gastos</h4> 
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th hidden>ID</th>
                        <th style="text-align: center;">Curso</th>
                        <th style="text-align: center;">¿Quien lo hizo?</th>
                        <th style="text-align: center;">Monto</th>
                        <th style="text-align: center;">Fecha de Gasto</th>
                        <th style="text-align: center;">Método</th>
                        <th style="text-align: center;">Categoría</th>
                        <th>Descripción</th>
                        <th>Comprobante</th>
                        <th style="text-align: center;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gastos as $gasto): ?>
                        <tr>
                            <td hidden><?= $gasto['id'] ?></td>
                            <td><?= htmlspecialchars($gasto['curso_nombre']) ?></td>
                            <td><?= htmlspecialchars($gasto['quien_hizo']) ?></td>
                            <td>$<?= number_format($gasto['monto'], 0, ',', '.') ?>.-</td>
                            <td><?= htmlspecialchars(date('d-m-Y', strtotime($gasto['fecha']))) ?></td>
                            <td><?= htmlspecialchars(ucfirst($gasto['metodo'])) ?></td>
                            <td><?= htmlspecialchars($gasto['categoria']) ?></td>
                            <td><?= htmlspecialchars($gasto['descripcion']) ?></td>
                            <td>
                                <?php if (!empty($gasto['archivo_comprobante'])): ?>
                                   <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalComprobante<?= $gasto['id'] ?>">
                                Ver
                            </button>
                            
                              <!-- Modal -->
                            <div class="modal fade" id="modalComprobante<?= $gasto['id'] ?>" tabindex="-1" aria-labelledby="modalComprobanteLabel<?= $gasto['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modalComprobanteLabel<?= $gasto['id'] ?>">Comprobante de Gasto #<?= $gasto['id'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <?php 
                                                $archivo = htmlspecialchars($gasto['archivo_comprobante']);
                                                $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
                                                $ruta = "" . $archivo;
                                                if(in_array($extension, ["jpg", "jpeg", "png", "gif"])) {
                                                    echo "<img src=\"$ruta\" alt=\"Comprobante\" class=\"img-fluid\" style=\"max-height:60vh;\">";
                                                } elseif($extension === "pdf") {
                                                    echo "<embed src=\"$ruta\" type=\"application/pdf\" width=\"100%\" height=\"500px\">";
                                                } else {
                                                    echo "<p>No se puede mostrar una vista previa de este comprobante.</p>";
                                                }
                                            ?>
                                        </div>
                                        <div class="modal-footer">
                                            <a href="<?= $ruta ?>" download class="btn btn-success">
                                                Descargar
                                            </a>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                <?php else: ?>
                                    Comprobante no disponible
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($gasto['estado'] === 'por pagar'): ?>
                                    <span class="badge bg-warning text-dark">Por pagar</span>
                                <?php elseif ($gasto['estado'] === 'pagado'): ?>
                                    <span class="badge bg-success">Pagado</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rechazado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <a href="gastos/ver_gastos.php" class="btn btn-primary mt-3">Ver Todos los Gastos</a>
        </div>
    </div>

    <!-- TABLA: Últimos 10 pagos -->
    <?php
    $stmt = $conn->prepare("
        SELECT p.id, p.monto, p.es_cuota_curso, p.fecha_pago, p.metodo, p.observacion, p.numero_comprobante, p.archivo_comprobante, 
               p.estado, c.nombre AS curso_nombre, s.primer_apellido, s.segundo_apellido, s.nombre AS alumno_nombre
        FROM pagos p
        LEFT JOIN cursos c ON p.curso_id = c.id
        LEFT JOIN students s ON p.alumno_id = s.id
        WHERE p.curso_id = ?  AND s.activo = 1
        ORDER BY p.fecha_pago DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $curso_id_seleccionado);
    $stmt->execute();
    $result = $stmt->get_result();
    $pagos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    ?>
    <div class="card shadow-sm p-4 mb-4">
        <h4>💰 Últimos 10 Pagos</h4> 
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th hidden>ID</th>
                        <th>Alumno</th>
                        <th style="text-align: center;">Curso</th>
                        <th style="text-align: center;">Monto</th>
                        <th style="text-align: center;">Fecha de Pago</th>
                        <th style="text-align: center;">Método</th>
                        <th>Observación</th>
                        <th>Comprobante</th>
                        <th style="text-align: center;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $pago): ?>
                        <tr>
                            <td hidden><?= $pago['id'] ?></td>
                            <td><?= htmlspecialchars($pago['alumno_nombre'] . ' ' . $pago['primer_apellido'] . ' ' . $pago['segundo_apellido']) ?></td>
                            <td><?= htmlspecialchars($pago['curso_nombre']) ?></td>
                            <td>$<?= number_format($pago['monto'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars(date('d-m-Y', strtotime($pago['fecha_pago']))) ?></td>
                            <td><?= htmlspecialchars(ucfirst($pago['metodo'])) ?></td>
                            <td><?= htmlspecialchars($pago['observacion']) ?></td>
                            <td>
                                <?php if (!empty($pago['archivo_comprobante'])): ?>
                                    <a href="<?= htmlspecialchars($pago['archivo_comprobante']) ?>" target="_blank">Ver</a>
                                <?php else: ?>
                                    Comprobante no disponible
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pago['estado'] === 'pendiente'): ?>
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                <?php elseif ($pago['estado'] === 'aprobado'): ?>
                                    <span class="badge bg-success">Aprobado</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rechazado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <a href="pagos/pagos_tesorero.php" class="btn btn-primary mt-3">Ver Todos los Pagos</a>
        </div>
    </div>

    <!-- TABLA: Deudores de cuotas -->
    <div class="card shadow-sm p-4 mb-4">
        <h6>👨‍🎓 Alumnos con cuotas de curso pendiente</h6>
        <div class="table-responsive">
            <?php
            // Paginación
            $pagina_actual = isset($_GET['pagina_deudores']) ? (int)$_GET['pagina_deudores'] : 1;
            $resultados_por_pagina = 15;
            $offset = ($pagina_actual - 1) * $resultados_por_pagina;
    
            // Total de filas para paginación
            $count_query = "
                SELECT COUNT(*) AS total
                FROM (
                    SELECT 
                        s.id,
                        s.cuota_total
                    FROM 
                        students s
                    JOIN 
                        cursos c ON s.curso_id = c.id
                    LEFT JOIN 
                        pagos p ON p.alumno_id = s.id
                    WHERE 
                        s.curso_id = $curso_id_seleccionado AND s.activo = 1
                    GROUP BY 
                        s.id, s.cuota_total
                    HAVING 
                        (s.cuota_total - IFNULL(SUM(CASE 
                            WHEN p.estado = 'aprobado' AND p.es_cuota_curso = 1 THEN p.monto ELSE 0 END), 0)) > 0
                ) AS subquery;
            ";
            $total_resultado = $conn->query($count_query);
            $total_filas = $total_resultado->fetch_assoc()['total'];
            $total_paginas = ceil($total_filas / $resultados_por_pagina);
    
            // Consulta paginada (¡s.cuota_total en SELECT y GROUP BY!)
            $query = "
                SELECT 
                    s.nombre AS alumno_nombre, 
                    s.primer_apellido, 
                    s.segundo_apellido, 
                    s.cuota_total, 
                    c.nombre AS curso,
                    IFNULL(SUM(CASE 
                        WHEN p.estado = 'aprobado' AND p.es_cuota_curso = 1 THEN p.monto
                        ELSE 0 
                    END), 0) AS pagado_aprobado,
                    u.nombre AS apoderado_nombre,
                    u.email AS apoderado_email
                FROM 
                    students s
                JOIN 
                    cursos c ON s.curso_id = c.id
                LEFT JOIN 
                    pagos p ON p.alumno_id = s.id
                LEFT JOIN 
                    (
                        SELECT 
                            aa.alumno_id, 
                            MIN(u.id) AS primer_apoderado_id
                        FROM 
                            alumno_apoderado aa
                        JOIN 
                            users u ON aa.apoderado_id = u.id
                        GROUP BY 
                            aa.alumno_id
                    ) AS primer_apoderado ON s.id = primer_apoderado.alumno_id
                LEFT JOIN 
                    users u ON primer_apoderado.primer_apoderado_id = u.id
                WHERE 
                    s.curso_id = $curso_id_seleccionado AND s.activo = 1
                GROUP BY 
                    s.id, s.cuota_total, s.nombre, s.primer_apellido, s.segundo_apellido, c.nombre, u.nombre, u.email
                HAVING 
                    (s.cuota_total - pagado_aprobado) > 0
                ORDER BY 
                    s.primer_apellido
                LIMIT $resultados_por_pagina OFFSET $offset;
            ";
            $resultado = $conn->query($query);
            ?>
            <table class="table table-bordered table-hover mt-3">
                <thead class="table-light">
                    <tr>
                        <th>Alumno</th>
                        <th>Apoderado</th>
                        <th>Email Apoderado</th>
                        <th>Curso</th>
                        <th style="text-align: center;">Total Cuota</th>
                        <th style="text-align: center;">Pagado</th>
                        <th style="text-align: center;">Pendiente</th>
                        <th style="text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($resultado->num_rows > 0): 
                    while ($fila = $resultado->fetch_assoc()):
                        $pendiente = $fila['cuota_total'] - $fila['pagado_aprobado'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($fila['alumno_nombre']) ?> <?= htmlspecialchars($fila['primer_apellido']) ?> <?= htmlspecialchars($fila['segundo_apellido']) ?></td>
                    <td><?= !empty($fila['apoderado_nombre']) ? htmlspecialchars($fila['apoderado_nombre']) : '<span class="text-muted">Sin asignar</span>' ?></td>
                    <td><?= !empty($fila['apoderado_email']) ? htmlspecialchars($fila['apoderado_email']) : '<span class="text-muted">Sin email</span>' ?></td>
                    <td><?= htmlspecialchars($fila['curso']) ?></td>
                    <td>$<?= number_format($fila['cuota_total'], 0, ',', '.') ?></td>
                    <td>$<?= number_format($fila['pagado_aprobado'], 0, ',', '.') ?></td>
                    <td><strong class="text-danger">$<?= number_format($pendiente, 0, ',', '.') ?></strong></td>
                    <td>
                        <?php if (!empty($fila['apoderado_email'])): ?>
                        <button 
                            class="btn btn-primary btn-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#emailModal" 
                            data-nombre-alumno="<?= htmlspecialchars($fila['alumno_nombre'] . ' ' . $fila['primer_apellido'] . ' ' . $fila['segundo_apellido']) ?>" 
                            data-nombre-apoderado="<?= htmlspecialchars($fila['apoderado_nombre']) ?>"
                            data-curso="<?= htmlspecialchars($fila['curso']) ?>" 
                            data-pendiente="<?= number_format($pendiente, 0, ',', '.') ?>" 
                            data-email="<?= htmlspecialchars($fila['apoderado_email']) ?>"
                            data-nombre-colegio="<?= htmlspecialchars($colegio_nombre) ?>" 
                        >
                        Enviar Email
                        </button>
                        <?php else: ?>
                            <span class="text-muted">Sin email</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">No hay alumnos con cuotas de curso pendientes</td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <!-- Paginación -->
            <nav>
                <ul class="pagination justify-content-center">
                    <?php if ($pagina_actual > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?pagina_deudores=<?= $pagina_actual - 1 ?>" aria-label="Anterior">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?= $i === $pagina_actual ? 'active' : '' ?>">
                            <a class="page-link" href="?pagina_deudores=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($pagina_actual < $total_paginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="?pagina_deudores=<?= $pagina_actual + 1 ?>" aria-label="Siguiente">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <!-- Modal para enviar email -->
        <div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="include/enviar_email_apoderado_cobro.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="emailModalLabel">Enviar Recordatorio de Deuda</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="nombreApoderado" class="form-label">Nombre del Apoderado</label>
                                <input type="text" class="form-control" id="nombreApoderado" name="nombreApoderado" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="emailApoderado" class="form-label">Email</label>
                                <input type="email" class="form-control" id="emailApoderado" name="emailApoderado" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="5" readonly></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Enviar Email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            const emailModal = document.getElementById('emailModal');
            emailModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const nombreApoderado = button.getAttribute('data-nombre-apoderado');
                const nombreAlumno = button.getAttribute('data-nombre-alumno');
                const curso = button.getAttribute('data-curso');
                const pendiente = button.getAttribute('data-pendiente');
                const email = button.getAttribute('data-email');
                const colegioNombre = button.getAttribute('data-nombre-colegio');
    
                const modalNombre = emailModal.querySelector('#nombreApoderado');
                const modalEmail = emailModal.querySelector('#emailApoderado');
                const modalDescripcion = emailModal.querySelector('#descripcion');
    
                modalNombre.value = nombreApoderado;
                modalEmail.value = email;
                modalDescripcion.value = `Estimado/a ${nombreApoderado},\n\nLe recordamos que mantiene una deuda pendiente para el alumno/a ${nombreAlumno}, que pertenece al curso ${curso} del ${colegioNombre}. El monto de la deuda es de $${pendiente}.- \nPor favor regularizar por los canales habilitados. \n\nSin otro particular, se despide \nDelegados del curso \n${curso}.`;
            });
        </script>
    </div>
 <?php include 'include/footer.php'; ?>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    function actualizarTotalCategoria(num) {
        const select = document.getElementById('select-categoria' + num);
        const categoria = select.value;
        const curso_id = <?= json_encode($curso_id_seleccionado) ?>;
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ajax_categoria=' + encodeURIComponent(categoria) + '&curso_id=' + encodeURIComponent(curso_id)
        })
        .then(response => response.text())
        .then(monto => {
            document.getElementById('total-categoria' + num).innerHTML = "$" + monto + ".-";
        });
    }
    if(document.getElementById('select-categoria1')) {
        document.getElementById('select-categoria1').addEventListener('change', function(){ actualizarTotalCategoria(1); });
        actualizarTotalCategoria(1);
    }
    if(document.getElementById('select-categoria2')) {
        document.getElementById('select-categoria2').addEventListener('change', function(){ actualizarTotalCategoria(2); });
        actualizarTotalCategoria(2);
    }
    // Gráfico de gastos por categoría
    const meses = <?= json_encode($nombres_meses) ?>;
    const categorias = <?= json_encode(array_values($categorias)) ?>;
    const dataCategorias = <?= json_encode($data_categorias) ?>;
    const colores = [
        '#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69',
        '#fd7e14','#20c997','#6610f2','#b7b7b7','#ff63a6'
    ];
    const datasets = categorias.map((cat, idx) => ({
        label: cat,
        data: meses.map((_, i) => dataCategorias[cat] && dataCategorias[cat][i+1] ? dataCategorias[cat][i+1] : 0),
        backgroundColor: colores[idx % colores.length]
    }));
    const ctxGraficoCategoria = document.getElementById('gastosBarStacked').getContext('2d');
    new Chart(ctxGraficoCategoria, {
        type: 'bar',
        data: {
            labels: meses,
            datasets: datasets
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: true, text: 'Gastos por Mes y Categoría' }
            },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Monto ($)' } }
            }
        }
    });
});
</script>
</body>
</html>