<?php

declare(strict_types=1);

use Cauce\Controllers\AboutController;
use Cauce\Controllers\AdminContentController;
use Cauce\Controllers\AdminSupportController;
use Cauce\Controllers\AdminCurrencyController;
use Cauce\Controllers\AdminController;
use Cauce\Controllers\AdminLegalController;
use Cauce\Controllers\AdminReportController;
use Cauce\Controllers\AdminUserController;
use Cauce\Controllers\ApiController;
use Cauce\Controllers\AppController;
use Cauce\Controllers\AuthController;
use Cauce\Controllers\CalendarController;
use Cauce\Controllers\CoverController;
use Cauce\Controllers\CurrencyController;
use Cauce\Controllers\DebtController;
use Cauce\Controllers\DonationController;
use Cauce\Controllers\ExpenseController;
use Cauce\Controllers\GamificationController;
use Cauce\Controllers\HomeController;
use Cauce\Controllers\IncomeController;
use Cauce\Controllers\InvestmentController;
use Cauce\Controllers\LanguageController;
use Cauce\Controllers\LegalController;
use Cauce\Controllers\LessonController;
use Cauce\Controllers\LockController;
use Cauce\Controllers\MoveController;
use Cauce\Controllers\MovementController;
use Cauce\Controllers\NotificationController;
use Cauce\Controllers\OnboardingController;
use Cauce\Controllers\PlaceholderController;
use Cauce\Controllers\PrivacyController;
use Cauce\Controllers\RecoveryController;
use Cauce\Controllers\RecurringController;
use Cauce\Controllers\RegistrationController;
use Cauce\Controllers\ReportController;
use Cauce\Controllers\SavingsController;
use Cauce\Controllers\SearchController;
use Cauce\Controllers\SettingsController;
use Cauce\Controllers\StatementController;
use Cauce\Controllers\SupportController;
use Cauce\Controllers\SubcategoryController;
use Cauce\Controllers\SummaryController;
use Cauce\Controllers\TagController;
use Cauce\Controllers\TemplateController;
use Cauce\Controllers\WishlistController;
use Cauce\Helpers\Router;
use Cauce\Http\Middleware\AdminMiddleware;
use Cauce\Http\Middleware\AnalyticsMiddleware;
use Cauce\Http\Middleware\AuthMiddleware;
use Cauce\Http\Middleware\DesktopOnlyMiddleware;
use Cauce\Http\Middleware\GamificationMiddleware;
use Cauce\Http\Middleware\LegalMiddleware;
use Cauce\Http\Middleware\LockMiddleware;
use Cauce\Http\Middleware\NotificationMiddleware;
use Cauce\Http\Middleware\OnboardingMiddleware;
use Cauce\Http\Middleware\RecurringExpenseMiddleware;

/**
 * Definición de rutas.
 *
 * @return Router
 */

$router = new Router();

// Públicas
$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/registro', [RegistrationController::class, 'showRegister']);
$router->post('/registro', [RegistrationController::class, 'register']);
$router->get('/recuperar', [RecoveryController::class, 'index']);
$router->get('/recuperar/preguntas', [RecoveryController::class, 'showQuestionsEmail']);
$router->post('/recuperar/preguntas', [RecoveryController::class, 'submitQuestionsEmail']);
$router->get('/recuperar/preguntas/responder', [RecoveryController::class, 'showQuestions']);
$router->post('/recuperar/preguntas/responder', [RecoveryController::class, 'verifyQuestions']);
$router->get('/recuperar/codigo', [RecoveryController::class, 'showCode']);
$router->post('/recuperar/codigo', [RecoveryController::class, 'verifyCode']);
$router->get('/recuperar/contacto', [RecoveryController::class, 'contact']);
$router->get('/recuperar/restablecer', [RecoveryController::class, 'showReset']);
$router->post('/recuperar/restablecer', [RecoveryController::class, 'reset']);
$router->get('/idioma', [LanguageController::class, 'switch']);
$router->get('/api/app/version', [ApiController::class, 'appVersion']);
$router->get('/privacidad', [LegalController::class, 'privacy']);
$router->get('/terminos', [LegalController::class, 'terms']);
$router->get('/aviso-legal', [LegalController::class, 'notice']);

