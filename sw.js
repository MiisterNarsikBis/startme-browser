/* Service Worker — Startme
   Stratégie : network-first pour les pages PHP et l'API,
   cache-first pour les assets statiques (JS, CSS, fonts, images). */

const CACHE = 'startme-v1';
const STATIC_ASSETS = [
  '/assets/favicon.svg',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(STATIC_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);

  // Ignorer les schemes non-cachables (extensions Chrome, etc.)
  if (!url.protocol.startsWith('http')) return;

  // Toujours réseau pour : PHP, API, auth
  if (url.pathname.endsWith('.php') || url.pathname.includes('/api/')) {
    return;
  }

  // Cache-first pour les assets statiques
  e.respondWith(
    caches.match(e.request).then(cached => {
      const networkPromise = fetch(e.request).then(res => {
        if (res.ok && e.request.method === 'GET') {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return res;
      });
      if (cached) {
        // Retourner le cache immédiatement ; mise à jour en arrière-plan (échecs ignorés)
        networkPromise.catch(() => {});
        return cached;
      }
      return networkPromise;
    })
  );
});
