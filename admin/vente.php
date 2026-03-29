<?php
/**
 * admin/vente.php
 * Caisse / Point de Vente (POS)
 * Interface complète : scanner, panier, total, monnaie, reçu
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caisse – <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/pos.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>
<div class="admin-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="admin-main pos-main">

    <!-- ══ EN-TÊTE CAISSE ══ -->
    <div class="pos-header">
        <div class="pos-header-left">
            <h1 class="page-title">🛒 Caisse</h1>
            <span id="posDateTime" class="pos-datetime"></span>
        </div>
        <div class="pos-header-right">
            <span class="pos-seller">👤 <?= e($_SESSION['admin_nom']) ?></span>
            <button class="btn btn-danger btn-sm" id="btnAnnulerVente">🗑️ Vider le panier</button>
            <a href="ventes_history.php" class="btn btn-sm">📋 Historique</a>
        </div>
    </div>

    <div class="pos-layout">

        <!-- ══════════════════════════════════════
             COLONNE GAUCHE : Recherche + Produits
        ══════════════════════════════════════ -->
        <div class="pos-left">

            <!-- Barre de recherche / scan -->
            <div class="pos-search-bar">
                <div class="pos-scan-input-wrap">
                    <span class="pos-scan-icon">🔍</span>
                    <input type="text" id="posSearch"
                           placeholder="Scanner ou taper le code-barres / nom du produit…"
                           autocomplete="off" autofocus>
                    <button id="posScanBtn" class="btn btn-secondary" title="Scanner avec caméra">
                        📷
                    </button>
                </div>
            </div>

            <!-- Visionneuse scanner -->
            <div id="posScannerContainer" class="scanner-container hidden" style="margin-bottom:16px">
                <div class="scanner-header">
                    <span>📷 Scan en cours…</span>
                    <button type="button" id="posStopScan" class="btn btn-sm btn-danger">✕ Fermer</button>
                </div>
                <div id="posInteractive" class="viewport"></div>
                <p id="posScanStatus" class="scan-status">Pointez vers le code-barres du produit</p>
            </div>

            <!-- Grille produits rapides (les 12 premiers produits) -->
            <div class="pos-quick-title">Produits rapides <small>— cliquez pour ajouter</small></div>
            <div class="pos-products-grid" id="posProductsGrid">
                <?php
                $pdo   = getPDO();
                $prods = $pdo->query('SELECT * FROM produits WHERE actif=1 ORDER BY nom ASC LIMIT 24')->fetchAll();
                foreach ($prods as $p):
                    $imgSrc = ($p['image'] && $p['image'] !== 'default.jpg' && file_exists(UPLOAD_PATH . $p['image']))
                              ? UPLOAD_URL . $p['image']
                              : APP_URL . '/assets/images/default.php';
                    $dispo  = (int)$p['quantite'] > 0;
                ?>
                <div class="pos-product-tile <?= $dispo ? '' : 'out-of-stock' ?>"
                     data-id="<?= $p['id'] ?>"
                     data-nom="<?= e($p['nom']) ?>"
                     data-prix="<?= $p['prix'] ?>"
                     data-code="<?= e($p['code_barre']) ?>"
                     data-stock="<?= $p['quantite'] ?>"
                     onclick="<?= $dispo ? 'addToCart(this)' : '' ?>"
                     title="<?= $dispo ? 'Ajouter : ' . e($p['nom']) : 'Rupture de stock' ?>">
                    <img src="<?= e($imgSrc) ?>" alt="<?= e($p['nom']) ?>" loading="lazy">
                    <div class="pos-tile-info">
                        <span class="pos-tile-name"><?= e(mb_substr($p['nom'], 0, 28)) ?></span>
                        <span class="pos-tile-price"><?= number_format((float)$p['prix'], 0, ',', ' ') ?> DA</span>
                    </div>
                    <?php if (!$dispo): ?>
                        <div class="pos-tile-rupture">Rupture</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

        </div><!-- /pos-left -->

        <!-- ══════════════════════════════════════
             COLONNE DROITE : Panier + Paiement
        ══════════════════════════════════════ -->
        <div class="pos-right">

            <!-- ── En-tête panier ── -->
            <div class="pos-cart-header">
                <span>🛒 Panier</span>
                <span id="cartCount" class="cart-count-badge">0 article</span>
            </div>

            <!-- ── Liste des articles ── -->
            <div class="pos-cart-items" id="cartItems">
                <div class="pos-cart-empty" id="cartEmpty">
                    <p>🛒</p>
                    <p>Le panier est vide</p>
                    <small>Scannez ou cliquez sur un produit</small>
                </div>
            </div>

            <!-- ── Récapitulatif ── -->
            <div class="pos-summary">
                <div class="pos-summary-row">
                    <span>Sous-total</span>
                    <span id="summarySubtotal">0,00 DA</span>
                </div>
                <div class="pos-summary-row">
                    <span>Remise</span>
                    <div class="remise-input-wrap">
                        <input type="number" id="remiseInput" min="0" max="100"
                               placeholder="0" step="1" title="Remise en %">
                        <span>%</span>
                    </div>
                    <span id="summaryRemise" class="remise-val">- 0,00 DA</span>
                </div>
                <div class="pos-summary-total">
                    <span>TOTAL</span>
                    <span id="summaryTotal">0,00 DA</span>
                </div>

                <!-- ── Paiement ── -->
                <div class="pos-payment">
                    <label>Montant reçu (DA)</label>
                    <div class="payment-input-row">
                        <input type="number" id="montantRecu" min="0" step="50"
                               placeholder="0,00" class="payment-input">
                        <div class="quick-amounts" id="quickAmounts"></div>
                    </div>
                    <div class="monnaie-display" id="monnaieDisplay">
                        <span>Monnaie à rendre</span>
                        <span id="monnaieVal" class="monnaie-val">–</span>
                    </div>
                </div>

                <!-- ── Bouton valider ── -->
                <button class="btn-valider-vente" id="btnValiderVente" disabled>
                    ✅ Valider la vente
                </button>
            </div>

        </div><!-- /pos-right -->

    </div><!-- /pos-layout -->

</main>
</div>

<!-- ══════════════════════════════════════════════
     MODAL REÇU DE VENTE
══════════════════════════════════════════════ -->
<div id="modalRecu" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-box recu-modal">
        <div class="recu-header">
            <div class="recu-logo">🛒</div>
            <h2><?= e(APP_NAME) ?></h2>
            <p id="recuNumero" class="recu-numero"></p>
            <p id="recuDate"   class="recu-date"></p>
        </div>
        <div class="recu-separator">— — — — — — — — — — — —</div>
        <table class="recu-table" id="recuItems"></table>
        <div class="recu-separator">— — — — — — — — — — — —</div>
        <div class="recu-totaux" id="recuTotaux"></div>
        <div class="recu-separator">— — — — — — — — — — — —</div>
        <p class="recu-merci">Merci pour votre achat !<br><small><?= e(APP_NAME) ?></small></p>
        <div class="recu-actions">
            <button onclick="window.print()" class="btn btn-secondary">🖨️ Imprimer</button>
            <button id="btnNouvelleVente" class="btn btn-primary">🛒 Nouvelle vente</button>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
<script>
/* ═══════════════════════════════════════════════════════
   POS – Logique complète de la caisse
   ═══════════════════════════════════════════════════════ */

