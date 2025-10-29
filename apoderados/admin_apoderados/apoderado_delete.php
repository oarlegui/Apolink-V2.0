<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../include/conexion.php';
require '../../include/seguridad.php';
require_login();
require_rol(['admin', 'admin_curso', 'tesorero']);

$user_id = intval($_GET['id'] ?? 0);
if ($user_id > 0) {
    // Eliminar relaciones
    $conn->query("DELETE FROM alumno_apoderado WHERE apoderado_id = $user_id");
    $conn->query("DELETE FROM user_curso WHERE user_id = $user_id");
    $conn->query("DELETE FROM user_colegio WHERE user_id = $user_id");
    $conn->query("DELETE FROM users WHERE id = $user_id AND rol='apoderado'");
}
header('Location: apoderado_list.php');
exit;