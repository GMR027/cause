-- ============================================================================
-- Cauce · esquema de base de datos
-- Fase 11: estructura completa (57 tablas) y datos semilla en seed.sql.
--
-- Características:
--   - MySQL 8.0+, InnoDB, utf8mb4 / utf8mb4_unicode_ci.
--   - PK BIGINT UNSIGNED AUTO_INCREMENT (salvo sesiones y tablas pivote).
--   - Montos en centavos (BIGINT) con moneda CHAR(3) DEFAULT 'MXN'.
--   - Fechas DATETIME en UTC; fechas de calendario en DATE.
--   - FKs de pertenencia ON DELETE CASCADE; referencias opcionales SET NULL.
--
-- Este archivo es reejecutable: elimina y vuelve a crear las tablas.
-- ¡ATENCIÓN! Destruye los datos existentes de la base `cauce`.
--
-- Uso:
--   mysql -u root -p < sql/schema.sql
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `cauce`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `cauce`;

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `export_tokens`;
DROP TABLE IF EXISTS `legal_aceptaciones`;
DROP TABLE IF EXISTS `legal_documentos`;
DROP TABLE IF EXISTS `analytics_eventos`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `estados_cuenta_consentimientos`;
DROP TABLE IF EXISTS `estados_cuenta`;
DROP TABLE IF EXISTS `soporte_mensajes`;
DROP TABLE IF EXISTS `soporte_conversaciones`;
DROP TABLE IF EXISTS `tasas_cambio`;
DROP TABLE IF EXISTS `monedas_usuario`;
DROP TABLE IF EXISTS `score_snapshots`;
DROP TABLE IF EXISTS `lecciones_vistas`;
DROP TABLE IF EXISTS `lecciones`;
DROP TABLE IF EXISTS `lista_deseos`;
DROP TABLE IF EXISTS `patrimonio_snapshots`;
DROP TABLE IF EXISTS `pagos_deuda`;
DROP TABLE IF EXISTS `deudas`;
DROP TABLE IF EXISTS `retos`;
DROP TABLE IF EXISTS `niveles`;
DROP TABLE IF EXISTS `rachas`;
DROP TABLE IF EXISTS `hitos_usuario`;
DROP TABLE IF EXISTS `notificaciones`;
DROP TABLE IF EXISTS `resumenes_mensuales`;
DROP TABLE IF EXISTS `push_suscripciones`;
DROP TABLE IF EXISTS `notificaciones_config`;
DROP TABLE IF EXISTS `pausas_inversion`;
DROP TABLE IF EXISTS `inversiones_precios`;
DROP TABLE IF EXISTS `inversiones_posiciones`;
DROP TABLE IF EXISTS `inversiones_fondos`;
DROP TABLE IF EXISTS `donaciones`;
DROP TABLE IF EXISTS `retiros_inversion`;
DROP TABLE IF EXISTS `aportaciones_inversion`;
DROP TABLE IF EXISTS `transferencias_ahorro`;
DROP TABLE IF EXISTS `retiros_ahorro`;
DROP TABLE IF EXISTS `aportaciones_ahorro`;
DROP TABLE IF EXISTS `metas_ahorro`;
DROP TABLE IF EXISTS `cuentas_ahorro`;
DROP TABLE IF EXISTS `movimientos`;
DROP TABLE IF EXISTS `gasto_tags`;
DROP TABLE IF EXISTS `tags`;
DROP TABLE IF EXISTS `plantillas_gasto`;
DROP TABLE IF EXISTS `gastos`;
DROP TABLE IF EXISTS `gastos_recurrentes`;
DROP TABLE IF EXISTS `no_controlado`;
DROP TABLE IF EXISTS `justificaciones`;
DROP TABLE IF EXISTS `asignaciones`;
DROP TABLE IF EXISTS `ingresos`;
DROP TABLE IF EXISTS `subcategorias`;
DROP TABLE IF EXISTS `usuario_metodos`;
DROP TABLE IF EXISTS `metodo_categorias`;
DROP TABLE IF EXISTS `metodos`;
DROP TABLE IF EXISTS `hogar_miembros`;
DROP TABLE IF EXISTS `admin_audit_log`;
DROP TABLE IF EXISTS `codigos_recuperacion`;
DROP TABLE IF EXISTS `intentos_recuperacion`;
DROP TABLE IF EXISTS `preguntas_recuperacion`;
DROP TABLE IF EXISTS `sesiones`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `hogares`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Hogares y usuarios
-- ============================================================================