const CSRF = '<?= csrfToken() ?>';
const APP_URL = '<?= APP_URL ?>';

// ── État du panier ────────────────────────────────────
let cart = [];   // [{id, nom, code, prix, quantite, stock}]

// ── Horloge ──────────────────────────────────────────
function updateClock() {
    const el = document.getElementById('posDateTime');
    if (!el) return;
    const now  = new Date();
    const opts = { weekday:'long', year:'numeric', month:'long', day:'numeric',
                   hour:'2-digit', minute:'2-digit', second:'2-digit' };
    el.textContent = now.toLocaleDateString('fr-FR', opts);
}
setInterval(updateClock, 1000);
updateClock();

// ── Ajouter au panier depuis la grille ───────────────
function addToCart(tile) {
    const id    = parseInt(tile.dataset.id);
    const nom   = tile.dataset.nom;
    const prix  = parseFloat(tile.dataset.prix);
    const code  = tile.dataset.code;
    const stock = parseInt(tile.dataset.stock);

    addProductToCart({ id, nom, prix, code, stock });
}

// ── Ajouter un produit au panier (générique) ─────────
function addProductToCart(p) {
    const existing = cart.find(item => item.id === p.id);

    if (existing) {
        if (existing.quantite >= existing.stock) {
            showNotif(`⚠️ Stock insuffisant pour ${p.nom} (max ${existing.stock})`, 'error');
            return;
        }
        existing.quantite++;
    } else {
        if (p.stock <= 0) {
            showNotif(`❌ ${p.nom} est en rupture de stock`, 'error');
            return;
        }
        cart.push({ id: p.id, nom: p.nom, prix: p.prix, code: p.code, quantite: 1, stock: p.stock });
    }

    renderCart();
    showNotif(`✅ ${p.nom} ajouté`, 'success', 1500);
}

