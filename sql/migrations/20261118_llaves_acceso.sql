-- ============================================================================
-- Cauce · Llaves de acceso (passkeys / WebAuthn): entrar con huella, rostro
-- o el bloqueo de pantalla del teléfono o la computadora.
--
-- · credencial_id: id de la credencial en base64url (único).
-- · llave_publica: llave pública en PEM (SPKI) que entrega el navegador.
-- · usuario_handle: id opaco que guarda el autenticador (igual en todas las
--   llaves de una persona).
-- · contador: signCount para detectar llaves clonadas.
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261118_llaves_acceso.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

CREATE TABLE `llaves_acceso` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `credencial_id` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `llave_publica` TEXT NOT NULL,
    `algoritmo` SMALLINT NOT NULL,
    `usuario_handle` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `contador` INT UNSIGNED NOT NULL DEFAULT 0,
    `nombre` VARCHAR(80) NOT NULL,
    `ultimo_uso_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_llaves_acceso_credencial` (`credencial_id`),
    KEY `idx_llaves_acceso_user` (`user_id`),
    CONSTRAINT `fk_llaves_acceso_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
