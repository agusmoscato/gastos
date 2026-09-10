const CACHE_NAME = "mi-libreta-v13";
const PRECACHE = [
  "/assets/style.css?v=v13",
  "/assets/app.js?v=v13",
  "/assets/icons/icon-192.png",
  "/assets/icons/icon-512.png",
  "/manifest.json",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);

  // La API nunca se cachea: siempre datos frescos.
  if (url.pathname.startsWith("/api/") || event.request.method !== "GET") {
    return;
  }

  // Paginas (navegacion): red primero, cache como respaldo si no hay conexion.
  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request).catch(() => caches.match(event.request).then((r) => r || caches.match("/index.php")))
    );
    return;
  }

  // Estaticos (css/js/iconos): RED PRIMERO. Así, cada vez que suba una
  // actualización, la vas a ver de inmediato; el cache solo se usa como
  // respaldo si en ese momento no hay conexión. (Antes era "cache primero",
  // que es más rápido pero podía dejarte con una versión vieja del código
  // hasta que la cache se pisara sola — eso fue lo que pasó con las cuotas.)
  if (url.origin === self.location.origin && PRECACHE.some((p) => url.pathname === p.split("?")[0])) {
    event.respondWith(
      fetch(event.request)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
          return res;
        })
        .catch(() => caches.match(event.request))
    );
  }
});
