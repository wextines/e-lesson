self.addEventListener("install", (event) => {
  console.log("Service Worker установлен");
  event.waitUntil(
    caches.open("iq-cache-v1").then((cache) => {
      return cache.addAll([
        "/",
        "/index.html",
        "/js/index.js",
        "/css/index.css",
        "/course/index.php",
        "/css/profile.css",
        "/profile/index.php",
        "/img/homepage/logo.png"
      ]);
    })
  );
});

self.addEventListener("fetch", (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});
