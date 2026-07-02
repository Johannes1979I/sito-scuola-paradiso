<?php
/* Upload di un file media nella cartella uploads/. */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login_api();
header('Content-Type: application/json');

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
if (!csrf_check((string)$csrf)) { http_response_code(403); echo json_encode(['error' => 'csrf']); exit; }

if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? 1) !== UPLOAD_ERR_OK) {
    http_response_code(400); echo json_encode(['error' => 'nessun file']); exit;
}
$f = $_FILES['file'];
if ($f['size'] > 50 * 1024 * 1024) { http_response_code(400); echo json_encode(['error' => 'file troppo grande (max 50MB)']); exit; }

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = (string)$finfo->file($f['tmp_name']);
$allowed = [
    'image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
    'image/webp' => 'webp', 'image/svg+xml' => 'svg',
    'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
    'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
    'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'audio/aac' => 'aac', 'audio/x-m4a' => 'm4a',
    'application/pdf' => 'pdf',
];
if (!isset($allowed[$mime])) {
    http_response_code(400); echo json_encode(['error' => 'tipo non consentito (' . $mime . ')']); exit;
}
$ext  = $allowed[$mime];
$base = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)pathinfo($f['name'], PATHINFO_FILENAME));
$base = trim((string)$base, '-');
if ($base === '') { $base = 'media'; }
$name = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
$dest = dirname(__DIR__) . '/uploads/' . $name;

if (!move_uploaded_file($f['tmp_name'], $dest)) {
    http_response_code(500); echo json_encode(['error' => 'salvataggio fallito']); exit;
}

/* --- Ottimizzazione immagini raster (side-effect isolato: se fallisce, resta l'originale).
   Ridimensiona il lato lungo a max 1600px e ricomprime; preserva la trasparenza PNG.
   GIF (possibile animazione) e SVG NON vengono toccati. --- */
if (in_array($ext, ['jpg', 'png', 'webp'], true) && function_exists('imagecreatetruecolor')) {
    try { optimize_image($dest, $ext); } catch (\Throwable $e) { /* teniamo l'originale */ }
}

$type = in_array($ext, ['mp4', 'webm', 'mov'], true) ? 'video'
    : (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'aac'], true) ? 'audio'
    : ($ext === 'pdf' ? 'pdf' : 'image'));
$out = ['ok' => true, 'url' => 'uploads/' . $name, 'type' => $type, 'mime' => $mime];
if ($type === 'image') {
    $dim = @getimagesize($dest);
    if ($dim) { $out['w'] = $dim[0]; $out['h'] = $dim[1]; }
    $out['bytes'] = @filesize($dest) ?: null;
}
echo json_encode($out);

/* Ridimensiona+comprime un'immagine sul posto. Tiene il risultato solo se è più
   leggero dell'originale (o se è stato necessario ridimensionare). */
function optimize_image(string $path, string $ext): void
{
    $MAX = 1600;   // lato lungo massimo (px)
    $Q   = 82;     // qualità JPEG/WebP
    $info = @getimagesize($path);
    if (!$info) { return; }
    [$w, $h] = $info;
    if ($w < 1 || $h < 1) { return; }

    switch ($ext) {
        case 'jpg':  $src = @imagecreatefromjpeg($path); break;
        case 'png':  $src = @imagecreatefrompng($path); break;
        case 'webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false; break;
        default:     return;
    }
    if (!$src) { return; }

    $scale = min(1.0, $MAX / max($w, $h));
    $needResize = ($scale < 1.0);
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    $dstImg = $src;
    if ($needResize) {
        $tmp = imagecreatetruecolor($nw, $nh);
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
            imagefilledrectangle($tmp, 0, 0, $nw, $nh, $transparent);
        }
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $dstImg = $tmp;
    }

    $tmpFile = $path . '.opt';
    $ok = false;
    switch ($ext) {
        case 'jpg':  $ok = imagejpeg($dstImg, $tmpFile, $Q); break;
        case 'webp': $ok = function_exists('imagewebp') ? imagewebp($dstImg, $tmpFile, $Q) : false; break;
        case 'png':  imagesavealpha($dstImg, true); $ok = imagepng($dstImg, $tmpFile, 8); break;
    }
    if ($dstImg !== $src) { imagedestroy($dstImg); }
    imagedestroy($src);

    if (!$ok || !is_file($tmpFile)) { if (is_file($tmpFile)) { @unlink($tmpFile); } return; }

    $origSize = @filesize($path);
    $newSize  = @filesize($tmpFile);
    if ($needResize || ($newSize && $origSize && $newSize < $origSize)) {
        @rename($tmpFile, $path);
    } else {
        @unlink($tmpFile);
    }
}
