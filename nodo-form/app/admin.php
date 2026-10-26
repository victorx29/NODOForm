<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']); session_start();
header('X-Robots-Tag: noindex');
$KEY = getenv('ADMIN_KEY') ?: ''; $DATA = (getenv('DATA_DIR') ?: '/var/www/data') . '/submissions';
$CSS = "<style>body{margin:0;background:#f2f5f8;color:#1c2530;font:15px/1.5 'Segoe UI',system-ui,sans-serif}.w{max-width:1100px;margin:0 auto;padding:18px 14px}h2,h3{color:#10294a;margin:0 0 12px}a{color:#1b3d94;font-weight:600;text-decoration:none}a:hover{text-decoration:underline}.top{display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:14px}.top .b{background:#6ab43e;color:#fff;padding:9px 14px;border-radius:6px}.box{background:#fff;border-radius:10px;box-shadow:0 1px 3px #0002;overflow-x:auto}table{border-collapse:collapse;width:100%}th{background:#10294a;color:#fff;text-align:left}th,td{padding:10px 12px;border-bottom:1px solid #e3e8ee;white-space:nowrap}tr:hover td{background:#f7f9fb}input{width:100%;padding:11px;border:1px solid #c5ced8;border-radius:6px;font:inherit;box-sizing:border-box}button{background:#10294a;color:#fff;border:0;border-radius:6px;padding:11px 20px;font:inherit;cursor:pointer;margin-top:10px}.l{max-width:340px;margin:18vh auto;background:#fff;padding:26px;border-radius:10px;box-shadow:0 1px 3px #0002}.ok{color:#2e7d32;font-weight:600}.ko{color:#b00020;font-weight:600}</style>";
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (isset($_POST['k'])) { if ($KEY !== '' && hash_equals($KEY, $_POST['k'])) { session_regenerate_id(true); $_SESSION['ok'] = 1; } else { sleep(2); $err = 1; } }
if (isset($_GET['out'])) { session_destroy(); header('Location: admin.php'); exit; }
if (empty($_SESSION['ok'])) {
  echo '<meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1">'.$CSS.'<body><div class=l><h3>Panel interno Nodo</h3><form method=post><input type=password name=k placeholder="Clave maestra" autofocus><button>Entrar</button></form>' . (isset($err) ? '<p class=ko>Clave incorrecta</p>' : '') . '</div>';
  exit;
}
$rows = [];
foreach (glob("$DATA/*/data.json") ?: [] as $f) $rows[] = json_decode(file_get_contents($f), true);
usort($rows, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
$id = $_GET['id'] ?? '';
if (isset($_GET['dl']) && $_GET['dl'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=nodo-personas.csv'); echo "\xEF\xBB\xBF";
  $o = fopen('php://output', 'w'); fputcsv($o, ['Marca', 'Concesionario', 'Ciudad', 'Área', 'Rol', 'Es asesor', 'Nombre', 'Teléfono', 'Correo', 'Foto'], ';');
  foreach ($rows as $r) foreach ($r['usuarios'] as $u) fputcsv($o, [$r['marca'], $r['nombre'], $r['ciudad'], $u['area'], $u['rol'], ($u['asesor'] ?? '') === 'si' ? 'Sí' : 'No', $u['nombre'], $u['tlf'], $u['correo'], $u['foto'] ?? ''], ';');
  exit;
}
if (isset($_GET['dl']) && preg_match('/^[0-9a-z_-]+$/', $id) && is_dir("$DATA/$id")) {
  if ($_GET['dl'] === 'pdf') { header('Content-Type: application/pdf'); header("Content-Disposition: attachment; filename=$id.pdf"); readfile("$DATA/$id/resumen.pdf"); exit; }
  if ($_GET['dl'] === 'zip') {
    $t = tempnam(sys_get_temp_dir(), 'z'); $z = new ZipArchive; $z->open($t, ZipArchive::OVERWRITE);
    foreach (glob("$DATA/$id/*") as $f) $z->addFile($f, basename($f)); $z->close();
    header('Content-Type: application/zip'); header("Content-Disposition: attachment; filename=$id.zip"); readfile($t); unlink($t); exit;
  }
}
echo '<meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1">'.$CSS.'<body><div class=w><h2>Envíos recibidos (' . count($rows) . ')</h2><div class=top><a class=b href="?dl=csv">Exportar usuarios (CSV)</a><a href="?out=1">Salir</a></div><div class=box><table><tr><th>Fecha<th>Marca<th>Concesionario<th>Ciudad<th>Usuarios<th>Asesores<th>Correo<th>Descargas</tr>';
foreach ($rows as $r) echo '<tr><td>' . e(substr($r['fecha'], 0, 16)) . '<td>' . e($r['marca']) . '<td>' . e($r['nombre']) . '<td>' . e($r['ciudad']) . '<td>' . count($r['usuarios']) . '<td>' . (count(array_filter($r['usuarios'], fn($u) => ($u['asesor'] ?? '') === 'si')) + count($r['asesores'] ?? [])) . '<td>' . ($r['correo_enviado'] ? '<span class=ok>Enviado</span>' : '<span class=ko>Falló</span>') . '<td><a href="?dl=pdf&id=' . e($r['id']) . '">PDF</a> · <a href="?dl=zip&id=' . e($r['id']) . '">ZIP (fotos y datos)</a></tr>';
echo '</table></div></div>';
