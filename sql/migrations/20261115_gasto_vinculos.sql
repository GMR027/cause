-- ============================================================================
-- Cauce · Vínculos para deshacer los efectos de un gasto al borrarlo
--
-- · gastos.deuda_exceso_id / deuda_exceso_centavos: cuando el exceso de un
--   gasto se manda a una deuda (nueva o existente), se guarda a cuál y cuánto
--   (moneda principal) para restarlo si el gasto se elimina.
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261115_gasto_vinculos.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `gastos`
    ADD COLUMN `deuda_exceso_id` BIGINT UNSIGNED NULL AFTER `fuente_exceso`,
    ADD COLUMN `deuda_exceso_centavos` BIGINT NULL AFTER `deuda_exceso_id`;
