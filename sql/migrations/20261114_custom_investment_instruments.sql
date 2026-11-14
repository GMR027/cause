-- ============================================================================
-- Cauce · Tipos de inversión personalizados
--
-- Cada usuario puede crear sus propios tipos de inversión además de los
-- predefinidos (fondo, cripto, cetes, acciones, otro). Los movimientos guardan
-- la clave del tipo: la predefinida tal cual, o `u<id>` para un tipo propio.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261114_custom_investment_instruments.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `instrumentos_inversion` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(60) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_instrumentos_inversion_user_nombre` (`user_id`, `nombre`),
    CONSTRAINT `fk_instrumentos_inversion_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `aportaciones_inversion`
    MODIFY COLUMN `instrumento` VARCHAR(40) NOT NULL;

ALTER TABLE `retiros_inversion`
    MODIFY COLUMN `instrumento` VARCHAR(40) NOT NULL;

ALTER TABLE `inversiones_fondos`
    MODIFY COLUMN `instrumento` VARCHAR(40) NOT NULL;
