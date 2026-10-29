<?php
// SERVIMAXX Monitoreo - funciones comunes
require_once __DIR__ . '/config.php';
date_default_timezone_set(ZONA_HORARIA);

// ---------- Plantillas de variables por tipo de equipo ----------
// clave => [nombre, unidad, min, max, decimales, grafico]
function plantillas_tipo() {
    return [
        'ph' => [
            'titulo' => 'pH metro',
            'vars' => [
                'ph'   => ['pH', '', 6.0, 8.5, 2, 1],
                'ma'   => ['Corriente', 'mA', 3.8, 20.5, 2, 0],
            ],
        ],
        'avicola' => [
            'titulo' => 'Galpón avícola',
            'vars' => [
                'temp' => ['Temperatura', '°C', 18, 32, 1, 1],
                'hum'  => ['Humedad', '%', 50, 70, 0, 1],
                'nh3'  => ['Amoníaco NH3', 'ppm', null, 20, 0, 1],
                'co2'  => ['CO2', 'ppm', null, 3000, 0, 1],
            ],
        ],
        'pivote' => [
            'titulo' => 'Pivote de riego',
            'vars' => [
                'angulo' => ['Posición', '°', null, null, 0, 1],
                'lat'    => ['Latitud', '', null, null, 6, 0],
                'lon'    => ['Longitud', '', null, null, 6, 0],
                'vel'    => ['Velocidad', 'm/h', null, null, 1, 1],
                'presion'=> ['Presión', 'bar', 1.5, null, 1, 1],
            ],
        ],
        'generico' => [
            'titulo' => 'Equipo genérico',
            'vars' => [],
        ],
    ];
}

// ---------- Base de datos ----------
function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (DB_DSN_PRUEBA !== '') {
        $pdo = new PDO(DB_DSN_PRUEBA);
    } else {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
function q($sql, $p = []) { $s = db()->prepare($sql); $s->execute($p); return $s; }
function ahora() { return date('Y-m-d H:i:s'); }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- Sesión y permisos ----------
function iniciar_sesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS'])]);
        session_start();
    }
}
function usuario() { iniciar_sesion(); return $_SESSION['u'] ?? null; }
function es_admin() { $u = usuario(); return $u && $u['rol'] === 'admin'; }
function exigir_login() { if (!usuario()) { header('Location: index.php'); exit; } }
function exigir_admin() { exigir_login(); if (!es_admin()) { http_response_code(403); exit('Sin permiso'); } }

function csrf() {
    iniciar_sesion();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_campo() { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function verificar_csrf() {
    if (!hash_equals(csrf(), $_POST['csrf'] ?? '')) { http_response_code(400); exit('Solicitud inválida'); }
}

// Equipo visible para el usuario actual (o null)
function equipo_permitido($id) {
    $e = q('SELECT e.*, c.nombre AS cliente FROM equipos e JOIN clientes c ON c.id = e.cliente_id WHERE e.id = ?', [$id])->fetch();
    if (!$e) return null;
    if (es_admin() || $e['cliente_id'] == usuario()['cliente_id']) return $e;
    return null;
}

function crear_variables_tipo($equipo_id, $tipo) {
    $pl = plantillas_tipo()[$tipo]['vars'] ?? [];
    $i = 0;
    foreach ($pl as $clave => $v) {
        q('INSERT INTO variables (equipo_id, clave, nombre, unidad, minimo, maximo, decimales, grafico, orden) VALUES (?,?,?,?,?,?,?,?,?)',
          [$equipo_id, $clave, $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $i++]);
    }
}

function en_linea($e) {
    return $e['ultimo_dato'] && (time() - strtotime($e['ultimo_dato'])) < MIN_SIN_CONEXION * 60;
}
function fmt($valor, $dec) { return $valor === null ? '—' : number_format((float)$valor, (int)$dec, ',', '.'); }
function fuera_rango($valor, $v) {
    if ($valor === null) return false;
    return ($v['minimo'] !== null && $valor < $v['minimo']) || ($v['maximo'] !== null && $valor > $v['maximo']);
}
function hace($fecha) {
    if (!$fecha) return 'nunca';
    $s = time() - strtotime($fecha);
    if ($s < 60) return 'hace ' . $s . ' s';
    if ($s < 3600) return 'hace ' . floor($s / 60) . ' min';
    if ($s < 86400) return 'hace ' . floor($s / 3600) . ' h';
    return date('d-m-Y H:i', strtotime($fecha));
}

// ---------- Plantilla HTML ----------
function cabecera($titulo, $extra_head = '') {
    $u = usuario();
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . h($titulo) . ' · ' . h(NOMBRE_SISTEMA) . '</title>'
       . '<link rel="stylesheet" href="estilo.css">' . $extra_head . '</head><body>';
    echo '<header class="top"><a class="marca" href="index.php"><span class="logo">S</span>' . h(NOMBRE_SISTEMA) . '</a>';
    if ($u) {
        echo '<nav><a href="index.php">Equipos</a>';
        if (es_admin()) echo '<a href="admin.php">Administrar</a>';
        echo '<a href="salir.php">Salir</a></nav>';
    }
    echo '</header><main>';
}
function pie() {
    echo '</main><footer>SERVIMAXX SpA · servimaxx.cl</footer></body></html>';
}
