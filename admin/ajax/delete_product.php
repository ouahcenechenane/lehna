<?php
/**
 * admin/ajax/delete_product.php
 * Suppression (soft-delete) d'un produit via AJAX
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Seules les requêtes POST sont acceptées
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée.');
}

// Vérification CSRF
verifyCsrf($_POST['csrf_token'] ?? '');

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    jsonResponse(false, 'ID invalide.');
}

// Soft-delete : on désactive le produit plutôt que de le supprimer
$pdo  = getPDO();
$stmt = $pdo->prepare('UPDATE produits SET actif = 0 WHERE id = :id');
$ok   = $stmt->execute([':id' => $id]);

if ($ok && $stmt->rowCount() > 0) {
    jsonResponse(true, 'Produit supprimé avec succès.');
} else {
    jsonResponse(false, 'Produit introuvable ou déjà supprimé.');
}
