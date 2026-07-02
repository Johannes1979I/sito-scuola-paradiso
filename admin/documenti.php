<?php
/* Gestore Documenti (albo/circolari/modulistica): CRUD su documents.json.
   Renderizzati dal sito nei contenitori [data-doc-list] e aperti nel modale .lm-*. */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login_redirect();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$FILE = dirname(__DIR__) . '/documents.json';
function load_docs(string $f): array { $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : []; return is_array($d) ? $d : []; }
function save_docs(string $f, array $d): bool {
    return file_put_contents($f, json_encode(array_values($d), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
}
$CATS = ['Circolari', 'Modulistica', 'Albo', 'Bilanci', 'PTOF', 'Amministrazione trasparente'];

$docs = load_docs($FILE);
$CSRF = csrf_token();
$flash = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('CSRF non valido'); }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = trim((string)($_POST['id'] ?? ''));
        if ($id === '') { $id = 'd' . bin2hex(random_bytes(4)); }
        $item = [
            'id'        => $id,
            'title'     => trim((string)($_POST['title'] ?? '')),
            'category'  => trim((string)($_POST['category'] ?? '')),
            'url'       => trim((string)($_POST['url'] ?? '')),
            'date'      => trim((string)($_POST['date'] ?? '')),
            'published' => isset($_POST['published']) ? 1 : 0,
        ];
        $found = false;
        foreach ($docs as &$d) { if (($d['id'] ?? '') === $id) { $d = $item; $found = true; break; } }
        unset($d);
        if (!$found) { $docs[] = $item; }
        save_docs($FILE, $docs);
        $flash = 'Documento salvato.';
    } elseif ($action === 'delete') {
        $id = trim((string)($_POST['id'] ?? ''));
        $docs = array_values(array_filter($docs, function ($d) use ($id) { return ($d['id'] ?? '') !== $id; }));
        save_docs($FILE, $docs);
        $flash = 'Documento eliminato.';
    }
    $docs = load_docs($FILE);
}

