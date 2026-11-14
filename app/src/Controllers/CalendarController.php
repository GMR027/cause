<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\CalendarExportService;
use Cauce\Services\CalendarService;

/**
 * Calendario mensual con agenda y hoja de día.
 */
final class CalendarController
{
    public function index(): void
    {
        $user = $this->user();
        $month = isset($_GET['mes']) && is_string($_GET['mes']) ? $_GET['mes'] : null;
        $date = isset($_GET['fecha']) && is_string($_GET['fecha']) ? $_GET['fecha'] : null;

        echo View::render('calendar/index', array_merge(
            (new CalendarService())->data($user, $month, $date),
            ['title' => __('calendar.title')]
        ), 'layouts/app');
    }

    public function exportIcs(): void
    {
        $user = $this->user();
        $content = (new CalendarExportService())->forUser($user);

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . CalendarExportService::FILENAME . '"');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo $content;
        exit;
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
}
