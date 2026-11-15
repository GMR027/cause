<?php

$days = $days ?? [];
$weekdays = $weekdays ?? [];
$bands = $bands ?? [];
$agenda = $agenda ?? [];
$income = $income ?? null;
$selectedEvents = $selected_events ?? [];
$monthTotal = (int) ($month_total ?? 0);
$pendingTotal = (int) ($pending_total ?? 0);
$pendingCount = (int) ($pending_count ?? 0);
$scheduledCount = (int) ($scheduled_count ?? 0);
$currency = (string) ($currency ?? 'MXN');
$month = (string) ($month ?? '');
$monthLabel = (string) ($month_label ?? '');
$prevMonth = (string) ($prev_month ?? '');
$nextMonth = (string) ($next_month ?? '');
$selectedDate = (string) ($selected_date ?? '');
$selectedLabel = (string) ($selected_label ?? '');
$today = (string) ($today ?? '');
$quickDate = $selectedDate;
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('calendar.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('calendar.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('calendar.subtitle')) ?></p>
    </div>

    <?php if ($income !== null): ?>
        <a class="calendar-income-chip" href="<?= e((string) ($income['url'] ?? '/ingresos/nuevo')) ?>">
            <span class="calendar-income-icon" aria-hidden="true">
                <i class="bi bi-arrow-down-circle"></i>
            </span>
            <span>
                <span class="d-block small text-secondary"><?= e(__('calendar.next_income')) ?></span>
                <span class="fw-semibold d-block"><?= e(format_date($income['date'] ?? null)) ?></span>
                <span class="small text-secondary">
                    <?= e((string) ($income['subtitle'] ?? '')) ?>
                    <?php if ((int) ($income['amount'] ?? 0) > 0): ?>
                        · <?= e(format_money((int) $income['amount'], (string) ($income['currency'] ?? $currency))) ?>
                    <?php endif; ?>
                </span>
            </span>
        </a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('calendar.month_total')) ?></span>
            <span class="finance-card-value"><?= money_html($monthTotal, $currency) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card <?= $pendingCount > 0 ? 'finance-card-accent' : '' ?>">
            <span class="finance-card-label"><?= e(__('expenses.pending.total')) ?></span>
            <span class="finance-card-value"><?= money_html($pendingTotal, $currency) ?></span>
            <span class="small text-secondary">
                <?= e(__('calendar.pending_summary', [
                    ':count' => (string) $pendingCount,
                    ':amount' => format_money($pendingTotal, $currency),
                ])) ?>
            </span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('calendar.legend.scheduled')) ?></span>
            <span class="finance-card-value"><?= $scheduledCount ?></span>
            <span class="small text-secondary"><?= e(__('calendar.events_count', [':count' => (string) $scheduledCount])) ?></span>
        </div>
    </div>
</div>

