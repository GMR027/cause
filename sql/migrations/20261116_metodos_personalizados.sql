-- ============================================================================
-- Cauce · Métodos de reparto personalizados
--
-- · metodos.user_id: NULL para el catálogo; con valor, el método lo creó esa
--   persona en Ajustes → Ingreso y solo ella lo ve.
-- · metodos.archivado_at: un método propio eliminado se archiva para no
--   romper el historial (usuario_metodos, ingresos).
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261116_metodos_personalizados.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `metodos`
    ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `id`,
    ADD COLUMN `archivado_at` DATETIME NULL AFTER `orden`,
    ADD KEY `idx_metodos_user_id` (`user_id`, `archivado_at`),
    ADD CONSTRAINT `fk_metodos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
