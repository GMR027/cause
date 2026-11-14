-- ============================================================================
-- Cauce · datos semilla
-- Fase 1A: catálogo de métodos de presupuestación y sus categorías.
--
-- Este archivo es idempotente: puede ejecutarse varias veces sin duplicar
-- registros. No crea usuarios ni subcategorías: las subcategorías semilla se
-- generan por usuario durante el registro (fase posterior).
--
-- Uso:
--   mysql -u root -p < sql/seed.sql
-- ============================================================================

USE `cauce`;

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- Métodos
-- ----------------------------------------------------------------------------

INSERT INTO `metodos`
    (`codigo`, `nombre`, `descripcion_corta`, `contexto_cultural`, `icono`, `es_cultural`, `orden`)
VALUES
    (
        '50_30_20',
        'Regla 50/30/20',
        'Distribuye tu ingreso en necesidades, deseos y ahorro.',
        NULL,
        'bi-pie-chart',
        0,
        1
    ),
    (
        'seis_sobres',
        'Seis sobres',
        'Divide tu ingreso en sobres con propósitos específicos.',
        NULL,
        'bi-envelope-paper',
        0,
        2
    ),
    (
        'cinco_frascos',
        'Cinco frascos',
        'Reparte el ingreso en cinco frascos de consumo, inversión, ahorro y donación.',
        'Inspirado en la tradición judía del diezmo; adaptación moderna, no precepto religioso',
        'bi-archive',
        1,
        3
    ),
    (
        '70_20_10',
        'Regla 70/20/10',
        'Destina 70% a gastos, 20% a ahorro y 10% a deudas o inversión.',
        NULL,
        'bi-sliders',
        0,
        4
    ),
    (
        'kakebo',
        'Kakebo',
        'Método japonés de registro consciente del gasto por categorías.',
        NULL,
        'bi-journal-text',
        0,
        5
    )
    AS `nuevo`
ON DUPLICATE KEY UPDATE
    `nombre` = `nuevo`.`nombre`,
    `descripcion_corta` = `nuevo`.`descripcion_corta`,
    `contexto_cultural` = `nuevo`.`contexto_cultural`,
    `icono` = `nuevo`.`icono`,
    `es_cultural` = `nuevo`.`es_cultural`,
    `orden` = `nuevo`.`orden`;

-- ----------------------------------------------------------------------------
-- Categorías por método
-- ----------------------------------------------------------------------------

SET @metodo_50_30_20 = (SELECT `id` FROM `metodos` WHERE `codigo` = '50_30_20');
SET @metodo_seis_sobres = (SELECT `id` FROM `metodos` WHERE `codigo` = 'seis_sobres');
SET @metodo_cinco_frascos = (SELECT `id` FROM `metodos` WHERE `codigo` = 'cinco_frascos');
SET @metodo_70_20_10 = (SELECT `id` FROM `metodos` WHERE `codigo` = '70_20_10');
SET @metodo_kakebo = (SELECT `id` FROM `metodos` WHERE `codigo` = 'kakebo');

-- 50/30/20
INSERT INTO `metodo_categorias` (`metodo_id`, `nombre`, `porcentaje`, `tipo`, `orden`)
VALUES
    (@metodo_50_30_20, 'Necesidades', 50, 'controlada', 1),
    (@metodo_50_30_20, 'Deseos', 30, 'controlada', 2),
    (@metodo_50_30_20, 'Ahorro', 20, 'ahorro', 3)
    AS `nuevo`
ON DUPLICATE KEY UPDATE
    `porcentaje` = `nuevo`.`porcentaje`,
    `tipo` = `nuevo`.`tipo`,
    `orden` = `nuevo`.`orden`;

-- Seis sobres
INSERT INTO `metodo_categorias` (`metodo_id`, `nombre`, `porcentaje`, `tipo`, `orden`)
VALUES
    (@metodo_seis_sobres, 'Necesidades', 55, 'controlada', 1),
    (@metodo_seis_sobres, 'Ahorro LP', 10, 'ahorro', 2),
    (@metodo_seis_sobres, 'Educación', 10, 'ahorro', 3),
    (@metodo_seis_sobres, 'Inversión', 10, 'inversion', 4),
    (@metodo_seis_sobres, 'Diversión', 10, 'controlada', 5),
    (@metodo_seis_sobres, 'Donación', 5, 'donacion', 6)
    AS `nuevo`
ON DUPLICATE KEY UPDATE
    `porcentaje` = `nuevo`.`porcentaje`,
    `tipo` = `nuevo`.`tipo`,
    `orden` = `nuevo`.`orden`;

