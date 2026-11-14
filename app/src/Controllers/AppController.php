<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\View;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\MetodoRepository;
use Cauce\Repositories\NoControladoRepository;
use Cauce\Repositories\UsuarioMetodoRepository;
use Cauce\Services\AuthService;
use Cauce\Services\FinanceSummaryService;
use Cauce\Services\GamificationService;
use Cauce\Services\GastoService;
use Cauce\Services\InsightService;
use Cauce\Services\OnboardingService;
use Cauce\Services\PeriodoService;
use Cauce\Services\ProjectionService;
use Cauce\Services\RepartoService;
use Cauce\Services\ScoreService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Pantallas base de la aplicación autenticada.
 */
final class AppController
{
    public function inicio(): void
    {
        $user = $this->user();
        $timezone = $this->timezone($user);
        $now = new DateTimeImmutable('now', $timezone);
        $hour = (int) $now->format('G');

        $greeting = match (true) {
            $hour < 12 => 'onboarding.greetings.morning',
            $hour < 19 => 'onboarding.greetings.afternoon',
            default => 'onboarding.greetings.evening',
        };

        $active = (new UsuarioMetodoRepository())->activeForUser((int) $user['id']);
        $methodName = null;
        $methodCode = null;
        $percentages = [];

        if ($active !== null) {
            $method = (new MetodoRepository())->findById((int) $active['metodo_id']);
            $methodName = $method['nombre'] ?? null;
            $methodCode = $method['codigo'] ?? null;
            $decoded = json_decode((string) ($active['porcentajes_json'] ?? ''), true);
            $percentages = is_array($decoded) ? $decoded : [];
        }

        $period = (new PeriodoService())->calcularPeriodo($user);
        $periodStart = $period['inicio']->format('Y-m-d H:i:s');
        $periodEnd = $period['fin']->format('Y-m-d H:i:s');
        $noControladoTotal = (new NoControladoRepository())->totalForPeriod(
            (int) $user['id'],
            $periodStart,
            $periodEnd
        );
        $pendingIncome = (new IngresoRepository())->pendingForUser((int) $user['id']);
        $gastosRepo = new GastoRepository();
        $pendingGastos = $gastosRepo->pendingForUser((int) $user['id']);
        $recurringConfirm = $gastosRepo->unconfirmedRecurring(
            (int) $user['id'],
            $periodStart,
            $periodEnd
        );
        $insights = (new InsightService())->forUser($user);
        $incomeTotal = (new IngresoRepository())->sumForPeriod((int) $user['id'], $periodStart, $periodEnd);
        $expenseTotal = $gastosRepo->sumByPeriod((int) $user['id'], $periodStart, $periodEnd);

        // Montos que representa cada categoría del método. Se calcula sobre el
        // ingreso del período; si aún no hay ingresos, sobre el ingreso
        // estimado que la persona capturó en el onboarding.
        $methodBase = $incomeTotal;
        $methodBaseSource = 'period';

        if ($methodBase <= 0) {
            $methodBase = (int) ((new OnboardingService())->state($user)['monto_centavos'] ?? 0);
            $methodBaseSource = 'estimated';
        }

        $methodAmounts = [];

        if ($percentages !== [] && $methodBase > 0) {
            try {
                $methodAmounts = (new RepartoService())->repartirIngreso($methodBase, $percentages);
            } catch (Throwable) {
                $methodAmounts = [];
            }
        }

        $availableMap = (new GastoService())->disponiblePorParte($user);
        $availableTotal = array_sum(array_column($availableMap, 'disponible'));
        $finance = (new FinanceSummaryService())->forUser($user);
        $progress = (new GamificationService())->homeCard($user);
        $projection = (new ProjectionService())->forUser($user);
        $score = (new ScoreService())->panel($user);

        echo View::render('app/inicio', [
            'title' => __('nav.inicio'),
            'greeting' => __($greeting),
            'now' => $now,
            'onboardingPending' => (int) ($user['onboarding_completado'] ?? 0) === 0,
            'methodName' => $methodName,
            'methodCode' => $methodCode,
            'percentages' => $percentages,
            'methodAmounts' => $methodAmounts,
            'methodBase' => $methodBase,
            'methodBaseSource' => $methodBaseSource,
            'period' => $period,
            'noControladoTotal' => $noControladoTotal,
            'pendingIncome' => $pendingIncome,
            'pendingGastos' => $pendingGastos,
            'recurringConfirm' => $recurringConfirm,
            'insights' => $insights,
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'availableTotal' => $availableTotal,
            'finance' => $finance,
            'progress' => $progress,
            'projection' => $projection,
            'score' => $score,
        ], 'layouts/app');
    }

    public function reportes(): void
    {
        echo View::render('app/reportes', [
            'title' => __('nav.reportes'),
        ], 'layouts/app');
    }

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function timezone(array $user): DateTimeZone
    {
        $name = (string) ($user['zona_horaria'] ?? '');

        try {
            return new DateTimeZone($name !== '' ? $name : (string) config('app.timezone', 'UTC'));
        } catch (\Exception) {
            return new DateTimeZone('UTC');
        }
    }
}
