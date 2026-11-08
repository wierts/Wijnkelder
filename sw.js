// Service worker voor offline gebruik (v2.4.0).
// - App zelf (index.html, versie.json): eerst internet, anders de bewaarde kopie.
//   Zo krijg je online altijd meteen de nieuwste versie.
// - Kelder, historie en producenten (api/*.php, alleen GET): eerst internet, anders de
//   laatst geladen gegevens. Wijzigen (POST/PATCH/DELETE) gaat altijd rechtstreeks.
// - Lettertypen, iconen, etiketfoto's en de kaartbibliotheken: uit de bewaarde kopie,
//   op de achtergrond ververst.
// Back-up, etiketscan en uploaden worden nooit bewaard.
const CACHE = 'wijnkelder-v2.4.0';
const SHELL = [
  './',
  'index.html',
  'versie.json',
  'manifest.webmanifest',
  'fonts/overpass.css',
  'fonts/overpass-latin.woff2',
  'fonts/overpass-latin-ext.woff2',
  'img/icons/icon-192.png',
  'img/icons/favicon-32.png',
  'img/icons/apple-touch-icon.png',
];
const API_CACHED = ['api/wines.php', 'api/tastings.php', 'api/producers.php', 'api/login.php'];
const CDN_HOSTS = ['cdnjs.cloudflare.com', 'cdn.jsdelivr.net'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k.startsWith('wijnkelder-') && k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

async function networkFirst(req, key) {
  const cache = await caches.open(CACHE);
  try {
    const res = await fetch(req);
    if (res.ok) cache.put(key || req, res.clone());
    return res;
  } catch (err) {
    const hit = await cache.match(key || req);
    if (hit) return hit;
    throw err;
  }
}

async function staleWhileRevalidate(req) {
  const cache = await caches.open(CACHE);
  const hit = await cache.match(req);
  const update = fetch(req).then(res => {
    if (res.ok || res.type === 'opaque') cache.put(req, res.clone());
    return res;
  }).catch(() => hit);
  return hit || update;
}

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return; // wijzigingen nooit via de cache
  const url = new URL(req.url);

  if (url.origin === self.location.origin) {
    const path = url.pathname;
    const api = API_CACHED.find(p => path.endsWith('/' + p));
    if (api) {
      // Eén bewaarde kopie per bestand (querystring telt niet mee)
      return e.respondWith(networkFirst(req, new Request(new URL(api, self.registration.scope))));
    }
    if (path.includes('/api/')) return; // export, scan, upload: altijd rechtstreeks
    if (req.mode === 'navigate' || path.endsWith('/index.html') || path.endsWith('/versie.json')) {
      const key = req.mode === 'navigate' ? new Request(new URL('./', self.registration.scope)) :
        new Request(url.origin + url.pathname);
      return e.respondWith(networkFirst(req, key));
    }
    if (/\/(fonts|img)\//.test(path) || path.endsWith('.webmanifest')) {
      return e.respondWith(staleWhileRevalidate(req));
    }
    return;
  }

  if (CDN_HOSTS.includes(url.hostname)) {
    e.respondWith(staleWhileRevalidate(req));
  }
});
