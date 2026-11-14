<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Helpers\Client;
use Cauce\Services\AnalyticsService;
use Cauce\Services\Export\PdfWriter;
use Cauce\Services\LegalService;
use Cauce\Services\PinService;
use PHPUnit\Framework\TestCase;

final class Phase8Test extends TestCase
{
    public function test_generador_pdf_produce_un_pdf_valido(): void
    {
        $pdf = new PdfWriter();
        $pdf->title('Cauce · resumen');
        $pdf->text('Ahorro, inversión y donación con acentos: áéíóú ñ.');
        $pdf->table(['Concepto', 'Monto'], [['Ahorro', '$1.000,00']], [260, 150]);

        $output = $pdf->output();

        self::assertStringStartsWith('%PDF-1.4', $output);
        self::assertStringContainsString('%%EOF', $output);
        self::assertStringContainsString('/Type /Catalog', $output);
    }

    public function test_deteccion_de_movil_por_user_agent(): void
    {
        $original = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';
        self::assertTrue(Client::isMobile());
        self::assertFalse(Client::isDesktop());

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120 Safari/537.36';
        self::assertFalse(Client::isMobile());
        self::assertTrue(Client::isDesktop());

        if ($original === null) {
            unset($_SERVER['HTTP_USER_AGENT']);
        } else {
            $_SERVER['HTTP_USER_AGENT'] = $original;
        }
    }

    public function test_funcion_de_analytics_por_ruta(): void
    {
        self::assertSame('calendario', AnalyticsService::featureForPath('/calendario'));
        self::assertSame('metas', AnalyticsService::featureForPath('/ahorro'));
        self::assertSame('metas', AnalyticsService::featureForPath('/ahorro/metas'));
        self::assertSame('deseos', AnalyticsService::featureForPath('/deseos'));
        self::assertSame('estado_cuenta', AnalyticsService::featureForPath('/estado-cuenta'));
        self::assertNull(AnalyticsService::featureForPath('/inicio'));
    }

    public function test_formato_de_tamano(): void
    {
        self::assertSame('512 B', format_bytes(512));
        self::assertSame('1,0 KB', format_bytes(1024));
        self::assertSame('10,0 MB', format_bytes(10485760));
    }

    public function test_titulo_y_contenido_legal_por_idioma(): void
    {
        $document = [
            'titulo_es' => 'Privacidad',
            'titulo_en' => 'Privacy',
            'contenido_es' => 'Contenido ES',
            'contenido_en' => 'Content EN',
        ];

        self::assertSame('Privacidad', LegalService::title($document, 'es'));
        self::assertSame('Privacy', LegalService::title($document, 'en'));
        self::assertSame('Contenido ES', LegalService::content($document, 'es'));
        self::assertSame('Content EN', LegalService::content($document, 'en'));
    }

    public function test_minutos_restantes_de_bloqueo_pin(): void
    {
        $until = gmdate('Y-m-d H:i:s', time() + 300);

        self::assertGreaterThanOrEqual(1, PinService::remainingLockMinutes($until));
        self::assertLessThanOrEqual(5, PinService::remainingLockMinutes($until));
    }
}
