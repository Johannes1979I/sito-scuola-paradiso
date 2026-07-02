<?php
/* Pannello SEO & social: titolo/description/OpenGraph per pagina, sitemap.xml, robots.txt.
   I meta vengono scritti DIRETTAMENTE nell'<head> delle pagine (i crawler e gli scraper
   social non eseguono JS, e GitHub Pages è statico). */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login_redirect();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ROOT     = dirname(__DIR__);
$SEO_FILE = $ROOT . '/seo.json';

function seo_read(string $f): array { $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null; return is_array($j) ? $j : []; }
function seo_write(string $f, array $d): bool { return file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false; }

/* Legge titolo/description attuali dall'HTML (per pre-compilare) */
function page_head_current(string $root, string $page): array {
    $path = $root . '/' . $page; $t = ''; $d = '';
    if (is_file($path)) {
        $html = (string)file_get_contents($path);
        if (preg_match('#<title>(.*?)</title>#is', $html, $m)) { $t = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')); }
        if (preg_match('#<meta\s+name="description"\s+content="(.*?)"\s*/?>#is', $html, $m)) { $d = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'); }
    }
    return ['title' => $t, 'desc' => $d];
}

function abs_url(string $base, string $path): string {
    if (preg_match('#^https?://#i', $path)) { return $path; }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/* Applica i meta SEO nell'<head> della pagina (idempotente, blocco delimitato) */
function seo_apply(string $root, string $page, array $s, array $g): bool {
    $path = $root . '/' . $page;
    if (!is_file($path)) { return false; }
    $html = (string)file_get_contents($path);
    $cur  = page_head_current($root, $page);
    $title = $s['title'] !== '' ? $s['title'] : ($cur['title'] !== '' ? $cur['title'] : $g['siteName']);
    $desc  = $s['desc']  !== '' ? $s['desc']  : $cur['desc'];

    // <title>
    $html = preg_replace('#<title>.*?</title>#is', '<title>' . h($title) . '</title>', $html, 1);
    // <meta name="description">
    $metaDesc = '<meta name="description" content="' . h($desc) . '">';
    if (preg_match('#<meta\s+name="description"[^>]*>#i', $html)) {
        $html = preg_replace('#<meta\s+name="description"[^>]*>#i', $metaDesc, $html, 1);
    } else {
        $html = preg_replace('#(</title>)#i', '$1' . "\n" . $metaDesc, $html, 1);
    }

    // blocco Open Graph / Twitter / canonical
    $url    = abs_url($g['base'], $page === 'index.html' ? '' : $page);
    $ogImg  = abs_url($g['base'], ($s['ogImage'] !== '' ? $s['ogImage'] : $g['ogDefault']));
    $lines = [
        '<!-- SEO auto: gestito dal pannello SEO, non modificare a mano -->',
        '<link rel="canonical" href="' . h($url) . '">',
        '<meta property="og:type" content="website">',
        '<meta property="og:site_name" content="' . h($g['siteName']) . '">',
        '<meta property="og:title" content="' . h($title) . '">',
        '<meta property="og:description" content="' . h($desc) . '">',
        '<meta property="og:url" content="' . h($url) . '">',
        '<meta property="og:image" content="' . h($ogImg) . '">',
        '<meta name="twitter:card" content="summary_large_image">',
        '<meta name="twitter:title" content="' . h($title) . '">',
        '<meta name="twitter:description" content="' . h($desc) . '">',
        '<meta name="twitter:image" content="' . h($ogImg) . '">',
    ];
    if ($s['keywords'] !== '') { $lines[] = '<meta name="keywords" content="' . h($s['keywords']) . '">'; }
    $lines[] = '<!-- /SEO auto -->';
    $block = implode("\n", $lines);

    if (strpos($html, '<!-- SEO auto') !== false) {
        $html = preg_replace('#<!-- SEO auto.*?<!-- /SEO auto -->#is', $block, $html, 1);
    } else {
        $html = preg_replace('#(\s*</head>)#i', "\n" . $block . '$1', $html, 1);
    }
    return file_put_contents($path, $html) !== false;
}

function seo_sitemap(string $root, array $pages, array $g): void {
    $base = rtrim($g['base'], '/');
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($pages as $p) {
        $loc = $base . '/' . ($p === 'index.html' ? '' : $p);
        $xml .= '  <url><loc>' . h($loc) . '</loc></url>' . "\n";
    }
    $xml .= '</urlset>' . "\n";
    file_put_contents($root . '/sitemap.xml', $xml);
}

function seo_robots(string $root, array $g): void {
    $base = rtrim($g['base'], '/');
    $txt  = "User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n";
    file_put_contents($root . '/robots.txt', $txt);
}

$CSRF  = csrf_token();
$pages = site_pages();
$seo   = seo_read($SEO_FILE);
if (!isset($seo['global']) || !is_array($seo['global'])) {
    $seo['global'] = ['base' => 'https://johannes1979i.github.io/sito-scuola-paradiso', 'siteName' => 'Scuola Santa Maria del Paradiso', 'ogDefault' => 'assets/img/logo.png'];
}
if (!isset($seo['pages']) || !is_array($seo['pages'])) { $seo['pages'] = []; }

$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('CSRF non valido'); }
    $seo['global']['base']      = rtrim(trim((string)($_POST['base'] ?? '')), '/');
    $seo['global']['siteName']  = trim((string)($_POST['siteName'] ?? ''));
    $seo['global']['ogDefault'] = trim((string)($_POST['ogDefault'] ?? ''));
    foreach ($pages as $p) {
        $seo['pages'][$p] = [
            'title'    => trim((string)($_POST['title'][$p] ?? '')),
            'desc'     => trim((string)($_POST['desc'][$p] ?? '')),
            'ogImage'  => trim((string)($_POST['ogImage'][$p] ?? '')),
            'keywords' => trim((string)($_POST['keywords'][$p] ?? '')),
        ];
    }
    seo_write($SEO_FILE, $seo);
    $ok = 0;
    foreach ($pages as $p) { if (seo_apply($ROOT, $p, $seo['pages'][$p], $seo['global'])) { $ok++; } }
    seo_sitemap($ROOT, $pages, $seo['global']);
    seo_robots($ROOT, $seo['global']);
    $flash = "Salvato e applicato all'HTML di {$ok} pagine · generati sitemap.xml e robots.txt.";
}

$labels = [
    'index.html' => 'Home', 'chi-siamo.html' => 'Chi siamo', 'offerta-formativa.html' => 'Offerta formativa',
    'vita-scolastica.html' => 'Vita scolastica', 'teatro.html' => 'Teatro', 'sostienici.html' => 'Sostienici',
    'news.html' => 'News', 'contatti.html' => 'Contatti', 'modulistica.html' => 'Modulistica',
    'albo.html' => 'Albo', 'amministrazione-trasparente.html' => 'Amministrazione Trasparente', 'privacy.html' => 'Privacy Policy',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SEO & social — Pannello</title>
<link rel="icon" href="../assets/img/favicon.jpg">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500..600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
<style>
  .seo-wrap{max-width:920px;margin:0 auto;padding:0 18px 60px}
  .seo-global{background:rgba(15,118,110,.05);border:1px solid #e4ebf5;border-left:3px solid var(--color-primary,#13449b);border-radius:14px;padding:18px;margin:18px 0 26px}
  .seo-global h3{margin:0 0 12px}
  .seo-card{background:#fff;border:1px solid #e4ebf5;border-radius:14px;padding:16px 18px;margin:0 0 16px}
  .seo-card h4{margin:0 0 12px;font-family:"Fraunces",serif;color:var(--color-primary,#13449b);display:flex;align-items:center;gap:8px}
  .seo-card h4 .pg{font-family:Inter,sans-serif;font-size:.72rem;font-weight:500;color:#8290a8;background:#f1f5fb;padding:2px 8px;border-radius:100px}
  .seo-field{margin:0 0 12px}
  .seo-field label{display:block;font-size:.82rem;font-weight:600;color:#33415c;margin:0 0 5px}
  .seo-field input,.seo-field textarea{width:100%;padding:9px 12px;border:1px solid #d7e0ee;border-radius:9px;font:inherit;font-size:.92rem;box-sizing:border-box}
  .seo-field textarea{resize:vertical;min-height:58px}
  .cc{float:right;font-size:.72rem;color:#8290a8;font-weight:500}
  .cc.warn{color:#d98324}.cc.bad{color:#d94545}
  .seo-bar{position:sticky;top:0;z-index:5;background:#fff;border-bottom:1px solid #e4ebf5;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px}
  .seo-hint{font-size:.82rem;color:#5a6678;margin:2px 0 0}
  .flash.ok{background:#e7f7ee;color:#1f7a44;border:1px solid #bfe6cf;padding:11px 14px;border-radius:10px;margin:12px 0}
</style>
</head>
<body>
  <div class="seo-bar">
    <span class="brand"><img src="../assets/img/logo.png" alt="">SEO &amp; social</span>
    <span class="topactions"><a href="index.php">← Pannello</a> <a href="../index.html" target="_blank">Vedi il sito ↗</a></span>
  </div>

  <form class="seo-wrap" method="post">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <h1 style="font-family:Fraunces,serif">SEO &amp; anteprima social</h1>
    <p class="seo-hint">Titolo e descrizione di ogni pagina, più l'immagine che appare quando condividi il link (WhatsApp, Facebook…). Al salvataggio i meta vengono scritti nell'HTML e si aggiornano <code>sitemap.xml</code> e <code>robots.txt</code>. Ricordati poi di fare <b>git push</b> per pubblicare.</p>

    <?php if ($flash): ?><div class="flash ok"><?= h($flash) ?></div><?php endif; ?>

    <div class="seo-global">
      <h3>Impostazioni globali</h3>
      <div class="seo-field"><label>Indirizzo del sito (base URL)</label><input type="url" name="base" value="<?= h($seo['global']['base']) ?>" placeholder="https://…"></div>
      <div class="seo-field"><label>Nome del sito</label><input type="text" name="siteName" value="<?= h($seo['global']['siteName']) ?>"></div>
      <div class="seo-field"><label>Immagine social predefinita (og:image)</label><input type="text" name="ogDefault" value="<?= h($seo['global']['ogDefault']) ?>" placeholder="assets/img/logo.png"></div>
      <p class="seo-hint">L'immagine social ideale è ~1200×630px. Consiglio: crea <code>assets/img/og.jpg</code> e mettilo qui.</p>
    </div>

    <?php foreach ($pages as $p):
        $data = $seo['pages'][$p] ?? [];
        $cur  = page_head_current($ROOT, $p);
        $title = $data['title'] ?? ''; if ($title === '') { $title = $cur['title']; }
        $desc  = $data['desc'] ?? '';  if ($desc === '')  { $desc  = $cur['desc']; }
        $lab = $labels[$p] ?? $p; ?>
      <div class="seo-card">
        <h4><?= h($lab) ?> <span class="pg"><?= h($p) ?></span></h4>
        <div class="seo-field"><label>Titolo (Google) <span class="cc" data-max="60"></span></label>
          <input type="text" name="title[<?= h($p) ?>]" value="<?= h($title) ?>" maxlength="80" data-count></div>
        <div class="seo-field"><label>Descrizione (Google/social) <span class="cc" data-max="160"></span></label>
          <textarea name="desc[<?= h($p) ?>]" maxlength="220" data-count><?= h($desc) ?></textarea></div>
        <div class="seo-field"><label>Immagine social di questa pagina (opzionale, altrimenti la predefinita)</label>
          <input type="text" name="ogImage[<?= h($p) ?>]" value="<?= h($data['ogImage'] ?? '') ?>" placeholder="assets/img/…"></div>
        <div class="seo-field"><label>Parole chiave (opzionale, separate da virgola)</label>
          <input type="text" name="keywords[<?= h($p) ?>]" value="<?= h($data['keywords'] ?? '') ?>"></div>
      </div>
    <?php endforeach; ?>

    <button class="btn" type="submit" style="position:sticky;bottom:16px">💾 Salva e applica a tutte le pagine</button>
  </form>

<script>
  // contatore caratteri con soglie
  function upd(el){
    var max = +el.getAttribute('maxlength'), soft = +(el.closest('.seo-field').querySelector('.cc').getAttribute('data-max')||max);
    var cc = el.closest('.seo-field').querySelector('.cc'), n = el.value.length;
    cc.textContent = n + '/' + soft;
    cc.className = 'cc' + (n > soft ? ' bad' : (n > soft*0.9 ? ' warn' : ''));
  }
  document.querySelectorAll('[data-count]').forEach(function(el){ upd(el); el.addEventListener('input', function(){ upd(el); }); });
</script>
</body>
</html>
