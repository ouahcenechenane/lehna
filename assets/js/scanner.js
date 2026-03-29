/**
 * assets/js/scanner.js
 * Scanner de code-barres – version corrigée et robuste
 * + Support scanner USB HID (HENEX et compatibles)
 */

'use strict';

(function () {

    const toggleBtn   = document.getElementById('toggleScanner');
    const stopBtn     = document.getElementById('stopScanner');
    const container   = document.getElementById('scannerContainer');
    const statusEl    = document.getElementById('scanStatus');
    const codeInput   = document.getElementById('code_barre');

    if (!toggleBtn || !codeInput) return;

    let isRunning        = false;
    let lastCode         = '';
    let detectionCount   = 0;
    let lastDetectedCode = '';
    let scanTimeout = null;

    // ════════════════════════════════════════════════════════════════════════
    // SCANNER USB HID (HENEX et compatibles)
    // Détecte les frappes ultra-rapides d'un scanner physique (<50 ms)
    // et leur donne la priorité sur la caméra Quagga.
    // ════════════════════════════════════════════════════════════════════════

    const USB_SPEED_THRESHOLD = 50;    // ms max entre deux touches → mode USB
    const CAMERA_RESUME_DELAY = 5000;  // ms d'inactivité avant relance caméra auto

    let usbBuffer         = '';   // tampon des caractères du scan en cours
    let usbLastKeyTime    = 0;    // timestamp de la dernière touche reçue
    let usbCameraTimer    = null; // timer de relance automatique caméra
    let autoRestartCamera = false;// doit-on relancer la caméra après un scan USB ?

    /**
     * Lance l'écoute du scanner USB sur l'ensemble du document.
     * Mécanisme : si l'intervalle entre deux touches est < USB_SPEED_THRESHOLD,
     * on est en mode scanner USB → on accumule un buffer.
     * Sur la touche "Enter", si le buffer est assez long, on traite le scan.
     */
    function initUSBScanner() {
        document.addEventListener('keydown', function (e) {

            // ── Ne pas intercepter si le focus est sur un autre champ ────────
            const active = document.activeElement;
            const isOtherInput = active &&
                ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName) &&
                active !== codeInput;
            if (isOtherInput) return;

            const now     = Date.now();
            const elapsed = now - usbLastKeyTime;

            // ── Touche Entrée : valider le scan ──────────────────────────────
            if (e.key === 'Enter') {
                if (usbBuffer.length >= 4) {
                    e.preventDefault();
                    e.stopPropagation();

                    const code = usbBuffer;
                    usbBuffer      = '';
                    usbLastKeyTime = 0;

                    console.log('%c\uD83D\uDD0C MODE SCANNER USB \u2014 code re\u00E7u : ' + code,
                        'color:#4ade80; font-weight:bold');

                    // Si la caméra Quagga est active → l'arrêter (USB a priorité)
                    if (isRunning) {
                        autoRestartCamera = true; // on la relancera après inactivité
                        stopScanner();
                    }

                    // Injecter le code dans le champ et signaler visuellement
                    codeInput.value = code;
                    codeInput.style.borderColor = '#4ade80';
                    setTimeout(function () { codeInput.style.borderColor = ''; }, 2000);

                    if (statusEl) {
                        statusEl.textContent = '\uD83D\uDD0C Scanner USB : ' + code;
                        statusEl.style.color  = '#4ade80';
                    }

                    playBeep();
                    lookupProduct(code);          // logique existante préservée
                    scheduleCameraRestart();       // relance caméra après délai
                }

                // Toujours vider le buffer sur Enter
                usbBuffer      = '';
                usbLastKeyTime = 0;
                return;
            }

            // ── Caractère imprimable ─────────────────────────────────────────
            if (e.key.length === 1) {
                if (usbBuffer.length === 0 || elapsed < USB_SPEED_THRESHOLD) {
                    // Frappe rapide (ou premier caractère) → on accumule
                    usbBuffer += e.key;

                    // À partir du 2e caractère rapide : masquer la frappe dans le champ
                    // pour éviter un doublon (le buffer remplacera la valeur à la fin)
                    if (usbBuffer.length > 1) {
                        e.preventDefault();
                    }
                } else {
                    // Frappe trop lente → saisie humaine, on réinitialise le buffer
                    usbBuffer         = e.key;
                    autoRestartCamera = false;
                }
                usbLastKeyTime = now;
            }
        });
    }

    /**
     * Planifie la relance automatique de Quagga après CAMERA_RESUME_DELAY ms
     * d'inactivité USB. N'agit que si la caméra était active avant le scan USB.
     */
    function scheduleCameraRestart() {
        clearTimeout(usbCameraTimer);
        if (!autoRestartCamera) return;

        usbCameraTimer = setTimeout(function () {
            if (!isRunning && autoRestartCamera) {
                console.log('%c\uD83D\uDCF7 MODE CAM\u00C9RA \u2014 relance automatique (inactivit\u00E9 USB)',
                    'color:#60a5fa; font-weight:bold');
                autoRestartCamera = false;
                lastCode          = '';
                startScanner();
            }
        }, CAMERA_RESUME_DELAY);
    }

    // ════════════════════════════════════════════════════════════════════════
    // SCANNER CAMÉRA (Quagga) — code original préservé à l'identique
    // ════════════════════════════════════════════════════════════════════════

    // ── Démarrage ────────────────────────────────────────────
    function startScanner() {
        if (isRunning) return;

        container.classList.remove('hidden');
        statusEl.textContent  = '\u23F3 D\u00E9marrage de la cam\u00E9ra\u2026';
        statusEl.style.color  = '#d4a35a';
        toggleBtn.textContent = '\u23F9 Arr\u00EAter';

        console.log('%c\uD83D\uDCF7 MODE CAM\u00C9RA \u2014 d\u00E9marrage Quagga',
            'color:#60a5fa; font-weight:bold');

        Quagga.init({
            inputStream: {
                name: 'Live',
                type: 'LiveStream',
                target: document.querySelector('#interactive'),
                constraints: {
                    facingMode: 'environment',
                    width:  { min: 640 },
                    height: { min: 480 },
                },
            },
            decoder: {
                readers: [
                    'ean_reader',
                    'ean_8_reader',
                    'code_128_reader',
                    'code_39_reader',
                    'upc_reader',
                ],
                multiple: false,
            },
            locate:    true,
            frequency: 5,
            numOfWorkers: 2,
        }, function (err) {
            if (err) {
                console.error('Scanner error:', err);
                let msg = '\u274C Erreur cam\u00E9ra';
                if (err.name === 'NotAllowedError')  msg = '\u274C Acc\u00E8s cam\u00E9ra refus\u00E9 \u2014 autorisez-le dans le navigateur.';
                if (err.name === 'NotFoundError')    msg = '\u274C Aucune cam\u00E9ra d\u00E9tect\u00E9e.';
                if (err.name === 'NotReadableError') msg = '\u274C Cam\u00E9ra utilis\u00E9e par une autre application.';
                statusEl.textContent = msg;
                statusEl.style.color = '#f87171';
                isRunning = false;
                toggleBtn.textContent = '\uD83D\uDCF7 Scanner';
                return;
            }

            Quagga.start();
            isRunning = true;
            statusEl.textContent = '\uD83D\uDCF7 Pointez vers le code-barres\u2026';
            statusEl.style.color = '#4ade80';
        });

        // ── Callback détection ───────────────────────────────
        Quagga.onDetected(function (data) {
            const code = data.codeResult.code;

            if (!code || code.length < 4) return;

            // Compter les détections consécutives du MÊME code
            // pour éviter les faux positifs de QuaggaJS
            if (code === lastDetectedCode) {
                detectionCount++;
            } else {
                lastDetectedCode = code;
                detectionCount   = 1;
            }

            // N'accepter qu'après 3 détections identiques consécutives
            if (detectionCount < 3) return;
            if (code === lastCode) return;

            lastCode         = code;
            detectionCount   = 0;
            lastDetectedCode = '';

            clearTimeout(scanTimeout);

            codeInput.value = code;
            codeInput.style.borderColor = '#4ade80';
            setTimeout(function() { codeInput.style.borderColor = ''; }, 2000);

            statusEl.textContent = '\u2705 Code d\u00E9tect\u00E9 : ' + code;
            statusEl.style.color = '#4ade80';

            playBeep();
            stopScanner();
            lookupProduct(code);
        });
    }

    // ── Arrêt complet ────────────────────────────────────────
    function stopScanner() {
        clearTimeout(scanTimeout);

        try {
            Quagga.offDetected();
            Quagga.stop();
        } catch (e) {}

        isRunning = false;
        lastCode  = '';
        detectionCount   = 0;
        lastDetectedCode = '';

        container.classList.add('hidden');
        toggleBtn.textContent = '\uD83D\uDCF7 Scanner';

        stopVideoTracks();
    }

    // ── Éteint le flux caméra ────────────────────────────────
    function stopVideoTracks() {
        document.querySelectorAll('#interactive video').forEach(function (video) {
            if (video.srcObject) {
                video.srcObject.getTracks().forEach(function (t) { t.stop(); });
                video.srcObject = null;
            }
        });
    }

    // ── Son bip ──────────────────────────────────────────────
    function playBeep() {
        try {
            const ctx  = new (window.AudioContext || window.webkitAudioContext)();
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 1200;
            osc.type = 'square';
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.15);
        } catch (e) {}
    }

    // ── Lookup produit existant ──────────────────────────────
    async function lookupProduct(code) {
        const nomInput = document.getElementById('nom');
        if (!nomInput) return;
        try {
            const resp = await fetch('ajax/search_barcode.php?code=' + encodeURIComponent(code));
            const data = await resp.json();
            if (data.success && data.produit) {
                if (nomInput.value.trim() === '') nomInput.value = data.produit.nom;
                const prixInput = document.getElementById('prix');
                if (prixInput && prixInput.value === '') prixInput.value = data.produit.prix;
                if (typeof showNotif === 'function')
                    showNotif('\uD83D\uDCE6 Produit d\u00E9j\u00E0 en base : ' + data.produit.nom, 'info');
            }
        } catch (e) {}
    }

    // ── Boutons ──────────────────────────────────────────────
    toggleBtn.addEventListener('click', function () {
        if (isRunning) { stopScanner(); } else { lastCode = ''; startScanner(); }
    });

    if (stopBtn) {
        stopBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            stopScanner();
        });
    }

    window.addEventListener('beforeunload', stopScanner);

    // ── Initialisation scanner USB ───────────────────────────
    // Appelé en dernier : toutes les fonctions sont déjà définies
    initUSBScanner();

})();