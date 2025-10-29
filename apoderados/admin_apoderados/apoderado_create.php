<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../include/conexion.php';

function require_login() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ../../index.php');
        exit;
    }
}
function require_rol($roles_permitidos) {
    if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $roles_permitidos)) {
        header('Location: ../../index.php');
        exit;
    }
}

require_login();
require_rol(['admin', 'admin_curso', 'tesorero']);

$user_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');

// Obtener cursos asignados al usuario logueado (tesorero o admin_curso)
$curso_ids = [];
if (in_array($rol, ['tesorero', 'admin_curso'])) {
    // Cursos donde es tesorero
    $q = $conn->prepare("SELECT curso_id FROM tesorero_curso WHERE tesorero_id = ?");
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) $curso_ids[] = $row['curso_id'];
    $q->close();

    // Cursos donde está por user_curso
    $q = $conn->prepare("SELECT curso_id FROM user_curso WHERE user_id = ?");
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) $curso_ids[] = $row['curso_id'];
    $q->close();
}
if (empty($curso_ids)) $curso_ids = [0];
$curso_ids = array_unique($curso_ids);
$curso_list = implode(',', array_map('intval', $curso_ids));

function getColegiosByCursos($conn, $curso_ids) {
    if (empty($curso_ids)) return [];
    $curso_list = implode(',', array_map('intval', $curso_ids));
    $sql = "SELECT DISTINCT col.id, col.nombre
            FROM colegios col
            JOIN cursos c ON c.colegio_id = col.id
            WHERE c.id IN ($curso_list)
            ORDER BY col.nombre";
    $res = $conn->query($sql);
    $out = [];
    while ($row = $res->fetch_assoc()) $out[] = $row;
    return $out;
}

$success = '';
$error = '';
$reset_form = false;

// Eliminar apoderado (usuario)
if (isset($_POST['eliminar_apoderado_id'])) {
    $eliminar_id = intval($_POST['eliminar_apoderado_id']);
    // Seguridad: Solo puede eliminar si el apoderado pertenece a un curso permitido
    $sql = "SELECT 1
            FROM users u
            JOIN alumno_apoderado aa ON aa.apoderado_id = u.id
            JOIN students a ON a.id = aa.alumno_id
            WHERE u.id = $eliminar_id AND a.curso_id IN ($curso_list) AND u.rol = 'apoderado'
            LIMIT 1";
    $res = $conn->query($sql);
    if ($res->num_rows > 0) {
        // Borra dependientes primero (si aplica)
        $conn->query("DELETE FROM alumno_apoderado WHERE apoderado_id = $eliminar_id");
        $conn->query("DELETE FROM user_curso WHERE user_id = $eliminar_id");
        $conn->query("DELETE FROM user_colegio WHERE user_id = $eliminar_id");
        $conn->query("DELETE FROM users WHERE id = $eliminar_id");
        $success = "Apoderado eliminado correctamente.";
        $reset_form = true;
    } else {
        $error = "No autorizado para eliminar este apoderado.";
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $rut = trim($_POST['rut'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol_post = $_POST['rol'] ?? '';
    $colegio_id = intval($_POST['colegio_id'] ?? 0);
    $curso_id = intval($_POST['curso_id'] ?? 0);
    $alumno_id = intval($_POST['alumno_id'] ?? 0);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El email no es válido.";
    } elseif (!preg_match('/^\d{7,8}-[kK\d]{1}$/', $rut)) {
        $error = "El RUT debe tener el formato 12345678-9 o 12345678-K.";
    } elseif (!preg_match('/^\+\d{1,3}\d{7,}$/', $telefono)) {
        $error = "El teléfono debe incluir el código de país, por ejemplo +56912345678";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($rol_post !== 'apoderado') {
        $error = "Rol no permitido.";
    } elseif (!in_array($curso_id, $curso_ids)) {
        $error = "Curso no permitido.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR rut = ?");
        $stmt->bind_param("ss", $email, $rut);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Ya existe un usuario con ese email o RUT.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (nombre, rut, email, telefono, password, rol) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $nombre, $rut, $email, $telefono, $password_hash, $rol_post);
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;

                $get_col = $conn->prepare("SELECT colegio_id FROM cursos WHERE id = ?");
                $get_col->bind_param("i", $curso_id);
                $get_col->execute();
                $get_col->bind_result($colegio_id_fromcurso);
                $get_col->fetch();
                $get_col->close();

                $stmt2 = $conn->prepare("INSERT INTO user_colegio (user_id, colegio_id) VALUES (?, ?)");
                $stmt2->bind_param("ii", $new_user_id, $colegio_id_fromcurso);
                $stmt2->execute();

                $stmt3 = $conn->prepare("INSERT INTO user_curso (user_id, curso_id) VALUES (?, ?)");
                $stmt3->bind_param("ii", $new_user_id, $curso_id);
                $stmt3->execute();

                $stmt4 = $conn->prepare("INSERT INTO alumno_apoderado (alumno_id, apoderado_id) VALUES (?, ?)");
                $stmt4->bind_param("ii", $alumno_id, $new_user_id);
                $stmt4->execute();

                $success = "Apoderado registrado exitosamente y asociado al alumno.";
                $reset_form = true;
            } else {
                $error = "Error al registrar el apoderado.";
            }
            $stmt->close();
        }
    }
}

