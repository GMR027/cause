-- ============================================================================
-- Cauce · Portafolios de inversión
--
-- Portafolio → Instrumentos (inversiones_posiciones) → Movimientos
-- (aportaciones, retiros y rendimientos/bajas/comisiones).
--
-- · inversiones_portafolios: agrupador con clase (SOFIPO, acciones, FIBRAs…).
--   `instrumento_legado` guarda la clave antigua (`cetes`, `u12`…) para
--   enlazar movimientos viejos y seguir llenando `instrumento`.
-- · inversiones_posiciones: ahora es el instrumento; se liga a un portafolio
--   y admite tasa, vencimiento y el modo de valor `calculado`.
-- · aportaciones/retiros: portafolio_id y posicion_id opcionales. NULL en
--   posicion_id significa «efectivo del portafolio».
-- · inversiones_rendimientos: intereses, dividendos, rentas, bajas y
--   comisiones; los pagados a la cuenta crean un ingreso extra.
--
-- Los datos existentes se convierten en PHP la primera vez que la persona
-- abre /inversion (PortfolioService::ensureMigrated).
--
-- No es idempotente; init-db.sh la aplica una sola vez.
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

CREATE TABLE `inversiones_portafolios` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(80) NOT NULL,
    `clase` ENUM('sofipo', 'acciones', 'fibras', 'cetes_bonos', 'fondos', 'cripto', 'otro') NOT NULL DEFAULT 'otro',
    `institucion` VARCHAR(120) NULL,
    `instrumento_legado` VARCHAR(40) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_inversiones_portafolios_user_nombre` (`user_id`, `nombre`),
    CONSTRAINT `fk_inversiones_portafolios_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `inversiones_posiciones`
    ADD COLUMN `portafolio_id` BIGINT UNSIGNED NULL AFTER `user_id`,
    ADD COLUMN `tasa_anual` DECIMAL(7,4) NULL AFTER `fecha_valor_manual`,
    ADD COLUMN `fecha_vencimiento` DATE NULL AFTER `tasa_anual`,
    ADD COLUMN `notas` VARCHAR(255) NULL AFTER `fecha_vencimiento`,
    MODIFY `tipo` ENUM('accion', 'etf', 'cripto', 'cetes', 'fondo', 'sofipo', 'fibra', 'pagare', 'bono', 'otro') NOT NULL DEFAULT 'otro',
    MODIFY `modo_valor` ENUM('automatico', 'manual', 'calculado') NOT NULL DEFAULT 'manual',
    ADD KEY `idx_inversiones_posiciones_portafolio` (`portafolio_id`),
    ADD CONSTRAINT `fk_inversiones_posiciones_portafolio` FOREIGN KEY (`portafolio_id`) REFERENCES `inversiones_portafolios` (`id`) ON DELETE SET NULL;

ALTER TABLE `aportaciones_inversion`
    ADD COLUMN `portafolio_id` BIGINT UNSIGNED NULL AFTER `instrumento`,
    ADD COLUMN `posicion_id` BIGINT UNSIGNED NULL AFTER `portafolio_id`,
    ADD KEY `idx_aportaciones_inversion_portafolio` (`portafolio_id`),
    ADD KEY `idx_aportaciones_inversion_posicion` (`posicion_id`),
    ADD CONSTRAINT `fk_aportaciones_inversion_portafolio` FOREIGN KEY (`portafolio_id`) REFERENCES `inversiones_portafolios` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_aportaciones_inversion_posicion` FOREIGN KEY (`posicion_id`) REFERENCES `inversiones_posiciones` (`id`) ON DELETE SET NULL;

ALTER TABLE `retiros_inversion`
    ADD COLUMN `portafolio_id` BIGINT UNSIGNED NULL AFTER `instrumento`,
    ADD COLUMN `posicion_id` BIGINT UNSIGNED NULL AFTER `portafolio_id`,
    ADD KEY `idx_retiros_inversion_portafolio` (`portafolio_id`),
    ADD KEY `idx_retiros_inversion_posicion` (`posicion_id`),
    ADD CONSTRAINT `fk_retiros_inversion_portafolio` FOREIGN KEY (`portafolio_id`) REFERENCES `inversiones_portafolios` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_retiros_inversion_posicion` FOREIGN KEY (`posicion_id`) REFERENCES `inversiones_posiciones` (`id`) ON DELETE SET NULL;

CREATE TABLE `inversiones_rendimientos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `portafolio_id` BIGINT UNSIGNED NOT NULL,
    `posicion_id` BIGINT UNSIGNED NULL,
    `tipo` ENUM('interes', 'dividendo', 'renta', 'baja', 'comision') NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `destino` ENUM('reinvertido', 'pagado') NOT NULL DEFAULT 'reinvertido',
    `ingreso_id` BIGINT UNSIGNED NULL,
    `fecha` DATETIME NOT NULL,
    `nota` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inversiones_rendimientos_user_fecha` (`user_id`, `fecha`),
    KEY `idx_inversiones_rendimientos_portafolio` (`portafolio_id`),
    KEY `idx_inversiones_rendimientos_posicion` (`posicion_id`),
    CONSTRAINT `fk_inversiones_rendimientos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inversiones_rendimientos_portafolio` FOREIGN KEY (`portafolio_id`) REFERENCES `inversiones_portafolios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inversiones_rendimientos_posicion` FOREIGN KEY (`posicion_id`) REFERENCES `inversiones_posiciones` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_inversiones_rendimientos_ingreso` FOREIGN KEY (`ingreso_id`) REFERENCES `ingresos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
