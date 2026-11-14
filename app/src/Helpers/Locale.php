<?php

declare(strict_types=1);

namespace Cauce\Helpers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use NumberFormatter;

/**
 * Formatos localizados de fechas, números y moneda.
 *
 * Usa intl cuando está disponible y cae a formatos manuales cuando no.
 */
final class Locale
{
    private function __construct()
    {
    }

    public static function locale(string $lang): string
    {
        return $lang === 'en' ? 'en_US' : 'es_MX';
    }

    public static function formatMoney(int $cents, string $currency = 'MXN', ?string $lang = null): string
    {
        $lang ??= I18n::current();

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter(self::locale($lang), NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($cents / 100, $currency);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        $amount = number_format(
            $cents / 100,
            2,
            $lang === 'en' ? '.' : ',',
            $lang === 'en' ? ',' : '.'
        );

        return '$' . $amount . ' ' . $currency;
    }

    public static function formatDate(DateTimeInterface|string|null $value, ?string $lang = null): string
    {
        $date = self::toDateTime($value);

        if ($date === null) {
            return '—';
        }

        $lang ??= I18n::current();
        self::setLocale($lang);

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                self::locale($lang),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::NONE,
                $date->getTimezone()
            );
            $formatted = $formatter->format($date);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return $date->format($lang === 'en' ? 'm/d/Y' : 'd/m/Y');
    }

    public static function formatDateTime(DateTimeInterface|string|null $value, ?string $lang = null): string
    {
        $date = self::toDateTime($value);

        if ($date === null) {
            return '—';
        }

        $lang ??= I18n::current();
        self::setLocale($lang);

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                self::locale($lang),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT,
                $date->getTimezone()
            );
            $formatted = $formatter->format($date);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return $date->format($lang === 'en' ? 'm/d/Y H:i' : 'd/m/Y H:i');
    }

    private static function setLocale(string $lang): void
    {
        $locale = self::locale($lang);

        // Best effort: IntlDateFormatter no depende de setlocale, pero algunos
        // entornos sin intl sí pueden aprovecharlo para strftime/formatos.
        @setlocale(LC_TIME, $locale . '.UTF-8', $locale);
    }

    private static function toDateTime(DateTimeInterface|string|null $value): ?DateTimeImmutable
    {        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($value)
                : new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }

        $timezone = new DateTimeZone((string) config('app.timezone', 'UTC'));

        return $date->setTimezone($timezone);
    }
}
