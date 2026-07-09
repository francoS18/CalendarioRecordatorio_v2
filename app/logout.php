<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    registrar_bitacora($pdo, 'Cierre de sesión', 'Tabla: usuarios | ID usuario: ' . current_user_id());
}

session_unset();
session_destroy();
session_start();
flash_message('Sesión cerrada correctamente');
redirect_to('login.php');
