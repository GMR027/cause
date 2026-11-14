<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\AdminAuditService;
use Cauce\Services\AdminStatsService;

/**
 * Estadísticas agregadas y anónimas del admin.
 */
final class AdminReportController
{
    public function index(): void
    {
        $service = new AdminStatsService();

        echo View::render('admin/reports', [
            'title' => __('admin.stats.title'),
            'stats' => $service->snapshot(),
        ], 'layouts/app');
    }

    public function refresh(): void
    {
        (new AdminStatsService())->refresh();
        AdminAuditService::log('admin.stats.refresh', 'analytics_eventos', null, []);
        Flash::success('admin.stats.refreshed');
        redirect('/admin/reportes');
    }
}
