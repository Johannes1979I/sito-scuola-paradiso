<?php
/* Editor visuale: modifica una pagina in anteprima reale. */
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login_redirect();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$page = basename((string)($_GET['page'] ?? ''));
if ($page === '' || !preg_match('/\.html$/', $page) || !is_file(dirname(__DIR__) . '/' . $page)) {
    http_response_code(404);
    echo 'Pagina non valida. <a href="index.php">Torna alla dashboard</a>';
    exit;
}
$CSRF = csrf_token();
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editor — <?= h($page) ?></title>
<link rel="icon" href="../assets/img/favicon.jpg">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500..600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
</head>
<body class="ed-body">

  <div class="ed-top">
    <div class="ed-row ed-row--main">
      <div class="ed-left">
        <a class="ed-back" href="index.php">← Pagine</a>
        <span class="ed-page">✏️ <?= h($page) ?></span>
        <span class="ed-status" id="edStatus">pronto</span>
      </div>
      <div class="ed-right">
        <button class="btn btn--ghost" id="btnMedia" type="button">🖼️ Libreria media</button>
        <button class="btn btn--ok" id="btnSave" type="button" disabled>Salva modifiche</button>
      </div>
    </div>
    <div class="ed-row ed-tools" id="edTools">
      <span class="tgrp">
        <button class="tbtn" data-cmd="undo" title="Annulla">↶</button>
        <button class="tbtn" data-cmd="redo" title="Ripeti">↷</button>
      </span>
      <span class="tgrp">
        <button class="tbtn" data-cmd="bold" title="Grassetto"><b>B</b></button>
        <button class="tbtn" data-cmd="italic" title="Corsivo"><i>I</i></button>
        <button class="tbtn" data-cmd="underline" title="Sottolineato"><u>U</u></button>
        <button class="tbtn" data-cmd="strikeThrough" title="Barrato"><s>S</s></button>
      </span>
      <span class="tgrp">
        <button class="tbtn tbtn--txt" data-cmd="formatBlock" data-val="h2" title="Titolo">Titolo</button>
        <button class="tbtn tbtn--txt" data-cmd="formatBlock" data-val="h3" title="Sottotitolo">Sottot.</button>
        <button class="tbtn tbtn--txt" data-cmd="formatBlock" data-val="p" title="Paragrafo">¶</button>
      </span>
      <span class="tgrp">
        <button class="tbtn" data-cmd="insertUnorderedList" title="Elenco puntato">• ≡</button>
        <button class="tbtn" data-cmd="insertOrderedList" title="Elenco numerato">1. ≡</button>
      </span>
      <span class="tgrp">
        <button class="tbtn" data-cmd="justifyLeft" title="Allinea a sinistra">⬅</button>
        <button class="tbtn" data-cmd="justifyCenter" title="Centra">⬌</button>
        <button class="tbtn" data-cmd="justifyRight" title="Allinea a destra">➡</button>
      </span>
      <span class="tgrp">
        <button class="tbtn" data-cmd="createLink" title="Inserisci link">🔗</button>
        <button class="tbtn" data-cmd="unlink" title="Rimuovi link">⛓️‍💥</button>
        <input class="tcolor" type="color" id="edColor" title="Colore testo" value="#13449b">
        <button class="tbtn" data-cmd="removeFormat" title="Pulisci formattazione">🧹</button>
      </span>
      <span class="tgrp">
        <select class="tbtn tbtn--txt" id="insType" title="Cosa inserire">
          <option value="media">🖼️ Media</option>
          <option value="box">▭ Riquadro</option>
          <option value="banner">🎟️ Banner</option>
          <option value="divider">― Divisore</option>
          <option value="spacer">␣ Spazio</option>
        </select>
        <button class="tbtn tbtn--txt" id="btnInsert" type="button" title="Poi clicca il punto della pagina">➕ Inserisci</button>
      </span>
      <span class="tgrp">
        <button class="tbtn tbtn--txt" id="btnMove" type="button" title="Trascina le caselle per riordinarle">↕ Sposta</button>
      </span>
      <span class="tgrp">
        <button class="tbtn tbtn--txt" id="btnStyle" type="button" title="Stile ed effetti dell'elemento selezionato">🎨 Stile &amp; effetti</button>
      </span>
      <span class="thint">Clicca un elemento per modificarlo o stilizzarlo</span>
    </div>
    <div class="ins-hint" id="insHint" hidden>🟢 Modalità inserimento: clicca il punto della pagina dove aggiungere il media — premi <b>ESC</b> per annullare</div>
    <div class="ins-hint ins-hint--move" id="moveHint" hidden>↕ Modalità spostamento: <b>trascina</b> una casella (testo, media, riquadro, grafica…) e rilasciala dove vuoi nella stessa sezione — premi <b>ESC</b> per uscire</div>
  </div>

  <div class="ed-stage">
    <div class="ed-frame-wrap">
      <iframe id="edFrame" src="../<?= h(rawurlencode($page)) ?>?edit=1" title="Anteprima editor"></iframe>
    </div>
  </div>

  <!-- Ispettore: stile ed effetti -->
  <aside class="inspector" id="inspector">
    <button class="insp-handle" id="inspHandle" type="button" title="Riduci / espandi il pannello">❯</button>
    <div class="insp-head">
      <div class="insp-target"><strong id="inspTitle">Nessun elemento</strong>
        <button class="mini" id="inspUp" type="button" title="Seleziona il contenitore">↑ Contenitore</button></div>
      <button class="modal-x" id="inspClose" type="button">✕</button>
    </div>
    <div class="insp-body">
      <section class="insp-sec">
        <h4>🎨 Stile</h4>
        <div class="insp-row"><label>Colore testo</label><span><input type="color" id="stColor"><button class="mini" id="stColorClear" type="button">×</button></span></div>
        <div class="insp-row"><label>Colore sfondo</label><span><input type="color" id="stBg"><button class="mini" id="stBgClear" type="button">×</button></span></div>
        <div class="insp-row"><label>Immagine di sfondo</label><span><button class="mini" id="stBgImg" type="button">Scegli</button><button class="mini" id="stBgImgClear" type="button">×</button></span></div>
        <div class="insp-row"><label>Arrotondamento <i id="stRadiusV"></i></label><input type="range" id="stRadius" min="0" max="48" step="1"></div>
        <div class="insp-row"><label>Spaziatura interna <i id="stPadV"></i></label><input type="range" id="stPad" min="0" max="80" step="2"></div>
        <div class="insp-row"><label>Ombra</label><label class="chk"><input type="checkbox" id="stShadow"> attiva</label></div>
        <div class="insp-row"><label>Allineamento</label><span class="seg" id="stAlign"><button data-al="left" type="button">⬅</button><button data-al="center" type="button">⬍</button><button data-al="right" type="button">➡</button></span></div>
      </section>
      <section class="insp-sec">
        <h4>✨ Effetti al passaggio del mouse</h4>
        <div class="insp-row"><label>Animazione</label>
          <select id="fxAnim">
            <option value="none">Nessuna</option>
            <option value="lift">Solleva</option>
            <option value="zoom">Ingrandisci</option>
            <option value="tilt">Inclina 3D</option>
            <option value="glow">Bagliore</option>
            <option value="bright">Luminosità</option>
            <option value="pulse">Pulsa</option>
            <option value="shake">Scuoti</option>
          </select>
        </div>
        <div class="insp-row"><label>Suono</label><span><button class="mini" id="fxSound" type="button">Scegli</button><button class="mini" id="fxSoundClear" type="button">×</button></span></div>
        <div class="insp-row"><label>Video</label><span><button class="mini" id="fxVideo" type="button">Scegli</button><button class="mini" id="fxVideoClear" type="button">×</button></span></div>
        <p class="insp-note">Suono e video partono al passaggio del mouse <b>sul sito pubblicato</b>; nell'editor vedi solo l'animazione.</p>
      </section>
    </div>
  </aside>

  <!-- Modale libreria media -->
  <div class="modal" id="mediaModal">
    <div class="modal-card">
      <div class="modal-head">
        <h3 id="mediaTitle">Libreria media</h3>
        <button class="modal-x" id="mediaClose" type="button">✕</button>
      </div>
      <div class="modal-tools">
        <label class="btn" style="cursor:pointer">⬆️ Carica file
          <input type="file" id="mediaUpload" accept="image/*,video/*,application/pdf" hidden>
        </label>
        <span class="spin" id="mediaSpin"></span>
      </div>
      <div class="modal-body">
        <div class="media-grid" id="mediaGrid"></div>
      </div>
    </div>
  </div>

<script>
  window.EDITOR_CONFIG = {
    page: <?= json_encode($page) ?>,
    csrf: <?= json_encode($CSRF) ?>
  };
</script>
<script src="editor.js?v=<?= filemtime(__DIR__ . '/editor.js') ?>"></script>
</body>
</html>