usort($docs, function ($a, $b) { return strcmp((string)($a['category'] ?? ''), (string)($b['category'] ?? '')) ?: strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')); });
$edit = null;
if (isset($_GET['edit'])) { foreach ($docs as $d) { if (($d['id'] ?? '') === $_GET['edit']) { $edit = $d; } } }
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Documenti — Pannello</title>
<link rel="icon" href="../assets/img/favicon.jpg">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500..600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
<style>
  .nw-wrap{max-width:900px;margin:0 auto;padding:0 18px 70px}
  .nw-bar{position:sticky;top:0;z-index:5;background:#fff;border-bottom:1px solid #e4ebf5;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
  .nw-sec{background:#fff;border:1px solid #e4ebf5;border-radius:14px;padding:18px;margin:0 0 18px}
  .nw-sec h3{margin:0 0 12px;font-family:"Fraunces",serif;color:var(--color-primary,#13449b)}
  .nw-field{margin:0 0 12px}
  .nw-field label{display:block;font-size:.82rem;font-weight:600;color:#33415c;margin:0 0 5px}
  .nw-field input,.nw-field select{width:100%;padding:10px 12px;border:1px solid #d7e0ee;border-radius:9px;font:inherit;font-size:.92rem;box-sizing:border-box}
  .nw-row{display:flex;gap:12px;flex-wrap:wrap}.nw-row .nw-field{flex:1 1 200px}
  .nw-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
  .nw-item{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e4ebf5;border-radius:12px;padding:12px 14px}
  .nw-item .meta{font-size:.8rem;color:#8290a8}
  .nw-item .st{font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:100px}
  .st.pub{background:#e7f7ee;color:#1f7a44}.st.dra{background:#fdf0e3;color:#a15a12}
  .nw-actions{display:flex;gap:8px}
  .flash.ok{background:#e7f7ee;color:#1f7a44;border:1px solid #bfe6cf;padding:11px 14px;border-radius:10px;margin:0 0 16px}
  .btn.sm{padding:7px 12px;font-size:.85rem}
  .btn.danger{background:#fdecec;color:#c0392b;border:1px solid #f3c0c0}
</style>
</head>
<body>
  <div class="nw-bar">
    <span class="brand"><img src="../assets/img/logo.png" alt="">Documenti</span>
    <span class="topactions"><a href="index.php">← Pannello</a> <a href="../modulistica.html" target="_blank">Modulistica ↗</a> <a href="../albo.html" target="_blank">Albo ↗</a></span>
  </div>

  <div class="nw-wrap">
    <?php if ($flash): ?><div class="flash ok"><?= h($flash) ?></div><?php endif; ?>

    <div class="nw-sec">
      <h3><?= $edit ? '✏️ Modifica documento' : '➕ Nuovo documento' ?></h3>
      <p style="color:#5a6678;font-size:.85rem;margin:-4px 0 14px">Carica prima il PDF dalla <b>Libreria media</b> dell'editor, poi incolla qui il percorso (es. <code>uploads/circolare.pdf</code>). Per i file su Google Drive usa il link <code>.../preview</code>.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
        <div class="nw-row">
          <div class="nw-field"><label>Titolo</label><input type="text" name="title" required value="<?= h($edit['title'] ?? '') ?>" placeholder="Titolo del documento"></div>
          <div class="nw-field" style="flex:0 0 240px"><label>Categoria</label>
            <select name="category"><?php foreach ($CATS as $c): ?><option <?= (($edit['category'] ?? '') === $c) ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <div class="nw-row">
          <div class="nw-field"><label>Percorso o URL del PDF</label><input type="text" name="url" required value="<?= h($edit['url'] ?? '') ?>" placeholder="uploads/documento.pdf"></div>
          <div class="nw-field" style="flex:0 0 200px"><label>Data (facoltativa)</label><input type="date" name="date" value="<?= h($edit['date'] ?? '') ?>"></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin:0 0 14px;font-size:.9rem"><input type="checkbox" name="published" <?= (!$edit || ($edit['published'] ?? 0)) ? 'checked' : '' ?>> Pubblicato (visibile sul sito)</label>
        <button class="btn" type="submit">💾 Salva documento</button>
        <?php if ($edit): ?> <a class="btn sm" href="documenti.php">Annulla</a><?php endif; ?>
      </form>
    </div>

    <div class="nw-sec">
      <h3>📋 Documenti (<?= count($docs) ?>)</h3>
      <?php if (!$docs): ?><p style="color:#5a6678">Nessun documento. Creane uno qui sopra.</p><?php else: ?>
      <ul class="nw-list">
        <?php foreach ($docs as $d): ?>
          <li class="nw-item">
            <div>
              <strong><?= h($d['title'] ?? '(senza titolo)') ?></strong>
              <span class="st <?= ($d['published'] ?? 0) ? 'pub' : 'dra' ?>"><?= ($d['published'] ?? 0) ? 'pubblicato' : 'bozza' ?></span>
              <div class="meta"><?= h($d['category'] ?? '') ?> · <?= h($d['url'] ?? '') ?></div>
            </div>
            <div class="nw-actions">
              <a class="btn sm" href="documenti.php?edit=<?= h($d['id'] ?? '') ?>">Modifica</a>
              <form method="post" onsubmit="return confirm('Eliminare questo documento?')" style="margin:0">
                <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= h($d['id'] ?? '') ?>">
                <button class="btn sm danger" type="submit">Elimina</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <p style="color:#8290a8;font-size:.85rem">Nel sito, i documenti compaiono dove è presente un contenitore <code>[data-doc-list]</code> (già inserito in <b>Modulistica</b> e <b>Albo</b>), filtrabile per categoria. Dopo le modifiche fai <b>git push</b> per pubblicare.</p>
  </div>
</body>
</html>
