<?php
// =====================================================================
//  SERVIMAXX Monitoreo - Configuración
//  Edita SOLO este archivo después de subir la plataforma a Host.cl
// =====================================================================

// --- Base de datos MySQL (cPanel > Bases de datos MySQL) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'servimax_monitoreo');   // nombre completo de la base
define('DB_USER', 'servimax_monuser');     // usuario con TODOS los privilegios
define('DB_PASS', 'CAMBIAR_CLAVE');

// --- Administrador principal (tú) ---
// Entra con este usuario para crear clientes y equipos.
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'CAMBIAR_CLAVE');

// --- Alarmas por correo ---
// Correo remitente (créalo en cPanel > Cuentas de correo si no existe)
define('MAIL_FROM', 'alertas@servimaxx.cl');
// Correo que recibe TODAS las alarmas (además del correo de cada cliente)
define('MAIL_ADMIN', 'administracion@servimaxx.cl');
// Minutos mínimos entre dos correos de la misma alarma (evita spam)
define('ALARMA_REPETIR_MIN', 30);

// --- General ---
define('ZONA_HORARIA', 'America/Santiago');
define('NOMBRE_SISTEMA', 'SERVIMAXX Monitoreo');
// Minutos sin datos para considerar un equipo "sin conexión"
define('MIN_SIN_CONEXION', 15);

// Solo para pruebas locales (déjalo vacío en Host.cl)
define('DB_DSN_PRUEBA', getenv('MON_DSN') ?: '');
