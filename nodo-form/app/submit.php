<?php
require '/var/www/vendor/autoload.php';
use Dompdf\Dompdf;
use PHPMailer\PHPMailer\PHPMailer;
header('Content-Type: application/json');
$DATA = getenv('DATA_DIR') ?: '/var/www/data';
function out($ok, $e = '') { echo json_encode(['ok' => $ok, 'error' => $e]); exit; }
function p($k) { return trim((string)($_POST[$k] ?? '')); }
function arr($k) { return array_map('trim', (array)($_POST[$k] ?? [])); }
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function img($f, $dir, $base) {
  $i = @getimagesize($f); $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$i[2] ?? 0] ?? null;
  if (!$ext || filesize($f) > 20 * 1048576) return null;
  return move_uploaded_file($f, "$dir/$base.$ext") ? "$base.$ext" : null;
}
function thumb($f, $w = 150) {
  $i = @getimagesize($f); if (!$i) return '';
  $im = match ($i[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($f), IMAGETYPE_PNG => @imagecreatefrompng($f), IMAGETYPE_WEBP => @imagecreatefromwebp($f), default => null };
  if (!$im) return '';
  if ($i[2] == IMAGETYPE_JPEG && function_exists('exif_read_data')) {
    $o = (int)(@exif_read_data($f)['Orientation'] ?? 1);
    $im = match ($o) { 3 => imagerotate($im, 180, 0), 6 => imagerotate($im, -90, 0), 8 => imagerotate($im, 90, 0), default => $im };
  }
  $im = imagescale($im, $w); ob_start(); imagejpeg($im, null, 70);
  return 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || p('web') !== '') out(false, 'Solicitud inválida');
foreach (['marca', 'nombre', 'rif', 'direccion', 'ciudad', 'estado', 'tlf', 'whatsapp', 's_nombre', 'c_nombre', 'acepto'] as $k)
  if (p($k) === '') out(false, "Falta el campo $k");
if (!in_array(p('marca'), ['changan', 'foton'])) out(false, 'Marca inválida');
$nu = count(arr('u_nombre')); $na = count(arr('a_nombre'));
if ($nu < 1 || $na < 1) out(false, 'Agrega al menos un usuario y un asesor');

$slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(@iconv('UTF-8', 'ASCII//TRANSLIT', p('nombre')))), '-');
$id = date('Ymd-His') . '-' . p('marca') . '-' . substr($slug, 0, 40);
$dir = "$DATA/submissions/$id";
if (!mkdir($dir, 0750, true)) out(false, 'No se pudo guardar');

$d = ['id' => $id, 'fecha' => date('c')];
foreach (['marca','nombre','rif','direccion','ciudad','estado','tlf','whatsapp','sucursal','sucursal_detalle','h_ventas','h_servicio','h_repuestos','feriados','r_instagram','r_facebook','r_tiktok','r_youtube','r_web','r_otras','dom','dominio','mail','s_nombre','s_cargo','s_tlf','s_correo','c_nombre','c_tlf','c_correo','obs'] as $k) $d[$k] = p($k);
$d['servicios'] = arr('servicios');
$d['logo'] = (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === 0) ? img($_FILES['logo']['tmp_name'], $dir, 'logo') : null;
$d['usuarios'] = []; $d['asesores'] = [];
for ($i = 0; $i < $nu; $i++) $d['usuarios'][] = ['area' => arr('u_area')[$i] ?? '', 'rol' => arr('u_rol')[$i] ?? '', 'nombre' => arr('u_nombre')[$i], 'tlf' => arr('u_tlf')[$i] ?? '', 'correo' => arr('u_correo')[$i] ?? ''];
for ($i = 0; $i < $na; $i++) {
  $t = $_FILES['a_foto']['tmp_name'][$i] ?? ''; $ok = $t && ($_FILES['a_foto']['error'][$i] ?? 4) === 0;
  $n = sprintf('asesor-%02d-%s', $i + 1, substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(@iconv('UTF-8', 'ASCII//TRANSLIT', arr('a_nombre')[$i]))), '-'), 0, 30));
  $d['asesores'][] = ['nombre' => arr('a_nombre')[$i], 'cargo' => arr('a_cargo')[$i] ?? '', 'whatsapp' => arr('a_whatsapp')[$i] ?? '', 'correo' => arr('a_correo')[$i] ?? '', 'foto' => $ok ? img($t, $dir, $n) : null];
}

