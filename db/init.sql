CREATE DATABASE IF NOT EXISTS calendario_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE calendario_db;

CREATE TABLE IF NOT EXISTS recordatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    descripcion VARCHAR(500),
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    estado ENUM('pendiente','completada','cancelada') NOT NULL DEFAULT 'pendiente',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_recordatorio_titulo_fecha_hora (titulo, fecha, hora),
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

INSERT INTO recordatorios (titulo, descripcion, fecha, hora, estado) VALUES
('Ejemplo de recordatorio', 'Este registro se crea automaticamente al iniciar la base de datos.', CURDATE(), '09:00:00', 'pendiente'),
('Revisar tareas pendientes', 'Ejemplo de tarea pendiente para probar el filtro.', CURDATE(), '12:30:00', 'pendiente'),
('Recordatorio completado', 'Ejemplo de tarea ya finalizada.', CURDATE(), '16:00:00', 'completada');