<ul class="nav nav-pills calendar-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="cal-tab-month" data-bs-toggle="pill"
                data-bs-target="#cal-pane-month" type="button" role="tab"
                aria-controls="cal-pane-month" aria-selected="true">
            <i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i><?= e(__('calendar.tab_month')) ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="cal-tab-agenda" data-bs-toggle="pill"
                data-bs-target="#cal-pane-agenda" type="button" role="tab"
                aria-controls="cal-pane-agenda" aria-selected="false">
            <i class="bi bi-list-ul me-1" aria-hidden="true"></i><?= e(__('calendar.tab_agenda')) ?>
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="cal-pane-month" role="tabpanel"
         aria-labelledby="cal-tab-month" tabindex="0">
        <div class="surface-card mb-3">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                <a class="btn btn-sm btn-outline-secondary" href="/calendario?mes=<?= e($prevMonth) ?>"
                   aria-label="<?= e(__('calendar.prev')) ?>">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </a>
                <div class="text-center">
                    <span class="h5 d-block mb-0"><?= e($monthLabel) ?></span>
                    <a class="small text-secondary" href="/calendario?mes=<?= e(substr($today, 0, 7)) ?>&fecha=<?= e($today) ?>">
                        <?= e(__('calendar.today')) ?>
                    </a>
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="/calendario?mes=<?= e($nextMonth) ?>"
                   aria-label="<?= e(__('calendar.next')) ?>">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            </div>

            <div class="calendar-grid" role="grid" aria-label="<?= e($monthLabel) ?>">
                <?php foreach ($weekdays as $weekday): ?>
                    <div class="calendar-weekday" role="columnheader"><?= e((string) $weekday) ?></div>
                <?php endforeach; ?>

                <?php foreach ($days as $day): ?>
                    <?php
                    $date = (string) $day['date'];
                    $classes = ['calendar-day'];

                    if (!$day['in_month']) {
                        $classes[] = 'is-outside';
                    }

                    if ($day['is_today']) {
                        $classes[] = 'is-today';
                    }

                    if ($day['is_selected']) {
                        $classes[] = 'is-selected';
                    }

                    if ($day['pending']) {
                        $classes[] = 'has-pending';
                    }

                    if ($day['scheduled']) {
                        $classes[] = 'has-scheduled';
                    }

                    if (!empty($day['income'])) {
                        $classes[] = 'has-income';
                    }

                    if ((int) $day['band'] > 0) {
                        $classes[] = 'band-' . (((int) $day['band'] % 2) + 1);
                    }
                    ?>
                    <a class="<?= e(implode(' ', $classes)) ?>"
                       href="/calendario?mes=<?= e($month) ?>&fecha=<?= e($date) ?>"
                       data-cal-day
                       data-cal-date="<?= e($date) ?>"
                       data-cal-label="<?= e(format_date($date)) ?>"
                       role="gridcell"
                       aria-label="<?= e(format_date($date)) ?>">
                        <span class="calendar-day-number"><?= (int) $day['day'] ?></span>

                        <span class="calendar-dots" aria-hidden="true">
                            <?php foreach (array_slice((array) $day['dots'], 0, 4) as $color): ?>
                                <span class="calendar-dot color-<?= (int) $color ?>"></span>
                            <?php endforeach; ?>
                            <?php if ($day['scheduled']): ?>
                                <span class="calendar-dot is-hollow"></span>
                            <?php endif; ?>
                        </span>

                        <?php if ((int) $day['event_count'] > 0): ?>
                            <span class="calendar-day-count"><?= (int) $day['event_count'] ?></span>
                        <?php endif; ?>

                        <?php if (!empty($day['income'])): ?>
                            <span class="calendar-income-mark" aria-hidden="true">
                                <i class="bi bi-arrow-down-circle"></i>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="calendar-legend mt-3">
                <span class="calendar-legend-item">
                    <span class="calendar-dot color-1"></span><?= e(__('calendar.legend.expense')) ?>
                </span>
                <span class="calendar-legend-item">
                    <span class="calendar-day-sample has-pending"></span><?= e(__('calendar.legend.pending')) ?>
                </span>
                <span class="calendar-legend-item">
                    <span class="calendar-dot is-hollow"></span><?= e(__('calendar.legend.scheduled')) ?>
                </span>
                <span class="calendar-legend-item">
                    <span class="calendar-income-mark"><i class="bi bi-arrow-down-circle"></i></span>
                    <?= e(__('calendar.legend.income')) ?>
                </span>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="surface-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <span class="small text-secondary d-block"><?= e(__('calendar.day_sheet')) ?></span>
                            <h2 class="h5 mb-0"><?= e($selectedLabel) ?></h2>
                        </div>
                        <span class="badge text-bg-light border">
                            <?= e(__('calendar.events_count', [':count' => (string) count($selectedEvents)])) ?>
                        </span>
                    </div>

                    <?php if ($selectedEvents === []): ?>
                        <p class="small text-secondary mb-0"><?= e(__('calendar.day_empty')) ?></p>
                    <?php else: ?>
                        <div class="finance-list">
                            <?php foreach ($selectedEvents as $event): ?>
                                <a class="finance-row calendar-event" href="<?= e((string) ($event['url'] ?? '/inicio')) ?>">
                                    <span class="calendar-event-icon <?= !empty($event['pending']) ? 'is-pending' : '' ?>"
                                          aria-hidden="true">
                                        <i class="bi <?= e((string) ($event['icon'] ?? 'bi-dot')) ?>"></i>
                                    </span>
                                    <span class="flex-grow-1">
                                        <span class="fw-semibold d-block"><?= e((string) ($event['title'] ?? '')) ?></span>
                                        <span class="small text-secondary">
                                            <?= e((string) ($event['subtitle'] ?? '')) ?>
                                            <?php if (($event['time'] ?? '') !== ''): ?>
                                                · <?= e((string) $event['time']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                    <span class="fw-semibold <?= !empty($event['scheduled']) ? 'text-secondary' : '' ?>">
                                        <?= money_html((int) ($event['amount'] ?? 0), (string) ($event['currency'] ?? $currency)) ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="surface-card h-100">
                    <h2 class="h5 mb-3"><?= e(__('calendar.quick_actions')) ?></h2>
                    <div class="d-grid gap-2">
                        <a class="btn btn-primary" href="/gastos/nuevo?fecha=<?= e($selectedDate) ?>">
                            <i class="bi bi-arrow-up-circle me-1" aria-hidden="true"></i>
                            <?= e(__('calendar.quick_expense')) ?>
                        </a>
                        <a class="btn btn-outline-primary" href="/ingresos/nuevo">
                            <i class="bi bi-arrow-down-circle me-1" aria-hidden="true"></i>
                            <?= e(__('calendar.quick_income')) ?>
                        </a>
                        <a class="btn btn-outline-secondary" href="/mover">
                            <i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>
                            <?= e(__('calendar.quick_move')) ?>
                        </a>
                    </div>
                    <p class="small text-secondary mt-3 mb-0">
                        <?= e(__('calendar.legend.band')) ?>:
                        <?php foreach ($bands as $band): ?>
                            <span class="badge text-bg-light border <?= !empty($band['current']) ? 'text-primary' : '' ?>">
                                <?= e((string) $band['label']) ?>
                            </span>
                        <?php endforeach; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="cal-pane-agenda" role="tabpanel"
         aria-labelledby="cal-tab-agenda" tabindex="0">
        <div class="surface-card">
            <?php if ($agenda === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('calendar.agenda_empty')) ?></p>
            <?php else: ?>
                <div class="calendar-agenda">
                    <?php foreach ($agenda as $item): ?>
                        <a class="calendar-agenda-day" href="/calendario?mes=<?= e($month) ?>&fecha=<?= e((string) $item['date']) ?>">
                            <span class="calendar-agenda-date">
                                <span class="fw-semibold d-block"><?= e((string) $item['label']) ?></span>
                                <?php if (!empty($item['is_today'])): ?>
                                    <span class="badge text-bg-primary"><?= e(__('calendar.today')) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="calendar-agenda-events">
                                <?php foreach (array_slice((array) $item['events'], 0, 4) as $event): ?>
                                    <span class="calendar-agenda-event <?= !empty($event['pending']) ? 'is-pending' : '' ?>">
                                        <i class="bi <?= e((string) ($event['icon'] ?? 'bi-dot')) ?>" aria-hidden="true"></i>
                                        <span class="text-truncate"><?= e((string) ($event['title'] ?? '')) ?></span>
                                        <span class="ms-auto text-nowrap">
                                            <?= money_html((int) ($event['amount'] ?? 0), (string) ($event['currency'] ?? $currency)) ?>
                                        </span>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count((array) $item['events']) > 4): ?>
                                    <span class="small text-secondary">
                                        <?= e(__('calendar.more_events', [':count' => (string) (count((array) $item['events']) - 4)])) ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="calendarQuick"
     aria-labelledby="calendarQuickTitle">
    <div class="offcanvas-header">
        <div>
            <span class="small text-secondary d-block"><?= e(__('calendar.quick_actions')) ?></span>
            <h2 class="offcanvas-title h5 mb-0" id="calendarQuickTitle"><?= e($selectedLabel) ?></h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="<?= e(__('calendar.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="small text-secondary" data-quick-label><?= e($selectedLabel) ?></p>
        <div class="d-grid gap-2">
            <a class="btn btn-primary" data-quick-link="expense" href="/gastos/nuevo">
                <i class="bi bi-arrow-up-circle me-1" aria-hidden="true"></i>
                <?= e(__('calendar.quick_expense')) ?>
            </a>
            <a class="btn btn-outline-primary" data-quick-link="income" href="/ingresos/nuevo">
                <i class="bi bi-arrow-down-circle me-1" aria-hidden="true"></i>
                <?= e(__('calendar.quick_income')) ?>
            </a>
            <a class="btn btn-outline-secondary" data-quick-link="move" href="/mover">
                <i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>
                <?= e(__('calendar.quick_move')) ?>
            </a>
            <a class="btn btn-outline-secondary" data-quick-link="day" href="/calendario">
                <i class="bi bi-calendar-day me-1" aria-hidden="true"></i>
                <?= e(__('calendar.view_day')) ?>
            </a>
        </div>
    </div>
</div>
