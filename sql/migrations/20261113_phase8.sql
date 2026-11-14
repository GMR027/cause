-- ============================================================================
-- Cauce · Fase 8: seguridad final, privacidad, estados de cuenta y admin
--
-- Migración para instalaciones existentes. En una instalación nueva basta con
-- ejecutar sql/schema.sql y sql/seed.sql.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261113_phase8.sql
--   mysql -u root -p cauce < sql/seed.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN `estado` ENUM('activo', 'suspendido') NOT NULL DEFAULT 'activo' AFTER `rol`,
    ADD COLUMN `suspendido_motivo` VARCHAR(255) NULL AFTER `estado`,
    ADD COLUMN `suspendido_at` DATETIME NULL AFTER `suspendido_motivo`,
    ADD COLUMN `pin_hash` VARCHAR(255) NULL AFTER `suspendido_at`,
    ADD COLUMN `pin_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `pin_hash`,
    ADD COLUMN `pin_locked_until` DATETIME NULL AFTER `pin_attempts`,
    ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pin_locked_until`,
    ADD COLUMN `analytics_opt_out` TINYINT(1) NOT NULL DEFAULT 0 AFTER `must_change_password`,
    ADD COLUMN `last_login_at` DATETIME NULL AFTER `analytics_opt_out`,
    ADD COLUMN `password_changed_at` DATETIME NULL AFTER `last_login_at`,
    ADD KEY `idx_users_estado` (`estado`);

-- Conserva la auditoría aunque se elimine la cuenta del admin.
ALTER TABLE `admin_audit_log`
    ADD COLUMN `admin_alias` VARCHAR(30) NULL AFTER `admin_id`,
    ADD COLUMN `admin_email` VARCHAR(190) NULL AFTER `admin_alias`;

UPDATE `admin_audit_log` a
JOIN `users` u ON u.id = a.admin_id
SET a.admin_alias = u.alias,
    a.admin_email = u.email
WHERE a.admin_alias IS NULL;

ALTER TABLE `admin_audit_log`
    DROP FOREIGN KEY `fk_admin_audit_log_admin`,
    MODIFY COLUMN `admin_id` BIGINT UNSIGNED NULL,
    ADD CONSTRAINT `fk_admin_audit_log_admin`
        FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `estados_cuenta`
    ADD COLUMN `mime` VARCHAR(100) NOT NULL DEFAULT 'application/pdf' AFTER `tamano_bytes`,
    ADD COLUMN `checksum` CHAR(64) NULL AFTER `mime`,
    MODIFY COLUMN `tipo` ENUM('banco', 'inversion') NOT NULL DEFAULT 'banco';

CREATE TABLE `estados_cuenta_consentimientos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `accepted_at` DATETIME NOT NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_estados_cuenta_consent_user_version` (`user_id`, `version`),
    CONSTRAINT `fk_estados_cuenta_consent_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `analytics_eventos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `evento` VARCHAR(60) NOT NULL,
    `contexto_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_analytics_evento_created` (`evento`, `created_at`),
    KEY `idx_analytics_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_analytics_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `legal_documentos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tipo` ENUM('privacidad', 'terminos', 'aviso') NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `titulo_es` VARCHAR(160) NOT NULL,
    `titulo_en` VARCHAR(160) NOT NULL,
    `contenido_es` MEDIUMTEXT NOT NULL,
    `contenido_en` MEDIUMTEXT NOT NULL,
    `vigente` TINYINT(1) NOT NULL DEFAULT 0,
    `published_at` DATETIME NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_legal_documentos_tipo_version` (`tipo`, `version`),
    KEY `idx_legal_documentos_vigente` (`tipo`, `vigente`),
    CONSTRAINT `fk_legal_documentos_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `legal_aceptaciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `tipo` ENUM('privacidad', 'terminos', 'aviso') NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `accepted_at` DATETIME NOT NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_legal_aceptaciones_user_tipo_version` (`user_id`, `tipo`, `version`),
    KEY `idx_legal_aceptaciones_tipo_version` (`tipo`, `version`),
    CONSTRAINT `fk_legal_aceptaciones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email_hash` CHAR(64) NOT NULL,
    `ip` VARCHAR(45) NULL,
    `exitoso` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_attempts_email_created` (`email_hash`, `created_at`),
    KEY `idx_login_attempts_ip_created` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `export_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `requested_by` BIGINT UNSIGNED NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_export_tokens_hash` (`token_hash`),
    KEY `idx_export_tokens_user` (`user_id`),
    CONSTRAINT `fk_export_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_export_tokens_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
