<?php
session_start();

$host = getenv('DB_HOST') ?: 'db';
$dbname = getenv('DB_NAME') ?: 'calendario_db';
$user = getenv('DB_USER') ?: 'admin';
$pass = getenv('DB_PASSWORD') ?: 'admin';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Error de conexion a la base de datos: ' . htmlspecialchars($e->getMessage()));
}

function redirect_home(string $queryString = ''): void {
    $url = 'index.php';
    if ($queryString !== '') {
        $url .= '?' . $queryString;
    }
    header('Location: ' . $url);
    exit;
}

function flash_message(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}

function ensure_schema(PDO $pdo): void {
    $stmt = $pdo->query("SHOW COLUMNS FROM recordatorios LIKE 'estado'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE recordatorios ADD COLUMN estado ENUM('pendiente','completada','cancelada') NOT NULL DEFAULT 'pendiente' AFTER hora");
    }

    // Se elimina la prioridad si la base de datos viene de una version anterior del proyecto.
    $stmt = $pdo->query("SHOW COLUMNS FROM recordatorios LIKE 'prioridad'");
    if ($stmt->fetch()) {
        $pdo->exec("ALTER TABLE recordatorios DROP COLUMN prioridad");
    }

    // Si no existen recordatorios duplicados, se agrega una restriccion para evitar duplicados.
    $stmt = $pdo->query("SHOW INDEX FROM recordatorios WHERE Key_name = 'uq_recordatorio_titulo_fecha_hora'");
    if (!$stmt->fetch()) {
        $duplicados = $pdo->query("SELECT titulo, fecha, hora, COUNT(*) AS total FROM recordatorios GROUP BY titulo, fecha, hora HAVING total > 1 LIMIT 1")->fetch();
        if (!$duplicados) {
            $pdo->exec("ALTER TABLE recordatorios ADD UNIQUE KEY uq_recordatorio_titulo_fecha_hora (titulo, fecha, hora)");
        }
    }
}

function existe_recordatorio_duplicado(PDO $pdo, string $titulo, string $fecha, string $hora, int $ignorarId = 0): bool {
    if ($ignorarId > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM recordatorios WHERE titulo = ? AND fecha = ? AND hora = ? AND id <> ?');
        $stmt->execute([$titulo, $fecha, $hora, $ignorarId]);
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM recordatorios WHERE titulo = ? AND fecha = ? AND hora = ?');
        $stmt->execute([$titulo, $fecha, $hora]);
    }

    return (int) $stmt->fetchColumn() > 0;
}

try {
    ensure_schema($pdo);
} catch (PDOException $e) {
    die('Error al preparar la base de datos: ' . htmlspecialchars($e->getMessage()));
}

