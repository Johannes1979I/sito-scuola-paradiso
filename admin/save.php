<?php
/* Salva gli override (testi/immagini/blocchi) in content.json, per pagina. */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login_api();
header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$body = json_decode((string)$raw, true);
if (!is_array($body)) { http_response_code(400); echo json_encode(['error' => 'bad json']); exit; }

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['csrf'] ?? '');
if (!csrf_check((string)$csrf)) { http_response_code(403); echo json_encode(['error' => 'csrf']); exit; }

$page = basename((string)($body['page'] ?? ''));
if ($page === '' || !preg_match('/\.html$/', $page) || !is_file(dirname(__DIR__) . '/' . $page)) {
    http_response_code(400); echo json_encode(['error' => 'bad page']); exit;
}

$content = read_content();
$cur = $content[$page] ?? [];
$stringBuckets = ['texts', 'images', 'blocks', 'alts'];
$objectBuckets = ['styles', 'fx'];   // valori = oggetti {prop: valore}
foreach (array_merge($stringBuckets, $objectBuckets) as $bucket) {
    if (!isset($cur[$bucket]) || !is_array($cur[$bucket])) { $cur[$bucket] = []; }
    if (!isset($body[$bucket]) || !is_array($body[$bucket])) { continue; }
    foreach ($body[$bucket] as $key => $val) {
        $key = (string)$key;
        if ($val === null) { unset($cur[$bucket][$key]); continue; }
        if (in_array($bucket, $objectBuckets, true) && is_array($val)) {
            if (!isset($cur[$bucket][$key]) || !is_array($cur[$bucket][$key])) { $cur[$bucket][$key] = []; }
            foreach ($val as $k2 => $v2) {
                if ($v2 === null || $v2 === '') { unset($cur[$bucket][$key][(string)$k2]); }
                else { $cur[$bucket][$key][(string)$k2] = (string)$v2; }
            }
            if (empty($cur[$bucket][$key])) { unset($cur[$bucket][$key]); }
        } else {
            $cur[$bucket][$key] = (string)$val;
        }
    }
}
// Bucket "order" = ordinamento figli per drag&drop: { containerKey: [indiceOriginale, ...] }
if (isset($body['order']) && is_array($body['order'])) {
    if (!isset($cur['order']) || !is_array($cur['order'])) { $cur['order'] = []; }
    foreach ($body['order'] as $key => $val) {
        $key = (string)$key;
        if ($val === null) { unset($cur['order'][$key]); continue; }
        if (is_array($val)) { $cur['order'][$key] = array_values(array_map('intval', $val)); }
    }
}
// Quando si salva un blocco, gli override per-figlio sono già inglobati nel blocco: rimuovili
if (isset($body['blocks']) && is_array($body['blocks'])) {
    foreach (array_keys($body['blocks']) as $bk) {
        foreach (['texts', 'images'] as $bucket) {
            foreach (array_keys($cur[$bucket]) as $k) {
                if (strpos((string)$k, (string)$bk . '>') === 0) { unset($cur[$bucket][$k]); }
            }
        }
    }
}
$content[$page] = $cur;

if (!write_content($content)) {
    http_response_code(500); echo json_encode(['error' => 'write failed (permessi?)']); exit;
}
// Snapshot per la cronologia versioni (ripristinabile dal pannello Backup). Non deve mai rompere il salvataggio.
try {
    $bdir = dirname(__DIR__) . '/backups';
    if (!is_dir($bdir)) { @mkdir($bdir, 0775, true); }
    if (is_dir($bdir) && is_writable($bdir)) {
        @copy(content_path(), $bdir . '/content-' . date('Ymd-His') . '.json');
        $snaps = glob($bdir . '/content-*.json') ?: [];
        if (count($snaps) > 40) { sort($snaps); foreach (array_slice($snaps, 0, count($snaps) - 40) as $old) { @unlink($old); } }
    }
} catch (\Throwable $e) { /* log-and-ignore */ }
echo json_encode(['ok' => true, 'page' => $page]);
