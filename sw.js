// Service Worker PWA — Se.Santé
const SW_PATH = self.location.pathname;
const BASE = SW_PATH.replace(/\/sw\.js(\?.*)?$/, '') || '';
const CACHE_NAME = 'sesante-pwa-v7';
const PWA_APP_NAME = 'Se.Santé';

function pwaUrl(path) {
  if (path.startsWith('http')) return path;
  const clean = path.replace(/^\.\//, '');
  const base = BASE.endsWith('/') ? BASE.slice(0, -1) : BASE;
  return (base + '/' + clean).replace(/\/+/g, '/');
}

const urlsToCache = [
  pwaUrl('manifest.php'),
  pwaUrl('login.php'),
  pwaUrl('assets/pwa/icon-96x96.png'),
  pwaUrl('assets/pwa/icon-192x192.png'),
  pwaUrl('assets/pwa/icon-512x512.png'),
  pwaUrl('assets/pwa/apple-touch-icon.png'),
];

function isStaticAsset(url) {
  return /\.(css|js|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot)(\?|$)/i.test(url.pathname)
    || url.pathname.includes('/assets/');
}

function isAppShellRequest(url) {
  return url.origin === self.location.origin
    && (url.pathname.endsWith('.php') || url.pathname.endsWith('/'));
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(urlsToCache))
      .catch((err) => console.warn('PWA precache:', err))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(
        names.filter((n) => n !== CACHE_NAME).map((n) => caches.delete(n))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  if (url.origin !== self.location.origin) {
    return;
  }

  /* Pages PHP : réseau uniquement (pas de cache HTML — évite pages tronquées / scripts manquants) */
  if (isAppShellRequest(url) && !isStaticAsset(url)) {
    event.respondWith(
      fetch(event.request).catch(() => Response.error())
    );
    return;
  }

  if (isStaticAsset(url)) {
    event.respondWith(
      caches.match(event.request).then((cached) => {
        if (cached) {
          return cached;
        }
        return fetch(event.request).then((response) => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
          }
          return response;
        }).catch(() => Response.error());
      })
    );
  }
});

self.addEventListener('push', (event) => {
  const options = {
    body: event.data ? event.data.text() : 'Nouvelle notification ' + PWA_APP_NAME,
    icon: pwaUrl('assets/pwa/icon-192x192.png'),
    badge: pwaUrl('assets/pwa/icon-96x96.png'),
    vibrate: [100, 50, 100],
    data: { dateOfArrival: Date.now() }
  };
  event.waitUntil(self.registration.showNotification(PWA_APP_NAME, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(clients.openWindow(pwaUrl('login.php')));
});
