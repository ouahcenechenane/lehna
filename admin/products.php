<?php
/**
 * admin/products.php
 * Liste paginée des produits avec recherche, filtres et actions rapides
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$flash = getFlash();

// ── Paramètres de recherche / filtrage ──────────
$search    = sanitizeString($_GET['search']    ?? '');
$categorie = sanitizeString($_GET['categorie'] ?? '');
$filter    = sanitizeString($_GET['filter']    ?? '');
$page      = max(1, (int) ($_GET['page'] ?? 1));

// Filtre spécial pour stock bas
if ($filter === 'low') {
    // On récupère manuellement les produits à stock bas
    $pdo    = getPDO();
    $stmt   = $pdo->query('SELECT * FROM produits WHERE actif=1 AND quantite <= 10 ORDER BY quantite ASC');
    $prods  = $stmt->fetchAll();
    $result = ['produits' => $prods, 'total' => count($prods), 'pages' => 1, 'current_page' => 1];
} else {
    $result = getProduits($page, ITEMS_PER_PAGE, $search, $categorie);
}

$categories = getCategories();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits – <?= e(APP_NAME) ?></title>
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
            <h1 class="page-title">Produits</h1>
            <p class="page-subtitle"><?= number_format($result['total']) ?> produit(s) trouvé(s)</p>
        </div>
        <a href="product_add.php" class="btn btn-primary">+ Ajouter</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <!-- ── Barre de recherche & filtres ── -->
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="🔍 Rechercher par nom ou code-barres…"
               value="<?= e($search) ?>" class="filter-input">

        <select name="categorie" class="filter-select">
            <option value="">Toutes les catégories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $categorie === $cat ? 'selected' : '' ?>>
                    <?= e($cat) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary">Filtrer</button>
        <?php if ($search || $categorie): ?>
            <a href="products.php" class="btn">✕ Réinitialiser</a>
        <?php endif; ?>
    </form>

    <!-- ── Table des produits ── -->
    <?php if (empty($result['produits'])): ?>
        <div class="empty-state-box">
            <p>🔍 Aucun produit trouvé.</p>
            <a href="product_add.php" class="btn btn-primary">Ajouter le premier produit</a>
        </div>
    <?php else: ?>
    <div class="card" style="padding:0;overflow:hidden">
        <table class="data-table data-table-full">
            <thead>
                <tr>
                    <th style="width:60px">Image</th>
                    <th>Nom</th>
                    <th>Code-barres</th>
                    <th>Catégorie</th>
                    <th>Prix</th>
                    <th>Stock</th>
                    <th style="width:160px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($result['produits'] as $p): ?>
                <?php
                    $imgSrc = ($p['image'] && $p['image'] !== 'default.jpg' && file_exists(UPLOAD_PATH . $p['image']))
                              ? UPLOAD_URL . $p['image']
                              : APP_URL . '/assets/images/default.php';
                ?>
                <tr data-id="<?= $p['id'] ?>">
                    <td>
                        <img src="<?= e($imgSrc) ?>" alt="<?= e($p['nom']) ?>"
                             class="product-thumb" loading="lazy">
                    </td>
                    <td>
                        <strong><?= e($p['nom']) ?></strong>
                        <?php if ($p['description']): ?>
                            <br><small class="text-muted"><?= e(mb_substr($p['description'], 0, 60)) ?>…</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code class="barcode-code"><?= e($p['code_barre']) ?></code>
                    </td>
                    <td>
                        <span class="badge badge-info"><?= e($p['categorie']) ?></span>
                    </td>
                    <td>
                        <!-- Prix éditable en ligne via AJAX -->
                        <div class="inline-price" data-id="<?= $p['id'] ?>">
                            <span class="price-display"><?= formatPrix((float)$p['prix']) ?></span>
                            <input type="number" class="price-input hidden" value="<?= $p['prix'] ?>"
                                   step="0.01" min="0" style="width:90px">
                            <button class="btn-icon price-edit-btn" title="Modifier le prix">✏️</button>
                            <button class="btn-icon price-save-btn hidden" title="Enregistrer">✅</button>
                        </div>
                    </td>
                    <td>
                        <?php if ($p['quantite'] == 0): ?>
                            <span class="badge badge-danger">Rupture</span>
                        <?php elseif ($p['quantite'] <= 10): ?>
                            <span class="badge badge-warning"><?= $p['quantite'] ?></span>
                        <?php else: ?>
                            <span class="badge badge-success"><?= $p['quantite'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="product_edit.php?id=<?= $p['id'] ?>" class="btn btn-sm" title="Modifier">✏️ Éditer</a>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $p['id'] ?>" data-nom="<?= e($p['nom']) ?>" title="Supprimer">🗑️</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Pagination ── -->
    <?php if ($result['pages'] > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&categorie=<?= urlencode($categorie) ?>"
               class="page-link <?= $i === $result['current_page'] ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</main>
</div>

<!-- Modal de confirmation suppression -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-box">
        <h3>🗑️ Confirmer la suppression</h3>
        <p>Supprimer le produit <strong id="deleteProductName"></strong> ?<br>
           <small>Cette action est irréversible.</small></p>
        <div class="modal-actions">
            <button id="cancelDelete" class="btn">Annuler</button>
            <button id="confirmDelete" class="btn btn-danger" data-id="">Supprimer</button>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
<script>
// ── Suppression via AJAX ───────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        const id  = btn.dataset.id;
        const nom = btn.dataset.nom;
        document.getElementById('deleteProductName').textContent = nom;
        document.getElementById('confirmDelete').dataset.id = id;
        document.getElementById('deleteModal').classList.remove('hidden');
    });
});

document.getElementById('cancelDelete')?.addEventListener('click', () => {
    document.getElementById('deleteModal').classList.add('hidden');
});

document.getElementById('confirmDelete')?.addEventListener('click', async function () {
    const id = this.dataset.id;
    this.textContent = 'Suppression…';
    this.disabled = true;

    const resp = await fetch('ajax/delete_product.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${id}&csrf_token=<?= csrfToken() ?>`
    });
    const data = await resp.json();

    if (data.success) {
        document.querySelector(`tr[data-id="${id}"]`)?.remove();
        document.getElementById('deleteModal').classList.add('hidden');
        showNotif('✅ ' + data.message, 'success');
    } else {
        showNotif('❌ ' + data.message, 'error');
        document.getElementById('deleteModal').classList.add('hidden');
    }
});

// ── Édition inline du prix ────────────────────────
document.querySelectorAll('.inline-price').forEach(wrapper => {
    const display  = wrapper.querySelector('.price-display');
    const input    = wrapper.querySelector('.price-input');
    const editBtn  = wrapper.querySelector('.price-edit-btn');
    const saveBtn  = wrapper.querySelector('.price-save-btn');

    editBtn.addEventListener('click', () => {
        display.classList.add('hidden');
        editBtn.classList.add('hidden');
        input.classList.remove('hidden');
        saveBtn.classList.remove('hidden');
        input.focus();
    });

    saveBtn.addEventListener('click', async () => {
        const id    = wrapper.dataset.id;
        const prix  = parseFloat(input.value);
        if (isNaN(prix) || prix < 0) { showNotif('Prix invalide', 'error'); return; }

        saveBtn.textContent = '⏳';
        const resp = await fetch('ajax/update_price.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}&prix=${prix}&csrf_token=<?= csrfToken() ?>`
        });
        const data = await resp.json();

        if (data.success) {
            display.textContent = data.prix_format;
            display.classList.remove('hidden');
            editBtn.classList.remove('hidden');
            input.classList.add('hidden');
            saveBtn.classList.add('hidden');
            saveBtn.textContent = '✅';
            showNotif('Prix mis à jour !', 'success');
        } else {
            showNotif('❌ ' + data.message, 'error');
            saveBtn.textContent = '✅';
        }
    });
});
</script>
</body>
</html>
