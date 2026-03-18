-- ============================================================
-- Nouvelles tables pour le système de vente (POS)
-- À exécuter dans phpMyAdmin sur la base lehna_db
-- ============================================================

USE lehna_db;

-- Table des ventes (chaque transaction)
CREATE TABLE IF NOT EXISTS `ventes` (
    `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `numero`       VARCHAR(20)    NOT NULL,           -- Ex: V-20240317-001
    `total`        DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `remise`       DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `total_final`  DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `montant_recu` DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `monnaie`      DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `nb_articles`  INT            NOT NULL DEFAULT 0,
    `admin_id`     INT UNSIGNED   NOT NULL DEFAULT 1,
    `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_numero` (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des lignes de vente (chaque produit dans une vente)
CREATE TABLE IF NOT EXISTS `vente_items` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `vente_id`    INT UNSIGNED   NOT NULL,
    `produit_id`  INT UNSIGNED   NOT NULL,
    `nom`         VARCHAR(200)   NOT NULL,
    `code_barre`  VARCHAR(50)    NOT NULL,
    `prix_unit`   DECIMAL(10,2)  NOT NULL,
    `quantite`    INT            NOT NULL DEFAULT 1,
    `sous_total`  DECIMAL(10,2)  NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_vente`   (`vente_id`),
    KEY `idx_produit` (`produit_id`),
    CONSTRAINT `fk_vente`   FOREIGN KEY (`vente_id`)   REFERENCES `ventes`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
