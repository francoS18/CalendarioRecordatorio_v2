<?php
session_start();
date_default_timezone_set('America/Santiago');

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

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash_message(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}

function is_logged_in(): bool {
    return isset($_SESSION['usuario_id']);
}

function current_user_id(): int {
    return (int) ($_SESSION['usuario_id'] ?? 0);
}

function current_user_name(): string {
    return (string) ($_SESSION['usuario_nombre'] ?? 'Invitado');
}

function obtener_ip_host_cliente() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }

    return 'No disponible';
}

function registrar_bitacora(PDO $pdo, string $tipo, string $detalle, ?string $nombreUsuario = null): void {
    $nombreUsuario = $nombreUsuario ?: current_user_name();
    $fechaHora = date('d/m/Y, H:i:s');
    $ipHostCliente = obtener_ip_host_cliente();

    try {
        $stmt = $pdo->prepare('INSERT INTO log_eventos (fecha_hora, nombre_usuario, tipo, detalle, ip_host_cliente) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$fechaHora, $nombreUsuario, $tipo, $detalle, $ipHostCliente]);
    } catch (PDOException $e) {
        error_log('No se pudo registrar la bitácora: ' . $e->getMessage());
    }
}

function require_login(): void {
    if (!is_logged_in()) {
        flash_message('Debes iniciar sesión para ingresar.', 'warning');
        redirect_to('login.php');
    }
}

function get_flash_message(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function ensure_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        correo VARCHAR(120) NOT NULL UNIQUE,
        contrasena VARCHAR(255) NOT NULL,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS log_eventos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha_hora VARCHAR(20) NOT NULL,
        nombre_usuario VARCHAR(50) NOT NULL,
        tipo ENUM('Creación de usuario','Inicio de sesión','Cierre de sesión','Crear registro','Modificar registro','Eliminar registro','Consultar registro') NOT NULL,
        detalle TEXT NOT NULL,
        ip_host_cliente VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS recordatorios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        titulo VARCHAR(100) NOT NULL,
        descripcion VARCHAR(500),
        fecha DATE NOT NULL,
        hora TIME NOT NULL,
        estado ENUM('pendiente','completada','cancelada') NOT NULL DEFAULT 'pendiente',
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_recordatorio_usuario_titulo_fecha_hora (usuario_id, titulo, fecha, hora),
        CONSTRAINT fk_recordatorios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        CONSTRAINT chk_titulo_minimo CHECK (CHAR_LENGTH(TRIM(titulo)) >= 3),
        CONSTRAINT chk_descripcion_minima CHECK (descripcion IS NULL OR descripcion = '' OR CHAR_LENGTH(TRIM(descripcion)) >= 5)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function existe_recordatorio_duplicado(PDO $pdo, int $usuarioId, string $titulo, string $fecha, string $hora, int $ignorarId = 0): bool {
    if ($ignorarId > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM recordatorios WHERE usuario_id = ? AND titulo = ? AND fecha = ? AND hora = ? AND id <> ?');
        $stmt->execute([$usuarioId, $titulo, $fecha, $hora, $ignorarId]);
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM recordatorios WHERE usuario_id = ? AND titulo = ? AND fecha = ? AND hora = ?');
        $stmt->execute([$usuarioId, $titulo, $fecha, $hora]);
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
