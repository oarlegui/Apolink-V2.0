<?php
session_start();
require '../../include/conexion.php';
require '../../include/seguridad.php';

$user_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$colegio_id = intval($_GET['colegio_id'] ?? 0);

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
    // Si quieres permitir que otros roles vean todo, aquí puedes dejarlo vacío o agregar lógica extra.
    $curso_ids = [0];
}
if (empty($curso_ids)) $curso_ids = [0];
$curso_list = implode(',', array_map('intval', array_unique($curso_ids)));

$sql = "SELECT id, nombre FROM cursos WHERE colegio_id = ? AND id IN ($curso_list) ORDER BY nombre";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colegio_id);
$stmt->execute();
$res = $stmt->get_result();

echo '<option value="">Seleccione...</option>';
while ($row = $res->fetch_assoc()) {
    echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['nombre']).'</option>';
}