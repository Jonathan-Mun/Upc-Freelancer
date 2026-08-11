
-- ============================================================
-- UPC FREELANCE — Migration : Litiges, annulations, fraude
-- À exécuter UNE SEULE FOIS sur la base existante
-- ============================================================

ALTER TABLE contracts
    ADD COLUMN cancel_requested_by INT UNSIGNED NULL AFTER completed_at,
    ADD COLUMN cancel_reason       TEXT NULL         AFTER cancel_requested_by,
    ADD COLUMN disputed_by         INT UNSIGNED NULL AFTER cancel_reason,
    ADD COLUMN dispute_reason      TEXT NULL         AFTER disputed_by,
    ADD COLUMN fraud_flag          TINYINT(1) NOT NULL DEFAULT 0 AFTER dispute_reason,
    ADD COLUMN fraud_note          TEXT NULL         AFTER fraud_flag,
    ADD COLUMN ai_analysis         TEXT NULL         AFTER fraud_note,
    ADD COLUMN ai_analyzed_at      DATETIME NULL     AFTER ai_analysis,
    ADD COLUMN resolved_by         INT UNSIGNED NULL AFTER ai_analyzed_at,
    ADD COLUMN resolved_at         DATETIME NULL     AFTER resolved_by,
    ADD COLUMN resolution_note     TEXT NULL         AFTER resolved_at;

ALTER TABLE contracts
    ADD CONSTRAINT fk_contract_cancel_by   FOREIGN KEY (cancel_requested_by) REFERENCES users(id)       ON DELETE SET NULL,
    ADD CONSTRAINT fk_contract_disputed_by FOREIGN KEY (disputed_by)         REFERENCES users(id)       ON DELETE SET NULL,
    ADD CONSTRAINT fk_contract_resolved_by FOREIGN KEY (resolved_by)         REFERENCES admin_users(id) ON DELETE SET NULL;

-- ============================================================
-- TABLE : contract_reports (plaintes)
-- ============================================================
CREATE TABLE `contract_reports` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contract_id` INT UNSIGNED NOT NULL,
    `reporter_id` INT UNSIGNED NOT NULL,
    `reason`      TEXT NOT NULL,
    `status`      ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
    `admin_note`  TEXT NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cr_contract` (`contract_id`),
    INDEX `idx_cr_status`   (`status`),
    CONSTRAINT `fk_cr_contract`    FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_cr_reporter`    FOREIGN KEY (`reporter_id`) REFERENCES `users`(`id`)         ON DELETE CASCADE,
    CONSTRAINT `fk_cr_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `admin_users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;