$estadosPermitidos = ['pendiente', 'completada', 'cancelada'];
$estadoLabels = [
    'pendiente' => 'Pendiente',
    'completada' => 'Completada',
    'cancelada' => 'Cancelada',
];

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = '';
$today = date('Y-m-d');
$filtroEstado = $_GET['estado'] ?? 'todas';
if ($filtroEstado !== 'todas' && !in_array($filtroEstado, $estadosPermitidos, true)) {
    $filtroEstado = 'todas';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = preg_replace('/\s+/', ' ', trim($_POST['titulo'] ?? ''));
    $fecha = trim($_POST['fecha'] ?? '');
    $hora = trim($_POST['hora'] ?? '');
    $descripcion = preg_replace('/\s+/', ' ', trim($_POST['descripcion'] ?? ''));
    $estado = isset($_POST['actualizar']) ? ($_POST['estado'] ?? 'pendiente') : 'pendiente';
    $postId = (int) ($_POST['id'] ?? 0);

    if ($titulo === '' || $fecha === '' || $hora === '') {
        $error = 'Completa titulo, fecha y hora.';
    } elseif (strlen($titulo) < 3) {
        $error = 'El titulo debe tener al menos 3 caracteres.';
    } elseif (strlen($titulo) > 100) {
        $error = 'El titulo no puede superar los 100 caracteres.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $error = 'La fecha ingresada no tiene un formato valido.';
    } elseif ($fecha < $today) {
        $error = 'La fecha no puede ser anterior a la fecha actual.';
    } elseif (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
        $error = 'La hora ingresada no tiene un formato valido.';
    } elseif (isset($_POST['actualizar']) && !in_array($estado, $estadosPermitidos, true)) {
        $error = 'El estado seleccionado no es valido.';
    } elseif (existe_recordatorio_duplicado($pdo, $titulo, $fecha, $hora, isset($_POST['actualizar']) ? $postId : 0)) {
        $error = 'Ya existe un recordatorio con el mismo titulo, fecha y hora.';
    } else {
        try {
            if (isset($_POST['crear'])) {
                $stmt = $pdo->prepare('INSERT INTO recordatorios (titulo, descripcion, fecha, hora, estado) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$titulo, $descripcion, $fecha, $hora, 'pendiente']);
                flash_message('Tarea creada correctamente');
                redirect_home();
            }

            if (isset($_POST['actualizar'])) {
                $stmt = $pdo->prepare('UPDATE recordatorios SET titulo = ?, descripcion = ?, fecha = ?, hora = ?, estado = ? WHERE id = ?');
                $stmt->execute([$titulo, $descripcion, $fecha, $hora, $estado, $postId]);
                flash_message('Tarea actualizada correctamente');
                redirect_home();
            }
        } catch (PDOException $e) {
            $error = 'No se pudo guardar el recordatorio: ' . htmlspecialchars($e->getMessage());
        }
    }
}

if ($action === 'delete' && $id > 0) {
    $stmt = $pdo->prepare('DELETE FROM recordatorios WHERE id = ?');
    $stmt->execute([$id]);
    flash_message('Tarea eliminada correctamente');
    redirect_home();
}

if ($action === 'complete' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE recordatorios SET estado = 'completada' WHERE id = ?");
    $stmt->execute([$id]);
    flash_message('Tarea marcada como completada');
    redirect_home();
}

$editReminder = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM recordatorios WHERE id = ?');
    $stmt->execute([$id]);
    $editReminder = $stmt->fetch();
}

