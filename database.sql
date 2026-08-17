-- ============================================================
-- UPC FREELANCE — Base de données complète v2
-- Encodage : UTF-8 | Moteur : InnoDB
-- ============================================================

DROP DATABASE IF EXISTS `upc_freelance`;
CREATE DATABASE IF NOT EXISTS `upc_freelance`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `upc_freelance`;

-- ============================================================
-- TABLE : users
-- ============================================================
CREATE TABLE `users` (
    `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`                   CHAR(36)      NOT NULL,
    `role`                   ENUM('client','freelancer') NOT NULL,
    `first_name`             VARCHAR(80)   NOT NULL,
    `last_name`              VARCHAR(80)   NOT NULL,
    `email`                  VARCHAR(180)  NOT NULL,
    `password_hash`          VARCHAR(255)  NOT NULL,
    `phone`                  VARCHAR(20)   DEFAULT NULL,
    `avatar`                 VARCHAR(255)  DEFAULT NULL,
    `is_verified`            TINYINT(1)    NOT NULL DEFAULT 0,
    `is_active`              TINYINT(1)    NOT NULL DEFAULT 1,
    `email_verified_at`      DATETIME      DEFAULT NULL,
    `remember_token`         VARCHAR(100)  DEFAULT NULL,
    `reset_token`            VARCHAR(100)  DEFAULT NULL,
    `reset_token_expires_at` DATETIME      DEFAULT NULL,
    `last_login_at`          DATETIME      DEFAULT NULL,
    `created_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_uuid`  (`uuid`),
    UNIQUE KEY `uq_users_email` (`email`),
    INDEX `idx_users_role`      (`role`),
    INDEX `idx_users_active`    (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : freelancer_profiles
-- ============================================================
CREATE TABLE `freelancer_profiles` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED  NOT NULL,
    -- Identité académique
    `university`     VARCHAR(120)  DEFAULT NULL,
    `field_of_study` VARCHAR(120)  DEFAULT NULL,
    -- Identité professionnelle
    `title`          VARCHAR(120)  DEFAULT NULL,
    `bio`            TEXT          DEFAULT NULL,
    `hourly_rate`    DECIMAL(10,2) DEFAULT NULL,
    `availability`   ENUM('available','busy','unavailable') NOT NULL DEFAULT 'available',
    -- Compétences & liens
    `skills`         JSON          DEFAULT NULL,
    `portfolio_url` JSON          DEFAULT NULL,
    `linkedin_url`   VARCHAR(255)  DEFAULT NULL,
    `github_url`     VARCHAR(255)  DEFAULT NULL,
    -- Stats calculées
    `rating`         DECIMAL(3,2)  DEFAULT NULL,
    `total_reviews`  INT UNSIGNED  DEFAULT 0,
    `total_earned`   DECIMAL(12,2) DEFAULT 0.00,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fp_user` (`user_id`),
    CONSTRAINT `fk_fp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : client_profiles
-- ============================================================
CREATE TABLE `client_profiles` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED  NOT NULL,
    -- Identité
    `bio`           TEXT          DEFAULT NULL,
    `company_name`  VARCHAR(120)  DEFAULT NULL,
    `website`       VARCHAR(255)  DEFAULT NULL,
    -- Stats calculées
    `rating`        DECIMAL(3,2)  DEFAULT NULL,
    `total_reviews` INT UNSIGNED  DEFAULT 0,
    `total_spent`   DECIMAL(12,2) DEFAULT 0.00,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cp_user` (`user_id`),
    CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : categories
-- ============================================================
CREATE TABLE `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(80)  NOT NULL,
    `slug`        VARCHAR(80)  NOT NULL,
    `icon`        VARCHAR(60)  DEFAULT NULL,
    `description` TEXT         DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : projects
-- ============================================================
CREATE TABLE `projects` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`          CHAR(36)      NOT NULL,
    `client_id`     INT UNSIGNED  NOT NULL,
    `category_id`   INT UNSIGNED  DEFAULT NULL,
    `title`         VARCHAR(200)  NOT NULL,
    `description`   LONGTEXT      NOT NULL,
    `budget_min`    DECIMAL(10,2) DEFAULT NULL,
    `budget_max`    DECIMAL(10,2) DEFAULT NULL,
    `deadline`      DATE          DEFAULT NULL,
    `skills_needed` JSON          DEFAULT NULL,
    `attachments`   JSON          DEFAULT NULL,
    `status`        ENUM('open','in_progress','completed','cancelled','disputed') NOT NULL DEFAULT 'open',
    `visibility`    ENUM('public','private') NOT NULL DEFAULT 'public',
    `views_count`   INT UNSIGNED  DEFAULT 0,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_proj_uuid`    (`uuid`),
    INDEX `idx_proj_client`      (`client_id`),
    INDEX `idx_proj_status`      (`status`),
    INDEX `idx_proj_category`    (`category_id`),
    CONSTRAINT `fk_proj_client`   FOREIGN KEY (`client_id`)   REFERENCES `users`(`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_proj_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : postulations
-- ============================================================
CREATE TABLE `postulations` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `project_id`     INT UNSIGNED  NOT NULL,
    `freelancer_id`  INT UNSIGNED  NOT NULL,
    `cover_letter`   TEXT          NOT NULL,
    `proposed_price` DECIMAL(10,2) NOT NULL,
    `proposed_days`  INT UNSIGNED  DEFAULT NULL,
    `status`         ENUM('pending','accepted','rejected','withdrawn') NOT NULL DEFAULT 'pending',
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_postul` (`project_id`, `freelancer_id`),
    INDEX `idx_postul_freelancer` (`freelancer_id`),
    CONSTRAINT `fk_postul_project`    FOREIGN KEY (`project_id`)    REFERENCES `projects`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_postul_freelancer` FOREIGN KEY (`freelancer_id`) REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : contracts
-- ============================================================
CREATE TABLE `contracts` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`           CHAR(36)      NOT NULL,
    `project_id`     INT UNSIGNED  NOT NULL,
    `client_id`      INT UNSIGNED  NOT NULL,
    `freelancer_id`  INT UNSIGNED  NOT NULL,
    `postulation_id` INT UNSIGNED  NOT NULL,
    `amount`         DECIMAL(10,2) NOT NULL,
    `start_date`     DATE          DEFAULT NULL,
    `end_date`       DATE          DEFAULT NULL,
    `status`         ENUM('active','completed','cancelled','disputed') NOT NULL DEFAULT 'active',
    `completed_at`   DATETIME      DEFAULT NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_contract_uuid`    (`uuid`),
    INDEX `idx_contract_client`      (`client_id`),
    INDEX `idx_contract_freelancer`  (`freelancer_id`),
    CONSTRAINT `fk_contract_project`     FOREIGN KEY (`project_id`)     REFERENCES `projects`(`id`)     ON DELETE CASCADE,
    CONSTRAINT `fk_contract_client`      FOREIGN KEY (`client_id`)      REFERENCES `users`(`id`)        ON DELETE CASCADE,
    CONSTRAINT `fk_contract_freelancer`  FOREIGN KEY (`freelancer_id`)  REFERENCES `users`(`id`)        ON DELETE CASCADE,
    CONSTRAINT `fk_contract_postulation` FOREIGN KEY (`postulation_id`) REFERENCES `postulations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : messages
-- ============================================================
CREATE TABLE `messages` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contract_id` INT UNSIGNED NOT NULL,
    `sender_id`   INT UNSIGNED NOT NULL,
    `body`        TEXT         NOT NULL,
    `attachments` JSON         DEFAULT NULL,
    `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_msg_contract` (`contract_id`),
    INDEX `idx_msg_sender`   (`sender_id`),
    CONSTRAINT `fk_msg_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msg_sender`   FOREIGN KEY (`sender_id`)   REFERENCES `users`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- UPC FREELANCE — Messages directs (hors contrat)
-- ============================================================
CREATE TABLE IF NOT EXISTS `direct_messages` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `sender_id`   INT UNSIGNED  NOT NULL,
    `receiver_id` INT UNSIGNED  NOT NULL,
    `body`        TEXT          NOT NULL,
    `is_read`     TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_dm_sender`   (`sender_id`),
    INDEX `idx_dm_receiver` (`receiver_id`),
    INDEX `idx_dm_conv`     (`sender_id`, `receiver_id`),
    CONSTRAINT `fk_dm_sender`   FOREIGN KEY (`sender_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dm_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE : wallets
-- ============================================================
CREATE TABLE `wallets` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  NOT NULL,
    `balance`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `locked`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wallet_user` (`user_id`),
    CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : transactions
-- ============================================================
CREATE TABLE `transactions` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `uuid`           CHAR(36)      NOT NULL,
    `user_id`        INT UNSIGNED  NOT NULL,
    `contract_id`    INT UNSIGNED  DEFAULT NULL,
    `type`           ENUM('deposit','withdrawal','payment','refund','lock','unlock') NOT NULL,
    `amount`         DECIMAL(12,2) NOT NULL,
    `balance_before` DECIMAL(12,2) NOT NULL,
    `balance_after`  DECIMAL(12,2) NOT NULL,
    `description`    VARCHAR(255)  DEFAULT NULL,
    `reference`      VARCHAR(100)  DEFAULT NULL,
    `status`         ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'completed',
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tx_uuid`  (`uuid`),
    INDEX `idx_tx_user`      (`user_id`),
    INDEX `idx_tx_contract`  (`contract_id`),
    CONSTRAINT `fk_tx_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE,
    CONSTRAINT `fk_tx_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : reviews
-- ============================================================
CREATE TABLE `reviews` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `contract_id` INT UNSIGNED     NOT NULL,
    `reviewer_id` INT UNSIGNED     NOT NULL,
    `reviewed_id` INT UNSIGNED     NOT NULL,
    `rating`      TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `comment`     TEXT             DEFAULT NULL,
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_review` (`contract_id`, `reviewer_id`),
    INDEX `idx_review_reviewed` (`reviewed_id`),
    CONSTRAINT `fk_review_contract`  FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_reviewer`  FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`)     ON DELETE CASCADE,
    CONSTRAINT `fk_review_reviewed`  FOREIGN KEY (`reviewed_id`) REFERENCES `users`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : notifications
-- ============================================================
CREATE TABLE `notifications` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       VARCHAR(60)  NOT NULL,
    `title`      VARCHAR(120) NOT NULL,
    `body`       TEXT         DEFAULT NULL,
    `link`       VARCHAR(255) DEFAULT NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notif_user`   (`user_id`),
    INDEX `idx_notif_is_read`(`is_read`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : contract_files
-- ============================================================
CREATE TABLE `contract_files` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contract_id` INT UNSIGNED NOT NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `file_name`   VARCHAR(255) NOT NULL,
    `file_path`   VARCHAR(255) NOT NULL,
    `file_size`   INT UNSIGNED NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cf_contract` (`contract_id`),
    CONSTRAINT `fk_cf_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cf_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : verification_docs
-- ============================================================
CREATE TABLE `verification_docs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `doc_type`    ENUM('student_card','id_card','diploma','other') NOT NULL,
    `file_path`   VARCHAR(255) NOT NULL,
    `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `admin_note`  TEXT         DEFAULT NULL,
    `reviewed_at` DATETIME     DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_vdoc_user`   (`user_id`),
    INDEX `idx_vdoc_status` (`status`),
    CONSTRAINT `fk_vdoc_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : admin_users
-- ============================================================
CREATE TABLE `admin_users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(120) NOT NULL,
    `email`         VARCHAR(180) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_super`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONNÉES INITIALES
-- ============================================================

INSERT INTO `categories` (`name`, `slug`, `icon`, `description`) VALUES
('Développement Web',      'dev-web',       'code',             'Sites web, applications, APIs'),
('Developpement Mobile',   'dev-mobile',    'smartphone',       'Apps iOS, Android, cross-platform'),
('Design & UI/UX',         'design',        'palette',          'Logos, maquettes, interfaces'),
('Marketing Digital',      'marketing',     'trending_up',      'SEO, réseaux sociaux, campagnes'),
('Rédaction & Contenu',    'redaction',     'edit_note',        'Articles, copywriting, traduction'),
('Data & Analyse',         'data',          'bar_chart',        'Data science, statistiques, BI'),
('Vidéo & Audio',          'video-audio',   'videocam',         'Montage, podcasts, animation'),
('Comptabilité & Finance', 'finance',       'account_balance',  'Comptabilité, conseil financier'),
('Informatique & Réseaux', 'informatique',  'computer',         'Systèmes, réseaux, cybersécurité');

