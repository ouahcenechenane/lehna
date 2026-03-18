<?php
/**
 * admin/ventes_history.php
 * Historique des ventes avec détails
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo  = getPDO();
$page = max(1, (int)($_GET['page'] ?? 1));
$limit= 20;
$offset = ($page - 1) * $limit;

$dateFrom = sanitizeString($_GET['from'] ?? date('Y-m-01'));
$dateTo   = sanitizeString($_GET['to']   ?? date('Y-m-d'));

// Stats du jour
$stmtStats = $pdo->prepare("
    SELECT COUNT(*) as nb_ventes, SUM(total_final) as ca, SUM(nb_articles) as articles
    FROM ventes WHERE DATE(created_at) = CURDATE()
");
$stmtStats->execute();
$statsJour = $stmtStats->fetch();

// Stats de la période filtrée
$stmtPeriode = $pdo->prepare("
    SELECT COUNT(*) as nb_ventes, SUM(total_final) as ca
    FROM ventes WHERE DATE(created_at) BETWEEN :from AND :to
");
$stmtPeriode->execute([':from' => $dateFrom, ':to' => $dateTo]);
$statsPeriode = $stmtPeriode->fetch();

// Total pour pagination
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ventes WHERE DATE(created_at) BETWEEN :from AND :to");
$stmtCount->execute([':from' => $dateFrom, ':to' => $dateTo]);
$totalVentes = (int)$stmtCount->fetchColumn();
$totalPages  = (int)ceil($totalVentes / $limit);

// Ventes
$stmtV = $pdo->prepare("
    SELECT * FROM ventes
    WHERE DATE(created_at) BETWEEN :from AND :to
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmtV->bindValue(':from',   $dateFrom);
$stmtV->bindValue(':to',     $dateTo);
$stmtV->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmtV->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtV->execute();
$ventes = $stmtV->fetchAll();

// Récupérer les items d'une vente si demandé
$detailVenteId = (int)($_GET['detail'] ?? 0);
$detailItems   = [];
if ($detailVenteId > 0) {
    $stmtItems = $pdo->prepare('SELECT * FROM vente_items WHERE vente_id = :id ORDER BY id');
    $stmtItems->execute([':id' => $detailVenteId]);
    $detailItems = $stmtItems->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique Ventes – <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/pos.css">
</head>
<body>
<?php include __DIR__ . '/partials/navbar.php'; ?>
<div class="admin-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">

    <div class="page-header">
        <div>
            <h1 class="page-title">📋 Historique des ventes</h1>
            <p class="page-subtitle">Toutes les transactions enregistrées</p>
        </div>
        <a href="vente.php" class="btn btn-primary">🛒 Aller à la caisse</a>
    </div>

    <!-- Stats du jour -->
    <div class="stats-grid" style="margin-bottom:24px">
        <div class="stat-card" style="--accent:#d4a35a">
            <div class="stat-icon">📅</div>
            <div class="stat-body">
                <span class="stat-value"><?= number_format((int)$statsJour['nb_ventes']) ?></span>
                <span class="stat-label">Ventes aujourd'hui</span>
            </div>
        </div>
        <div class="stat-card" style="--accent:#4ade80">
            <div class="stat-icon">💰</div>
            <div class="stat-body">
                <span class="stat-value"><?= formatPrix((float)($statsJour['ca'] ?? 0)) ?></span>
                <span class="stat-label">CA aujourd'hui</span>
            </div>
        </div>
        <div class="stat-card" style="--accent:#60a5fa">
            <div class="stat-icon">🛒</div>
            <div class="stat-body">
                <span class="stat-value"><?= number_format((int)($statsJour['articles'] ?? 0)) ?></span>
                <span class="stat-label">Articles vendus aujourd'hui</span>
            </div>
        </div>
        <div class="stat-card" style="--accent:#f87171">
            <div class="stat-icon">📊</div>
            <div class="stat-body">
                <span class="stat-value"><?= formatPrix((float)($statsPeriode['ca'] ?? 0)) ?></span>
                <span class="stat-label">CA période filtrée</span>
            </div>
        </div>
    </div>

    <!-- Filtre dates -->
    <form method="GET" class="filter-bar" style="margin-bottom:20px">
        <label style="color:var(--text-muted);font-size:.85rem">Du</label>
        <input type="date" name="from" value="<?= e($dateFrom) ?>" class="filter-input" style="max-width:160px">
        <label style="color:var(--text-muted);font-size:.85rem">Au</label>
        <input type="date" name="to"   value="<?= e($dateTo) ?>"   class="filter-input" style="max-width:160px">
        <button type="submit" class="btn btn-primary">Filtrer</button>
        <a href="ventes_history.php" class="btn">Aujourd'hui</a>
    </form>

    <!-- Tableau des ventes -->
    <?php if (empty($ventes)): ?>
        <div class="empty-state-box"><p>Aucune vente sur cette période.</p></div>
    <?php else: ?>
    <div class="card" style="padding:0;overflow:hidden">
        <table class="data-table data-table-full">
            <thead>
                <tr>
                    <th>N° Ticket</th>
                    <th>Date & Heure</th>
                    <th>Articles</th>
                    <th>Sous-total</th>
                    <th>Remise</th>
                    <th>Total TTC</th>
                    <th>Reçu</th>
                    <th>Monnaie</th>
                    <th>Détail</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ventes as $v): ?>
                <tr>
                    <td><code class="barcode-code"><?= e($v['numero']) ?></code></td>
                    <td><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></td>
                    <td><span class="badge badge-info"><?= $v['nb_articles'] ?></span></td>
                    <td><?= formatPrix((float)$v['total']) ?></td>
                    <td>
                        <?php if ($v['remise'] > 0): ?>
                            <span class="badge badge-warning">- <?= formatPrix((float)$v['remise']) ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-muted)">–</span>
                        <?php endif; ?>
                    </td>
                    <td><strong style="color:var(--gold)"><?= formatPrix((float)$v['total_final']) ?></strong></td>
                    <td><?= formatPrix((float)$v['montant_recu']) ?></td>
                    <td style="color:#4ade80"><?= formatPrix((float)$v['monnaie']) ?></td>
                    <td>
                        <a href="?detail=<?= $v['id'] ?>&from=<?= urlencode($dateFrom) ?>&to=<?= urlencode($dateTo) ?>"
                           class="btn btn-sm">🔍 Voir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:16px">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&from=<?= urlencode($dateFrom) ?>&to=<?= urlencode($dateTo) ?>"
               class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Détail d'une vente -->
    <?php if ($detailVenteId && !empty($detailItems)):
        $stmtVente = $pdo->prepare('SELECT * FROM ventes WHERE id = :id');
        $stmtVente->execute([':id' => $detailVenteId]);
        $venteDetail = $stmtVente->fetch();
    ?>
    <div class="card" style="margin-top:24px">
        <div class="card-header">
            <h2 class="card-title">🔍 Détail : <?= e($venteDetail['numero']) ?></h2>
            <span class="text-muted"><?= date('d/m/Y H:i', strtotime($venteDetail['created_at'])) ?></span>
        </div>
        <table class="data-table">
            <thead><tr><th>Produit</th><th>Code-barres</th><th>Prix unit.</th><th>Qté</th><th>Sous-total</th></tr></thead>
            <tbody>
            <?php foreach ($detailItems as $item): ?>
                <tr>
                    <td><?= e($item['nom']) ?></td>
                    <td><code class="barcode-code"><?= e($item['code_barre']) ?></code></td>
                    <td><?= formatPrix((float)$item['prix_unit']) ?></td>
                    <td><?= $item['quantite'] ?></td>
                    <td><strong><?= formatPrix((float)$item['sous_total']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="text-align:right;padding:16px;border-top:1px solid var(--border)">
            <span style="color:var(--text-muted)">Total final : </span>
            <strong style="color:var(--gold);font-size:1.2rem"><?= formatPrix((float)$venteDetail['total_final']) ?></strong>
        </div>
    </div>
    <?php endif; ?>

</main>
</div>
<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
</body>
</html>