-- Cinco frascos
INSERT INTO `metodo_categorias` (`metodo_id`, `nombre`, `porcentaje`, `tipo`, `orden`)
VALUES
    (@metodo_cinco_frascos, 'Consumo', 50, 'controlada', 1),
    (@metodo_cinco_frascos, 'Inversión', 20, 'inversion', 2),
    (@metodo_cinco_frascos, 'Ahorro', 10, 'ahorro', 3),
    (@metodo_cinco_frascos, 'Donación', 10, 'donacion', 4),
    (@metodo_cinco_frascos, 'Compartir', 10, 'donacion', 5)
    AS `nuevo`
ON DUPLICATE KEY UPDATE
    `porcentaje` = `nuevo`.`porcentaje`,
    `tipo` = `nuevo`.`tipo`,
    `orden` = `nuevo`.`orden`;

-- 70/20/10
INSERT INTO `metodo_categorias` (`metodo_id`, `nombre`, `porcentaje`, `tipo`, `orden`)
VALUES
    (@metodo_70_20_10, 'Gastos', 70, 'controlada', 1),
    (@metodo_70_20_10, 'Ahorro', 20, 'ahorro', 2),
    (@metodo_70_20_10, 'Deudas/Inversión', 10, 'inversion', 3)
    AS `nuevo`
ON DUPLICATE KEY UPDATE
    `porcentaje` = `nuevo`.`porcentaje`,
    `tipo` = `nuevo`.`tipo`,
    `orden` = `nuevo`.`orden`;

-- Donación opcional: métodos que no la incluyen pueden activarla.
INSERT INTO `metodo_categorias` (`metodo_id`, `nombre`, `porcentaje`, `tipo`, `orden`)
VALUES
    (@metodo_50_30_20, 'Donación', NULL, 'donacion', 4),
    (@metodo_70_20_10, 'Donación', NULL, 'donacion', 4)
    AS `nuevo`
ON DUPLICATE KEY UPDATE
    `porcentaje` = NULL,
    `tipo` = 'donacion',
    `orden` = `nuevo`.`orden`;

-- Kakebo (sin porcentaje fijo)
INSERT INTO `metodo_categorias` (`metodo_id`, `nombre`, `porcentaje`, `tipo`, `orden`)
VALUES
    (@metodo_kakebo, 'Necesidades', NULL, 'controlada', 1),
    (@metodo_kakebo, 'Ocio', NULL, 'controlada', 2),
    (@metodo_kakebo, 'Cultura', NULL, 'controlada', 3),
    (@metodo_kakebo, 'Extras', NULL, 'controlada', 4)
    AS `nuevo`
ON DUPLICATE KEY UPDATE
    `porcentaje` = `nuevo`.`porcentaje`,
    `tipo` = `nuevo`.`tipo`,
    `orden` = `nuevo`.`orden`;

-- ----------------------------------------------------------------------------
-- Documentos legales iniciales (borrador revisable por el responsable legal)
-- ----------------------------------------------------------------------------

INSERT INTO `legal_documentos`
    (`tipo`, `version`, `titulo_es`, `titulo_en`, `contenido_es`, `contenido_en`,
     `vigente`, `published_at`, `created_at`, `updated_at`)
