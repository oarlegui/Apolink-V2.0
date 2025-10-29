<?php
session_start();
require '../../include/conexion.php';
require '../../include/seguridad.php';

require_login();
require_rol(['admin', 'admin_curso', 'tesorero']);

$id = intval($_POST['id'] ?? 0);
$field = $_POST['field'] ?? '';
$valor = trim($_POST['valor'] ?? '');

$permitidos = ['nombre', 'rut', 'email', 'telefono'];
if ($id <= 0 || !in_array($field, $permitidos)) {
    echo "Campo no permitido";
    exit;
}

// Validaciones mínimas
if ($field === 'email' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
    echo "Email inválido";
    exit;
}
if ($field === 'rut' && !preg_match('/^\d{7,8}-[kK\d]{1}$/', $valor)) {
    echo "RUT inválido";
    exit;
}
if ($field === 'telefono' && !preg_match('/^\+\d{1,3}\d{7,}$/', $valor)) {
    echo "Teléfono inválido";
    exit;
}

// Solo editar apoderados de cursos permitidos
$user_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$curso_ids = [];
if (in_array($rol, ['tesorero', 'admin_curso'])) {
    $q = $conn->prepare("SELECT curso_id FROM tesorero_curso WHERE tesorero_id = ? UNION SELECT curso_id FROM user_curso WHERE user_id = ?");
    $q->bind_param("ii", $user_id, $user_id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) $curso_ids[] = $row['curso_id'];
    $q->close();
}
if (empty($curso_ids)) $curso_ids = [0];
$curso_list = implode(',', array_map('intval', array_unique($curso_ids)));

$sql = "SELECT 1
        FROM users u
        JOIN alumno_apoderado aa ON aa.apoderado_id = u.id
        JOIN students a ON a.id = aa.alumno_id
        WHERE u.id = $id AND a.curso_id IN ($curso_list) AND u.rol = 'apoderado'
        LIMIT 1";
$res = $conn->query($sql);
if ($res->num_rows == 0) {
    echo "No autorizado";
    exit;
}

$stmt = $conn->prepare("UPDATE users SET $field = ? WHERE id = ?");
$stmt->bind_param("si", $valor, $id);
if ($stmt->execute()) {
    echo "OK";
} else {
    echo "Error al guardar";
}