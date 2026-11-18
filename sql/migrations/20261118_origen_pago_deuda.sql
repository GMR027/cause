-- ============================================================================
-- Cauce · De dónde sale el dinero de un pago de deuda
--
-- · origen: ingreso (gasto del método), ahorro (retiro de una bolsa),
--   tarjeta (se suma a otra deuda, la tarjeta) u otro (solo se registra).
-- · retiro_ahorro_id: el retiro de ahorro que pagó la deuda.
-- · deuda_tarjeta_id: la deuda (tarjeta) a la que se sumó el pago.
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261118_origen_pago_deuda.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `pagos_deuda`
    ADD COLUMN `origen` ENUM('ingreso', 'ahorro', 'tarjeta', 'otro') NOT NULL DEFAULT 'otro' AFTER `gasto_id`,
    ADD COLUMN `retiro_ahorro_id` BIGINT UNSIGNED NULL AFTER `origen`,
    ADD COLUMN `deuda_tarjeta_id` BIGINT UNSIGNED NULL AFTER `retiro_ahorro_id`,
    ADD KEY `idx_pagos_deuda_retiro` (`retiro_ahorro_id`),
    ADD CONSTRAINT `fk_pagos_deuda_retiro` FOREIGN KEY (`retiro_ahorro_id`) REFERENCES `retiros_ahorro` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_pagos_deuda_tarjeta` FOREIGN KEY (`deuda_tarjeta_id`) REFERENCES `deudas` (`id`) ON DELETE SET NULL;

-- Los pagos que ya registraron un gasto salieron del ingreso.
UPDATE `pagos_deuda` SET `origen` = 'ingreso' WHERE `gasto_id` IS NOT NULL;
