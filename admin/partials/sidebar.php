<?php
/* admin/partials/sidebar.php */
$current = basename($_SERVER['PHP_SELF']);
function navLink(string $file, string $label, string $icon): string {
    global $current;
    $active = ($current === $file) ? ' active' : '';
    return sprintf(
        '<a href="%s" class="nav-link%s"><span class="nav-icon">%s</span>%s</a>',
        APP_URL . '/admin/' . $file, $active, $icon, $label
    );
}
?>
<aside class="admin-sidebar">
    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-title">Principal</span>
            <?= navLink('index.php',          'Dashboard',       '📊') ?>
            <?= navLink('vente.php',          'Caisse / Vente',  '🛒') ?>
            <?= navLink('ventes_history.php', 'Historique',      '📋') ?>
            <?= navLink('products.php',       'Produits',        '📦') ?>
            <?= navLink('product_add.php',    'Ajouter',         '➕') ?>
            <?= navLink('prices.php',         'Gestion Prix',    '💲') ?>
        </div>
    </nav>
    <div class="sidebar-footer">
        <span>v<?= APP_VERSION ?></span>
    </div>
</aside>
