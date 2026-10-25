<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']); session_start();
header('X-Robots-Tag: noindex');
$KEY = getenv('ADMIN_KEY') ?: ''; $DATA = (getenv('DATA_DIR') ?: '/var/www/data') . '/submissions';
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (isset($_POST['k'])) { if ($KEY !== '' && hash_equals($KEY, $_POST['k'])) { session_regenerate_id(true); $_SESSION['ok'] = 1; } else { sleep(2); $err = 1; } }
if (isset($_GET['out'])) { session_destroy(); header('Location: admin.php'); exit; }
if (empty($_SESSION['ok'])) {
  echo '<meta name=viewport content="width=device-width,initial-scale=1"><body style="font-family:sans-serif;max-width:340px;margin:20vh auto"><h3>Panel interno Nodo</h3><form method=post><input type=password name=k placeholder="Clave maestra" style="width:100%;padding:10px" autofocus><button style="margin-top:10px;padding:10px 18px">Entrar</button></form>' . (isset($err) ? '<p style="color:#b00">Clave incorrecta</p>' : '');
  exit;
}
$rows = [];
foreach (glob("$DATA/*/data.json") ?: [] as $f) $rows[] = json_decode(file_get_contents($f), true);
usort($rows, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
$id = $_GET['id'] ?? '';
if (isset($_GET['dl']) && $_GET['dl'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=nodo-personas.csv'); echo "\xEF\xBB\xBF";
  $o = fopen('php://output', 'w'); fputcsv($o, ['Marca', 'Concesionario', 'Ciudad', 'Tipo', 'Área/Cargo', 'Rol', 'Nombre', 'Teléfono/WhatsApp', 'Correo', 'Foto'], ';');
  foreach ($rows as $r) {
    foreach ($r['usuarios'] as $u) fputcsv($o, [$r['marca'], $r['nombre'], $r['ciudad'], 'Usuario', $u['area'], $u['rol'], $u['nombre'], $u['tlf'], $u['correo'], ''], ';');
    foreach ($r['asesores'] as $a) fputcsv($o, [$r['marca'], $r['nombre'], $r['ciudad'], 'Asesor', $a['cargo'], '', $a['nombre'], $a['whatsapp'], $a['correo'], $a['foto']], ';');
  }
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
echo '<meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><body style="font-family:sans-serif;margin:16px"><h2>Envíos recibidos (' . count($rows) . ')</h2><p><a href="?dl=csv">Exportar usuarios y asesores (CSV para Excel)</a> · <a href="?out=1">Salir</a></p><div style="overflow-x:auto"><table border=1 cellpadding=6 style="border-collapse:collapse"><tr><th>Fecha<th>Marca<th>Concesionario<th>Ciudad<th>Usuarios<th>Asesores<th>Correo<th>Descargas</tr>';
foreach ($rows as $r) echo '<tr><td>' . e(substr($r['fecha'], 0, 16)) . '<td>' . e($r['marca']) . '<td>' . e($r['nombre']) . '<td>' . e($r['ciudad']) . '<td>' . count($r['usuarios']) . '<td>' . count($r['asesores']) . '<td>' . ($r['correo_enviado'] ? 'Enviado' : 'Falló') . '<td><a href="?dl=pdf&id=' . e($r['id']) . '">PDF</a> · <a href="?dl=zip&id=' . e($r['id']) . '">ZIP (fotos y datos)</a></tr>';
echo '</table></div>';
