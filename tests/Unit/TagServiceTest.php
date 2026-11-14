<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\TagService;
use PHPUnit\Framework\TestCase;

final class TagServiceTest extends TestCase
{
    private TagService $service;

    protected function setUp(): void
    {
        $this->service = new TagService();
    }

    public function test_normaliza_a_minusculas_y_sin_acentos(): void
    {
        self::assertSame('cafe con leche', $this->service->normalize('  Café con Leche '));
        self::assertSame('nandu', $this->service->normalize('Ñandú'));
    }

    public function test_limita_a_cinco_tags(): void
    {
        $tags = $this->service->parseInput('uno, dos, tres, cuatro, cinco, seis');

        self::assertCount(5, $tags);
        self::assertSame(['uno', 'dos', 'tres', 'cuatro', 'cinco'], $tags);
    }

    public function test_elimina_duplicados(): void
    {
        $tags = $this->service->parseInput(['Café', 'cafe', 'CAFÉ', 'trabajo']);

        self::assertSame(['cafe', 'trabajo'], $tags);
    }

    public function test_ignora_vacios(): void
    {
        self::assertSame(['uno', 'dos'], $this->service->parseInput('uno,, ,dos'));
    }
}
