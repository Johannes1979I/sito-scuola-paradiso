/* =============================================================
   Editor visuale — opera sull'iframe (stessa origine).
   ============================================================= */
(function () {
  "use strict";
  var CFG = window.EDITOR_CONFIG || {};
  var frame = document.getElementById("edFrame");
  var statusEl = document.getElementById("edStatus");
  var btnSave = document.getElementById("btnSave");
  var btnMedia = document.getElementById("btnMedia");
  var tools = document.getElementById("edTools");
  var edColor = document.getElementById("edColor");
  var btnInsert = document.getElementById("btnInsert");
  var insHint = document.getElementById("insHint");
  var btnMove = document.getElementById("btnMove");
  var moveHint = document.getElementById("moveHint");

  var modal = document.getElementById("mediaModal");
  var mediaGrid = document.getElementById("mediaGrid");
  var mediaUpload = document.getElementById("mediaUpload");
  var mediaClose = document.getElementById("mediaClose");
  var mediaTitle = document.getElementById("mediaTitle");
  var mediaSpin = document.getElementById("mediaSpin");

  var btnDelete = document.getElementById("btnDelete");
  var btnSections = document.getElementById("btnSections");
  var secModal = document.getElementById("secModal");
  var secGrid = document.getElementById("secGrid");
  var secClose = document.getElementById("secClose");
  var edHilite = document.getElementById("edHilite");
  var edFont = document.getElementById("edFont");
  var edDevices = document.getElementById("edDevices");
  var frameWrap = document.querySelector(".ed-frame-wrap");
  var INS_HINT_DEFAULT = insHint ? insHint.innerHTML : "";

  var FD, ER;                       // documento e runtime dell'iframe
  var dirty = { texts: {}, images: {}, blocks: {}, styles: {}, fx: {}, order: {}, alts: {} };
  var activeEl = null;              // elemento testo attualmente in modifica
  var savedRange = null;            // selezione salvata (per colore/link)
  var mediaMode = "browse";         // browse | replace | insert | insert-at
  var mediaTarget = null;
  var insertMode = false;
  var pendingSection = null;        // HTML della sezione da inserire (libreria sezioni)
  var lastHover = null;
  var moveMode = false;
  var dragSrc = null;
  var dragGhost = null;
  var dragging = false;
  var dropHint = null;
  var dropAfter = false;
  var grabDX = 0, grabDY = 0, ghostW = 0, ghostH = 0;
  // Contenitori i cui FIGLI DIRETTI sono "pezzi" spostabili (card, testi, media, voci…).
  // NB: niente .container/.split/section → non si trascinano interi blocchi/sezioni, solo i singoli pezzi.
  var MOVE_CONTAINERS = ".grid,.mission-grid,.gallery-grid,[data-gallery],.value-list,.levels,.promos,.cards,.news-grid,.hero-stats,.hero-copy,.section-head,.mission-head,.cta-inner,.split>div";

  /* ---------- util stato ---------- */
  function setStatus(txt, cls) {
    statusEl.textContent = txt;
    statusEl.className = "ed-status" + (cls ? " " + cls : "");
  }
  function markDirty() { btnSave.disabled = false; setStatus("modifiche non salvate", "dirty"); }

  /* ---------- pulizia HTML (rimuove gli artefatti dell'editor) ---------- */
  function cleanHTML(el) {
    var c = el.cloneNode(true);
    c.querySelectorAll(".ed-chrome").forEach(function (n) { n.remove(); });
    c.querySelectorAll("[contenteditable]").forEach(function (n) { n.removeAttribute("contenteditable"); });
    c.querySelectorAll("[data-ed-key],[data-ed-img],[data-ed-oi]").forEach(function (n) {
      n.removeAttribute("data-ed-key"); n.removeAttribute("data-ed-img"); n.removeAttribute("data-ed-oi");
    });
    ["ed-editable", "ed-img", "ed-img-noalt", "ed-hl"].forEach(function (cl) {
      c.querySelectorAll("." + cl).forEach(function (n) {
        n.classList.remove(cl); if (!n.classList.length) { n.removeAttribute("class"); }
      });
    });
    return c.innerHTML.trim();
  }

  /* ---------- testi modificabili (foglie) ---------- */
  function collectTextLeaves() {
    var sel = "h1,h2,h3,h4,h5,h6,p,li,blockquote,figcaption,span,strong,em,a,button";
    var nodes = Array.prototype.slice.call(FD.querySelectorAll(sel));
    var cand = nodes.filter(function (el) {
      if (ER.isSystem(el)) { return false; }
      if (el.closest("[data-noedit]")) { return false; }
      if (!el.textContent || !el.textContent.replace(/\s+/g, "").length) { return false; }
      var block = false;
      el.querySelectorAll("h1,h2,h3,h4,h5,h6,p,li,blockquote").forEach(function (c) {
        if (c.textContent.replace(/\s+/g, "").length) { block = true; }
      });
      return !block;
    });
    return cand.filter(function (el) {
      return !cand.some(function (o) { return o !== el && o.contains(el); });
    });
  }

  function setupText() {
    collectTextLeaves().forEach(function (el) {
      el.setAttribute("contenteditable", "true");
      el.dataset.edKey = ER.domKey(el);
      el.classList.add("ed-editable");
      el.addEventListener("focus", function () { activeEl = el; });
      el.addEventListener("input", function () {
        dirty.texts[el.dataset.edKey] = cleanHTML(el);
        markDirty();
      });
    });
  }

  /* ---------- immagini ---------- */
  function setupImages() {
    FD.querySelectorAll("img").forEach(function (img) {
      if (ER.isSystem(img)) { return; }
      img.dataset.edImg = ER.domKey(img);
      img.classList.add("ed-img");
      img.setAttribute("title", "Clicca per cambiare immagine");
      img.addEventListener("click", function (e) {
        e.preventDefault(); e.stopPropagation();
        openMedia("replace", img);
      });
      addAltBadge(img);
    });
  }
  /* Badge "testo alternativo" (accessibilità + SEO) su ogni immagine modificabile */
  function altKeyFor(img) { return img.dataset.edImg || ER.domKey(img); }
  function addAltBadge(img) {
    var parent = img.parentElement;
    if (!parent || parent.querySelector(":scope > .ed-alt-badge")) { return; }
    if (FD.defaultView.getComputedStyle(parent).position === "static") { parent.style.position = "relative"; }
    var badge = FD.createElement("button");
    badge.type = "button"; badge.className = "ed-chrome ed-alt-badge";
    function sync() {
      var a = (img.getAttribute("alt") || "").trim();
      badge.classList.toggle("noalt", !a);
      badge.textContent = a ? "alt ✓" : "⚠ alt";
      badge.title = a ? ('Testo alternativo: "' + a + '" — clicca per modificarlo')
                      : "Manca il testo alternativo (accessibilità e SEO) — clicca per aggiungerlo";
      img.classList.toggle("ed-img-noalt", !a);
    }
    badge.addEventListener("click", function (e) {
      e.preventDefault(); e.stopPropagation();
      var val = window.prompt("Testo alternativo dell'immagine (descrizione per accessibilità e motori di ricerca):", img.getAttribute("alt") || "");
      if (val === null) { return; }
      val = val.trim();
      img.setAttribute("alt", val);
      dirty.alts[altKeyFor(img)] = val;
      markDirty(); sync();
    });
    parent.appendChild(badge);
    sync();
  }

  /* ---------- gallerie (aggiungi/rimuovi media) ---------- */
  function blockKeyFor(container) { return ER.domKey(container); }
  function refreshBlockDirty(container) {
    var key = blockKeyFor(container);
    dirty.blocks[key] = cleanHTML(container);
    [dirty.texts, dirty.images].forEach(function (bag) {
      Object.keys(bag).forEach(function (k) { if (k.indexOf(key + ">") === 0) { delete bag[k]; } });
    });
    markDirty();
  }
  function addRemoveBtn(child, container) {
    if (child.querySelector(":scope > .ed-chrome")) { return; }
    child.style.position = "relative";
    var x = FD.createElement("button");
    x.className = "ed-chrome ed-remove";
    x.type = "button";
    x.setAttribute("contenteditable", "false");
    x.textContent = "✕";
    x.title = "Rimuovi";
    x.addEventListener("click", function (e) {
      e.preventDefault(); e.stopPropagation();
      child.remove(); refreshBlockDirty(container);
    });
    child.appendChild(x);
  }
  function setupGalleries() {
    FD.querySelectorAll(".gallery-grid, [data-gallery]").forEach(function (container) {
      Array.prototype.forEach.call(container.children, function (child) { addRemoveBtn(child, container); });
      var add = FD.createElement("button");
      add.className = "ed-chrome ed-add";
      add.type = "button";
      add.textContent = "＋ Aggiungi media";
      add.addEventListener("click", function (e) {
        e.preventDefault();
        openMedia("insert", container);
      });
      container.parentNode.insertBefore(add, container.nextSibling);
    });
  }
  function insertMediaIntoGallery(container, url, type) {
    var cell = FD.createElement("div");
    cell.className = "gallery-cell";
    if (type === "video") {
      cell.innerHTML = '<video src="' + url + '" controls></video>';
    } else if (type === "pdf") {
      cell.innerHTML = '<a href="' + url + '" target="_blank" rel="noopener" style="display:grid;place-items:center;height:100%;font-weight:600">📄 Apri PDF</a>';
    } else {
      cell.innerHTML = '<img src="' + url + '" alt="">';
    }
    container.appendChild(cell);
    addRemoveBtn(cell, container);
    refreshBlockDirty(container);
  }

  /* ---------- inserimento media in un punto qualsiasi ---------- */
  function makeMediaEl(url, type) {
    var fig = FD.createElement("figure");
    fig.className = "media-block";
    if (type === "video") { fig.innerHTML = '<video src="' + url + '" controls></video>'; }
    else if (type === "pdf") { fig.innerHTML = '<a href="' + url + '" target="_blank" rel="noopener" style="padding:22px;text-align:center;font-weight:600">📄 Apri il PDF</a>'; }
    else { fig.innerHTML = '<img src="' + url + '" alt="">'; }
    return fig;
  }
  function findContainer(el) {
    var c = el.closest(".container");
    if (c && !ER.isSystem(c)) { return c; }
    var p = el.parentElement;
    return (p && p.tagName !== "BODY" && !ER.isSystem(p)) ? p : null;
  }
  function insCandidate(el) {
    if (!el || ER.isSystem(el)) { return null; }
    var c = el.closest("p,h1,h2,h3,h4,h5,h6,li,blockquote,figure,.media-figure,img,.level,.svc,.promo,.news,.mission,.mission-item,.cta-band,.split,.grid,.section-head,.info-card,.form-card,.gallery-grid,.theatre-banner,.value-list,.media-block");
    if (c && !ER.isSystem(c)) { return c; }
    // ripiego: clic in un'area "vuota" → inserisci in fondo al contenitore/sezione più vicini
    var cont = el.closest(".container") || el.closest("section");
    if (cont && !ER.isSystem(cont)) {
      var inner = cont.classList.contains("container") ? cont : (cont.querySelector(":scope > .container") || cont);
      return inner.lastElementChild || inner;
    }
    return null;
  }
  function insertMediaAt(target, url, type) {
    var fig = makeMediaEl(url, type);
    target.parentNode.insertBefore(fig, target.nextSibling);
    var container = findContainer(fig) || fig.parentNode;
    addRemoveBtn(fig, container);
    refreshBlockDirty(container);
    exitInsertMode();
  }
  function enterInsertMode() {
    if (!FD) { return; }
    if (moveMode) { exitMoveMode(); }
    insertMode = true;
    FD.body.classList.add("ed-inserting");
    insHint.hidden = false;
    btnInsert.classList.add("active");
  }
  function exitInsertMode() {
    insertMode = false;
    pendingSection = null;
    if (lastHover) { lastHover.classList.remove("ed-insert-hover"); lastHover = null; }
    if (FD && FD.body) { FD.body.classList.remove("ed-inserting"); }
    insHint.hidden = true;
    if (INS_HINT_DEFAULT) { insHint.innerHTML = INS_HINT_DEFAULT; }
    btnInsert.classList.remove("active");
  }
  function onInsMove(e) {
    if (!insertMode) { return; }
    var c = insCandidate(e.target);
    if (lastHover && lastHover !== c) { lastHover.classList.remove("ed-insert-hover"); }
    if (c) { c.classList.add("ed-insert-hover"); lastHover = c; }
  }
  function onInsClick(e) {
    if (!insertMode) { return; }
    var c = insCandidate(e.target);
    if (!c) { return; }
    e.preventDefault(); e.stopPropagation();
    if (pendingSection) { insertSectionAt(c, pendingSection); pendingSection = null; return; }
    var t = insType ? insType.value : "media";
    if (t === "media") { openMedia("insert-at", c); }
    else { insertGraphicAt(c, t); }
  }

  /* ---------- SPOSTAMENTO: drag & drop per riordinare le caselle ---------- */
  // Indice originale di ogni figlio (serve a salvare il nuovo ordine in modo stabile).
  function tagOriginalIndices() {
    FD.querySelectorAll(MOVE_CONTAINERS).forEach(function (cont) {
      if (ER.isSystem(cont)) { return; }
      Array.prototype.forEach.call(cont.children, function (ch, i) {
        if (ch.nodeType === 1 && !ch.hasAttribute("data-ed-oi")) { ch.setAttribute("data-ed-oi", String(i)); }
      });
    });
  }
  function moveCandidates() {
    var out = [];
    FD.querySelectorAll(MOVE_CONTAINERS).forEach(function (cont) {
      if (ER.isSystem(cont)) { return; }
      var real = Array.prototype.filter.call(cont.children, function (ch) {
        return ch.nodeType === 1 && !ER.isSystem(ch) && !ch.classList.contains("ed-chrome");
      });
      if (real.length < 2) { return; }   // serve almeno un fratello con cui scambiarsi
      real.forEach(function (ch) { out.push(ch); });
    });
    return out;
  }
  function refreshKeys() {
    FD.querySelectorAll(".ed-editable").forEach(function (el) { el.dataset.edKey = ER.domKey(el); });
    FD.querySelectorAll(".ed-img").forEach(function (img) { img.dataset.edImg = ER.domKey(img); });
  }
  function recordOrder(container) {
    var key = ER.domKey(container);
    var arr = [], ok = true;
    Array.prototype.forEach.call(container.children, function (ch) {
      if (ch.classList.contains("ed-chrome")) { return; }
      var v = ch.getAttribute("data-ed-oi");
      if (v === null) { ok = false; } else { arr.push(parseInt(v, 10)); }
    });
    if (!ok || arr.length < 2) { refreshBlockDirty(container); return; }   // ripiego: contenitore con elementi inseriti
    dirty.order[key] = arr;
    markDirty();
  }
  // Riallinea gli override esistenti dei figli che cambiano posizione (così una personalizzazione
  // fatta PRIMA dello spostamento resta agganciata alla casella giusta anche dopo).
  function remapOverridesForMove(container) {
    var buckets = ["texts", "images", "styles", "fx"], renames = [];
    Array.prototype.forEach.call(container.children, function (ch) {
      if (ch.classList.contains("ed-chrome") || ch.__edOldKey == null) { return; }
      var oldK = ch.__edOldKey, newK = ER.domKey(ch);
      if (newK === oldK) { return; }
      buckets.forEach(function (b) {
        var seen = {};
        if (saved[b]) { Object.keys(saved[b]).forEach(function (k) { seen[k] = 1; }); }
        if (dirty[b]) { Object.keys(dirty[b]).forEach(function (k) { seen[k] = 1; }); }
        Object.keys(seen).forEach(function (key) {
          if (key === oldK || key.indexOf(oldK + ">") === 0) {
            var val = (dirty[b] && dirty[b][key] !== undefined) ? dirty[b][key] : (saved[b] ? saved[b][key] : undefined);
            renames.push({ b: b, oldKey: key, newKey: newK + key.slice(oldK.length), val: val });
          }
        });
      });
    });
    renames.forEach(function (o) {
      if (saved[o.b]) { delete saved[o.b][o.oldKey]; }
      if (dirty[o.b]) { delete dirty[o.b][o.oldKey]; }
      dirty[o.b] = dirty[o.b] || {}; dirty[o.b][o.oldKey] = null;
    });
    renames.forEach(function (o) {
      if (o.val == null) { return; }
      saved[o.b] = saved[o.b] || {}; saved[o.b][o.newKey] = o.val;
      dirty[o.b] = dirty[o.b] || {}; dirty[o.b][o.newKey] = o.val;
    });
  }
  function clearDropHint() {
    if (dropHint) { dropHint.classList.remove("ed-drop-before", "ed-drop-after"); dropHint = null; }
  }
  // Trascinamento con POINTER EVENTS (mouse + touch): affidabile, niente HTML5 drag nativo.
  function onPointerDown(e) {
    if (!moveMode) { return; }
    if (e.pointerType === "mouse" && e.button !== 0) { return; }
    var box = (e.target && e.target.closest) ? e.target.closest(".ed-draggable") : null;
    if (!box) { return; }
    e.preventDefault();
    var r = box.getBoundingClientRect();
    grabDX = e.clientX - r.left; grabDY = e.clientY - r.top; ghostW = r.width; ghostH = r.height;
    dragSrc = box; dragging = true; dropAfter = false;
    box.classList.add("ed-drag-src");
  }
  function onPointerMove(e) {
    if (!dragging || !dragSrc) { return; }
    e.preventDefault();
    if (!dragGhost) {                                  // crea il "fantasma" al primo movimento (non al semplice clic)
      dragGhost = dragSrc.cloneNode(true);
      dragGhost.classList.add("ed-ghost");
      dragGhost.classList.remove("ed-draggable", "ed-drag-src");
      dragGhost.style.width = ghostW + "px"; dragGhost.style.height = ghostH + "px";
      FD.body.appendChild(dragGhost);
    }
    dragGhost.style.left = (e.clientX - grabDX) + "px";
    dragGhost.style.top = (e.clientY - grabDY) + "px";
    // bersaglio = fratello PIÙ VICINO al puntatore (robusto e tollerante ai gap; niente elementFromPoint)
    var parent = dragSrc.parentElement, kids = parent.children, x = e.clientX, y = e.clientY;
    var target = null, bestDist = Infinity;
    for (var i = 0; i < kids.length; i++) {
      var ch = kids[i];
      if (ch === dragSrc || !ch.classList || !ch.classList.contains("ed-draggable")) { continue; }
      var cr = ch.getBoundingClientRect();
      var cx = cr.left + cr.width / 2, cy = cr.top + cr.height / 2, dx = x - cx, dy = y - cy, d = dx * dx + dy * dy;
      if (d < bestDist) { bestDist = d; target = ch; dropAfter = (Math.abs(dx) > Math.abs(dy)) ? (dx > 0) : (dy > 0); }
    }
    if (!target) { clearDropHint(); return; }
    if (dropHint && dropHint !== target) { dropHint.classList.remove("ed-drop-before", "ed-drop-after"); }
    dropHint = target;
    target.classList.toggle("ed-drop-after", dropAfter);
    target.classList.toggle("ed-drop-before", !dropAfter);
  }
  function onPointerUp() {
    if (!dragging) { return; }
    dragging = false;
    if (dropHint && dragSrc && dropHint !== dragSrc && dropHint.parentElement === dragSrc.parentElement) {
      var parent = dragSrc.parentElement;
      Array.prototype.forEach.call(parent.children, function (ch) { if (!ch.classList.contains("ed-chrome")) { ch.__edOldKey = ER.domKey(ch); } });
      if (dropAfter) { parent.insertBefore(dragSrc, dropHint.nextSibling); } else { parent.insertBefore(dragSrc, dropHint); }
      try { remapOverridesForMove(parent); } catch (err) {}
      Array.prototype.forEach.call(parent.children, function (ch) { try { delete ch.__edOldKey; } catch (er) {} });
      recordOrder(parent);
      refreshKeys();
    }
    if (dragSrc) { dragSrc.classList.remove("ed-drag-src"); }
    if (dragGhost && dragGhost.parentNode) { dragGhost.parentNode.removeChild(dragGhost); }
    dragGhost = null; dragSrc = null;
    clearDropHint();
  }
  function enterMoveMode() {
    if (!FD) { return; }
    if (insertMode) { exitInsertMode(); }
    moveMode = true;
    FD.body.classList.add("ed-moving");
    if (moveHint) { moveHint.hidden = false; }
    if (btnMove) { btnMove.classList.add("active"); }
    FD.querySelectorAll('[contenteditable="true"]').forEach(function (t) { t.setAttribute("data-ed-ce", "1"); t.removeAttribute("contenteditable"); });
    moveCandidates().forEach(function (el) { el.classList.add("ed-draggable"); });
  }
  function exitMoveMode() {
    moveMode = false;
    clearDropHint();
    if (dragSrc) { dragSrc.classList.remove("ed-drag-src"); dragSrc = null; }
    if (FD && FD.body) { FD.body.classList.remove("ed-moving"); }
    if (moveHint) { moveHint.hidden = true; }
    if (btnMove) { btnMove.classList.remove("active"); }
    if (FD) {
      FD.querySelectorAll(".ed-draggable").forEach(function (el) { el.removeAttribute("draggable"); el.classList.remove("ed-draggable", "ed-drop-before", "ed-drop-after"); });
      FD.querySelectorAll('[data-ed-ce="1"]').forEach(function (t) { t.setAttribute("contenteditable", "true"); t.removeAttribute("data-ed-ce"); });
    }
  }

  /* ---------- selezione (per colore/link) ---------- */
  function saveSelection() {
    var sel = FD.getSelection();
    if (sel && sel.rangeCount) { savedRange = sel.getRangeAt(0).cloneRange(); }
  }
  function restoreSelection() {
    if (!savedRange) { return; }
    var sel = FD.getSelection();
    sel.removeAllRanges(); sel.addRange(savedRange);
  }
  function syncActiveDirty() {
    if (activeEl) { dirty.texts[activeEl.dataset.edKey] = cleanHTML(activeEl); markDirty(); }
  }

  /* ---------- toolbar ---------- */
  function exec(cmd, val) { frame.contentWindow.focus(); restoreSelection(); FD.execCommand(cmd, false, val || null); }
  function updateToolbarState() {
    var map = { bold: "bold", italic: "italic", underline: "underline", strikeThrough: "strikeThrough",
      insertUnorderedList: "insertUnorderedList", insertOrderedList: "insertOrderedList",
      justifyLeft: "justifyLeft", justifyCenter: "justifyCenter", justifyRight: "justifyRight" };
    tools.querySelectorAll(".tbtn[data-cmd]").forEach(function (b) {
      var c = b.dataset.cmd;
      if (map[c]) { try { b.classList.toggle("active", FD.queryCommandState(map[c])); } catch (e) {} }
    });
  }
  tools.addEventListener("mousedown", function (e) {
    var btn = e.target.closest(".tbtn[data-cmd]");
    if (!btn) { return; }
    e.preventDefault();
    var cmd = btn.dataset.cmd;
    if (cmd === "createLink") {
      var url = window.prompt("Indirizzo del link (https://…):", "https://");
      if (url) { exec("createLink", url); }
    } else if (cmd === "formatBlock") {
      exec("formatBlock", btn.dataset.val);
    } else {
      exec(cmd);
    }
    syncActiveDirty();
    updateToolbarState();
  });
  edColor.addEventListener("input", function () { exec("foreColor", edColor.value); syncActiveDirty(); });
  if (edHilite) {
    edHilite.addEventListener("input", function () {
      frame.contentWindow.focus(); restoreSelection();
      try { FD.execCommand("styleWithCSS", false, true); } catch (e) {}
      if (!FD.execCommand("hiliteColor", false, edHilite.value)) { FD.execCommand("backColor", false, edHilite.value); }
      syncActiveDirty();
    });
  }
  if (edFont) {
    edFont.addEventListener("change", function () {
      if (!edFont.value) { return; }
      exec("fontSize", edFont.value); syncActiveDirty(); edFont.selectedIndex = 0;
    });
  }

  /* ---------- libreria media ---------- */
  function openMedia(mode, target) {
    mediaMode = mode; mediaTarget = target || null;
    mediaTitle.textContent = mode === "replace" ? "Scegli la nuova immagine"
      : ((mode === "insert" || mode === "insert-at") ? "Aggiungi un media" : "Libreria media");
    modal.classList.add("open");
    loadMedia();
  }
  function closeMedia() { modal.classList.remove("open"); }
  function loadMedia() {
    mediaSpin.textContent = "Carico…";
    mediaGrid.innerHTML = "";
    fetch("media.php", { headers: { "X-CSRF-Token": CFG.csrf } })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        mediaSpin.textContent = "";
        (res.media || []).forEach(function (m) {
          var tile = document.createElement("div");
          tile.className = "media-tile";
          var thumb = m.type === "image"
            ? '<div class="media-thumb"><img src="../' + m.url + '" alt=""></div>'
            : '<div class="media-thumb"><span class="ph">' + (m.type === "video" ? "🎬" : "📄") + "</span></div>";
          tile.innerHTML = thumb + '<div class="media-meta">' + m.name + "</div>";
          tile.addEventListener("click", function () { pickMedia(m); });
          mediaGrid.appendChild(tile);
        });
        if (!(res.media || []).length) { mediaGrid.innerHTML = '<p class="spin">Nessun media. Carica il primo file.</p>'; }
      })
      .catch(function () { mediaSpin.textContent = "Errore nel caricamento."; });
  }
  function pickMedia(m) {
    if (mediaMode === "replace" && mediaTarget) {
      mediaTarget.setAttribute("src", m.url);
      dirty.images[mediaTarget.dataset.edImg] = m.url;
      markDirty();
    } else if (mediaMode === "insert" && mediaTarget) {
      insertMediaIntoGallery(mediaTarget, m.url, m.type);
    } else if (mediaMode === "insert-at" && mediaTarget) {
      insertMediaAt(mediaTarget, m.url, m.type);
    } else if (mediaMode === "bg" && mediaTarget) {
      selectEl(mediaTarget);
      setStyle("background-image", "url('" + m.url + "')");
      setStyle("background-size", "cover");
      setStyle("background-position", "center");
    } else if (mediaMode === "sound" && mediaTarget) {
      selectEl(mediaTarget); rememberFx("sound", m.url); fillInspector();
    } else if (mediaMode === "video" && mediaTarget) {
      selectEl(mediaTarget); rememberFx("video", m.url); fillInspector();
    }
    closeMedia();
  }
  mediaUpload.addEventListener("change", function () {
    var file = mediaUpload.files && mediaUpload.files[0];
    if (!file) { return; }
    mediaSpin.textContent = "Caricamento…";
    var fd = new FormData();
    fd.append("file", file);
    fd.append("csrf", CFG.csrf);
    fetch("upload.php", { method: "POST", headers: { "X-CSRF-Token": CFG.csrf }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        mediaUpload.value = "";
        if (res.ok) { mediaSpin.textContent = "Caricato ✓"; loadMedia(); }
        else { mediaSpin.textContent = "Errore: " + (res.error || "?"); }
      })
      .catch(function () { mediaSpin.textContent = "Errore di rete."; });
  });
  btnMedia.addEventListener("click", function () { openMedia("browse", null); });
  btnInsert.addEventListener("click", function () { if (insertMode) { exitInsertMode(); } else { enterInsertMode(); } });
  if (btnMove) { btnMove.addEventListener("click", function () { if (moveMode) { exitMoveMode(); } else { enterMoveMode(); } }); }
  if (btnSections) { btnSections.addEventListener("click", openSections); }
  if (btnDelete) { btnDelete.addEventListener("click", deleteSelected); }
  if (secClose) { secClose.addEventListener("click", closeSections); }
  if (secModal) { secModal.addEventListener("click", function (e) { if (e.target === secModal) { closeSections(); } }); }
  if (edDevices) { edDevices.addEventListener("click", function (e) { var b = e.target.closest(".dvbtn"); if (b) { setDevice(b.dataset.dev); } }); }
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") { if (insertMode) { exitInsertMode(); } if (moveMode) { exitMoveMode(); } if (secModal && secModal.classList.contains("open")) { closeSections(); } } });
  mediaClose.addEventListener("click", closeMedia);
  modal.addEventListener("click", function (e) { if (e.target === modal) { closeMedia(); } });

  /* ---------- salvataggio ---------- */
  btnSave.addEventListener("click", function () {
    btnSave.disabled = true; setStatus("salvataggio…");
    fetch("save.php", {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": CFG.csrf },
      body: JSON.stringify({ page: CFG.page, texts: dirty.texts, images: dirty.images, blocks: dirty.blocks, styles: dirty.styles, fx: dirty.fx, order: dirty.order, alts: dirty.alts })
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok) { mergeDirtyIntoSaved(); dirty = { texts: {}, images: {}, blocks: {}, styles: {}, fx: {}, order: {}, alts: {} }; setStatus("salvato ✓", "saved"); }
        else { setStatus("errore: " + (res.error || "?"), "dirty"); btnSave.disabled = false; }
      })
      .catch(function () { setStatus("errore di rete", "dirty"); btnSave.disabled = false; });
  });

  /* ---------- init dopo il caricamento dell'iframe ---------- */
  function injectStyle() {
    var css = "" +
      "*{transition:none!important;animation:none!important}" +
      ".ed-editable{outline:1.5px dashed transparent;outline-offset:3px;border-radius:4px;cursor:text}" +
      ".ed-editable:hover{outline-color:#9cc0ff;background:rgba(19,68,155,.05)}" +
      ".ed-editable:focus{outline:2px solid #13449b;background:rgba(19,68,155,.07)}" +
      ".ed-img{cursor:pointer;outline:2px solid transparent}" +
      ".ed-img:hover{outline-color:#1fc196}" +
      ".ed-chrome{font-family:Inter,sans-serif}" +
      ".ed-remove{position:absolute;top:6px;right:6px;z-index:9;width:26px;height:26px;border-radius:50%;border:none;background:#ff5470;color:#fff;cursor:pointer;font-size:.8rem;box-shadow:0 4px 10px rgba(0,0,0,.25)}" +
      ".ed-add{display:inline-flex;margin:16px auto 0;padding:11px 20px;border:2px dashed #13449b;background:#eef3fc;color:#13449b;font-weight:600;border-radius:100px;cursor:pointer}" +
      ".gallery-cell{border-radius:18px;overflow:hidden;border:1px solid #e4ebf5;background:#000;aspect-ratio:16/10}" +
      ".gallery-cell img,.gallery-cell video{width:100%;height:100%;object-fit:cover;display:block}" +
      ".ed-inserting,.ed-inserting *{cursor:copy!important}" +
      ".ed-insert-hover{outline:3px solid #1fc196!important;outline-offset:2px!important}" +
      ".ed-moving [contenteditable]{cursor:default}" +
      ".ed-draggable{cursor:grab;outline:1.5px dashed #b9c7e6;outline-offset:2px;touch-action:none;-webkit-user-select:none;user-select:none}" +
      ".ed-draggable:hover{outline-color:#13449b;background:rgba(19,68,155,.05)}" +
      ".ed-draggable:active{cursor:grabbing}" +
      ".ed-drag-src{opacity:.3}" +
      ".ed-drop-before{box-shadow:inset 0 5px 0 -1px #1fc196!important}" +
      ".ed-drop-after{box-shadow:inset 0 -5px 0 -1px #1fc196!important}" +
      ".ed-ghost{position:fixed;z-index:99999;pointer-events:none;opacity:.85;margin:0!important;border-radius:10px;overflow:hidden;box-shadow:0 18px 50px rgba(15,28,51,.35);transform:rotate(1.5deg)}" +
      ".ed-alt-badge{position:absolute;top:6px;left:6px;z-index:9;padding:3px 8px;border:none;border-radius:100px;font-size:.7rem;font-weight:700;cursor:pointer;box-shadow:0 3px 8px rgba(0,0,0,.22);background:#1fc196;color:#fff}" +
      ".ed-alt-badge.noalt{background:#f5a623;color:#3a2a00}" +
      ".ed-img-noalt{outline:2px dashed #f5a623!important;outline-offset:2px}" +
      ".ed-selected{outline:2px solid #ff7a3c!important;outline-offset:2px}";
    var st = FD.createElement("style");
    st.textContent = css;
    FD.head.appendChild(st);
  }

  function init() {
    try { FD = frame.contentDocument; ER = frame.contentWindow.EditRuntime; } catch (e) { FD = null; }
    if (!FD || !ER) { setStatus("errore: anteprima non accessibile", "dirty"); return; }

    // applica gli override salvati per questa pagina, poi prepara l'editing
    fetch("../content.json", { cache: "no-store" })
      .then(function (r) { return r.ok ? r.json() : {}; })
      .then(function (all) { saved = (all && all[CFG.page]) || {}; tagOriginalIndices(); ER.applyOverrides(FD, saved, false); })
      .catch(function () {})
      .then(function () {
        injectStyle();
        FD.querySelectorAll(".reveal").forEach(function (e) { e.classList.add("in"); });
        FD.addEventListener("click", function (e) { var a = e.target.closest("a"); if (a) { e.preventDefault(); } }, true);
        FD.addEventListener("selectionchange", function () { saveSelection(); updateToolbarState(); });
        FD.addEventListener("click", onSelect);
        setupText();
        setupImages();
        setupGalleries();
        FD.addEventListener("mousemove", onInsMove);
        FD.addEventListener("click", onInsClick, true);
        FD.addEventListener("pointerdown", onPointerDown, true);
        FD.addEventListener("pointermove", onPointerMove, true);
        FD.addEventListener("pointerup", onPointerUp, true);
        FD.addEventListener("pointercancel", onPointerUp, true);
        setStatus("pronto");
      });
  }

  /* ============================================================
     ISPETTORE (stile & effetti) + inserimento parti grafiche
     ============================================================ */
  var inspector = document.getElementById("inspector");
  var inspTitle = document.getElementById("inspTitle");
  var inspUp = document.getElementById("inspUp");
  var inspClose = document.getElementById("inspClose");
  var inspHandle = document.getElementById("inspHandle");
  var btnStyle = document.getElementById("btnStyle");
  var insType = document.getElementById("insType");
  var selectedEl = null;
  var inspectorOpen = false;
  var saved = {};

  function $id(id) { return document.getElementById(id); }
  function selKey() { return selectedEl ? ER.domKey(selectedEl) : null; }
  function effective(bucket) {
    var k = selKey(); if (!k) { return {}; }
    return Object.assign({}, (saved[bucket] && saved[bucket][k]) || {}, (dirty[bucket] && dirty[bucket][k]) || {});
  }
  function rememberStyle(prop, val) { var k = selKey(); if (!k) { return; } dirty.styles[k] = dirty.styles[k] || {}; dirty.styles[k][prop] = val; markDirty(); }
  function rememberFx(prop, val) {
    var k = selKey(); if (!k) { return; }
    dirty.fx[k] = dirty.fx[k] || {}; dirty.fx[k][prop] = val; markDirty();
    if (prop === "anim" && selectedEl) { ER.applyFx(selectedEl, effective("fx"), false, FD); }
  }
  function setStyle(prop, val) {
    if (!selectedEl) { return; }
    if (val === "") { selectedEl.style.removeProperty(prop); } else { selectedEl.style.setProperty(prop, val); }
    rememberStyle(prop, val);
  }
  function mergeDirtyIntoSaved() {
    ["texts", "images", "blocks", "order", "alts"].forEach(function (b) { saved[b] = saved[b] || {}; Object.keys(dirty[b]).forEach(function (k) { saved[b][k] = dirty[b][k]; }); });
    ["styles", "fx"].forEach(function (b) { saved[b] = saved[b] || {}; Object.keys(dirty[b]).forEach(function (k) { saved[b][k] = Object.assign({}, saved[b][k], dirty[b][k]); }); });
  }
  function onSelect(e) { if (insertMode || moveMode) { return; } selectEl(e.target); }
  function selectEl(el) {
    if (!el || el.nodeType !== 1 || ER.isSystem(el)) { return; }
    if (selectedEl) { selectedEl.classList.remove("ed-selected"); }
    selectedEl = el; selectedEl.classList.add("ed-selected");
    if (inspectorOpen) { fillInspector(); }
  }
  function rgbToHex(c) {
    var m = (c || "").match(/\d+/g); if (!m || m.length < 3) { return "#000000"; }
    return "#" + [m[0], m[1], m[2]].map(function (n) { return ("0" + parseInt(n, 10).toString(16)).slice(-2); }).join("");
  }
  function fillInspector() {
    if (!selectedEl) { inspTitle.textContent = "Clicca un elemento"; return; }
    var cs = selectedEl.ownerDocument.defaultView.getComputedStyle(selectedEl);
    var st = effective("styles");
    var cls = (typeof selectedEl.className === "string" ? selectedEl.className.split(" ").filter(function (c) { return c && c.indexOf("ed-") !== 0 && c.indexOf("fx") !== 0; })[0] : "");
    inspTitle.textContent = selectedEl.tagName.toLowerCase() + (cls ? "." + cls : "");
    $id("stColor").value = rgbToHex(st["color"] || cs.color);
    $id("stBg").value = rgbToHex(st["background-color"] || cs.backgroundColor);
    var rad = parseInt(st["border-radius"] || cs.borderTopLeftRadius, 10) || 0; $id("stRadius").value = rad; $id("stRadiusV").textContent = rad + "px";
    var pad = parseInt(st["padding"] || cs.paddingTop, 10) || 0; $id("stPad").value = pad; $id("stPadV").textContent = pad + "px";
    $id("stShadow").checked = !!(st["box-shadow"] && st["box-shadow"] !== "none");
    var fx = effective("fx");
    $id("fxAnim").value = fx.anim || "none";
    $id("fxSound").classList.toggle("on", !!fx.sound);
    $id("fxVideo").classList.toggle("on", !!fx.video);
  }
  function openInspector() {
    inspectorOpen = true;
    inspector.classList.add("show"); inspector.classList.remove("collapsed");
    document.body.classList.add("inspector-open"); document.body.classList.remove("insp-collapsed");
    btnStyle.classList.add("active");
    if (inspHandle) { inspHandle.textContent = "❯"; }
    if (!selectedEl && FD) { var f = FD.querySelector(".mission, .hero-copy, section .container > *, section"); if (f) { selectEl(f); } }
    fillInspector();
  }
  function closeInspector() {
    inspectorOpen = false;
    inspector.classList.remove("show", "collapsed");
    document.body.classList.remove("inspector-open", "insp-collapsed");
    btnStyle.classList.remove("active");
  }

  function makeGraphicEl(type) {
    var d;
    if (type === "text") { d = FD.createElement("p"); d.className = "gx-text"; d.textContent = "Nuovo testo. Clicca per modificarlo."; }
    else if (type === "heading") { d = FD.createElement("h2"); d.textContent = "Nuovo titolo"; }
    else if (type === "subheading") { d = FD.createElement("h3"); d.textContent = "Nuovo sottotitolo"; }
    else if (type === "divider") { d = FD.createElement("hr"); d.className = "gx-divider"; }
    else if (type === "spacer") { d = FD.createElement("div"); d.className = "gx-spacer"; }
    else if (type === "banner") { d = FD.createElement("div"); d.className = "gx-banner"; d.innerHTML = "<h3>Titolo del banner</h3><p>Sottotitolo o messaggio.</p>"; }
    else { d = FD.createElement("div"); d.className = "gx-box"; d.innerHTML = "<h3>Titolo del riquadro</h3><p>Testo del riquadro. Clicca per modificarlo.</p>"; }
    return d;
  }
  function insertGraphicAt(c, type) {
    var el = makeGraphicEl(type);
    c.parentNode.insertBefore(el, c.nextSibling);
    var container = findContainer(el) || el.parentNode;
    addRemoveBtn(el, container);
    var edMe = [];
    if (/^(H1|H2|H3|H4|H5|P|LI|BLOCKQUOTE)$/.test(el.tagName)) { edMe.push(el); }
    el.querySelectorAll("h1,h2,h3,h4,p,li").forEach(function (t) { edMe.push(t); });
    edMe.forEach(function (t) {
      t.setAttribute("contenteditable", "true"); t.classList.add("ed-editable");
      t.addEventListener("input", function () { refreshBlockDirty(container); });
    });
    refreshBlockDirty(container);
    exitInsertMode();
  }

  /* ---------- ELIMINA elemento (casella di testo, riquadro, immagine…) ---------- */
  function deleteSelected() {
    var el = selectedEl;
    if (!el || ER.isSystem(el) || el === FD.body) {
      window.alert("Prima clicca la casella di testo (o l'elemento) da eliminare: comparirà un bordo arancione.");
      return;
    }
    var txt = (el.textContent || "").replace(/\s+/g, " ").trim().slice(0, 70);
    if (!window.confirm("Eliminare questo elemento (" + el.tagName.toLowerCase() + ")?" + (txt ? "\n\n« " + txt + " »" : ""))) { return; }
    var container = findContainer(el) || el.parentElement;
    el.classList.remove("ed-selected");
    selectedEl = null;
    el.remove();
    if (container && !ER.isSystem(container) && container.tagName !== "BODY") { refreshBlockDirty(container); }
    else { markDirty(); }
    if (inspectorOpen) { fillInspector(); }
  }

  /* ---------- LIBRERIA SEZIONI (modelli pronti) ---------- */
  var SECTIONS = [
    { ico: "📛", name: "Intestazione sezione", desc: "Etichetta, titolo e sottotitolo centrati.",
      html: '<div class="section-head center mx-auto"><span class="eyebrow">Etichetta</span><h2>Titolo della sezione</h2><p class="lead">Sottotitolo descrittivo: spiega in poche parole di cosa si tratta.</p></div>' },
    { ico: "🎟️", name: "Fascia invito (CTA)", desc: "Banner colorato con titolo, testo e pulsanti.",
      html: '<div class="cta-band"><div class="cta-inner"><div><span class="eyebrow" style="background:rgba(255,255,255,.16);color:#fff">Invito</span><h2>Titolo dell\'invito</h2><p>Un breve messaggio che invita le famiglie a compiere un\'azione: prenotare, iscriversi, contattare la scuola.</p><div class="actions" style="margin-top:26px"><a href="#" class="btn btn--warm">Azione principale</a><a href="#" class="btn btn--light">Azione secondaria</a></div></div></div></div>' },
    { ico: "▤", name: "Due colonne", desc: "Testo a sinistra, contenuto a destra.",
      html: '<div class="split" style="align-items:center"><div><span class="eyebrow">Etichetta</span><h2>Titolo a due colonne</h2><p class="lead" style="margin:16px 0">Testo introduttivo della colonna di sinistra.</p><p style="color:#38465e">Descrivi qui il contenuto in modo più esteso.</p></div><div><h3>Colonna di destra</h3><p>Contenuto della seconda colonna: testo, un\'immagine o un video.</p></div></div>' },
    { ico: "🔳", name: "Tre riquadri", desc: "Griglia di tre box affiancati.",
      html: '<div class="grid grid-3"><div class="gx-box"><h3>Riquadro 1</h3><p>Testo del primo riquadro.</p></div><div class="gx-box"><h3>Riquadro 2</h3><p>Testo del secondo riquadro.</p></div><div class="gx-box"><h3>Riquadro 3</h3><p>Testo del terzo riquadro.</p></div></div>' },
    { ico: "❝", name: "Citazione", desc: "Frase in evidenza con autore.",
      html: '<blockquote class="gx-quote"><p>«La frase o citazione che vuoi mettere in evidenza.»</p><cite>— Autore della citazione</cite></blockquote>' }
  ];
  function insertSectionAt(c, html) {
    var wrap = FD.createElement("div");
    wrap.innerHTML = html;
    var el = wrap.firstElementChild;
    if (!el) { return; }
    c.parentNode.insertBefore(el, c.nextSibling);
    var container = findContainer(el) || el.parentNode;
    addRemoveBtn(el, container);
    el.querySelectorAll("h1,h2,h3,h4,h5,p,li,cite").forEach(function (t) {
      if (t.querySelector("h1,h2,h3,h4,h5,p,li")) { return; }
      t.setAttribute("contenteditable", "true"); t.classList.add("ed-editable");
      t.addEventListener("input", function () { refreshBlockDirty(container); });
    });
    refreshBlockDirty(container);
    exitInsertMode();
  }
  function buildSections() {
    if (!secGrid || secGrid.childElementCount) { return; }
    SECTIONS.forEach(function (s) {
      var card = document.createElement("button");
      card.type = "button"; card.className = "sec-card";
      card.innerHTML = '<div class="sec-ico">' + s.ico + '</div><h4>' + s.name + '</h4><p>' + s.desc + '</p>';
      card.addEventListener("click", function () { pickSection(s); });
      secGrid.appendChild(card);
    });
  }
  function openSections() { buildSections(); secModal.classList.add("open"); }
  function closeSections() { secModal.classList.remove("open"); }
  function pickSection(s) {
    closeSections();
    pendingSection = s.html;
    enterInsertMode();
    insHint.innerHTML = "🧱 Clicca il punto della pagina dove inserire la sezione «" + s.name + "» — premi <b>ESC</b> per annullare";
  }
  /* ---------- ANTEPRIMA RESPONSIVE ---------- */
  function setDevice(dev) {
    if (!frameWrap) { return; }
    frameWrap.classList.remove("dev-tablet", "dev-mobile");
    if (dev === "tablet") { frameWrap.classList.add("dev-tablet"); }
    else if (dev === "mobile") { frameWrap.classList.add("dev-mobile"); }
    if (edDevices) { edDevices.querySelectorAll(".dvbtn").forEach(function (b) { b.classList.toggle("active", b.dataset.dev === dev); }); }
  }

  if (btnStyle) {
    btnStyle.addEventListener("click", function () { if (inspectorOpen) { closeInspector(); } else { openInspector(); } });
    inspClose.addEventListener("click", closeInspector);
    if (inspHandle) {
      inspHandle.addEventListener("click", function () {
        var collapsed = inspector.classList.toggle("collapsed");
        document.body.classList.toggle("insp-collapsed", collapsed);
        inspHandle.textContent = collapsed ? "❮" : "❯";
      });
    }
    inspUp.addEventListener("click", function () {
      if (selectedEl && selectedEl.parentElement && selectedEl.parentElement.tagName !== "BODY" && !ER.isSystem(selectedEl.parentElement)) { selectEl(selectedEl.parentElement); }
    });
    $id("stColor").addEventListener("input", function () { setStyle("color", this.value); });
    $id("stColorClear").addEventListener("click", function () { setStyle("color", ""); fillInspector(); });
    $id("stBg").addEventListener("input", function () { setStyle("background-color", this.value); });
    $id("stBgClear").addEventListener("click", function () { setStyle("background-color", ""); fillInspector(); });
    $id("stBgImg").addEventListener("click", function () { if (selectedEl) { openMedia("bg", selectedEl); } });
    $id("stBgImgClear").addEventListener("click", function () { setStyle("background-image", ""); setStyle("background-size", ""); setStyle("background-position", ""); });
    $id("stRadius").addEventListener("input", function () { setStyle("border-radius", this.value + "px"); $id("stRadiusV").textContent = this.value + "px"; });
    $id("stPad").addEventListener("input", function () { setStyle("padding", this.value + "px"); $id("stPadV").textContent = this.value + "px"; });
    $id("stShadow").addEventListener("change", function () { setStyle("box-shadow", this.checked ? "0 18px 50px rgba(15,28,51,.16)" : ""); });
    $id("stAlign").addEventListener("click", function (e) { var b = e.target.closest("button[data-al]"); if (b) { setStyle("text-align", b.dataset.al); } });
    $id("fxAnim").addEventListener("change", function () { rememberFx("anim", this.value); });
    $id("fxSound").addEventListener("click", function () { if (selectedEl) { openMedia("sound", selectedEl); } });
    $id("fxSoundClear").addEventListener("click", function () { rememberFx("sound", ""); fillInspector(); });
    $id("fxVideo").addEventListener("click", function () { if (selectedEl) { openMedia("video", selectedEl); } });
    $id("fxVideoClear").addEventListener("click", function () { rememberFx("video", ""); fillInspector(); });
  }

  frame.addEventListener("load", init);
})();