$colegios = getColegiosByCursos($conn, $curso_ids);

$per_page = 1;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$total_cursos_q = $conn->query("SELECT COUNT(*) as total FROM cursos WHERE id IN ($curso_list)");
$total_cursos = $total_cursos_q->fetch_assoc()['total'];
$total_pages = ceil($total_cursos / $per_page);

$offset = ($page - 1) * $per_page;

$cursos_q = $conn->query("SELECT id, nombre FROM cursos WHERE id IN ($curso_list) ORDER BY nombre LIMIT $per_page OFFSET $offset");
$cursos = [];
while ($c = $cursos_q->fetch_assoc()) {
    $cursos[] = $c;
}

$apoderados_por_curso = [];
foreach ($cursos as $curso) {
    $cid = $curso['id'];
    $query = "
    SELECT 
        u.id, u.nombre, u.rut, u.email, u.telefono,
        col.nombre AS colegio,
        cur.nombre AS curso,
        a.nombre AS alumno_nombre, a.primer_apellido, a.segundo_apellido
    FROM users u
    LEFT JOIN alumno_apoderado aa ON aa.apoderado_id = u.id
    LEFT JOIN students a ON a.id = aa.alumno_id
    LEFT JOIN cursos cur ON cur.id = a.curso_id
    LEFT JOIN colegios col ON col.id = cur.colegio_id
    WHERE u.rol = 'apoderado'
      AND a.curso_id = $cid
    ORDER BY u.nombre
    ";
    $apoderados = $conn->query($query);
    $apoderados_por_curso[$curso['nombre']] = $apoderados;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Apoderado - Apolink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="../../include/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<?php include '../../include/sidebar_dashboard_tesorero.php'; ?>
<div class="main">
     <div class="card bg-light shadow-sm mb-4 p-3 d-flex flex-row">
        <div class="d-flex align-items-center">
            <div class="me-3">
             <!--   <img src="<?= $logo_colegio ?>" alt="Logo Colegio" class="rounded-circle" style="width: 60px; height: 60px; object-fit: cover;">-->
            </div>
            <div>
                <h4 class="mb-1">👋 Bienvenido, <span class="text-primary"><?= $nombre_usuario ?></span></h4>
                <!-- <p class="text-muted mb-2">Colegio: <strong><?= $colegio_nombre ?></strong></p>
                <p class="text-muted mb-2">
                    Curso: <strong id="curso-actual-text"><?= $curso_nombre ?></strong>
                    <button class="btn btn-link p-0 ms-2" data-bs-toggle="modal" data-bs-target="#cambiarCursoModal" style="font-size: 0.9rem;">Cambiar curso</button>
                </p> -->
            </div> 
        </div>
    </div>
    <div class="container mt-4">
        <h3>Registrar Apoderado</h3>
        <?php if ($success): ?>
            <div class="alert alert-success" id="msg-alert"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger" id="msg-alert"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST" class="row g-3" id="form-apoderado" autocomplete="off">
            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre completo</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= $reset_form ? '' : htmlspecialchars($_POST['nombre'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label for="rut" class="form-label">RUT</label>
                <input type="text" class="form-control" id="rut" name="rut" required maxlength="10" value="<?= $reset_form ? '' : htmlspecialchars($_POST['rut'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label for="telefono" class="form-label">Teléfono</label>
                <input type="text" class="form-control" id="telefono" name="telefono" required placeholder="+56912345678" value="<?= $reset_form ? '' : htmlspecialchars($_POST['telefono'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?= $reset_form ? '' : htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="6" value="">
            </div>
            <div class="col-md-3">
                <label for="rol" class="form-label">Rol</label>
                <select class="form-select" id="rol" name="rol" required>
                    <option value="apoderado" selected>Apoderado</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="colegio_id" class="form-label">Colegio</label>
                <select class="form-select" id="colegio_id" name="colegio_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach($colegios as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($reset_form ? '' : (($_POST['colegio_id'] ?? '') == $c['id'] ? 'selected' : '')) ?>><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="curso_id" class="form-label">Curso</label>
                <select class="form-select" id="curso_id" name="curso_id" required>
                    <option value="">Seleccione colegio primero</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="alumno_id" class="form-label">Alumno</label>
                <select class="form-select" id="alumno_id" name="alumno_id" required>
                    <option value="">Seleccione curso primero</option>
                </select>
            </div>
            <input type="hidden" name="submit" value="1">
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Registrar Apoderado</button>
            </div>
        </form>

        <hr class="my-5">
        <h4>Listado de Apoderados</h4>
         <small>Para poder ver el resto de los curso, pinchar los números de abajo de la tabla</small>
        <?php foreach ($apoderados_por_curso as $curso_nombre => $rows): ?>
            <h5 class="mt-4"><?= htmlspecialchars($curso_nombre) ?></h5>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 180px;">Nombre</th>
                            <th style="width: 50px;">RUT</th>
                            <th style="width: 100px;">Email</th>
                            <th style="width: 50px;">Teléfono</th>
                            <th style="width: 200px;">Colegio</th>
                            <th style="width: 160px;">Alumno</th>
                            <th style="width: 151px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows && $rows->num_rows > 0): ?>
                        <?php while ($row = $rows->fetch_assoc()): ?>
                            <tr>
                                <td class="editable" data-id="<?= $row['id'] ?>" data-field="nombre" style="width: 180px;"><?= htmlspecialchars($row['nombre'] ?? '') ?></td>
                                <td class="editable" data-id="<?= $row['id'] ?>" data-field="rut" style="width: 50px;"><?= htmlspecialchars($row['rut'] ?? '') ?></td>
                                <td class="editable" data-id="<?= $row['id'] ?>" data-field="email" style="width: 100px;"><?= htmlspecialchars($row['email'] ?? '') ?></td>
                                <td class="editable" data-id="<?= $row['id'] ?>" data-field="telefono" style="width: 50px;"><?= htmlspecialchars($row['telefono'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['colegio'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(
                                    trim(
                                        ($row['alumno_nombre'] ?? '') . " " .
                                        ($row['primer_apellido'] ?? '') . " " .
                                        ($row['segundo_apellido'] ?? '')
                                    )
                                ) ?></td>
                                <td style="width: 160px;">
                                    <button 
                                          class="btn btn-warning btn-sm btn-reset-pass" 
                                          data-id="<?= $row['id'] ?>"
                                          type="button" title="Resetear Contraseña">
                                          Contraseña
                                    </button>
                                    <form method="POST" style="display:inline;" class="form-eliminar-apoderado" onsubmit="return confirm('¿Está seguro de eliminar este apoderado?');">
                                        <input type="hidden" name="eliminar_apoderado_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar apoderado">
                                            <span aria-hidden="true">&times;</span> Eliminar
                                        </button>
                                    </form>
                                </td>
                                
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">No hay apoderados en este curso.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <nav>
            <ul class="pagination">
                <?php for($i=1;$i<=$total_pages;$i++): ?>
                    <li class="page-item<?= $i==$page ? ' active':'' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
</div>
<script>

$(document).on('click', '.btn-reset-pass', function() {
    var apoderadoId = $(this).data('id');
    var nuevaPass = prompt("Ingrese la nueva contraseña para el apoderado:");
    if(nuevaPass && nuevaPass.length >= 6){
        $.post('apoderado_password.php', {id: apoderadoId, password: nuevaPass}, function(resp){
            alert(resp);
        });
    } else if (nuevaPass) {
        alert('La contraseña debe tener al menos 6 caracteres.');
    }
});

$('#colegio_id').change(function(){
    let id = $(this).val();
    $('#curso_id').html('<option value="">Cargando...</option>');
    $('#alumno_id').html('<option value="">Seleccione curso primero</option>');
    if(id) {
        $.get('ajax_get_cursos.php', {colegio_id:id}, function(data){
            $('#curso_id').html(data);
        });
    }
});
$('#curso_id').change(function(){
    let id = $(this).val();
    $('#alumno_id').html('<option value="">Cargando...</option>');
    if(id) {
        $.get('ajax_get_alumnos.php', {curso_id:id}, function(data){
            $('#alumno_id').html(data);
        });
    }
});

// Limpiar formulario y ocultar alertas tras éxito o error
$(document).ready(function() {
    <?php if ($success || $error): ?>
        setTimeout(function() {
            $("#msg-alert").fadeOut();
        }, 5000);
        // Limpiar campos del formulario
        $("#form-apoderado")[0].reset();
        // Reset selects dependientes (cursos/alumnos)
        $('#curso_id').html('<option value="">Seleccione colegio primero</option>');
        $('#alumno_id').html('<option value="">Seleccione curso primero</option>');
    <?php endif; ?>
});

// Edición en línea por doble click sobre celdas .editable
$(document).on('dblclick', '.editable', function() {
    var td = $(this);
    if (td.find('input').length > 0) return; // Ya está editando

    var valor = td.text().trim();
    var id = td.data('id');
    var field = td.data('field');
    var input = $('<input type="text" class="form-control form-control-sm" />').val(valor);

    td.empty().append(input);
    input.focus().select();

    input.on('blur keydown', function(e) {
        if (e.type === "blur" || (e.type === "keydown" && e.which === 13)) {
            var nuevo = input.val();
            if (nuevo !== valor) {
                $.post('apoderado_update.php', {id: id, field: field, valor: nuevo}, function(resp) {
                    if (resp === 'OK') {
                        td.text(nuevo);
                    } else {
                        td.text(valor);
                        alert(resp);
                    }
                });
            } else {
                td.text(valor);
            }
        }
    });
});
</script>
</body>
</html>