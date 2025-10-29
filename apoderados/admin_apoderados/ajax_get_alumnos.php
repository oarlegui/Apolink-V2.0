<?php
session_start();
require '../../include/conexion.php';
require '../../include/seguridad.php';

$user_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$curso_id = intval($_GET['curso_id'] ?? 0);

// Obtener cursos asignados al usuario logueado (tesorero/admin_curso)
$curso_ids = [];
if (in_array($rol, ['tesorero', 'admin_curso'])) {
    $q = $conn->prepare("SELECT curso_id FROM tesorero_curso WHERE tesorero_id = ? UNION SELECT curso_id FROM user_curso WHERE user_id = ?");
    $q->bind_param("ii", $user_id, $user_id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) $curso_ids[] = $row['curso_id'];
    $q->close();
} else {
    $curso_ids = [0];
}
if (empty($curso_ids) || !in_array($curso_id, $curso_ids)) {
    echo '<option value="">No autorizado</option>';
    exit;
}

$res = $conn->query("SELECT id, nombre, primer_apellido, segundo_apellido FROM students WHERE curso_id = $curso_id ORDER BY nombre, primer_apellido, segundo_apellido");
echo '<option value="">Seleccione...</option>';
while ($row = $res->fetch_assoc()) {
    echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['nombre'].' '.$row['primer_apellido'].' '.$row['segundo_apellido']).'</option>';
}