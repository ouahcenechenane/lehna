<?php
/**
 * includes/auth.php
 * Vérifie que l'utilisateur est bien connecté en tant qu'admin.
 * À inclure en tête de chaque page admin protégée.
 */

require_once __DIR__ . '/config.php';

// Vérifie la présence et la fraîcheur de la session
if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_logged_in'])) {
    // Mémorise la page demandée pour rediriger après login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

// Sécurité : expiration de session après inactivité
$lastActivity = $_SESSION['last_activity'] ?? 0;
if (time() - $lastActivity > SESSION_LIFETIME) {
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/admin/login.php?expired=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Régénère l'ID de session régulièrement (protection fixation)
if (empty($_SESSION['session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = true;
}
