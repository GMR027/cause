# Cauce

Aplicación web de finanzas personales que ayuda a repartir el ingreso según un método de presupuestación.

Estado actual:

- **Fase 0:** proyecto base, Gulp, Bootstrap 5, PDO, `.env`, logging y home de verificación.
- **Fase 1A:** esquema inicial y seed de los 5 métodos de presupuestación.
- **Fase 1B:** autenticación, recuperación de acceso, roles/admin básico, i18n y servicio de reparto.
- **Fase 2:** onboarding retomable, layout con header/sidebar/drawer/dock/FAB, temas, QR y placeholder de Android.
- **Fase 3:** cálculo de período, registro de ingresos con acreditación/justificación/No controlado y bolsa de No controlado.
- **Fase 4:** gastos manuales, plantillas, recurrentes automáticos, conciliación de pendientes, tags, fotos y detección de inconsistencias.
- **Fase 5:** paneles de ahorro, inversión y donación; metas, fondo de emergencia, pausas de inversión y rebalanceo.
- **Fase 6:** movimientos entre categorías, notificaciones in-app y Web Push, modo vacaciones, resumen mensual inmutable, búsqueda global y calendario con agenda.
- **Fase 7:** racha, badges, niveles por cumplimiento, retos personalizados, comparaciones, switch de gamificación y lista de deseos.
- **Fase 8:** cierre del MVP: PIN de desbloqueo, analytics propio opt-out, exportación y borrado de cuenta, estados de cuenta solo en escritorio, panel de admin con metadatos y estadísticas agregadas, y documentos legales versionados.
- **Fase 9:** deudas con estrategias y pagos, patrimonio neto con snapshots, metas con plazo y aporte sugerido, proyección de fin de período, análisis de suscripciones y búsqueda ampliada.
- **Fase 10:** lecciones contextuales neutrales con administración, exportación de recurrentes a `.ics` y score privado de salud financiera con snapshots y evolución.
- **Fase 11:** multi-moneda completa: monedas por usuario, tasas globales, conversión por fecha, reportes consolidados y recálculo al cambiar la moneda principal.

Todavía **no incluye** hogar, familia ni Android nativo. El valor de mercado de inversiones y las bolsas múltiples de ahorro ya están incluidos.

## Stack

- PHP 8.2+ (sin framework, PDO, sesiones nativas)
- MySQL 8.0+
- HTML5 + CSS3 + Bootstrap 5.3 + Bootstrap Icons
- JavaScript vanilla + `qrcode-generator`
- Gulp 4 + Dart Sass
- Composer (phpdotenv, Monolog, PHPUnit, minishlink/web-push, php-http/guzzle7-adapter)
- Contraseñas con ARGON2ID (fallback BCRYPT)

## Requisitos

- PHP 8.2 o superior con `pdo_mysql`, `mbstring`, `fileinfo` y `zip` (`intl` es opcional)
- Composer 2
- Node.js 18+ y npm
- MySQL 8.0+ en ejecución

## Instalación

```bash
# 1. Dependencias PHP y de frontend
composer install
npm install

# 2. Configuración local
cp .env.example .env
# Edita .env con tus credenciales de MySQL y tus claves de aplicación

# 3. Crear la base de datos y cargar semillas
mysql -u root -p < sql/schema.sql
mysql -u root -p < sql/seed.sql

# 4. Compilar assets (CSS, JS, iconos)
npx gulp build

# 5. Levantar la app
php -S localhost:8000 -t public public/router.php
```

Abre <http://localhost:8000>.

> `public/router.php` hace que las rutas amigables pasen por el front controller igual que `.htaccess` en Apache.

## Primer admin

El registro público crea usuarios con rol `user`. Para habilitar el primer administrador, registra una cuenta y promuévela desde MySQL:

```bash
mysql -u root -p cauce -e "UPDATE users SET rol='admin' WHERE email='tu@correo.com';"
```

No hace falta volver a iniciar sesión: el rol se lee de la base en cada petición. El panel estará en `/admin`.

## Onboarding

Flujo lineal de 9 pasos, retomable:

1. **Tutorial** (3 pantallas).
2. **Simulador**: monto editable (default **$10,000 MXN**, configurable con `ONBOARDING_DEFAULT_INCOME_CENTS`) y 4 métodos comparados en vivo.
3. **Elegir método**: 50/30/20, Seis sobres, Cinco frascos o 70/20/10. Kakebo queda fuera porque no tiene porcentajes fijos.
4. **Moneda** (MXN/USD/EUR).
5. **Tipo de ingreso** (fijo/variable/mixto).
6. **Configuración rápida o completa**. La rápida salta la recurrencia.
7. **Recurrencia** (diaria/semanal/mensual/anual).
8. **Primer ingreso** (placeholder de Fase 3).
9. **Recordatorios** (preferencias guardadas; Web Push se activa después desde Ajustes → Notificaciones).

Detalles:

- El progreso se guarda en `users.onboarding_estado` (JSON) después de cada paso.
- El simulador guarda monto y pestaña activa con `POST /api/onboarding/estado` (autosave con debounce), así se retoma a media simulación.
- Al terminar: `onboarding_completado = 1`, `moneda_activa`, `tipo_ingreso`, `recurrencia` y una fila en `usuario_metodos` con `porcentajes_json` y `vigente_desde = NOW()`.
- El middleware `OnboardingMiddleware` obliga a completar el onboarding antes de `/inicio`, `/movimientos` y `/reportes`. `/ajustes`, `/onboarding` y `/admin` quedan accesibles.
- El test de 3 preguntas (`MethodRecommender`) resalta un método sin ocultar los demás. Es determinista y tiene tests unitarios.

> Nota para Fase 3: MySQL reordena las claves de un objeto JSON. `IngresoService` reconstruye el orden con `metodo_categorias.orden` antes de llamar a `RepartoService`, así que el desempate del residuo es estable.

## Ingresos y período

### Cálculo del período

`app/src/Services/PeriodoService.php` calcula los extremos en la zona horaria del usuario y los devuelve en UTC:

- **Mensual**: desde `periodo_inicio_dia` a las 00:00 hasta el día anterior del siguiente ciclo a las 23:59:59. Si el mes no tiene ese día, se usa el último disponible (31 → 28/29/30).
- **Semanal**: lunes 00:00 a domingo 23:59:59.
- **Diaria**: el día completo.
- **Anual**: 1 de enero a 31 de diciembre.

Cambio de recurrencia:

- El período actual **termina con la regla anterior**.
- Se guardan `users.recurrencia_anterior` y `users.recurrencia_cambio_desde`; el cambio aplica desde el siguiente período.
- El primer período con la nueva regla se recorta al instante del cambio para no solaparse.

Ejemplo: `periodo_inicio_dia=15`, referencia `2026-10-20` → `2026-10-15 06:00:00` a `2026-11-15 05:59:59` UTC (hora de México).

### Flujo de ingreso

1. `/ingresos/nuevo`: monto (precargado con el último ingreso fijo o el monto del onboarding) y tipo fijo/variable/extra.
2. Al confirmar se crea el `ingreso` y **todas** las `asignaciones` como `pendiente_asignacion`.
3. `/ingresos/acreditar?id=N`: desglose por categoría con tres decisiones:
   - **Acreditado** → estado `acreditado`.
   - **Justificar** → inserta `justificaciones` (`aun_no_transfiero`, `lo_usare_luego`, `efectivo`, `otro` + texto).
   - **No controlado** → estado `no_controlado` + fila en `no_controlado` con `origen='asignacion'`.
4. Si se cierra la app antes de guardar, las asignaciones siguen pendientes y aparece el banner **“Tienes un ingreso pendiente de acreditar”** en Inicio.
5. Si el usuario cierra, no se persisten decisiones parciales: al reabrir se vuelven a elegir.

Reglas:

