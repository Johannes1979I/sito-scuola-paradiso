/* =============================================================
   Scuola Santa Maria del Paradiso — Layout condiviso
   Inietta header, footer e pulsante chat in ogni pagina + logica UI.
   (Nessun fetch: funziona anche aprendo i file con doppio-click.)
   ============================================================= */
(function () {
  "use strict";

  /* ----------------------------------------------------------
     CONFIGURAZIONE — modifica qui i dati una volta sola
     ---------------------------------------------------------- */
  var CFG = {
    nome: "Santa Maria del Paradiso",
    sottotitolo: "Scuola Paritaria · Viterbo",
    logo: "assets/img/logo.png",
    indirizzo: "Via del Paradiso, 22 — 01100 Viterbo (VT)",
    tel: "0761 308770",
    telRaw: "+390761308770",
    cell: "340 5416568",
    cellRaw: "+393405416568",
    email: "segreteria@scuolasantamariadelparadiso.it",
    pec: "spsmparadiso@inviapec.it",
    orari: "Lun–Ven · 7:45–10:00 / 12:00–13:30",
    // Canale chat PROVVISORIO (da decidere: WhatsApp Business o altro)
    whatsapp: "393405416568",
    whatsappMsg: "Salve, vorrei alcune informazioni sulla scuola.",
    // Social ufficiali della scuola
    facebook: "https://www.facebook.com/scuolasantamariadelparadisoviterbo/",
    instagram: "https://www.instagram.com/scuolasantamariadelparadiso/",
    youtube: "",
    // Analytics & cookie (impostabili dal pannello Avanzate)
    ga: "",
    cookieText: "",
    // Moduli — invio email (impostabili dal pannello Avanzate).
    // formEndpoint = URL del servizio (Formspree "https://formspree.io/f/xxx" o Web3Forms
    // "https://api.web3forms.com/submit"); formKey = access_key (solo Web3Forms);
    // formEmail = indirizzo per il fallback mailto se non c'è endpoint.
    formEndpoint: "",
    formKey: "",
    formEmail: "",
    // Claim "Scuola e Famiglie unite"
    claim: "Scuola e Famiglie, unite per crescere insieme."
  };

  var NAV = [
    { page: "home",     label: "Home",             href: "index.html" },
    { page: "chi",      label: "Chi siamo",        href: "chi-siamo.html", children: [
      { label: "La nostra identità", href: "chi-siamo.html" },
      { label: "La nostra mission",  href: "chi-siamo.html#mission" },
      { label: "Corpo docenti",      href: "docenti.html" },
      { label: "I nostri eroi",      href: "eroi.html" }
    ] },
    { page: "offerta",  label: "Offerta formativa",href: "offerta-formativa.html", children: [
      { label: "Scuola dell'Infanzia",   href: "offerta-formativa.html#infanzia" },
      { label: "Scuola Primaria",        href: "offerta-formativa.html#primaria" },
      { label: "Secondaria di I grado",  href: "offerta-formativa.html#secondaria" },
      { label: "PTOF",                   href: "ptof.html" },
      { label: "Erasmus",                href: "erasmus.html" }
    ] },
    { page: "openday",  label: "Open Day",         href: "open-day.html" },
    { page: "vita",     label: "Vita scolastica",  href: "vita-scolastica.html", children: [
      { label: "Calendario e servizi",   href: "vita-scolastica.html" },
      { label: "Il Giornale della scuola",href: "vita-scolastica.html#giornale" },
      { label: "Radio SMP",              href: "vita-scolastica.html#radio" },
      { label: "Concorsi",               href: "concorsi.html" }
    ] },
    { page: "galleria", label: "Galleria",         href: "galleria.html" },
    { page: "teatro",   label: "Teatro",           href: "teatro.html" },
    { page: "genitori", label: "Scuola e Genitori",href: "genitori.html" },
    { page: "sostieni", label: "Sostienici",       href: "sostienici.html" },
    { page: "news",     label: "News",             href: "news.html" },
    { page: "contatti", label: "Contatti",         href: "contatti.html" }
  ];

  var current = document.body.getAttribute("data-page") || "home";

  /* ----------------------------------------------------------
     ICONE SVG
     ---------------------------------------------------------- */
  var I = {
    pin:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>',
    tel:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>',
    wa:   '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M.05 24l1.69-6.16a11.87 11.87 0 0 1-1.59-5.95C.16 5.34 5.5 0 12.06 0a11.82 11.82 0 0 1 8.41 3.49 11.82 11.82 0 0 1 3.48 8.41c0 6.56-5.34 11.9-11.9 11.9a11.9 11.9 0 0 1-5.69-1.45L.05 24zm6.6-3.8c1.68.99 3.28 1.59 5.4 1.59 5.45 0 9.89-4.43 9.89-9.88a9.83 9.83 0 0 0-9.88-9.89C6.6 1.99 2.16 6.42 2.16 11.87c0 2.23.65 3.9 1.74 5.65l-1 3.66 3.75-.98zm11.39-5.55c-.07-.12-.27-.2-.57-.35-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51-.17-.01-.37-.01-.57-.01-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.49 0 1.47 1.07 2.89 1.22 3.09.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.62.71.23 1.36.2 1.87.12.57-.08 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42z"/></svg>',
    chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg>',
    clock:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    fb:   '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z"/></svg>',
    ig:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>',
    yt:   '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23 12s0-3.2-.4-4.7a2.5 2.5 0 0 0-1.77-1.77C19.34 5.13 12 5.13 12 5.13s-7.34 0-8.83.4A2.5 2.5 0 0 0 1.4 7.3C1 8.8 1 12 1 12s0 3.2.4 4.7a2.5 2.5 0 0 0 1.77 1.77c1.49.4 8.83.4 8.83.4s7.34 0 8.83-.4a2.5 2.5 0 0 0 1.77-1.77C23 15.2 23 12 23 12ZM9.75 15.02V8.98L15.5 12l-5.75 3.02Z"/></svg>'
  };

  /* ----------------------------------------------------------
     HEADER
     ---------------------------------------------------------- */
  function navList() {
    return NAV.map(function (n) {
      var act = n.page === current ? ' class="active"' : "";
      if (n.children && n.children.length) {
        var sub = n.children.map(function (c) {
          return '<li><a href="' + c.href + '">' + c.label + "</a></li>";
        }).join("");
        return '<li class="has-sub"><a href="' + n.href + '"' + act + ">" + n.label + "</a>" +
               '<ul class="submenu">' + sub + "</ul></li>";
      }
      return '<li><a href="' + n.href + '"' + act + ">" + n.label + "</a></li>";
    }).join("");
  }
  var headerHTML =
    '<a class="skip-link" href="#contenuto-principale">Salta al contenuto</a>' +
    '<header class="header" id="header"><div class="container"><nav class="nav" aria-label="Navigazione principale">' +
      '<a href="index.html" class="brand">' +
        '<img src="' + CFG.logo + '" alt="Logo ' + CFG.nome + '">' +
        '<span class="brand-txt"><strong>' + CFG.nome + "</strong><span>" + CFG.sottotitolo + "</span></span>" +
      "</a>" +
      '<ul class="nav-links" id="navLinks">' + navList() +
        '<li><a href="iscrizioni.html" class="btn btn--primary">Iscriviti</a></li>' +
      "</ul>" +
      '<div class="nav-cta">' +
        '<a href="iscrizioni.html" class="btn btn--primary">Iscriviti</a>' +
        '<button class="burger" id="burger" aria-label="Apri menu" aria-expanded="false" aria-controls="navLinks"><span></span></button>' +
      "</div>" +
    "</nav></div></header>";

  /* ----------------------------------------------------------
     FOOTER
     ---------------------------------------------------------- */
  var socials = [
    { u: CFG.facebook, i: I.fb, n: "Facebook" },
    { u: CFG.instagram, i: I.ig, n: "Instagram" },
    { u: CFG.youtube, i: I.yt, n: "YouTube" }
  ].filter(function (s) { return s.u; });
  var socialHTML = socials.length
    ? '<div class="footer-social">' + socials.map(function (s) {
        return '<a href="' + s.u + '" target="_blank" rel="noopener" aria-label="' + s.n + '">' + s.i + "</a>";
      }).join("") + "</div>"
    : "";

  var footerHTML =
    '<footer class="footer"><div class="container"><div class="footer-grid">' +
      '<div><div class="footer-brand"><img src="' + CFG.logo + '" alt="Logo"><strong>' + CFG.nome + "</strong></div>" +
        "<p>Scuola Paritaria a Viterbo. Un cammino educativo dall'Infanzia alla Secondaria di I grado, con al centro la persona, i valori e la gioia di crescere insieme.</p>" +
        '<p class="footer-claim">' + CFG.claim + "</p>" + socialHTML + "</div>" +
      '<div class="footer-col"><h4>Scuola</h4><ul class="footer-links" id="footScuola">' +
        '<li><a href="chi-siamo.html">Chi siamo</a></li>' +
        '<li><a href="offerta-formativa.html">Offerta formativa</a></li>' +
        '<li><a href="open-day.html">Open Day</a></li>' +
        '<li><a href="iscrizioni.html">Come iscriversi</a></li>' +
        '<li><a href="docenti.html">Corpo docenti</a></li>' +
        '<li><a href="vita-scolastica.html">Vita scolastica</a></li>' +
        '<li><a href="teatro.html">Teatro di fine anno</a></li>' +
        '<li><a href="genitori.html">Scuola e Genitori</a></li>' +
        '<li><a href="erasmus.html">Erasmus</a></li>' +
        '<li><a href="galleria.html">Galleria foto</a></li>' +
        '<li><a href="sostienici.html">Sostienici</a></li>' +
        '<li><a href="lavora-con-noi.html">Lavora con noi</a></li>' +
        '<li><a href="news.html">News ed eventi</a></li></ul></div>' +
      '<div class="footer-col"><h4>Area documenti</h4><ul class="footer-links">' +
        '<li><a href="modulistica.html">Modulistica</a></li>' +
        '<li><a href="modulistica.html#richieste">Richieste documentali</a></li>' +
        '<li><a href="albo.html">Albo</a></li>' +
        '<li><a href="amministrazione-trasparente.html">Amministrazione Trasparente</a></li>' +
        '<li><a href="privacy.html">Privacy Policy</a></li></ul></div>' +
      '<div class="footer-col"><h4>Contatti</h4><ul class="footer-contact">' +
        "<li>" + I.pin + " " + CFG.indirizzo + "</li>" +
        "<li>" + I.tel + ' <a href="tel:' + CFG.telRaw + '">' + CFG.tel + "</a></li>" +
        "<li>" + I.mail + ' <a href="mailto:' + CFG.email + '">' + CFG.email + "</a></li></ul></div>" +
    '</div><div class="footer-bottom">' +
      '<span>© 2026 Scuola Paritaria ' + CFG.nome + ' · Viterbo<span class="site-hits" id="siteHits"> · Visite: …</span></span>' +
      '<span class="footer-meta"><a href="privacy.html">Privacy Policy</a> · <a href="contatti.html">Contatti</a></span>' +
    "</div></div></footer>";

  /* ----------------------------------------------------------
     PULSANTE CHAT
     ---------------------------------------------------------- */
  var waHref = "https://wa.me/" + CFG.whatsapp + "?text=" + encodeURIComponent(CFG.whatsappMsg);
  var chatHTML =
    '<button class="chat-fab" id="chatFab" aria-label="Chatta con la scuola" aria-expanded="false">' +
      '<span class="pulse"></span>' + I.chat + '<span class="lbl-mobile">Chatta con la scuola</span>' +
    "</button>" +
    '<div class="chat-panel" id="chatPanel" role="dialog" aria-label="Contatti rapidi">' +
      '<div class="head"><strong>Chatta con la scuola</strong><span>Siamo qui per aiutarti, scegli come scriverci</span></div>' +
      '<div class="body">' +
        '<a class="chat-opt wa" href="' + waHref + '" target="_blank" rel="noopener">' +
          '<span class="ico">' + I.wa + '</span><span><strong>WhatsApp</strong><span>Scrivici un messaggio</span></span></a>' +
        '<a class="chat-opt tel" href="tel:' + CFG.telRaw + '">' +
          '<span class="ico">' + I.tel + '</span><span><strong>Chiama la segreteria</strong><span>' + CFG.tel + "</span></span></a>" +
        '<a class="chat-opt mail" href="mailto:' + CFG.email + '">' +
          '<span class="ico">' + I.mail + '</span><span><strong>Email</strong><span>Ti rispondiamo al più presto</span></span></a>' +
      "</div>" +
      '<div class="foot">Canale di messaggistica in fase di attivazione</div>' +
    "</div>";

  /* ----------------------------------------------------------
     LOGO FISSO — badge sempre visibile (in tutte le pagine)
     ---------------------------------------------------------- */
  var logoBadgeHTML =
    '<a class="logo-badge" href="index.html" aria-label="' + CFG.nome + ' — Home" title="' + CFG.nome + '">' +
      '<img src="assets/img/logo.png" alt="Logo ' + CFG.nome + '">' +
    "</a>";

  // Banner cookie (solo cookie tecnici)
  var cookieHTML =
    '<div class="cookie-bar" id="cookieBar" role="dialog" aria-label="Informativa cookie" hidden>' +
      "<p>Questo sito usa solo <strong>cookie tecnici</strong> necessari al funzionamento. " +
      'Per saperne di più leggi la <a href="privacy.html">Privacy &amp; Cookie Policy</a>.</p>' +
      '<button type="button" class="btn btn--primary" id="cookieOk">Ho capito</button>' +
    "</div>";

  /* ----------------------------------------------------------
     INIEZIONE NEL DOM
     ---------------------------------------------------------- */
  document.body.insertAdjacentHTML("afterbegin", headerHTML);
  document.body.insertAdjacentHTML("beforeend", footerHTML + chatHTML + logoBadgeHTML + cookieHTML);
  // Bersaglio dello skip-link (accessibilità): un ancoraggio focalizzabile subito dopo l'header
  (function () { var hd = document.getElementById("header"); if (hd && !document.getElementById("contenuto-principale")) { hd.insertAdjacentHTML("afterend", '<span id="contenuto-principale" tabindex="-1"></span>'); } })();

  // Footer a fisarmonica: su mobile gli elenchi si aprono/chiudono cliccando il titolo della colonna
  Array.prototype.forEach.call(document.querySelectorAll(".footer-col h4"), function (h) {
    h.addEventListener("click", function () { h.parentNode.classList.toggle("open"); });
  });

  // Banner cookie: visibile finché non si accetta (ricordato in localStorage)
  (function () {
    var bar = document.getElementById("cookieBar");
    if (!bar) { return; }
    var seen = false;
    try { seen = !!localStorage.getItem("cookieOk"); } catch (e) {}
    if (!seen) { bar.hidden = false; }
    var ok = document.getElementById("cookieOk");
    if (ok) {
      ok.addEventListener("click", function () {
        bar.hidden = true;
        try { localStorage.setItem("cookieOk", "1"); } catch (e) {}
      });
    }
  })();

  // Contatore visite anonimo (counterapi.dev) — l'elemento è già visibile ("Visite: …"),
  // qui sostituiamo i puntini con il numero reale appena disponibile.
  (function () {
    var el = document.getElementById("siteHits");
    if (!el) { return; }
    fetch("https://api.counterapi.dev/v1/scuolasmparadiso/sito/up")
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (d && typeof d.count === "number") {
          el.textContent = " · Visite: " + d.count.toLocaleString("it-IT");
        }
      })
      .catch(function () {});
  })();


  /* ----------------------------------------------------------
     INTERAZIONI
     ---------------------------------------------------------- */
  // Menu mobile
  var burger = document.getElementById("burger");
  var navLinks = document.getElementById("navLinks");
  if (burger && navLinks) {
    burger.addEventListener("click", function () {
      var open = navLinks.classList.toggle("open");
      burger.classList.toggle("open", open);
      burger.setAttribute("aria-expanded", open ? "true" : "false");
      burger.setAttribute("aria-label", open ? "Chiudi menu" : "Apri menu");
    });
    navLinks.addEventListener("click", function (e) {
      if (e.target.closest("a")) {
        navLinks.classList.remove("open");
        burger.classList.remove("open");
        burger.setAttribute("aria-expanded", "false");
      }
    });
  }

  // Header: ombra allo scroll
  var header = document.getElementById("header");
  var onScroll = function () { header.classList.toggle("scrolled", window.scrollY > 8); };
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });

  // Pulsante chat
  var fab = document.getElementById("chatFab");
  var panel = document.getElementById("chatPanel");
  if (fab && panel) {
    var toggleChat = function (force) {
      var open = typeof force === "boolean" ? force : !panel.classList.contains("open");
      panel.classList.toggle("open", open);
      fab.setAttribute("aria-expanded", open ? "true" : "false");
    };
    fab.addEventListener("click", function (e) { e.stopPropagation(); toggleChat(); });
    document.addEventListener("click", function (e) {
      if (panel.classList.contains("open") && !panel.contains(e.target) && e.target !== fab) toggleChat(false);
    });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") toggleChat(false); });
  }

  // Reveal on scroll
  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && reveals.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); }
      });
    }, { threshold: 0, rootMargin: "0px 0px -80px 0px" });
    reveals.forEach(function (el) { io.observe(el); });
    // rete di sicurezza: se per qualunque motivo qualcosa non viene rivelato, dopo 2,5s mostra tutto
    setTimeout(function () { reveals.forEach(function (el) { el.classList.add("in"); }); }, 2500);
  } else {
    reveals.forEach(function (el) { el.classList.add("in"); });
  }

  // Moduli funzionanti (contatti / pre-iscrizione / Open Day): invio via endpoint
  // configurato (Formspree/Web3Forms) o fallback mailto. Ogni <form data-form="Oggetto">.
  (function () {
    if (new URLSearchParams(location.search).get("edit") === "1") { return; }
    function noteEl(form) {
      var n = form.querySelector("[data-form-note]");
      if (!n) { n = document.createElement("p"); n.setAttribute("data-form-note", ""); form.appendChild(n); }
      return n;
    }
    function say(form, ok, msg) {
      var n = noteEl(form);
      n.hidden = false;
      n.className = "form-note " + (ok ? "form-note--ok" : "form-note--err");
      n.textContent = msg;
      n.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    function attach(form) {
      form.addEventListener("submit", function (ev) {
        ev.preventDefault();
        if (form.checkValidity && !form.checkValidity()) { form.reportValidity(); return; }
        var subject = form.getAttribute("data-form") || "Messaggio dal sito";
        var endpoint = (CFG.formEndpoint || "").trim();
        var btn = form.querySelector("[type=submit]");
        if (endpoint) {
          var fd = new FormData(form);
          if (CFG.formKey) { fd.append("access_key", CFG.formKey); }
          fd.append("subject", subject + " — " + CFG.nome);
          fd.append("pagina", location.href);
          var old = btn ? btn.textContent : "";
          if (btn) { btn.disabled = true; btn.textContent = "Invio in corso…"; }
          fetch(endpoint, { method: "POST", headers: { "Accept": "application/json" }, body: fd })
            .then(function (r) { return r.ok; })
            .then(function (ok) {
              if (ok) { form.reset(); say(form, true, "✅ Messaggio inviato! Ti risponderemo al più presto."); }
              else { say(form, false, "⚠️ Invio non riuscito. Riprova, oppure scrivici a " + (CFG.email || "") + "."); }
            })
            .catch(function () { say(form, false, "⚠️ Errore di rete: controlla la connessione e riprova."); })
            .then(function () { if (btn) { btn.disabled = false; btn.textContent = old; } });
        } else {
          var to = (CFG.formEmail || CFG.email || "").trim();
          var lines = [];
          Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.type === "submit" || el.type === "hidden") { return; }
            if (el.type === "checkbox" && !el.checked) { return; }
            var lab = (el.labels && el.labels[0]) ? el.labels[0].textContent.trim().replace(/\s+/g, " ") : el.name;
            lines.push(lab + ": " + (el.type === "checkbox" ? "sì" : el.value));
          });
          var href = "mailto:" + to + "?subject=" + encodeURIComponent(subject) + "&body=" + encodeURIComponent(lines.join("\n"));
          window.location.href = href;
          say(form, true, "📧 Si aprirà il tuo programma di posta col messaggio già pronto: premi Invia. (Per l'invio automatico, configura un servizio dal pannello Avanzate.)");
        }
      });
    }
    document.querySelectorAll("form[data-form]").forEach(attach);
  })();

  // Avvisi/News dinamici: popola #news-feed da news.json (salta in editor)
  (function () {
    if (new URLSearchParams(location.search).get("edit") === "1") { return; }
    var host = document.getElementById("news-feed");
    if (!host) { return; }
    var grid = host.querySelector("[data-news-grid]") || host;
    var months = ["gennaio", "febbraio", "marzo", "aprile", "maggio", "giugno", "luglio", "agosto", "settembre", "ottobre", "novembre", "dicembre"];
    function fmt(d) { var t = d ? new Date(d) : null; return (t && !isNaN(t.getTime())) ? (t.getDate() + " " + months[t.getMonth()] + " " + t.getFullYear()) : (d || ""); }
    function esc(s) { return String(s || "").replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;"); }
    fetch("news.json", { cache: "no-store" })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (items) {
        items = (items || []).filter(function (n) { return n && n.published; });
        items.sort(function (a, b) { return String(b.date || "").localeCompare(String(a.date || "")); });
        var limit = parseInt(host.getAttribute("data-limit"), 10) || 0;
        if (limit > 0) { items = items.slice(0, limit); }
        if (!items.length) { host.hidden = true; return; }
        host.hidden = false;
        grid.innerHTML = items.map(function (n) {
          var media = n.image ? '<div class="ni-media"><img src="' + esc(n.image) + '" alt="' + esc(n.title) + '"></div>' : "";
          return '<article class="news-item">' + media +
            '<div class="ni-body"><span class="date">' + fmt(n.date) + '</span><h3>' + (n.title || "") + '</h3>' +
            '<div class="ni-text">' + (n.body || "") + '</div></div></article>';
        }).join("");
      })
      .catch(function () { host.hidden = true; });
  })();

  // Documenti dinamici: popola [data-doc-list] da documents.json (aprono nel modale .lm-*)
  (function () {
    if (new URLSearchParams(location.search).get("edit") === "1") { return; }
    var hosts = document.querySelectorAll("[data-doc-list]");
    if (!hosts.length) { return; }
    function esc(s) { return String(s || "").replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;"); }
    fetch("documents.json", { cache: "no-store" })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (all) {
        all = (all || []).filter(function (d) { return d && d.published && d.url; });
        Array.prototype.forEach.call(hosts, function (host) {
          var cats = (host.getAttribute("data-doc-category") || "").split(",").map(function (s) { return s.trim(); }).filter(Boolean);
          var items = cats.length ? all.filter(function (d) { return cats.indexOf(d.category || "") >= 0; }) : all.slice();
          var grid = host.querySelector("[data-doc-grid]") || host;
          if (!items.length) { host.hidden = true; return; }
          host.hidden = false;
          var FICO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>';
          var GO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
          grid.innerHTML = items.map(function (d) {
            return '<a class="doc-item" href="' + esc(d.url) + '" data-modal data-modal-title="' + esc(d.title) + '" data-modal-embed="' + esc(d.url) + '" data-modal-download="' + esc(d.url) + '" target="_blank" rel="noopener">' +
              '<span class="fico">' + FICO + '</span>' +
              '<span class="meta"><strong>' + (d.title || "") + '</strong>' + (d.category ? '<span>' + esc(d.category) + '</span>' : '') + '</span>' +
              '<span class="go">Apri ' + GO + '</span></a>';
          }).join("");
        });
      })
      .catch(function () { Array.prototype.forEach.call(hosts, function (h) { h.hidden = true; }); });
  })();

  // Eventi/agenda dinamici: popola [data-events] da events.json (solo i prossimi, salvo data-show-past)
  (function () {
    if (new URLSearchParams(location.search).get("edit") === "1") { return; }
    var host = document.querySelector("[data-events]");
    if (!host) { return; }
    var months = ["gennaio", "febbraio", "marzo", "aprile", "maggio", "giugno", "luglio", "agosto", "settembre", "ottobre", "novembre", "dicembre"];
    function esc(s) { return String(s || "").replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;"); }
    var todayStr = new Date().toISOString().slice(0, 10);
    fetch("events.json", { cache: "no-store" })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (all) {
        all = (all || []).filter(function (e) { return e && e.published && e.date; });
        if (!host.hasAttribute("data-show-past")) { all = all.filter(function (e) { return String(e.date) >= todayStr; }); }
        all.sort(function (a, b) { return String(a.date).localeCompare(String(b.date)); });
        var limit = parseInt(host.getAttribute("data-limit"), 10) || 0;
        if (limit > 0) { all = all.slice(0, limit); }
        var grid = host.querySelector("[data-events-grid]") || host;
        if (!all.length) { host.hidden = true; return; }
        host.hidden = false;
        grid.innerHTML = all.map(function (e) {
          var t = new Date(e.date); var ok = !isNaN(t.getTime());
          var day = ok ? t.getDate() : ""; var mon = ok ? months[t.getMonth()].slice(0, 3) : "";
          return '<div class="ev-item"><div class="ev-date"><span class="ev-d">' + day + '</span><span class="ev-m">' + mon + '</span></div>' +
            '<div class="ev-body"><h3>' + (e.title || "") + '</h3>' +
            '<div class="ev-meta">' + (e.time ? '🕒 ' + esc(e.time) + '  ' : '') + (e.place ? '📍 ' + esc(e.place) : '') + '</div>' +
            (e.description ? '<p>' + (e.description) + '</p>' : '') + '</div></div>';
        }).join("");
      })
      .catch(function () { host.hidden = true; });
  })();

  // PWA: registra il service worker (offline + installabile). Salta in editor e su file://
  (function () {
    if (new URLSearchParams(location.search).get("edit") === "1") { return; }
    if (location.protocol === "file:") { return; }
    if ("serviceWorker" in navigator) {
      window.addEventListener("load", function () {
        navigator.serviceWorker.register("sw.js").catch(function () {});
      });
    }
  })();

  /* --- Runtime di editing condiviso (usato dal sito e dal pannello editor) --- */
  window.EditRuntime = {
    domKey: function (el) {
      var parts = [];
      while (el && el.nodeType === 1 && el.tagName !== "BODY") {
        var tag = el.tagName, i = 1, s = el;
        while ((s = s.previousElementSibling)) { if (s.tagName === tag) { i++; } }
        parts.unshift(tag + ":" + i);
        el = el.parentElement;
      }
      return parts.join(">");
    },
    resolveKey: function (key, doc) {
      doc = doc || document;
      var node = doc.body;
      if (!node || !key) { return null; }
      var parts = key.split(">");
      for (var p = 0; p < parts.length; p++) {
        var seg = parts[p].split(":"), tag = seg[0], idx = parseInt(seg[1], 10),
            count = 0, child = node.firstElementChild, found = null;
        while (child) {
          if (child.tagName === tag) { if (++count === idx) { found = child; break; } }
          child = child.nextElementSibling;
        }
        if (!found) { return null; }
        node = found;
      }
      return node;
    },
    isSystem: function (el) {
      return !!(el.closest && el.closest(".header,.footer,.chat-fab,.chat-panel,.logo-badge,#paradiso-intro,#logo-cursor,#page-transition"));
    },
    applyOverrides: function (doc, pd, live) {
      pd = pd || {};
      var self = this;
      // Ordine dei figli (drag&drop): si applica PER PRIMO, così tutti i domKey successivi
      // (blocchi/testi/immagini/stili/effetti) restano coerenti con il nuovo ordine.
      Object.keys(pd.order || {}).forEach(function (k) {
        var el = self.resolveKey(k, doc); if (!el) { return; }
        var kids = Array.prototype.slice.call(el.children);
        (pd.order[k] || []).forEach(function (oi) { var c = kids[oi]; if (c) { el.appendChild(c); } });
      });
      Object.keys(pd.blocks || {}).forEach(function (k) { var el = self.resolveKey(k, doc); if (el) { el.innerHTML = pd.blocks[k]; } });
      Object.keys(pd.images || {}).forEach(function (k) { var el = self.resolveKey(k, doc); if (el && el.tagName === "IMG") { el.setAttribute("src", pd.images[k]); } });
      Object.keys(pd.alts || {}).forEach(function (k) { var el = self.resolveKey(k, doc); if (el && el.tagName === "IMG") { el.setAttribute("alt", pd.alts[k]); } });
      Object.keys(pd.texts || {}).forEach(function (k) { var el = self.resolveKey(k, doc); if (el) { el.innerHTML = pd.texts[k]; } });
      Object.keys(pd.styles || {}).forEach(function (k) {
        var el = self.resolveKey(k, doc); if (!el) { return; }
        var s = pd.styles[k]; Object.keys(s).forEach(function (p) { try { el.style.setProperty(p, s[p]); } catch (e) {} });
      });
      Object.keys(pd.fx || {}).forEach(function (k) { var el = self.resolveKey(k, doc); if (el) { self.applyFx(el, pd.fx[k], live, doc); } });
    },
    applyFx: function (el, fx, live, doc) {
      fx = fx || {};
      el.classList.add("fx");
      ["lift", "zoom", "tilt", "glow", "bright", "pulse", "shake"].forEach(function (a) { el.classList.remove("fx-" + a); });
      if (fx.anim && fx.anim !== "none") { el.classList.add("fx-" + fx.anim); }
      if (!live) { return; }                 // nell'editor: solo l'animazione, niente suono/video
      doc = doc || document;
      if (fx.sound) {
        el.addEventListener("mouseenter", function () {
          var now = Date.now();
          if (el.__sndAt && now - el.__sndAt < 1200) { return; }
          el.__sndAt = now;
          try { var a = new Audio(fx.sound); a.volume = 0.9; var p = a.play(); if (p && p.catch) { p.catch(function () {}); } } catch (e) {}
        });
      }
      if (fx.video) {
        el.addEventListener("mouseenter", function () {
          if (doc.querySelector(".fx-vid-overlay")) { return; }
          var ov = doc.createElement("div");
          ov.className = "fx-vid-overlay";
          ov.innerHTML = '<video src="' + fx.video + '" autoplay controls playsinline></video>';
          ov.addEventListener("click", function (e) { if (e.target === ov) { ov.remove(); } });
          doc.body.appendChild(ov);
        });
      }
    }
  };

  /* --- Applica gli override salvati dal pannello (salta in modalità editor ?edit=1) --- */
  (function () {
    if (location.protocol === "file:") { return; }
    if (new URLSearchParams(location.search).get("edit") === "1") { return; }
    var page = location.pathname.split("/").pop() || "index.html";
    fetch("content.json", { cache: "no-store" })
      .then(function (r) { return r.ok ? r.json() : {}; })
      .then(function (all) { window.EditRuntime.applyOverrides(document, (all && all[page]) || {}, true); })
      .catch(function () {});
  })();

  /* --- Cookie consent + Google Analytics (caricato SOLO dopo consenso — GDPR) --- */
  (function () {
    if (new URLSearchParams(location.search).get("edit") === "1") { return; }
    var GA = (CFG.ga || "").trim();
    if (!GA) { return; }                 // nessuna analitica configurata → nessun banner (solo cookie tecnici)
    function loadGA() {
      var s = document.createElement("script"); s.async = true;
      s.src = "https://www.googletagmanager.com/gtag/js?id=" + GA;
      document.head.appendChild(s);
      window.dataLayer = window.dataLayer || [];
      function gtag() { window.dataLayer.push(arguments); }
      window.gtag = gtag;
      gtag("js", new Date());
      gtag("config", GA, { anonymize_ip: true });
    }
    var consent = null; try { consent = localStorage.getItem("cookie-consent"); } catch (e) {}
    if (consent === "yes") { loadGA(); return; }
    if (consent === "no") { return; }
    function set(v) { try { localStorage.setItem("cookie-consent", v); } catch (e) {} }
    function build() {
      var b = document.createElement("div");
      b.className = "cookie-banner";
      var txt = CFG.cookieText || "Usiamo cookie tecnici necessari e, con il tuo consenso, cookie di statistica (Google Analytics) per migliorare il sito.";
      b.innerHTML = "<p>" + txt + ' <a href="privacy.html">Informativa</a></p>' +
        '<div class="ck-btns"><button class="ck-no" type="button">Solo necessari</button>' +
        '<button class="ck-yes" type="button">Accetta</button></div>';
      document.body.appendChild(b);
      setTimeout(function () { b.classList.add("show"); }, 30);
      b.querySelector(".ck-yes").addEventListener("click", function () { set("yes"); b.remove(); loadGA(); });
      b.querySelector(".ck-no").addEventListener("click", function () { set("no"); b.remove(); });
    }
    if (document.body) { build(); } else { document.addEventListener("DOMContentLoaded", build); }
  })();

  /* --- Modale/lightbox uniforme del sito: apri documenti/immagini DENTRO il sito via [data-modal] --- */
  (function () {
    if (new URLSearchParams(location.search).get("edit") === "1") { return; }
    var cur = null;
    function onKey(e) { if (e.key === "Escape") { kill(); } }
    function kill() {
      if (!cur) { return; }
      var el = cur; cur = null;
      el.classList.remove("show");
      document.body.classList.remove("lm-open");
      document.removeEventListener("keydown", onKey);
      setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 260);
    }
    function build(o) {
      kill();
      Array.prototype.forEach.call(document.querySelectorAll(".lm-backdrop"), function (b) { if (b.parentNode) { b.parentNode.removeChild(b); } });
      cur = null;
      var media = "";
      if (o.embed) { media = '<div class="lm-frame"><iframe src="' + o.embed + '" loading="lazy" allow="autoplay; fullscreen; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>'; }
      else if (o.image) { media = '<div class="lm-frame lm-frame--img"><img src="' + o.image + '" alt=""></div>'; }
      else if (o.content) { var src = document.querySelector(o.content); media = '<div class="lm-rich">' + (src ? src.innerHTML : '<p>Contenuto non disponibile.</p>') + '</div>'; }
      var m = document.createElement("div");
      m.className = "lm-backdrop";
      m.innerHTML =
        '<div class="lm-card" role="dialog" aria-modal="true">' +
          '<div class="lm-head"><strong>' + (o.title || "") + '</strong>' +
          '<button class="lm-x" type="button" aria-label="Chiudi">✕</button></div>' +
          (o.note ? '<p class="lm-note">' + o.note + '</p>' : '') +
          media +
          (o.download ? '<div class="lm-foot"><a class="btn btn--primary" href="' + o.download + '" target="_blank" rel="noopener">Apri / scarica il file</a></div>' : '') +
        '</div>';
      document.body.appendChild(m);
      cur = m;
      document.body.classList.add("lm-open");
      requestAnimationFrame(function () { m.classList.add("show"); });
      m.addEventListener("click", function (e) { if (e.target === m || (e.target.closest && e.target.closest(".lm-x"))) { kill(); } });
      document.addEventListener("keydown", onKey);
    }
    document.addEventListener("click", function (e) {
      var t = e.target.closest ? e.target.closest("[data-modal]") : null;
      if (!t) { return; }
      e.preventDefault();
      build({
        title: t.getAttribute("data-modal-title") || "",
        note: t.getAttribute("data-modal-note") || "",
        embed: t.getAttribute("data-modal-embed") || "",
        image: t.getAttribute("data-modal-image") || "",
        content: t.getAttribute("data-modal-content") || "",
        download: t.getAttribute("data-modal-download") || ""
      });
    });
  })();

  /* --- Menu dinamico da site.json (pagine create/eliminate aggiornano il menu) --- */
  (function () {
    if (location.protocol === "file:") { return; }
    var currentFile = location.pathname.split("/").pop() || "index.html";
    fetch("site.json", { cache: "no-store" })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (site) {
        if (!site || !Array.isArray(site.nav)) { return; }
        var items = site.nav.filter(function (n) { return n && n.page && n.label; });
        // Il menu in alto è gestito staticamente (con tendine) da NAV: non lo riscriviamo qui.
        var fs = document.getElementById("footScuola");
        if (fs) {
          fs.innerHTML = items.filter(function (n) {
            return n.page !== "index.html" && n.page !== "contatti.html";
          }).map(function (n) {
            return '<li><a href="' + n.page + '">' + n.label + "</a></li>";
          }).join("");
        }
      })
      .catch(function () {});
  })();

  /* --- Effetti del SITO: cursore-logo, animazione al clic, intro home + audio --- */
  /*     Disattivati nell'editor (?edit=1) per non disturbare la modifica.          */
  (function () {
    if (new URLSearchParams(location.search).get("edit") === "1") { return; }
    var finePointer = window.matchMedia && window.matchMedia("(pointer: fine)").matches;
    var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // Cursore-logo: appare quando il mouse si FERMA
    if (finePointer && !reduceMotion) {
      var lc = document.createElement("div");
      lc.id = "logo-cursor";
      document.body.appendChild(lc);
      var idleT;
      window.addEventListener("mousemove", function (e) {
        lc.style.left = e.clientX + "px";
        lc.style.top = e.clientY + "px";
        lc.classList.remove("show");
        document.body.classList.remove("cursor-idle");
        clearTimeout(idleT);
        idleT = setTimeout(function () {
          lc.classList.add("show");
          document.body.classList.add("cursor-idle");
        }, 450);
      }, { passive: true });
    }

    // Transizione "a logo" quando si apre una pagina interna
    function hidePageTransition() {
      var pt = document.getElementById("page-transition");
      if (pt) { pt.classList.remove("on"); }
    }
    function pageTransition(go) {
      if (reduceMotion) { go(); return; }
      var pt = document.getElementById("page-transition");
      if (!pt) {
        pt = document.createElement("div");
        pt.id = "page-transition";
        pt.innerHTML = '<span class="ring"></span><img src="assets/img/logo.png" alt="">';
        document.body.appendChild(pt);
      }
      requestAnimationFrame(function () { pt.classList.add("on"); });
      setTimeout(go, 650);
      // sicurezza: se la navigazione non avviene (stessa pagina/anchor o errore) l'overlay non resta bloccato
      setTimeout(hidePageTransition, 3000);
    }
    // Col tasto Indietro/Avanti la pagina può tornare dalla cache del browser (bfcache) con l'overlay ancora attivo:
    // il logo continuerebbe a girare coprendo tutto. Lo nascondiamo al ripristino della pagina.
    window.addEventListener("pageshow", hidePageTransition);
    window.addEventListener("popstate", hidePageTransition);

    // Animazione al clic + transizione sui link interni
    document.addEventListener("click", function (e) {
      if (!reduceMotion) {
        var r = document.createElement("div");
        r.className = "logo-ripple";
        r.style.left = e.clientX + "px";
        r.style.top = e.clientY + "px";
        document.body.appendChild(r);
        setTimeout(function () { r.remove(); }, 700);
      }

      var a = e.target.closest("a[href]");
      if (a) {
        var href = a.getAttribute("href") || "";
        var internal = href && !/^(https?:|mailto:|tel:|#)/i.test(href) && a.getAttribute("target") !== "_blank";
        if (internal) { e.preventDefault(); pageTransition(function () { location.href = href; }); }
      }
    });

    // Intro della home: parte con .play; se l'autoplay audio è bloccato, attende un clic ("Clicca per entrare")
    if (document.body.getAttribute("data-page") === "home") {
      var intro = document.getElementById("paradiso-intro");
      var au = document.getElementById("intro-audio");
      if (intro && reduceMotion) { intro.remove(); intro = null; }
      // L'intro parte al PRIMO avvio del sito (arrivo da un link esterno, dai preferiti o indirizzo
      // digitato: referrer vuoto o di un altro sito). NON riparte quando si torna alla home navigando
      // tra le pagine interne o ricaricando (referrer dello stesso dominio).
      var fromInternal = false;
      try { fromInternal = !!document.referrer && document.referrer.indexOf(location.origin) === 0; } catch (e) {}
      if (intro && fromInternal) { intro.remove(); intro = null; }
      if (intro) {
        var started = false;
        var fadeAudio = function () {
          if (!au) { return; }
          var f = setInterval(function () {
            au.volume = Math.max(0, au.volume - 0.03);
            if (au.volume <= 0.02) { try { au.pause(); } catch (e) {} clearInterval(f); }
          }, 120);
        };
        var run = function (playAudio) {
          if (started) { return; }
          started = true;
          var pr = intro.querySelector(".pi-enter");
          if (pr) { pr.remove(); }
          intro.classList.add("play");
          if (playAudio && au) {
            try { au.currentTime = 0; } catch (e) {}
            au.volume = 0.9;
            var p = au.play(); if (p && p.catch) { p.catch(function () {}); }
          }
          setTimeout(fadeAudio, 5600);
          setTimeout(function () { if (intro && intro.parentNode) { intro.remove(); } }, 7300);
          // salta-intro col clic (armato dopo 700ms per non scattare sul clic d'ingresso)
          setTimeout(function () {
            intro.addEventListener("click", function () {
              intro.style.transition = "opacity .4s"; intro.style.opacity = "0";
              if (au) { try { au.pause(); } catch (e) {} }
              setTimeout(function () { if (intro.parentNode) { intro.remove(); } }, 400);
            });
          }, 700);
        };
        var arm = function () {
          var p = document.createElement("div");
          p.className = "pi-enter";
          p.innerHTML = '<span class="play-ico">&#9654;</span> Clicca per entrare';
          intro.appendChild(p);
          var go = function () {
            document.removeEventListener("pointerdown", go);
            document.removeEventListener("keydown", go);
            run(true);
          };
          document.addEventListener("pointerdown", go);
          document.addEventListener("keydown", go);
          // se l'invito viene ignorato, dopo 6s l'intro parte comunque (senza audio)
          setTimeout(function () {
            if (!started) { document.removeEventListener("pointerdown", go); document.removeEventListener("keydown", go); run(false); }
          }, 6000);
        };
        if (au) {
          au.volume = 0.9;
          var test = au.play();
          if (test && test.then) { test.then(function () { run(false); }).catch(function () { arm(); }); }
          else { run(true); }
        } else {
          run(true);
        }
      }
    }
  })();
})();

