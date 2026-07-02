<?php
/* Pannello Branding & impostazioni globali: colori del tema (scritti in style.css :root),
   dati scuola/contatti/social/WhatsApp/logo (scritti nel CFG di layout.js).
   Al salvataggio incrementa il ?v= in tutte le pagine (cache-busting). */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login_redirect();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ROOT = dirname(__DIR__);
$CSS  = $ROOT . '/assets/css/style.css';
$JS   = $ROOT . '/assets/js/layout.js';

$COLORS = [
    'blu' => 'Blu principale', 'blu-scuro' => 'Blu scuro', 'blu-chiaro' => 'Blu chiaro',
    'giallo' => 'Giallo', 'arancio' => 'Arancio', 'rosa' => 'Rosa', 'verde' => 'Verde', 'ciano' => 'Ciano',
];
$FIELDS = [
    'nome' => ['Nome scuola', 'text'], 'sottotitolo' => ['Sottotitolo', 'text'], 'logo' => ['Logo — percorso immagine', 'text'],
    'indirizzo' => ['Indirizzo', 'text'], 'orari' => ['Orari segreteria', 'text'], 'claim' => ['Claim / motto', 'text'],
    'tel' => ['Telefono (mostrato)', 'text'], 'telRaw' => ['Telefono per link (+39…)', 'text'],
    'cell' => ['Cellulare (mostrato)', 'text'], 'cellRaw' => ['Cellulare per link (+39…)', 'text'],
    'email' => ['Email', 'text'], 'pec' => ['PEC', 'text'],
    'whatsapp' => ['WhatsApp (senza +, es. 39340…)', 'text'], 'whatsappMsg' => ['Messaggio WhatsApp precompilato', 'text'],
    'facebook' => ['Facebook (URL)', 'url'], 'instagram' => ['Instagram (URL)', 'url'], 'youtube' => ['YouTube (URL, opzionale)', 'url'],
];

function read_colors(string $css, array $names): array {
    $out = [];
    foreach ($names as $n => $_l) {
        $out[$n] = preg_match('/--' . preg_quote($n, '/') . ':\s*(#[0-9a-fA-F]{3,8})/', $css, $m) ? $m[1] : '#000000';
    }
    return $out;
}
function read_cfg(string $js, array $fields): array {
    $out = [];
    foreach ($fields as $k => $_l) {
        $out[$k] = preg_match('/\b' . preg_quote($k, '/') . ':\s*"((?:[^"\\\\]|\\\\.)*)"/', $js, $m) ? stripcslashes($m[1]) : '';
    }
    return $out;
}
function write_colors(string $css, array $vals): string {
    foreach ($vals as $n => $v) {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) { continue; }
        $css = preg_replace_callback('/(--' . preg_quote($n, '/') . ':\s*)#[0-9a-fA-F]{3,8}/', function ($m) use ($v) { return $m[1] . $v; }, $css, 1);
    }
    return $css;
}
function write_cfg(string $js, array $vals): string {
    foreach ($vals as $k => $v) {
        $safe = str_replace(['\\', '"', "\r", "\n"], ['\\\\', '', ' ', ' '], $v);
        $js = preg_replace_callback('/(\b' . preg_quote($k, '/') . ':\s*")(?:[^"\\\\]|\\\\.)*(")/', function ($m) use ($safe) { return $m[1] . $safe . $m[2]; }, $js, 1);
    }
    return $js;
}
function bump_versions(string $root, array $pages): void {
    foreach ($pages as $p) {
        $f = $root . '/' . $p; if (!is_file($f)) { continue; }
        $hh = (string)file_get_contents($f);
        $hh = preg_replace_callback('/layout\.js\?v=(\d+)/', function ($m) { return 'layout.js?v=' . ((int)$m[1] + 1); }, $hh);
        $hh = preg_replace_callback('/style\.css\?v=(\d+)/', function ($m) { return 'style.css?v=' . ((int)$m[1] + 1); }, $hh);
        file_put_contents($f, $hh);
    }
}

$CSRF  = csrf_token();
$pages = site_pages();
$flash = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('CSRF non valido'); }
    $css = (string)file_get_contents($CSS);
    $js  = (string)file_get_contents($JS);

    $newColors = [];
    foreach ($COLORS as $n => $_l) { $newColors[$n] = trim((string)($_POST['color'][$n] ?? '')); }
    $newCfg = [];
    foreach ($FIELDS as $k => $_l) { $newCfg[$k] = trim((string)($_POST['cfg'][$k] ?? '')); }

    $css = write_colors($css, $newColors);
    $js  = write_cfg($js, $newCfg);
    $okCss = file_put_contents($CSS, $css) !== false;
    $okJs  = file_put_contents($JS, $js) !== false;
    bump_versions($ROOT, $pages);
    $flash = ($okCss && $okJs)
        ? 'Salvato! Colori e dati applicati a tutto il sito, versioni cache aggiornate. Ricordati di fare git push per pubblicare.'
        : 'Errore di scrittura (permessi?).';
}

