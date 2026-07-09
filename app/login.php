<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    redirect_to('index.php?action=list');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = preg_replace('/\s+/', '', trim($_POST['usuario'] ?? ''));
    $contrasena = (string) ($_POST['contrasena'] ?? '');

    if ($usuario === '' || $contrasena === '') {
        $error = 'Completa usuario y contraseña.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $usuario)) {
        $error = 'El usuario debe tener entre 3 y 50 caracteres y solo puede usar letras, números o guion bajo.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, usuario, contrasena FROM usuarios WHERE usuario = ? LIMIT 1');
            $stmt->execute([$usuario]);
            $usuarioEncontrado = $stmt->fetch();

            if (!$usuarioEncontrado || !password_verify($contrasena, $usuarioEncontrado['contrasena'])) {
                $error = 'Usuario o contraseña incorrectos.';
            } else {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = (int) $usuarioEncontrado['id'];
                $_SESSION['usuario_nombre'] = $usuarioEncontrado['usuario'];
                registrar_bitacora($pdo, 'Inicio de sesión', 'Tabla: usuarios | ID usuario: ' . (int) $usuarioEncontrado['id'], $usuarioEncontrado['usuario']);
                flash_message('Inicio de sesión correcto');
                redirect_to('index.php?action=list');
            }
        } catch (PDOException $e) {
            $error = 'No se pudo iniciar sesión: ' . e($e->getMessage());
        }
    }
}

$flash = get_flash_message();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inicio de sesión | Calendario de Recordatorios</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="hero">
    <div class="hero-content">
        <p class="eyebrow">Organiza tus pendientes</p>
        <h1>Calendario de Recordatorios</h1>
        <p class="hero-text">Inicia sesión para acceder a tus recordatorios.</p>
    </div>
</header>

<main>
    <section class="card auth-card">
        <div class="section-title">
            <span class="badge">Acceso</span>
            <h2>Inicio de sesión</h2>
        </div>

        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

        <form method="post" class="reminder-form">
            <label for="usuario">Usuario</label>
            <input id="usuario" name="usuario" required minlength="3" maxlength="50" autocomplete="username" value="<?= e($_POST['usuario'] ?? '') ?>">

            <label for="contrasena">Contraseña</label>
            <input id="contrasena" name="contrasena" type="password" required minlength="6" autocomplete="current-password">

            <div class="form-actions">
                <button class="btn" type="submit">Iniciar sesión</button>
            </div>
        </form>

        <p class="auth-switch">¿No tienes cuenta? <a href="registro.php">Registra un nuevo usuario</a></p>
    </section>
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
