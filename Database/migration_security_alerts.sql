-- =============================================================
-- Connect'Academia — Détection d'intrusion & alertes de sécurité
-- À rejouer en production (idempotent).
-- =============================================================

CREATE TABLE IF NOT EXISTS `security_alerts` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`          VARCHAR(64)  NOT NULL,                       -- brute_force_admin, sqli, xss, path_traversal, scanner, flood, login_echec, ...
  `severity`      ENUM('faible','moyenne','elevee','critique') NOT NULL DEFAULT 'moyenne',
  `ip`            VARCHAR(45)  NULL,                            -- IPv4 / IPv6
  `user_agent`    VARCHAR(255) NULL,
  `method`        VARCHAR(10)  NULL,
  `uri`           VARCHAR(512) NULL,
  `message`       VARCHAR(255) NOT NULL,
  `context`       JSON         NULL,                           -- indices déclencheurs (motif, échantillon, seuil…)
  `occurrences`   INT UNSIGNED NOT NULL DEFAULT 1,             -- regroupement d'événements identiques
  `statut`        ENUM('nouveau','vu','resolu') NOT NULL DEFAULT 'nouveau',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut`   (`statut`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created`  (`created_at`),
  KEY `idx_ip`       (`ip`),
  KEY `idx_dedupe`   (`type`, `ip`, `statut`, `last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