$css = (string)file_get_contents($CSS);
$js  = (string)file_get_contents($JS);
$colors = read_colors($css, $COLORS);
$cfg    = read_cfg($js, $FIELDS);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Branding &amp; impostazioni — Pannello</title>
<link rel="icon" href="../assets/img/favicon.jpg">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500..600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
<style>
  .bp-wrap{max-width:920px;margin:0 auto;padding:0 18px 70px}
  .bp-bar{position:sticky;top:0;z-index:5;background:#fff;border-bottom:1px solid #e4ebf5;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px}
  .bp-sec{background:#fff;border:1px solid #e4ebf5;border-radius:14px;padding:16px 18px;margin:0 0 18px}
  .bp-sec h3{margin:0 0 14px;font-family:"Fraunces",serif;color:var(--color-primary,#13449b)}
  .bp-colors{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px}
  .bp-color{display:flex;align-items:center;gap:10px;border:1px solid #eef2f8;border-radius:10px;padding:8px 10px}
  .bp-color input[type=color]{width:44px;height:38px;border:none;background:none;cursor:pointer;padding:0}
  .bp-color .lab{font-size:.82rem;font-weight:600;color:#33415c;line-height:1.15}
  .bp-color .hex{font-size:.72rem;color:#8290a8;font-family:monospace}
  .bp-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px}
  .bp-field{margin:0}
  .bp-field.full{grid-column:1/-1}
  .bp-field label{display:block;font-size:.82rem;font-weight:600;color:#33415c;margin:0 0 5px}
  .bp-field input{width:100%;padding:9px 12px;border:1px solid #d7e0ee;border-radius:9px;font:inherit;font-size:.92rem;box-sizing:border-box}
  .flash.ok{background:#e7f7ee;color:#1f7a44;border:1px solid #bfe6cf;padding:11px 14px;border-radius:10px;margin:12px 0}
  .flash.err{background:#fdecec;color:#c0392b;border:1px solid #f3c0c0;padding:11px 14px;border-radius:10px;margin:12px 0}
  .bp-hint{font-size:.82rem;color:#5a6678}
  @media(max-width:620px){.bp-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
  <div class="bp-bar">
    <span class="brand"><img src="../assets/img/logo.png" alt="">Branding &amp; impostazioni</span>
    <span class="topactions"><a href="index.php">← Pannello</a> <a href="../index.html" target="_blank">Vedi il sito ↗</a></span>
  </div>

  <form class="bp-wrap" method="post">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <h1 style="font-family:Fraunces,serif">Branding &amp; impostazioni globali</h1>
    <p class="bp-hint">Colori del tema, dati della scuola, contatti, social e WhatsApp — validi per tutto il sito. Al salvataggio vengono scritti in <code>style.css</code> e <code>layout.js</code> e le versioni cache si aggiornano. Poi fai <b>git push</b> per pubblicare.</p>

    <?php if ($flash): ?><div class="flash <?= strpos($flash, 'Errore') === 0 ? 'err' : 'ok' ?>"><?= h($flash) ?></div><?php endif; ?>

    <div class="bp-sec">
      <h3>🎨 Colori del tema</h3>
      <div class="bp-colors">
        <?php foreach ($COLORS as $n => $lab): $v = $colors[$n]; ?>
          <label class="bp-color">
            <input type="color" name="color[<?= h($n) ?>]" value="<?= h($v) ?>" oninput="this.closest('.bp-color').querySelector('.hex').textContent=this.value">
            <span><span class="lab"><?= h($lab) ?></span><br><span class="hex"><?= h($v) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="bp-hint" style="margin-top:12px">Il <b>Blu principale</b> è il colore-guida (pulsanti, titoli, link). Gli accenti vanno usati con parsimonia.</p>
    </div>

    <div class="bp-sec">
      <h3>🏫 Dati, contatti e social</h3>
      <div class="bp-grid">
        <?php foreach ($FIELDS as $k => $meta):
            $full = in_array($k, ['nome', 'indirizzo', 'claim', 'whatsappMsg', 'facebook', 'instagram', 'youtube', 'logo'], true); ?>
          <div class="bp-field<?= $full ? ' full' : '' ?>">
            <label><?= h($meta[0]) ?></label>
            <input type="<?= h($meta[1]) ?>" name="cfg[<?= h($k) ?>]" value="<?= h($cfg[$k]) ?>">
          </div>
        <?php endforeach; ?>
      </div>
      <p class="bp-hint" style="margin-top:12px">Per cambiare l'<b>immagine del logo</b>: carica il file (es. da un editor pagina → libreria media) e metti qui il suo percorso, oppure sostituisci <code>assets/img/logo.png</code>.</p>
    </div>

    <button class="btn" type="submit" style="position:sticky;bottom:16px">💾 Salva e applica a tutto il sito</button>
  </form>
</body>
</html>