// ── Rendu du panier ──────────────────────────────────
function renderCart() {
    const container  = document.getElementById('cartItems');
    const emptyEl    = document.getElementById('cartEmpty');
    const countBadge = document.getElementById('cartCount');

    // Supprimer les anciennes lignes (pas l'empty)
    container.querySelectorAll('.cart-row').forEach(r => r.remove());

    if (cart.length === 0) {
        emptyEl.style.display = '';
        countBadge.textContent = '0 article';
        updateSummary();
        return;
    }

    emptyEl.style.display = 'none';
    const totalArticles = cart.reduce((s, i) => s + i.quantite, 0);
    countBadge.textContent = `${totalArticles} article${totalArticles > 1 ? 's' : ''}`;

    cart.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'cart-row';
        row.innerHTML = `
            <div class="cart-row-info">
                <span class="cart-row-name">${escHtml(item.nom)}</span>
                <span class="cart-row-code">${escHtml(item.code)}</span>
            </div>
            <div class="cart-row-controls">
                <button class="qty-btn" onclick="changeQty(${index}, -1)">−</button>
                <span class="qty-val">${item.quantite}</span>
                <button class="qty-btn" onclick="changeQty(${index},  1)">+</button>
            </div>
            <div class="cart-row-price">
                <span class="cart-row-unit">${fmtPrix(item.prix)}</span>
                <span class="cart-row-total">${fmtPrix(item.prix * item.quantite)}</span>
            </div>
            <button class="cart-row-del" onclick="removeFromCart(${index})" title="Supprimer">🗑️</button>
        `;
        container.appendChild(row);
    });

    updateSummary();
}

// ── Changer la quantité d'un article ─────────────────
function changeQty(index, delta) {
    const item = cart[index];
    const newQty = item.quantite + delta;

    if (newQty <= 0) {
        removeFromCart(index);
        return;
    }
    if (newQty > item.stock) {
        showNotif(`⚠️ Stock max : ${item.stock}`, 'error');
        return;
    }
    item.quantite = newQty;
    renderCart();
}

// ── Supprimer un article ──────────────────────────────
function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

// ── Mettre à jour le récapitulatif ───────────────────
function updateSummary() {
    const subtotal  = cart.reduce((s, i) => s + i.prix * i.quantite, 0);
    const remisePct = parseFloat(document.getElementById('remiseInput').value) || 0;
    const remiseMt  = subtotal * (remisePct / 100);
    const total     = subtotal - remiseMt;

    document.getElementById('summarySubtotal').textContent = fmtPrix(subtotal);
    document.getElementById('summaryRemise').textContent   = '- ' + fmtPrix(remiseMt);
    document.getElementById('summaryTotal').textContent    = fmtPrix(total);

    // Montants rapides
    updateQuickAmounts(total);
    updateMonnaie();

    // Activer / désactiver le bouton valider
    const recu  = parseFloat(document.getElementById('montantRecu').value) || 0;
    const btnV  = document.getElementById('btnValiderVente');
    btnV.disabled = !(cart.length > 0 && recu >= total && total > 0);
}

// ── Boutons montants rapides ──────────────────────────
function updateQuickAmounts(total) {
    const container = document.getElementById('quickAmounts');
    const amounts   = [
        Math.ceil(total / 100) * 100,
        Math.ceil(total / 200) * 200,
        Math.ceil(total / 500) * 500,
        Math.ceil(total / 1000) * 1000,
    ];
    const uniq = [...new Set(amounts)].filter(a => a >= total).slice(0, 4);

    container.innerHTML = '';
    uniq.forEach(a => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'quick-amount-btn';
        btn.textContent = fmtNb(a) + ' DA';
        btn.onclick = () => {
            document.getElementById('montantRecu').value = a;
            updateMonnaie();
        };
        container.appendChild(btn);
    });
}

