<?php
/* Gestione pagine del sito: crea / proprietà (menu) / elimina. */
declare(strict_types=1);
require __DIR__ . '/auth.php';

if (!is_logged()) { header('Location: index.php'); exit; }

function back(string $type, string $msg): void {
    header('Location: index.php?flash=' . $type . '&msg=' . rawurlencode($msg));
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string)($_POST['csrf'] ?? ''))) {
    back('err', 'Sessione scaduta, riprova.');
}

$root   = dirname(__DIR__);
$action = (string)($_POST['action'] ?? '');
$site   = read_site();

/* Modello per una pagina nuova */
function page_template(string $slug, string $label): string {
    $L = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $L . ' — Scuola Santa Maria del Paradiso · Viterbo</title>
<meta name="description" content="' . $L . ' — Scuola Santa Maria del Paradiso, Viterbo.">
<link rel="icon" href="assets/img/favicon.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..700;1,9..144,400..600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-page="' . $slug . '">

<section class="page-hero">
  <div class="container">
    <div class="reveal">
      <div class="crumbs"><a href="index.html">Home</a> &nbsp;/&nbsp; ' . $L . '</div>
      <span class="eyebrow">' . $L . '</span>
      <h1>' . $L . '</h1>
      <p class="lead">Scrivi qui l\'introduzione di questa pagina. Puoi modificare tutto dal pannello.</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="reveal" style="max-width:760px">
      <h2>Titolo della sezione</h2>
      <p class="lead" style="margin-top:16px">Questo è il contenuto della pagina. Clicca i testi per modificarli, inserisci immagini e media dal pannello editor.</p>
    </div>
  </div>
</section>

<script src="assets/js/layout.js"></script>
</body>
</html>
';
}

if ($action === 'create') {
    $label = trim((string)($_POST['label'] ?? ''));
    if ($label === '') { back('err', 'Inserisci il nome della pagina.'); }
    $slug = slugify_page($label);
    if ($slug === '' || in_array($slug, reserved_slugs(), true)) { back('err', 'Nome non valido, scegline un altro.'); }
    $file = $root . '/' . $slug . '.html';
    if (is_file($file)) { back('err', 'Esiste già una pagina con questo nome.'); }
    if (@file_put_contents($file, page_template($slug, $label)) === false) {
        back('err', 'Impossibile creare il file (permessi di scrittura?).');
    }
    $inMenu = !empty($_POST['in_menu']);
    if ($inMenu) {
        $site['nav'][] = ['page' => $slug . '.html', 'label' => $label];
        write_site($site);
    }
    back('ok', 'Pagina «' . $label . '» creata.');
}

if ($action === 'props') {
    $page  = basename((string)($_POST['page'] ?? ''));
    $label = trim((string)($_POST['label'] ?? ''));
    if (!preg_match('/\.html$/', $page) || !is_file($root . '/' . $page)) { back('err', 'Pagina non valida.'); }
    if ($label === '') { back('err', 'Inserisci un\'etichetta.'); }
    $inMenu = !empty($_POST['in_menu']);
    // rimuovi eventuale voce esistente
    $site['nav'] = array_values(array_filter($site['nav'], function ($n) use ($page) {
        return ($n['page'] ?? '') !== $page;
    }));
    if ($inMenu) { $site['nav'][] = ['page' => $page, 'label' => $label]; }
    write_site($site);
    back('ok', 'Proprietà aggiornate.');
}

if ($action === 'delete') {
    $page = basename((string)($_POST['page'] ?? ''));
    if ($page === 'index.html') { back('err', 'La home non può essere eliminata.'); }
    if (!preg_match('/\.html$/', $page) || !is_file($root . '/' . $page)) { back('err', 'Pagina non valida.'); }
    @unlink($root . '/' . $page);
    // rimuovi dal menu
    $site['nav'] = array_values(array_filter($site['nav'], function ($n) use ($page) {
        return ($n['page'] ?? '') !== $page;
    }));
    write_site($site);
    // rimuovi gli override di contenuto della pagina
    $content = read_content();
    if (isset($content[$page])) { unset($content[$page]); write_content($content); }
    back('ok', 'Pagina eliminata.');
}

back('err', 'Azione sconosciuta.');