- Los porcentajes se ordenan por `metodo_categorias.orden` y se reparten con `RepartoService` en centavos.
- El residuo va a la primera categoría con mayor porcentaje.
- **Ingreso extra**: usa el reparto configurado por el usuario en Ajustes → Ingreso. Si no hay regla, va 100 % a la primera categoría de ahorro del método (fallback a inversión y luego a la primera).
- **No controlado** se acumula por usuario y período; en Inicio aparece una tarjeta ámbar con el total del período actual.

## Layout

- **Header**: ☰, título, campana (placeholder), ojo de modo discreto, avatar y dropdown “Registrar” en desktop.
- **Sidebar** ≥1200px: expandido por defecto; ☰ colapsa a iconos y el estado se recuerda en `localStorage`.
- **Drawer** móvil con el mismo contenido del sidebar.
- **Dock** móvil/tablet: Inicio, Movimientos, Reportes y Menú.
- **FAB** `+`: bottom sheet con Registrar ingreso, Registrar gasto, Mover entre categorías y Aportar/Retirar. Los dos últimos siguen como placeholders.
- La navegación se define una sola vez en `app/config/navigation.php`.
- **Modo discreto**: oculta importes con `••••`; el estado vive en `sessionStorage` y se reinicia al reabrir la pestaña. No se guarda en la base.
- **Inicio** saluda según la hora en la zona horaria del usuario, muestra métricas reales del período, método activo, No controlado, pendientes y avisos suaves.

## Temas

Ajustes → Temas:

- Modo **Claro / Oscuro / Automático** (`prefers-color-scheme`).
- **Color principal**: paleta curada de 10 colores seguros + selector personalizado.
- Validación **WCAG 2.1**: el texto blanco sobre el color debe alcanzar 4.5:1. Si no cumple, se bloquea el guardado y se sugiere el color seguro más cercano.
- **Enfoque**: Iconos / Botones / Mixto.
- **Fondos**: Ninguno / Radial / Textura / Radial + Textura, con texturas `dots`, `grid` y `waves`.
- **Previsualización obligatoria** con frasco, botón y FAB antes de aplicar.
- **Restablecer** vuelve a `#157f6b`, automático, mixto y sin fondo.

Persistencia:

- Servidor: `users.tema_modo`, `tema_color_principal`, `tema_enfoque`, `tema_efecto_fondo`, `tema_textura`.
- Cliente: `localStorage.cauce_theme` para aplicar antes del primer render y evitar flash.
- El tema del servidor gana al iniciar sesión y se reescribe en `localStorage`.
- Los derivados (hover, active, soft) se recalculan en PHP para el primer render y en JS al cambiar de tema.

## Compartir y Android

- `/ajustes/compartir` genera un QR grande de `APP_SHARE_URL` con `qrcode-generator` (sin backend) y botón para copiar el enlace.
- `/ajustes/android` muestra la versión actual (`APP_VERSION`), “App Android: próximamente”, “Buscar actualizaciones” y “Descargar APK” deshabilitado.
- `GET /api/app/version` devuelve `{"disponible": false, "version": "..."}`.

## Idiomas

- Diccionarios en `lang/es/` y `lang/en/`: `auth`, `common`, `nav`, `settings`, `themes`, `onboarding`, `app`, `emails`, `placeholder`, `finance`, `income`, `expenses`, `savings`, `investment`, `donation`, `moves`, `notifications`, `search`, `summary`, `calendar`, `gamification` y `wishlist`.
- `__($key, $params, $lang)` con fallback a español y, si tampoco existe, la clave.
- `t($key, $params)` en JavaScript con el diccionario inyectado desde el layout.
- Resolución: usuario autenticado → cookie `app_lang` (1 año) → `Accept-Language` → español.
- Selector ES | EN en el header del onboarding, en la sesión (`/login`, `/registro`) y en Ajustes → Perfil.
- Al cambiar el idioma se guarda en `users.idioma` de inmediato.
- Fechas y moneda con `IntlDateFormatter`/`NumberFormatter` si `intl` existe; fallback manual. En JS, `Intl.NumberFormat`/`Intl.DateTimeFormat`.

## Notificaciones y Web Push

- El motor vive en `NotificationService` y corre de forma perezosa desde `NotificationMiddleware` (máximo cada 5 minutos). No requiere cron.
- `notificaciones_config` guarda activo, hora y frecuencia por tipo; `notificaciones.clave` evita repetir el mismo aviso dentro del período.
- Los avisos críticos (ingreso del día, rescate, recurrente, categoría y resumen) siempre usan tono neutral. El tono motivacional/empático aplica a los informativos.
- Web Push usa `minishlink/web-push` + `php-http/guzzle7-adapter`. Configura en `.env`:
  - `PUSH_ENABLED=true`
  - `VAPID_PUBLIC_KEY` y `VAPID_PRIVATE_KEY` (genera con `Minishlink\WebPush\VAPID::createVapidKeys()`)
  - `VAPID_SUBJECT=mailto:...`
- Si faltan claves, el push está deshabilitado o la entrega falla, el aviso queda en la app y se registra en `storage/logs/push.log`.
- El service worker está en `public/sw.js` y se registra desde Ajustes → Notificaciones.

## Rutas principales

| Ruta | Descripción |
| --- | --- |
| `/` | Landing pública; si hay sesión redirige a `/inicio` |
| `/registro`, `/login`, `/logout` | Autenticación |
| `/recuperar/*` | Recuperación por preguntas, código o contacto |
| `/onboarding` | Redirige al paso pendiente |
| `/onboarding/{paso}` | Tutorial, simulador, método, moneda, tipo, configuración, recurrencia, primer ingreso, notificaciones |
| `/inicio` | Inicio autenticado |
| `/ingresos/nuevo` | Registrar ingreso (monto y tipo) |
| `/ingresos/acreditar?id=N` | Desglose, acreditación y guardado |
| `/gastos/nuevo` | Registrar gasto manual |
| `/gastos/ver?id=N` | Detalle del gasto |
| `/gastos/editar?id=N` | Edición dentro del período |
| `/gastos/foto?id=N` | Foto autenticada del gasto |
| `/gastos/conciliar` | Conciliación de pendientes |
| `/movimientos` | Lista, filtros y búsqueda de gastos |
| `/mover` | Movimientos entre categorías (redistribución) |
| `/categorias/ver?parte=N` | Detalle e historial de una categoría |
| `/notificaciones` | Bandeja de notificaciones in-app |
| `/ajustes/notificaciones` | Configuración, tono, vacaciones, Web Push y exportación `.ics` |
| `/resumen` | Lista de resúmenes mensuales; `/resumen?id=N` abre el detalle |
| `/buscar` | Búsqueda global con filtros combinables |
| `/calendario` | Calendario mensual, agenda y hoja de día |
| `/reportes` | Patrimonio neto, score, efectivo manual y resumen de suscripciones |
| `/reportes/suscripciones` | Suscripciones agrupadas, costo mensual/anual y ahorro potencial |
| `/deudas` | Deudas, estrategia, pagos y recordatorios |
| `/acerca-de` | Información del producto, principios, versión y soporte |
| `/tags` | Alias de la gestión de tags |
| `/subcategorias` | Gestión de subcategorías por categoría |
| `/logros` | Racha, nivel, badges, reto del período y comparación social |
| `/deseos` | Lista de deseos con prioridad, enfriamiento e impacto |
| `/estado-cuenta` | Estados de cuenta en PDF; solo escritorio, con reautenticación |
| `/ajustes/privacidad` | Exportar datos, opt-out de analytics y borrar cuenta |
| `/privacidad`, `/terminos`, `/aviso-legal` | Documentos legales públicos y versionados |
| `/exportar/descargar?token=…` | Descarga ARCO de un solo uso para el usuario |
| `/desbloquear` | Desbloqueo por PIN o contraseña tras inactividad |
| `/admin/usuarios` | Gestión de usuarios (solo metadatos) |
| `/admin/reportes` | Estadísticas globales anónimas con mínimo de 10 |
| `/admin/legal` | Publicar y versionar documentos legales |
| `/admin/contenido` | Crear, editar, publicar y despublicar lecciones |
| `/ahorro` | Panel de ahorro, metas y fondo de emergencia |
| `/inversion` | Panel de inversión, instrumentos y pausas |
| `/donacion` | Panel de donación, causas e historial |
| `/ajustes` | Índice de ajustes |
| `/ajustes/perfil` | Cuenta, idioma, efectivo manual, switches de gamificación y comparación social |
| `/ajustes/monedas` | Monedas activas, moneda principal y tasas vigentes |
| `/soporte` | Bandeja de conversaciones entre el usuario y la administración |
| `/admin/monedas` | Tasas de cambio globales y recálculo de conversiones |
| `/admin/soporte` | Bandeja de soporte, respuestas y cierre de conversaciones |
| `/ajustes/ingreso` | Recurrencia, día de corte y reparto del extra |
| `/ajustes/recurrentes` | Gastos recurrentes |
| `/ajustes/plantillas` | Plantillas de gasto |
| `/ajustes/tags` | Gestión de tags |
| `/ajustes/temas` | Editor de tema |
| `/ajustes/seguridad` | Contraseña, PIN, preguntas y sesiones |
| `/ajustes/compartir` | QR y copiar enlace |
| `/ajustes/android` | Placeholder de app Android |
| `/admin/*` | Panel, usuarios, reportes, legal, auditoría y placeholders |
| `/api/app/version` | Estado de la app Android |
| `/api/onboarding/estado` | Autosave del simulador (requiere sesión) |

