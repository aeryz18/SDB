/**
 * DryBox AI — Service Worker
 *
 * Strategy:
 *  - Cache static assets (fonts, Tailwind CDN, Chart.js, Firebase SDK)
 *  - Never cache: Firebase RTDB streams, Laravel routes, API calls
 *  - Serve offline page if network is unavailable
 */

const CACHE_NAME  = 'drybox-v1';
const OFFLINE_URL = '/offline';

// Static assets to pre-cache on install
const PRECACHE = [
  '/offline',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

// Domains whose responses should NEVER be cached (real-time data)
const NO_CACHE_HOSTS = [
  'firebaseio.com',
  'firebasedatabase.app',
  'googleapis.com',
];

// ── Install: pre-cache essentials ─────────────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(PRECACHE))
      .then(() => self.skipWaiting())
  );
});

// ── Activate: clear old caches ────────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ── Fetch: network-first for app routes, cache-first for assets ───
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Never intercept Firebase or Google API calls — always go to network
  if (NO_CACHE_HOSTS.some(host => url.hostname.includes(host))) {
    return; // let browser handle it normally
  }

  // Never cache POST/PUT/DELETE
  if (event.request.method !== 'GET') return;

  // For CDN assets (Tailwind, Chart.js, fonts) — cache-first
  if (url.hostname !== self.location.hostname) {
    event.respondWith(
      caches.match(event.request).then(cached => {
        if (cached) return cached;
        return fetch(event.request).then(response => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          }
          return response;
        });
      })
    );
    return;
  }

  // For Laravel app routes — network-first, fall back to offline page
  event.respondWith(
    fetch(event.request)
      .catch(() => caches.match(OFFLINE_URL))
  );
});
