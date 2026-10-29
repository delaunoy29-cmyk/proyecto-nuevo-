-- Importar en cPanel > phpMyAdmin > (tu base) > Importar
CREATE TABLE IF NOT EXISTS lecturas (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo      VARCHAR(50)   NOT NULL,
  ph          DECIMAL(5,2)  NOT NULL,
  temperatura DECIMAL(5,2)  NULL,
  nota        VARCHAR(255)  NULL,
  fecha       DATETIME      NOT NULL,
  INDEX idx_equipo_fecha (equipo, fecha),
  INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