// Autenticadas (sin exigir onboarding: ajustes y onboarding mismo)
$router->group('', static function (Router $routes): void {
    $routes->post('/logout', [AuthController::class, 'logout']);
    $routes->get('/desbloquear', [LockController::class, 'show']);
    $routes->post('/desbloquear', [LockController::class, 'unlock']);
    $routes->get('/ajustes/seguridad/cambiar-password', [LockController::class, 'changePasswordForm']);
    $routes->post('/ajustes/seguridad/cambiar-password', [LockController::class, 'changePassword']);
    $routes->get('/legal/aceptar', [LegalController::class, 'acceptForm']);
    $routes->post('/legal/aceptar', [LegalController::class, 'accept']);
    $routes->get('/exportar/descargar', [PrivacyController::class, 'download']);
    $routes->get('/registro/codigo', [RegistrationController::class, 'showCode']);
    $routes->post('/registro/codigo', [RegistrationController::class, 'continueAfterCode']);

    $routes->get('/onboarding', [OnboardingController::class, 'index']);
    $routes->get('/onboarding/tutorial', [OnboardingController::class, 'showTutorial']);
    $routes->post('/onboarding/tutorial', [OnboardingController::class, 'saveTutorial']);
    $routes->get('/onboarding/simulador', [OnboardingController::class, 'showSimulador']);
    $routes->post('/onboarding/simulador', [OnboardingController::class, 'saveSimulador']);
    $routes->get('/onboarding/metodo', [OnboardingController::class, 'showMetodo']);
    $routes->post('/onboarding/metodo', [OnboardingController::class, 'saveMetodo']);
    $routes->get('/onboarding/moneda', [OnboardingController::class, 'showMoneda']);
    $routes->post('/onboarding/moneda', [OnboardingController::class, 'saveMoneda']);
    $routes->get('/onboarding/tipo-ingreso', [OnboardingController::class, 'showTipoIngreso']);
    $routes->post('/onboarding/tipo-ingreso', [OnboardingController::class, 'saveTipoIngreso']);
    $routes->get('/onboarding/configuracion', [OnboardingController::class, 'showConfiguracion']);
    $routes->post('/onboarding/configuracion', [OnboardingController::class, 'saveConfiguracion']);
    $routes->get('/onboarding/recurrencia', [OnboardingController::class, 'showRecurrencia']);
    $routes->post('/onboarding/recurrencia', [OnboardingController::class, 'saveRecurrencia']);
    $routes->get('/onboarding/primer-ingreso', [OnboardingController::class, 'showPrimerIngreso']);
    $routes->post('/onboarding/primer-ingreso', [OnboardingController::class, 'savePrimerIngreso']);
    $routes->get('/onboarding/notificaciones', [OnboardingController::class, 'showNotificaciones']);
    $routes->post('/onboarding/notificaciones', [OnboardingController::class, 'finish']);

    $routes->post('/api/onboarding/estado', [ApiController::class, 'onboardingState']);

    $routes->get('/ajustes', [SettingsController::class, 'index']);
    $routes->get('/ajustes/perfil', [SettingsController::class, 'perfil']);
    $routes->post('/ajustes/perfil/gamificacion', [GamificationController::class, 'toggleGamification']);
    $routes->post('/ajustes/perfil/comparacion', [GamificationController::class, 'toggleSocial']);
    $routes->post('/ajustes/perfil/efectivo', [SettingsController::class, 'updateCash']);
    $routes->get('/acerca-de', [AboutController::class, 'index']);
    $routes->get('/soporte', [SupportController::class, 'index']);
    $routes->post('/soporte', [SupportController::class, 'store']);
    $routes->get('/soporte/ver', [SupportController::class, 'show']);
    $routes->post('/soporte/responder', [SupportController::class, 'reply']);
    $routes->get('/ajustes/monedas', [CurrencyController::class, 'index']);
    $routes->post('/ajustes/monedas/agregar', [CurrencyController::class, 'add']);
    $routes->post('/ajustes/monedas/quitar', [CurrencyController::class, 'remove']);
    $routes->post('/ajustes/monedas/principal', [CurrencyController::class, 'principal']);
    $routes->get('/ajustes/ingreso', [SettingsController::class, 'ingreso']);
    $routes->post('/ajustes/ingreso/recurrencia', [SettingsController::class, 'updateRecurrencia']);
    $routes->post('/ajustes/ingreso/extra', [SettingsController::class, 'updateExtra']);
    $routes->get('/ajustes/temas', [SettingsController::class, 'temas']);
    $routes->post('/ajustes/temas', [SettingsController::class, 'updateTemas']);
    $routes->get('/ajustes/compartir', [SettingsController::class, 'compartir']);
    $routes->get('/ajustes/android', [SettingsController::class, 'android']);
    $routes->get('/ajustes/seguridad', [SettingsController::class, 'security']);
    $routes->post('/ajustes/seguridad/preguntas', [SettingsController::class, 'updateQuestions']);
    $routes->post('/ajustes/seguridad/cerrar-sesiones', [SettingsController::class, 'closeSessions']);
    $routes->post('/ajustes/seguridad/pin', [SettingsController::class, 'setPin']);
    $routes->post('/ajustes/seguridad/pin/eliminar', [SettingsController::class, 'removePin']);

    $routes->get('/ingresos/nuevo', [IncomeController::class, 'create']);
    $routes->post('/ingresos/nuevo', [IncomeController::class, 'store']);
    $routes->get('/ingresos/acreditar', [IncomeController::class, 'acreditar']);
    $routes->post('/ingresos/acreditar', [IncomeController::class, 'finalizar']);

    $routes->get('/gastos/nuevo', [ExpenseController::class, 'create']);
    $routes->post('/gastos/nuevo', [ExpenseController::class, 'store']);
    $routes->get('/gastos/ver', [ExpenseController::class, 'show']);
    $routes->get('/gastos/editar', [ExpenseController::class, 'edit']);
    $routes->post('/gastos/editar', [ExpenseController::class, 'update']);
    $routes->post('/gastos/eliminar', [ExpenseController::class, 'destroy']);
    $routes->get('/gastos/foto', [ExpenseController::class, 'photo']);
    $routes->get('/gastos/conciliar', [ExpenseController::class, 'conciliar']);
    $routes->post('/gastos/conciliar/todos', [ExpenseController::class, 'conciliarAll']);
    $routes->post('/gastos/conciliar/uno', [ExpenseController::class, 'conciliarOne']);
    $routes->post('/gastos/confirmar', [ExpenseController::class, 'confirmRecurring']);
    $routes->post('/gastos/recurrente/detener', [ExpenseController::class, 'stopRecurring']);

    $routes->get('/ajustes/plantillas', [TemplateController::class, 'index']);
    $routes->post('/ajustes/plantillas', [TemplateController::class, 'store']);
    $routes->post('/ajustes/plantillas/eliminar', [TemplateController::class, 'delete']);
    $routes->post('/ajustes/plantillas/desde-gasto', [TemplateController::class, 'fromGasto']);

    $routes->get('/ajustes/tags', [TagController::class, 'index']);
    $routes->get('/tags', [TagController::class, 'index']);
    $routes->get('/subcategorias', [SubcategoryController::class, 'index']);
    $routes->post('/subcategorias', [SubcategoryController::class, 'store']);
    $routes->post('/subcategorias/actualizar', [SubcategoryController::class, 'update']);
    $routes->post('/subcategorias/eliminar', [SubcategoryController::class, 'delete']);
    $routes->post('/ajustes/tags', [TagController::class, 'store']);
    $routes->post('/ajustes/tags/renombrar', [TagController::class, 'rename']);
    $routes->post('/ajustes/tags/fusionar', [TagController::class, 'merge']);
    $routes->post('/ajustes/tags/eliminar', [TagController::class, 'delete']);

    $routes->get('/ajustes/recurrentes', [RecurringController::class, 'index']);
    $routes->post('/ajustes/recurrentes', [RecurringController::class, 'store']);
    $routes->post('/ajustes/recurrentes/actualizar', [RecurringController::class, 'update']);
    $routes->post('/ajustes/recurrentes/desactivar', [RecurringController::class, 'deactivate']);

    // Notificaciones: bandeja, ajustes y Web Push
    $routes->get('/notificaciones', [NotificationController::class, 'index']);
    $routes->post('/notificaciones/leer', [NotificationController::class, 'markRead']);
    $routes->post('/notificaciones/push', [NotificationController::class, 'subscribe']);
    $routes->post('/notificaciones/push/eliminar', [NotificationController::class, 'unsubscribe']);
    $routes->get('/ajustes/notificaciones', [NotificationController::class, 'settings']);
    $routes->post('/ajustes/notificaciones', [NotificationController::class, 'updateConfig']);
    $routes->post('/ajustes/notificaciones/tono', [NotificationController::class, 'updateTone']);
    $routes->post('/ajustes/notificaciones/vacaciones', [NotificationController::class, 'vacation']);
    $routes->get('/ajustes/notificaciones/calendario.ics', [CalendarController::class, 'exportIcs']);

    // Lecciones contextuales
    $routes->post('/lecciones/vista', [LessonController::class, 'markSeen']);

    $routes->get('/resumen', [SummaryController::class, 'index']);
    $routes->post('/resumen/reflexion', [SummaryController::class, 'reflection']);

    $routes->get('/buscar', [SearchController::class, 'index']);
    $routes->get('/calendario', [CalendarController::class, 'index']);
    $routes->get('/mover', [MoveController::class, 'index']);
    $routes->post('/mover', [MoveController::class, 'store']);
    $routes->get('/categorias/ver', [MoveController::class, 'category']);

    // Placeholders accesibles también durante el onboarding (p. ej. primer ingreso)
    foreach ((array) config('navigation.placeholders', []) as $path => $meta) {
        if (is_array($meta) && empty($meta['admin'])) {
            $routes->get((string) $path, [PlaceholderController::class, 'show']);
        }
    }
}, [AuthMiddleware::class, LockMiddleware::class, LegalMiddleware::class, RecurringExpenseMiddleware::class, NotificationMiddleware::class, GamificationMiddleware::class, AnalyticsMiddleware::class]);

