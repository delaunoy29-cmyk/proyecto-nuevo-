<?php
require_once __DIR__ . '/lib.php';
iniciar_sesion();

// ---------- Login ----------
$error = '';
if (!usuario() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $us = trim($_POST['usuario'] ?? '');
    $cl = (string)($_POST['clave'] ?? '');
    $u = null;
    if ($us === ADMIN_USER && ADMIN_PASS !== 'CAMBIAR_CLAVE_ADMIN' && hash_equals(ADMIN_PASS, $cl)) {
        $u = ['rol' => 'admin', 'nombre' => 'Administrador', 'cliente_id' => null];
    } else {
        $c = q('SELECT * FROM clientes WHERE usuario = ? AND activo = 1', [$us])->fetch();
        if ($c && password_verify($cl, $c['clave_hash'])) {
            $u = ['rol' => 'cliente', 'nombre' => $c['nombre'], 'cliente_id' => (int)$c['id']];
        }
    }
    if ($u) { session_regenerate_id(true); $_SESSION['u'] = $u; header('Location: index.php'); exit; }
    usleep(500000);
    $error = 'Usuario o clave incorrectos';
}

if (!usuario()) {
    cabecera('Ingresar');
    echo '<form class="login card" method="post"><h1>Ingresar</h1>';
    if ($error) echo '<p class="err">' . h($error) . '</p>';
    if (ADMIN_PASS === 'CAMBIAR_CLAVE_ADMIN') echo '<p class="err">Configura ADMIN_PASS en config.php</p>';
    echo '<label>Usuario<input name="usuario" autocomplete="username" required autofocus></label>'
       . '<label>Clave<input type="password" name="clave" autocomplete="current-password" required></label>'
       . '<button>Entrar</button></form>';
    pie(); exit;
}

// ---------- Panel de equipos ----------
$u = usuario();
if (es_admin()) {
    $equipos = q('SELECT e.*, c.nombre AS cliente FROM equipos e JOIN clientes c ON c.id = e.cliente_id
                  WHERE e.activo = 1 ORDER BY c.nombre, e.nombre')->fetchAll();
} else {
    $equipos = q('SELECT e.*, c.nombre AS cliente FROM equipos e JOIN clientes c ON c.id = e.cliente_id
                  WHERE e.activo = 1 AND e.cliente_id = ? ORDER BY e.nombre', [$u['cliente_id']])->fetchAll();
}
$vars = [];
if ($equipos) {
    $ids = implode(',', array_map('intval', array_column($equipos, 'id')));
    foreach (q("SELECT * FROM variables WHERE equipo_id IN ($ids) ORDER BY orden, id") as $v) $vars[$v['equipo_id']][] = $v;
    $desde = date('Y-m-d H:i:s', time() - 86400);
    $alarmas_24h = [];
    foreach (q("SELECT equipo_id, COUNT(*) n FROM alarmas WHERE equipo_id IN ($ids) AND fecha > ? AND vista = 0 GROUP BY equipo_id", [$desde]) as $a)
        $alarmas_24h[$a['equipo_id']] = $a['n'];
}
$tipos = plantillas_tipo();

cabecera('Equipos');
echo '<div class="titulo"><h1>Hola, ' . h($u['nombre']) . '</h1>';
$on = count(array_filter($equipos, 'en_linea'));
echo '<p class="sub">' . count($equipos) . ' equipos · ' . $on . ' en línea</p></div>';

if (!$equipos) {
    echo '<div class="card vacio">No hay equipos todavía.' . (es_admin() ? ' <a href="admin.php">Crea un cliente y un equipo</a>.' : '') . '</div>';
}

$cliente_actual = null;
echo '<div class="grilla">';
foreach ($equipos as $e) {
    if (es_admin() && $cliente_actual !== $e['cliente']) {
        $cliente_actual = $e['cliente'];
        echo '</div><h2 class="cliente">' . h($cliente_actual) . '</h2><div class="grilla">';
    }
    $linea = en_linea($e);
    $na = $alarmas_24h[$e['id']] ?? 0;
    echo '<a class="card equipo" href="equipo.php?id=' . (int)$e['id'] . '">';
    echo '<div class="eq-top"><span class="tipo t-' . h($e['tipo']) . '">' . h($tipos[$e['tipo']]['titulo'] ?? $e['tipo']) . '</span>'
       . '<span class="estado ' . ($linea ? 'on' : 'off') . '">' . ($linea ? 'En línea' : 'Sin conexión') . '</span></div>';
    echo '<h3>' . h($e['nombre']) . '</h3>';
    if ($e['ubicacion']) echo '<p class="ubic">' . h($e['ubicacion']) . '</p>';
    echo '<div class="valores">';
    $n = 0;
    foreach ($vars[$e['id']] ?? [] as $v) {
        if (!$v['grafico'] || $v['ultimo_valor'] === null || $n++ >= 4) continue;
        $mal = fuera_rango($v['ultimo_valor'] === null ? null : (float)$v['ultimo_valor'], $v);
        echo '<div class="val' . ($mal ? ' mal' : '') . '"><span>' . h($v['nombre']) . '</span><b>'
           . fmt($v['ultimo_valor'], $v['decimales']) . '<small> ' . h($v['unidad']) . '</small></b></div>';
    }
    echo '</div><div class="eq-pie"><span>' . h(hace($e['ultimo_dato'])) . '</span>';
    if ($na) echo '<span class="alerta">' . $na . ' alarma' . ($na > 1 ? 's' : '') . '</span>';
    echo '</div></a>';
}
echo '</div>';
echo '<script>setTimeout(function(){location.reload()},60000)</script>';
pie();
