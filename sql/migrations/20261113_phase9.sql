-- ============================================================================
-- Cauce · Fase 9: deudas, patrimonio, metas, proyección y suscripciones
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261113_phase9.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `gastos_recurrentes`
    ADD COLUMN `deuda_id` BIGINT UNSIGNED NULL AFTER `subcategoria_id`,
    ADD KEY `idx_gastos_recurrentes_deuda_id` (`deuda_id`),
    ADD CONSTRAINT `fk_gastos_recurrentes_deuda`
        FOREIGN KEY (`deuda_id`) REFERENCES `deudas` (`id`) ON DELETE SET NULL;