Los placeholders de finanzas, herramientas y admin se resuelven desde `navigation.placeholders` con una sola vista genérica.

## Base de datos

- **57 tablas** InnoDB con `utf8mb4_unicode_ci`, montos en centavos (`BIGINT`) y `moneda CHAR(3) DEFAULT 'MXN'`.
- `sql/schema.sql` es **reejecutable y destructivo**.
- Para instalaciones existentes, `sql/migrations/20261113_phase7.sql` aplica los cambios de Fase 7, `sql/migrations/20261113_phase8.sql` los de Fase 8, `sql/migrations/20261113_phase9.sql` los de Fase 9, `sql/migrations/20261113_phase10.sql` los de Fase 10 y `sql/migrations/20261113_phase11.sql` los de Fase 11 y `sql/migrations/20261113_support.sql` los del sistema de soporte, sin recrear la base. Después de aplicar las migraciones, ejecuta `sql/seed.sql` para sembrar documentos legales y lecciones iniciales.
- `sql/seed.sql` es **idempotente**: 5 métodos, 23 categorías (incluye Donación opcional en 50/30/20 y 70/20/10), documentos legales 1.0 y 6 lecciones contextuales en español e inglés.
- Cada usuario recibe 16 subcategorías semilla (9 Necesidades + 7 Deseos).
- `users` incluye `onboarding_estado`, `tema_*`, `modo_discreto`, `tipo_ingreso`, `recurrencia`, `recurrencia_anterior`, `recurrencia_cambio_desde`, `extra_reparto_json`, `estado`, `pin_hash`, `must_change_password`, `analytics_opt_out` y `last_login_at`.
- `estados_cuenta` guarda MIME, checksum, tipo y ruta privada; `estados_cuenta_consentimientos` registra la aceptación del aviso. `analytics_eventos` no guarda montos ni notas. `legal_documentos` y `legal_aceptaciones` versionan privacidad, términos y aviso. `login_attempts` limita intentos y `export_tokens` emite enlaces ARCO de un solo uso.
- `deudas` y `pagos_deuda` guardan deuda original, saldo, tasa, pago mínimo y vínculo opcional al gasto. `patrimonio_snapshots` guarda el cierre mensual de activos, pasivos y neto. `gastos_recurrentes.deuda_id` marca recordatorios de pago que no generan gasto.
- `monedas_usuario` guarda las monedas activas y la principal por usuario. `tasas_cambio` guarda pares globales por fecha. Las tablas monetarias conservan su moneda original y añaden columnas `*_principal_centavos` para la consolidación.
- `lecciones` guarda contenido bilingüe por sección con `publicada` y `orden`; `lecciones_vistas` registra la primera lectura por usuario. `score_snapshots` guarda el score mensual y su desglose JSON.
- `ingresos` guarda el período UTC, el método usado (`metodo_id`) y el tipo fijo/variable/extra.
- `gastos` guarda el período UTC, `fuente_exceso`, `confirmado`, el vínculo al recurrente y la foto. `plantillas_gasto` guarda `tags_json`, `usos` y `ultimo_uso_at`. `subcategorias.parte_metodo_actual` recuerda el mapeo por usuario.
- `pausas_inversion.monto_centavos` guarda el disponible que se acumula al siguiente período.
- `inversiones_fondos` queda preparada con `valor_actual_centavos` y `fecha_valor_actual` nullables; no se usa todavía.
- `movimientos` guarda la redistribución entre categorías con período UTC y motivo obligatorio.
- `notificaciones` usa `clave` única por usuario para no repetir avisos; `notificaciones_config` guarda activo/hora/frecuencia. `push_suscripciones` almacena los endpoints Web Push y `resumenes_mensuales` el snapshot JSON inmutable del cierre.
- `rachas` guarda el contador, el récord, el último período evaluado y la marca de congelamiento. `niveles` guarda el nivel, el porcentaje y el desglose JSON. `retos` guarda un reto por período y `hitos_usuario` los 5 badges con `celebrado_at`.
- `users` incluye `gamificacion_activa` y `comparacion_social`. `lista_deseos` guarda prioridad, fechas, estado, vínculo opcional a `gastos` y `comprado_at`.
- El color por defecto del tema es `#157f6b`.

### Métodos y porcentajes

| Método | Reparto |
| --- | --- |
| 50/30/20 | Necesidades 50 · Deseos 30 · Ahorro 20 · Donación opcional |
| Seis sobres | Necesidades 55 · Ahorro LP 10 · Educación 10 · Inversión 10 · Diversión 10 · Donación 5 |
| Cinco frascos | Consumo 50 · Inversión 20 · Ahorro 10 · Donación 10 · Compartir 10 |
| 70/20/10 | Gastos 70 · Ahorro 20 · Deudas/Inversión 10 · Donación opcional |
| Kakebo | Sin porcentajes fijos (no elegible en onboarding) |

## Gastos

### Registro manual

- `/gastos/nuevo` (pantalla de flujo): monto, parte del método y fecha.
- **Bloqueo sin ingreso**: si no hay ingresos en el período actual, se muestra el mensaje y el CTA a `/ingresos/nuevo`. Los recurrentes están exentos.
- **Más detalles**: subcategoría (solo para partes `controlada`), nota, foto, compartido, medio de pago y tags.
  - **Compartido**: se guarda el total y “mi parte”; solo “mi parte” descuenta.
  - **Subcategoría**: se sugiere por parte recordada e historial; sin subcategoría se agrupa como “Sin clasificar”. Se guarda `subcategoria_nombre` como snapshot.
  - **Tags**: máximo 5, normalizados a minúsculas y sin acentos. Autocompletado con los tags existentes.
  - **Foto**: JPG/PNG/WebP, se redimensiona a 1600 px y se comprime a menos de 1 MB con GD. Se guarda en `storage/uploads/gastos/{user}/` y se sirve por `GET /gastos/foto?id=N` con sesión.
  - **Medio de pago**: se sugiere con el más usado en los últimos gastos.
