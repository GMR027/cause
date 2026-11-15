<?php

$config = $config ?? [];
$types = $types ?? [];
$tone = (string) ($tone ?? 'neutral');
$vacationUntil = $vacation_until ?? null;
$pushEnabled = (bool) ($push_enabled ?? false);
$pushPublicKey = (string) ($push_public_key ?? '');
$subscriptions = (int) ($subscriptions ?? 0);
$timezone = new DateTimeZone((string) ($currentUser['zona_horaria'] ?? config('app.timezone', 'UTC')));
$timedTypes = ['ingreso_dia_menos_1', 'ingreso_dia_0', 'ingreso_rescate', 'resumen_mensual'];
$vacationLabel = null;

if ($vacationUntil !== null && trim((string) $vacationUntil) !== '') {
    $vacationLabel = format_datetime(
        (new DateTimeImmutable((string) $vacationUntil, new DateTimeZone('UTC')))->setTimezone($timezone)
    );
}
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('notifications.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('notifications.settings.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('notifications.settings.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/ajustes/notificaciones/calendario.ics" class="btn btn-outline-secondary">
            <i class="bi bi-download me-1" aria-hidden="true"></i>
            <?= e(__('calendar.ics.export')) ?>
        </a>
        <a href="/notificaciones" class="btn btn-outline-secondary">
            <i class="bi bi-bell me-1" aria-hidden="true"></i><?= e(__('notifications.title')) ?>
        </a>
    </div>
</div>

<div class="alert alert-secondary py-2 small" role="status" data-dismissible data-dismiss-key="notifications-info">
    <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>
    <?= e(__('calendar.ics.hint')) ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <div class="surface-card">
            <h2 class="h5 mb-1"><?= e(__('notifications.settings.config_title')) ?></h2>
            <p class="small text-secondary"><?= e(__('notifications.settings.config_hint')) ?></p>

            <form method="post" action="/ajustes/notificaciones">
                <?= csrf_field() ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-3">
                        <thead>
                            <tr>
                                <th scope="col"><?= e(__('notifications.settings.active')) ?></th>
                                <th scope="col"><?= e(__('notifications.title')) ?></th>
                                <th scope="col"><?= e(__('notifications.settings.hour')) ?></th>
                                <th scope="col"><?= e(__('notifications.settings.frequency')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($types as $type): ?>
                                <?php $row = $config[$type] ?? []; ?>
                                <tr>
                                    <td>
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox"
                                                   id="notif-<?= e((string) $type) ?>"
                                                   name="activo[<?= e((string) $type) ?>]" value="1"
                                                   <?= !empty($row['activo']) ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                    <td>
                                        <label class="form-check-label" for="notif-<?= e((string) $type) ?>">
                                            <?= e(__('notifications.types.' . (string) $type)) ?>
                                        </label>
                                    </td>
                                    <td>
                                        <?php if (in_array((string) $type, $timedTypes, true)): ?>
                                            <input type="time" class="form-control form-control-sm"
                                                   name="hora[<?= e((string) $type) ?>]"
                                                   value="<?= e(substr((string) ($row['hora'] ?? '09:00:00'), 0, 5)) ?>">
                                        <?php else: ?>
                                            <span class="text-secondary small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm"
                                                name="frecuencia[<?= e((string) $type) ?>]">
                                            <?php foreach (['una_vez', 'diaria', 'semanal', 'mensual'] as $frequency): ?>
                                                <option value="<?= e($frequency) ?>"
                                                        <?= (string) ($row['frecuencia'] ?? '') === $frequency ? 'selected' : '' ?>>
                                                    <?= e(__('notifications.settings.frequencies.' . $frequency)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?= e(__('notifications.settings.save')) ?>
                </button>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('notifications.settings.tone_title')) ?></h2>
            <p class="small text-secondary"><?= e(__('notifications.settings.tone_hint')) ?></p>

            <form method="post" action="/ajustes/notificaciones/tono">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="notif-tone"><?= e(__('notifications.settings.tone_title')) ?></label>
                    <select class="form-select" id="notif-tone" name="tono">
                        <?php foreach (['neutral', 'motivacional', 'empatico'] as $option): ?>
                            <option value="<?= e($option) ?>" <?= $tone === $option ? 'selected' : '' ?>>
                                <?= e(__('notifications.settings.tones.' . $option)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-outline-primary w-100">
                    <?= e(__('notifications.settings.tone_save')) ?>
                </button>
            </form>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('notifications.settings.vacation_title')) ?></h2>
            <p class="small text-secondary"><?= e(__('notifications.settings.vacation_hint')) ?></p>
            <p class="small mb-3">
                <?php if ($vacationLabel !== null): ?>
                    <span class="badge text-bg-warning">
                        <?= e(__('notifications.settings.vacation_active', [':date' => $vacationLabel])) ?>
                    </span>
                <?php else: ?>
                    <span class="text-secondary"><?= e(__('notifications.settings.vacation_none')) ?></span>
                <?php endif; ?>
            </p>

            <div class="d-flex flex-wrap gap-2">
                <?php foreach ([7, 15, 30] as $days): ?>
                    <form method="post" action="/ajustes/notificaciones/vacaciones" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="dias" value="<?= $days ?>">
                        <button type="submit" class="btn btn-outline-secondary">
                            <?= e(__('notifications.settings.pause_' . $days)) ?>
                        </button>
                    </form>
                <?php endforeach; ?>

                <?php if ($vacationLabel !== null): ?>
                    <form method="post" action="/ajustes/notificaciones/vacaciones" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="dias" value="0">
                        <button type="submit" class="btn btn-primary">
                            <?= e(__('notifications.settings.resume')) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('notifications.settings.push_title')) ?></h2>
            <p class="small text-secondary"><?= e(__('notifications.settings.push_hint')) ?></p>

            <p class="small mb-2">
                <?php if ($pushEnabled): ?>
                    <span class="badge text-bg-success"><?= e(__('notifications.settings.push_enabled')) ?></span>
                <?php else: ?>
                    <span class="badge text-bg-secondary"><?= e(__('notifications.settings.push_disabled')) ?></span>
                <?php endif; ?>
            </p>

            <p class="small text-secondary">
                <?= e(__('notifications.settings.push_subscribed', [':count' => (string) $subscriptions])) ?>
            </p>

            <button type="button" class="btn btn-primary"
                    data-push-subscribe
                    data-public-key="<?= e($pushPublicKey) ?>"
                    data-token="<?= e(csrf_token()) ?>"
                    <?= $pushEnabled && $pushPublicKey !== '' ? '' : 'disabled' ?>>
                <i class="bi bi-phone me-1" aria-hidden="true"></i>
                <?= e(__('notifications.settings.push_activate')) ?>
            </button>
            <p class="small mt-2 mb-0" data-push-status role="status"></p>
        </div>
    </div>
</div>
