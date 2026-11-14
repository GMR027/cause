<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\I18n;
use Cauce\Repositories\UserRepository;
use Cauce\Services\AdminAuditService;
use Cauce\Services\AuthService;

final class LanguageController
{
    public function switch(): void
    {
        $lang = I18n::normalize(isset($_GET['lang']) && is_string($_GET['lang']) ? $_GET['lang'] : null);

        if ($lang === null) {
            redirect($this->destination());
        }

        I18n::set($lang);

        $user = AuthService::user();

        if ($user !== null) {
            (new UserRepository())->updateIdioma((int) $user['id'], $lang);
            $_SESSION['user_lang'] = $lang;

            if (($user['rol'] ?? 'user') === 'admin') {
                AdminAuditService::logFor((int) $user['id'], 'admin.language.change', 'users', (int) $user['id'], [
                    'lang' => $lang,
                ]);
            }
        }

        redirect($this->destination());
    }

    private function destination(): string
    {
        $next = isset($_GET['next']) && is_string($_GET['next']) ? $_GET['next'] : '';

        if ($next !== '' && $this->isSafePath($next)) {
            return $next;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $path = is_string($referer) ? parse_url($referer, PHP_URL_PATH) : null;

        if (is_string($path) && $this->isSafePath($path)) {
            return $path;
        }

        return '/';
    }

    private function isSafePath(string $path): bool
    {
        return str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, "\n")
            && !str_contains($path, "\r");
    }
}
