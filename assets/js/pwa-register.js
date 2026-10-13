// public/js/pwa-register.js

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('MyDigitrans PWA : Moteur local ServiceWorker activé avec succès !');
            })
            .catch(error => {
                console.error('MyDigitrans PWA : Échec de l’enrôlement du ServiceWorker :', error);
            });
    });
}
