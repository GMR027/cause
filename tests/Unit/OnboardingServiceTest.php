<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\OnboardingService;
use PHPUnit\Framework\TestCase;

final class OnboardingServiceTest extends TestCase
{
    private OnboardingService $service;

    protected function setUp(): void
    {
        $this->service = new OnboardingService();
    }

    public function test_default_state_uses_configured_amount(): void
    {
        $state = $this->service->state([]);

        self::assertSame('tutorial', $state['paso']);
        self::assertSame(1000000, $state['monto_centavos']);
        self::assertFalse($state['tutorial_visto']);
    }

    public function test_state_restores_saved_progress(): void
    {
        $state = $this->service->state([
            'onboarding_estado' => json_encode([
                'paso' => 'simulador',
                'monto_centavos' => 2500000,
                'metodo_preview' => 'seis_sobres',
            ]),
        ]);

        self::assertSame('simulador', $state['paso']);
        self::assertSame(2500000, $state['monto_centavos']);
        self::assertSame('seis_sobres', $state['metodo_preview']);
    }

    public function test_merge_ignores_unknown_keys(): void
    {
        $state = $this->service->state([]);
        $next = $this->service->merge($state, [
            'metodo' => '50_30_20',
            'hacked' => 'valor',
        ]);

        self::assertArrayNotHasKey('hacked', $next);
        self::assertSame('50_30_20', $next['metodo']);
    }

    public function test_merge_clamps_amount(): void
    {
        $state = $this->service->state([]);

        self::assertSame(0, $this->service->merge($state, ['monto_centavos' => -500])['monto_centavos']);
        self::assertSame(
            (int) config('onboarding.max_income_cents'),
            $this->service->merge($state, ['monto_centavos' => 999999999999999])['monto_centavos']
        );
    }

    public function test_merge_keeps_only_known_quiz_keys(): void
    {
        $state = $this->service->state([]);
        $next = $this->service->merge($state, [
            'quiz' => ['p1' => 'fijo', 'p2' => 'si', 'p3' => 'ahorrar', 'extra' => 'x'],
        ]);

        self::assertSame(['p1' => 'fijo', 'p2' => 'si', 'p3' => 'ahorrar'], $next['quiz']);
    }

    public function test_quick_setup_skips_recurrence(): void
    {
        self::assertSame(
            'primer-ingreso',
            $this->service->nextStep('configuracion', ['configuracion' => 'rapida'])
        );
    }

    public function test_full_setup_goes_to_recurrence(): void
    {
        self::assertSame(
            'recurrencia',
            $this->service->nextStep('configuracion', ['configuracion' => 'completa'])
        );
    }

    public function test_notifications_is_the_last_step(): void
    {
        self::assertSame('completado', $this->service->nextStep('notificaciones', []));
    }

    public function test_unknown_step_falls_back_to_tutorial(): void
    {
        self::assertSame('tutorial', $this->service->nextStep('desconocido', []));
    }

    public function test_step_route_for_completed(): void
    {
        self::assertSame('/inicio', $this->service->stepRoute('completado'));
        self::assertSame('/onboarding/metodo', $this->service->stepRoute('metodo'));
    }

    public function test_is_complete(): void
    {
        self::assertTrue($this->service->isComplete(['onboarding_completado' => 1]));
        self::assertFalse($this->service->isComplete(['onboarding_completado' => 0]));
    }
}
