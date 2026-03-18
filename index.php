<?php
/**
 * index.php – Boutique publique LEHNA
 * Affichage des produits avec recherche et filtres
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// ── Paramètres de navigation ──────────────────────────────
$search    = sanitizeString($_GET['search']    ?? '');
$categorie = sanitizeString($_GET['cat']       ?? '');
$page      = max(1, (int)($_GET['page']        ?? 1));

$result     = getProduits($page, ITEMS_PER_PAGE, $search, $categorie);
$categories = getCategories();
$stats      = getStats();

$pageTitle = APP_NAME;
if ($search)    $pageTitle = 'Recherche : ' . $search . ' – ' . APP_NAME;
if ($categorie) $pageTitle = $categorie . ' – ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e(APP_NAME) ?> – Votre alimentation générale de proximité. Produits frais, épicerie, boissons et plus.">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<!-- ═══════════════ HEADER ═══════════════ -->
<header class="site-header">
    <div class="header-inner">
        <div class="site-logo">
            <div class="logo-icon">🛒</div>
            <div class="logo-text">
                <h1>Alimentation<br>Générale <strong>LEHNA</strong></h1>
                <small>Épicerie fine & produits du quotidien</small>
            </div>
        </div>

        <!-- Barre de recherche -->
        <div class="search-bar">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput"
                   placeholder="Rechercher un produit…"
                   value="<?= e($search) ?>"
                   autocomplete="off"
                   aria-label="Rechercher un produit">
        </div>

        <div class="header-right">
            <span class="header-badge">
                📦 <?= number_format($stats['total_produits']) ?> produits
            </span>
        </div>
    </div>
</header>

<!-- ═══════════════ HERO ═══════════════ -->
<section class="hero">
    <div class="hero-inner">
        <h2>Votre épicerie de<br>confiance à Alger</h2>
        <p>Produits frais, épicerie, boissons et articles ménagers – tout ce dont vous avez besoin au quotidien.</p>
        <div class="hero-badges">
            <span class="hero-badge">✅ Stock mis à jour en temps réel</span>
            <span class="hero-badge">🏷️ Prix compétitifs</span>
            <span class="hero-badge">🚀 Disponibilité vérifiée</span>
        </div>
    </div>
</section>

<!-- ═══════════════ CATALOGUE ═══════════════ -->
<section class="catalogue-section">

    <!-- Filtres catégories -->
    <div class="category-filters" role="group" aria-label="Filtrer par catégorie">
        <button class="cat-filter-btn <?= $categorie === '' ? 'active' : '' ?>"
                data-cat="all">
            Tous les produits
        </button>
        <?php foreach ($categories as $cat): ?>
            <button class="cat-filter-btn <?= $categorie === $cat ? 'active' : '' ?>"
                    data-cat="<?= e($cat) ?>">
                <?= e($cat) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Compteur de résultats -->
    <p class="results-info">
        <span id="resultCount"><?= number_format($result['total']) ?> produit<?= $result['total'] > 1 ? 's' : '' ?></span>
        <?php if ($search || $categorie): ?>
            trouvé<?= $result['total'] > 1 ? 's' : '' ?>
            <?= $search ? 'pour « <strong>' . e($search) . '</strong> »' : '' ?>
            <?= $categorie ? 'dans <strong>' . e($categorie) . '</strong>' : '' ?>
            · <a href="<?= APP_URL ?>">Réinitialiser</a>
        <?php endif; ?>
    </p>

    <!-- Grille de produits -->
    <div class="products-grid" id="productsGrid">

        <?php if (empty($result['produits'])): ?>
            <div class="no-products" id="noProducts">
                <p style="font-size:2.5rem">🔍</p>
                <p>Aucun produit trouvé.</p>
                <?php if ($search || $categorie): ?>
                    <a href="<?= APP_URL ?>" style="margin-top:12px;display:inline-block">← Voir tous les produits</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Élément "vide" caché pour le filtrage JS -->
            <div class="no-products" id="noProducts" style="display:none;grid-column:1/-1">
                <p style="font-size:2.5rem">🔍</p>
                <p>Aucun résultat pour votre recherche.</p>
            </div>

            <?php foreach ($result['produits'] as $produit): ?>
                <?php
                    // Détermine le chemin de l'image
                    $imgSrc = ($produit['image'] && $produit['image'] !== 'default.jpg' && file_exists(UPLOAD_PATH . $produit['image']))
                              ? UPLOAD_URL . $produit['image']
                              : APP_URL . '/assets/images/default.php';

                    // Badge stock
                    $qty = (int)$produit['quantite'];
                    if ($qty === 0) {
                        $badgeClass = 'out-stock';
                        $badgeText  = 'Rupture';
                    } elseif ($qty <= 10) {
                        $badgeClass = 'low-stock';
                        $badgeText  = 'Presque épuisé';
                    } else {
                        $badgeClass = 'in-stock';
                        $badgeText  = 'Disponible';
                    }
                ?>
                <article class="product-card"
                         data-nom="<?= e(mb_strtolower($produit['nom'])) ?>"
                         data-code="<?= e($produit['code_barre']) ?>"
                         data-categorie="<?= e($produit['categorie']) ?>">

                    <!-- Image -->
                    <div class="product-img-wrap">
                        <img src="<?= e($imgSrc) ?>"
                             alt="<?= e($produit['nom']) ?>"
                             loading="lazy"
                             width="300" height="300">
                        <span class="stock-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    </div>

                    <!-- Infos -->
                    <div class="product-info">
                        <div class="product-category"><?= e($produit['categorie']) ?></div>
                        <h3 class="product-name"><?= e($produit['nom']) ?></h3>

                        <div style="margin-top:12px">
                            <div class="product-price">
                                <?= number_format((float)$produit['prix'], 2, ',', ' ') ?>
                                <span class="currency">DA</span>
                            </div>
                            <div class="product-qty">
                                <?php if ($qty === 0): ?>
                                    <span style="color:#c84646">❌ En rupture de stock</span>
                                <?php elseif ($qty <= 10): ?>
                                    <span style="color:#d4a35a">⚠️ Plus que <?= $qty ?> en stock</span>
                                <?php else: ?>
                                    <span style="color:#2d6a4f">✅ En stock (<?= $qty ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- /products-grid -->

    <!-- ── Pagination ── -->
    <?php if ($result['pages'] > 1): ?>
        <nav class="pub-pagination" aria-label="Pagination">
            <?php if ($result['current_page'] > 1): ?>
                <a href="?page=<?= $result['current_page'] - 1 ?>&search=<?= urlencode($search) ?>&cat=<?= urlencode($categorie) ?>"
                   class="pub-page-link">← Précédent</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&cat=<?= urlencode($categorie) ?>"
                   class="pub-page-link <?= $i === $result['current_page'] ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($result['current_page'] < $result['pages']): ?>
                <a href="?page=<?= $result['current_page'] + 1 ?>&search=<?= urlencode($search) ?>&cat=<?= urlencode($categorie) ?>"
                   class="pub-page-link">Suivant →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

</section>

<!-- ═══════════════ FOOTER ═══════════════ -->
<footer class="site-footer">
    <p>
        <strong><?= e(APP_NAME) ?></strong> · Alimentation Générale ·
        <a href="<?= APP_URL ?>/admin/">Administration</a>
    </p>
    <p style="margin-top:6px;font-size:.78rem;opacity:.6">
        © <?= date('Y') ?> LEHNA – Tous droits réservés
    </p>
</footer>

<script src="<?= APP_URL ?>/assets/js/store.js"></script>
</body>
</html>
