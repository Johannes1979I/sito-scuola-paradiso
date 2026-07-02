/* Service Worker — Scuola Santa Maria del Paradiso (PWA).
   Pagine: network-first (contenuti sempre aggiornati, fallback offline).
   Asset statici: cache-first (velocità; sono versionati con ?v= quindi non restano stantii).
   Cambia CACHE per forzare la pulizia della vecchia cache. */
var CACHE = "smp-v2";
var CORE = ["./", "index.html", "assets/css/style.css", "assets/js/layout.js", "assets/img/logo.png"];

self.addEventListener("install", function (e) {
  self.skipWaiting();
  e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(CORE).catch(function () {}); }));
});

self.addEventListener("activate", function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (k) { if (k !== CACHE) { return caches.delete(k); } }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener("fetch", function (e) {
  var req = e.request;
  if (req.method !== "GET") { return; }
  var url;
  try { url = new URL(req.url); } catch (err) { return; }
  if (url.origin !== location.origin) { return; }              // gestiamo solo lo stesso dominio

  if (url.pathname.slice(-5) === ".json") {                    // dati dinamici: sempre network-first (mai stantii)
    e.respondWith(
      fetch(req).then(function (res) {
        var copy = res.clone();
        caches.open(CACHE).then(function (c) { c.put(req, copy); });
        return res;
      }).catch(function () { return caches.match(req); })
    );
    return;
  }

  if (req.mode === "navigate") {                               // pagine: network-first
    e.respondWith(
      fetch(req).then(function (res) {
        var copy = res.clone();
        caches.open(CACHE).then(function (c) { c.put(req, copy); });
        return res;
      }).catch(function () {
        return caches.match(req).then(function (m) { return m || caches.match("index.html"); });
      })
    );
    return;
  }

  e.respondWith(                                               // asset: cache-first
    caches.match(req).then(function (m) {
      return m || fetch(req).then(function (res) {
        if (res && res.status === 200 && res.type === "basic") {
          var copy = res.clone();
          caches.open(CACHE).then(function (c) { c.put(req, copy); });
        }
        return res;
      }).catch(function () { return m; });
    })
  );
});
