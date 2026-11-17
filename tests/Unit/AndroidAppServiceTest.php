<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\AndroidAppService;
use PHPUnit\Framework\TestCase;

final class AndroidAppServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_COOKIE[AndroidAppService::COOKIE]);
    }

    public function test_version_code_como_tauri(): void
    {
        self::assertSame(1000000, AndroidAppService::versionCode('1.0.0'));
        self::assertSame(2013004, AndroidAppService::versionCode('2.13.4'));
        self::assertSame(0, AndroidAppService::versionCode('beta'));
        self::assertGreaterThan(AndroidAppService::versionCode('1.9.99'), AndroidAppService::versionCode('1.10.0'));
    }

    public function test_desactualizada_compara_versiones(): void
    {
        $latest = ['version' => '1.2.0'];

        self::assertTrue(AndroidAppService::isOutdated('1.1.9', $latest));
        self::assertFalse(AndroidAppService::isOutdated('1.2.0', $latest));
        self::assertFalse(AndroidAppService::isOutdated('1.10.0', $latest));
        self::assertFalse(AndroidAppService::isOutdated('1.0.0', null));
    }

    public function test_cookie_de_la_app(): void
    {
        $_COOKIE[AndroidAppService::COOKIE] = '1.2.3|0b6f6d3e-1111-4222-8333-444455556666';
        self::assertSame(
            ['version' => '1.2.3', 'instalacion' => '0b6f6d3e-1111-4222-8333-444455556666'],
            AndroidAppService::fromApp()
        );

        $_COOKIE[AndroidAppService::COOKIE] = '1.2.3|<script>';
        self::assertNull(AndroidAppService::fromApp());

        $_COOKIE[AndroidAppService::COOKIE] = ['1.2.3'];
        self::assertNull(AndroidAppService::fromApp());
    }

    public function test_modelo_desde_el_agente_del_webview(): void
    {
        $agent = 'Mozilla/5.0 (Linux; Android 15; SM-A556E Build/AP3A.240905.015; wv) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36';
        self::assertSame(['modelo' => 'SM-A556E', 'android' => '15'], AndroidAppService::deviceFromUserAgent($agent));

        $reducido = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36';
        self::assertSame(['modelo' => null, 'android' => '10'], AndroidAppService::deviceFromUserAgent($reducido));

        self::assertSame(['modelo' => null, 'android' => null], AndroidAppService::deviceFromUserAgent('curl/8.0'));
    }
}
