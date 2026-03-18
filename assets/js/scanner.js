/**
 * assets/js/scanner.js
 * Scanner de code-barres – version corrigée et robuste
 */

'use strict';

(function () {

    const toggleBtn   = document.getElementById('toggleScanner');
    const stopBtn     = document.getElementById('stopScanner');
    const container   = document.getElementById('scannerContainer');
    const statusEl    = document.getElementById('scanStatus');
    const codeInput   = document.getElementById('code_barre');

    if (!toggleBtn || !codeInput) return;

    let isRunning   = false;
    let lastCode    = '';
    let scanTimeout = null;

    // ── Démarrage ────────────────────────────────────────────
    function startScanner() {
        if (isRunning) return;

        container.classList.remove('hidden');
        statusEl.textContent  = '⏳ Démarrage de la caméra…';
        statusEl.style.color  = '#d4a35a';
        toggleBtn.textContent = '⏹ Arrêter';

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
                let msg = '❌ Erreur caméra';
                if (err.name === 'NotAllowedError')  msg = '❌ Accès caméra refusé — autorisez-le dans le navigateur.';
                if (err.name === 'NotFoundError')    msg = '❌ Aucune caméra détectée.';
                if (err.name === 'NotReadableError') msg = '❌ Caméra utilisée par une autre application.';
                statusEl.textContent = msg;
                statusEl.style.color = '#f87171';
                isRunning = false;
                toggleBtn.textContent = '📷 Scanner';
                return;
            }

            Quagga.start();
            isRunning = true;
            statusEl.textContent = '📷 Pointez vers le code-barres…';
            statusEl.style.color = '#4ade80';
        });

        // ── Callback détection ───────────────────────────────
        Quagga.onDetected(function (data) {
            const code = data.codeResult.code;

            if (!code || code.length < 4) return;
            if (code === lastCode) return;
            lastCode = code;

            clearTimeout(scanTimeout);

            scanTimeout = setTimeout(function () {
                codeInput.value = code;
                codeInput.style.borderColor = '#4ade80';
                setTimeout(function() { codeInput.style.borderColor = ''; }, 2000);

                statusEl.textContent = '✅ Code détecté : ' + code;
                statusEl.style.color = '#4ade80';

                playBeep();
                stopScanner();
                lookupProduct(code);

            }, 300);
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

        container.classList.add('hidden');
        toggleBtn.textContent = '📷 Scanner';

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
                    showNotif('📦 Produit déjà en base : ' + data.produit.nom, 'info');
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

})();
