<?php
/* Impostazioni avanzate: Google Analytics (con consenso cookie), testo banner cookie,
   codice <head> personalizzato (es. verifica Search Console). GA e testo → CFG di layout.js;
   codice head → scritto nell'<head> di ogni pagina (blocco delimitato). */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login_redirect();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ROOT = dirname(__DIR__);
$JS   = $ROOT . '/assets/js/layout.js';
$ADV  = $ROOT . '/advanced.json';

function read_cfg_field(string $js, string $k): string {
    return preg_match('/\b' . preg_quote($k, '/') . ':\s*"((?:[^"\\\\]|\\\\.)*)"/', $js, $m) ? stripcslashes($m[1]) : '';
}
function write_cfg_field(string $js, string $k, string $v): string {
    $safe = str_replace(['\\', '"', "\r", "\n"], ['\\\\', '', ' ', ' '], $v);
    return preg_replace_callback('/(\b' . preg_quote($k, '/') . ':\s*")(?:[^"\\\\]|\\\\.)*(")/', function ($m) use ($safe) { return $m[1] . $safe . $m[2]; }, $js, 1);
}
function apply_headcode(string $root, string $page, string $code): void {
    $path = $root . '/' . $page; if (!is_file($path)) { return; }
    $html = (string)file_get_contents($path);
    $block = $code !== '' ? "<!-- HEAD extra: gestito dal pannello Avanzate -->\n" . $code . "\n<!-- /HEAD extra -->" : '';
    if (strpos($html, '<!-- HEAD extra') !== false) {
        $html = preg_replace('/\s*<!-- HEAD extra.*?<!-- \/HEAD extra -->/is', ($block ? "\n" . $block : ''), $html, 1);
    } elseif ($block) {
        $html = preg_replace('/(\s*<\/head>)/i', "\n" . $block . '$1', $html, 1);
    }
    file_put_contents($path, $html);
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
$adv   = is_file($ADV) ? json_decode((string)file_get_contents($ADV), true) : [];
if (!is_array($adv)) { $adv = []; }
$flash = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('CSRF non valido'); }
    $ga = trim((string)($_POST['ga'] ?? ''));
    // se incollano l'URL/snippet, estrai solo l'ID
    if (preg_match('/\b((?:G|UA|GT)-[A-Z0-9\-]+)\b/i', $ga, $m)) { $ga = strtoupper($m[1]); }
    $cookieText = trim((string)($_POST['cookieText'] ?? ''));
    $headCode   = trim((string)($_POST['headCode'] ?? ''));
    $formEndpoint = trim((string)($_POST['formEndpoint'] ?? ''));
    $formKey      = trim((string)($_POST['formKey'] ?? ''));
    $formEmail    = trim((string)($_POST['formEmail'] ?? ''));

    $js = (string)file_get_contents($JS);
    $js = write_cfg_field($js, 'ga', $ga);
    $js = write_cfg_field($js, 'cookieText', $cookieText);
    $js = write_cfg_field($js, 'formEndpoint', $formEndpoint);
    $js = write_cfg_field($js, 'formKey', $formKey);
    $js = write_cfg_field($js, 'formEmail', $formEmail);
    $okJs = file_put_contents($JS, $js) !== false;

    foreach ($pages as $p) { apply_headcode($ROOT, $p, $headCode); }
    $adv['headCode'] = $headCode;
    file_put_contents($ADV, json_encode($adv, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    bump_versions($ROOT, $pages);
    $flash = $okJs ? 'Salvato e applicato a tutto il sito. Ricordati di fare git push per pubblicare.' : 'Errore di scrittura (permessi?).';
}

$js = (string)file_get_contents($JS);
$ga = read_cfg_field($js, 'ga');
$cookieText = read_cfg_field($js, 'cookieText');
$formEndpoint = read_cfg_field($js, 'formEndpoint');
$formKey = read_cfg_field($js, 'formKey');
$formEmail = read_cfg_field($js, 'formEmail');
$headCode = (string)($adv['headCode'] ?? '');
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Impostazioni avanzate — Pannello</title>
<link rel="icon" href="../assets/img/favicon.jpg">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500..600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
<style>
  .av-wrap{max-width:820px;margin:0 auto;padding:0 18px 70px}
  .av-bar{position:sticky;top:0;z-index:5;background:#fff;border-bottom:1px solid #e4ebf5;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px}
  .av-sec{background:#fff;border:1px solid #e4ebf5;border-radius:14px;padding:18px;margin:0 0 18px}
  .av-sec h3{margin:0 0 6px;font-family:"Fraunces",serif;color:var(--color-primary,#13449b)}
  .av-hint{font-size:.85rem;color:#5a6678;margin:0 0 14px;line-height:1.5}
  .av-field{margin:0 0 12px}
  .av-field label{display:block;font-size:.82rem;font-weight:600;color:#33415c;margin:0 0 5px}
  .av-field input,.av-field textarea{width:100%;padding:10px 12px;border:1px solid #d7e0ee;border-radius:9px;font:inherit;font-size:.92rem;box-sizing:border-box}
  .av-field textarea{resize:vertical;min-height:110px;font-family:monospace;font-size:.85rem}
  .flash.ok{background:#e7f7ee;color:#1f7a44;border:1px solid #bfe6cf;padding:11px 14px;border-radius:10px;margin:12px 0}
  .flash.err{background:#fdecec;color:#c0392b;border:1px solid #f3c0c0;padding:11px 14px;border-radius:10px;margin:12px 0}
</style>
</head>
<body>
  <div class="av-bar">
    <span class="brand"><img src="../assets/img/logo.png" alt="">Impostazioni avanzate</span>
    <span class="topactions"><a href="index.php">← Pannello</a> <a href="../index.html" target="_blank">Vedi il sito ↗</a></span>
  </div>

  <form class="av-wrap" method="post">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <h1 style="font-family:Fraunces,serif">Impostazioni avanzate</h1>
    <p class="av-hint">Statistiche di visita, consenso cookie e codice tecnico. Al salvataggio le impostazioni vengono applicate a tutto il sito; poi fai <b>git push</b> per pubblicare.</p>

    <?php if ($flash): ?><div class="flash <?= strpos($flash, 'Errore') === 0 ? 'err' : 'ok' ?>"><?= h($flash) ?></div><?php endif; ?>

    <div class="av-sec">
      <h3>📊 Google Analytics</h3>
      <p class="av-hint">Incolla il tuo <b>ID di misurazione</b> (es. <code>G-XXXXXXXXXX</code>). Le statistiche partono <b>solo dopo il consenso</b> dell'utente (banner cookie automatico, IP anonimizzato). Lascia vuoto per disattivare (in tal caso non appare alcun banner: restano solo i cookie tecnici).</p>
      <div class="av-field"><label>ID di misurazione</label><input type="text" name="ga" value="<?= h($ga) ?>" placeholder="G-XXXXXXXXXX"></div>
    </div>

    <div class="av-sec">
      <h3>🍪 Testo del banner cookie</h3>
      <p class="av-hint">Mostrato solo se Analytics è attivo. Lascia vuoto per il testo predefinito.</p>
      <div class="av-field"><textarea name="cookieText" style="min-height:70px;font-family:inherit;font-size:.92rem"><?= h($cookieText) ?></textarea></div>
    </div>

    <div class="av-sec">
      <h3>📩 Moduli — invio email</h3>
      <p class="av-hint">Rende i moduli del sito (contatti, pre-iscrizione, Open Day) <b>realmente invianti</b>. Consigliato un servizio gratuito: <b>Web3Forms</b> (endpoint <code>https://api.web3forms.com/submit</code> + Access Key, si ottiene con la sola email su web3forms.com) oppure <b>Formspree</b> (endpoint <code>https://formspree.io/f/xxxxxx</code>). Se lasci vuoto l'endpoint, i moduli aprono il programma di posta col messaggio già compilato verso l'indirizzo qui sotto.</p>
      <div class="av-field"><label>Endpoint del servizio</label><input type="text" name="formEndpoint" value="<?= h($formEndpoint) ?>" placeholder="https://api.web3forms.com/submit"></div>
      <div class="av-field"><label>Access Key (solo Web3Forms)</label><input type="text" name="formKey" value="<?= h($formKey) ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></div>
      <div class="av-field"><label>Email per il fallback (se endpoint vuoto)</label><input type="text" name="formEmail" value="<?= h($formEmail) ?>" placeholder="segreteria@scuolasantamariadelparadiso.it"></div>
    </div>

    <div class="av-sec">
      <h3>🧩 Codice personalizzato nel &lt;head&gt;</h3>
      <p class="av-hint">Per esperti: HTML/meta/script da inserire nell'<code>&lt;head&gt;</code> di tutte le pagine (es. il meta di verifica di Google Search Console). Lascia vuoto per rimuoverlo.</p>
      <div class="av-field"><textarea name="headCode" placeholder='&lt;meta name="google-site-verification" content="…"&gt;'><?= h($headCode) ?></textarea></div>
    </div>

    <button class="btn" type="submit" style="position:sticky;bottom:16px">💾 Salva e applica</button>
  </form>
</body>
</html>
