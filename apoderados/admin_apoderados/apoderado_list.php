<?php
session_start();
require '../../include/conexion.php';
require '../../include/seguridad.php';
require_login();
require_rol(['admin', 'admin_curso', 'tesorero']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Apoderados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<?php include '../../include/sidebar_dashboard_tesorero.php'; ?>
<div class="main">
    <div class="container mt-4">
        <h3>Listado de Apoderados</h3>
        <a href="apoderado_create.php" class="btn btn-success mb-3">➕ Nuevo Apoderado</a>
        <div id="tabla-apoderados"></div>
    </div>
</div>
<script>
function cargarTabla(page=1) {
    $.get('ajax_apoderado_table.php', {page: page}, function(data){
        $('#tabla-apoderados').html(data);
    });
}
$(document).on('click', '.pagination a', function(e){
    e.preventDefault();
    const page = $(this).data('page');
    cargarTabla(page);
});
$(document).ready(function(){
    cargarTabla(1);
});
</script>
</body>
</html>