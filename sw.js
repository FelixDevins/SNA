/* Service worker da Tracklife Fit: deixa a loja abrir rápido e funcionar sem internet (modo básico). */
var V = 'tracklife-v4';
var CORE = ['./', 'index.html', 'manifest.json', 'icon-192.png'];

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(V).then(function (c) { return c.addAll(CORE); }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (ks) {
    return Promise.all(ks.filter(function (k) { return k !== V; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
  var r = e.request;
  if (r.method !== 'GET') return;
  var u = new URL(r.url);
  if (u.origin !== location.origin) return;                 // Supabase e fontes: direto da rede
  if (u.pathname.indexOf('pagamento.json') !== -1 || u.pathname.indexOf('/admin') === 0 || u.pathname.indexOf('/api/') === 0 || u.pathname.indexOf('instalar') !== -1 || u.pathname.indexOf('painel-') !== -1) return; // painéis, API e instalador nunca em cache
  if (r.mode === 'navigate') {                              // páginas: rede primeiro, cache se estiver offline
    e.respondWith(fetch(r).then(function (res) {
      var c = res.clone(); caches.open(V).then(function (ca) { ca.put('index.html', c); });
      return res;
    }).catch(function () { return caches.match('index.html'); }));
    return;
  }
  e.respondWith(caches.match(r).then(function (hit) {       // arquivos: cache e atualiza em segundo plano
    var net = fetch(r).then(function (res) {
      if (res && res.ok) { var c = res.clone(); caches.open(V).then(function (ca) { ca.put(r, c); }); }
      return res;
    }).catch(function () { return hit; });
    return hit || net;
  }));
});