/* --- Carosello News in home (auto-rotazione, frecce, puntini, swipe) --- */
(function () {
  function initCarousel(root) {
    var track = root.querySelector(".nc-track");
    var slides = root.querySelectorAll(".nc-slide");
    if (!track || slides.length === 0) { return; }
    var dotsWrap = root.querySelector(".nc-dots");
    var prevBtn = root.querySelector(".nc-prev");
    var nextBtn = root.querySelector(".nc-next");
    var n = slides.length, i = 0, timer = null;
    var delay = parseInt(root.getAttribute("data-autoplay"), 10) || 6000;
    var editMode = new URLSearchParams(location.search).get("edit") === "1";
    var dots = [];

    if (dotsWrap) {
      for (var k = 0; k < n; k++) {
        var b = document.createElement("button");
        b.type = "button"; b.className = "nc-dot";
        b.setAttribute("aria-label", "Vai alla notizia " + (k + 1));
        (function (idx) { b.addEventListener("click", function () { go(idx); restart(); }); })(k);
        dotsWrap.appendChild(b); dots.push(b);
      }
    }
    function update() {
      track.style.transform = "translateX(" + (-i * 100) + "%)";
      for (var d = 0; d < dots.length; d++) { dots[d].classList.toggle("active", d === i); }
    }
    function go(idx) { i = (idx + n) % n; update(); }
    function start() { if (!editMode && n > 1 && !timer) { timer = setInterval(function () { go(i + 1); }, delay); } }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    function restart() { stop(); start(); }

    if (nextBtn) { nextBtn.addEventListener("click", function () { go(i + 1); restart(); }); }
    if (prevBtn) { prevBtn.addEventListener("click", function () { go(i - 1); restart(); }); }
    root.addEventListener("mouseenter", stop);
    root.addEventListener("mouseleave", start);
    document.addEventListener("visibilitychange", function () { if (document.hidden) { stop(); } else { start(); } });

    var x0 = null;
    root.addEventListener("touchstart", function (e) { x0 = e.touches[0].clientX; stop(); }, { passive: true });
    root.addEventListener("touchend", function (e) {
      if (x0 === null) { return; }
      var dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 40) { go(i + (dx < 0 ? 1 : -1)); }
      x0 = null; restart();
    }, { passive: true });

    update(); start();
  }
  function boot() {
    var list = document.querySelectorAll(".news-carousel");
    Array.prototype.forEach.call(list, initCarousel);
  }
  if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", boot); }
  else { boot(); }
})();
