-- ============================================================================
-- Cauce · Transferencias de ahorro: borrado en cascada
--
-- Las claves de cuenta_origen_id / cuenta_destino_id eran ON DELETE RESTRICT.
-- Al eliminar un usuario, el borrado en cascada de sus bolsas chocaba con sus
-- transferencias y la eliminación de cuenta fallaba (error 1451). Las bolsas
-- nunca se borran desde la app (solo se archivan), así que CASCADE solo actúa
-- al eliminar al usuario.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261114_transfers_fk_cascade.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `transferencias_ahorro`
    DROP FOREIGN KEY `fk_transferencias_ahorro_origen`,
    DROP FOREIGN KEY `fk_transferencias_ahorro_destino`;

ALTER TABLE `transferencias_ahorro`
    ADD CONSTRAINT `fk_transferencias_ahorro_origen` FOREIGN KEY (`cuenta_origen_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_transferencias_ahorro_destino` FOREIGN KEY (`cuenta_destino_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE CASCADE;
