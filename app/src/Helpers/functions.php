<?php

declare(strict_types=1);

/*
 * Funciones globales de Cauce.
 *
 * Composer carga este archivo automáticamente (autoload.files) antes de que se
 * ejecute cualquier clase del proyecto.
 */

use Cauce\Helpers\Csrf;
use Cauce\Helpers\I18n;
use Cauce\Helpers\Locale;
use Cauce\Services\AuthService;
use Cauce\Services\Mail\MailerFactory;

if (!function_exists('env')) {
    /**
     * Lee una variable de entorno y normaliza booleanos y null.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Lee configuración desde app/config/{archivo}.php con notación de puntos.
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        $segments = explode('.', $key);
        $file = array_shift($segments);

        if ($file === null || $file === '') {
            return $default;
        }

        if (!array_key_exists($file, $cache)) {
            $path = base_path('app/config/' . $file . '.php');
            $cache[$file] = is_file($path) ? require $path : [];
        }

        $value = $cache[$file];

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('e')) {
    /**
     * Escapa texto para insertarlo en HTML.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('asset')) {
    /**
     * Construye la URL pública de un asset.
     */
    function asset(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('asset_versioned')) {
    /**
     * URL de asset con versión basada en la fecha del archivo.
     */
    function asset_versioned(string $path): string
    {
        $url = asset($path);
        $file = base_path('public/assets/' . ltrim($path, '/'));

        if (!is_file($file)) {
            return $url;
        }

        $version = @filemtime($file);

        return $version === false ? $url : $url . '?v=' . $version;
    }
}

if (!function_exists('base_path')) {
    /**
     * Ruta absoluta dentro del proyecto.
     */
    function base_path(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);

        return $path === '' ? $base : rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirige y termina la petición.
     */
    function redirect(string $path, int $status = 302): never
    {
        header('Location: ' . $path, true, $status);
        exit;
    }
}

if (!function_exists('input')) {
    /**
     * Lee un campo de texto de POST/GET ya recortado.
     */
    function input(string $key, ?string $default = null): ?string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? null;

        if (is_string($value)) {
            return trim($value);
        }

        return $value === null ? $default : (is_scalar($value) ? trim((string) $value) : $default);
    }
}

if (!function_exists('__')) {
    /**
     * Traduce una clave con fallback a español.
     *
     * @param array<string, string> $params
     */
    function __(string $key, array $params = [], ?string $lang = null): string
    {
        return I18n::translate($key, $params, $lang);
    }
}

if (!function_exists('lang_array')) {
    /**
     * Devuelve un arreglo traducido (por ejemplo, preguntas por defecto).
     *
     * @return array<mixed>
     */
    function lang_array(string $key, ?string $lang = null): array
    {
        $value = I18n::get($key, $lang);

        return is_array($value) ? $value : [];
    }
}

if (!function_exists('current_lang')) {
    function current_lang(): string
    {
        return I18n::current();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return AuthService::user();
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return AuthService::check();
    }
}

if (!function_exists('format_money')) {
    function format_money(int $cents, string $currency = 'MXN', ?string $lang = null): string
    {
        return Locale::formatMoney($cents, $currency, $lang);
    }
}

if (!function_exists('format_date')) {
    function format_date(string|\DateTimeInterface|null $value, ?string $lang = null): string
    {
        return Locale::formatDate($value, $lang);
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(string|\DateTimeInterface|null $value, ?string $lang = null): string
    {
        return Locale::formatDateTime($value, $lang);
    }
}

if (!function_exists('format_bytes')) {
    /**
     * Tamaño legible para archivos.
     */
    function format_bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }

        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
}

if (!function_exists('partial')) {
    /**
     * Renderiza un partial sin layout.
     *
     * @param array<string, mixed> $data
     */
    function partial(string $name, array $data = []): string
    {
        return \Cauce\Helpers\View::render($name, $data, '');
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }
}

