/**
 * Connect'Academia — Service Worker
 * Stratégie: Cache-first (assets), Network-first (pages), Network-only (API)
 */

'use strict';

const CACHE_VERSION = 'v1.0.1';
const STATIC_CACHE  = 'ca-static-'  + CACHE_VERSION;
const PAGE_CACHE    = 'ca-pages-'   + CACHE_VERSION;

// Dériver le scope (fonctionne en sous-dossier MAMP et en production)
const SW_SCOPE = self.registration.scope;
const BASE_PATH = new URL(SW_SCOPE).pathname.replace(/\/$/, '');

// Assets critiques à précacher lors de l'install
const PRECACHE_ASSETS = [
  BASE_PATH + '/public/offline.html',
  BASE_PATH + '/public/assets/css/global.css',
  BASE_PATH + '/public/assets/css/colors_and_type.css',
  BASE_PATH + '/public/assets/js/app.js',
  BASE_PATH + '/public/assets/images/logo-officiel.png',
  BASE_PATH + '/public/assets/images/icons/icon-192x192.png',
  BASE_PATH + '/public/assets/images/icons/icon-512x512.png',
  BASE_PATH + '/public/assets/images/default-avatar.svg',
];

// ── INSTALL : précache des assets critiques ──────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      return Promise.allSettled(
        PRECACHE_ASSETS.map((url) =>
          cache.add(url).catch((err) => {
            console.warn('[SW] Précache échoué:', url, err);
          })
        )
      );
    }).then(() => self.skipWaiting())
  );
});

// ── ACTIVATE : supprimer les anciens caches ──────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== STATIC_CACHE && key !== PAGE_CACHE)
            .map((key) => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

// ── FETCH : stratégie de routage ─────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Ignorer: non-GET, autres origines, DevTools
  if (request.method !== 'GET') return;
  if (url.origin !== self.location.origin) return;
  if (url.pathname.includes('__')) return;

  // Ignorer les fichiers média — les range requests (206) doivent passer
  // directement au réseau pour éviter que Chrome navigue vers l'URL du média
  if (/\.(mp4|webm|ogg|mp3|wav|flac|aac|m4a|mov|avi|mkv)$/i.test(url.pathname)) return;

  const path = url.pathname;

  // ── API / Routes dynamiques → Network-only (pas de cache) ────────────────
  if (isApiRequest(path)) {
    event.respondWith(networkOnly(request));
    return;
  }

  // ── Assets statiques → Cache-first ───────────────────────────────────────
  if (isStaticAsset(path)) {
    event.respondWith(cacheFirst(request, STATIC_CACHE));
    return;
  }

  // ── Pages HTML → Network-first avec fallback offline ─────────────────────
  if (isNavigationRequest(request)) {
    event.respondWith(networkFirstWithOfflineFallback(request));
    return;
  }
});

// ── Stratégies ───────────────────────────────────────────────────────────────

async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    return new Response('Asset indisponible', { status: 503 });
  }
}

async function networkOnly(request) {
  try {
    return await fetch(request);
  } catch {
    return new Response(JSON.stringify({ error: 'Hors ligne' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' },
    });
  }
}

async function networkFirstWithOfflineFallback(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(PAGE_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await caches.match(request);
    if (cached) return cached;

    const offlineUrl = BASE_PATH + '/public/offline.html';
    const offlinePage = await caches.match(offlineUrl);
    return offlinePage || new Response('<h1>Hors ligne</h1>', {
      headers: { 'Content-Type': 'text/html; charset=utf-8' },
    });
  }
}

// ── Helpers de classification ─────────────────────────────────────────────────

function isApiRequest(path) {
  return path.includes('/api/') ||
         path.includes('/admin/') ||
         path.includes('/auth/') ||
         path.includes('/paiement/');
}

function isStaticAsset(path) {
  return path.includes('/public/assets/') ||
         path.includes('/public/uploads/') ||
         /\.(css|js|png|jpg|jpeg|svg|webp|gif|woff|woff2|ttf|ico)$/i.test(path);
}

function isNavigationRequest(request) {
  return request.mode === 'navigate' ||
         request.headers.get('Accept')?.includes('text/html');
}
