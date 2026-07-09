CREATE DATABASE IF NOT EXISTS calendario_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE calendario_db;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    correo VARCHAR(120) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS log_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora VARCHAR(20) NOT NULL,
    nombre_usuario VARCHAR(50) NOT NULL,
    tipo ENUM('Creación de usuario','Inicio de sesión','Cierre de sesión','Crear registro','Modificar registro','Eliminar registro','Consultar registro') NOT NULL,
    detalle TEXT NOT NULL,
    ip_host_cliente VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS recordatorios (
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
);

DROP TRIGGER IF EXISTS validar_fecha_recordatorio_insert;
DROP TRIGGER IF EXISTS validar_fecha_recordatorio_update;

DELIMITER $$

CREATE TRIGGER validar_fecha_recordatorio_insert
BEFORE INSERT ON recordatorios
FOR EACH ROW
BEGIN
    IF NEW.fecha < CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La fecha del recordatorio no puede ser anterior a la fecha actual';
    END IF;
END$$

CREATE TRIGGER validar_fecha_recordatorio_update
BEFORE UPDATE ON recordatorios
FOR EACH ROW
BEGIN
    IF NEW.fecha < CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La fecha del recordatorio no puede ser anterior a la fecha actual';
    END IF;
END$$

DELIMITER ;