// ── Calcul monnaie ────────────────────────────────────
function updateMonnaie() {
    const subtotal  = cart.reduce((s, i) => s + i.prix * i.quantite, 0);
    const remisePct = parseFloat(document.getElementById('remiseInput').value) || 0;
    const total     = subtotal - subtotal * (remisePct / 100);
    const recu      = parseFloat(document.getElementById('montantRecu').value) || 0;
    const monnaie   = recu - total;

    const monnaieEl = document.getElementById('monnaieVal');
    const monnaieBox= document.getElementById('monnaieDisplay');
    const btnV      = document.getElementById('btnValiderVente');

    if (recu <= 0 || cart.length === 0) {
        monnaieEl.textContent = '–';
        monnaieEl.style.color = '';
        monnaieBox.classList.remove('monnaie-ok', 'monnaie-err');
        btnV.disabled = true;
        return;
    }

    if (monnaie < 0) {
        monnaieEl.textContent = '⚠️ Insuffisant (' + fmtPrix(Math.abs(monnaie)) + ' manquant)';
        monnaieEl.style.color = '#f87171';
        monnaieBox.classList.remove('monnaie-ok');
        monnaieBox.classList.add('monnaie-err');
        btnV.disabled = true;
    } else {
        monnaieEl.textContent = fmtPrix(monnaie);
        monnaieEl.style.color = '#4ade80';
        monnaieBox.classList.add('monnaie-ok');
        monnaieBox.classList.remove('monnaie-err');
        btnV.disabled = !(total > 0 && cart.length > 0);
    }
}

// ── Vider le panier ───────────────────────────────────
document.getElementById('btnAnnulerVente').addEventListener('click', () => {
    if (cart.length === 0) return;
    if (confirm('Vider tout le panier ?')) {
        cart = [];
        document.getElementById('montantRecu').value = '';
        document.getElementById('remiseInput').value = '';
        renderCart();
    }
});

// ── Remise ────────────────────────────────────────────
document.getElementById('remiseInput').addEventListener('input', updateSummary);
document.getElementById('montantRecu').addEventListener('input', updateMonnaie);

// ── Recherche produit texte ───────────────────────────
let searchTimeout;
let usbScanInProgress = false; // vrai pendant un scan USB → bloque le debounce

document.getElementById('posSearch').addEventListener('input', function () {
    if (usbScanInProgress) return; // scanner USB en cours → ignorer l'événement input
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 2) return;

    searchTimeout = setTimeout(async () => {
        const resp = await fetch(`ajax/pos_search.php?q=${encodeURIComponent(q)}&csrf_token=${CSRF}`);
        const data = await resp.json();
        if (data.success && data.produits.length === 1) {
            // Un seul résultat → ajouter directement
            addProductToCart(data.produits[0]);
            document.getElementById('posSearch').value = '';
        } else if (data.success && data.produits.length > 1) {
            showSearchResults(data.produits);
        } else {
            showNotif('❌ Produit introuvable : ' + q, 'error');
        }
    }, 300);
});

document.getElementById('posSearch').addEventListener('keydown', async function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const q = this.value.trim();
    if (!q) return;

    const resp = await fetch(`ajax/pos_search.php?q=${encodeURIComponent(q)}&csrf_token=${CSRF}`);
    const data = await resp.json();

    if (data.success && data.produits.length >= 1) {
        addProductToCart(data.produits[0]);
        this.value = '';
    } else {
        showNotif('❌ Produit introuvable', 'error');
    }
});

// ── Dropdown résultats recherche ─────────────────────
function showSearchResults(produits) {
    let dropdown = document.getElementById('searchDropdown');
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.id = 'searchDropdown';
        dropdown.className = 'search-dropdown';
        document.getElementById('posSearch').parentNode.appendChild(dropdown);
    }
    dropdown.innerHTML = '';
    produits.slice(0, 8).forEach(p => {
        const item = document.createElement('div');
        item.className = 'search-dropdown-item';
        item.innerHTML = `
            <span class="sd-name">${escHtml(p.nom)}</span>
            <span class="sd-price">${fmtPrix(p.prix)}</span>
            <span class="sd-stock ${p.stock <= 0 ? 'out' : ''}">${p.stock > 0 ? p.stock + ' en stock' : 'Rupture'}</span>
        `;
        if (p.stock > 0) {
            item.onclick = () => {
                addProductToCart(p);
                dropdown.remove();
                document.getElementById('posSearch').value = '';
            };
        } else {
            item.style.opacity = '.5';
        }
        dropdown.appendChild(item);
    });
    document.addEventListener('click', () => dropdown.remove(), { once: true });
}

