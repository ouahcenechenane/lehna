<?php
/**
 * admin/ajax/update_price.php
 * Mise à jour du prix d'un produit via AJAX
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée.');
}

verifyCsrf($_POST['csrf_token'] ?? '');

$id   = (int)   ($_POST['id']   ?? 0);
$prix = (float) ($_POST['prix'] ?? -1);

if ($id <= 0) {
    jsonResponse(false, 'ID invalide.');
}
if ($prix < 0) {
    jsonResponse(false, 'Prix invalide (doit être ≥ 0).');
}

$pdo  = getPDO();
$stmt = $pdo->prepare('UPDATE produits SET prix = :prix WHERE id = :id AND actif = 1');
$ok   = $stmt->execute([':prix' => $prix, ':id' => $id]);

if ($ok && $stmt->rowCount() > 0) {
    jsonResponse(true, 'Prix mis à jour.', [
        'prix_format' => number_format($prix, 2, ',', ' ') . ' DA',
    ]);
} else {
    jsonResponse(false, 'Produit introuvable.');
}
