-- ============================================================================
-- Cauce · Fase 11: multi-moneda, monedas por usuario y tasas de cambio
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261113_phase11.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `monedas_usuario` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `codigo` CHAR(3) NOT NULL,
    `es_principal` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_monedas_usuario_user_codigo` (`user_id`, `codigo`),
    KEY `idx_monedas_usuario_user_principal` (`user_id`, `es_principal`),
    CONSTRAINT `fk_monedas_usuario_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasas_cambio` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `moneda_origen` CHAR(3) NOT NULL,
    `moneda_destino` CHAR(3) NOT NULL,
    `tasa` DECIMAL(18,8) NOT NULL,
    `fecha` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tasas_cambio_par_fecha` (`moneda_origen`, `moneda_destino`, `fecha`),
    KEY `idx_tasas_cambio_fecha` (`fecha`),
    CONSTRAINT `chk_tasas_cambio_positiva` CHECK (`tasa` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ingresos`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `asignaciones`
    ADD COLUMN `monto_principal_centavos` BIGINT NOT NULL DEFAULT 0 AFTER `moneda`;

ALTER TABLE `no_controlado`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `gastos`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`,
    ADD COLUMN `monto_total_compartido_principal_centavos` BIGINT NULL AFTER `monto_principal_centavos`;

ALTER TABLE `gastos_recurrentes`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `plantillas_gasto`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `movimientos`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `metas_ahorro`
    ADD COLUMN `objetivo_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `aportaciones_ahorro`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `retiros_ahorro`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `aportaciones_inversion`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `retiros_inversion`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `donaciones`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `inversiones_fondos`
    ADD COLUMN `valor_actual_principal_centavos` BIGINT NULL AFTER `valor_actual_centavos`;

ALTER TABLE `deudas`
    ADD COLUMN `monto_total_principal_centavos` BIGINT NULL AFTER `moneda`,
    ADD COLUMN `saldo_actual_principal_centavos` BIGINT NULL AFTER `monto_total_principal_centavos`,
    ADD COLUMN `pago_minimo_principal_centavos` BIGINT NULL AFTER `saldo_actual_principal_centavos`;

ALTER TABLE `pagos_deuda`
    ADD COLUMN `monto_principal_centavos` BIGINT NULL AFTER `moneda`;

ALTER TABLE `lista_deseos`
    ADD COLUMN `monto_estimado_principal_centavos` BIGINT NULL AFTER `moneda`;

-- Moneda principal de cada usuario existente.
INSERT IGNORE INTO `monedas_usuario` (`user_id`, `codigo`, `es_principal`, `created_at`, `updated_at`)
SELECT `id`, `moneda_activa`, 1, NOW(), NOW() FROM `users`;

-- Backfill de registros que ya están en la moneda principal.
UPDATE `ingresos` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `asignaciones` t
SET t.`monto_principal_centavos` = t.`monto_centavos`;

UPDATE `no_controlado` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `gastos` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`,
    t.`monto_total_compartido_principal_centavos` = t.`monto_total_compartido_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `gastos_recurrentes` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `plantillas_gasto` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `movimientos` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `metas_ahorro` t JOIN `users` u ON u.id = t.user_id
SET t.`objetivo_principal_centavos` = t.`objetivo_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `aportaciones_ahorro` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `retiros_ahorro` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `aportaciones_inversion` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `retiros_inversion` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `donaciones` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `inversiones_fondos` t JOIN `users` u ON u.id = t.user_id
SET t.`valor_actual_principal_centavos` = t.`valor_actual_centavos`
WHERE t.`moneda` = u.`moneda_activa` AND t.`valor_actual_centavos` IS NOT NULL;

UPDATE `deudas` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_total_principal_centavos` = t.`monto_total_centavos`,
    t.`saldo_actual_principal_centavos` = t.`saldo_actual_centavos`,
    t.`pago_minimo_principal_centavos` = t.`pago_minimo_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `pagos_deuda` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_principal_centavos` = t.`monto_centavos`
WHERE t.`moneda` = u.`moneda_activa`;

UPDATE `lista_deseos` t JOIN `users` u ON u.id = t.user_id
SET t.`monto_estimado_principal_centavos` = t.`monto_estimado_centavos`
WHERE t.`moneda` = u.`moneda_activa`;
