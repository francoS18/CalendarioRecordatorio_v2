<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    redirect_to('index.php?action=list');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = preg_replace('/\s+/', '', trim($_POST['usuario'] ?? ''));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $contrasenaConfirm = (string) ($_POST['contrasena_confirm'] ?? '');

    if ($usuario === '' || $contrasena === '' || $contrasenaConfirm === '') {
        $error = 'Completa todos los campos.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $usuario)) {
        $error = 'El usuario debe tener entre 3 y 50 caracteres y solo puede usar letras, números o guion bajo.';
    } elseif (strlen($contrasena) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($contrasena !== $contrasenaConfirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE usuario = ?');
            $stmt->execute([$usuario]);

            if ((int) $stmt->fetchColumn() > 0) {
                $error = 'Ese usuario ya está registrado.';
            } else {
                $hash = password_hash($contrasena, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO usuarios (usuario, contrasena) VALUES (?, ?)');
                $stmt->execute([$usuario, $hash]);
                $nuevoUsuarioId = (int) $pdo->lastInsertId();
                registrar_bitacora($pdo, 'Creación de usuario', 'Tabla: usuarios | ID usuario: ' . $nuevoUsuarioId, $usuario);
                flash_message('Usuario registrado correctamente. Ahora inicia sesión.');
                redirect_to('login.php');
            }
        } catch (PDOException $e) {
            $error = 'No se pudo registrar el usuario: ' . e($e->getMessage());
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
    <title>Registro | Calendario de Recordatorios</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="hero">
    <div class="hero-content">
        <p class="eyebrow">Organiza tus pendientes</p>
        <h1>Calendario de Recordatorios</h1>
        <p class="hero-text">Crea un usuario para comenzar a guardar recordatorios.</p>
    </div>
</header>

<main>
    <section class="card auth-card">
        <div class="section-title">
            <span class="badge">Registro</span>
            <h2>Registrar nuevo usuario</h2>
        </div>

        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

        <form method="post" class="reminder-form">
            <label for="usuario">Usuario</label>
            <input id="usuario" name="usuario" required minlength="3" maxlength="50" autocomplete="username" value="<?= e($_POST['usuario'] ?? '') ?>">

            <label for="contrasena">Contraseña</label>
            <input id="contrasena" name="contrasena" type="password" required minlength="6" autocomplete="new-password">

            <label for="contrasena_confirm">Confirmar contraseña</label>
            <input id="contrasena_confirm" name="contrasena_confirm" type="password" required minlength="6" autocomplete="new-password">

            <div class="form-actions">
                <button class="btn" type="submit">Registrar usuario</button>
            </div>
        </form>

        <p class="auth-switch">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
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
