-- ============================================================
-- NEXARENA VOTE TRACKING
-- Cette table est creee automatiquement si Config.Database.Enabled = true
-- Vous pouvez aussi l'importer manuellement avec ce fichier
-- ============================================================

CREATE TABLE IF NOT EXISTS `nexarena_votes` (
    `id`            INT(11)      NOT NULL AUTO_INCREMENT,
    `identifier`    VARCHAR(100) NOT NULL COMMENT 'Discord ID, Steam ID, IP ou username selon la config',
    `player_name`   VARCHAR(100) DEFAULT NULL COMMENT 'Dernier pseudo connu du joueur',
    `vote_count`    INT(11)      NOT NULL DEFAULT 0,
    `last_vote_at`  DATETIME     DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
