-- ============================================================
-- Base de données : Alimentation Générale LEHNA
-- Auteur  : Projet LEHNA
-- Version : 1.0
-- ============================================================

CREATE DATABASE IF NOT EXISTS lehna_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lehna_db;

-- ------------------------------------------------------------
-- Table : admin
-- Stocke les comptes administrateurs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(60)      NOT NULL,
    `password`   VARCHAR(255)     NOT NULL,          -- Hash bcrypt
    `nom`        VARCHAR(100)     NOT NULL DEFAULT '',
    `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compte admin par défaut : admin / Admin@1234
-- Mot de passe haché avec password_hash('Admin@1234', PASSWORD_BCRYPT)
INSERT INTO `admin` (`username`, `password`, `nom`) VALUES
('admin', '$2y$12$Sz6UbxFnpBIgUHn3R/XSLO6u9sQLJEDxCc2J0GQdZ6b.OQMuCAqEa', 'Administrateur LEHNA');

-- ------------------------------------------------------------
-- Table : produits
-- Catalogue complet des articles de la boutique
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `produits` (
    `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `nom`          VARCHAR(200)     NOT NULL,
    `prix`         DECIMAL(10, 2)   NOT NULL DEFAULT 0.00,
    `code_barre`   VARCHAR(50)      NOT NULL,          -- EAN-13, QR, etc.
    `quantite`     INT              NOT NULL DEFAULT 0,
    `image`        VARCHAR(255)     NOT NULL DEFAULT 'default.jpg',
    `description`  TEXT,
    `categorie`    VARCHAR(100)     NOT NULL DEFAULT 'Général',
    `actif`        TINYINT(1)       NOT NULL DEFAULT 1, -- 1=visible, 0=archivé
    `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code_barre` (`code_barre`),
    KEY `idx_nom`       (`nom`),
    KEY `idx_categorie` (`categorie`),
    KEY `idx_actif`     (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données de démonstration
INSERT INTO `produits` (`nom`, `prix`, `code_barre`, `quantite`, `image`, `categorie`) VALUES
('Huile de Table 1L',       320.00, '6191234560001', 45, 'default.jpg', 'Épicerie'),
('Farine Spéciale 1kg',     85.00,  '6191234560002', 120,'default.jpg', 'Épicerie'),
('Sucre Blanc 1kg',         90.00,  '6191234560003', 80, 'default.jpg', 'Épicerie'),
('Lait UHT 1L',             145.00, '6191234560004', 60, 'default.jpg', 'Produits Laitiers'),
('Eau Minérale 1.5L',       35.00,  '6191234560005', 200,'default.jpg', 'Boissons'),
('Café Moulu 250g',         250.00, '6191234560006', 30, 'default.jpg', 'Boissons'),
('Savon Marseille 400g',    120.00, '6191234560007', 55, 'default.jpg', 'Hygiène'),
('Pâtes Semoule 500g',      75.00,  '6191234560008', 90, 'default.jpg', 'Épicerie'),
('Tomate Concentrée 135g',  65.00,  '6191234560009', 70, 'default.jpg', 'Conserves'),
('Yaourt Nature x4',        180.00, '6191234560010', 40, 'default.jpg', 'Produits Laitiers');

-- Vue pratique pour l'administration
CREATE OR REPLACE VIEW v_produits_stats AS
SELECT
    COUNT(*)                         AS total_produits,
    SUM(quantite)                    AS total_stock,
    SUM(quantite * prix)             AS valeur_stock,
    SUM(CASE WHEN quantite = 0 THEN 1 ELSE 0 END)  AS ruptures,
    SUM(CASE WHEN quantite > 0 AND quantite <= 10 THEN 1 ELSE 0 END) AS stock_bas
FROM produits
WHERE actif = 1;
