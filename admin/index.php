<?php
/**
 * admin/index.php
 * Tableau de bord – statistiques et vue d'ensemble
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$stats = getStats();
$flash = getFlash();

// Produits en stock faible (≤ 10) pour alerte rapide
$pdo       = getPDO();
$stmtLow   = $pdo->query('SELECT * FROM produits WHERE actif=1 AND quantite > 0 AND quantite <= 10 ORDER BY quantite ASC LIMIT 5');
$lowStock  = $stmtLow->fetchAll();

// Derniers produits ajoutés
$stmtRecent = $pdo->query('SELECT * FROM produits WHERE actif=1 ORDER BY created_at DESC LIMIT 5');
$recent     = $stmtRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – <?= e(APP_NAME) ?></title>
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
                <h1 class="page-title">Tableau de bord</h1>
                <p class="page-subtitle">Bienvenue, <?= e($_SESSION['admin_nom']) ?> · <?= date('d/m/Y H:i') ?></p>
            </div>
            <a href="product_add.php" class="btn btn-primary">+ Ajouter un produit</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <!-- ── Cartes statistiques ── -->
        <div class="stats-grid">
            <div class="stat-card" style="--accent:#d4a35a">
                <div class="stat-icon">📦</div>
                <div class="stat-body">
                    <span class="stat-value"><?= number_format($stats['total_produits']) ?></span>
                    <span class="stat-label">Produits actifs</span>
                </div>
            </div>
            <div class="stat-card" style="--accent:#4ade80">
                <div class="stat-icon">🏪</div>
                <div class="stat-body">
                    <span class="stat-value"><?= number_format($stats['total_stock']) ?></span>
                    <span class="stat-label">Articles en stock</span>
                </div>
            </div>
            <div class="stat-card" style="--accent:#60a5fa">
                <div class="stat-icon">💰</div>
                <div class="stat-body">
                    <span class="stat-value"><?= formatPrix($stats['valeur_stock']) ?></span>
                    <span class="stat-label">Valeur du stock</span>
                </div>
            </div>
            <div class="stat-card" style="--accent:#f87171">
                <div class="stat-icon">⚠️</div>
                <div class="stat-body">
                    <span class="stat-value"><?= number_format($stats['ruptures']) ?></span>
                    <span class="stat-label">Ruptures de stock</span>
                </div>
            </div>
        </div>

        <!-- ── Contenu en deux colonnes ── -->
        <div class="dashboard-grid">

            <!-- Stock bas -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">⚠️ Stock bas (≤ 10)</h2>
                    <a href="products.php?filter=low" class="btn btn-sm">Voir tout</a>
                </div>
                <?php if (empty($lowStock)): ?>
                    <p class="empty-state">✅ Aucun produit en stock critique.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Produit</th><th>Quantité</th><th>Prix</th></tr></thead>
                        <tbody>
                        <?php foreach ($lowStock as $p): ?>
                            <tr>
                                <td><?= e($p['nom']) ?></td>
                                <td>
                                    <span class="badge badge-danger"><?= $p['quantite'] ?></span>
                                </td>
                                <td><?= formatPrix((float)$p['prix']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Derniers ajouts -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🆕 Derniers ajouts</h2>
                    <a href="products.php" class="btn btn-sm">Voir tout</a>
                </div>
                <?php if (empty($recent)): ?>
                    <p class="empty-state">Aucun produit pour le moment.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Produit</th><th>Catégorie</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $p): ?>
                            <tr>
                                <td><?= e($p['nom']) ?></td>
                                <td><span class="badge badge-info"><?= e($p['categorie']) ?></span></td>
                                <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div><!-- /dashboard-grid -->

        <!-- ── Actions rapides ── -->
        <div class="quick-actions">
            <h2 class="card-title" style="margin-bottom:16px">⚡ Actions rapides</h2>
            <div class="quick-actions-grid">
                <a href="product_add.php" class="quick-btn">
                    <span>➕</span> Ajouter un produit
                </a>
                <a href="product_add.php#scanner" class="quick-btn">
                    <span>📷</span> Scanner un code-barres
                </a>
                <a href="prices.php" class="quick-btn">
                    <span>💲</span> Gérer les prix
                </a>
                <a href="products.php" class="quick-btn">
                    <span>📋</span> Liste des produits
                </a>
            </div>
        </div>

    </main>
</div><!-- /admin-layout -->

<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
</body>
</html>
