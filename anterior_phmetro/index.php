<?php
require __DIR__ . '/config.php';
session_start();

// ---- Login ----
if (isset($_GET['salir'])) { session_destroy(); header('Location: index.php'); exit; }
if (isset($_POST['password'])) {
    if (hash_equals(PANEL_PASSWORD, $_POST['password'])) {
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
    } else { $error = 'Contraseña incorrecta'; }
}
if (empty($_SESSION['ok'])) { ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Monitoreo pH · Servimaxx</title>
<style>body{font-family:system-ui,sans-serif;background:#0f2a3d;display:grid;place-items:center;min-height:100vh;margin:0}
form{background:#fff;padding:28px;border-radius:12px;width:min(320px,90vw)}h1{font-size:18px;margin:0 0 16px;color:#0f2a3d}
input,button{width:100%;padding:11px;font-size:15px;border-radius:8px;box-sizing:border-box}input{border:1px solid #ccd}
button{margin-top:10px;background:#1479a8;color:#fff;border:0;cursor:pointer}.e{color:#c0392b;font-size:14px}</style></head>
<body><form method="post"><h1>Monitoreo de pH · Servimaxx</h1>
<?php if (!empty($error)) echo '<p class="e">'.htmlspecialchars($error).'</p>'; ?>
<input type="password" name="password" placeholder="Contraseña" autofocus required><button>Entrar</button></form></body></html>
<?php exit; }

// ---- Filtros ----
$pdo    = db();
$equipos = $pdo->query('SELECT DISTINCT equipo FROM lecturas ORDER BY equipo')->fetchAll(PDO::FETCH_COLUMN);
$equipo = $_GET['equipo'] ?? ($equipos[0] ?? '');
$desde  = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
$hasta  = $_GET['hasta'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-d', strtotime('-7 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = date('Y-m-d');

$st = $pdo->prepare('SELECT fecha, ph, temperatura, nota FROM lecturas
                     WHERE equipo = ? AND fecha BETWEEN ? AND ? ORDER BY fecha');
$st->execute([$equipo, "$desde 00:00:00", "$hasta 23:59:59"]);
$rows = $st->fetchAll();

// ---- Exportar CSV (abre en Excel) ----
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=ph_{$equipo}_{$desde}_{$hasta}.csv");
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Fecha', 'pH', 'Temperatura °C', 'Fuera de rango', 'Nota'], ';');
    foreach ($rows as $r) {
        $fuera = ($r['ph'] < PH_MIN || $r['ph'] > PH_MAX) ? 'SÍ' : '';
        fputcsv($out, [$r['fecha'], str_replace('.', ',', $r['ph']),
                       $r['temperatura'] === null ? '' : str_replace('.', ',', $r['temperatura']),
                       $fuera, $r['nota']], ';');
    }
    exit;
}

// ---- Indicadores ----
$phs    = array_map('floatval', array_column($rows, 'ph'));
$n      = count($phs);
$ultimo = $n ? end($rows) : null;
$prom   = $n ? array_sum($phs) / $n : null;
$min    = $n ? min($phs) : null;
$max    = $n ? max($phs) : null;
$fuera  = count(array_filter($phs, fn($v) => $v < PH_MIN || $v > PH_MAX));
$f = fn($v) => $v === null ? '—' : number_format($v, 2, ',', '.');
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$ultFuera = $ultimo && ($ultimo['ph'] < PH_MIN || $ultimo['ph'] > PH_MAX);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="300">
<title>Monitoreo pH · Servimaxx</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
:root{--azul:#0f2a3d;--acento:#1479a8;--ok:#1e8e5a;--mal:#c0392b;--borde:#e3e8ee}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,sans-serif;background:#f4f7fa;color:#1c2530}
header{background:var(--azul);color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
header h1{font-size:17px;margin:0}header a{color:#bcd;font-size:14px}
main{max-width:1100px;margin:auto;padding:16px}
form.f{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
form.f select,form.f input,form.f button,.btn{padding:9px 11px;border:1px solid var(--borde);border-radius:8px;font-size:14px;background:#fff}
form.f button,.btn{background:var(--acento);color:#fff;border:0;text-decoration:none;cursor:pointer}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:14px}
.k{background:#fff;border:1px solid var(--borde);border-radius:10px;padding:12px}
.k small{color:#6b7785;font-size:12px}.k b{display:block;font-size:24px;margin-top:4px}
.mal{color:var(--mal)}.ok{color:var(--ok)}
.card{background:#fff;border:1px solid var(--borde);border-radius:10px;padding:12px;margin-bottom:14px}
.chart{position:relative;height:320px}
table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:8px;border-bottom:1px solid var(--borde);text-align:left}
th{color:#6b7785;font-weight:600}tr.x td{background:#fdecea}
.scroll{max-height:420px;overflow:auto}
</style></head><body>
<header><h1>Monitoreo de pH · Servimaxx</h1><a href="?salir=1">Salir</a></header>
<main>
<?php if (!$equipos): ?>
  <div class="card">Aún no llegan lecturas. Cuando el equipo envíe su primer dato aparecerá aquí.</div>
<?php else: ?>
<form class="f" method="get">
  <select name="equipo"><?php foreach ($equipos as $e): ?>
    <option <?= $e === $equipo ? 'selected' : '' ?>><?= $h($e) ?></option><?php endforeach; ?></select>
  <input type="date" name="desde" value="<?= $h($desde) ?>">
  <input type="date" name="hasta" value="<?= $h($hasta) ?>">
  <button>Ver</button>
  <a class="btn" href="?<?= $h(http_build_query(['equipo'=>$equipo,'desde'=>$desde,'hasta'=>$hasta,'csv'=>1])) ?>">Descargar Excel (CSV)</a>
</form>

<div class="kpis">
  <div class="k"><small>Última lectura</small><b class="<?= $ultFuera ? 'mal' : 'ok' ?>"><?= $f($ultimo['ph'] ?? null) ?></b>
    <small><?= $ultimo ? $h(date('d-m H:i', strtotime($ultimo['fecha']))) : '' ?></small></div>
  <div class="k"><small>Promedio</small><b><?= $f($prom) ?></b></div>
  <div class="k"><small>Mín / Máx</small><b><?= $f($min) ?> / <?= $f($max) ?></b></div>
  <div class="k"><small>Fuera de rango (<?= $f(PH_MIN) ?>–<?= $f(PH_MAX) ?>)</small><b class="<?= $fuera ? 'mal' : 'ok' ?>"><?= $fuera ?> de <?= $n ?></b></div>
</div>

<div class="card"><div class="chart"><canvas id="g"></canvas></div></div>

<div class="card scroll"><table>
  <tr><th>Fecha</th><th>pH</th><th>Temp °C</th><th>Nota</th></tr>
  <?php foreach (array_reverse($rows) as $r): $x = $r['ph'] < PH_MIN || $r['ph'] > PH_MAX; ?>
  <tr class="<?= $x ? 'x' : '' ?>"><td><?= $h(date('d-m-Y H:i', strtotime($r['fecha']))) ?></td>
    <td><b><?= $f((float)$r['ph']) ?></b></td><td><?= $r['temperatura'] === null ? '—' : $f((float)$r['temperatura']) ?></td>
    <td><?= $h($r['nota']) ?></td></tr>
  <?php endforeach; ?>
</table></div>

<script>
const datos = <?= json_encode(array_map(fn($r) => ['t' => $r['fecha'], 'ph' => (float)$r['ph'],
    'temp' => $r['temperatura'] === null ? null : (float)$r['temperatura']], $rows)) ?>;
const MIN = <?= PH_MIN ?>, MAX = <?= PH_MAX ?>;
const etiquetas = datos.map(d => d.t.slice(5, 16).replace('-', '/'));
new Chart(document.getElementById('g'), {
  type: 'line',
  data: { labels: etiquetas, datasets: [
    { label: 'pH', data: datos.map(d => d.ph), borderColor: '#1479a8', backgroundColor: '#1479a8',
      pointRadius: datos.length > 200 ? 0 : 2,
      pointBackgroundColor: datos.map(d => (d.ph < MIN || d.ph > MAX) ? '#c0392b' : '#1479a8'), tension: .2, yAxisID: 'y' },
    { label: 'Límite mín', data: datos.map(() => MIN), borderColor: '#c0392b', borderDash: [6,4], pointRadius: 0, yAxisID: 'y' },
    { label: 'Límite máx', data: datos.map(() => MAX), borderColor: '#c0392b', borderDash: [6,4], pointRadius: 0, yAxisID: 'y' },
    { label: 'Temp °C', data: datos.map(d => d.temp), borderColor: '#e6a23c', pointRadius: 0, hidden: true, yAxisID: 'y2' }
  ]},
  options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
    scales: { y: { title: { display: true, text: 'pH' } },
              y2: { position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '°C' } },
              x: { ticks: { maxTicksLimit: 8 } } } }
});
</script>
<?php endif; ?>
</main></body></html>
