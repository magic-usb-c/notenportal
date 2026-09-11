// Service Worker Notenportal (Block AC): cached NUR gebaute Assets (public/build/*), die
// Icons und die statische Offline-Seite. NIE HTML mit Personendaten oder API-Antworten cachen.
// Navigationsanfragen: network-first, bei Fehler die Offline-Seite. Version = Hash des
// Vite-Manifests (public/build/manifest.json) – neuer Build erzeugt einen neuen Cache-Namen,
// alte Caches werden beim activate gelöscht. Registrierung: resources/js/pwa.js.

const MANIFEST_URL = '/build/manifest.json';
const OFFLINE_URL = '/offline';
const ICON_URLS = ['/app-icons/icon-192.png', '/app-icons/icon-512.png', '/app-icons/icon-maskable-512.png'];
const CACHE_PREFIX = 'notenportal-';

async function ladeManifest() {
    const antwort = await fetch(MANIFEST_URL, { cache: 'no-store' });
    const text = await antwort.text();
    const hashPuffer = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
    const hash = Array.from(new Uint8Array(hashPuffer)).map((b) => b.toString(16).padStart(2, '0')).join('').slice(0, 16);

    return { text, cacheName: CACHE_PREFIX + hash };
}

function assetUrls(manifestText) {
    const manifest = JSON.parse(manifestText);
    const urls = new Set();

    for (const eintrag of Object.values(manifest)) {
        if (eintrag.file) urls.add('/build/' + eintrag.file);
        for (const css of eintrag.css ?? []) urls.add('/build/' + css);
        for (const asset of eintrag.assets ?? []) urls.add('/build/' + asset);
    }

    return [...urls];
}

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const { text, cacheName } = await ladeManifest();
        const cache = await caches.open(cacheName);
        await cache.addAll([...assetUrls(text), ...ICON_URLS, OFFLINE_URL]);
    })());
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const { cacheName } = await ladeManifest();
        const vorhandene = await caches.keys();
        await Promise.all(
            vorhandene.filter((name) => name.startsWith(CACHE_PREFIX) && name !== cacheName).map((name) => caches.delete(name)),
        );
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Navigation: network-first, damit stets der aktuelle (personenbezogene) Inhalt kommt;
    // nur bei Netzfehler die gecachte, personendatenfreie Offline-Seite.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Nur gebaute Assets und Icons aus dem Cache bedienen; alles andere (API, Uploads) normal ans Netz.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/app-icons/')) {
        event.respondWith(caches.match(request).then((treffer) => treffer ?? fetch(request)));
    }
});

// Abmelden auf geteilten Geräten: resources/js/pwa.js schickt das beim Logout-Formular.
self.addEventListener('message', (event) => {
    if (event.data?.type !== 'ABMELDEN') return;

    event.waitUntil(
        caches.keys().then((namen) => Promise.all(namen.filter((n) => n.startsWith(CACHE_PREFIX)).map((n) => caches.delete(n)))),
    );
});
