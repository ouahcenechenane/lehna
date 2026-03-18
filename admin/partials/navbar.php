<?php /* admin/partials/navbar.php */ ?>
<header class="admin-navbar">
    <div class="navbar-brand">
        <span class="brand-icon">🛒</span>
        <span class="brand-name"><?= e(APP_NAME) ?></span>
    </div>
    <div class="navbar-right">
        <span class="navbar-user">👤 <?= e($_SESSION['admin_nom'] ?? 'Admin') ?></span>
        <a href="<?= APP_URL ?>/index.php" class="btn btn-sm" target="_blank">🏠 Boutique</a>
        <a href="<?= APP_URL ?>/admin/logout.php" class="btn btn-danger btn-sm">Déconnexion</a>
    </div>
</header>