// PDF compacto
$col = $d['marca'] === 'foton' ? '#3d4a5c' : '#1b3d94';
$r = fn($a, $b) => $b === '' ? '' : '<tr><td class=k>' . e($a) . '</td><td>' . e($b) . '</td></tr>';
$h = "<style>body{font-family:DejaVu Sans;font-size:9px;color:#222}h1{background:$col;color:#fff;padding:8px;font-size:14px;margin:0}h2{color:$col;font-size:11px;border-bottom:1px solid $col;margin:12px 0 4px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:3px;text-align:left;vertical-align:middle}.k{width:28%;background:#f1f3f6;font-weight:bold}img{width:50px}</style>";
$h .= '<h1>Nodo CRM · ' . e(strtoupper($d['marca'])) . ' · ' . e($d['nombre']) . '</h1><p>Recibido: ' . e(date('d/m/Y H:i')) . '</p>';
$h .= '<h2>Concesionario</h2><table>' . $r('RIF', $d['rif']) . $r('Dirección', $d['direccion']) . $r('Ciudad / Estado', $d['ciudad'] . ' / ' . $d['estado']) . $r('Teléfono', $d['tlf']) . $r('WhatsApp', $d['whatsapp']) . $r('Sucursal', $d['sucursal'] === 'si' ? $d['sucursal_detalle'] : 'No') . $r('Áreas', implode(', ', $d['servicios'])) . '</table>';
$h .= '<h2>Horarios</h2><table>' . $r('Ventas', $d['h_ventas']) . $r('Servicio', $d['h_servicio']) . $r('Repuestos', $d['h_repuestos']) . $r('Feriados', $d['feriados']) . '</table>';
$h .= '<h2>Presencia digital</h2><table>' . $r('Instagram', $d['r_instagram']) . $r('Facebook', $d['r_facebook']) . $r('TikTok', $d['r_tiktok']) . $r('YouTube', $d['r_youtube']) . $r('Web', $d['r_web']) . $r('Otras', $d['r_otras']) . $r('Dominio propio', $d['dom'] === 'si' ? $d['dominio'] : 'No') . $r('Correos corporativos', $d['mail'] === 'si' ? 'Sí' : 'No (la marca entrega cuenta)') . '</table>';
$h .= '<h2>Contactos</h2><table>' . $r('Seguimiento', "{$d['s_nombre']} · {$d['s_cargo']} · {$d['s_tlf']} · {$d['s_correo']}") . $r('Responsable CRM', "{$d['c_nombre']} · {$d['c_tlf']} · {$d['c_correo']}") . '</table>';
$h .= '<h2>Usuarios CRM (' . $nu . ')</h2><table><tr><th>Área</th><th>Rol</th><th>Nombre</th><th>Teléfono</th><th>Correo</th></tr>';
foreach ($d['usuarios'] as $u) $h .= '<tr><td>' . e($u['area']) . '</td><td>' . e($u['rol']) . '</td><td>' . e($u['nombre']) . '</td><td>' . e($u['tlf']) . '</td><td>' . e($u['correo']) . '</td></tr>';
$h .= '</table><h2>Asesores (' . $na . ')</h2><table><tr><th>Foto</th><th>Nombre</th><th>Cargo</th><th>WhatsApp</th><th>Correo</th></tr>';
foreach ($d['asesores'] as $a) $h .= '<tr><td>' . ($a['foto'] ? '<img src="' . thumb("$dir/{$a['foto']}") . '">' : '') . '</td><td>' . e($a['nombre']) . '</td><td>' . e($a['cargo']) . '</td><td>' . e($a['whatsapp']) . '</td><td>' . e($a['correo']) . '</td></tr>';
$h .= '</table>' . ($d['obs'] ? '<h2>Observaciones</h2><p>' . e($d['obs']) . '</p>' : '');
$pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']); $pdf->loadHtml($h, 'UTF-8'); $pdf->setPaper('letter'); $pdf->render();
file_put_contents("$dir/resumen.pdf", $pdf->output());

// Correo
$d['correo_enviado'] = false;
try {
  $m = new PHPMailer(true); $m->isSMTP(); $m->Host = getenv('SMTP_HOST'); $m->Port = (int)(getenv('SMTP_PORT') ?: 587);
  $m->SMTPAuth = true; $m->Username = getenv('SMTP_USER'); $m->Password = getenv('SMTP_PASS'); $m->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls'; $m->Timeout = 20; $m->CharSet = 'UTF-8';
  $m->setFrom(getenv('SMTP_FROM') ?: getenv('SMTP_USER'), 'Formulario Nodo CRM');
  foreach (explode(',', getenv('MAIL_TO')) as $t) $m->addAddress(trim($t));
  $m->Subject = 'Nodo CRM: ' . strtoupper($d['marca']) . ' - ' . $d['nombre'];
  $m->Body = "Nuevo formulario recibido.\nConcesionario: {$d['nombre']} ({$d['ciudad']})\nUsuarios: $nu / Asesores: $na\nFotos y datos completos en el panel interno (id: $id).";
  $m->addAttachment("$dir/resumen.pdf", "$id.pdf"); $m->send(); $d['correo_enviado'] = true;
} catch (Throwable $x) { file_put_contents("$DATA/mail-errors.log", date('c') . " $id " . $x->getMessage() . "\n", FILE_APPEND); }
file_put_contents("$dir/data.json", json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
out(true);
