<?php
/**
 * admin/ajax/pos_search.php
 * Recherche produit pour la caisse (par code-barres ou nom)
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$q = sanitizeString($_GET['q'] ?? '', 100);

if ($q === '') {
    jsonResponse(false, 'Requête vide.');
}

$pdo = getPDO();

// Recherche par code-barres exact EN PREMIER
$stmt = $pdo->prepare('SELECT * FROM produits WHERE code_barre = :q AND actif = 1 LIMIT 1');
$stmt->execute([':q' => $q]);
$exact = $stmt->fetch();

if ($exact) {
    jsonResponse(true, 'OK', ['produits' => [[
        'id'    => (int)$exact['id'],
        'nom'   => $exact['nom'],
        'prix'  => (float)$exact['prix'],
        'code'  => $exact['code_barre'],
        'stock' => (int)$exact['quantite'],
    ]]]);
}

// Sinon recherche par nom
$stmt = $pdo->prepare('
    SELECT * FROM produits
    WHERE actif = 1 AND (nom LIKE :q OR code_barre LIKE :q2)
    ORDER BY nom ASC LIMIT 10
');
$stmt->execute([':q' => '%' . $q . '%', ':q2' => '%' . $q . '%']);
$rows = $stmt->fetchAll();

if (empty($rows)) {
    jsonResponse(false, 'Aucun produit trouvé.');
}

$produits = array_map(fn($r) => [
    'id'    => (int)$r['id'],
    'nom'   => $r['nom'],
    'prix'  => (float)$r['prix'],
    'code'  => $r['code_barre'],
    'stock' => (int)$r['quantite'],
], $rows);

jsonResponse(true, 'OK', ['produits' => $produits]);