CREATE TABLE `hogares` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(120) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `alias` VARCHAR(30) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `foto_url` VARCHAR(500) NULL,
    `moneda_activa` CHAR(3) NOT NULL DEFAULT 'MXN',
    `zona_horaria` VARCHAR(64) NOT NULL DEFAULT 'America/Mexico_City',
    `idioma` ENUM('es', 'en') NOT NULL DEFAULT 'es',
    `rol` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `estado` ENUM('activo', 'suspendido') NOT NULL DEFAULT 'activo',
    `suspendido_motivo` VARCHAR(255) NULL,
    `suspendido_at` DATETIME NULL,
    `pin_hash` VARCHAR(255) NULL,
    `pin_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `pin_locked_until` DATETIME NULL,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `analytics_opt_out` TINYINT(1) NOT NULL DEFAULT 0,
    `last_login_at` DATETIME NULL,
    `password_changed_at` DATETIME NULL,
    `tono_notificaciones` ENUM('neutral', 'motivacional', 'empatico') NOT NULL DEFAULT 'neutral',
    `push_pausado_hasta` DATETIME NULL,
    `gamificacion_activa` TINYINT(1) NOT NULL DEFAULT 1,
    `comparacion_social` TINYINT(1) NOT NULL DEFAULT 0,
    `modo_discreto` TINYINT(1) NOT NULL DEFAULT 0,
    `onboarding_completado` TINYINT(1) NOT NULL DEFAULT 0,
    `onboarding_estado` JSON NULL,
    `periodo_inicio_dia` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `tipo_ingreso` ENUM('fijo', 'variable', 'mixto') NULL,
    `recurrencia` ENUM('diaria', 'semanal', 'mensual', 'anual') NULL,
    `recurrencia_anterior` ENUM('diaria', 'semanal', 'mensual', 'anual') NULL,
    `recurrencia_cambio_desde` DATETIME NULL,
    `extra_reparto_json` JSON NULL,
    `efectivo_manual_centavos` BIGINT NULL,
    `estrategia_deuda` ENUM('bola_nieve', 'avalancha', 'manual') NOT NULL DEFAULT 'manual',
    `hogar_id` BIGINT UNSIGNED NULL,
    `tema_modo` ENUM('claro', 'oscuro', 'auto') NOT NULL DEFAULT 'auto',
    `tema_color_principal` CHAR(7) NOT NULL DEFAULT '#157f6b',
    `tema_enfoque` ENUM('iconos', 'botones', 'mixto') NOT NULL DEFAULT 'mixto',
    `tema_efecto_fondo` ENUM('ninguno', 'radial', 'textura', 'radial_textura') NOT NULL DEFAULT 'ninguno',
    `tema_textura` VARCHAR(40) NULL,
    `email_verificado_at` DATETIME NULL,
    `email_verificacion_token` VARCHAR(64) NULL,
    `email_verificacion_expira` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_alias` (`alias`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_estado` (`estado`),
    KEY `idx_users_hogar_id` (`hogar_id`),
    KEY `idx_users_email_verificacion_token` (`email_verificacion_token`),
    CONSTRAINT `fk_users_hogar` FOREIGN KEY (`hogar_id`) REFERENCES `hogares` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Monedas y tasas de cambio
-- ============================================================================

CREATE TABLE `monedas_usuario` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `codigo` CHAR(3) NOT NULL,
    `es_principal` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_monedas_usuario_user_codigo` (`user_id`, `codigo`),
    KEY `idx_monedas_usuario_user_principal` (`user_id`, `es_principal`),
    CONSTRAINT `fk_monedas_usuario_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tasas_cambio` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `moneda_origen` CHAR(3) NOT NULL,
    `moneda_destino` CHAR(3) NOT NULL,
    `tasa` DECIMAL(18,8) NOT NULL,
    `fecha` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tasas_cambio_par_fecha` (`moneda_origen`, `moneda_destino`, `fecha`),
    KEY `idx_tasas_cambio_fecha` (`fecha`),
    CONSTRAINT `chk_tasas_cambio_positiva` CHECK (`tasa` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Seguridad y sesiones
-- ============================================================================

CREATE TABLE `password_resets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_password_resets_token_hash` (`token_hash`),
    KEY `idx_password_resets_user_id` (`user_id`),
    CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sesiones` (
    `id` CHAR(128) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `last_activity` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sesiones_user_id` (`user_id`),
    KEY `idx_sesiones_last_activity` (`last_activity`),
    CONSTRAINT `fk_sesiones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `preguntas_recuperacion` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `pregunta` VARCHAR(255) NOT NULL,
    `respuesta_hash` VARCHAR(255) NOT NULL,
    `orden` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_preguntas_recuperacion_user_orden` (`user_id`, `orden`),
    CONSTRAINT `fk_preguntas_recuperacion_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `intentos_recuperacion` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `exitoso` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_intentos_recuperacion_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_intentos_recuperacion_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `codigos_recuperacion` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `codigo_hash` CHAR(64) NOT NULL,
    `usado` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_codigos_recuperacion_codigo_hash` (`codigo_hash`),
    KEY `idx_codigos_recuperacion_user_id` (`user_id`),
    CONSTRAINT `fk_codigos_recuperacion_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_audit_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` BIGINT UNSIGNED NULL,
    `admin_alias` VARCHAR(30) NULL,
    `admin_email` VARCHAR(190) NULL,
    `accion` VARCHAR(80) NOT NULL,
    `entidad` VARCHAR(60) NULL,
    `entidad_id` BIGINT UNSIGNED NULL,
    `detalles_json` JSON NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_audit_log_admin_created` (`admin_id`, `created_at`),
    KEY `idx_admin_audit_log_entidad` (`entidad`, `entidad_id`),
    CONSTRAINT `fk_admin_audit_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Hogares: miembros
-- ============================================================================

CREATE TABLE `hogar_miembros` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `hogar_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `rol` ENUM('owner', 'miembro') NOT NULL DEFAULT 'miembro',
    `estado` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_hogar_miembros_hogar_user` (`hogar_id`, `user_id`),
    KEY `idx_hogar_miembros_user_id` (`user_id`),
    CONSTRAINT `fk_hogar_miembros_hogar` FOREIGN KEY (`hogar_id`) REFERENCES `hogares` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hogar_miembros_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Métodos de presupuestación (catálogo)
-- ============================================================================

CREATE TABLE `metodos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo` VARCHAR(40) NOT NULL,
    `nombre` VARCHAR(80) NOT NULL,
    `descripcion_corta` VARCHAR(255) NOT NULL,
    `contexto_cultural` TEXT NULL,
    `icono` VARCHAR(60) NULL,
    `es_cultural` TINYINT(1) NOT NULL DEFAULT 0,
    `orden` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_metodos_codigo` (`codigo`),
    KEY `idx_metodos_orden` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `metodo_categorias` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `metodo_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(80) NOT NULL,
    `porcentaje` TINYINT UNSIGNED NULL,
    `tipo` ENUM('controlada', 'ahorro', 'inversion', 'donacion', 'no_controlado') NOT NULL,
    `orden` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_metodo_categorias_metodo_nombre` (`metodo_id`, `nombre`),
    KEY `idx_metodo_categorias_metodo_orden` (`metodo_id`, `orden`),
    CONSTRAINT `fk_metodo_categorias_metodo` FOREIGN KEY (`metodo_id`) REFERENCES `metodos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_metodo_categorias_porcentaje` CHECK (`porcentaje` IS NULL OR `porcentaje` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_metodos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `metodo_id` BIGINT UNSIGNED NOT NULL,
    `porcentajes_json` JSON NOT NULL,
    `vigente_desde` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `vigente_hasta` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_usuario_metodos_user_vigente` (`user_id`, `vigente_hasta`),
    KEY `idx_usuario_metodos_metodo_id` (`metodo_id`),
    CONSTRAINT `fk_usuario_metodos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_usuario_metodos_metodo` FOREIGN KEY (`metodo_id`) REFERENCES `metodos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Subcategorías, ingresos y asignaciones
-- ============================================================================

CREATE TABLE `subcategorias` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(80) NOT NULL,
    `parte_metodo_default` VARCHAR(60) NOT NULL,
    `parte_metodo_actual` VARCHAR(60) NULL,
    `es_semilla` TINYINT(1) NOT NULL DEFAULT 0,
    `deleted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subcategorias_user_nombre` (`user_id`, `nombre`),
    KEY `idx_subcategorias_user_deleted` (`user_id`, `deleted_at`),
    CONSTRAINT `fk_subcategorias_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ingresos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `metodo_id` BIGINT UNSIGNED NULL,
    `periodo_inicio` DATETIME NOT NULL,
    `periodo_fin` DATETIME NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `tipo` ENUM('fijo', 'variable', 'extra') NOT NULL,
    `recurrencia` ENUM('diaria', 'semanal', 'mensual', 'anual') NULL,
    `fecha` DATETIME NOT NULL,
    `nota` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ingresos_user_periodo` (`user_id`, `periodo_inicio`, `periodo_fin`),
    KEY `idx_ingresos_user_fecha` (`user_id`, `fecha`),
    KEY `idx_ingresos_metodo_id` (`metodo_id`),
    CONSTRAINT `fk_ingresos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ingresos_metodo` FOREIGN KEY (`metodo_id`) REFERENCES `metodos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `asignaciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ingreso_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `categoria` VARCHAR(60) NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NOT NULL DEFAULT 0,
    `estado` ENUM('acreditado', 'justificado', 'no_controlado', 'pendiente_asignacion') NOT NULL,
    `justificacion_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_asignaciones_ingreso_id` (`ingreso_id`),
    KEY `idx_asignaciones_user_estado` (`user_id`, `estado`),
    KEY `idx_asignaciones_justificacion_id` (`justificacion_id`),
    CONSTRAINT `fk_asignaciones_ingreso` FOREIGN KEY (`ingreso_id`) REFERENCES `ingresos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_asignaciones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `justificaciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `asignacion_id` BIGINT UNSIGNED NOT NULL,
    `motivo_catalogo` ENUM('aun_no_transfiero', 'lo_usare_luego', 'efectivo', 'otro') NOT NULL,
    `texto` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_justificaciones_asignacion_id` (`asignacion_id`),
    CONSTRAINT `fk_justificaciones_asignacion` FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Referencia circular: se agrega después de crear justificaciones.
ALTER TABLE `asignaciones`
    ADD CONSTRAINT `fk_asignaciones_justificacion`
    FOREIGN KEY (`justificacion_id`) REFERENCES `justificaciones` (`id`) ON DELETE SET NULL;

-- ============================================================================
-- No controlado
-- ============================================================================

CREATE TABLE `no_controlado` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `periodo_inicio` DATETIME NOT NULL,
    `periodo_fin` DATETIME NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `origen` ENUM('asignacion', 'gasto_excedido', 'ajuste', 'otro') NOT NULL,
    `referencia_id` BIGINT UNSIGNED NULL,
    `nota` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_no_controlado_user_periodo` (`user_id`, `periodo_inicio`, `periodo_fin`),
    CONSTRAINT `fk_no_controlado_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Gastos, recurrentes, plantillas y etiquetas
-- ============================================================================

CREATE TABLE `gastos_recurrentes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `monto_variable` TINYINT(1) NOT NULL DEFAULT 0,
    `parte_metodo` VARCHAR(60) NOT NULL,
    `subcategoria_id` BIGINT UNSIGNED NULL,
    `deuda_id` BIGINT UNSIGNED NULL,
    `recurrencia` ENUM('diaria', 'semanal', 'mensual', 'anual') NOT NULL,
    `proxima_ejecucion` DATETIME NOT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gastos_recurrentes_user_activo` (`user_id`, `activo`),
    KEY `idx_gastos_recurrentes_proxima_ejecucion` (`proxima_ejecucion`),
    KEY `idx_gastos_recurrentes_subcategoria_id` (`subcategoria_id`),
    KEY `idx_gastos_recurrentes_deuda_id` (`deuda_id`),
    CONSTRAINT `fk_gastos_recurrentes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gastos_recurrentes_subcategoria` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gastos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `periodo_inicio` DATETIME NOT NULL,
    `periodo_fin` DATETIME NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `monto_total_compartido_principal_centavos` BIGINT NULL,
    `monto_total_compartido_centavos` BIGINT NULL,
    `parte_metodo` VARCHAR(60) NOT NULL,
    `subcategoria_id` BIGINT UNSIGNED NULL,
    `subcategoria_nombre` VARCHAR(60) NULL,
    `fecha` DATETIME NOT NULL,
    `nota` TEXT NULL,
    `foto_path` VARCHAR(500) NULL,
    `medio_pago` ENUM('efectivo', 'debito', 'credito', 'transferencia', 'otro') NULL,
    `estado` ENUM('asignado', 'pendiente_asignacion', 'no_controlado', 'cerrado') NOT NULL DEFAULT 'asignado',
    `fuente_exceso` ENUM('ahorro', 'inversion', 'ingreso_no_registrado', 'deuda', 'no_lo_se', 'no_aplica') NOT NULL DEFAULT 'no_aplica',
    `confirmado` TINYINT(1) NOT NULL DEFAULT 1,
    `es_recurrente` TINYINT(1) NOT NULL DEFAULT 0,
    `gasto_recurrente_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gastos_user_periodo` (`user_id`, `periodo_inicio`, `periodo_fin`),
    KEY `idx_gastos_user_fecha` (`user_id`, `fecha`),
    KEY `idx_gastos_user_estado` (`user_id`, `estado`),
    KEY `idx_gastos_subcategoria_id` (`subcategoria_id`),
    KEY `idx_gastos_gasto_recurrente_id` (`gasto_recurrente_id`),
    FULLTEXT KEY `ft_gastos_nota_subcategoria` (`nota`, `subcategoria_nombre`),
    CONSTRAINT `fk_gastos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gastos_subcategoria` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_gastos_gasto_recurrente` FOREIGN KEY (`gasto_recurrente_id`) REFERENCES `gastos_recurrentes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `plantillas_gasto` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `parte_metodo` VARCHAR(60) NOT NULL,
    `subcategoria_id` BIGINT UNSIGNED NULL,
    `tags_json` JSON NULL,
    `usos` INT UNSIGNED NOT NULL DEFAULT 0,
    `ultimo_uso_at` DATETIME NULL,
    `orden` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_plantillas_gasto_user_orden` (`user_id`, `orden`),
    KEY `idx_plantillas_gasto_subcategoria_id` (`subcategoria_id`),
    CONSTRAINT `fk_plantillas_gasto_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_plantillas_gasto_subcategoria` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tags` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre_normalizado` VARCHAR(80) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tags_user_nombre` (`user_id`, `nombre_normalizado`),
    CONSTRAINT `fk_tags_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gasto_tags` (
    `gasto_id` BIGINT UNSIGNED NOT NULL,
    `tag_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`gasto_id`, `tag_id`),
    KEY `idx_gasto_tags_tag_id` (`tag_id`),
    CONSTRAINT `fk_gasto_tags_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gasto_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Movimientos entre categorías
-- ============================================================================

CREATE TABLE `movimientos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `periodo_inicio` DATETIME NOT NULL,
    `periodo_fin` DATETIME NOT NULL,
    `origen` VARCHAR(60) NOT NULL,
    `destino` VARCHAR(60) NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `motivo_catalogo` ENUM('emergencia', 'ajuste_prioridades', 'error_asignacion', 'otro') NOT NULL,
    `motivo_texto` VARCHAR(255) NULL,
    `fecha` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_movimientos_user_fecha` (`user_id`, `fecha`),
    KEY `idx_movimientos_user_periodo` (`user_id`, `periodo_inicio`, `periodo_fin`),
    CONSTRAINT `fk_movimientos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Ahorro
-- ============================================================================

CREATE TABLE `cuentas_ahorro` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `tipo` ENUM('banco', 'efectivo', 'cooperativa', 'otro') NOT NULL DEFAULT 'otro',
    `institucion` VARCHAR(120) NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `es_predeterminada` TINYINT(1) NOT NULL DEFAULT 0,
    `activa` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cuentas_ahorro_user_activa` (`user_id`, `activa`),
    KEY `idx_cuentas_ahorro_user_predeterminada` (`user_id`, `es_predeterminada`),
    CONSTRAINT `fk_cuentas_ahorro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `metas_ahorro` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `cuenta_id` BIGINT UNSIGNED NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `tipo` ENUM('ahorro', 'retiro', 'casa', 'educacion', 'otro') NOT NULL DEFAULT 'ahorro',
    `objetivo_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `objetivo_principal_centavos` BIGINT NULL,
    `fecha_objetivo` DATE NULL,
    `plazo_meses` TINYINT UNSIGNED NULL,
    `es_emergencia` TINYINT(1) NOT NULL DEFAULT 0,
    `meses_objetivo` TINYINT NOT NULL DEFAULT 3,
    `activa` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_metas_ahorro_user_activa` (`user_id`, `activa`),
    KEY `idx_metas_ahorro_cuenta` (`cuenta_id`),
    CONSTRAINT `fk_metas_ahorro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_metas_ahorro_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aportaciones_ahorro` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `meta_id` BIGINT UNSIGNED NULL,
    `cuenta_id` BIGINT UNSIGNED NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `es_saldo_inicial` TINYINT(1) NOT NULL DEFAULT 0,
    `es_reasignacion` TINYINT(1) NOT NULL DEFAULT 0,
    `fecha` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_aportaciones_ahorro_user_fecha` (`user_id`, `fecha`),
    KEY `idx_aportaciones_ahorro_meta_id` (`meta_id`),
    KEY `idx_aportaciones_ahorro_cuenta` (`cuenta_id`),
    CONSTRAINT `fk_aportaciones_ahorro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_aportaciones_ahorro_meta` FOREIGN KEY (`meta_id`) REFERENCES `metas_ahorro` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_aportaciones_ahorro_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `retiros_ahorro` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `meta_id` BIGINT UNSIGNED NULL,
    `cuenta_id` BIGINT UNSIGNED NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `es_reasignacion` TINYINT(1) NOT NULL DEFAULT 0,
    `motivo_catalogo` ENUM('emergencia', 'meta_cumplida', 'reasignacion', 'otro') NOT NULL,
    `motivo_texto` VARCHAR(255) NULL,
    `gasto_asociado_id` BIGINT UNSIGNED NULL,
    `fecha` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_retiros_ahorro_user_fecha` (`user_id`, `fecha`),
    KEY `idx_retiros_ahorro_meta_id` (`meta_id`),
    KEY `idx_retiros_ahorro_cuenta` (`cuenta_id`),
    KEY `idx_retiros_ahorro_gasto_id` (`gasto_asociado_id`),
    CONSTRAINT `fk_retiros_ahorro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_retiros_ahorro_meta` FOREIGN KEY (`meta_id`) REFERENCES `metas_ahorro` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_retiros_ahorro_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_retiros_ahorro_gasto` FOREIGN KEY (`gasto_asociado_id`) REFERENCES `gastos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transferencias_ahorro` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `cuenta_origen_id` BIGINT UNSIGNED NOT NULL,
    `cuenta_destino_id` BIGINT UNSIGNED NOT NULL,
    `meta_id` BIGINT UNSIGNED NULL,
    `motivo` ENUM('transferencia', 'mover_meta') NOT NULL DEFAULT 'transferencia',
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `nota` VARCHAR(255) NULL,
    `fecha` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_transferencias_ahorro_user_fecha` (`user_id`, `fecha`),
    KEY `idx_transferencias_ahorro_origen` (`cuenta_origen_id`),
    KEY `idx_transferencias_ahorro_destino` (`cuenta_destino_id`),
    KEY `idx_transferencias_ahorro_meta` (`meta_id`),
    CONSTRAINT `fk_transferencias_ahorro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transferencias_ahorro_origen` FOREIGN KEY (`cuenta_origen_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_transferencias_ahorro_destino` FOREIGN KEY (`cuenta_destino_id`) REFERENCES `cuentas_ahorro` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_transferencias_ahorro_meta` FOREIGN KEY (`meta_id`) REFERENCES `metas_ahorro` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Inversión y donación
-- ============================================================================

-- Tipos de inversión propios del usuario; los movimientos los referencian
-- con la clave `u<id>` en la columna `instrumento`.
CREATE TABLE `instrumentos_inversion` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(60) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_instrumentos_inversion_user_nombre` (`user_id`, `nombre`),
    CONSTRAINT `fk_instrumentos_inversion_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aportaciones_inversion` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `instrumento` VARCHAR(40) NOT NULL,
    `es_extra` TINYINT(1) NOT NULL DEFAULT 0,
    `fecha` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_aportaciones_inversion_user_fecha` (`user_id`, `fecha`),
    KEY `idx_aportaciones_inversion_instrumento` (`instrumento`),
    CONSTRAINT `fk_aportaciones_inversion_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `retiros_inversion` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `instrumento` VARCHAR(40) NOT NULL,
    `motivo_catalogo` ENUM('liquidez', 'meta_cumplida', 'rebalanceo', 'otro') NOT NULL,
    `motivo_texto` VARCHAR(255) NULL,
    `fecha` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_retiros_inversion_user_fecha` (`user_id`, `fecha`),
    KEY `idx_retiros_inversion_instrumento` (`instrumento`),
    CONSTRAINT `fk_retiros_inversion_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `donaciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `causa` VARCHAR(120) NULL,
    `es_extra` TINYINT(1) NOT NULL DEFAULT 0,
    `fecha` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_donaciones_user_fecha` (`user_id`, `fecha`),
    CONSTRAINT `fk_donaciones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pausas_inversion` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `periodo_inicio` DATETIME NOT NULL,
    `periodo_fin` DATETIME NOT NULL,
    `monto_centavos` BIGINT NULL,
    `motivo_catalogo` ENUM('apuro', 'desempleo', 'otro') NOT NULL,
    `motivo_texto` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pausas_inversion_user_periodo` (`user_id`, `periodo_inicio`, `periodo_fin`),
    CONSTRAINT `fk_pausas_inversion_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Fondos de inversión (preparación Nivel 2)
-- ============================================================================

CREATE TABLE `inversiones_fondos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `instrumento` VARCHAR(40) NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `valor_actual_centavos` BIGINT NULL,
    `valor_actual_principal_centavos` BIGINT NULL,
    `fecha_valor_actual` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inversiones_fondos_user` (`user_id`),
    CONSTRAINT `fk_inversiones_fondos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inversiones_posiciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `tipo` ENUM('accion', 'etf', 'cripto', 'cetes', 'fondo', 'otro') NOT NULL DEFAULT 'otro',
    `mercado` ENUM('BMV', 'EUA', 'CRIPTO', 'CETES', 'OTRO') NOT NULL DEFAULT 'OTRO',
    `ticker` VARCHAR(32) NULL,
    `simbolo_proveedor` VARCHAR(64) NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `cantidad` DECIMAL(24,8) NOT NULL DEFAULT 1,
    `costo_total_centavos` BIGINT NOT NULL DEFAULT 0,
    `costo_total_principal_centavos` BIGINT NULL,
    `modo_valor` ENUM('automatico', 'manual') NOT NULL DEFAULT 'manual',
    `valor_manual_centavos` BIGINT NULL,
    `valor_manual_principal_centavos` BIGINT NULL,
    `fecha_valor_manual` DATETIME NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inversiones_posiciones_user_activo` (`user_id`, `activo`),
    KEY `idx_inversiones_posiciones_simbolo` (`simbolo_proveedor`),
    CONSTRAINT `fk_inversiones_posiciones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inversiones_precios` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `simbolo_proveedor` VARCHAR(64) NOT NULL,
    `mercado` ENUM('BMV', 'EUA', 'CRIPTO', 'OTRO') NOT NULL DEFAULT 'OTRO',
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `precio` DECIMAL(24,8) NOT NULL,
    `fecha` DATE NOT NULL,
    `fuente` VARCHAR(40) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inversiones_precios_simbolo_fecha` (`simbolo_proveedor`, `fecha`),
    KEY `idx_inversiones_precios_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Notificaciones
-- ============================================================================

CREATE TABLE `notificaciones_config` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `tipo` ENUM('ingreso_dia_menos_1', 'ingreso_dia_0', 'ingreso_rescate', 'gasto_recurrente', 'recordatorio_deuda', 'alerta_categoria', 'resumen_mensual', 'soporte_respuesta') NOT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `hora` TIME NULL,
    `frecuencia` ENUM('una_vez', 'diaria', 'semanal', 'mensual') NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_notificaciones_config_user_tipo` (`user_id`, `tipo`),
    CONSTRAINT `fk_notificaciones_config_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notificaciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `tipo` VARCHAR(60) NOT NULL,
    `clave` VARCHAR(120) NULL,
    `titulo` VARCHAR(160) NOT NULL,
    `cuerpo` TEXT NOT NULL,
    `periodo_inicio` DATETIME NULL,
    `periodo_fin` DATETIME NULL,
    `canal` ENUM('inapp', 'push') NOT NULL DEFAULT 'inapp',
    `push_enviado_at` DATETIME NULL,
    `leida` TINYINT(1) NOT NULL DEFAULT 0,
    `referencia_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_notificaciones_user_clave` (`user_id`, `clave`),
    KEY `idx_notificaciones_user_leida_created` (`user_id`, `leida`, `created_at`),
    CONSTRAINT `fk_notificaciones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `resumenes_mensuales` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `periodo_inicio` DATETIME NOT NULL,
    `periodo_fin` DATETIME NOT NULL,
    `contenido_json` JSON NOT NULL,
    `reflexion` TEXT NULL,
    `generado_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `leido_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_resumenes_user_periodo` (`user_id`, `periodo_inicio`, `periodo_fin`),
    CONSTRAINT `fk_resumenes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `push_suscripciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `endpoint` VARCHAR(500) NOT NULL,
    `endpoint_hash` CHAR(64) NOT NULL,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_push_suscripciones_user_endpoint` (`user_id`, `endpoint_hash`),
    CONSTRAINT `fk_push_suscripciones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Gamificación
-- ============================================================================

CREATE TABLE `hitos_usuario` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `tipo` ENUM('primera_meta', 'primer_mes_completo', 'emergencia_50', 'emergencia_100', 'racha_30') NOT NULL,
    `fecha_logro` DATE NOT NULL,
    `celebrado_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_hitos_usuario_user_tipo` (`user_id`, `tipo`),
    CONSTRAINT `fk_hitos_usuario_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rachas` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `periodos_consecutivos` INT UNSIGNED NOT NULL DEFAULT 0,
    `mejor_racha` INT UNSIGNED NOT NULL DEFAULT 0,
    `estado` ENUM('activa', 'pausa', 'rota') NOT NULL DEFAULT 'activa',
    `ultimo_periodo_inicio` DATETIME NULL,
    `ultimo_periodo_fin` DATETIME NULL,
    `congelada_desde` DATETIME NULL,
    `ultima_actualizacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rachas_user` (`user_id`),
    CONSTRAINT `fk_rachas_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `niveles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nivel_actual` TINYINT NOT NULL DEFAULT 1,
    `porcentaje_cumplimiento` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `detalle_json` JSON NULL,
    `fecha_calculo` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_niveles_user` (`user_id`),
    CONSTRAINT `fk_niveles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `retos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `periodo_inicio` DATETIME NOT NULL,
    `periodo_fin` DATETIME NOT NULL,
    `tipo` ENUM('cumple_ahorro', 'cumple_inversion', 'cumple_donacion', 'registra_todo', 'racha') NOT NULL,
    `objetivo` DECIMAL(5,2) NULL,
    `progreso` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `estado` ENUM('activo', 'completado', 'expirado', 'descartado') NOT NULL DEFAULT 'activo',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_retos_user_estado` (`user_id`, `estado`),
    KEY `idx_retos_user_periodo` (`user_id`, `periodo_inicio`, `periodo_fin`),
    CONSTRAINT `fk_retos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Deudas y patrimonio
-- ============================================================================

CREATE TABLE `deudas` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `monto_total_centavos` BIGINT NOT NULL,
    `saldo_actual_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_total_principal_centavos` BIGINT NULL,
    `saldo_actual_principal_centavos` BIGINT NULL,
    `pago_minimo_principal_centavos` BIGINT NULL,
    `tasa_interes` DECIMAL(5,2) NULL,
    `pago_minimo_centavos` BIGINT NULL,
    `fecha_limite` DATE NULL,
    `activa` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_deudas_user_activa` (`user_id`, `activa`),
    CONSTRAINT `fk_deudas_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `gastos_recurrentes`
    ADD CONSTRAINT `fk_gastos_recurrentes_deuda`
        FOREIGN KEY (`deuda_id`) REFERENCES `deudas` (`id`) ON DELETE SET NULL;

CREATE TABLE `pagos_deuda` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `deuda_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `monto_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_principal_centavos` BIGINT NULL,
    `fecha` DATETIME NOT NULL,
    `gasto_id` BIGINT UNSIGNED NULL,
    `nota` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pagos_deuda_deuda_id` (`deuda_id`),
    KEY `idx_pagos_deuda_user_fecha` (`user_id`, `fecha`),
    KEY `idx_pagos_deuda_gasto_id` (`gasto_id`),
    CONSTRAINT `fk_pagos_deuda_deuda` FOREIGN KEY (`deuda_id`) REFERENCES `deudas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pagos_deuda_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pagos_deuda_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `patrimonio_snapshots` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `fecha` DATE NOT NULL,
    `activos_centavos` BIGINT NOT NULL,
    `pasivos_centavos` BIGINT NOT NULL,
    `neto_centavos` BIGINT NOT NULL,
    `neto_aportado_centavos` BIGINT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_patrimonio_snapshots_user_fecha` (`user_id`, `fecha`),
    CONSTRAINT `fk_patrimonio_snapshots_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Lista de deseos
-- ============================================================================

CREATE TABLE `lista_deseos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `monto_estimado_centavos` BIGINT NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'MXN',
    `monto_estimado_principal_centavos` BIGINT NULL,
    `prioridad` TINYINT NOT NULL,
    `fecha_objetivo` DATE NULL,
    `fecha_enfriamiento` DATE NULL,
    `estado` ENUM('activo', 'comprado', 'descartado') NOT NULL DEFAULT 'activo',
    `nota` VARCHAR(255) NULL,
    `gasto_id` BIGINT UNSIGNED NULL,
    `comprado_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lista_deseos_user_estado` (`user_id`, `estado`),
    KEY `idx_lista_deseos_user_prioridad` (`user_id`, `prioridad`),
    KEY `idx_lista_deseos_gasto_id` (`gasto_id`),
    CONSTRAINT `fk_lista_deseos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lista_deseos_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Soporte
-- ============================================================================

CREATE TABLE `soporte_conversaciones` (
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

CREATE TABLE `soporte_mensajes` (
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

-- ============================================================================
-- Educación financiera
-- ============================================================================

CREATE TABLE `lecciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo` VARCHAR(40) NOT NULL,
    `seccion` VARCHAR(40) NOT NULL,
    `titulo_es` VARCHAR(160) NOT NULL,
    `titulo_en` VARCHAR(160) NULL,
    `contenido_es` TEXT NOT NULL,
    `contenido_en` TEXT NULL,
    `publicada` TINYINT(1) NOT NULL DEFAULT 1,
    `orden` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lecciones_codigo` (`codigo`),
    KEY `idx_lecciones_seccion_publicada` (`seccion`, `publicada`),
    KEY `idx_lecciones_orden` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lecciones_vistas` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `leccion_id` BIGINT UNSIGNED NOT NULL,
    `vista_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `leccion_id`),
    KEY `idx_lecciones_vistas_leccion_id` (`leccion_id`),
    CONSTRAINT `fk_lecciones_vistas_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lecciones_vistas_leccion` FOREIGN KEY (`leccion_id`) REFERENCES `lecciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Score financiero y estados de cuenta
-- ============================================================================

CREATE TABLE `score_snapshots` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `fecha` DATE NOT NULL,
    `score` TINYINT NOT NULL,
    `desglose_json` JSON NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_score_snapshots_user_fecha` (`user_id`, `fecha`),
    CONSTRAINT `fk_score_snapshots_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `estados_cuenta` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nombre_original` VARCHAR(255) NOT NULL,
    `ruta_archivo` VARCHAR(500) NOT NULL,
    `tamano_bytes` BIGINT NOT NULL,
    `mime` VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    `checksum` CHAR(64) NULL,
    `tipo` ENUM('banco', 'inversion') NOT NULL DEFAULT 'banco',
    `fecha_subida` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_estados_cuenta_user_fecha` (`user_id`, `fecha_subida`),
    CONSTRAINT `fk_estados_cuenta_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Fase 8: privacidad, legal, analytics y seguridad
-- ============================================================================

CREATE TABLE `estados_cuenta_consentimientos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `accepted_at` DATETIME NOT NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_estados_cuenta_consent_user_version` (`user_id`, `version`),
    CONSTRAINT `fk_estados_cuenta_consent_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `analytics_eventos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `evento` VARCHAR(60) NOT NULL,
    `contexto_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_analytics_evento_created` (`evento`, `created_at`),
    KEY `idx_analytics_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_analytics_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `legal_documentos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tipo` ENUM('privacidad', 'terminos', 'aviso') NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `titulo_es` VARCHAR(160) NOT NULL,
    `titulo_en` VARCHAR(160) NOT NULL,
    `contenido_es` MEDIUMTEXT NOT NULL,
    `contenido_en` MEDIUMTEXT NOT NULL,
    `vigente` TINYINT(1) NOT NULL DEFAULT 0,
    `published_at` DATETIME NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_legal_documentos_tipo_version` (`tipo`, `version`),
    KEY `idx_legal_documentos_vigente` (`tipo`, `vigente`),
    CONSTRAINT `fk_legal_documentos_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `legal_aceptaciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `tipo` ENUM('privacidad', 'terminos', 'aviso') NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `accepted_at` DATETIME NOT NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_legal_aceptaciones_user_tipo_version` (`user_id`, `tipo`, `version`),
    KEY `idx_legal_aceptaciones_tipo_version` (`tipo`, `version`),
    CONSTRAINT `fk_legal_aceptaciones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email_hash` CHAR(64) NOT NULL,
    `ip` VARCHAR(45) NULL,
    `exitoso` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_attempts_email_created` (`email_hash`, `created_at`),
    KEY `idx_login_attempts_ip_created` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `export_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `requested_by` BIGINT UNSIGNED NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_export_tokens_hash` (`token_hash`),
    KEY `idx_export_tokens_user` (`user_id`),
    CONSTRAINT `fk_export_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_export_tokens_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
