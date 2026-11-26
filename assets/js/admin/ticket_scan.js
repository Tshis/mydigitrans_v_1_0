// public/js/admin/ticket_scan.js

document.addEventListener('DOMContentLoaded', () => {
    const scopeEl = document.querySelector('.admin-agency-scan-scope');
    const verifyUrl = scopeEl?.dataset.verifyUrl || '/admin/agency/ticket/scan/verify';

    const cameraSelect = document.getElementById('js-camera-selection');
    const resultModal = document.getElementById('js-scan-result-overlay');
    const manualForm = document.getElementById('js-manual-form');
    const manualInput = document.getElementById('js-manual-ref');
    const btnClose = document.getElementById('js-btn-close-res');
    const historyList = document.getElementById('js-scan-history-list');

    const config = { fps: 15, qrbox: { width: 280, height: 250 } };
    let html5QrCode = null;
    let isProcessing = false;

    // 1. Initialisation caméra (n'empêche pas la saisie manuelle si pas de caméra)
    if (typeof Html5Qrcode !== 'undefined') {
        html5QrCode = new Html5Qrcode('reader');

        Html5Qrcode.getCameras()
            .then((devices) => {
                if (devices && devices.length) {
                    cameraSelect.innerHTML = '';
                    devices.forEach((device, idx) => {
                        const option = document.createElement('option');
                        option.value = device.id;
                        option.text = device.label || `Caméra ${idx + 1}`;
                        cameraSelect.appendChild(option);
                    });
                    startScanner(devices[0].id);
                } else {
                    cameraSelect.innerHTML = "<option value=''>Aucune caméra détectée</option>";
                }
            })
            .catch(() => {
                cameraSelect.innerHTML = "<option value=''>Caméra indisponible (utilisez la saisie manuelle)</option>";
            });
    } else {
        if (cameraSelect) {
            cameraSelect.innerHTML = "<option value=''>Lecteur optique non chargé</option>";
        }
    }

    function startScanner(cameraId) {
        if (!html5QrCode || html5QrCode.getState() === 2) return;
        html5QrCode
            .start(cameraId, config, (text) => handleValidation(text))
            .catch((err) => console.error('Erreur démarrage caméra :', err));
    }

    cameraSelect?.addEventListener('change', async (e) => {
        if (!html5QrCode || !e.target.value) return;
        if (html5QrCode.getState() === 2) {
            await html5QrCode.stop();
        }
        startScanner(e.target.value);
    });

    // --- VALIDATION RÉELLE EN BASE VIA POST ---
    async function handleValidation(rawRef) {
        const ref = (rawRef || '').trim();
        if (!ref || isProcessing) return;
        isProcessing = true;

        if (html5QrCode && html5QrCode.getState() === 2) {
            try {
                await html5QrCode.pause();
            } catch (e) {
                console.warn('Pause caméra ignorée :', e);
            }
        }

        try {
            const response = await fetch(verifyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ code: ref }),
            });

            const data = await response.json();
            const isSuccess = Boolean(data.valid);

            const normalized = {
                ticketNumber: data.ticketNumber || ref.toUpperCase(),
                name: data.passengerName || 'Billet Inconnu / Invalide',
                seat: data.seatNumber || '--',
                dest: data.destination || 'N/A',
                tripCode: data.tripCode || '--',
                msg: data.message || (isSuccess ? 'EMBARQUEMENT AUTORISÉ' : 'BILLET REFUSÉ'),
            };

            showResult(isSuccess, normalized);
            prependHistory(isSuccess, normalized);
        } catch (error) {
            console.error('Erreur réseau lors du scan :', error);
            showResult(false, {
                ticketNumber: ref.toUpperCase(),
                name: 'Erreur de communication',
                seat: '--',
                dest: 'N/A',
                tripCode: '--',
                msg: 'Impossible de joindre le serveur de contrôle.',
            });
        } finally {
            isProcessing = false;
        }
    }

    function showResult(success, data) {
        resultModal.classList.remove('hidden', 'success', 'error');
        resultModal.classList.add(success ? 'success' : 'error');

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

    function prependHistory(success, data) {
        if (!historyList) return;

        const emptyState = historyList.querySelector('.empty-state');
        if (emptyState) emptyState.remove();

        const now = new Date();
        const timeStr = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        const item = document.createElement('div');
        item.className = `history-item ${success ? 'valid' : 'invalid'}`;
        item.innerHTML = `
            <div class="history-item-meta">
                <strong>#${escapeHtml(data.ticketNumber)}</strong>
                <small class="text-gray">Contrôleur : ${escapeHtml(data.scannedBy || 'Agent')}</small>
            </div>
            <div class="history-item-status text-right">
                <span class="font-bold ${success ? 'text-success' : 'text-danger'}">
                    ${success ? 'VALIDÉ' : 'REFUSÉ'}
                </span>
                <small class="text-gray">${timeStr}</small>
            </div>
        `;

        historyList.prepend(item);
    }


    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    if (manualForm) {
        manualForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleValidation(manualInput.value);
        });
    }

    if (btnClose) {
        btnClose.addEventListener('click', async () => {
            resultModal.classList.add('hidden');
            if (manualInput) {
                manualInput.value = '';
                manualInput.focus();
            }

            if (html5QrCode && html5QrCode.getState() === 3) {
                try {
                    await html5QrCode.resume();
                } catch (e) {
                    console.error('Erreur reprise scanner :', e);
                }
            }
        });
    }
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
        gain.gain.exponentialRampToValueAtTime(0.00001, context.currentTime + 0.4);
        osc.stop(context.currentTime + 0.4);
    } catch (e) {
        console.warn('Audio non supporté par le navigateur');
    }
}
