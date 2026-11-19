-- ============================================================================
-- Cauce · De dónde vino el dinero de una deuda
--
-- · deudas.origen: tarjeta (de crédito), ahorro (préstamo de una bolsa propia),
--   prestamo (banco o persona) u otro.
-- · deudas.origen_cuenta_id / origen_meta_id: la bolsa (o meta) que prestó.
-- · deudas.retiro_ahorro_id: el retiro que bajó esa bolsa al crear la deuda.
-- · pagos_deuda.aporte_ahorro_id: lo que cada pago devolvió a la bolsa.
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261118_origen_deuda.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `deudas`
    ADD COLUMN `origen` ENUM('tarjeta', 'ahorro', 'prestamo', 'otro') NOT NULL DEFAULT 'otro' AFTER `moneda`,
    ADD COLUMN `origen_cuenta_id` BIGINT UNSIGNED NULL AFTER `origen`,
    ADD COLUMN `origen_meta_id` BIGINT UNSIGNED NULL AFTER `origen_cuenta_id`,
    ADD COLUMN `retiro_ahorro_id` BIGINT UNSIGNED NULL AFTER `origen_meta_id`,
    ADD KEY `idx_deudas_retiro` (`retiro_ahorro_id`),
    ADD CONSTRAINT `fk_deudas_origen_cuenta` FOREIGN KEY (`origen_cuenta_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_deudas_origen_meta` FOREIGN KEY (`origen_meta_id`) REFERENCES `metas_ahorro` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_deudas_retiro` FOREIGN KEY (`retiro_ahorro_id`) REFERENCES `retiros_ahorro` (`id`) ON DELETE SET NULL;

ALTER TABLE `pagos_deuda`
    ADD COLUMN `aporte_ahorro_id` BIGINT UNSIGNED NULL AFTER `deuda_tarjeta_id`,
    ADD KEY `idx_pagos_deuda_aporte` (`aporte_ahorro_id`),
    ADD CONSTRAINT `fk_pagos_deuda_aporte` FOREIGN KEY (`aporte_ahorro_id`) REFERENCES `aportaciones_ahorro` (`id`) ON DELETE SET NULL;
