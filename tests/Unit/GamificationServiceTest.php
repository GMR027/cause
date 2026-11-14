<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\GamificationService;
use PHPUnit\Framework\TestCase;

final class GamificationServiceTest extends TestCase
{
    public function test_racha_activa_avanza_con_ingreso(): void
    {
        $result = GamificationService::streakTransition('activa', 4, true, true, 4);

        self::assertSame('activa', $result['estado']);
        self::assertSame(5, $result['periodos_consecutivos']);
        self::assertSame(5, $result['mejor_racha']);
    }

    public function test_racha_en_pausa_continua_donde_estaba(): void
    {
        $result = GamificationService::streakTransition('pausa', 4, true, true, 4);

        self::assertSame('activa', $result['estado']);
        self::assertSame(5, $result['periodos_consecutivos']);
    }

    public function test_racha_rota_reinicia_en_uno(): void
    {
        $result = GamificationService::streakTransition('rota', 0, true, true, 8);

        self::assertSame('activa', $result['estado']);
        self::assertSame(1, $result['periodos_consecutivos']);
        self::assertSame(8, $result['mejor_racha']);
    }

    public function test_racha_activa_pasa_a_pausa_si_el_periodo_cierra_sin_ingreso(): void
    {
        $result = GamificationService::streakTransition('activa', 4, false, true, 4);

        self::assertSame('pausa', $result['estado']);
        self::assertSame(4, $result['periodos_consecutivos']);
    }

    public function test_sin_racha_previa_no_entra_en_pausa(): void
    {
        $result = GamificationService::streakTransition('activa', 0, false, true, 0);

        self::assertSame('activa', $result['estado']);
        self::assertSame(0, $result['periodos_consecutivos']);
    }

    public function test_racha_en_pausa_se_rompe_con_otro_periodo_sin_ingreso(): void
    {
        $result = GamificationService::streakTransition('pausa', 4, false, true, 4);

        self::assertSame('rota', $result['estado']);
        self::assertSame(0, $result['periodos_consecutivos']);
        self::assertSame(4, $result['mejor_racha']);
    }

    public function test_periodo_abierto_sin_ingreso_no_penaliza(): void
    {
        $result = GamificationService::streakTransition('activa', 4, false, false, 4);

        self::assertSame('activa', $result['estado']);
        self::assertSame(4, $result['periodos_consecutivos']);
    }

    public function test_niveles_por_porcentaje(): void
    {
        self::assertSame(1, GamificationService::levelForPercent(0));
        self::assertSame(1, GamificationService::levelForPercent(49.9));
        self::assertSame(2, GamificationService::levelForPercent(50));
        self::assertSame(2, GamificationService::levelForPercent(69.9));
        self::assertSame(3, GamificationService::levelForPercent(70));
        self::assertSame(3, GamificationService::levelForPercent(89.9));
        self::assertSame(4, GamificationService::levelForPercent(90));
        self::assertSame(4, GamificationService::levelForPercent(100));
    }

    public function test_siguiente_nivel_indica_lo_que_falta(): void
    {
        $next = GamificationService::nextLevel(1, 40);

        self::assertSame(2, $next['level']);
        self::assertSame(10.0, $next['missing']);
        self::assertSame('gamification.levels.names.2', $next['key']);

        self::assertNull(GamificationService::nextLevel(4, 95));
    }

    public function test_cumplimiento_controlado(): void
    {
        self::assertSame(1.0, GamificationService::compliance(true, 100, 80));
        self::assertEqualsWithDelta(0.8333, GamificationService::compliance(true, 100, 120), 0.001);
        self::assertSame(0.5, GamificationService::compliance(true, 100, 200));
    }

    public function test_cumplimiento_por_tipo(): void
    {
        self::assertSame(0.5, GamificationService::compliance(false, 100, 50));
        self::assertSame(1.0, GamificationService::compliance(false, 100, 150));
        self::assertSame(0.0, GamificationService::compliance(false, 0, 150));
    }