if (!function_exists('money_html')) {
    /**
     * Importe enmascarable por el modo discreto.
     */
    function money_html(int $cents, string $currency = 'MXN', ?string $lang = null): string
    {
        return '<span class="money">'
            . '<span class="money-value">' . e(format_money($cents, $currency, $lang)) . '</span>'
            . '<span class="money-mask" aria-hidden="true">••••</span>'
            . '</span>';
    }
}

if (!function_exists('theme_vars')) {
    /**
     * Variables CSS del tema para el primer render (evita flash).
     *
     * @param array{modo: string, color: string, enfoque: string, fondo: string, textura: string} $theme
     */
    function theme_vars(array $theme): string
    {
        $derived = (new \Cauce\Services\ThemeService())->derive($theme['color']);

        return implode(';', [
            '--cauce-primary:' . $derived['color'],
            '--cauce-primary-rgb:' . $derived['rgb'],
            '--cauce-primary-hover:' . $derived['hover'],
            '--cauce-primary-active:' . $derived['active'],
            '--cauce-primary-soft:' . $derived['soft'],
        ]);
    }
}

if (!function_exists('theme_attributes')) {
    /**
     * Atributos del tema para <html>.
     *
     * @param array{modo: string, color: string, enfoque: string, fondo: string, textura: string} $theme
     */
    function theme_attributes(array $theme): string
    {
        $bs = match ($theme['modo']) {
            'oscuro' => 'dark',
            default => 'light',
        };

        return implode(' ', [
            'data-cauce-modo="' . e($theme['modo']) . '"',
            'data-enfoque="' . e($theme['enfoque']) . '"',
            'data-fondo="' . e($theme['fondo']) . '"',
            'data-textura="' . e($theme['textura']) . '"',
            'data-bs-theme="' . $bs . '"',
            'style="' . e(theme_vars($theme)) . '"',
        ]);
    }
}

if (!function_exists('audit_action_label')) {
    /**
     * Nombre legible de una acción de auditoría (con fallback al código).
     */
    function audit_action_label(string $action): string
    {
        $map = \Cauce\Helpers\I18n::get('admin.audit_actions');

        if (is_array($map) && isset($map[$action]) && is_string($map[$action])) {
            return $map[$action];
        }

        return $action;
    }
}

if (!function_exists('parse_money_cents')) {
    /**
     * Convierte un monto en pesos a centavos enteros.
     */
    function parse_money_cents(string $raw): ?int
    {
        $normalized = str_replace([' ', '$'], '', trim($raw));

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                // 10.000,50
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                // 10,000.50
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif (str_contains($normalized, ',')) {
            $parts = explode(',', $normalized);

            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== ''
                && ctype_digit($parts[1]) && strlen($parts[1]) === 3
            ) {
                // 10,000
                $normalized = $parts[0] . $parts[1];
            } else {
                $normalized = str_replace(',', '.', $normalized);
            }
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        $amount = (float) $normalized;

        if (!is_finite($amount) || abs($amount) > PHP_INT_MAX / 100) {
            return null;
        }

        return (int) round($amount * 100);
    }
}

if (!function_exists('currency_options')) {
    /**
     * @return list<array<string, mixed>>
     */
    function currency_options(): array
    {
        $user = \Cauce\Services\AuthService::user();

        if ($user === null) {
            return [];
        }

        return (new \Cauce\Services\CurrencyService())->currencies($user);
    }
}

if (!function_exists('lesson_for')) {
    /**
     * Lección publicada para una sección, con su estado de lectura.
     *
     * @return array<string, mixed>|null
     */
    function lesson_for(string $section): ?array
    {
        $user = \Cauce\Services\AuthService::user();

        if ($user === null) {
            return null;
        }

        return (new \Cauce\Services\LessonService())->forSection($user, $section);
    }
}

if (!function_exists('json_response')) {
    /**
     * @param array<string, mixed> $payload
     */
    function json_response(array $payload, int $status = 200): never
    {
        http_response_code($status);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('mailer')) {
    function mailer(): \Cauce\Services\Mail\MailerInterface
    {
        return MailerFactory::make();
    }
}