// App autenticada (exige onboarding completado)
$router->group('', static function (Router $routes): void {
    $routes->get('/inicio', [AppController::class, 'inicio']);
    $routes->get('/movimientos', [MovementController::class, 'index']);
    $routes->get('/reportes', [ReportController::class, 'index']);
    $routes->get('/reportes/suscripciones', [ReportController::class, 'subscriptions']);
    $routes->post('/reportes/efectivo', [ReportController::class, 'updateCash']);

    $routes->get('/ahorro', [SavingsController::class, 'index']);
    $routes->post('/ahorro/cuentas', [SavingsController::class, 'storeAccount']);
    $routes->post('/ahorro/cuentas/editar', [SavingsController::class, 'updateAccount']);
    $routes->post('/ahorro/cuentas/archivar', [SavingsController::class, 'archiveAccount']);
    $routes->post('/ahorro/transferir', [SavingsController::class, 'transfer']);
    $routes->post('/ahorro/asignar', [SavingsController::class, 'assignMeta']);
    $routes->post('/ahorro/metas/mover', [SavingsController::class, 'moveMeta']);
    $routes->post('/ahorro/metas', [SavingsController::class, 'storeMeta']);
    $routes->post('/ahorro/metas/editar', [SavingsController::class, 'updateMeta']);
    $routes->post('/ahorro/metas/desactivar', [SavingsController::class, 'deactivateMeta']);
    $routes->post('/ahorro/aportar', [SavingsController::class, 'contribute']);
    $routes->post('/ahorro/retirar', [SavingsController::class, 'withdraw']);
    $routes->post('/ahorro/aportaciones/editar', [SavingsController::class, 'updateContribution']);
    $routes->post('/ahorro/aportaciones/eliminar', [SavingsController::class, 'deleteContribution']);
    $routes->post('/ahorro/retiros/editar', [SavingsController::class, 'updateWithdrawal']);
    $routes->post('/ahorro/retiros/eliminar', [SavingsController::class, 'deleteWithdrawal']);
    $routes->post('/ahorro/emergencia', [SavingsController::class, 'updateEmergency']);

    $routes->get('/inversion', [InvestmentController::class, 'index']);
    $routes->post('/inversion/aportar', [InvestmentController::class, 'contribute']);
    $routes->post('/inversion/retirar', [InvestmentController::class, 'withdraw']);
    $routes->post('/inversion/pausar', [InvestmentController::class, 'pause']);
    $routes->get('/inversion/tipos', [InvestmentController::class, 'instruments']);
    $routes->post('/inversion/tipos', [InvestmentController::class, 'storeInstrument']);
    $routes->post('/inversion/tipos/editar', [InvestmentController::class, 'updateInstrument']);
    $routes->post('/inversion/tipos/eliminar', [InvestmentController::class, 'deleteInstrument']);
    $routes->post('/inversion/porcentaje', [InvestmentController::class, 'adjustPercentage']);
    $routes->post('/inversion/posiciones', [InvestmentController::class, 'storePosition']);
    $routes->post('/inversion/posiciones/editar', [InvestmentController::class, 'updatePosition']);
    $routes->post('/inversion/posiciones/valor', [InvestmentController::class, 'updatePositionValue']);
    $routes->post('/inversion/posiciones/eliminar', [InvestmentController::class, 'deletePosition']);
    $routes->post('/inversion/cotizaciones/actualizar', [InvestmentController::class, 'refreshQuotes']);
    $routes->post('/inversion/aportaciones/editar', [InvestmentController::class, 'updateContribution']);
    $routes->post('/inversion/aportaciones/eliminar', [InvestmentController::class, 'deleteContribution']);
    $routes->post('/inversion/retiros/editar', [InvestmentController::class, 'updateWithdrawal']);
    $routes->post('/inversion/retiros/eliminar', [InvestmentController::class, 'deleteWithdrawal']);

    $routes->get('/donacion', [DonationController::class, 'index']);
    $routes->post('/donacion/donar', [DonationController::class, 'donate']);
    $routes->post('/donacion/activar', [DonationController::class, 'activate']);
    $routes->post('/donacion/editar', [DonationController::class, 'updateDonation']);
    $routes->post('/donacion/eliminar', [DonationController::class, 'deleteDonation']);

    // Deudas
    $routes->get('/deudas', [DebtController::class, 'index']);
    $routes->post('/deudas', [DebtController::class, 'store']);
    $routes->post('/deudas/actualizar', [DebtController::class, 'update']);
    $routes->post('/deudas/eliminar', [DebtController::class, 'delete']);
    $routes->post('/deudas/estrategia', [DebtController::class, 'strategy']);
    $routes->post('/deudas/pagar', [DebtController::class, 'pay']);
    $routes->post('/deudas/recordatorio', [DebtController::class, 'reminder']);

    // Privacidad
    $routes->get('/ajustes/privacidad', [PrivacyController::class, 'index']);
    $routes->post('/ajustes/privacidad/exportar', [PrivacyController::class, 'export']);
    $routes->post('/ajustes/privacidad/analytics', [PrivacyController::class, 'updateAnalytics']);
    $routes->post('/ajustes/privacidad/eliminar', [PrivacyController::class, 'deleteAccount']);

    // Estado de cuenta (solo escritorio: UI + backend)
    $routes->get('/estado-cuenta', [StatementController::class, 'index'], [DesktopOnlyMiddleware::class]);
    $routes->post('/estado-cuenta/consentimiento', [StatementController::class, 'acceptConsent'], [DesktopOnlyMiddleware::class]);
    $routes->post('/estado-cuenta/autenticar', [StatementController::class, 'authenticate'], [DesktopOnlyMiddleware::class]);
    $routes->post('/estado-cuenta/subir', [StatementController::class, 'upload'], [DesktopOnlyMiddleware::class]);
    $routes->get('/estado-cuenta/ver', [StatementController::class, 'view'], [DesktopOnlyMiddleware::class]);
    $routes->post('/estado-cuenta/eliminar', [StatementController::class, 'delete'], [DesktopOnlyMiddleware::class]);

    // Progreso, celebraciones y lista de deseos
    $routes->get('/logros', [GamificationController::class, 'index']);
    $routes->post('/logros/celebracion', [GamificationController::class, 'celebrate']);
    $routes->post('/logros/reto/descartar', [GamificationController::class, 'dismissChallenge']);

    $routes->get('/deseos', [WishlistController::class, 'index']);
    $routes->post('/deseos', [WishlistController::class, 'store']);
    $routes->post('/deseos/actualizar', [WishlistController::class, 'update']);
    $routes->post('/deseos/comprar', [WishlistController::class, 'purchase']);
    $routes->post('/deseos/descartar', [WishlistController::class, 'discard']);
    $routes->post('/deseos/abonar', [WishlistController::class, 'contribute']);

    // Portadas de tarjetas (deseos, ahorro, deudas e inversión)
    $routes->post('/portadas', [CoverController::class, 'update']);
    $routes->get('/portadas/ver', [CoverController::class, 'show']);
}, [AuthMiddleware::class, LockMiddleware::class, LegalMiddleware::class, OnboardingMiddleware::class, RecurringExpenseMiddleware::class, NotificationMiddleware::class, GamificationMiddleware::class, AnalyticsMiddleware::class]);

