<?php
// ==========================================================
//  CONFIGURACIÓN — edita estos valores antes de usar
// ==========================================================

// Datos de la base de datos (los creas en cPanel > Bases de datos MySQL)
define('DB_HOST', 'localhost');
define('DB_NAME', 'servimax_phmetro');   // cPanel antepone tu usuario: servimax_...
define('DB_USER', 'servimax_phuser');
define('DB_PASS', 'CAMBIA_ESTA_CLAVE');

// Clave que usará el equipo para enviar datos (inventa una larga, sin espacios)
define('API_KEY', 'CAMBIA_ESTA_CLAVE_LARGA_DEL_EQUIPO');

// Contraseña para entrar al panel web
define('PANEL_PASSWORD', 'CAMBIA_ESTA_CLAVE_DEL_PANEL');

// Rango aceptable de pH (fuera de esto se marca en rojo).
// Ajusta según la norma que te aplique a la descarga.
define('PH_MIN', 6.0);
define('PH_MAX', 8.5);

// Zona horaria
date_default_timezone_set('America/Santiago');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}
