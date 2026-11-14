-- ============================================================================
-- Cauce · Fase 7: enganche, progreso y lista de deseos
--
-- Migración para instalaciones existentes. En una instalación nueva basta con
-- ejecutar sql/schema.sql, que ya incluye estos cambios.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261113_phase7.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN `comparacion_social` TINYINT(1) NOT NULL DEFAULT 0 AFTER `gamificacion_activa`;

ALTER TABLE `hitos_usuario`
    ADD COLUMN `celebrado_at` DATETIME NULL AFTER `fecha_logro`;

ALTER TABLE `rachas`
    ADD COLUMN `mejor_racha` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `periodos_consecutivos`,
    ADD COLUMN `ultimo_periodo_inicio` DATETIME NULL AFTER `estado`,
    ADD COLUMN `ultimo_periodo_fin` DATETIME NULL AFTER `ultimo_periodo_inicio`,
    ADD COLUMN `congelada_desde` DATETIME NULL AFTER `ultimo_periodo_fin`;

ALTER TABLE `niveles`
    ADD COLUMN `detalle_json` JSON NULL AFTER `porcentaje_cumplimiento`;

ALTER TABLE `retos`
    MODIFY COLUMN `tipo` ENUM('cumple_ahorro', 'cumple_inversion', 'cumple_donacion', 'registra_todo', 'racha') NOT NULL,
    MODIFY COLUMN `estado` ENUM('activo', 'completado', 'expirado', 'descartado') NOT NULL DEFAULT 'activo';

ALTER TABLE `lista_deseos`
    ADD COLUMN `gasto_id` BIGINT UNSIGNED NULL AFTER `nota`,
    ADD COLUMN `comprado_at` DATETIME NULL AFTER `gasto_id`,
    ADD KEY `idx_lista_deseos_gasto_id` (`gasto_id`),
    ADD CONSTRAINT `fk_lista_deseos_gasto`
        FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE SET NULL;
