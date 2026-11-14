-- Tasas iniciales para que la aplicación multi-moneda funcione al primer arranque.
-- Solo se ejecuta cuando la base se inicializa desde cero.
USE `cauce`;

INSERT INTO `tasas_cambio` (`moneda_origen`, `moneda_destino`, `tasa`, `fecha`)
VALUES
    ('USD', 'MXN', 17.50000000, CURDATE()),
    ('MXN', 'USD', 0.05714286, CURDATE()),
    ('EUR', 'MXN', 18.20000000, CURDATE()),
    ('MXN', 'EUR', 0.05494505, CURDATE())
ON DUPLICATE KEY UPDATE
    `tasa` = VALUES(`tasa`),
    `updated_at` = NOW();
