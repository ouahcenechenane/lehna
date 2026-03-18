<?php
/**
 * includes/db.php
 * Connexion PDO à MySQL – singleton
 * Protection contre les injections SQL via requêtes préparées
 */

require_once __DIR__ . '/config.php';

/**
 * Retourne l'instance unique de PDO.
 * Utilisation : $pdo = getPDO();
 */
function getPDO(): PDO
{
    static $pdo = null;   // Mémorise la connexion entre les appels

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Lance des exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Tableau associatif
            PDO::ATTR_EMULATE_PREPARES   => false,                   // Vraies requêtes préparées
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Ne jamais afficher les détails en production
            if (DEBUG_MODE) {
                die('<div style="padding:20px;background:#fee;border:1px solid #f00;font-family:monospace">'
                    . '❌ Erreur de connexion : ' . htmlspecialchars($e->getMessage())
                    . '</div>');
            } else {
                die('Erreur de connexion à la base de données. Contactez l\'administrateur.');
            }
        }
    }

    return $pdo;
}
