<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';

if ($action === 'login') {
    redirect_to('login.php');
}

if ($action === 'register') {
    redirect_to('registro.php');
}

if ($action === 'logout') {
    redirect_to('logout.php');
}

require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = '';
$today = date('Y-m-d');
$filtroEstado = $_GET['estado'] ?? 'todas';

if ($filtroEstado !== 'todas' && !in_array($filtroEstado, $estadosPermitidos, true)) {
    $filtroEstado = 'todas';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioId = current_user_id();
    $titulo = preg_replace('/\s+/', ' ', trim($_POST['titulo'] ?? ''));
    $fecha = trim($_POST['fecha'] ?? '');
    $hora = trim($_POST['hora'] ?? '');
    $descripcion = preg_replace('/\s+/', ' ', trim($_POST['descripcion'] ?? ''));
    $estado = isset($_POST['actualizar']) ? ($_POST['estado'] ?? 'pendiente') : 'pendiente';
    $postId = (int) ($_POST['id'] ?? 0);

    if ($titulo === '' || $fecha === '' || $hora === '') {
        $error = 'Completa título, fecha y hora.';
    } elseif (strlen($titulo) < 3) {
        $error = 'El título debe tener al menos 3 caracteres.';
    } elseif (strlen($titulo) > 100) {
        $error = 'El título no puede superar los 100 caracteres.';
    } elseif ($descripcion !== '' && strlen($descripcion) < 5) {
        $error = 'La descripción debe tener al menos 5 caracteres o quedar vacía.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $error = 'La fecha ingresada no tiene un formato válido.';
    } elseif ($fecha < $today) {
        $error = 'La fecha no puede ser anterior a la fecha actual.';
    } elseif (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
        $error = 'La hora ingresada no tiene un formato válido.';
    } elseif (isset($_POST['actualizar']) && !in_array($estado, $estadosPermitidos, true)) {
        $error = 'El estado seleccionado no es válido.';
    } elseif (existe_recordatorio_duplicado($pdo, $usuarioId, $titulo, $fecha, $hora, isset($_POST['actualizar']) ? $postId : 0)) {
        $error = 'Ya existe un recordatorio con el mismo título, fecha y hora.';
    } else {
        try {
            if (isset($_POST['crear'])) {
                $stmt = $pdo->prepare('INSERT INTO recordatorios (usuario_id, titulo, descripcion, fecha, hora, estado) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$usuarioId, $titulo, $descripcion, $fecha, $hora, 'pendiente']);
                $nuevoRecordatorioId = (int) $pdo->lastInsertId();
                registrar_bitacora($pdo, 'Crear registro', 'Tabla: recordatorios | ID recordatorio: ' . $nuevoRecordatorioId . ' | Usuario ID: ' . $usuarioId . ' | Título: ' . $titulo);
                flash_message('Recordatorio creado correctamente');
                redirect_to('index.php?action=list');
            }

            if (isset($_POST['actualizar'])) {
                $stmt = $pdo->prepare('UPDATE recordatorios SET titulo = ?, descripcion = ?, fecha = ?, hora = ?, estado = ? WHERE id = ? AND usuario_id = ?');
                $stmt->execute([$titulo, $descripcion, $fecha, $hora, $estado, $postId, $usuarioId]);
                registrar_bitacora($pdo, 'Modificar registro', 'Tabla: recordatorios | ID recordatorio: ' . $postId . ' | Usuario ID: ' . $usuarioId . ' | Estado: ' . $estado);
                flash_message('Recordatorio actualizado correctamente');
                redirect_to('index.php?action=edit_list');
            }
        } catch (PDOException $e) {
            $error = 'No se pudo guardar el recordatorio: ' . e($e->getMessage());
        }
    }
}

if ($action === 'delete' && $id > 0) {
    $usuarioId = current_user_id();
    $stmtDetalle = $pdo->prepare('SELECT titulo FROM recordatorios WHERE id = ? AND usuario_id = ?');
    $stmtDetalle->execute([$id, $usuarioId]);
    $recordatorioEliminado = $stmtDetalle->fetch();

    $stmt = $pdo->prepare('DELETE FROM recordatorios WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuarioId]);

    if ($stmt->rowCount() > 0) {
        registrar_bitacora($pdo, 'Eliminar registro', 'Tabla: recordatorios | ID recordatorio: ' . $id . ' | Usuario ID: ' . $usuarioId . ' | Título: ' . ($recordatorioEliminado['titulo'] ?? 'sin detalle'));
    }

    flash_message('Recordatorio eliminado correctamente');
    redirect_to('index.php?action=delete_list');
}

if ($action === 'complete' && $id > 0) {
    $usuarioId = current_user_id();
    $stmt = $pdo->prepare("UPDATE recordatorios SET estado = 'completada' WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$id, $usuarioId]);

    if ($stmt->rowCount() > 0) {
        registrar_bitacora($pdo, 'Modificar registro', 'Tabla: recordatorios | ID recordatorio: ' . $id . ' | Usuario ID: ' . $usuarioId . ' | Estado: completada');
    }

    flash_message('Recordatorio marcado como completado');
    redirect_to('index.php?action=list');
}

$accionesApp = ['list', 'create', 'edit_list', 'edit', 'delete_list', 'delete', 'complete'];
if (!in_array($action, $accionesApp, true)) {
    $action = 'list';
}

$editReminder = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM recordatorios WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, current_user_id()]);
    $editReminder = $stmt->fetch();

    if (!$editReminder) {
        flash_message('No se encontró el recordatorio solicitado', 'warning');
        redirect_to('index.php?action=edit_list');
    }
}

