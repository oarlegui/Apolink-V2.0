<?php


session_start();
require 'include/conexion.php';
require 'include/seguridad.php';
require_login();
require_rol(['ROL_CORRECTO']);



require 'include/conexion.php';
require 'include/seguridad.php';

require_login();
$rol = $_SESSION['rol'];
$usuario_id = $_SESSION['usuario_id'];
$colegio_id = $_SESSION['colegio_id'];

// Función para verificar módulo activo
function modulo_activo($modulo, $curso_id, $colegio_id, $conn) {
    $stmt = $conn->prepare("SELECT activo FROM curso_modulo WHERE curso_id=? AND colegio_id=? AND modulo=?");
    $stmt->bind_param("iis", $curso_id, $colegio_id, $modulo);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r && $r['activo'] == 1;
}

// Curso actual (puedes personalizar)
$curso_id = $_SESSION['curso_id'] ?? 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | Apolink</title>
  <link href="assets/css/apolink.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="display: flex; min-height: 100vh;">
<!-- Sidebar -->
<div class="sidebar-apolink">
  <h5 class="mb-4">📘 Apolink</h5>
  <a href="dashboard.php" class="d-block mb-2 btn btn-outline-primary w-100">🏠 Inicio</a>
  <a href="alumnos/students.php" class="d-block mb-2 btn btn-outline-primary w-100 <?= modulo_activo('alumnos', $curso_id, $colegio_id, $conn) ? '' : 'disabled-module' ?>">👩‍🎓 Alumnos</a>
  <a href="apoderados/apoderados.php" class="d-block mb-2 btn btn-outline-primary w-100 <?= modulo_activo('apoderados', $curso_id, $colegio_id, $conn) ? '' : 'disabled-module' ?>">👨‍👧 Apoderados</a>
  <a href="pagos/pagos.php" class="d-block mb-2 btn btn-outline-primary w-100 <?= modulo_activo('pagos', $curso_id, $colegio_id, $conn) ? '' : 'disabled-module' ?>">💰 Pagos</a>
  <a href="gastos/gastos.php" class="d-block mb-2 btn btn-outline-primary w-100 <?= modulo_activo('gastos', $curso_id, $colegio_id, $conn) ? '' : 'disabled-module' ?>">📉 Gastos</a>
  <a href="eventos/calendar.php" class="d-block mb-2 btn btn-outline-primary w-100 <?= modulo_activo('eventos', $curso_id, $colegio_id, $conn) ? '' : 'disabled-module' ?>">📅 Eventos</a>
  <a href="empresas/empresas.php" class="d-block mb-2 btn btn-outline-primary w-100 <?= modulo_activo('empresas', $curso_id, $colegio_id, $conn) ? '' : 'disabled-module' ?>">🏪 Empresas</a>
  <a href="logout.php" class="btn btn-outline-danger mt-3 w-100">Salir</a>
  <?php if ($_SESSION['rol'] === 'admin'): ?>
  <hr>
  <h6 class="text-muted">⚙️ Administración</h6>
  <a href="include/modulo.php" class="d-block mb-2 btn btn-outline-secondary w-100">🧩 Activar Módulos</a>
  <a href="sesiones.php" class="d-block mb-2 btn btn-outline-secondary w-100">🔐 Sesiones</a>
  <a href="bloquear_ip.php" class="d-block mb-2 btn btn-outline-secondary w-100">🚫 Bloquear IP</a>
  <a href="ips_bloqueadas.php" class="d-block mb-2 btn btn-outline-secondary w-100">🛑 IPs Bloqueadas</a>
<?php endif; ?>

</div>

<!-- Contenido -->
<div class="flex-grow-1 p-4">
  <div class="topbar-apolink d-flex justify-content-between align-items-center mb-4">
    <span>Panel de <strong><?= strtoupper($rol) ?></strong></span>
    <img src="assets/img/logo_apolink.png" height="30" alt="Logo Apolink">
  </div>

  <div class="row">
    <?php
      $modulos = [
        'alumnos' => '👩‍🎓 Alumnos',
        'apoderados' => '👨‍👧 Apoderados',
        'pagos' => '💰 Pagos',
        'gastos' => '📉 Gastos',
        'eventos' => '📅 Eventos',
        'empresas' => '🏪 Empresas'
      ];

      foreach ($modulos as $clave => $nombre):
        $activo = modulo_activo($clave, $curso_id, $colegio_id, $conn);
    ?>
    <div class="col-md-4 mb-3">
      <div class="card-apolink text-center <?= $activo ? '' : 'disabled-module' ?>">
        <h6><?= $nombre ?></h6>
        <p class="text-muted small"><?= $activo ? 'Disponible' : 'No habilitado para este curso' ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