VALUES
(
    'privacidad',
    '1.0',
    'Política de privacidad',
    'Privacy policy',
    'Cauce es una aplicación de finanzas personales. Esta política describe qué datos tratamos y cómo puedes controlarlos.\n\n1. Datos que tratamos\n- Datos de cuenta: alias, correo, idioma, moneda, zona horaria y preferencias.\n- Datos financieros que tú registras: ingresos, gastos, movimientos, aportaciones, retiros, donaciones, metas y lista de deseos.\n- Archivos que decides subir: fotos de gastos y estados de cuenta en PDF.\n- Datos de uso: eventos anónimos de navegación y funciones utilizadas, sin montos ni notas.\n\n2. Para qué los usamos\nPara prestarte el servicio, calcular tu período, mostrarte tus paneles y mejorar la aplicación. No vendemos ni compartimos tus datos con terceros.\n\n3. Dónde se guardan\nEn servidores del proveedor de infraestructura, con TLS en tránsito y cifrado en reposo del proveedor. Los archivos se guardan fuera de la carpeta pública y solo se sirven por endpoints autenticados.\n\n4. Cuánto tiempo los conservamos\nMientras tu cuenta esté activa. Si borras tu cuenta, los datos se eliminan de inmediato en la aplicación; las copias de seguridad del proveedor pueden conservarlos hasta 30 días. Los eventos de analytics se conservan hasta 12 meses.\n\n5. Tus derechos\nPuedes exportar tus datos en CSV, PDF y ZIP, corregir tu información, desactivar el analytics y eliminar tu cuenta desde Ajustes. También puedes solicitar apoyo al administrador para una solicitud ARCO.\n\n6. Estados de cuenta\nSubirlos es tu decisión. La información es tu responsabilidad. La app solo los guarda para tu consulta y no extrae información de ellos. Puedes eliminarlos cuando quieras.\n\n7. Cambios\nSi publicamos una nueva versión, te pediremos aceptarla antes de continuar.\n\n8. Contacto\nAbre una conversación desde Ajustes → Soporte dentro de Cauce para dudas sobre privacidad.',
    'Cauce is a personal finance application. This policy describes what data we process and how you can control it.\n\n1. Data we process\n- Account data: alias, email, language, currency, time zone and preferences.\n- Financial data you record: income, expenses, movements, contributions, withdrawals, donations, goals and wish list.\n- Files you choose to upload: expense photos and PDF statements.\n- Usage data: anonymous navigation and feature events, without amounts or notes.\n\n2. Why we use it\nTo provide the service, calculate your period, show your panels and improve the app. We do not sell or share your data with third parties.\n\n3. Where it is stored\nOn infrastructure provider servers, with TLS in transit and provider at-rest encryption. Files are stored outside the public folder and served only through authenticated endpoints.\n\n4. How long we keep it\nWhile your account is active. If you delete your account, data is removed immediately in the app; provider backups may retain it for up to 30 days. Analytics events are kept for up to 12 months.\n\n5. Your rights\nYou can export your data as CSV, PDF and ZIP, correct your information, opt out of analytics and delete your account from Settings. You can also ask the administrator for support with an ARCO request.\n\n6. Statements\nUploading them is your decision. The information is your responsibility. The app only stores them for your consultation and does not extract information from them. You can delete them whenever you want.\n\n7. Changes\nIf we publish a new version, we will ask you to accept it before continuing.\n\n8. Contact\nOpen a conversation from Settings → Support inside Cauce for privacy questions.',
    1,
    UTC_TIMESTAMP(),
    NOW(),
    NOW()
),
(
    'terminos',
    '1.0',
    'Términos de uso',
    'Terms of use',
    '1. Servicio\nCauce te ayuda a repartir tu ingreso según un método de presupuestación. Es una herramienta de organización, no asesoría financiera, fiscal o legal.\n\n2. Tu cuenta\nEres responsable de mantener tu contraseña y tu PIN seguros. No compartas tu cuenta. Puedes cerrar sesión en todos tus dispositivos desde Seguridad.\n\n3. Tu información\nLos datos que registras son tuyos. Debes asegurarte de que los archivos que subes no contengan información que no quieras almacenar.\n\n4. Uso aceptable\nNo uses la app para actividades ilícitas, para intentar acceder a cuentas ajenas o para interferir con el servicio.\n\n5. Disponibilidad\nBuscamos mantener el servicio disponible, pero puede haber interrupciones por mantenimiento o causas externas.\n\n6. Límites\nNo garantizamos resultados financieros. Las decisiones que tomes son tuyas.\n\n7. Terminación\nPuedes eliminar tu cuenta cuando quieras. Podemos suspender cuentas que incumplan estos términos.\n\n8. Cambios\nSi publicamos una nueva versión, te pediremos aceptarla antes de continuar.\n\n9. Contacto\nAbre una conversación desde Ajustes → Soporte dentro de Cauce.',
    '1. Service\nCauce helps you distribute your income according to a budgeting method. It is an organization tool, not financial, tax or legal advice.\n\n2. Your account\nYou are responsible for keeping your password and PIN secure. Do not share your account. You can log out on all devices from Security.\n\n3. Your information\nThe data you record is yours. You must ensure that the files you upload do not contain information you do not want to store.\n\n4. Acceptable use\nDo not use the app for illegal activities, to attempt access to other accounts or to interfere with the service.\n\n5. Availability\nWe aim to keep the service available, but there may be interruptions due to maintenance or external causes.\n\n6. Limits\nWe do not guarantee financial results. The decisions you make are yours.\n\n7. Termination\nYou can delete your account whenever you want. We may suspend accounts that breach these terms.\n\n8. Changes\nIf we publish a new version, we will ask you to accept it before continuing.\n\n9. Contact\nOpen a conversation from Settings → Support inside Cauce.',
    1,
    UTC_TIMESTAMP(),
    NOW(),
    NOW()
),
(
    'aviso',
    '1.0',
    'Aviso legal',
    'Legal notice',
    'Cauce es una aplicación de organización financiera personal. La información que muestra se basa en los datos que tú registras y en el método que eliges. No constituye asesoría financiera, fiscal, legal ni una recomendación de inversión. Verifica siempre la información con un profesional cuando lo necesites.\n\nLos estados de cuenta que subes son tu responsabilidad. La app no los analiza ni extrae información de ellos. Puedes eliminarlos en cualquier momento.',
    'Cauce is a personal financial organization application. The information it shows is based on the data you record and the method you choose. It does not constitute financial, tax, legal advice or an investment recommendation. Always verify information with a professional when needed.\n\nThe statements you upload are your responsibility. The app does not analyze or extract information from them. You can delete them at any time.',
    1,
    UTC_TIMESTAMP(),
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    `titulo_es` = VALUES(`titulo_es`),
    `titulo_en` = VALUES(`titulo_en`),
    `contenido_es` = VALUES(`contenido_es`),
    `contenido_en` = VALUES(`contenido_en`),
    `updated_at` = NOW();

-- ----------------------------------------------------------------------------
-- Lecciones contextuales iniciales (es/en)
-- ----------------------------------------------------------------------------

INSERT IGNORE INTO `lecciones`
    (`codigo`, `seccion`, `titulo_es`, `titulo_en`, `contenido_es`, `contenido_en`,
     `publicada`, `orden`, `created_at`, `updated_at`)
VALUES
(
    'ahorro_basico',
    'ahorro',
    '¿Qué es el ahorro?',
    'What is saving?',
    'El ahorro es la parte del ingreso que reservas en lugar de gastarla de inmediato. Puede vivir en una cuenta, en efectivo o dentro de una meta con nombre y fecha. Su función es darte margen: cubrir un gasto que no estaba planeado, aprovechar una oportunidad o financiar un objetivo. En Cauce, el ahorro se separa del gasto diario y puede tener metas específicas. Revisar cuánto entra, cuánto sale y cuánto reservas te ayuda a ver el tamaño de tu margen. El ahorro no tiene una sola forma correcta: depende de tus ingresos, tus compromisos y tus prioridades. Saber dónde está y para qué lo quieres forma parte de su organización.',
    'Saving is the part of your income you set aside instead of spending right away. It can sit in an account, in cash or inside a named goal with a date. Its purpose is to give you room: covering an unexpected expense, taking an opportunity or funding an objective. In Cauce, savings are separate from daily spending and can have specific goals. Reviewing what comes in, what goes out and what you set aside helps you see the size of your margin. There is no single correct way to save: it depends on your income, commitments and priorities. Knowing where it is and what you want it for is part of organizing it.',
    1,
    1,
    NOW(),
    NOW()
),
(
    'inversion_basico',
    'inversion',
    '¿Qué es invertir?',
    'What is investing?',
    'Invertir es destinar recursos a un activo con la expectativa de obtener un rendimiento en el futuro. Ese rendimiento puede ser positivo o negativo: el valor de un activo cambia y no está garantizado. Invertir suele implicar un horizonte de tiempo, un nivel de riesgo y costos. En Cauce, la inversión se registra aparte del ahorro y del gasto diario, con instrumentos y aportaciones. Entender qué se compra, cómo se mide su valor, qué comisiones existen y en qué plazo se necesita el dinero son elementos habituales al invertir. La información y la diversificación son temas frecuentes; ninguna inversión elimina el riesgo. Este contenido es descriptivo y no constituye una recomendación.',
    'Investing means putting resources into an asset with the expectation of earning a return in the future. That return can be positive or negative: an asset value changes and is not guaranteed. Investing usually involves a time horizon, a level of risk and costs. In Cauce, investing is recorded separately from savings and daily spending, with instruments and contributions. Understanding what is being bought, how its value is measured, what fees exist and when the money is needed are common elements of investing. Information and diversification are common topics; no investment removes risk. This content is descriptive and is not a recommendation.',
    1,
    2,
    NOW(),
    NOW()
),
(
    'donacion_basico',
    'donacion',
    '¿Qué es donar?',
    'What is donating?',
    'Donar es destinar recursos a una causa, una persona o una organización sin esperar una contraprestación económica directa. Puede ser dinero, tiempo o bienes. En Cauce, la donación se registra como una categoría propia y se descuenta del disponible del período, igual que otros compromisos. Definir un monto, una causa y una frecuencia ayuda a que la donación sea parte del plan y no una decisión impulsiva. También permite ver cuánto has destinado y compararlo con lo que querías. Donar no es obligatorio ni tiene una sola forma correcta; es una decisión personal que puede cambiar con el tiempo.',
    'Donating means giving resources to a cause, a person or an organization without expecting a direct economic return. It can be money, time or goods. In Cauce, donations are recorded as their own category and reduce the amount available for the period, like other commitments. Defining an amount, a cause and a frequency helps make donating part of the plan instead of an impulsive decision. It also shows how much you have given and how it compares with what you intended. Donating is not mandatory and has no single correct form; it is a personal decision that can change over time.',
    1,
    3,
    NOW(),
    NOW()
),
(
    'deudas_basico',
    'deudas',
    '¿Qué es una deuda?',
    'What is debt?',
    'Una deuda es dinero que recibes ahora y te comprometes a devolver después, normalmente con un costo adicional llamado interés. Puede tener una fecha límite, un pago mínimo y una tasa. En Cauce puedes registrar el monto original, el saldo actual, la tasa y el pago mínimo para ver cómo evoluciona. Pagar una deuda reduce el saldo; el interés puede hacer que el total pagado sea mayor que el monto original. Comparar saldo, tasa y plazo ayuda a entender el peso de cada deuda. No todas las deudas son iguales: una compra a plazos y un crédito de largo plazo pueden tener costos y riesgos distintos. Este contenido describe conceptos y no recomienda endeudarse ni dejar de hacerlo.',
    'Debt is money you receive now and commit to repay later, usually with an additional cost called interest. It can have a due date, a minimum payment and a rate. In Cauce you can record the original amount, current balance, rate and minimum payment to see how it changes. Paying a debt reduces the balance; interest can make the total paid larger than the original amount. Comparing balance, rate and term helps understand the weight of each debt. Not all debts are the same: an installment purchase and a long-term loan can have different costs and risks. This content describes concepts and does not recommend taking on debt or avoiding it.',
    1,
    4,
    NOW(),
    NOW()
),
(
    'patrimonio_basico',
    'patrimonio',
    '¿Qué es el patrimonio neto?',
    'What is net worth?',
    'El patrimonio neto es la diferencia entre lo que tienes y lo que debes. Para calcularlo se suman tus activos —ahorro, inversiones y efectivo— y se restan tus pasivos, como los saldos de deudas activas. Un patrimonio positivo significa que tus activos superan tus deudas; uno negativo, lo contrario. El patrimonio puede cambiar por aportaciones, retiros, rendimientos, intereses o nuevos compromisos. En Cauce se guarda un registro mensual para que puedas ver su evolución. No es una medida de tu valor como persona ni un diagnóstico: es una fotografía de tu situación en una fecha. Revisarlo con calma ayuda a entender qué mueve tu balance.',
    'Net worth is the difference between what you own and what you owe. To calculate it, your assets are added —savings, investments and cash— and your liabilities, such as active debt balances, are subtracted. Positive net worth means your assets exceed your debts; negative means the opposite. Net worth can change through contributions, withdrawals, returns, interest or new commitments. In Cauce a monthly record is kept so you can see how it evolves. It is not a measure of your worth as a person or a diagnosis: it is a snapshot of your situation on a date. Reviewing it calmly helps understand what moves your balance.',
    1,
    5,
    NOW(),
    NOW()
),
(
    'fondo_emergencia_basico',
    'fondo_emergencia',
    '¿Qué es el fondo de emergencia?',
    'What is an emergency fund?',
    'El fondo de emergencia es un monto reservado para cubrir gastos imprevistos, como una reparación, un gasto médico o una pérdida temporal de ingreso. Se mantiene separado del dinero que usas para el día a día y suele estar en un lugar de fácil acceso. En Cauce el fondo tiene una meta propia dentro del ahorro. Una referencia común es cubrir varios meses de gasto, pero el tamaño adecuado depende de tus circunstancias, tus compromisos y tu tolerancia a la incertidumbre. Usarlo para una emergencia no es un fracaso: es su función. Reconstruirlo poco a poco es una opción.',
    'An emergency fund is an amount reserved for unexpected expenses, such as a repair, a medical expense or a temporary loss of income. It is kept separate from the money you use day to day and is usually easy to access. In Cauce the fund has its own goal within savings. A common reference is covering several months of expenses, but the right size depends on your circumstances, commitments and tolerance for uncertainty. Using it for an emergency is not a failure: it is its purpose. Rebuilding it little by little is an option.',
    1,
    6,
    NOW(),
    NOW()
);
