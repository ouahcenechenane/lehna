<?php
/**
 * admin/ajax/process_sale.php
 * Traitement d'une vente : enregistrement BDD + décrément stock
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée.');
}

// Lecture du JSON envoyé
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    jsonResponse(false, 'Données invalides.');
}

// Vérification CSRF
if (!hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
    jsonResponse(false, 'Token CSRF invalide.');
}

$items      = $data['items']       ?? [];
$remise     = (float)($data['remise']      ?? 0);
$total      = (float)($data['total']       ?? 0);
$montantRecu= (float)($data['montant_recu']?? 0);
$monnaie    = (float)($data['monnaie']     ?? 0);

if (empty($items)) {
    jsonResponse(false, 'Le panier est vide.');
}
if ($total <= 0) {
    jsonResponse(false, 'Total invalide.');
}
if ($montantRecu < $total) {
    jsonResponse(false, 'Montant reçu insuffisant.');
}

$pdo = getPDO();

try {
    $pdo->beginTransaction();

    // ── Vérification stock pour chaque produit ────────
    foreach ($items as $item) {
        $id  = (int)($item['id']       ?? 0);
        $qty = (int)($item['quantite'] ?? 0);

        if ($id <= 0 || $qty <= 0) {
            throw new Exception('Données article invalides.');
        }

        $stmt = $pdo->prepare('SELECT quantite, nom FROM produits WHERE id = :id AND actif = 1 FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $prod = $stmt->fetch();

        if (!$prod) {
            throw new Exception("Produit #$id introuvable.");
        }
        if ((int)$prod['quantite'] < $qty) {
            throw new Exception("Stock insuffisant pour « {$prod['nom']} » (disponible : {$prod['quantite']}).");
        }
    }

    // ── Génération du numéro de vente ─────────────────
    $date       = date('Ymd');
    $stmtCount  = $pdo->prepare("SELECT COUNT(*) FROM ventes WHERE DATE(created_at) = :d");
    $stmtCount->execute([':d' => date('Y-m-d')]);
    $count      = (int)$stmtCount->fetchColumn() + 1;
    $numero     = 'V-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

    // ── Calcul sous-total ─────────────────────────────
    $subtotal = array_sum(array_map(fn($i) => (float)$i['prix'] * (int)$i['quantite'], $items));
    $nbArticles = array_sum(array_map(fn($i) => (int)$i['quantite'], $items));

    // ── Insertion vente ───────────────────────────────
    $stmtV = $pdo->prepare('
        INSERT INTO ventes (numero, total, remise, total_final, montant_recu, monnaie, nb_articles, admin_id)
        VALUES (:numero, :total, :remise, :total_final, :montant_recu, :monnaie, :nb, :admin)
    ');
    $stmtV->execute([
        ':numero'      => $numero,
        ':total'       => $subtotal,
        ':remise'      => $remise,
        ':total_final' => $total,
        ':montant_recu'=> $montantRecu,
        ':monnaie'     => $monnaie,
        ':nb'          => $nbArticles,
        ':admin'       => $_SESSION['admin_id'],
    ]);
    $venteId = (int)$pdo->lastInsertId();

    // ── Insertion lignes + décrément stock ────────────
    $stmtItem = $pdo->prepare('
        INSERT INTO vente_items (vente_id, produit_id, nom, code_barre, prix_unit, quantite, sous_total)
        VALUES (:vente_id, :prod_id, :nom, :code, :prix, :qty, :sous_total)
    ');
    $stmtStock = $pdo->prepare('
        UPDATE produits SET quantite = quantite - :qty WHERE id = :id
    ');

    $itemsForRecu = [];
    foreach ($items as $item) {
        $id       = (int)$item['id'];
        $qty      = (int)$item['quantite'];
        $prix     = (float)$item['prix'];
        $sousTotal= $prix * $qty;

        // Récup nom & code barre
        $stmtProd = $pdo->prepare('SELECT nom, code_barre FROM produits WHERE id = :id');
        $stmtProd->execute([':id' => $id]);
        $prod = $stmtProd->fetch();

        $stmtItem->execute([
            ':vente_id'  => $venteId,
            ':prod_id'   => $id,
            ':nom'       => $prod['nom'],
            ':code'      => $prod['code_barre'],
            ':prix'      => $prix,
            ':qty'       => $qty,
            ':sous_total'=> $sousTotal,
        ]);

        $stmtStock->execute([':qty' => $qty, ':id' => $id]);

        $itemsForRecu[] = [
            'nom'        => $prod['nom'],
            'quantite'   => $qty,
            'prix_unit'  => $prix,
            'sous_total' => $sousTotal,
        ];
    }

    $pdo->commit();

    // ── Réponse avec données du reçu ──────────────────
    jsonResponse(true, 'Vente enregistrée.', [
        'vente' => [
            'numero'      => $numero,
            'date'        => date('d/m/Y H:i'),
            'items'       => $itemsForRecu,
            'subtotal'    => $subtotal,
            'remise'      => $remise,
            'total_final' => $total,
            'montant_recu'=> $montantRecu,
            'monnaie'     => $monnaie,
        ],
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false, $e->getMessage());
}
