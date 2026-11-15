-- ============================================================================
-- Cauce · Acreditar en un paso y opacidad de texturas
--
-- · gastos_recurrentes.no_controlado_hasta: al acreditar un ingreso, la persona
--   puede mandar un recurrente a «no controlado» para ese período. Mientras la
--   fecha del cobro sea <= a este valor, el gasto se registra como no
--   controlado y no descuenta de su categoría.
-- · users.tema_textura_opacidad: opacidad (%) de la textura de fondo.
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261115_acreditar_temas.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `gastos_recurrentes`
    ADD COLUMN `no_controlado_hasta` DATETIME NULL AFTER `activo`;

ALTER TABLE `users`
    ADD COLUMN `tema_textura_opacidad` TINYINT UNSIGNED NULL AFTER `tema_textura`;
