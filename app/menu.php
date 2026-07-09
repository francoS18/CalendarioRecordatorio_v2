<?php
if (!function_exists('is_logged_in')) {
    require_once __DIR__ . '/config.php';
}

$accionActual = $action ?? ($_GET['action'] ?? 'list');
$statsMenu = $stats ?? [
    'total' => 0,
    'pendiente' => 0,
    'completada' => 0,
    'cancelada' => 0,
];
?>
<?php if (is_logged_in()): ?>
    <aside class="sidebar">
        <div class="brand-card">
            <div class="brand-icon">✓</div>
            <div>
                <p class="eyebrow">Panel</p>
                <h2>Recordatorios</h2>
            </div>
        </div>

        <nav class="sidebar-nav" aria-label="Menú principal CRUD">
            <a href="index.php?action=create" class="sidebar-link <?= $accionActual === 'create' ? 'active' : '' ?>">Crear</a>
            <a href="index.php?action=list" class="sidebar-link <?= $accionActual === 'list' ? 'active' : '' ?>">Consultar</a>
            <a href="index.php?action=edit_list" class="sidebar-link <?= in_array($accionActual, ['edit_list', 'edit'], true) ? 'active' : '' ?>">Modificar</a>
            <a href="index.php?action=delete_list" class="sidebar-link <?= in_array($accionActual, ['delete_list', 'delete'], true) ? 'active' : '' ?>">Eliminar</a>
            <a href="logout.php" class="sidebar-link sidebar-danger">Cerrar sesión</a>
        </nav>

        <div class="sidebar-summary">
            <span><?= (int) ($statsMenu['total'] ?? 0) ?></span>
            <p>recordatorios registrados</p>
        </div>
    </aside>
<?php endif; ?>
