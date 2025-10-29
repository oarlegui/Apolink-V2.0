<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'include/conexion.php';
require 'include/sesion_logger.php';

$ip = $_SERVER['REMOTE_ADDR'];
$check = $conn->prepare("SELECT id FROM bloqueos_ip WHERE ip = ?");
$check->bind_param("s", $ip);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    die("Tu IP ha sido bloqueada del sistema.");
}

$error = '';
$ultimo_perfil = '';

if (isset($_SESSION['usuario_id'])) {
    $usuario_id = $_SESSION['usuario_id'];

    // Obtener el último perfil seleccionado del usuario
    $stmt_perfil = $conn->prepare("SELECT ultimo_perfil FROM users WHERE id = ?");
    $stmt_perfil->bind_param("i", $usuario_id);
    $stmt_perfil->execute();
    $resultado = $stmt_perfil->get_result()->fetch_assoc();
    $ultimo_perfil = $resultado['ultimo_perfil'] ?? '';
    $stmt_perfil->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colegio_id = $_POST['colegio_id'];
    $clave = $_POST['password'];
    $perfil = $_POST['perfil'];
    $email = $_POST['email'] ?? '';
    $rut = $_POST['rut'] ?? '';

    if (empty($colegio_id) || empty($perfil)) {
        $error = "Por favor completa todos los campos.";
    } else {
        if ($perfil === 'apoderado') {
                $stmt = $conn->prepare("SELECT u.*, uc.colegio_id FROM users u
                    JOIN user_colegio uc ON uc.user_id = u.id
                    WHERE u.rut = ? AND uc.colegio_id = ? AND FIND_IN_SET(?, u.rol)");
                $stmt->bind_param("sis", $rut, $colegio_id, $perfil);
            } else {
                $stmt = $conn->prepare("SELECT u.*, uc.colegio_id FROM users u
                    JOIN user_colegio uc ON uc.user_id = u.id
                    WHERE u.email = ? AND uc.colegio_id = ? AND FIND_IN_SET(?, u.rol)");
                $stmt->bind_param("sis", $email, $colegio_id, $perfil);
            }
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();

        if ($usuario && password_verify($clave, $usuario['password'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $perfil;
            $_SESSION['colegio_id'] = $colegio_id;

            // Actualizar el último perfil seleccionado en la base de datos
            $stmt_update = $conn->prepare("UPDATE users SET ultimo_perfil = ? WHERE id = ?");
            $stmt_update->bind_param("si", $perfil, $usuario['id']);
            $stmt_update->execute();
            $stmt_update->close();

            // Obtener alumnos asociados
            $alumno_stmt = $conn->prepare("
                SELECT s.id AS alumno_id, s.nombre AS alumno_nombre ,
                s.primer_apellido AS alumno_apellido1,
                s.segundo_apellido AS alumno_apellido2
                FROM students s
                JOIN alumno_apoderado aa ON aa.alumno_id = s.id
                WHERE aa.apoderado_id = ?
            ");
            $alumno_stmt->bind_param("i", $usuario['id']);
            $alumno_stmt->execute();
            $result = $alumno_stmt->get_result();

            if ($result->num_rows > 1) {
                // Si hay más de un alumno, mostrar modal para seleccionar
                while ($row = $result->fetch_assoc()) {
                    $alumnos[] = $row;
                }
            } elseif ($result->num_rows === 1) {
                // Si hay solo un alumno, seleccionarlo automáticamente
                $alumno = $result->fetch_assoc();
                $_SESSION['alumno_id'] = $alumno['alumno_id'];
                $_SESSION['alumno_nombre'] = $alumno['alumno_nombre'];
            }

            // Redirigir según rol
            if (empty($alumnos)) { // Si ya seleccionamos el alumno
                switch ($usuario['rol']) {
                    case 'admin':
                        header("Location: dashboard_admin.php");
                        break;
                    case 'admin_curso':
                        header("Location: dashboard_admin_curso.php");
                        break;
                    case 'tesorero':
                        header("Location: dashboard_tesorero.php");
                        break;
                    case 'apoderado':
                        header("Location: dashboard_apoderado_v2.php");
                        break;
                }
                exit;
            }
        } else {
            $error = "Datos inválidos.";
        }
    }
}



// Lista de palabras motivadoras
$palabras_motivadoras = [
    "ESFUÉRZATE", "SUPÉRATE", "APRENDE", "CRECE", "PERSEVERA", "CONFÍA EN TI", 
    "INSPIRA", "LOGRA", "DREAM BIG", "ÉXITO", "TRABAJA DURO", "IMAGINA", "APOLINK"
];
$palabras_rojas = ["CRECE", "PERSEVERA", "CONFÍA EN TI"];
$palabras_azules = ["APOLINK"];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login Apolink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
    body {
      background-color: #f8f9fa;
      height: 100vh;
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden;
      position: relative;
    }

    /* Contenedor para las filas de palabras */
    .motivational-rows {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      overflow: hidden;
      z-index: -1;
    }

    .row {
      position: absolute;
      width: 100%;
      white-space: nowrap;
      font-size: calc(10rem + 4vw); /* Tamaño muy grande */
      font-weight: bold;
      text-transform: uppercase;
      animation-duration: var(--animation-speed); /* Duración dinámica */
      animation-timing-function: linear;
      animation-iteration-count: infinite;
    }

    /* Estilo para palabras normales */
    .row span {
      color: rgba(0, 0, 0, 0.05); /* Negro transparente */
    }

    /* Estilo para palabras especiales (rojas con borde de cada letra) */
    .row span.red {
      color: rgba(255, 0, 0, 0); /* Transparente para el relleno */
      -webkit-text-stroke: 3px rgba(255, 0, 0, 0.5); /* Borde rojo transparente alrededor de cada letra */
      text-stroke: 3px rgba(255, 0, 0, 0.5); /* Compatibilidad adicional */
    }

    /* Estilo para palabras azules (APOLINK) */
    .row span.blue {
      color: rgba(0, 0, 255, 1); /* Azul visible para el relleno */
      -webkit-text-stroke: 4px rgba(0, 0, 255, 0.8); /* Borde azul más grueso */
      text-stroke: 4px rgba(0, 0, 255, 0.8); /* Compatibilidad adicional */
    }

    /* Animación para mover de izquierda a derecha */
    @keyframes scroll-row {
      0% {
        transform: translateX(-100%);
      }
      100% {
        transform: translateX(100%);
      }
    }

    /* Animación para mover de derecha a izquierda */
    @keyframes scroll-row-reverse {
      0% {
        transform: translateX(100%);
      }
      100% {
        transform: translateX(-100%);
      }
    }

    /* Estilos dinámicos: posición, dirección y velocidad */
    <?php foreach ($palabras_motivadoras as $index => $palabra): ?>
    .row-<?= $index ?> {
      top: <?= ($index * 8) % 100 ?>%; /* Distribuir filas uniformemente */
      animation-name: <?= $index % 2 == 0 ? 'scroll-row' : 'scroll-row-reverse' ?>; /* Alternar direcciones */
      --animation-speed: <?= rand(15, 25) ?>s; /* Ritmo aleatorio */
    }
    <?php endforeach; ?>

    .login-box {
      max-width: 450px;
      margin: auto;
      z-index: 1; /* Por encima del fondo */
    }

    .btn-primary {
      background-color: #4a90e2;
      border: none;
    }

    .btn-primary:hover {
      background-color: #357ab8;
    }

    .alert-danger {
      margin-bottom: 20px;
    }

    .form-control:focus {
      border-color: #4a90e2;
      box-shadow: 0 0 5px rgba(74, 144, 226, 0.5);
    }

    .card {
      border-radius: 15px;
      background: rgba(255, 255, 255, 0.9); /* Fondo semitransparente */
    }

    .card-header {
      background: linear-gradient(45deg, #007bff, #6610f2);
      color: white;
      border-bottom: none;
      text-align: center;
      font-size: 1.5rem;
    }

    .card-footer {
      background-color: #f1f1f1;
      border-top: none;
    }
  </style>
</head>
<body>
    <!-- Fondo motivador -->
          <div class="motivational-rows">
              <?php foreach ($palabras_motivadoras as $index => $palabra): ?>
                <div class="row row-<?= $index ?>">
                  <span class="<?= in_array($palabra, $palabras_rojas) ? 'red' : (in_array($palabra, $palabras_azules) ? 'blue' : '') ?>">
                    <?= $palabra ?>
                  </span>
                </div>
                
              <?php endforeach;  ?>
        </div>
        <style>
        .red { color: red; font-weight: bold; }
        .blue { color: blue; font-weight: bold; }
        </style>
    
    <div class="container login-box">
        <div class="card shadow-lg">
            <div class="card-header">
                <img src="include/img/logo_apolink_tejido1.png" alt="Logo" style="max-height: 100px;" class="mb-2">
                <div>¡Bienvenid@ a Apolink!</div>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <div class="mb-3">
                        <label class="form-label">Colegio</label>
                        <input type="text" name="colegio_nombre" id="colegio_nombre" class="form-control" placeholder="Escribe el nombre del colegio..." required>
                        <input type="hidden" name="colegio_id" id="colegio_id">
                    </div>
                     <div class="mb-3">
                        <label class="form-label">Perfil</label>
                        <select name="perfil" id="perfil" class="form-select" required>
                            <option value="" disabled selected>Selecciona tu perfil</option>
                            <option value="apoderado">Apoderado</option>
                            <option value="admin">Administrador</option>
                            <option value="admin_curso">Administrador de Curso</option>
                            <option value="tesorero">Tesorero</option>
                        </select>
                    </div>
                    <div class="mb-3" id="email-group" style="display: none;">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Ej: usuario@dominio.com">
                    </div>
                    <div class="mb-3" id="rut-group" style="display: none;">
                        <label class="form-label">RUT</label>
                        <input type="text" name="rut" class="form-control" placeholder="Ej: 12345678-9">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control" placeholder="Introduce tu contraseña" required>
                    </div>
                   
                    <button class="btn btn-primary w-100 py-2" type="submit">Ingresar</button>
                </form>
            </div>
            <div class="card-footer text-center text-muted small">
                ¿Problemas para ingresar?<br> Contacte al administrador de su curso.
                <div class="mt-3">
                    <span>Power by</span><br>
                  <img src="https://arlegui-it.cl/arlegui-it-logo.png" alt="Arlegui IT" style="height: 30px;">
                </div>
            </div>
        </div>
    </div>

  <!-- Modal para seleccionar alumno -->
  <?php if (!empty($alumnos)): ?>
  <div class="modal fade" id="seleccionarAlumnoModal" tabindex="-1" aria-labelledby="seleccionarAlumnoLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="seleccionarAlumnoLabel">Seleccionar Alumno</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Selecciona el alumno con el que deseas continuar:</p>
          <form id="seleccionarAlumnoForm" method="POST" action="seleccionar_alumno.php">
            <?php foreach ($alumnos as $alumno): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="alumno_id" id="alumno-<?= $alumno['alumno_id'] ?>" value="<?= $alumno['alumno_id'] ?>" required>
                <label class="form-check-label" for="alumno-<?= $alumno['alumno_id'] ?>">
                  <?= htmlspecialchars($alumno['alumno_nombre']) ?> <?= htmlspecialchars($alumno['alumno_apellido1']) ?> <?= htmlspecialchars($alumno['alumno_apellido2']) ?>
                </label>
              </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary mt-3">Continuar</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <script>
    $(document).ready(function () {
      $('#seleccionarAlumnoModal').modal('show');
    });
  </script>
  <?php endif; ?>
  
   <script>
        $(function() {
          $('#colegio_nombre').autocomplete({
            source: 'include/buscar_colegio.php', // Archivo que devuelve los datos
            minLength: 2, // Comienza a buscar después de 2 caracteres
            select: function(event, ui) {
              $('#colegio_nombre').val(ui.item.label); // Muestra el nombre del colegio seleccionado
              $('#colegio_id').val(ui.item.value); // Guarda el ID del colegio en un input oculto
              localStorage.setItem('ultimoColegio', JSON.stringify({ label: ui.item.label, value: ui.item.value })); // Guarda el último colegio en el almacenamiento local
            }
          });
        
          // Restaura el último colegio seleccionado al cargar la página
          const ultimo = JSON.parse(localStorage.getItem('ultimoColegio'));
          if (ultimo) {
            $('#colegio_nombre').val(ultimo.label);
            $('#colegio_id').val(ultimo.value);
          }
        });
    </script>
    <script>
        $('#perfil').on('change', function() {
            let perfil = $(this).val();
            if(perfil === 'apoderado') {
                $('#rut-group').show();
                $('#email-group').hide();
                $('input[name="rut"]').prop('required', true);
                $('input[name="email"]').prop('required', false);
            } else {
                $('#rut-group').hide();
                $('#email-group').show();
                $('input[name="rut"]').prop('required', false);
                $('input[name="email"]').prop('required', true);
            }
        });
</script>
</body>
</html>