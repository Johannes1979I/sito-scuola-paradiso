<?php
/* Elenco dei media disponibili (assets/img + uploads). */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login_api();
header('Content-Type: application/json');

$root = dirname(__DIR__);
$dirs = ['uploads', 'assets/img'];
$exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'webm', 'mov', 'mp3', 'wav', 'ogg', 'm4a', 'aac', 'pdf'];
$out = [];
foreach ($dirs as $d) {
    foreach (glob($root . '/' . $d . '/*') as $f) {
        if (!is_file($f)) { continue; }
        $ext = strtolower((string)pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) { continue; }
        $type = in_array($ext, ['mp4', 'webm', 'mov'], true) ? 'video'
            : (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'aac'], true) ? 'audio'
            : ($ext === 'pdf' ? 'pdf' : 'image'));
        $out[] = [
            'url'   => $d . '/' . basename($f),
            'name'  => basename($f),
            'type'  => $type,
            'mtime' => filemtime($f) ?: 0,
        ];
    }
}
usort($out, function ($a, $b) { return $b['mtime'] <=> $a['mtime']; });
echo json_encode(['ok' => true, 'media' => $out]);
