<?php
require '../../include/conexion.php';
require '../../include/seguridad.php';
$por_pagina = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page-1)*$por_pagina;

$res_total = $conn->query("SELECT COUNT(*) as total FROM users WHERE rol='apoderado'");
$total = $res_total->fetch_assoc()['total'];
$total_paginas = ceil($total/$por_pagina);

$query = "
SELECT u.id, u.nombre, u.rut, u.email, u.telefono, u.rol,
       c.nombre AS colegio, cur.nombre AS curso, 
       a.nombre AS alumno_nombre, a.primer_apellido, a.segundo_apellido
FROM users u
LEFT JOIN user_colegio uc ON uc.user_id = u.id
LEFT JOIN colegios c ON c.id = uc.colegio_id
LEFT JOIN user_curso ucu ON ucu.user_id = u.id
LEFT JOIN cursos cur ON cur.id = ucu.curso_id
LEFT JOIN alumno_apoderado aa ON aa.apoderado_id = u.id
LEFT JOIN students a ON a.id = aa.alumno_id
WHERE u.rol = 'apoderado'
ORDER BY u.nombre
LIMIT $por_pagina OFFSET $offset
";
$res = $conn->query($query);
?>
<div class="table-responsive">
    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>Nombre</th>
                <th>RUT</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>Colegio</th>
                <th>Curso</th>
                <th>Alumno</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['nombre']) ?></td>
                <td><?= htmlspecialchars($row['rut']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['telefono']) ?></td>
                <td><?= htmlspecialchars($row['colegio'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['curso'] ?? '-') ?></td>
                <td><?= htmlspecialchars(trim($row['alumno_nombre'] . " " . $row['primer_apellido'] . " " . $row['segundo_apellido']) ?? '-') ?></td>
                <td>
                    <a href="apoderado_edit.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">Editar</a>
                    <a href="apoderado_password.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Password</a>
                    <a href="apoderado_delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar?');">Eliminar</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<nav>
    <ul class="pagination">
        <?php for($i=1;$i<=$total_paginas;$i++): ?>
            <li class="page-item<?= $i==$page ? ' active':'' ?>">
                <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>