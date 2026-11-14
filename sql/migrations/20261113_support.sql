-- ============================================================================
-- Cauce · Soporte: conversaciones entre usuarios y administración
--
-- Uso:
--   mysql -u root -p cauce < sql/migrations/20261113_support.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `soporte_conversaciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `asunto` VARCHAR(160) NOT NULL,
    `estado` ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
    `ultimo_mensaje_at` DATETIME NULL,
    `ultimo_autor` ENUM('usuario', 'admin') NULL,
    `usuario_leido_at` DATETIME NULL,
    `admin_leido_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_soporte_conversaciones_user` (`user_id`, `ultimo_mensaje_at`),
    KEY `idx_soporte_conversaciones_estado` (`estado`, `ultimo_mensaje_at`),
    CONSTRAINT `fk_soporte_conversaciones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `soporte_mensajes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversacion_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `autor` ENUM('usuario', 'admin') NOT NULL,
    `autor_id` BIGINT UNSIGNED NULL,
    `mensaje` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_soporte_mensajes_conversacion` (`conversacion_id`, `created_at`),
    KEY `idx_soporte_mensajes_user` (`user_id`),
    CONSTRAINT `fk_soporte_mensajes_conversacion` FOREIGN KEY (`conversacion_id`) REFERENCES `soporte_conversaciones` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_soporte_mensajes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `notificaciones_config`
    MODIFY `tipo` ENUM(
        'ingreso_dia_menos_1', 'ingreso_dia_0', 'ingreso_rescate',
        'gasto_recurrente', 'recordatorio_deuda', 'alerta_categoria',
        'resumen_mensual', 'soporte_respuesta'
    ) NOT NULL;

DELETE FROM `notificaciones_config` WHERE `tipo` = '';

UPDATE `legal_documentos`
SET
    `contenido_es` = REPLACE(
        REPLACE(
            `contenido_es`,
            'Escribe a soporte@cauce.app para dudas sobre privacidad.',
            'Abre una conversación desde Ajustes → Soporte dentro de Cauce para dudas sobre privacidad.'
        ),
        'Escribe a soporte@cauce.app.',
        'Abre una conversación desde Ajustes → Soporte dentro de Cauce.'
    ),
    `contenido_en` = REPLACE(
        REPLACE(
            `contenido_en`,
            'Write to soporte@cauce.app for privacy questions.',
            'Open a conversation from Settings → Support inside Cauce for privacy questions.'
        ),
        'Write to soporte@cauce.app.',
        'Open a conversation from Settings → Support inside Cauce.'
    ),
    `updated_at` = NOW();
