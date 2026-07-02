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
<style>
  /* Anteprima responsive (Modulo C) */
  .ed-devices { display: inline-flex; gap: 2px; background: #eef2f8; border-radius: 100px; padding: 3px; margin-right: 6px; }
  .dvbtn { border: none; background: transparent; padding: 6px 10px; border-radius: 100px; cursor: pointer; font-size: .95rem; line-height: 1; }
  .dvbtn.active { background: #fff; box-shadow: 0 2px 6px rgba(15, 28, 51, .15); }
  #edFrame { transition: width .35s cubic-bezier(.2, .8, .2, 1); }
  .ed-frame-wrap.dev-tablet, .ed-frame-wrap.dev-mobile { display: grid; place-items: start center; background: #e9eef6; overflow: auto; }
  .ed-frame-wrap.dev-tablet #edFrame, .ed-frame-wrap.dev-mobile #edFrame { margin: 18px auto; border-radius: 22px; box-shadow: 0 20px 60px rgba(15, 28, 51, .28); border: 1px solid #d7e0ee; height: calc(100% - 36px); }
  .ed-frame-wrap.dev-tablet #edFrame { width: 820px; max-width: 96%; }
  .ed-frame-wrap.dev-mobile #edFrame { width: 402px; max-width: 96%; }
  /* Evidenziatore testo */
  .tcolor-wrap { display: inline-flex; align-items: center; gap: 1px; cursor: pointer; }
  .tcolor-ic { font-size: .95rem; }
  /* Modale sezioni */
  .sec-hint { margin: 0 0 14px; color: #5a6678; font-size: .9rem; line-height: 1.5; }
  .sec-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 14px; }
  .sec-card { border: 1px solid #e4ebf5; border-radius: 14px; padding: 15px; cursor: pointer; background: #fff; transition: transform .15s, box-shadow .15s, border-color .15s; text-align: left; }
  .sec-card:hover { transform: translateY(-3px); box-shadow: 0 14px 34px rgba(15, 28, 51, .14); border-color: #9cc0ff; }
  .sec-card .sec-ico { font-size: 1.5rem; }
  .sec-card h4 { margin: 8px 0 4px; font-family: "Fraunces", serif; font-size: 1.02rem; color: #13449b; }
  .sec-card p { margin: 0; font-size: .82rem; color: #5a6678; line-height: 1.45; }
</style>
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
        <span class="ed-devices" id="edDevices" title="Anteprima responsive">
          <button class="dvbtn active" data-dev="desktop" type="button" title="Desktop">🖥️</button>
          <button class="dvbtn" data-dev="tablet" type="button" title="Tablet">▭</button>
          <button class="dvbtn" data-dev="mobile" type="button" title="Telefono">📱</button>
        </span>
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
        <button class="tbtn tbtn--txt" data-cmd="formatBlock" data-val="blockquote" title="Citazione">❝</button>
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
        <label class="tcolor-wrap" title="Evidenzia testo"><span class="tcolor-ic">🖍️</span><input class="tcolor" type="color" id="edHilite" value="#fff3a3"></label>
        <button class="tbtn" data-cmd="removeFormat" title="Pulisci formattazione">🧹</button>
      </span>
      <span class="tgrp">
        <select class="tbtn tbtn--txt" id="edFont" title="Dimensione del testo">
          <option value="">Dimensione…</option>
          <option value="2">Piccolo</option>
          <option value="3">Normale</option>
          <option value="5">Grande</option>
          <option value="6">Molto grande</option>
        </select>
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
        <button class="tbtn tbtn--txt" id="btnSections" type="button" title="Inserisci una sezione già pronta">🧱 Sezioni</button>
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

  <!-- Modale libreria sezioni -->
  <div class="modal" id="secModal">
    <div class="modal-card">
      <div class="modal-head">
        <h3>Aggiungi una sezione</h3>
        <button class="modal-x" id="secClose" type="button">✕</button>
      </div>
      <div class="modal-body">
        <p class="sec-hint">Scegli un modello, poi <b>clicca il punto della pagina</b> dove inserirlo. Tutti i testi restano modificabili. Premi <b>ESC</b> per annullare.</p>
        <div class="sec-grid" id="secGrid"></div>
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
