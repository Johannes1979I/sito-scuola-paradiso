<?php
/* Gestore Eventi/Scadenze: CRUD su events.json. Renderizzato dal sito come agenda in [data-events]. */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login_redirect();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$FILE = dirname(__DIR__) . '/events.json';
function load_ev(string $f): array { $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : []; return is_array($d) ? $d : []; }
function save_ev(string $f, array $d): bool {
    return file_put_contents($f, json_encode(array_values($d), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
}

$events = load_ev($FILE);
$CSRF = csrf_token();
$flash = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('CSRF non valido'); }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = trim((string)($_POST['id'] ?? ''));
        if ($id === '') { $id = 'e' . bin2hex(random_bytes(4)); }
        $item = [
            'id'        => $id,
            'date'      => trim((string)($_POST['date'] ?? '')),
            'time'      => trim((string)($_POST['time'] ?? '')),
            'title'     => trim((string)($_POST['title'] ?? '')),
            'place'     => trim((string)($_POST['place'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'published' => isset($_POST['published']) ? 1 : 0,
        ];
        $found = false;
        foreach ($events as &$e) { if (($e['id'] ?? '') === $id) { $e = $item; $found = true; break; } }
        unset($e);
        if (!$found) { $events[] = $item; }
        save_ev($FILE, $events);
        $flash = 'Evento salvato.';
    } elseif ($action === 'delete') {
        $id = trim((string)($_POST['id'] ?? ''));
        $events = array_values(array_filter($events, function ($e) use ($id) { return ($e['id'] ?? '') !== $id; }));
        save_ev($FILE, $events);
        $flash = 'Evento eliminato.';
    }
    $events = load_ev($FILE);
}

usort($events, function ($a, $b) { return strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? '')); });
$edit = null;
if (isset($_GET['edit'])) { foreach ($events as $e) { if (($e['id'] ?? '') === $_GET['edit']) { $edit = $e; } } }
$monthsIt = [1=>'gennaio',2=>'febbraio',3=>'marzo',4=>'aprile',5=>'maggio',6=>'giugno',7=>'luglio',8=>'agosto',9=>'settembre',10=>'ottobre',11=>'novembre',12=>'dicembre'];
function pretty_date_it(string $d, array $m): string { $t = strtotime($d); return $t ? (date('j', $t) . ' ' . $m[(int)date('n', $t)] . ' ' . date('Y', $t)) : $d; }
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eventi / Scadenze — Pannello</title>
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
  .nw-field input,.nw-field textarea{width:100%;padding:10px 12px;border:1px solid #d7e0ee;border-radius:9px;font:inherit;font-size:.92rem;box-sizing:border-box}
  .nw-field textarea{resize:vertical;min-height:90px}
  .nw-row{display:flex;gap:12px;flex-wrap:wrap}.nw-row .nw-field{flex:1 1 200px}
  .nw-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
  .nw-item{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e4ebf5;border-radius:12px;padding:12px 14px}
  .nw-item.past{opacity:.55}
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
    <span class="brand"><img src="../assets/img/logo.png" alt="">Eventi / Scadenze</span>
    <span class="topactions"><a href="index.php">← Pannello</a> <a href="../vita-scolastica.html" target="_blank">Vita scolastica ↗</a></span>
  </div>

  <div class="nw-wrap">
    <?php if ($flash): ?><div class="flash ok"><?= h($flash) ?></div><?php endif; ?>

    <div class="nw-sec">
      <h3><?= $edit ? '✏️ Modifica evento' : '➕ Nuovo evento' ?></h3>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
        <div class="nw-row">
          <div class="nw-field"><label>Titolo</label><input type="text" name="title" required value="<?= h($edit['title'] ?? '') ?>" placeholder="Es. Open Day, Colloqui, Chiusura scuola"></div>
          <div class="nw-field" style="flex:0 0 170px"><label>Data</label><input type="date" name="date" required value="<?= h($edit['date'] ?? date('Y-m-d')) ?>"></div>
          <div class="nw-field" style="flex:0 0 130px"><label>Ora (facolt.)</label><input type="time" name="time" value="<?= h($edit['time'] ?? '') ?>"></div>
        </div>
        <div class="nw-field"><label>Luogo (facoltativo)</label><input type="text" name="place" value="<?= h($edit['place'] ?? '') ?>" placeholder="Es. Sede della scuola"></div>
        <div class="nw-field"><label>Descrizione (facoltativa)</label><textarea name="description" placeholder="Dettagli dell'evento"><?= h($edit['description'] ?? '') ?></textarea></div>
        <label style="display:flex;align-items:center;gap:8px;margin:0 0 14px;font-size:.9rem"><input type="checkbox" name="published" <?= (!$edit || ($edit['published'] ?? 0)) ? 'checked' : '' ?>> Pubblicato (visibile sul sito)</label>
        <button class="btn" type="submit">💾 Salva evento</button>
        <?php if ($edit): ?> <a class="btn sm" href="eventi.php">Annulla</a><?php endif; ?>
      </form>
    </div>

    <div class="nw-sec">
      <h3>📋 Eventi (<?= count($events) ?>)</h3>
      <?php if (!$events): ?><p style="color:#5a6678">Nessun evento. Creane uno qui sopra.</p><?php else: ?>
      <ul class="nw-list">
        <?php foreach ($events as $e): $past = (string)($e['date'] ?? '') < $today; ?>
          <li class="nw-item <?= $past ? 'past' : '' ?>">
            <div>
              <strong><?= h($e['title'] ?? '(senza titolo)') ?></strong>
              <span class="st <?= ($e['published'] ?? 0) ? 'pub' : 'dra' ?>"><?= ($e['published'] ?? 0) ? 'pubblicato' : 'bozza' ?></span>
              <div class="meta"><?= h(pretty_date_it((string)($e['date'] ?? ''), $monthsIt)) ?><?= ($e['time'] ?? '') ? ' · ' . h($e['time']) : '' ?><?= ($e['place'] ?? '') ? ' · ' . h($e['place']) : '' ?><?= $past ? ' · (passato)' : '' ?></div>
            </div>
            <div class="nw-actions">
              <a class="btn sm" href="eventi.php?edit=<?= h($e['id'] ?? '') ?>">Modifica</a>
              <form method="post" onsubmit="return confirm('Eliminare questo evento?')" style="margin:0">
                <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= h($e['id'] ?? '') ?>">
                <button class="btn sm danger" type="submit">Elimina</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <p style="color:#8290a8;font-size:.85rem">Nel sito compaiono i <b>prossimi</b> eventi pubblicati (agenda in <b>Vita scolastica</b>). Dopo le modifiche fai <b>git push</b> per pubblicare.</p>
  </div>
</body>
</html>