- **Exceso**: el gasto se guarda completo; lo cubierto descuenta del disponible y el exceso va a `no_controlado` con `origen='gasto_excedido'`. El formulario muestra “$X disponible + $Y a No controlado” y pregunta “¿De dónde salió?”. La fuente se guarda en `gastos.fuente_exceso`; los retiros de ahorro/inversión y la deuda se ejecutarán en sus fases.
- **Edición/borrado**: solo si el gasto pertenece al período actual. Fuera de él se muestra “Período cerrado” y solo lectura. Al borrar se eliminan sus tags, su foto y sus filas de exceso; el disponible se libera.

### Plantillas y tags

- Plantillas: máximo 8 chips visibles, ordenados por `usos` y `ultimo_uso_at`. Se crean desde Ajustes → Plantillas o desde el detalle de un gasto con “Guardar como favorito”. Al tocar un chip se precargan monto, parte, subcategoría y tags.
- Tags: Ajustes → Tags permite renombrar, fusionar y borrar. La fusión reasigna `gasto_tags` y las plantillas. No afectan la asignación; solo filtran y agrupan.

### Recurrentes

- Configuración en Ajustes → Gastos recurrentes: nombre, monto fijo/variable, parte, subcategoría, recurrencia y próxima ejecución.
- Se ejecutan **sin cron**, desde `RecurringExpenseMiddleware`: al autenticarse, procesa las ocurrencias vencidas (máximo una vez por minuto y con tope de 24 ocurrencias por recurrente).
- Con ingreso del período: gasto `asignado` (o exceso a No controlado) y `confirmado=0`.
- Sin ingreso: gasto `pendiente_asignacion`; no descuenta.
- Inicio muestra el aviso **Correcto / Editar / Ya no tengo este gasto**. “Ya no tengo” desactiva la recurrencia y borra la ocurrencia si el período está abierto.
- Los atrasos se procesan en orden, cada uno con su período histórico.

### Conciliación

- Banner en Inicio con la cantidad de gastos `pendiente_asignacion`.
- `/gastos/conciliar` lista los pendientes y permite “Conciliar todos” o uno por uno.
- Se descuentan en orden de fecha contra el disponible real. Si no alcanza, el excedente va a No controlado.

### Movimientos e inconsistencias

- `/movimientos` lista los gastos del período con fecha, monto, parte, subcategoría, estado, medio, tags y miniatura.
- Filtros: período (incluye “Todos”), parte, subcategoría (incluye “Sin clasificar”), tag y medio. Búsqueda FULLTEXT sobre nota/subcategoría con fallback `LIKE` para términos cortos.
- `InsightService` muestra en Inicio hasta dos avisos suaves: gastos > ingresos dos períodos, No controlado creciente, categoría al 100 % y ahorro/inversión sin movimientos.

## Ahorro, inversión y donación

Tres paneles separados, accesibles desde la sección **FINANZAS** del sidebar/drawer. Cada uno tiene su propio lenguaje y su propio bottom sheet de Aportar/Retirar.

### Ahorro

- **Total acumulado** = aportaciones − retiros, sumando todas las bolsas y metas.
- **Bolsas de ahorro** (`cuentas_ahorro`): nombre, tipo (`banco`, `efectivo`, `cooperativa`, `otro`), institución, moneda, saldo inicial y bolsa predeterminada.
  - Cada bolsa muestra saldo, asignado a metas y sin asignar.
  - El saldo inicial no cuenta como aportación del período ni afecta el score.
  - Se pueden transferir saldos entre bolsas de la misma moneda.
- **Metas**: cada meta pertenece a una bolsa. Nombre, tipo (`ahorro`, `retiro`, `casa`, `educacion`, `otro`), objetivo, fecha objetivo o plazo en meses, activa/inactiva. Progreso y aporte mensual sugerido `(objetivo − acumulado) / meses restantes`.
  - **Asignar a meta** mueve saldo sin asignar a una meta sin contar como aportación.
  - **Mover meta** transfiere su acumulado a otra bolsa y actualiza su bolsa.
- **Fondo de emergencia**: meta automática de 3 meses de gasto promedio.
  - Promedio de los últimos 3 meses calendario cerrados; si no hay historial, usa el período actual.
  - Si el objetivo cambia más de 20 %, aparece un aviso con **Actualizar fondo** / **Mantener**.
- **Aportar**: monto, bolsa, meta opcional y fecha. No se permite aportar a metas inactivas.
- **Retirar**: bolsa, meta opcional, monto, motivo (`emergencia`, `meta_cumplida`, `reasignacion`, `otro`) y confirmación. No se puede retirar dinero asignado a metas sin retirarlo desde la meta.
- **Disponible** = asignaciones de categorías `ahorro` del período − aportaciones normales del período. Si se excede, se marca “Aportación por encima del %”.
- Movimientos recientes con edición/borrado solo en el período actual.

### Inversión

- Instrumentos de flujo: fondo, cripto, CETES, acciones y otro. Se muestra **aportado/acumulado** por instrumento y el total.
- **Portafolio** (`inversiones_posiciones`): posiciones por ticker con tipo, mercado, moneda, cantidad, costo total, valor manual y modo de actualización.
  - BMV y EUA usan cotizaciones de Yahoo Finance.
  - Cripto usa CoinGecko.
  - CETES y fondos usan valor manual.
  - El CronJob `cause-quotes` actualiza cotizaciones diariamente después del cierre.
  - Las posiciones manuales y las cotizaciones desactualizadas se marcan en la interfaz.
- **Aportar**:
  - Normal: descuenta del disponible de inversión.
  - Extra (`es_extra=1`): no descuenta.
  - Si lo normal excede el disponible, se permite y se marca.
- **Retirar**: instrumento, monto, motivo (`liquidez`, `meta_cumplida`, `rebalanceo`, `otro`) y texto. Valida contra el acumulado del instrumento.
- **Rebalanceo**: si el motivo es `rebalanceo` y se elige destino, se registra la salida y una aportación extra al instrumento destino en una transacción.
- **Pausa**: por período y motivo (`apuro`, `desempleo`, `otro`).
  - Guarda el disponible en `pausas_inversion.monto_centavos` y lo acumula al período siguiente.
  - Mientras está pausada, solo se permiten aportaciones extra.
  - Dos pausas consecutivas muestran el aviso “¿Ajustamos tu %?”. **Ajustar mi %** crea una nueva versión de `usuario_metodos` moviendo la diferencia a ahorro; **Seguir pausando** oculta el aviso.
- `inversiones_fondos` queda como tabla heredada sin uso; el portafolio vive en `inversiones_posiciones` e `inversiones_precios`.

### Donación

- Total del período, total histórico, desglose por causa e historial.
- **Donar**: monto, causa libre opcional, fecha y `es_extra`.
  - Normal: descuenta del disponible de donación.
  - Extra: no descuenta.
- **Activar donación**: para métodos sin categoría de donación (50/30/20 y 70/20/10), se añade `Donación` con el porcentaje elegido y se reduce la mayor categoría controlada. Aplica solo a ingresos futuros. Mientras no se active, solo se permiten donaciones extra.
- Cinco frascos ya incluye Donación 10 % y Seis sobres 5 %.

### Versiones del método

Cualquier cambio de porcentajes (activar donación o ajustar inversión) cierra la fila vigente de `usuario_metodos` e inserta una nueva con `vigente_desde=NOW()`. El cambio aplica **solo a ingresos futuros**; los ingresos ya registrados conservan sus asignaciones.

### Inicio

Tres tarjetas resumen con monto y barra cuando aplica:

- **Ahorro**: acumulado + progreso del fondo de emergencia.
- **Inversión**: acumulado.
- **Donación**: donado en el período + progreso contra lo asignado.

Cada tarjeta abre su panel. Los montos respetan el modo discreto.

## Progreso y gamificación

- **Racha**: cuenta períodos con ingreso finalizado. La evaluación es híbrida: hook al finalizar un ingreso y reconciliación perezosa desde `GamificationMiddleware` (máximo cada 5 minutos, sin cron).
  - `activa` → `pausa` tras un período cerrado sin registro; `pausa` → `rota` tras dos seguidos.
  - Al registrar después de una pausa, la racha continúa donde estaba. Al reiniciar tras una rotura, vuelve a 1 y conserva `mejor_racha`.
  - Si el switch de gamificación está apagado, la racha se congela: no se evalúan los períodos apagados.
