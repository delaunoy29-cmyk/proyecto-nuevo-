<?php
// Recibe lecturas del equipo.
// POST https://servimaxx.cl/phmetro/api.php
// Cabecera: X-API-Key: <API_KEY>
// Cuerpo JSON: {"equipo":"PH-01","ph":7.12,"temperatura":18.4}
// (también acepta formulario normal o parámetros GET para equipos simples)
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function responder(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { $input = array_merge($_GET, $_POST); }

$key = $_SERVER['HTTP_X_API_KEY'] ?? ($input['key'] ?? '');
if (!hash_equals(API_KEY, (string)$key)) {
    responder(401, ['ok' => false, 'error' => 'Clave inválida']);
}

$equipo = substr(trim((string)($input['equipo'] ?? 'PH-01')), 0, 50);
$ph     = isset($input['ph']) ? (float)$input['ph'] : null;
$temp   = (isset($input['temperatura']) && $input['temperatura'] !== '') ? (float)$input['temperatura'] : null;
$nota   = isset($input['nota']) ? substr((string)$input['nota'], 0, 255) : null;

if ($ph === null || $ph < 0 || $ph > 14) {
    responder(422, ['ok' => false, 'error' => 'pH fuera de rango 0–14 o ausente']);
}
if ($equipo === '' || !preg_match('/^[\w\-. ]+$/u', $equipo)) {
    responder(422, ['ok' => false, 'error' => 'Nombre de equipo inválido']);
}

try {
    $st = db()->prepare('INSERT INTO lecturas (equipo, ph, temperatura, nota, fecha) VALUES (?,?,?,?,NOW())');
    $st->execute([$equipo, round($ph, 2), $temp, $nota]);
    responder(200, ['ok' => true, 'id' => (int)db()->lastInsertId(),
                    'alarma' => ($ph < PH_MIN || $ph > PH_MAX)]);
} catch (Throwable $e) {
    responder(500, ['ok' => false, 'error' => 'Error de base de datos']);
}
