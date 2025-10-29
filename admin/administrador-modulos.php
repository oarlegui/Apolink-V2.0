
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

?>
<div class="modulos-container">
    <h3>Administrar Módulos</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Módulo</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php
           

            // Verificar conexión
            if ($conn->connect_error) {
                die("Error de conexión: " . $conn->connect_error);
            }

            $curso_id = 1; // ID del curso (puedes obtenerlo dinámicamente)
            $sql = "SELECT id, modulo, activo FROM curso_modulo WHERE curso_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $curso_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $estado = $row['activo'] ? "Habilitado" : "Deshabilitado";
                $boton_texto = $row['activo'] ? "Deshabilitar" : "Habilitar";
                $boton_clase = $row['activo'] ? "btn-danger" : "btn-success";
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['modulo']); ?></td>
                    <td><?= $estado; ?></td>
                    <td>
                        <button class="btn <?= $boton_clase; ?>" 
                                onclick="toggleModulo(<?= $row['id']; ?>, <?= $row['activo']; ?>)">
                            <?= $boton_texto; ?>
                        </button>
                    </td>
                </tr>
                <?php
            }

            $stmt->close();
            $conn->close();
            ?>
        </tbody>
    </table>
</div>

<script>
    function toggleModulo(moduloId, estadoActual) {
        const nuevoEstado = estadoActual ? 0 : 1; // Alternar estado (1 -> 0, 0 -> 1)
        
        fetch('toggle_modulo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: moduloId,
                activo: nuevoEstado
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Módulo actualizado correctamente.');
                location.reload(); // Recargar la página para mostrar cambios
            } else {
                alert('Error al actualizar el módulo: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Hubo un problema al procesar la solicitud.');
        });
    }
</script>