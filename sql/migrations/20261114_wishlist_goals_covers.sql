-- ============================================================================
-- Cauce · Abonos a deseos y portadas de tarjetas
--
-- · lista_deseos.meta_id enlaza un deseo con la meta de ahorro (y su bolsa)
--   que se crea la primera vez que el usuario pulsa «Abonar».
-- · metas_ahorro.tipo admite 'deseo' para identificar esas metas en /ahorro.
-- · portada_path guarda una imagen pequeña (200 px) de portada para deseos,
--   bolsas, metas, deudas y posiciones de inversión. Los archivos viven en
--   storage/uploads/portadas/<user_id>/.
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261114_wishlist_goals_covers.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `metas_ahorro`
    MODIFY COLUMN `tipo` ENUM('ahorro', 'retiro', 'casa', 'educacion', 'otro', 'deseo') NOT NULL DEFAULT 'ahorro',
    ADD COLUMN `portada_path` VARCHAR(80) NULL AFTER `activa`;

ALTER TABLE `cuentas_ahorro`
    ADD COLUMN `portada_path` VARCHAR(80) NULL AFTER `activa`;

ALTER TABLE `deudas`
    ADD COLUMN `portada_path` VARCHAR(80) NULL AFTER `activa`;

ALTER TABLE `inversiones_posiciones`
    ADD COLUMN `portada_path` VARCHAR(80) NULL AFTER `activo`;

ALTER TABLE `lista_deseos`
    ADD COLUMN `meta_id` BIGINT UNSIGNED NULL AFTER `gasto_id`,
    ADD COLUMN `portada_path` VARCHAR(80) NULL AFTER `meta_id`,
    ADD KEY `idx_lista_deseos_meta_id` (`meta_id`),
    ADD CONSTRAINT `fk_lista_deseos_meta` FOREIGN KEY (`meta_id`) REFERENCES `metas_ahorro` (`id`) ON DELETE SET NULL;
