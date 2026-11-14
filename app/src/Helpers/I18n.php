<?php

declare(strict_types=1);

namespace Cauce\Helpers;

/**
 * Sistema de idiomas de Cauce.
 *
 * Resuelve el idioma actual (usuario → cookie → Accept-Language → es), carga
 * diccionarios desde lang/{lang}/*.php y aplica fallback a español.
 */
final class I18n
{
    public const SUPPORTED = ['es', 'en'];
    public const FALLBACK = 'es';
    public const COOKIE = 'app_lang';

    private static string $lang = self::FALLBACK;

    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    private function __construct()
    {
    }

    public static function boot(?string $preferred = null): string
    {
        $lang = self::normalize($preferred)
            ?? self::normalize($_COOKIE[self::COOKIE] ?? null)
            ?? self::fromAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
            ?? self::FALLBACK;

        self::$lang = $lang;

        if (!headers_sent() && ($_COOKIE[self::COOKIE] ?? null) !== $lang) {
            self::writeCookie($lang);
        }

        return $lang;
    }

    public static function current(): string
    {
        return self::$lang;
    }

    public static function set(string $lang): void
    {
        $normalized = self::normalize($lang);

        if ($normalized === null) {
            return;
        }

        self::$lang = $normalized;
        self::writeCookie($normalized);
    }

    public static function translate(string $key, array $params = [], ?string $lang = null): string
    {
        $value = self::get($key, $lang);

        if (!is_string($value)) {
            $value = $key;
        }

        return $params === [] ? $value : strtr($value, $params);
    }

    public static function get(string $key, ?string $lang = null): mixed
    {
        $lang = $lang !== null ? self::normalize($lang) : null;
        $lang ??= self::$lang;

        $segments = explode('.', $key);
        $file = array_shift($segments);

        if ($file === null || $file === '') {
            return null;
        }

        $value = self::lookup($lang, $file, $segments);

        if ($value === null && $lang !== self::FALLBACK) {
            $value = self::lookup(self::FALLBACK, $file, $segments);
        }

        return $value;
    }

    /**
     * Diccionario completo para inyectar en JavaScript.
     *
     * @return array<string, mixed>
     */
    public static function jsPayload(): array
    {
        return [
            'lang' => self::$lang,
            'locale' => str_replace('_', '-', Locale::locale(self::$lang)),
            'currency' => 'MXN',
            'translations' => self::dictionary(self::$lang),
            'fallbackTranslations' => self::dictionary(self::FALLBACK),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function dictionary(string $lang): array
    {
        $dictionary = [];

        foreach (['common', 'auth', 'nav', 'settings', 'themes', 'onboarding', 'app', 'income', 'expenses', 'finance', 'savings', 'investment', 'donation', 'moves', 'notifications', 'search', 'summary', 'calendar', 'gamification', 'wishlist', 'admin', 'legal', 'privacy', 'statements', 'debts', 'reports', 'projection', 'lessons', 'score', 'currency', 'landing', 'support', 'about', 'subcategories'] as $file) {
            $dictionary[$file] = self::load($lang, $file);
        }

        return $dictionary;
    }

    /**
     * @param list<string> $segments
     */
    private static function lookup(string $lang, string $file, array $segments): mixed
    {
        $data = self::load($lang, $file);

        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(string $lang, string $file): array
    {
        $cacheKey = $lang . '.' . $file;

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $path = base_path('lang/' . $lang . '/' . $file . '.php');
        $data = is_file($path) ? require $path : [];

        self::$cache[$cacheKey] = is_array($data) ? $data : [];

        return self::$cache[$cacheKey];
    }

    public static function normalize(?string $lang): ?string
    {
        if ($lang === null || $lang === '') {
            return null;
        }

        $candidate = strtolower(substr(trim($lang), 0, 2));

        return in_array($candidate, self::SUPPORTED, true) ? $candidate : null;
    }

    private static function fromAcceptLanguage(string $header): ?string
    {
        if (trim($header) === '') {
            return null;
        }

        $candidates = [];

        foreach (explode(',', $header) as $part) {
            $pieces = explode(';q=', trim($part));
            $tag = strtolower(trim($pieces[0]));
            $quality = isset($pieces[1]) ? (float) $pieces[1] : 1.0;

            $candidates[] = ['q' => $quality, 'lang' => substr($tag, 0, 2)];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['q'] <=> $a['q']);

        foreach ($candidates as $candidate) {
            if (in_array($candidate['lang'], self::SUPPORTED, true)) {
                return $candidate['lang'];
            }
        }

        return null;
    }

    private static function writeCookie(string $lang): void
    {
        $_COOKIE[self::COOKIE] = $lang;

        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $lang, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => (string) config('app.env', 'production') === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
