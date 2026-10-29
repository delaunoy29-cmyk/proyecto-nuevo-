<?php
// =====================================================================
//  API de ingreso de datos (la usan los ESP32)
//
//  GET  api.php?key=CLAVE_EQUIPO&ph=7.12&temp=18.4
//  POST api.php  (formulario o JSON {"key":"...","ph":7.12,"temp":18.4})
//
//  Cualquier variable nueva se crea sola (máx. 20 por equipo).
// =====================================================================
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');

function responder($codigo, $datos) { http_response_code($codigo); echo json_encode($datos); exit; }

$datos = $_GET + $_POST;
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) $datos = $json + $datos;
}

$key = (string)($datos['key'] ?? '');
unset($datos['key']);
// Lecturas atrasadas: 'hace' = segundos de antigüedad (tarjetas 4G que guardan datos sin señal)
$hace = (isset($datos['hace']) && is_numeric($datos['hace'])) ? max(0, min(604800, (int)$datos['hace'])) : 0;
unset($datos['hace']);
if ($key === '') responder(401, ['ok' => false, 'error' => 'falta key']);

try {
    $eq = q('SELECT e.*, c.email AS cliente_email, c.nombre AS cliente FROM equipos e JOIN clientes c ON c.id = e.cliente_id
             WHERE e.api_key = ? AND e.activo = 1 AND c.activo = 1', [$key])->fetch();
    if (!$eq) responder(401, ['ok' => false, 'error' => 'key inválida o equipo inactivo']);

    $vars = [];
    foreach (q('SELECT * FROM variables WHERE equipo_id = ?', [$eq['id']]) as $v) $vars[$v['clave']] = $v;

    // Pivote: si llega lat/lon y no ángulo, se calcula desde el centro (0° = norte, sentido horario)
    if ($eq['tipo'] === 'pivote' && $eq['centro_lat'] !== null && isset($datos['lat'], $datos['lon'])
        && !isset($datos['angulo']) && is_numeric($datos['lat']) && is_numeric($datos['lon'])) {
        $dy = ((float)$datos['lat'] - (float)$eq['centro_lat']);
        $dx = ((float)$datos['lon'] - (float)$eq['centro_lon']) * cos(deg2rad((float)$eq['centro_lat']));
        $datos['angulo'] = round(fmod(rad2deg(atan2($dx, $dy)) + 360, 360), 1);
    }

    $fecha = $hace ? date('Y-m-d H:i:s', time() - $hace) : ahora();
    $guardadas = [];
    $alarmas = [];
    db()->beginTransaction();
    foreach ($datos as $clave => $valor) {
        $clave = strtolower(trim((string)$clave));
        if (!preg_match('/^[a-z][a-z0-9_]{0,29}$/', $clave)) continue;
        if (!is_numeric($valor)) continue;
        $valor = (float)$valor;

        if (!isset($vars[$clave])) {
            if (count($vars) >= 20) continue;
            $esRssi = $clave === 'rssi';
            q('INSERT INTO variables (equipo_id, clave, nombre, unidad, decimales, grafico, orden) VALUES (?,?,?,?,?,?,?)',
              [$eq['id'], $clave, $esRssi ? 'Señal WiFi' : ucfirst($clave), $esRssi ? 'dBm' : '', $esRssi ? 0 : 1, $esRssi ? 0 : 1, $esRssi ? 90 : count($vars)]);
            $vars[$clave] = q('SELECT * FROM variables WHERE equipo_id = ? AND clave = ?', [$eq['id'], $clave])->fetch();
        }
        $v = $vars[$clave];
        q('INSERT INTO lecturas (equipo_id, clave, valor, fecha) VALUES (?,?,?,?)', [$eq['id'], $clave, $valor, $fecha]);
        q('UPDATE variables SET ultimo_valor = ? WHERE id = ?', [$valor, $v['id']]);
        $guardadas[] = $clave;

        if (fuera_rango($valor, $v)) {
            $limite = date('Y-m-d H:i:s', time() - ALARMA_REPETIR_MIN * 60);
            $reciente = q('SELECT id FROM alarmas WHERE equipo_id = ? AND clave = ? AND fecha > ? LIMIT 1',
                          [$eq['id'], $clave, $limite])->fetch();
            if (!$reciente) {
                $txt = ($v['minimo'] !== null && $valor < $v['minimo'])
                    ? 'bajo el mínimo (' . fmt($v['minimo'], $v['decimales']) . ')'
                    : 'sobre el máximo (' . fmt($v['maximo'], $v['decimales']) . ')';
                $msg = $v['nombre'] . ' = ' . trim(fmt($valor, $v['decimales']) . ' ' . $v['unidad']) . ' ' . $txt;
                q('INSERT INTO alarmas (equipo_id, clave, valor, mensaje, fecha) VALUES (?,?,?,?,?)',
                  [$eq['id'], $clave, $valor, $msg, $fecha]);
                $alarmas[] = $msg;
            }
        }
    }
    if ($guardadas && $hace < 600) q('UPDATE equipos SET ultimo_dato = ? WHERE id = ?', [$fecha, $eq['id']]);
    db()->commit();

    if ($alarmas) enviar_alarma($eq, $alarmas, $fecha);
    responder(200, ['ok' => true, 'equipo' => $eq['nombre'], 'guardadas' => $guardadas, 'alarmas' => count($alarmas), 'fecha' => $fecha]);
} catch (Throwable $ex) {
    try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $e2) {}
    error_log('api monitoreo: ' . $ex->getMessage());
    responder(500, ['ok' => false, 'error' => 'error interno']);
}

function enviar_alarma($eq, $alarmas, $fecha) {
    if (DB_DSN_PRUEBA !== '') return; // sin correo en pruebas
    $para = array_filter([MAIL_ADMIN, $eq['cliente_email']]);
    if (!$para) return;
    $asunto = '=?UTF-8?B?' . base64_encode('⚠ Alarma: ' . $eq['nombre'] . ' (' . $eq['cliente'] . ')') . '?=';
    $cuerpo = "Equipo: {$eq['nombre']}\nCliente: {$eq['cliente']}\nUbicación: {$eq['ubicacion']}\nFecha: $fecha\n\n"
            . implode("\n", $alarmas)
            . "\n\nRevisa el panel: https://" . ($_SERVER['HTTP_HOST'] ?? 'servimaxx.cl')
            . dirname($_SERVER['SCRIPT_NAME'] ?? '/monitoreo/api.php') . "/equipo.php?id={$eq['id']}\n";
    $cab = "From: " . NOMBRE_SISTEMA . " <" . MAIL_FROM . ">\r\nContent-Type: text/plain; charset=UTF-8";
    @mail(implode(',', $para), $asunto, $cuerpo, $cab);
}