if ($filtroEstado === 'todas') {
    $stmt = $pdo->query('SELECT * FROM recordatorios ORDER BY fecha ASC, hora ASC');
    $recordatorios = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT * FROM recordatorios WHERE estado = ? ORDER BY fecha ASC, hora ASC');
    $stmt->execute([$filtroEstado]);
    $recordatorios = $stmt->fetchAll();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendario de Recordatorios</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="hero">
    <div class="hero-content">
        <p class="eyebrow">Organiza tus pendientes</p>
        <h1>Calendario de Recordatorios</h1>
    </div>
</header>
<main>
    <nav class="top-actions">
        <a href="index.php" class="nav-link">Ver recordatorios</a>
        <a href="index.php?action=create" class="nav-link nav-primary">Crear recordatorio</a>
    </nav>

    <?php if ($action === 'create' || $editReminder): ?>
        <section class="card form-card">
            <div class="section-title">
                <span class="badge"><?= $editReminder ? 'Editar' : 'Nuevo' ?></span>
                <h2><?= $editReminder ? 'Modificar recordatorio' : 'Crear recordatorio' ?></h2>
            </div>
            <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <form method="post" class="reminder-form">
                <?php if ($editReminder): ?>
                    <input type="hidden" name="id" value="<?= (int) $editReminder['id'] ?>">
                <?php endif; ?>

                <label for="titulo">Título</label>
                <input id="titulo" name="titulo" placeholder="Ej: Crear el Hito" required minlength="3" maxlength="100" value="<?= htmlspecialchars($editReminder['titulo'] ?? ($_POST['titulo'] ?? '')) ?>">

                <div class="form-grid">
                    <div>
                        <label for="fecha">Fecha</label>
                        <input id="fecha" name="fecha" type="date" min="<?= htmlspecialchars($today) ?>" required value="<?= htmlspecialchars($editReminder['fecha'] ?? ($_POST['fecha'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="hora">Hora</label>
                        <input id="hora" name="hora" type="time" required value="<?= htmlspecialchars(substr($editReminder['hora'] ?? ($_POST['hora'] ?? ''), 0, 5)) ?>">
                    </div>
                </div>

                <?php if ($editReminder): ?>
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado" required>
                        <?php foreach ($estadoLabels as $valor => $label): ?>
                            <option value="<?= htmlspecialchars($valor) ?>" <?= (($editReminder['estado'] ?? 'pendiente') === $valor) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" maxlength="500" placeholder="Agrega detalles opcionales del recordatorio"><?= htmlspecialchars($editReminder['descripcion'] ?? ($_POST['descripcion'] ?? '')) ?></textarea>

                <div class="form-actions">
                    <?php if ($editReminder): ?>
                        <button class="btn" name="actualizar" type="submit">Actualizar</button>
                        <a class="btn btn-secondary" href="index.php">Cancelar</a>
                    <?php else: ?>
                        <button class="btn" name="crear" type="submit">Guardar</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($action !== 'create' && !$editReminder): ?>
        <section class="card filter-card">
            <div class="section-title filter-title">
                <div>
                    <span class="badge">Filtro</span>
                    <h2>Filtrar recordatorios</h2>
                </div>
            </div>
            <form method="get" class="filter-form">
                <label for="estadoFiltro">Estado</label>
                <select id="estadoFiltro" name="estado">
                    <option value="todas" <?= $filtroEstado === 'todas' ? 'selected' : '' ?>>Todas</option>
                    <?php foreach ($estadoLabels as $valor => $label): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $filtroEstado === $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Aplicar filtro</button>
                <a class="btn btn-secondary" href="index.php">Limpiar</a>
            </form>
        </section>

        <section class="card">
            <div class="section-title list-title">
                <span class="badge">Listado</span>
                <h2>Recordatorios registrados</h2>
            </div>
            <?php if (count($recordatorios) === 0): ?>
                <p class="empty">No hay recordatorios registrados para este filtro.</p>
            <?php else: ?>
                <div class="reminders-grid">
                    <?php foreach ($recordatorios as $r): ?>
                        <article class="reminder-card row-estado-<?= htmlspecialchars($r['estado']) ?>">
                            <div class="reminder-card-header">
                                <div>
                                    <p class="reminder-date"><?= htmlspecialchars(date('d-m-Y', strtotime($r['fecha']))) ?></p>
                                    <p class="reminder-time"><?= htmlspecialchars(substr($r['hora'], 0, 5)) ?> hrs</p>
                                </div>
                                <span class="status-badge status-<?= htmlspecialchars($r['estado']) ?>">
                                    <?= htmlspecialchars($estadoLabels[$r['estado']] ?? $r['estado']) ?>
                                </span>
                            </div>

                            <h3 class="reminder-title"><?= htmlspecialchars($r['titulo']) ?></h3>

                            <?php if (trim($r['descripcion']) !== ''): ?>
                                <p class="reminder-description"><?= htmlspecialchars($r['descripcion']) ?></p>
                            <?php else: ?>
                                <p class="reminder-description muted-text">Sin descripción.</p>
                            <?php endif; ?>

                            <div class="actions card-actions">
                                <?php if ($r['estado'] !== 'completada'): ?>
                                    <a class="btn btn-small" href="index.php?action=complete&id=<?= (int) $r['id'] ?>">Completar</a>
                                <?php endif; ?>
                                <a class="btn btn-secondary btn-small" href="index.php?action=edit&id=<?= (int) $r['id'] ?>">Modificar</a>
                                <a class="btn btn-danger btn-small delete-link" href="index.php?action=delete&id=<?= (int) $r['id'] ?>" data-title="<?= htmlspecialchars($r['titulo']) ?>">Borrar</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

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
