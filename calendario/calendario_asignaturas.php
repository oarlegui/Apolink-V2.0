<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../include/conexion.php';

//require_login();
//require_rol(['admin', 'admin_curso']);

// Verificar si el usuario est�� autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}



// Obtener el curso ID desde la sesión o parámetro
$curso_id = isset($_GET['curso_id']) ? intval($_GET['curso_id']) : 1; // Curso predeterminado

// Obtener asignaturas comunes y extra programáticas
function obtenerAsignaturas($tipo, $curso_id, $conn) {
    $query = "SELECT a.id, a.nombre, a.color, c.dia_semana, c.hora_inicio, c.hora_fin
              FROM asignaturas a
              LEFT JOIN calendario_asignaturas c ON a.id = c.asignatura_id
              WHERE a.tipo = ? AND a.curso_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $tipo, $curso_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $asignaturas = [];
    while ($row = $result->fetch_assoc()) {
        $asignaturas[] = $row;
    }
    return $asignaturas;
}

$asignaturas_comunes = obtenerAsignaturas('comun', $curso_id, $conn);
$asignaturas_extras = obtenerAsignaturas('extra_programatica', $curso_id, $conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calendario de Asignaturas - Apolink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>
    <style>
        .fc-event {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <!-- Sidebar -->
    <?php include '../include/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main flex-fill p-4">
        <h3 class="mb-4">📅 Calendario de Asignaturas</h3>
        
        <div class="row">
            <div class="col-md-6">
                <h5>Asignaturas Comunes</h5>
                <div id="calendarioComunes"></div>
            </div>
            <div class="col-md-6">
                <h5>Asignaturas Extra Programáticas</h5>
                <div id="calendarioExtras"></div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Configuración del calendario
        const calendarioComunes = new FullCalendar.Calendar(document.getElementById('calendarioComunes'), {
            initialView: 'timeGridWeek',
            editable: true,
            events: [
                <?php foreach ($asignaturas_comunes as $asignatura): ?>
                    {
                        title: '<?= htmlspecialchars($asignatura['nombre']) ?>',
                        startTime: '<?= $asignatura['hora_inicio'] ?>',
                        endTime: '<?= $asignatura['hora_fin'] ?>',
                        daysOfWeek: [<?= convertDayToNumber($asignatura['dia_semana']) ?>],
                        color: '<?= htmlspecialchars($asignatura['color']) ?>'
                    },
                <?php endforeach; ?>
            ]
        });

        const calendarioExtras = new FullCalendar.Calendar(document.getElementById('calendarioExtras'), {
            initialView: 'timeGridWeek',
            editable: true,
            events: [
                <?php foreach ($asignaturas_extras as $asignatura): ?>
                    {
                        title: '<?= htmlspecialchars($asignatura['nombre']) ?>',
                        startTime: '<?= $asignatura['hora_inicio'] ?>',
                        endTime: '<?= $asignatura['hora_fin'] ?>',
                        daysOfWeek: [<?= convertDayToNumber($asignatura['dia_semana']) ?>],
                        color: '<?= htmlspecialchars($asignatura['color']) ?>'
                    },
                <?php endforeach; ?>
            ]
        });

        calendarioComunes.render();
        calendarioExtras.render();
    });

    // Función para convertir días a números
    function convertDayToNumber(dia) {
        const dias = {
            'lunes': 1,
            'martes': 2,
            'miercoles': 3,
            'jueves': 4,
            'viernes': 5
        };
        return dias[dia] || 0;
    }
</script>
</body>
</html>