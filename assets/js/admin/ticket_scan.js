document.addEventListener('DOMContentLoaded', () => {
    
    // Fonction qui attend le chargement effectif de la librairie du CDN
    function initScannerWhenReady() {
        if (typeof Html5Qrcode === 'undefined') {
            console.log("Html5Qrcode n'est pas encore prêt, nouvelle tentative dans 100ms...");
            setTimeout(initScannerWhenReady, 100); // Réessaie toutes les 100ms
            return;
        }

        console.log("Html5Qrcode est chargé avec succès ! Initialisation du scanner...");
        initializeMyScanner();
    }

    function initializeMyScanner() {
        const html5QrCode = new Html5Qrcode("reader");
        const cameraSelect = document.getElementById('js-camera-selection');
        const resultModal = document.getElementById('js-scan-result-overlay');
        const manualForm = document.getElementById('js-manual-form');
        const btnClose = document.getElementById('js-btn-close-res');
        const config = { fps: 15, qrbox: { width: 280, height: 250 } };

        // 1. Lister et initialiser les caméras
        Html5Qrcode.getCameras().then(devices => {
            if (devices && devices.length) {
                cameraSelect.innerHTML = ""; 
                devices.forEach(device => {
                    const option = document.createElement('option');
                    option.value = device.id;
                    option.text = device.label || `Caméra ${cameraSelect.length + 1}`;
                    cameraSelect.appendChild(option);
                });
                startScanner(devices[0].id);
            }
        }).catch(err => {
            cameraSelect.innerHTML = "<option value=''>Caméra indisponible</option>";
        });

        function startScanner(cameraId) {
            html5QrCode.start(cameraId, config, (text) => handleValidation(text))
                .catch(err => console.error("Erreur de démarrage", err));
        }

        // 2. Changement dynamique de device
        cameraSelect.addEventListener('change', async (e) => {
            if (html5QrCode.getState() === 2) { // 2 = SCANNING
                await html5QrCode.stop();
            }
            startScanner(e.target.value);
        });

        // 3. Logique de validation
        async function handleValidation(ref) {
            if(!ref || ref.trim() === "") return;
            try { await html5QrCode.pause(); } catch(e) {}

            const isSuccess = !ref.includes('000') && ref.length > 4; 
            
            showResult(isSuccess, {
                name: "Daniel Lukonu", 
                seat: "12",
                dest: "Matadi",
                msg: isSuccess ? "EMBARQUEMENT AUTORISÉ" : "TICKET INVALIDE OU DÉJÀ UTILISÉ"
            });
        }

        function showResult(success, data) {
            resultModal.classList.remove('hidden', 'success', 'error');
            resultModal.classList.add(success ? 'success' : 'error');
            document.getElementById('js-res-icon').innerHTML = success ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-circle-xmark"></i>';
            document.getElementById('js-res-name').innerText = data.name;
            document.getElementById('js-res-seat').innerText = "Siège " + data.seat;
            document.getElementById('js-res-dest').innerText = "Dest: " + data.dest;
            document.getElementById('js-res-msg').innerText = data.msg;
            playBeep(success);
        }

        manualForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleValidation(document.getElementById('js-manual-ref').value);
        });

        btnClose.addEventListener('click', () => {
            resultModal.classList.add('hidden');
            document.getElementById('js-manual-ref').value = "";
            html5QrCode.resume();
        });
    }

    // Lancement de la surveillance
    initScannerWhenReady();
});

function playBeep(success) {
    try {
        const context = new (window.AudioContext || window.webkitAudioContext)();
        const osc = context.createOscillator();
        const gain = context.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(success ? 880 : 200, context.currentTime);
        osc.connect(gain);
        gain.connect(context.destination);
        osc.start();
        gain.gain.exponentialRampToValueAtTime(0.00001, context.currentTime + 0.5);
        osc.stop(context.currentTime + 0.5);
    } catch(e) {}
}
