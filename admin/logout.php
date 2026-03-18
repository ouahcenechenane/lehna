<?php
/**
 * admin/logout.php
 * Déconnexion sécurisée de l'administrateur
 */
require_once __DIR__ . '/../includes/config.php';

session_unset();
session_destroy();

// Supprime le cookie de session
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

header('Location: ' . APP_URL . '/admin/login.php');
exit;
