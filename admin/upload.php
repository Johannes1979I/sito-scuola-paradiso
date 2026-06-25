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
$type = in_array($ext, ['mp4', 'webm', 'mov'], true) ? 'video'
    : (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'aac'], true) ? 'audio'
    : ($ext === 'pdf' ? 'pdf' : 'image'));
echo json_encode(['ok' => true, 'url' => 'uploads/' . $name, 'type' => $type, 'mime' => $mime]);
