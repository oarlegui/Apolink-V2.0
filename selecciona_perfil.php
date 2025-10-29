<?php
session_start();
require 'include/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rol'])) {
    $rol_seleccionado = $_POST['rol'];
    $_SESSION['rol'] = $rol_seleccionado;

    // Redirigir según el rol seleccionado
    switch ($rol_seleccionado) {
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
        default:
            header("Location: index.php");
            break;
    }
    exit;
} else {
    // Si no se selecciona un rol válido, redirigir al inicio
    header("Location: index.php");
    exit;
}