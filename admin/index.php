<?php
/* Dashboard pannello admin: login + elenco pagine modificabili. */
declare(strict_types=1);
require __DIR__ . '/auth.php';

$CSRF = csrf_token();

/* Logout */
if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: index.php'); exit; }

/* Login */
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['login'])) {
    $cfg = admin_cfg();
    if (csrf_check((string)($_POST['csrf'] ?? ''))
        && hash_equals($cfg['user'], (string)($_POST['user'] ?? ''))
        && password_verify((string)($_POST['pass'] ?? ''), $cfg['pass_hash'])) {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Credenziali non valide. Riprova.';
}

$authed = is_logged();
$pages  = $authed ? site_pages() : [];
$site   = $authed ? read_site() : ['nav' => []];
$navByPage = [];
foreach ($site['nav'] as $n) { if (isset($n['page'], $n['label'])) { $navByPage[$n['page']] = $n['label']; } }
$flash    = $_GET['flash'] ?? '';
$flashMsg = (string)($_GET['msg'] ?? '');

/* Etichette leggibili per le pagine */
$labels = [
    'index.html' => 'Home', 'chi-siamo.html' => 'Chi siamo', 'offerta-formativa.html' => 'Offerta formativa',
    'vita-scolastica.html' => 'Vita scolastica', 'teatro.html' => 'Teatro', 'sostienici.html' => 'Sostienici',
    'news.html' => 'News', 'contatti.html' => 'Contatti', 'modulistica.html' => 'Modulistica',
    'albo.html' => 'Albo', 'amministrazione-trasparente.html' => 'Amministrazione Trasparente',
    'privacy.html' => 'Privacy Policy',
];
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pannello contenuti — Santa Maria del Paradiso</title>
<link rel="icon" href="../assets/img/favicon.jpg">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500..600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
</head>
<body>

<?php if (!$authed): ?>
  <div class="login">
    <div class="card">
      <div class="logo"><img src="../assets/img/logo.png" alt="Logo"></div>
      <h1>Pannello contenuti</h1>
      <p class="sub">Accedi per modificare il sito.</p>
      <?php if ($error): ?><div class="flash err"><?= h($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="login" value="1">
        <div class="field"><label>Utente</label><input type="text" name="user" required autofocus></div>
        <div class="field"><label>Password</label><input type="password" name="pass" required></div>
        <button class="btn" type="submit">Accedi</button>
      </form>
    </div>
  </div>

<?php else: ?>
  <div class="topbar">
    <span class="brand"><img src="../assets/img/logo.png" alt="">Pannello contenuti</span>
    <span class="topactions"><a href="branding.php">🎨 Branding</a><a href="seo.php">🔍 SEO</a><a href="backup.php">💾 Backup</a><a href="news.php">📰 News</a><a href="documenti.php">🗂️ Documenti</a><a href="eventi.php">📅 Eventi</a><a href="avanzate.php">⚙️ Avanzate</a><a href="../index.html" target="_blank">Vedi il sito ↗</a><a href="?logout=1" class="logout">Esci</a></span>
  </div>

  <div class="wrap">
    <h1>Gestione del sito</h1>
    <p class="sub">Modifica i contenuti di ogni pagina nell'editor visuale, oppure crea, rinomina ed elimina pagine.</p>

    <?php if ($flash): ?><div class="flash <?= $flash === 'ok' ? 'ok' : 'err' ?>"><?= h($flashMsg) ?></div><?php endif; ?>

    <form class="newpage" method="post" action="pages.php">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
      <input type="hidden" name="action" value="create">
      <input type="text" name="label" placeholder="Nome della nuova pagina (es. Progetti)" required>
      <label class="chk"><input type="checkbox" name="in_menu" checked> nel menu</label>
      <button class="btn" type="submit">➕ Crea pagina</button>
    </form>

    <div class="pagegrid">
      <?php foreach ($pages as $p):
        $label  = $navByPage[$p] ?? ($labels[$p] ?? $p);
        $inMenu = isset($navByPage[$p]);
        $isHome = ($p === 'index.html'); ?>
        <div class="pagecard">
          <div class="pc-top">
            <div class="pc-ico">📄</div>
            <div class="pc-body">
              <strong><?= h($label) ?></strong>
              <span><?= h($p) ?><?= $inMenu ? ' · nel menu' : ' · fuori dal menu' ?></span>
            </div>
          </div>
          <div class="pc-actions">
            <a class="mini" href="editor.php?page=<?= h(rawurlencode($p)) ?>">✏️ Modifica</a>
            <button class="mini" type="button" onclick="toggleProps('<?= h($p) ?>')">⚙️ Proprietà</button>
            <?php if (!$isHome): ?>
              <form method="post" action="pages.php" style="display:inline" onsubmit="return confirm('Eliminare definitivamente «<?= h($label) ?>»? Operazione non reversibile.');">
                <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="page" value="<?= h($p) ?>">
                <button class="mini danger" type="submit">🗑️ Elimina</button>
              </form>
            <?php endif; ?>
          </div>
          <form class="props" id="props-<?= h($p) ?>" method="post" action="pages.php" hidden>
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="action" value="props">
            <input type="hidden" name="page" value="<?= h($p) ?>">
            <label>Etichetta nel menu</label>
            <input type="text" name="label" value="<?= h($label) ?>">
            <label class="chk"><input type="checkbox" name="in_menu" <?= $inMenu ? 'checked' : '' ?>> Mostra nel menu</label>
            <button class="btn" type="submit">Salva proprietà</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
    function toggleProps(p){ var el=document.getElementById('props-'+p); if(el){ el.hidden=!el.hidden; } }
  </script>
<?php endif; ?>

</body>
</html>
