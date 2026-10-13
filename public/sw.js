// public/sw.js
const CACHE_NAME = 'mydigitrans-v1.0-cache';
const ASSETS_TO_CACHE = [
    // 0. Les points d'entrée et liens structurels d'usine
    '/',
    '/login',
    '/business',
    '/register-agency',
    '/contact',
    '/manifest.json', // 🚨 AJOUTÉ : Indispensable pour l'installation sur smartphone !

    // 1. L'identité visuelle brute (Logo et Favicon)
    '/img-core/favicon.png',
    '/img-core/logo.png',

    // 2. Les scripts JavaScript structurels globaux (App Shell)
    '/js/admin/app.js',
    '/js/public/app.js',
    '/js/pwa-register.js',

    // 3. Les styles CSS globaux compilés
    '/styles/app.css',

    // 4. Les polices d'icônes et typographies (Fichiers complets et précis)
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css', // 🚨 AJOUTÉ : Ta version 6.7.2 optimisée !
];


// Installation du cache d'usine
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

// Distribution ultra-rapide des fichiers mis en cache
self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request).then(response => {
            return response || fetch(event.request);
        })
    );
});