    public function test_reto_50_30_20(): void
    {
        $result = GamificationService::challengeCandidate([
            ['nombre' => 'Necesidades', 'porcentaje' => 50, 'tipo' => 'controlada'],
            ['nombre' => 'Deseos', 'porcentaje' => 30, 'tipo' => 'controlada'],
            ['nombre' => 'Ahorro', 'porcentaje' => 20, 'tipo' => 'ahorro'],
            ['nombre' => 'Donación', 'porcentaje' => null, 'tipo' => 'donacion'],
        ]);

        self::assertSame('cumple_ahorro', $result['tipo']);
        self::assertSame(20.0, $result['objetivo']);
    }

    public function test_reto_seis_sobres_suma_por_tipo(): void
    {
        $result = GamificationService::challengeCandidate([
            ['nombre' => 'Necesidades', 'porcentaje' => 55, 'tipo' => 'controlada'],
            ['nombre' => 'Ahorro LP', 'porcentaje' => 10, 'tipo' => 'ahorro'],
            ['nombre' => 'Educación', 'porcentaje' => 10, 'tipo' => 'ahorro'],
            ['nombre' => 'Inversión', 'porcentaje' => 10, 'tipo' => 'inversion'],
            ['nombre' => 'Donación', 'porcentaje' => 5, 'tipo' => 'donacion'],
        ]);

        self::assertSame('cumple_ahorro', $result['tipo']);
        self::assertSame(20.0, $result['objetivo']);
    }

    public function test_reto_cinco_frascos_prioriza_inversion_sobre_donacion(): void
    {
        $result = GamificationService::challengeCandidate([
            ['nombre' => 'Consumo', 'porcentaje' => 50, 'tipo' => 'controlada'],
            ['nombre' => 'Inversión', 'porcentaje' => 20, 'tipo' => 'inversion'],
            ['nombre' => 'Ahorro', 'porcentaje' => 10, 'tipo' => 'ahorro'],
            ['nombre' => 'Donación', 'porcentaje' => 10, 'tipo' => 'donacion'],
            ['nombre' => 'Compartir', 'porcentaje' => 10, 'tipo' => 'donacion'],
        ]);

        self::assertSame('cumple_inversion', $result['tipo']);
        self::assertSame(20.0, $result['objetivo']);
    }

    public function test_reto_kakebo_usa_registro(): void
    {
        $result = GamificationService::challengeCandidate([
            ['nombre' => 'Necesidades', 'porcentaje' => null, 'tipo' => 'controlada'],
            ['nombre' => 'Ocio', 'porcentaje' => null, 'tipo' => 'controlada'],
        ]);

        self::assertSame('registra_todo', $result['tipo']);
        self::assertNull($result['objetivo']);
    }

    public function test_comparacion_con_yo_pasado(): void
    {
        $up = GamificationService::compareNet(150, 100);
        self::assertSame('up', $up['direction']);
        self::assertSame(50.0, $up['percent']);
        self::assertTrue($up['comparable']);

        $down = GamificationService::compareNet(50, 100);
        self::assertSame('down', $down['direction']);
        self::assertSame(-50.0, $down['percent']);

        $noBase = GamificationService::compareNet(100, 0);
        self::assertSame('up', $noBase['direction']);
        self::assertNull($noBase['percent']);
        self::assertFalse($noBase['comparable']);

        $same = GamificationService::compareNet(0, 0);
        self::assertSame('same', $same['direction']);
        self::assertFalse($same['comparable']);
    }

    public function test_detalle_json_se_decodifica(): void
    {
        self::assertSame([], GamificationService::decodeDetail(null));
        self::assertSame(['a' => 1], GamificationService::decodeDetail('{"a":1}'));
        self::assertSame(['a' => 1], GamificationService::decodeDetail(['a' => 1]));
    }
}
