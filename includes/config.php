<?php
/**
 * includes/config.php
 * Configuration globale de l'application LEHNA
 * -----------------------------------------------
 * MODIFIEZ ces constantes selon votre environnement
 */

// ── Base de données ─────────────────────────────
define('DB_HOST',   'localhost');
define('DB_NAME',   'lehna_db');
define('DB_USER',   'root');       // Modifier en production
define('DB_PASS',   '');           // Modifier en production
define('DB_CHARSET','utf8mb4');

// ── Application ──────────────────────────────────
define('APP_NAME',  'Alimentation Générale LEHNA');
define('APP_URL',   'http://localhost:8080/lehna');   // URL racine du projet
define('APP_VERSION', '1.0.0');

// ── Chemins ──────────────────────────────────────
define('ROOT_PATH',    dirname(__DIR__));
define('UPLOAD_PATH',  ROOT_PATH . '/assets/images/uploads/');
define('UPLOAD_URL',   APP_URL . '/assets/images/uploads/');

// ── Upload images ─────────────────────────────────
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);   // 5 Mo
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// ── Sessions ──────────────────────────────────────
define('SESSION_LIFETIME', 3600);  // 1 heure en secondes

// ── Pagination ────────────────────────────────────
define('ITEMS_PER_PAGE', 12);

// ── Fuseau horaire ────────────────────────────────
date_default_timezone_set('Africa/Algiers');

// ── Démarrage sécurisé de la session ─────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,   // Passer à true si HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Mode debug (désactiver en production) ─────────
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
