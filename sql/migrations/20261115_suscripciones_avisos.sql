-- ============================================================================
-- Cauce · Registro de suscripciones y avisos descartables
--
-- · notificaciones.descartada: la persona cerró la notificación con la X. Se
--   conserva la fila (su clave única evita que se vuelva a generar) pero ya
--   no se muestra ni cuenta como pendiente.
-- · gastos_recurrentes: campos para dar seguimiento a suscripciones
--   registradas desde Reportes → Suscripciones (proveedor, prueba gratis,
--   recordatorio antes del cobro, nota y fecha de cancelación).
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261115_suscripciones_avisos.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `notificaciones`
    ADD COLUMN `descartada` TINYINT(1) NOT NULL DEFAULT 0 AFTER `leida`;

ALTER TABLE `gastos_recurrentes`
    ADD COLUMN `es_suscripcion` TINYINT(1) NOT NULL DEFAULT 0 AFTER `deuda_id`,
    ADD COLUMN `proveedor` VARCHAR(80) NULL AFTER `es_suscripcion`,
    ADD COLUMN `prueba_hasta` DATE NULL AFTER `proveedor`,
    ADD COLUMN `recordatorio_dias` TINYINT UNSIGNED NULL AFTER `prueba_hasta`,
    ADD COLUMN `notas` VARCHAR(255) NULL AFTER `recordatorio_dias`,
    ADD COLUMN `cancelada_at` DATETIME NULL AFTER `notas`;
