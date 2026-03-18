<?php
/**
 * admin/prices.php
 * Gestion rapide des prix – mise à jour AJAX
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Récupère tous les produits actifs (sans pagination pour gestion rapide)
$pdo  = getPDO();
$stmt = $pdo->query('SELECT id, nom, prix, quantite, categorie FROM produits WHERE actif=1 ORDER BY categorie, nom');
$produits = $stmt->fetchAll();

$categories = [];
foreach ($produits as $p) {
    $categories[$p['categorie']][] = $p;
}
ksort($categories);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des prix – <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>
<div class="admin-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">

    <div class="page-header">
        <div>
            <h1 class="page-title">Gestion des prix</h1>
            <p class="page-subtitle">Modifiez les prix directement dans le tableau – sauvegarde automatique</p>
        </div>
        <div style="display:flex;gap:10px">
            <input type="text" id="priceSearch" placeholder="🔍 Filtrer les produits…"
                   class="filter-input" style="max-width:260px">
            <button id="saveAllBtn" class="btn btn-primary" onclick="saveAll()">💾 Tout sauvegarder</button>
        </div>
    </div>

    <div class="alert alert-info" style="margin-bottom:24px">
        💡 Modifiez un prix puis appuyez sur <kbd>Entrée</kbd> ou cliquez hors du champ pour sauvegarder automatiquement.
    </div>

    <?php if (empty($produits)): ?>
        <div class="empty-state-box">Aucun produit disponible.</div>
    <?php else: ?>

    <?php foreach ($categories as $cat => $prods): ?>
    <div class="card price-category-card" style="margin-bottom:20px">
        <div class="card-header">
            <h2 class="card-title">📁 <?= e($cat) ?> <small style="color:var(--text-muted)">(<?= count($prods) ?> produits)</small></h2>
        </div>
        <table class="data-table data-table-full price-table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th style="width:160px">Prix actuel (DA)</th>
                    <th style="width:120px">Stock</th>
                    <th style="width:100px">Statut</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($prods as $p): ?>
                <tr data-id="<?= $p['id'] ?>" class="price-row">
                    <td class="product-name-cell"><?= e($p['nom']) ?></td>
                    <td>
                        <div class="price-cell-wrapper">
                            <input type="number" class="price-field form-control"
                                   value="<?= htmlspecialchars($p['prix']) ?>"
                                   data-original="<?= htmlspecialchars($p['prix']) ?>"
                                   data-id="<?= $p['id'] ?>"
                                   step="1" min="0" style="max-width:120px">
                            <span class="price-status"></span>
                        </div>
                    </td>
                    <td><?= (int)$p['quantite'] ?></td>
                    <td>
                        <?php if ($p['quantite'] == 0): ?>
                            <span class="badge badge-danger">Rupture</span>
                        <?php elseif ($p['quantite'] <= 10): ?>
                            <span class="badge badge-warning">Stock bas</span>
                        <?php else: ?>
                            <span class="badge badge-success">OK</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <!-- Compteur de modifications -->
    <div id="changesBar" class="changes-bar hidden">
        <span id="changesCount">0</span> modification(s) en attente ·
        <button onclick="saveAll()" class="btn btn-sm btn-primary">Sauvegarder tout</button>
        <button onclick="cancelAll()" class="btn btn-sm">Annuler</button>
    </div>

    <?php endif; ?>

</main>
</div>

<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
<script>
const CSRF_TOKEN = '<?= csrfToken() ?>';
const pendingChanges = new Map();

// ── Sauvegarde d'un prix individuel ──────────────────────
async function saveSinglePrice(input) {
    const id    = input.dataset.id;
    const prix  = parseFloat(input.value);
    const orig  = parseFloat(input.dataset.original);
    const status = input.closest('tr').querySelector('.price-status');

    if (isNaN(prix) || prix < 0) {
        input.style.borderColor = 'var(--danger)';
        showNotif('Prix invalide', 'error');
        return;
    }
    if (prix === orig) return; // Aucun changement

    status.textContent = '⏳';
    input.style.borderColor = 'var(--gold)';

    try {
        const resp = await fetch('ajax/update_price.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&prix=${prix}&csrf_token=${CSRF_TOKEN}`
        });
        const data = await resp.json();

        if (data.success) {
            status.textContent = '✅';
            input.dataset.original = prix;
            input.style.borderColor = 'var(--success)';
            pendingChanges.delete(id);
            updateChangesBar();
            showNotif('Prix mis à jour !', 'success');
            setTimeout(() => { status.textContent = ''; input.style.borderColor = ''; }, 2000);
        } else {
            status.textContent = '❌';
            input.style.borderColor = 'var(--danger)';
            showNotif('❌ ' + data.message, 'error');
        }
    } catch {
        status.textContent = '❌';
        showNotif('Erreur réseau', 'error');
    }
}

// ── Sauvegarde globale ────────────────────────────────────
async function saveAll() {
    const fields = document.querySelectorAll('.price-field');
    const promises = [];
    fields.forEach(input => {
        if (parseFloat(input.value) !== parseFloat(input.dataset.original)) {
            promises.push(saveSinglePrice(input));
        }
    });
    if (promises.length === 0) {
        showNotif('Aucune modification à sauvegarder', 'info');
        return;
    }
    await Promise.all(promises);
    showNotif(`${promises.length} prix mis à jour !`, 'success');
}

// ── Annuler toutes les modifications ─────────────────────
function cancelAll() {
    document.querySelectorAll('.price-field').forEach(input => {
        input.value = input.dataset.original;
        input.style.borderColor = '';
    });
    pendingChanges.clear();
    updateChangesBar();
}

function updateChangesBar() {
    const bar = document.getElementById('changesBar');
    const cnt = document.getElementById('changesCount');
    if (pendingChanges.size > 0) {
        bar.classList.remove('hidden');
        cnt.textContent = pendingChanges.size;
    } else {
        bar.classList.add('hidden');
    }
}

// ── Événements sur les champs de prix ────────────────────
document.querySelectorAll('.price-field').forEach(input => {
    input.addEventListener('change', () => {
        const id = input.dataset.id;
        if (parseFloat(input.value) !== parseFloat(input.dataset.original)) {
            pendingChanges.set(id, true);
        } else {
            pendingChanges.delete(id);
        }
        updateChangesBar();
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveSinglePrice(input);
        }
    });

    input.addEventListener('blur', () => saveSinglePrice(input));
});

// ── Filtrage produits ─────────────────────────────────────
document.getElementById('priceSearch')?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.price-row').forEach(row => {
        const name = row.querySelector('.product-name-cell')?.textContent.toLowerCase() ?? '';
        row.style.display = name.includes(q) ? '' : 'none';
    });
    // Masquer les catégories vides
    document.querySelectorAll('.price-category-card').forEach(card => {
        const visible = card.querySelectorAll('.price-row:not([style*="display: none"])').length;
        card.style.display = visible ? '' : 'none';
    });
});
</script>
</body>
</html>
