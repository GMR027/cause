-- ============================================================================
-- Cauce · Entrar con PIN (opcional)
--
-- · users.pin_login: 1 si la persona activó en Ajustes → Seguridad entrar con
--   su correo y PIN en lugar de la contraseña. Apagado por defecto; quitar el
--   PIN también lo apaga.
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261118_entrar_con_pin.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN `pin_login` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pin_locked_until`;
