<?php
// archivo: seleccionar_alumno.php
//DEBE IR OBLIGATORIAMENTE EN TODOS LOS ARCHIVOS

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'include/conexion.php';
require 'include/seguridad.php';
require 'include/get_assigned_courses.php';

$alumno_id = $_SESSION['alumno_id']; // Usar el alumno seleccionado en la sesi贸n
$apoderado_id = $_SESSION['usuario_id'];
$colegio_id = $_SESSION['colegio_id'];
$rol = $_SESSION['rol'];
$usuario_id = $_SESSION['usuario_id'];
// Obtener la fecha actual
$fecha_actual = date("Y-m-d");
// Obtener el día y mes actual
$dia_mes_actual = date("m-d");

//CIERRE DE DEBE IR OBLIGATORIAMENTE EN TODOS LOS ARCHIVOS

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

// Verificar que se haya enviado un alumno_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alumno_id'])) {
     $nuevo_alumno_id = (int) $_POST['alumno_id'];

    // Actualizar el alumno en la sesión
    $_SESSION['alumno_id'] = $nuevo_alumno_id;

    // Verificar que el alumno pertenece al usuario autenticado
    $stmt = $conn->prepare("
        SELECT s.id AS alumno_id, s.nombre AS alumno_nombre 
        FROM students s
        JOIN alumno_apoderado aa ON aa.alumno_id = s.id
        WHERE s.id = ? AND aa.apoderado_id = ?
    ");
    $stmt->bind_param("ii", $nuevo_alumno_id, $_SESSION['usuario_id']);
    $stmt->execute();
    $alumno = $stmt->get_result()->fetch_assoc();

    if ($alumno) {
        // Alumno válido, guardar en la sesión
        $_SESSION['alumno_id'] = $alumno['alumno_id'];
        $_SESSION['alumno_nombre'] = $alumno['alumno_nombre'];

        // Redirigir al dashboard del apoderado
        header("Location: dashboard_apoderado_v2.php"); 
        exit;
    } else {
        // Si el alumno no pertenece al usuario, mostrar error
        $_SESSION['error'] = "El alumno seleccionado no está asociado a tu cuenta.";
    }
} else {
    // Si no se envió alumno_id, mostrar error
    $_SESSION['error'] = "Por favor selecciona un alumno para continuar.";
}
// Redirigir de vuelta al index si hay un error
header("Location: index.php");
exit;