- **Niveles**: `Constante` (0–49), `Equilibrado` (50–69), `Disciplinado` (70–89) y `Consolidado` (90–100). Se calculan con los últimos 3 períodos cerrados.
  - Categorías controladas: cumplimiento si no se excede lo asignado.
  - Ahorro, inversión y donación: cumplimiento por tipo, comparando lo aportado contra lo asignado. Las aportaciones no están etiquetadas por categoría, así que se agregan por tipo (ej. Seis sobres suma Ahorro LP + Educación).
  - `niveles.detalle_json` guarda el desglose y lo que falta para el siguiente nivel. El nivel es privado y solo se muestra en Perfil y `/logros`. Las bajadas de nivel no generan avisos.
- **Badges**: solo `primera_meta`, `primer_mes_completo`, `emergencia_50`, `emergencia_100` y `racha_30`. La celebración es un modal ligero de 1–2 s, una sola vez por hito y con opción de saltar. Nunca se envía por Web Push.
- **Retos**: uno por período, generado según el mayor tipo no controlado del método (ahorro, inversión o donación), con desempate ahorro > inversión > donación. En métodos sin porcentajes fijos (Kakebo) se usa `registra_todo`. El usuario puede ignorarlo hasta el siguiente período.
- **Comparación con tu yo pasado**: siempre visible. Compara ahorro + inversión netos del período actual contra el anterior.
- **Comparación social**: opt-in y apagada por defecto. Agrupa por método, recurrencia y moneda; requiere al menos 10 usuarios con nivel calculado. Solo muestra un texto contra el promedio agregado y anónimo; nunca rankings, personas ni montos.
- **Switch**: “Mostrar racha y logros” en Ajustes → Perfil. Apaga racha, badges, celebraciones, retos y comparación social; mantiene el nivel privado, la comparación con tu yo pasado y los avisos funcionales. Los hitos siguen siendo informativos, pero no se celebran.
- La lógica vive en `GamificationService`, `GamificationRepository` y `GamificationMiddleware`. La página de detalle es `/logros`.

## Lista de deseos

- Ruta `/deseos`, accesible desde el drawer/sidebar junto a Ahorro.
- Campos: nombre, monto estimado, prioridad alta/media/baja, fecha objetivo opcional, enfriamiento opcional, estado (`activo`, `comprado`, `descartado`) y nota.
- Tarjetas con monto, prioridad, estado, impacto y fecha de compra cuando aplica.
- **Impacto**: `ahorro total acumulado − monto`, con piso 0; muestra el nuevo total y el porcentaje del ahorro.
- **Enfriamiento**: se puede elegir 7, 15 o 30 días, o una fecha personalizada. Mientras está vigente, la compra se bloquea con opción explícita de saltar la espera.
- **Compra**: permite marcar solo el estado o registrar también un gasto. Si la categoría no tiene disponible, el gasto queda `pendiente_asignacion`; si hay disponible parcial, el exceso sigue la regla normal de No controlado. El gasto queda vinculado en `lista_deseos.gasto_id`.
- `WishlistService` valida montos, prioridad, fechas y enfriamiento. `WishlistController` expone crear, editar, comprar y descartar.

## Seguridad final

