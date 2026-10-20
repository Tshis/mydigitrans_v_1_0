// public/sw.js

// 🚨 ON CHANGE LE NOM DE LA CLÉ POUR FORCER L'INCRÉMENTATION D'USINE
//date du 20/10/2026 à 12H43 LSHI derniere modification
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

// 1. ÉTAPE D'INSTALLATION (Création du nouveau conteneur)
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(ASSETS_TO_CACHE);
        }).then(() => {
            // 🚨 FORCE LE SERVICE WORKER EN ATTENTE À S'ACTIVER TOUT DE SUITE
            return self.skipWaiting();
        })
    );
});

// 2. 🚨 NOUVEAU - ÉTAPE D'ACTIVATION : NETTOYAGE CHIRURGICAL DES ANCIENS CACHES PERIMÉS
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            // 🚨 LE FIX : On utilise le point (.) de JavaScript à la place des deux-points (::) de PHP [Profile]
            return Promise.all(
                cacheNames.map(cache => {
                    // Si le nom du cache en mémoire est différent du nouveau (v1.1), on le détruit d'office
                    if (cache !== CACHE_NAME) {
                        console.log('PWA MyDigitrans : Destruction de l\'ancien cache obsolète :', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => {
            // Prend le contrôle immédiat de tous les onglets ouverts
            return self.clients.claim();
        })
    );
});


// 3. DISTRIBUTION DES FLUX
self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request).then(response => {
            return response || fetch(event.request);
        })
    );
});
