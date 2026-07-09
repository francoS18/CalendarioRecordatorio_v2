<?php
require_once __DIR__ . '/config.php';

require_login();

$action = 'bitacora';
$usuarioId = current_user_id();

$stats = [
    'total' => 0,
    'pendiente' => 0,
    'completada' => 0,
    'cancelada' => 0,
];

try {
    $stmtStats = $pdo->prepare('SELECT estado, COUNT(*) AS total FROM recordatorios WHERE usuario_id = ? GROUP BY estado');
    $stmtStats->execute([$usuarioId]);
    foreach ($stmtStats->fetchAll() as $row) {
        $estadoKey = $row['estado'];
        if (array_key_exists($estadoKey, $stats)) {
            $stats[$estadoKey] = (int) $row['total'];
            $stats['total'] += (int) $row['total'];
        }
    }
} catch (PDOException $e) {
    $stats = [
        'total' => 0,
        'pendiente' => 0,
        'completada' => 0,
        'cancelada' => 0,
    ];
}

registrar_bitacora(
    $pdo,
    'Consultar registro',
    'Tabla: log_eventos | Usuario ID: ' . $usuarioId . ' | Consulta de bitácora'
);

try {
    $stmt = $pdo->query('
        SELECT id, fecha_hora, nombre_usuario, tipo, detalle, ip_host_cliente
        FROM log_eventos
        ORDER BY id DESC
        LIMIT 100
    ');
    $bitacora = $stmt->fetchAll();
} catch (PDOException $e) {
    $bitacora = [];
    $error = 'No se pudo consultar la bitácora: ' . e($e->getMessage());
}

$flash = get_flash_message();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bitácora de eventos</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .bitacora-tabla-contenedor {
            overflow-x: auto;
        }

        .bitacora-tabla {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .bitacora-tabla th,
        .bitacora-tabla td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }

        .bitacora-tabla th {
            color: var(--primary-dark);
            background: var(--primary-soft);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .bitacora-tabla td {
            color: var(--text);
            line-height: 1.45;
        }

        .bitacora-detalle {
            max-width: 360px;
            overflow-wrap: anywhere;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/menu.php'; ?>

    <div class="workspace">
        <header class="hero">
            <div class="hero-content">
                <p class="eyebrow">Bitácora</p>
                <h1>Bitácora de eventos</h1>
                <p class="hero-text">Usuario: <?= e(current_user_name()) ?></p>
            </div>
        </header>

        <main>
            <section class="card">
                <div class="section-title">
                    <span class="badge">Eventos</span>
                    <h2>Últimos 100 eventos registrados</h2>
                </div>

                <?php if ($flash): ?>
                    <p class="<?= e($flash['type'] ?? 'success') ?>"><?= e($flash['message'] ?? '') ?></p>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <p class="error"><?= e($error) ?></p>
                <?php elseif (!$bitacora): ?>
                    <p class="empty">Todavía no hay eventos registrados en la bitácora.</p>
                <?php else: ?>
                    <div class="bitacora-tabla-contenedor">
                        <table class="bitacora-tabla">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha hora</th>
                                    <th>Usuario</th>
                                    <th>Tipo</th>
                                    <th>Detalle</th>
                                    <th>IP_HOST_CLIENTE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bitacora as $evento): ?>
                                    <tr>
                                        <td><?= (int) $evento['id'] ?></td>
                                        <td><?= e($evento['fecha_hora']) ?></td>
                                        <td><?= e($evento['nombre_usuario']) ?></td>
                                        <td><?= e($evento['tipo']) ?></td>
                                        <td class="bitacora-detalle"><?= e($evento['detalle']) ?></td>
                                        <td><?= e($evento['ip_host_cliente']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
</body>
</html>
