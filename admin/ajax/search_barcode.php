<?php
/**
 * admin/ajax/search_barcode.php
 * Recherche d'un produit par code-barres (pour le scanner)
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$code = sanitizeString($_GET['code'] ?? '', 50);

if ($code === '') {
    jsonResponse(false, 'Code vide.');
}

$produit = getProduitByCodeBarre($code);

if ($produit) {
    jsonResponse(true, 'Produit trouvé.', [
        'produit' => [
            'id'          => $produit['id'],
            'nom'         => $produit['nom'],
            'prix'        => $produit['prix'],
            'quantite'    => $produit['quantite'],
            'code_barre'  => $produit['code_barre'],
        ]
    ]);
} else {
    jsonResponse(false, 'Produit non trouvé dans la base.');
}
