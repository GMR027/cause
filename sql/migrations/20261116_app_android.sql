-- ============================================================================
-- Cauce · Aplicación Android (Tauri 2, carpeta android/ del repositorio)
--
-- · app_android_versiones: cada APK publicado con bin/publicar_android.php.
--   El archivo vive en storage/android/ (volumen persistente), aquí solo el
--   registro. version_code = mayor·1 000 000 + menor·1 000 + parche (el mismo
--   número que Tauri pone en el APK; Android exige que crezca).
-- · dispositivos_android: lo que la app reporta al entrar por
--   /app/android/entrar (id aleatorio de instalación y versión instalada).
--   Sirve para ver qué teléfonos siguen con una versión vieja.
--
-- No es idempotente; init-db.sh la aplica una sola vez.
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261116_app_android.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

CREATE TABLE `app_android_versiones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version` VARCHAR(20) NOT NULL,
    `version_code` INT UNSIGNED NOT NULL,
    `archivo` VARCHAR(160) NOT NULL COMMENT 'Nombre dentro de storage/android/',
    `tamano` BIGINT UNSIGNED NOT NULL,
    `sha256` CHAR(64) NOT NULL,
    `notas` VARCHAR(500) NULL,
    `publicado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_app_android_version` (`version`),
    KEY `idx_app_android_code` (`version_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dispositivos_android` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `instalacion_id` CHAR(36) NOT NULL COMMENT 'UUID que genera la app al instalarse',
    `version` VARCHAR(20) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `modelo` VARCHAR(80) NULL,
    `version_android` VARCHAR(20) NULL,
    `primera_vez` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ultima_vez` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dispositivos_android_instalacion` (`instalacion_id`),
    KEY `idx_dispositivos_android_user` (`user_id`),
    CONSTRAINT `fk_dispositivos_android_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