- La contraseña se pide en cada inicio de sesión. La sesión PHP usa cookie `HttpOnly`, `SameSite=Lax` y `Secure` en producción.
- Tras 15 minutos de inactividad la sesión se bloquea. Se desbloquea con PIN de 6 dígitos o con la contraseña. El PIN se guarda con `password_hash`, tiene 5 intentos y bloqueo de 15 minutos. La biometría/WebAuthn queda documentada como atajo futuro; esta fase implementa el PIN.
- Las sesiones caducan por inactividad (12 h) y por duración absoluta (30 días); se validan contra la tabla `sesiones`. “Cerrar todas las sesiones” sigue disponible en Ajustes → Seguridad.
- Los intentos de login se limitan por email + IP con `login_attempts`.
- Suspender una cuenta invalida sus sesiones de inmediato y bloquea el login.
- Cabeceras de seguridad: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` y HSTS cuando hay HTTPS en producción.
- TLS y cifrado en reposo son responsabilidad del despliegue. `app/config/database.php` es el único punto de conexión PDO, pensado para sustituirse por SQLCipher sin tocar la lógica de negocio.
- No hay analytics de terceros. El analytics propio guarda eventos sin montos ni notas, con retención de 12 meses y opt-out en Ajustes → Privacidad. Al activar el opt-out se borran los eventos previos del usuario.
- El modo discreto 👁️ sigue siendo por sesión (`sessionStorage` + `money_html`) y se restablece al reabrir.

## Privacidad, exportación y borrado

- **Exportar** en Ajustes → Privacidad genera un ZIP en streaming con:
  - `datos/*.csv` de ingresos, asignaciones, gastos, movimientos, aportaciones y retiros, donaciones, metas, deudas, deseos, tags, subcategorías, recurrentes, plantillas, resúmenes, rachas, niveles, retos, hitos y configuración.
  - `resumen.pdf` con totales, metas y deseos.
  - `estados_cuenta/*.pdf` y `fotos/*`.
  - `LEEME.txt` con el alcance y la nota de que no queda copia en el servidor.
- El ZIP se crea en un temporal, se transmite y se elimina en `finally`. No se guarda en `storage/`.
- El envío por email queda deshabilitado hasta que exista un transporte SMTP real; la interfaz lo indica.
- **Borrar cuenta** exige contraseña, escribir `ELIMINAR` y marcar la casilla. Elimina la cuenta y, por cascada, datos financieros, notas, tokens, sesiones, push y archivos. Conserva la auditoría administrativa con alias snapshot.
- El plazo de backups del proveedor se documenta como hasta 30 días. Al borrar, las sesiones de otros dispositivos dejan de funcionar.

## Estados de cuenta

- Ruta `/estado-cuenta` dentro de Herramientas. Solo se muestra en escritorio (≥1200 px) y con UA no móvil. El backend responde **403** a UA móviles/tablet.
- Al entrar se pide la contraseña. La reautenticación dura 15 minutos por sesión.
- Antes de habilitar la subida se muestra el aviso obligatorio y se exige “Entiendo y acepto”. Se registra versión, fecha/hora, IP y UA en `estados_cuenta_consentimientos`.
- Solo se aceptan PDFs reales: extensión `.pdf`, MIME `application/pdf` con `finfo` y firma `%PDF-`. Máximo 10 MB por archivo y 240 archivos por usuario.
- Los archivos viven en `storage/estados_cuenta/{user_id}/`, fuera de `public/`. Se sirven por `/estado-cuenta/ver`, que valida sesión, propiedad y reautenticación; nunca hay URL pública.
- No se extrae información de los PDFs. Se incluyen en la exportación y se eliminan al borrar la cuenta.

## Admin, estadísticas y legal

- `/admin/usuarios` lista solo metadatos: alias, email, registro, último acceso, estado, idioma, moneda, onboarding, rol, número/tamaño de PDFs y sesiones.
- Acciones: buscar, crear, suspender/reactivar con motivo, eliminar con doble confirmación, forzar cierre de sesión, resetear contraseña con temporal mostrada una vez y cambio obligatorio, cambiar email manualmente, ver onboarding/hogar y generar enlace ARCO de un solo uso.
- El admin nunca ve montos, notas, causas, distribución ni contenido de PDFs. El enlace ARCO lo descarga el propio usuario autenticado; el admin solo recibe la URL.
- Todas las acciones quedan en `admin_audit_log`, que conserva `admin_alias` y `admin_email` aunque se borre la cuenta del administrador.
- `/admin/reportes` calcula agregados anónimos con mínimo 10 usuarios y caché de 1 hora:
  - usuarios totales, activos 7/30 días, onboarding y nuevos por día;
  - % que ahorra este mes, % que cumple su método (≥70 %), % con deudas activas y promedio de deuda;
  - distribución por método, % con fondo de emergencia completo y meses promedio de fondo;
  - uso de funciones en 30 días, evolución mensual y retención por cohorte;
  - el score de salud no se muestra: es privado y no entra en estadísticas agregadas.
- Los buckets de método con menos de 10 usuarios se agrupan en “Otros”. Las cohortes con menos de 10 no se muestran.
- `/admin/legal` permite publicar versiones bilingües de privacidad, términos y aviso legal. Al publicar, la versión anterior deja de estar vigente y los usuarios con onboarding completo deben aceptar la nueva antes de seguir.

## Despliegue, offline y backups

- El service worker cachea solo el shell (CSS, JS, iconos y `offline.html`). **Nunca** cachea HTML autenticado ni respuestas con datos financieros. Si no hay red, se muestra una página de “Sin conexión”.
- El contrato de sincronización futura es “última escritura gana” con marcas de tiempo (`updated_at`). Las escrituras actuales requieren conexión; no hay cola offline.
- En producción: habilita TLS, HSTS, backups cifrados del proveedor y una política de retención de 30 días. El correo sigue deshabilitado hasta configurar `MAIL_ENABLED` y un transporte real.

## Deudas

- `/deudas` en Finanzas. Estrategia global en `users.estrategia_deuda`: bola de nieve, avalancha o manual.
- Cada deuda guarda nombre, monto total, saldo actual, tasa opcional, pago mínimo opcional y fecha límite.
- Panel con progreso por deuda y global, deuda prioritaria según estrategia, próximo pago e historial.
- Registrar pago:
  - fecha libre; si se registra como gasto, debe caer en el período actual y existir ingreso;
  - descuenta el saldo y deja `activa=0` al llegar a cero;
  - si se registra como gasto, usa una categoría controlada o una que contenga “Deuda” (ej. Deudas/Inversión en 70/20/10);
  - rechaza pagos mayores al saldo.
- Al eliminar una deuda se eliminan sus pagos y recordatorios; los gastos históricos permanecen.
- En el formulario de gasto, “De deuda” permite sumar el exceso a una deuda existente o crear una nueva.
- Recordatorio de pago mínimo: fila en `gastos_recurrentes` con `deuda_id`. No crea gasto ni descuenta saldo: emite una notificación `recordatorio_deuda`.

## Patrimonio neto

- `/reportes` muestra activos, pasivos y patrimonio:
  - activos = ahorro neto + inversión a valor de mercado + `users.efectivo_manual_centavos`;
  - la inversión a valor de mercado suma posiciones y el aportado sin asignar a posiciones;
  - pasivos = saldos de deudas activas.
- El efectivo manual se edita en Ajustes → Perfil y en Reportes.
- El score usa el patrimonio **aportado** (no el de mercado) para no premiar ni castigar movimientos del mercado.
- Snapshot mensual en `patrimonio_snapshots` con fecha del primer día del mes:
  - guarda `neto_centavos` (mercado) y `neto_aportado_centavos` (score);
  - perezoso al abrir Reportes;
  - también al cerrar período.
- Gráfica SVG de 12 meses. Sin backfill: el historial empieza con la fase.

## Metas con plazo

- Se usan `tipo`, `fecha_objetivo` y `plazo_meses` ya existentes.
- La tarjeta muestra tipo, objetivo, aportado, progreso, fecha o plazo restante y aporte mensual sugerido.
- El aporte sugerido es `(objetivo − aportado) / meses restantes`; solo se muestra, no se descuenta.
- Si la meta se cumple, aparece el badge visual “Cumplida”. No se agrega un hito nuevo.

## Proyección de fin de período

- Cálculo aritmético con los días del período en la zona horaria del usuario.
- En Inicio: resumen de gasto proyectado y hasta dos categorías controladas en riesgo.
- En el detalle de categoría: proyección de gasto, disponible estimado y fecha de agotamiento.
- Si ya se agotó, se indica “ya se agotó” en lugar de una fecha pasada.

## Suscripciones

- `/reportes/suscripciones` toma los gastos recurrentes activos y excluye recordatorios de deuda.
- Agrupa por nombre normalizado; los montos variables se muestran con etiqueta.
- Anualiza según frecuencia: diaria × 365, semanal × 52, mensual × 12, anual × 1.
- Muestra costo mensual, costo anual y checkboxes para calcular el ahorro potencial de cancelar seleccionadas.

## Búsqueda global

- Incluye deudas, aportaciones, retiros de ahorro e inversión, pagos de deuda, deseos y metas.
- Los nuevos tipos tienen etiqueta e icono propios.

## Educación contextual

- Cada sección muestra un botón **“¿Qué es esto?”** con una lección breve: Ahorro, Inversión, Donación, Deudas, Patrimonio y Fondo de emergencia.
- La lección se abre en un offcanvas y nunca bloquea la pantalla.
- Al abrirla por primera vez se registra en `lecciones_vistas` y desaparece el chip **“Nueva lección”**. No hay push ni notificación adicional.
- El contenido es descriptivo, neutral y bilingüe; máximo 200 palabras por idioma.
- `/admin/contenido` permite crear, editar, publicar y despublicar lecciones. El código es fijo después de crear y no se elimina contenido: solo se despublica.

## Calendario externo

- `/ajustes/notificaciones/calendario.ics` descarga un archivo `.ics` con los gastos recurrentes activos y los recordatorios de deuda.
- Cada evento incluye nombre, monto, recurrencia y próxima ejecución, con `RRULE` para que el calendario repita el evento.
- Funciona con Google Calendar, Apple Calendar y Outlook. Sin OAuth ni sincronización directa.

## Score de salud financiera

- Score privado de 0 a 100 en Inicio y Reportes. No se comparte, no se usa en gamificación y no aparece en estadísticas de admin.
- Componentes: cumplimiento del método (30%), ahorro neto sobre ingreso (25%), deuda sobre ingreso mensual normalizado (20%), fondo de emergencia (15%) y patrimonio neto (10%).
- Se calcula con el período actual y se guarda en `score_snapshots` con fecha del primer día del mes, de forma perezosa y al cerrar período.
- Si no hay ingreso o no hay datos de cumplimiento del método, se muestra “Aún no hay datos suficientes” y no se guarda snapshot.
- La gráfica muestra 12 meses y el detalle incluye el desglose por componente.
- Lenguaje neutral: “Vas mejorando”, “Revisa tu progreso” o “Se mantiene”. Incluye el aviso “El score es una guía, no un diagnóstico financiero.”

## Multi-moneda

- La moneda principal (`users.moneda_activa`) es la unidad contable de presupuestos, categorías, score y reportes.
- `monedas_usuario` permite activar MXN, USD y EUR. La principal se cambia en `/ajustes/monedas` con advertencia y recálculo.
- `tasas_cambio` es global y la administra el admin en `/admin/monedas`. Se resuelven pares directos, inversos, cruces por pivote y la tasa anterior más reciente.
- Cada movimiento conserva su moneda y monto originales y guarda su equivalente en `*_principal_centavos`.
- Metas, deudas e inversiones conservan su moneda; sus aportes y pagos la heredan.
- Los reportes, patrimonio, score, proyección, suscripciones, calendario y exportación consolidan en la moneda principal.
- Si falta una tasa, el movimiento se bloquea con aviso. Si no hay tasa exacta, se usa la anterior más reciente.
- Al corregir una tasa se recalculan movimientos, metas, deudas, inversiones, ingresos y asignaciones; las asignaciones se escalan proporcionalmente para conservar la consistencia del presupuesto.
- Al cambiar la moneda principal se recalculan también ingresos, asignaciones y efectivo manual.

## Acerca de

- `/acerca-de` explica qué es Cauce, qué no es, cómo funciona y sus principios.
- Muestra la versión actual, métodos disponibles, estado del proyecto, soporte y enlaces legales.
- No es una segunda landing: es una página de producto y transparencia para usuarios autenticados.

## Tags y subcategorías

- `/tags` y `/ajustes/tags` comparten la gestión de tags: crear, renombrar, fusionar y eliminar.
- Los tags también se crean desde el formulario de gasto; una cuenta nueva puede empezar vacía.
- `/subcategorias` lista las 16 subcategorías semilla por categoría y permite crear, renombrar y desactivar.
- El borrado de subcategorías es suave: los gastos históricos conservan su nombre.

## Soporte

- `/soporte` permite al usuario abrir conversaciones, responder y consultar todo el historial.
- `/admin/soporte` muestra la bandeja global, mensajes sin leer, respuestas y cierre/reapertura.
- Cada conversación tiene asunto, estado `abierta/cerrada`, último mensaje y marcas de lectura por separado para usuario y admin.
- Las respuestas del admin generan una notificación in-app `soporte_respuesta`; no se envía push.
- El correo de soporte se retiró de la aplicación y de los documentos legales. El canal es la bandeja interna.
- La recuperación de acceso dirige a iniciar sesión y abrir Soporte cuando el usuario aún puede entrar.

## Servicio de reparto

`app/src/Services/RepartoService.php`:

```php
$reparto = (new RepartoService())->repartirIngreso(10000, [
    'Necesidades' => 50,
    'Deseos' => 30,
    'Ahorro' => 20,
]);

// ['Necesidades' => 5000, 'Deseos' => 3000, 'Ahorro' => 2000]
```

- Trabaja en centavos enteros.
- Calcula `floor(monto * pct / 100)` por categoría.
- Todo el residuo se asigna a la primera categoría con mayor porcentaje (empate por orden de entrada).
- Los porcentajes deben sumar 100 ±0.01.

## Roles y admin

- `/admin/*` pasa por `AdminMiddleware`: invitado → login, usuario sin rol admin → 403.
- El panel lista usuarios (alias, email, registro, rol, onboarding), permite crear cuentas y forzar un nuevo código de recuperación.
- Cada acción admin se registra en `admin_audit_log`: login/logout, accesos, creación, reseteo, cambio de idioma y accesos denegados.
- La auditoría nunca guarda contraseñas ni códigos en claro.
- El panel usa el layout general, con el grupo ADMIN en el sidebar.

## Scripts

| Comando | Descripción |
| --- | --- |
| `npm run serve:php` | Servidor PHP embebido en `localhost:8000` |
| `npm run build` | Compila assets de producción |
| `npm run dev` | `gulp`: build + watch + BrowserSync |
| `npm run watch` | Observa SCSS/JS y recompila |
| `npm run serve` | BrowserSync con proxy a `localhost:8000` |
| `composer serve` | Equivalente a `serve:php` |
| `composer test` | Ejecuta PHPUnit |

### Tareas de Gulp

| Tarea | Acción |
| --- | --- |
| `gulp styles` | `assets/scss/app.scss` → `public/assets/css/app.css` |
| `gulp css` | Minifica a `app.min.css` |
| `gulp js` | Bootstrap + qrcode-generator + `assets/js/*.js` → `app.min.js` |
| `gulp icons` | Copia Bootstrap Icons y sus fuentes |
| `gulp images` | Optimiza imágenes |
| `gulp build` | Compila todo para producción |
| `gulp` | build + watch + serve |

## Tests

```bash
composer test
```

129 tests / 316 assertions:

- `RepartoServiceTest`: porcentajes exactos, 33.33 %, tercios, 7 categorías, centavos, residuo y validaciones.
- `MethodRecommenderTest`: reglas del test de 3 preguntas y empates.
- `ThemeServiceTest`: contraste WCAG, paleta, sugerencias y derivados.
- `OnboardingServiceTest`: estado por defecto, restauración, whitelist, clamps y pasos.
- `PeriodoServiceTest`: día 15, febrero, bisiesto, día 31, diaria/semanal/anual y cambio de recurrencia.
- `IngresoServiceTest`: reparto por método, residuo, regla extra y fallbacks.
- `GastoServiceTest`: gasto dentro del disponible, exceso, disponible cero y edición por período.
- `MoneyTest`: parseo de montos en formatos `10000`, `10,000`, `10.000,50`.
- `RecurrenceServiceTest`: siguiente fecha diaria/semanal/mensual/anual, ajustes de día y ocurrencias vencidas.
- `TagServiceTest`: normalización, límite de 5 y deduplicación.
- `MethodServiceTest`: versionado, activación de donación y ajuste del % de inversión.
- `CalendarServiceTest`: primer día de semana por idioma, nombres de mes y validación de mes.
- `GamificationServiceTest`: transiciones de racha, pausa/rotura/continuación, niveles, cumplimiento, selección de reto por método y comparación pasada.
- `WishlistServiceTest`: impacto de compra, piso en cero y vigencia del enfriamiento.
- `Phase8Test`: PDF válido sin dependencias, detección de UA móvil, mapeo de funciones de analytics, formato de tamaños, textos legales por idioma y minutos de bloqueo del PIN.
- `Phase9Test`: factores de anualización de suscripciones y estrategias de deuda disponibles.
- `Phase10Test`: fórmulas del score, tendencia, generación y plegado de `.ics`, y neutralidad del contenido inicial.
- `Phase11Test`: catálogo de monedas y conversión con redondeo.

## Estructura

```
app/
  config/          env.php · app.php · auth.php · database.php · onboarding.php · navigation.php
                   periodo.php · ingreso.php · themes.php · push.php · security.php
  src/
    Controllers/   Auth · Registration · Recovery · Settings · Admin · Language · Home
                   App · Onboarding · Placeholder · Api · Income · Expense
                   Movement · Template · Tag · Recurring · Savings · Investment · Donation
                   Move · Notification · Summary · Search · Calendar · Gamification · Wishlist
                   Legal · Lock · Privacy · Statement · AdminUser · AdminReport · AdminLegal
                   Debt · Report · AdminContent · Lesson · Currency · AdminCurrency
                   Support · AdminSupport
    Services/      Auth · Registration · Recovery · Reparto · Onboarding · MethodRecommender
                   Theme · Navigation · Periodo · Ingreso · Gasto · Photo · Recurrence
                   RecurringExpense · Tag · Insight · Method · FinanceSupport · EmergencyFund
                   Savings · Pause · Investment · Donation · FinanceSummary · AdminAudit · Mail/
                   Move · Notification · Push · Summary · Search · Calendar · Gamification · Wishlist
                   LoginThrottle · Pin · Analytics · Legal · Statement · Export · ExportToken
                   AccountDeletion · AdminUser · AdminStats · Debt · NetWorth · Projection
                   Subscription · Lesson · MethodCompliance · Score · CalendarExport
                   Currency · ExchangeRate · CurrencyRecalculation · Support
    Services/Export/ PdfWriter
    Repositories/  User · Session · Recovery · Subcategoria · AdminAudit · Metodo · UsuarioMetodo
                   Ingreso · Asignacion · Justificacion · NoControlado · Gasto · Move
                   Tag · Plantilla · RecurringExpense · Savings · Investment · Donation · Pause
                   Notification · Summary · Gamification · Wishlist · Debt · Lesson
                   Currency · ExchangeRate · Support
    Helpers/       Router · View · Session · Csrf · Flash · Validator · I18n · Locale · Logger
                   PasswordHasher · Cache · Client · SecurityHeaders · Storage
    Http/          Middleware/ (Auth · Lock · Legal · Onboarding · Admin · DesktopOnly
                   Analytics · RecurringExpense · Notification · Gamification)
  views/
    app/           inicio · movimientos · reportes · placeholder
    debts/         index
    reports/       index · subscriptions
    income/        nuevo · acreditar
    expenses/      form · show · conciliar · blocked
    savings/       index · sheets
    investment/    index · sheets
    donation/      index · sheets
    moves/         index · category
    notifications/ index · settings
    summary/       index · show
    search/        index
    calendar/      index
    progress/      index
    wishlist/      index
    legal/         show · accept
    statements/    index
    onboarding/    tutorial · simulador · metodo · moneda · tipo-ingreso · configuracion
                   recurrencia · primer-ingreso · notificaciones
    ajustes/       index · perfil · ingreso · recurrentes · plantillas · tags
                   monedas · temas · seguridad · compartir · android · privacidad
    support/       index · show
    subcategories/ index
    about/         index
    auth/          login · registro · código · recuperación · unlock · change-password
    admin/         panel · usuarios · user-detail · crear usuario · auditoría · reports · legal
                   content · rates · support/index · support/show
    partials/      header · sidebar · nav · drawer · dock · fab · errors
                   progress-card · past-comparison · celebration · lesson
                   score-widget · score-detail · currency-select
    layouts/       app · flow · onboarding · base · auth · admin · landing
  routes/          web.php
assets/
  scss/            app.scss · _shell.scss · _income.scss · _expenses.scss · _finance.scss
                   _phase6.scss · _phase7.scss · _phase8.scss · _phase9.scss · _phase10.scss
                   _landing.scss · _support.scss
  js/              00-core · 10-layout · 20-theme · 30-onboarding · 40-settings
                   50-income · 60-expenses · 70-finance · 75-moves · 80-notifications
                   85-wishlist · 88-debts · 89-reports · 90-calendar · 91-lessons · 95-progress
public/            index.php · router.php · sw.js · offline.html · manifest.webmanifest
assets/img/        icon-source · icon-192 · icon-512 · icon-maskable · apple-touch · favicon
sql/               schema.sql · seed.sql · migrations/20261113_phase7.sql
                   migrations/20261113_phase8.sql · migrations/20261113_phase9.sql
                   migrations/20261113_phase10.sql · migrations/20261113_phase11.sql
                   migrations/20261113_support.sql
storage/uploads/   gastos/{user}/ (fotos privadas)
storage/estados_cuenta/ {user}/ (PDFs privados)
storage/cache/     caché de estadísticas del admin
lang/es · lang/en  auth · common · nav · settings · themes · onboarding · app
                   income · expenses · finance · savings · investment · donation
                   moves · notifications · search · summary · calendar
                   gamification · wishlist · admin · legal · privacy · statements
                   debts · reports · projection · lessons · score · currency · landing · support
                   emails · placeholder
tests/Unit/        RepartoService · MethodRecommender · ThemeService · OnboardingService
                   PeriodoService · IngresoService · GastoService · MoneyTest
                   RecurrenceService · TagService · MethodService · CalendarService
                   GamificationService · WishlistService · Phase8Test · Phase9Test
                   Phase10Test · Phase11Test
```

## Seguridad

- `.env`, `vendor/`, `node_modules/` y los logs quedan fuera de git.
- El document root es `public/`; el código PHP y `.env` no son accesibles por HTTP.
- Contraseñas y respuestas con ARGON2ID; códigos de recuperación con SHA-256 y un solo uso.
- Cookies de sesión `HttpOnly`, `SameSite=Lax` y `Secure` en producción; `session.use_strict_mode=1`.
- Bloqueo por inactividad con PIN o contraseña; límite de intentos de login y de PIN.
- CSRF en todos los POST, incluido el autosave del onboarding (respuesta 419 si falta).
- El tema se valida otra vez en servidor: enums, formato `#RRGGBB` y contraste WCAG.
- El estado del onboarding solo acepta claves conocidas; el cliente no puede sobrescribir el JSON completo.
- Bloqueo de recuperación tras 3 fallos durante 15 minutos.
- Restablecer contraseña revoca todas las sesiones.
- Auditoría de todas las acciones administrativas, con snapshot de alias/email.
- Estados de cuenta solo por endpoints autenticados, con reautenticación y bloqueo de UA móvil.
- Exportación en streaming; el temporal se elimina al terminar.
- Documentos legales versionados y aceptación bloqueante para usuarios con onboarding completo.

## Logs

```bash
tail -f storage/logs/app.log
tail -f storage/logs/mail.log
```

## Solución de problemas

**❌ Access denied (1045)**  
Revisa `DB_USERNAME` y `DB_PASSWORD`. En MySQL de Ubuntu es común que `root` use `auth_socket`; crea un usuario dedicado:

```sql
CREATE DATABASE IF NOT EXISTS cauce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cauce'@'localhost' IDENTIFIED BY 'tu_password';
GRANT ALL PRIVILEGES ON cauce.* TO 'cauce'@'localhost';
FLUSH PRIVILEGES;
```

**❌ Base de datos no existe (1049)**  
Ejecuta `mysql -u root -p < sql/schema.sql`.

**No aparecen los iconos**  
Vuelve a compilar con `npx gulp icons` o `npx gulp build`.

**El tema no se aplica antes del render**  
Comprueba que `localStorage` no esté bloqueado. El servidor sigue guardando el tema y lo aplica en el HTML.

**El QR no se dibuja**  
Verifica que `npx gulp build` haya incluido `qrcode-generator` en `public/assets/js/app.min.js` y que `APP_SHARE_URL` no esté vacío.

**No se pueden subir fotos**  
Cauce necesita la extensión GD de PHP y permisos de escritura en `storage/uploads/gastos`. La imagen se comprime a JPEG por debajo de 1 MB.

**La búsqueda no encuentra términos de 1–2 letras**  
MySQL FULLTEXT ignora palabras cortas; Cauce usa `LIKE` como respaldo, pero el mínimo de InnoDB puede seguir afectando. Busca con 3 o más caracteres.

**`/admin` devuelve 403**  
El usuario no tiene rol `admin`. Ejecuta el `UPDATE` de “Primer admin”.

**Puerto 8000 ocupado**  
Usa `php -S localhost:8001 -t public public/router.php` o ajusta `BROWSER_SYNC_PROXY` y `BROWSER_SYNC_PORT`.

## Alcance actual

**Incluido:** landing pública, estructura, PDO, logging, 61 tablas, seed, registro, login/logout, recuperación, preguntas de seguridad, cierre global de sesiones, i18n, mailer nulo, roles/admin, auditoría, `RepartoService`, onboarding completo, layout responsive, temas, QR, placeholder Android, cálculo de período, ingresos, gastos manuales, excesos, recurrentes, conciliación, plantillas, tags, fotos, movimientos con filtros, inconsistencias, paneles de ahorro/inversión/donación, bolsas múltiples de ahorro, metas por bolsa, transferencias y saldos iniciales, portafolio de inversión por ticker con cotizaciones de Yahoo Finance y CoinGecko, cron de cotizaciones, fondo de emergencia, pausas de inversión, rebalanceo, versionado del método, movimientos entre categorías, notificaciones in-app y Web Push, modo vacaciones, resumen mensual, búsqueda global, calendario, racha, badges, niveles, retos personalizados, comparaciones, switch de gamificación, lista de deseos, PIN de desbloqueo, analytics propio con opt-out, exportación CSV/PDF/ZIP, borrado de cuenta, estados de cuenta solo en escritorio, panel de usuarios con metadatos, estadísticas agregadas anónimas, documentos legales versionados, deudas con estrategias y pagos, patrimonio neto con snapshots, metas con plazo y aporte sugerido, proyección de fin de período, análisis de suscripciones, búsqueda ampliada, lecciones contextuales bilingües, exportación `.ics`, score privado de salud financiera con snapshots, multi-moneda completa y bandeja de soporte interno.

**Excluido a propósito:** hogar, familia y Android nativo (fases posteriores).
