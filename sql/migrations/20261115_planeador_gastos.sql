-- ============================================================================
-- Cauce · Planeador de gastos
--
-- Guarda un historial de planes calculados a partir del próximo ingreso:
-- fecha, monto, moneda, método y porcentajes usados, resumen del cálculo y
-- conceptos planeados por sección.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261115_planeador_gastos.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `planes_gastos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `fecha_ingreso` DATE NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NOT NULL,
    `metodo_id` BIGINT UNSIGNED NULL,
    `metodo_codigo` VARCHAR(40) NULL,
    `metodo_nombre` VARCHAR(80) NULL,
    `porcentajes_json` JSON NOT NULL,
    `resumen_json` JSON NOT NULL,
    `notas` VARCHAR(500) NULL,
    `estado` ENUM('activo', 'completado') NOT NULL DEFAULT 'activo',
    `ingreso_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_planes_gastos_user_fecha` (`user_id`, `fecha_ingreso` DESC, `created_at` DESC),
    KEY `idx_planes_gastos_ingreso` (`ingreso_id`),
    CONSTRAINT `fk_planes_gastos_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_planes_gastos_metodo`
        FOREIGN KEY (`metodo_id`) REFERENCES `metodos` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_planes_gastos_ingreso`
        FOREIGN KEY (`ingreso_id`) REFERENCES `ingresos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plan_gastos_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plan_id` BIGINT UNSIGNED NOT NULL,
    `seccion` VARCHAR(60) NOT NULL,
    `concepto` VARCHAR(120) NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `orden` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_plan_gastos_items_plan` (`plan_id`, `seccion`, `orden`),
    CONSTRAINT `fk_plan_gastos_items_plan`
        FOREIGN KEY (`plan_id`) REFERENCES `planes_gastos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
