-- ============================================================================
-- Cauce · Bolsas de ahorro y portafolio de inversión
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261114_savings_bags_investment.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- Bolsas/cuentas de ahorro
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `cuentas_ahorro` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `tipo` ENUM('banco', 'efectivo', 'cooperativa', 'otro') NOT NULL DEFAULT 'otro',
    `institucion` VARCHAR(120) NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `es_predeterminada` TINYINT(1) NOT NULL DEFAULT 0,
    `activa` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cuentas_ahorro_user_activa` (`user_id`, `activa`),
    KEY `idx_cuentas_ahorro_user_predeterminada` (`user_id`, `es_predeterminada`),
    CONSTRAINT `fk_cuentas_ahorro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `metas_ahorro`
    ADD COLUMN `cuenta_id` BIGINT UNSIGNED NULL AFTER `user_id`,
    ADD KEY `idx_metas_ahorro_cuenta` (`cuenta_id`),
    ADD CONSTRAINT `fk_metas_ahorro_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE SET NULL;

ALTER TABLE `aportaciones_ahorro`
    ADD COLUMN `cuenta_id` BIGINT UNSIGNED NULL AFTER `meta_id`,
    ADD COLUMN `es_saldo_inicial` TINYINT(1) NOT NULL DEFAULT 0 AFTER `monto_principal_centavos`,
    ADD COLUMN `es_reasignacion` TINYINT(1) NOT NULL DEFAULT 0 AFTER `es_saldo_inicial`,
    ADD KEY `idx_aportaciones_ahorro_cuenta` (`cuenta_id`),
    ADD CONSTRAINT `fk_aportaciones_ahorro_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE SET NULL;

ALTER TABLE `retiros_ahorro`
    ADD COLUMN `cuenta_id` BIGINT UNSIGNED NULL AFTER `meta_id`,
    ADD COLUMN `es_reasignacion` TINYINT(1) NOT NULL DEFAULT 0 AFTER `monto_principal_centavos`,
    ADD KEY `idx_retiros_ahorro_cuenta` (`cuenta_id`),
    ADD CONSTRAINT `fk_retiros_ahorro_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `transferencias_ahorro` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `cuenta_origen_id` BIGINT UNSIGNED NOT NULL,
    `cuenta_destino_id` BIGINT UNSIGNED NOT NULL,
    `meta_id` BIGINT UNSIGNED NULL,
    `motivo` ENUM('transferencia', 'mover_meta') NOT NULL DEFAULT 'transferencia',
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `nota` VARCHAR(255) NULL,
    `fecha` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_transferencias_ahorro_user_fecha` (`user_id`, `fecha`),
    KEY `idx_transferencias_ahorro_origen` (`cuenta_origen_id`),
    KEY `idx_transferencias_ahorro_destino` (`cuenta_destino_id`),
    KEY `idx_transferencias_ahorro_meta` (`meta_id`),
    CONSTRAINT `fk_transferencias_ahorro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transferencias_ahorro_origen` FOREIGN KEY (`cuenta_origen_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_transferencias_ahorro_destino` FOREIGN KEY (`cuenta_destino_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_transferencias_ahorro_meta` FOREIGN KEY (`meta_id`) REFERENCES `metas_ahorro` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bolsa predeterminada para usuarios que ya tienen ahorros.
INSERT INTO `cuentas_ahorro`
    (`user_id`, `nombre`, `tipo`, `moneda`, `es_predeterminada`, `activa`, `created_at`, `updated_at`)
SELECT u.`id`, 'Ahorro general', 'otro', u.`moneda_activa`, 1, 1, NOW(), NOW()
FROM `users` u
WHERE (
        EXISTS (SELECT 1 FROM `aportaciones_ahorro` a WHERE a.`user_id` = u.`id`)
        OR EXISTS (SELECT 1 FROM `retiros_ahorro` r WHERE r.`user_id` = u.`id`)
        OR EXISTS (SELECT 1 FROM `metas_ahorro` m WHERE m.`user_id` = u.`id`)
    )
    AND NOT EXISTS (
        SELECT 1 FROM `cuentas_ahorro` c
        WHERE c.`user_id` = u.`id` AND c.`es_predeterminada` = 1
    );

UPDATE `aportaciones_ahorro` a
JOIN `cuentas_ahorro` c ON c.`user_id` = a.`user_id` AND c.`es_predeterminada` = 1
SET a.`cuenta_id` = c.`id`
WHERE a.`cuenta_id` IS NULL;

UPDATE `retiros_ahorro` r
JOIN `cuentas_ahorro` c ON c.`user_id` = r.`user_id` AND c.`es_predeterminada` = 1
SET r.`cuenta_id` = c.`id`
WHERE r.`cuenta_id` IS NULL;

UPDATE `metas_ahorro` m
JOIN `cuentas_ahorro` c ON c.`user_id` = m.`user_id` AND c.`es_predeterminada` = 1
SET m.`cuenta_id` = c.`id`
WHERE m.`cuenta_id` IS NULL;

-- ----------------------------------------------------------------------------
-- Portafolio de inversión
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `inversiones_posiciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `tipo` ENUM('accion', 'etf', 'cripto', 'cetes', 'fondo', 'otro') NOT NULL DEFAULT 'otro',
    `mercado` ENUM('BMV', 'EUA', 'CRIPTO', 'CETES', 'OTRO') NOT NULL DEFAULT 'OTRO',
    `ticker` VARCHAR(32) NULL,
    `simbolo_proveedor` VARCHAR(64) NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `cantidad` DECIMAL(24,8) NOT NULL DEFAULT 1,
    `costo_total_centavos` BIGINT NOT NULL DEFAULT 0,
    `costo_total_principal_centavos` BIGINT NULL,
    `modo_valor` ENUM('automatico', 'manual') NOT NULL DEFAULT 'manual',
    `valor_manual_centavos` BIGINT NULL,
    `valor_manual_principal_centavos` BIGINT NULL,
    `fecha_valor_manual` DATETIME NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inversiones_posiciones_user_activo` (`user_id`, `activo`),
    KEY `idx_inversiones_posiciones_simbolo` (`simbolo_proveedor`),
    CONSTRAINT `fk_inversiones_posiciones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inversiones_precios` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `simbolo_proveedor` VARCHAR(64) NOT NULL,
    `mercado` ENUM('BMV', 'EUA', 'CRIPTO', 'OTRO') NOT NULL DEFAULT 'OTRO',
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `precio` DECIMAL(24,8) NOT NULL,
    `fecha` DATE NOT NULL,
    `fuente` VARCHAR(40) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inversiones_precios_simbolo_fecha` (`simbolo_proveedor`, `fecha`),
    KEY `idx_inversiones_precios_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `patrimonio_snapshots`
    ADD COLUMN `neto_aportado_centavos` BIGINT NULL AFTER `neto_centavos`;
