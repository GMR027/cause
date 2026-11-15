-- ============================================================================
-- Cauce · Sección «Compartir» separada de Donación
--
-- · metodo_categorias.tipo admite 'compartir'. En Cinco frascos la categoría
--   «Compartir» era de tipo 'donacion' y se sumaba al panel de Donación.
-- · compartidos guarda lo que la persona comparte (familia, amigos, regalos)
--   contra el % de Compartir del método.
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261114_compartir.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `metodo_categorias`
    MODIFY COLUMN `tipo` ENUM('controlada', 'ahorro', 'inversion', 'donacion', 'compartir', 'no_controlado') NOT NULL;

UPDATE `metodo_categorias` mc
JOIN `metodos` m ON m.id = mc.metodo_id
SET mc.tipo = 'compartir'
WHERE m.codigo = 'cinco_frascos' AND mc.nombre = 'Compartir';

CREATE TABLE `compartidos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `destinatario` VARCHAR(120) NULL,
    `es_extra` TINYINT(1) NOT NULL DEFAULT 0,
    `fecha` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_compartidos_user_fecha` (`user_id`, `fecha`),
    CONSTRAINT `fk_compartidos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