$recordatorios = [];
if ($filtroEstado === 'todas') {
    $stmt = $pdo->prepare('SELECT * FROM recordatorios WHERE usuario_id = ? ORDER BY fecha ASC, hora ASC');
    $stmt->execute([current_user_id()]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM recordatorios WHERE usuario_id = ? AND estado = ? ORDER BY fecha ASC, hora ASC');
    $stmt->execute([current_user_id(), $filtroEstado]);
}
$recordatorios = $stmt->fetchAll();

if ($action === 'list') {
    registrar_bitacora($pdo, 'Consultar registro', 'Tabla: recordatorios | Usuario ID: ' . current_user_id() . ' | Filtro estado: ' . $filtroEstado . ' | Cantidad consultada: ' . count($recordatorios));
}

$stats = [
    'total' => 0,
    'pendiente' => 0,
    'completada' => 0,
    'cancelada' => 0,
];
$stmt = $pdo->prepare('SELECT estado, COUNT(*) AS total FROM recordatorios WHERE usuario_id = ? GROUP BY estado');
$stmt->execute([current_user_id()]);
foreach ($stmt->fetchAll() as $row) {
    $estadoKey = $row['estado'];
    if (array_key_exists($estadoKey, $stats)) {
        $stats[$estadoKey] = (int) $row['total'];
        $stats['total'] += (int) $row['total'];
    }
}

$stmt = $pdo->prepare("SELECT * FROM recordatorios WHERE usuario_id = ? AND estado = 'pendiente' AND fecha >= ? ORDER BY fecha ASC, hora ASC LIMIT 4");
$stmt->execute([current_user_id(), $today]);
$proximosRecordatorios = $stmt->fetchAll();


$flash = get_flash_message();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendario de Recordatorios</title>
    <link rel="stylesheet" href="styles.css?v=3-columnas-forzado">
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/menu.php'; ?>

    <div class="workspace">
        <header class="hero">
            <div class="hero-content">
                <p class="eyebrow">Organiza tus pendientes</p>
                <h1>Calendario de Recordatorios</h1>
                <p class="hero-text">Usuario: <?= e($_SESSION['usuario_nombre'] ?? '') ?></p>
            </div>
        </header>

        <main>
            <?php if ($action === 'create' || $editReminder): ?>
                <section class="card form-card">
                    <div class="section-title">
                        <span class="badge"><?= $editReminder ? 'Modificar' : 'Crear' ?></span>
                        <h2><?= $editReminder ? 'Modificar recordatorio' : 'Crear recordatorio' ?></h2>
                    </div>
                    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
                    <form method="post" class="reminder-form">
                        <?php if ($editReminder): ?>
                            <input type="hidden" name="id" value="<?= (int) $editReminder['id'] ?>">
                        <?php endif; ?>

                        <label for="titulo">Título</label>
                        <input id="titulo" name="titulo" placeholder="Ej: Crear el Hito" required minlength="3" maxlength="100" value="<?= e($editReminder['titulo'] ?? ($_POST['titulo'] ?? '')) ?>">

                        <div class="form-grid">
                            <div>
                                <label for="fecha">Fecha</label>
                                <input id="fecha" name="fecha" type="date" min="<?= e($today) ?>" required value="<?= e($editReminder['fecha'] ?? ($_POST['fecha'] ?? '')) ?>">
                            </div>
                            <div>
                                <label for="hora">Hora</label>
                                <input id="hora" name="hora" type="time" required value="<?= e(substr($editReminder['hora'] ?? ($_POST['hora'] ?? ''), 0, 5)) ?>">
                            </div>
                        </div>

                        <?php if ($editReminder): ?>
                            <label for="estado">Estado</label>
                            <select id="estado" name="estado" required>
                                <?php foreach ($estadoLabels as $valor => $label): ?>
                                    <option value="<?= e($valor) ?>" <?= (($editReminder['estado'] ?? 'pendiente') === $valor) ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" maxlength="500" placeholder="Agrega detalles opcionales del recordatorio"><?= e($editReminder['descripcion'] ?? ($_POST['descripcion'] ?? '')) ?></textarea>

                        <div class="form-actions">
                            <?php if ($editReminder): ?>
                                <button class="btn" name="actualizar" type="submit">Actualizar</button>
                                <a class="btn btn-secondary" href="index.php?action=edit_list">Cancelar</a>
                            <?php else: ?>
                                <button class="btn" name="crear" type="submit">Guardar</button>
                                <a class="btn btn-secondary" href="index.php?action=list">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <?php if (in_array($action, ['list', 'edit_list', 'delete_list'], true)): ?>
                <section class="stats-grid" aria-label="Resumen de recordatorios">
                    <article class="stat-card">
                        <span>Total</span>
                        <strong><?= (int) $stats['total'] ?></strong>
                        <p>Recordatorios creados</p>
                    </article>
                    <article class="stat-card">
                        <span>Pendientes</span>
                        <strong><?= (int) $stats['pendiente'] ?></strong>
                        <p>Por realizar</p>
                    </article>
                    <article class="stat-card">
                        <span>Completadas</span>
                        <strong><?= (int) $stats['completada'] ?></strong>
                        <p>Tareas finalizadas</p>
                    </article>
                    <article class="stat-card">
                        <span>Canceladas</span>
                        <strong><?= (int) $stats['cancelada'] ?></strong>
                        <p>Tareas pausadas</p>
                    </article>
                </section>

                <section class="dashboard-layout">
                    <div class="dashboard-main">
                        <section class="card filter-card">
                            <div class="section-title filter-title">
                                <div>
                                    <span class="badge">
                                        <?php if ($action === 'edit_list'): ?>Modificar<?php elseif ($action === 'delete_list'): ?>Eliminar<?php else: ?>Consultar<?php endif; ?>
                                    </span>
                                    <h2>
                                        <?php if ($action === 'edit_list'): ?>Selecciona un recordatorio para modificar<?php elseif ($action === 'delete_list'): ?>Selecciona un recordatorio para eliminar<?php else: ?>Consultar recordatorios<?php endif; ?>
                                    </h2>
                                </div>
                            </div>
                            <form method="get" class="filter-form">
                                <input type="hidden" name="action" value="<?= e($action) ?>">
                                <div>
                                    <label for="estadoFiltro">Filtrar por estado</label>
                                    <select id="estadoFiltro" name="estado">
                                        <option value="todas" <?= $filtroEstado === 'todas' ? 'selected' : '' ?>>Todas</option>
                                        <?php foreach ($estadoLabels as $valor => $label): ?>
                                            <option value="<?= e($valor) ?>" <?= $filtroEstado === $valor ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button class="btn" type="submit">Aplicar filtro</button>
                                <a class="btn btn-secondary" href="index.php?action=<?= e($action) ?>">Limpiar</a>
                            </form>
                        </section>

                        <section class="card">
                            <?php if (!$recordatorios): ?>
                                <p class="empty">No hay recordatorios para mostrar.</p>
                            <?php else: ?>
                                <div class="reminders-grid">
                                    <?php foreach ($recordatorios as $r): ?>
                                        <article class="reminder-card row-estado-<?= e($r['estado']) ?>">
                                            <div class="reminder-card-header">
                                                <div>
                                                    <p class="reminder-date"><?= e(date('d-m-Y', strtotime($r['fecha']))) ?></p>
                                                    <p class="reminder-time"><?= e(substr($r['hora'], 0, 5)) ?> hrs</p>
                                                </div>
                                                <span class="status-badge status-<?= e($r['estado']) ?>">
                                                    <?= e($estadoLabels[$r['estado']] ?? $r['estado']) ?>
                                                </span>
                                            </div>

                                            <h3 class="reminder-title"><?= e($r['titulo']) ?></h3>

                                            <?php if (trim((string) $r['descripcion']) !== ''): ?>
                                                <p class="reminder-description"><?= e($r['descripcion']) ?></p>
                                            <?php else: ?>
                                                <p class="reminder-description muted-text">Sin descripción.</p>
                                            <?php endif; ?>

                                            <?php if ($action === 'edit_list'): ?>
                                                <div class="actions card-actions">
                                                    <a class="btn btn-secondary btn-small" href="index.php?action=edit&id=<?= (int) $r['id'] ?>">Modificar</a>
                                                </div>
                                            <?php elseif ($action === 'delete_list'): ?>
                                                <div class="actions card-actions">
                                                    <a class="btn btn-danger btn-small delete-link" href="index.php?action=delete&id=<?= (int) $r['id'] ?>" data-title="<?= e($r['titulo']) ?>">Eliminar</a>
                                                </div>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <aside class="dashboard-side">

                        <section class="card next-card">
                            <div class="section-title compact-title">
                                <span class="badge">Próximos</span>
                                <h2>Pendientes cercanos</h2>
                            </div>
                            <?php if (!$proximosRecordatorios): ?>
                                <p class="empty side-empty">No tienes pendientes próximos.</p>
                            <?php else: ?>
                                <div class="next-list">
                                    <?php foreach ($proximosRecordatorios as $proximo): ?>
                                        <article class="next-item">
                                            <strong><?= e($proximo['titulo']) ?></strong>
                                            <span><?= e(date('d-m-Y', strtotime($proximo['fecha']))) ?> · <?= e(substr($proximo['hora'], 0, 5)) ?> hrs</span>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </aside>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>

<div class="modal-backdrop" id="appModal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button class="modal-close" type="button" id="modalClose" aria-label="Cerrar ventana">&times;</button>
        <h3 id="modalTitle">Confirmación</h3>
        <p id="modalMessage">Mensaje de confirmación</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" type="button" id="modalCancel">Cancelar</button>
            <button class="btn btn-danger" type="button" id="modalConfirm">Confirmar</button>
        </div>
    </div>
</div>

<?php if ($flash): ?>
    <script>
        window.flashMessage = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;
    </script>
<?php endif; ?>
<script src="script.js"></script>
</body>
</html>