// Administración
$router->group('/admin', static function (Router $routes): void {
    $routes->get('', [AdminController::class, 'index']);
    $routes->get('/usuarios', [AdminUserController::class, 'index']);
    $routes->get('/usuarios/ver', [AdminUserController::class, 'show']);
    $routes->get('/usuarios/crear', [AdminController::class, 'createUserForm']);
    $routes->post('/usuarios', [AdminController::class, 'storeUser']);
    $routes->post('/usuarios/suspender', [AdminUserController::class, 'suspend']);
    $routes->post('/usuarios/reactivar', [AdminUserController::class, 'reactivate']);
    $routes->post('/usuarios/cerrar-sesiones', [AdminUserController::class, 'forceLogout']);
    $routes->post('/usuarios/restablecer', [AdminUserController::class, 'resetPassword']);
    $routes->post('/usuarios/cambiar-email', [AdminUserController::class, 'changeEmail']);
    $routes->post('/usuarios/exportar', [AdminUserController::class, 'exportLink']);
    $routes->post('/usuarios/eliminar', [AdminUserController::class, 'delete']);
    $routes->get('/reportes', [AdminReportController::class, 'index']);
    $routes->post('/reportes/refrescar', [AdminReportController::class, 'refresh']);
    $routes->get('/legal', [AdminLegalController::class, 'index']);
    $routes->post('/legal/publicar', [AdminLegalController::class, 'publish']);
    $routes->get('/soporte', [AdminSupportController::class, 'index']);
    $routes->get('/soporte/ver', [AdminSupportController::class, 'show']);
    $routes->post('/soporte/responder', [AdminSupportController::class, 'reply']);
    $routes->post('/soporte/estado', [AdminSupportController::class, 'status']);
    $routes->get('/monedas', [AdminCurrencyController::class, 'index']);
    $routes->post('/monedas/guardar', [AdminCurrencyController::class, 'save']);
    $routes->post('/monedas/eliminar', [AdminCurrencyController::class, 'delete']);
    $routes->post('/monedas/recalcular', [AdminCurrencyController::class, 'recalculate']);
    $routes->get('/contenido', [AdminContentController::class, 'index']);
    $routes->post('/contenido/guardar', [AdminContentController::class, 'save']);
    $routes->post('/contenido/publicar', [AdminContentController::class, 'publish']);
    $routes->get('/auditoria', [AdminController::class, 'audit']);

    foreach ((array) config('navigation.placeholders', []) as $path => $meta) {
        if (is_array($meta) && !empty($meta['admin'])) {
            $routes->get(substr((string) $path, strlen('/admin')), [PlaceholderController::class, 'show']);
        }
    }
}, [AdminMiddleware::class, LockMiddleware::class, LegalMiddleware::class]);

return $router;
