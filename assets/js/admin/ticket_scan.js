// public/js/admin/ticket_scan.js

document.addEventListener('DOMContentLoaded', () => {
    
    if (typeof Html5Qrcode === 'undefined') {
        console.error("ÉCHEC : La bibliothèque locale html5-qrcode n'est pas chargée.");
        return;
    }

    const html5QrCode = new Html5Qrcode("reader");
    const cameraSelect = document.getElementById('js-camera-selection');
    const resultModal = document.getElementById('js-scan-result-overlay');
    const manualForm = document.getElementById('js-manual-form');
    const manualInput = document.getElementById('js-manual-ref');
    const btnClose = document.getElementById('js-btn-close-res');
    const config = { fps: 15, qrbox: { width: 280, height: 250 } };

    // 1. Lister et initialiser les caméras disponibles
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            cameraSelect.innerHTML = ""; 
            devices.forEach(device => {
                const option = document.createElement('option');
                option.value = device.id;
                option.text = device.label || `Caméra ${cameraSelect.length + 1}`;
                cameraSelect.appendChild(option);
            });
            startScanner(devices[0].id); // Démarre avec le premier périphérique trouvé
        } else {
            cameraSelect.innerHTML = "<option value=''>Aucune caméra détectée</option>";
        }
    }).catch(err => {
        console.warn("Permissions caméra refusées ou absentes :", err);
        cameraSelect.innerHTML = "<option value=''>Erreur d'accès caméra</option>";
    });

    // Fonction sécurisée pour démarrer le scan
    function startScanner(cameraId) {
        if (html5QrCode.getState() === 2) return; // Si déjà en cours de scan, on ignore

        html5QrCode.start(cameraId, config, (text) => handleValidation(text))
            .catch(err => console.error("Erreur lors du démarrage de la caméra :", err));
    }

    // Changement dynamique de caméra
    cameraSelect.addEventListener('change', async (e) => {
        if (html5QrCode.getState() === 2) { // 2 = SCANNING
            await html5QrCode.stop();
        }
        startScanner(e.target.value);
    });

    // --- LOGIQUE DE VALIDATION CENTRALISÉE ET SÉCURISÉE ---
    async function handleValidation(ref) {
        if (!ref || ref.trim() === "") return;

        // ÉVITE LE CRASH : On vérifie si le scanner est bien en train de tourner avant de faire pause
        if (html5QrCode.getState() === 2) { 
            try { 
                await html5QrCode.pause(); 
            } catch (e) { 
                console.warn("Impossible de mettre en pause, l'état de la caméra est transitoire :", e); 
            }
        }

        console.log("Validation en cours pour la référence :", ref);

        // Simulation d'un traitement serveur (Remplacer par votre appel API plus tard)
        const isSuccess = !ref.includes('000') && ref.length > 4; 

        const mockData = {
            name: isSuccess ? "Daniel Lukonu" : "Billet Inconnu / Invalide", 
            seat: isSuccess ? "12" : "--",
            dest: isSuccess ? "Matadi" : "N/A",
            tripCode: "KIN-MAT-260824",
            msg: isSuccess ? "EMBARQUEMENT AUTORISÉ" : "TICKET NON VALIDE OU DÉJÀ SCANNE"
        };

        showResult(isSuccess, mockData);
    }

    function showResult(success, data) {
        // Rendre la modale visible immédiatement
        resultModal.classList.remove('hidden', 'success', 'error');
        resultModal.classList.add(success ? 'success' : 'error');
        
        // Mettre à jour les éléments de la modale raffinée
        document.getElementById('js-res-icon').innerHTML = success 
            ? '<i class="fa-solid fa-circle-check"></i>' 
            : '<i class="fa-solid fa-circle-xmark"></i>';
            
        document.getElementById('js-res-name').innerText = data.name;
        document.getElementById('js-res-seat').innerText = data.seat;
        document.getElementById('js-res-dest').innerText = data.dest;
        document.getElementById('js-res-trip').innerText = data.tripCode;
        document.getElementById('js-res-msg').innerText = data.msg;

        playBeep(success);
    }

    // Gestion du formulaire de saisie manuelle de secours
    if (manualForm) {
        manualForm.addEventListener('submit', (e) => {
            e.preventDefault(); // Bloque le rechargement de la page qui casse le JS
            handleValidation(manualInput.value);
        });
    }

    // Bouton "Passager suivant" de la modale
    if (btnClose) {
        btnClose.addEventListener('click', async () => {
            resultModal.classList.add('hidden');
            manualInput.value = "";
            
            // Relancer le scanner uniquement s'il a été mis en pause (État 3 = PAUSED)
            if (html5QrCode.getState() === 3) {
                try {
                    await html5QrCode.resume();
                } catch(e) {
                    console.error("Erreur lors de la reprise du scanner :", e);
                }
            }
        });
    }
});

// Générateur de bip sonore HTML5 natif pour le terrain
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
        gain.gain.exponentialRampToValueAtTime(0.00001, context.currentTime + 0.4);
        osc.stop(context.currentTime + 0.4);
    } catch(e) {
        console.warn("Audio non supporté par le navigateur");
    }
}