// ════════════════════════════════════════════════════════════════════════
// SCANNER USB HID (caisse POS) — HENEX et compatibles
// Écoute les frappes rapides sur #posSearch (ou document sans focus)
// et court-circuite le debounce de recherche normale.
// ════════════════════════════════════════════════════════════════════════
(function () {
    const USB_SPEED = 50;  // ms max entre deux touches → mode scanner USB

    let usbBuf  = '';   // buffer des caractères accumulés
    let usbTime = 0;    // timestamp de la dernière touche

    const posSearchEl = document.getElementById('posSearch');

    document.addEventListener('keydown', function (e) {
        // ── Ne pas intercepter un autre champ de saisie ──────────────────
        const active = document.activeElement;
        const isOtherInput = active &&
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName) &&
            active !== posSearchEl;
        if (isOtherInput) return;

        const now     = Date.now();
        const elapsed = now - usbTime;

        // ── Touche Entrée : valider le scan USB ──────────────────────────
        if (e.key === 'Enter') {
            if (usbBuf.length >= 4) {
                e.preventDefault();
                e.stopPropagation();

                const code = usbBuf;
                usbBuf  = '';
                usbTime = 0;
                usbScanInProgress = false;

                console.log('%c🔌 MODE SCANNER USB (caisse) — code : ' + code,
                    'color:#4ade80; font-weight:bold');

                // Arrêter la caméra si elle tourne
                if (posScanning) stopPosScanner();

                // Annuler tout debounce de recherche en attente
                clearTimeout(searchTimeout);
                posSearchEl.value = '';

                // Rechercher et ajouter au panier immédiatement
                fetch(`ajax/pos_search.php?q=${encodeURIComponent(code)}&csrf_token=${CSRF}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.produits.length > 0) {
                            playBeep();
                            addProductToCart(data.produits[0]);
                        } else {
                            showNotif('❌ Produit non trouvé : ' + code, 'error');
                        }
                    })
                    .catch(function () {
                        showNotif('❌ Erreur réseau', 'error');
                    });
            }
            usbBuf  = '';
            usbTime = 0;
            usbScanInProgress = false;
            return;
        }

        // ── Caractère imprimable ─────────────────────────────────────────
        if (e.key.length === 1) {
            if (usbBuf.length === 0 || elapsed < USB_SPEED) {
                usbBuf += e.key;

                // Dès le 2e caractère rapide : bloquer l'événement input
                // et masquer la frappe dans le champ (le buffer sera injecté à la fin)
                if (usbBuf.length > 1) {
                    e.preventDefault();
                    usbScanInProgress = true;
                    clearTimeout(searchTimeout);
                }
            } else {
                // Frappe lente → saisie humaine normale
                usbBuf            = e.key;
                usbScanInProgress = false;
            }
            usbTime = now;
        }
    });
})();

// ── Scanner caméra POS ───────────────────────────────
let posScanning = false;

document.getElementById('posScanBtn').addEventListener('click', () => {
    if (posScanning) { stopPosScanner(); return; }
    startPosScanner();
});

document.getElementById('posStopScan').addEventListener('click', stopPosScanner);

function startPosScanner() {
    console.log('%c📷 MODE CAMÉRA (caisse) — démarrage Quagga',
        'color:#60a5fa; font-weight:bold');
    document.getElementById('posScannerContainer').classList.remove('hidden');
    document.getElementById('posScanStatus').textContent = '⏳ Démarrage…';
    document.getElementById('posScanBtn').textContent = '⏹';

    Quagga.init({
        inputStream: {
            name: 'Live', type: 'LiveStream',
            target: document.getElementById('posInteractive'),
            constraints: { facingMode: 'environment', width: { min: 640 }, height: { min: 480 } },
        },
        decoder: { readers: ['ean_reader','ean_8_reader','code_128_reader','code_39_reader','upc_reader'], multiple: false },
        locate: true, frequency: 5, numOfWorkers: 2,
    }, err => {
        if (err) {
            document.getElementById('posScanStatus').textContent = '❌ ' + (err.message || 'Erreur caméra');
            stopPosScanner();
            return;
        }
        Quagga.start();
        posScanning = true;
        document.getElementById('posScanStatus').textContent = '📷 Pointez vers le code-barres…';
    });

    let lastCode = '', debounce = null;
    Quagga.onDetected(async data => {
        const code = data.codeResult.code;
        if (!code || code === lastCode) return;
        lastCode = code;
        clearTimeout(debounce);
        debounce = setTimeout(async () => {
            playBeep();
            stopPosScanner();
            const resp = await fetch(`ajax/pos_search.php?q=${encodeURIComponent(code)}&csrf_token=${CSRF}`);
            const res  = await resp.json();
            if (res.success && res.produits.length > 0) {
                addProductToCart(res.produits[0]);
            } else {
                showNotif('❌ Produit non trouvé : ' + code, 'error');
            }
        }, 300);
    });
}

function stopPosScanner() {
    try { Quagga.offDetected(); Quagga.stop(); } catch(e) {}
    posScanning = false;
    document.getElementById('posScannerContainer').classList.add('hidden');
    document.getElementById('posScanBtn').textContent = '📷';
    document.querySelectorAll('#posInteractive video').forEach(v => {
        if (v.srcObject) { v.srcObject.getTracks().forEach(t => t.stop()); v.srcObject = null; }
    });
}

// ── Son bip ──────────────────────────────────────────
function playBeep() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator(), gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.value = 1200; osc.type = 'square';
        gain.gain.setValueAtTime(0.1, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.15);
    } catch(e) {}
}

// ── Valider la vente ──────────────────────────────────
document.getElementById('btnValiderVente').addEventListener('click', async () => {
    const subtotal  = cart.reduce((s, i) => s + i.prix * i.quantite, 0);
    const remisePct = parseFloat(document.getElementById('remiseInput').value) || 0;
    const remiseMt  = subtotal * (remisePct / 100);
    const total     = subtotal - remiseMt;
    const recu      = parseFloat(document.getElementById('montantRecu').value) || 0;

    if (cart.length === 0 || recu < total) return;

    const btn = document.getElementById('btnValiderVente');
    btn.disabled = true;
    btn.textContent = '⏳ Traitement…';

    const payload = {
        csrf_token: CSRF,
        items:      cart.map(i => ({ id: i.id, quantite: i.quantite, prix: i.prix })),
        remise:     remiseMt,
        total:      total,
        montant_recu: recu,
        monnaie:    recu - total,
    };

    try {
        const resp = await fetch('ajax/process_sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await resp.json();

        if (data.success) {
            afficherRecu(data.vente);
        } else {
            showNotif('❌ ' + data.message, 'error');
            btn.disabled = false;
            btn.textContent = '✅ Valider la vente';
        }
    } catch (err) {
        showNotif('❌ Erreur réseau', 'error');
        btn.disabled = false;
        btn.textContent = '✅ Valider la vente';
    }
});

// ── Afficher le reçu ─────────────────────────────────
function afficherRecu(vente) {
    document.getElementById('recuNumero').textContent = 'Ticket N° ' + vente.numero;
    document.getElementById('recuDate').textContent   = vente.date;

    const tbody = document.getElementById('recuItems');
    tbody.innerHTML = `
        <tr class="recu-th"><th>Article</th><th>Qté</th><th>P.U</th><th>Total</th></tr>
    `;
    vente.items.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escHtml(item.nom)}</td>
            <td>${item.quantite}</td>
            <td>${fmtPrix(item.prix_unit)}</td>
            <td>${fmtPrix(item.sous_total)}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('recuTotaux').innerHTML = `
        <div class="recu-total-row"><span>Sous-total</span><span>${fmtPrix(vente.subtotal)}</span></div>
        ${vente.remise > 0 ? `<div class="recu-total-row"><span>Remise</span><span>- ${fmtPrix(vente.remise)}</span></div>` : ''}
        <div class="recu-total-row recu-total-final"><span>TOTAL</span><span>${fmtPrix(vente.total_final)}</span></div>
        <div class="recu-total-row"><span>Reçu</span><span>${fmtPrix(vente.montant_recu)}</span></div>
        <div class="recu-total-row recu-monnaie"><span>Monnaie rendue</span><span>${fmtPrix(vente.monnaie)}</span></div>
    `;

    document.getElementById('modalRecu').classList.remove('hidden');
}

// ── Nouvelle vente ────────────────────────────────────
document.getElementById('btnNouvelleVente').addEventListener('click', () => {
    cart = [];
    document.getElementById('montantRecu').value = '';
    document.getElementById('remiseInput').value = '';
    document.getElementById('btnValiderVente').textContent = '✅ Valider la vente';
    document.getElementById('btnValiderVente').disabled = true;
    renderCart();
    document.getElementById('modalRecu').classList.add('hidden');
    document.getElementById('posSearch').focus();
});

// ── Utilitaires ───────────────────────────────────────
function fmtPrix(v) {
    return new Intl.NumberFormat('fr-DZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v) + ' DA';
}
function fmtNb(v) {
    return new Intl.NumberFormat('fr-DZ').format(v);
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Init
renderCart();
</script>
</body>
</html>