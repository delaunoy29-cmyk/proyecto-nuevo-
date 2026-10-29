-- =====================================================================
--  SERVIMAXX Monitoreo - Estructura de base de datos (MySQL / MariaDB)
--  phpMyAdmin > selecciona la base > Importar > este archivo
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS clientes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(120) NOT NULL,
  rut         VARCHAR(20)  DEFAULT NULL,
  usuario     VARCHAR(60)  NOT NULL UNIQUE,
  clave_hash  VARCHAR(255) NOT NULL,
  email       VARCHAR(150) DEFAULT NULL,
  telefono    VARCHAR(30)  DEFAULT NULL,
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  creado      DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS equipos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id   INT          NOT NULL,
  nombre       VARCHAR(120) NOT NULL,
  tipo         VARCHAR(20)  NOT NULL,          -- ph | avicola | pivote | generico
  ubicacion    VARCHAR(150) DEFAULT NULL,
  api_key      VARCHAR(64)  NOT NULL UNIQUE,
  centro_lat   DOUBLE       DEFAULT NULL,      -- pivote: centro del pivote
  centro_lon   DOUBLE       DEFAULT NULL,
  radio_m      DOUBLE       DEFAULT NULL,      -- pivote: largo en metros
  ultimo_dato  DATETIME     DEFAULT NULL,
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  creado       DATETIME     NOT NULL,
  INDEX (cliente_id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Variables que mide cada equipo, con sus límites de alarma
CREATE TABLE IF NOT EXISTS variables (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  equipo_id  INT         NOT NULL,
  clave      VARCHAR(30) NOT NULL,             -- ph, temp, hum, nh3, co2, lat, lon, angulo...
  nombre     VARCHAR(60) NOT NULL,
  unidad     VARCHAR(15) DEFAULT '',
  minimo     DOUBLE      DEFAULT NULL,         -- alarma si valor < minimo
  maximo     DOUBLE      DEFAULT NULL,         -- alarma si valor > maximo
  decimales  TINYINT     NOT NULL DEFAULT 1,
  grafico    TINYINT(1)  NOT NULL DEFAULT 1,   -- 0 = no se grafica (ej. lat/lon)
  orden      INT         NOT NULL DEFAULT 0,
  ultimo_valor DOUBLE    DEFAULT NULL,
  UNIQUE KEY equipo_clave (equipo_id, clave),
  FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lecturas (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  equipo_id  INT         NOT NULL,
  clave      VARCHAR(30) NOT NULL,
  valor      DOUBLE      NOT NULL,
  fecha      DATETIME    NOT NULL,
  INDEX equipo_clave_fecha (equipo_id, clave, fecha),
  FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alarmas (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  equipo_id  INT          NOT NULL,
  clave      VARCHAR(30)  NOT NULL,
  valor      DOUBLE       DEFAULT NULL,
  mensaje    VARCHAR(255) NOT NULL,
  fecha      DATETIME     NOT NULL,
  vista      TINYINT(1)   NOT NULL DEFAULT 0,
  INDEX equipo_fecha (equipo_id, fecha),
  FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
