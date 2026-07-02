<?php
/* Backup & cronologia versioni: scarica/importa i contenuti, ripristina uno snapshot.
   Gli snapshot vengono creati automaticamente a ogni salvataggio da save.php in /backups. */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login_redirect();

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ROOT = dirname(__DIR__);
$BDIR = $ROOT . '/backups';
$SEO  = $ROOT . '/seo.json';

function snapshot_now(string $bdir): void {
    if (!is_dir($bdir)) { @mkdir($bdir, 0775, true); }
    if (is_dir($bdir) && is_writable($bdir)) { @copy(content_path(), $bdir . '/content-' . date('Ymd-His') . '.json'); }
}

/* Download backup (prima di qualsiasi output) */
if (isset($_GET['download'])) {
    $bundle = [
        'exportedAt' => date('c'),
        'site'       => 'Scuola Santa Maria del Paradiso',
        'content'    => read_content(),
        'seo'        => is_file($SEO) ? json_decode((string)file_get_contents($SEO), true) : null,
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup-sito-scuola-' . date('Ymd-His') . '.json"');
    echo json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$CSRF = csrf_token();
$flash = ''; $err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('CSRF non valido'); }
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'restore') {
        $file = basename((string)($_POST['file'] ?? ''));
        $path = $BDIR . '/' . $file;
        if (preg_match('/^content-\d{8}-\d{6}\.json$/', $file) && is_file($path)) {
            $chk = json_decode((string)file_get_contents($path), true);
            if (is_array($chk)) {
                snapshot_now($BDIR);                 // salva lo stato attuale (ripristino annullabile)
                if (@copy($path, content_path())) { $flash = 'Ripristinata la versione del ' . h(pretty_date($file)) . '.'; }
                else { $err = 'Ripristino non riuscito (permessi?).'; }
            } else { $err = 'Snapshot non leggibile.'; }
        } else { $err = 'Versione non valida.'; }

    } elseif ($action === 'import') {
        $tmp = $_FILES['backup']['tmp_name'] ?? '';
        if ($tmp && is_uploaded_file($tmp)) {
            $data = json_decode((string)file_get_contents($tmp), true);
            $content = (is_array($data) && isset($data['content']) && is_array($data['content'])) ? $data['content'] : $data;
            if (is_array($content)) {
                snapshot_now($BDIR);
                $okC = write_content($content);
                if ($okC && is_array($data) && isset($data['seo']) && is_array($data['seo'])) {
                    file_put_contents($SEO, json_encode($data['seo'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
                $flash = $okC ? 'Backup importato: contenuti ripristinati.' : 'Import non riuscito (permessi?).';
            } else { $err = 'File di backup non valido.'; }
        } else { $err = 'Nessun file caricato.'; }
    }
}

function pretty_date(string $fname): string {
    if (preg_match('/content-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})\.json/', $fname, $m)) {
        return "{$m[3]}/{$m[2]}/{$m[1]} {$m[4]}:{$m[5]}:{$m[6]}";
    }
    return $fname;
}

$snaps = glob($BDIR . '/content-*.json') ?: [];
rsort($snaps); // più recenti prima (i nomi ordinano cronologicamente)
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Backup &amp; versioni — Pannello</title>
<link rel="icon" href="../assets/img/favicon.jpg">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500..600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
<style>
  .bk-wrap{max-width:820px;margin:0 auto;padding:0 18px 70px}
  .bk-bar{position:sticky;top:0;z-index:5;background:#fff;border-bottom:1px solid #e4ebf5;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px}
  .bk-sec{background:#fff;border:1px solid #e4ebf5;border-radius:14px;padding:18px;margin:0 0 18px}
  .bk-sec h3{margin:0 0 8px;font-family:"Fraunces",serif;color:var(--color-primary,#13449b)}
  .bk-hint{font-size:.85rem;color:#5a6678;margin:0 0 14px}
  .bk-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border:1px solid #eef2f8;border-radius:10px;margin:0 0 8px}
  .bk-row .when{font-weight:600;color:#33415c}
  .bk-row .who{font-size:.74rem;color:#8290a8}
  .flash.ok{background:#e7f7ee;color:#1f7a44;border:1px solid #bfe6cf;padding:11px 14px;border-radius:10px;margin:12px 0}
  .flash.err{background:#fdecec;color:#c0392b;border:1px solid #f3c0c0;padding:11px 14px;border-radius:10px;margin:12px 0}
  .btn-sm{padding:7px 14px;border-radius:8px;border:1px solid var(--color-primary,#13449b);background:#fff;color:var(--color-primary,#13449b);font-weight:600;cursor:pointer;font-size:.85rem}
  .btn-sm:hover{background:var(--color-primary,#13449b);color:#fff}
  .file-in{display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap}
</style>
</head>
<body>
  <div class="bk-bar">
    <span class="brand"><img src="../assets/img/logo.png" alt="">Backup &amp; versioni</span>
    <span class="topactions"><a href="index.php">← Pannello</a></span>
  </div>

  <div class="bk-wrap">
    <h1 style="font-family:Fraunces,serif">Backup &amp; cronologia versioni</h1>
    <p class="bk-hint">Salva una copia di tutti i contenuti del sito, importane una, oppure torna a una versione precedente. Ogni volta che salvi dall'editor viene creata automaticamente una versione qui.</p>

    <?php if ($flash): ?><div class="flash ok"><?= h($flash) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="flash err"><?= h($err) ?></div><?php endif; ?>

    <div class="bk-sec">
      <h3>💾 Scarica un backup</h3>
      <p class="bk-hint">Scarica un file con TUTTI i contenuti (testi, media, SEO). Conservalo: potrai reimportarlo qui in qualsiasi momento.</p>
      <a class="btn" href="backup.php?download=1">⬇️ Scarica backup adesso</a>
    </div>

    <div class="bk-sec">
      <h3>📥 Importa / ripristina da file</h3>
      <p class="bk-hint">Carica un file di backup scaricato in precedenza: i contenuti attuali verranno sostituiti (viene comunque salvata prima una versione dello stato corrente).</p>
      <form method="post" enctype="multipart/form-data" class="file-in" onsubmit="return confirm('Sostituire i contenuti attuali con quelli del file di backup?');">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="action" value="import">
        <input type="file" name="backup" accept="application/json,.json" required>
        <button class="btn" type="submit">Importa backup</button>
      </form>
    </div>

    <div class="bk-sec">
      <h3>🕓 Versioni salvate (<?= count($snaps) ?>)</h3>
      <p class="bk-hint">Ogni salvataggio dall'editor crea una versione. Puoi tornare a una qualsiasi (verrà salvata prima quella attuale, così anche il ripristino è annullabile).</p>
      <?php if (!$snaps): ?>
        <p class="bk-hint">Nessuna versione ancora. Verranno create automaticamente ai prossimi salvataggi.</p>
      <?php else: foreach ($snaps as $sp): $fn = basename($sp); ?>
        <div class="bk-row">
          <span><span class="when"><?= h(pretty_date($fn)) ?></span> &nbsp; <span class="who"><?= h(number_format(filesize($sp))) ?> byte</span></span>
          <form method="post" onsubmit="return confirm('Tornare a questa versione del <?= h(pretty_date($fn)) ?>?');">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="file" value="<?= h($fn) ?>">
            <button class="btn-sm" type="submit">↩︎ Ripristina</button>
          </form>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</body>
</html>
