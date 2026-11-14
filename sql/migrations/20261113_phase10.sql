-- ============================================================================
-- Cauce · Fase 10: lecciones bilingües y administración de contenido
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261113_phase10.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `lecciones`
    CHANGE COLUMN `titulo` `titulo_es` VARCHAR(160) NOT NULL,
    CHANGE COLUMN `contenido` `contenido_es` TEXT NOT NULL,
    CHANGE COLUMN `contexto` `seccion` VARCHAR(40) NOT NULL DEFAULT 'ahorro' AFTER `codigo`,
    ADD COLUMN `titulo_en` VARCHAR(160) NULL AFTER `titulo_es`,
    ADD COLUMN `contenido_en` TEXT NULL AFTER `contenido_es`,
    ADD COLUMN `publicada` TINYINT(1) NOT NULL DEFAULT 1 AFTER `contenido_en`,
    ADD KEY `idx_lecciones_seccion_publicada` (`seccion`, `publicada`);
