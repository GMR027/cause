<?php

$notifications = $notifications ?? [];
$unread = (int) ($unread ?? 0);
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('notifications.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('notifications.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('notifications.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/ajustes/notificaciones" class="btn btn-outline-secondary">
            <i class="bi bi-sliders me-1" aria-hidden="true"></i>
            <?= e(__('notifications.view_settings')) ?>
        </a>
        <?php if ($unread > 0): ?>
            <form method="post" action="/notificaciones/leer" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="todos" value="1">
                <button type="submit" class="btn btn-primary">
                    <?= e(__('notifications.mark_all')) ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($unread > 0): ?>
    <p class="small text-secondary">
        <?= e(__('notifications.unread', [':count' => (string) $unread])) ?>
    </p>
<?php endif; ?>

<div class="surface-card">
    <?php if ($notifications === []): ?>
        <div class="text-center py-4">
            <i class="bi bi-bell-slash fs-2 text-secondary" aria-hidden="true"></i>
            <p class="mt-2 mb-1 fw-semibold"><?= e(__('notifications.empty')) ?></p>
            <p class="small text-secondary mb-0"><?= e(__('notifications.empty_hint')) ?></p>
        </div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($notifications as $notification): ?>
                <?php
                $type = (string) $notification['tipo'];
                $reference = (int) ($notification['referencia_id'] ?? 0);
                $url = match ($type) {
                    'resumen_mensual' => $reference > 0 ? '/resumen?id=' . $reference : '/resumen',
                    'gasto_recurrente' => $reference > 0 ? '/gastos/ver?id=' . $reference : '/movimientos',
                    'recordatorio_deuda' => '/deudas',
                    'recordatorio_suscripcion', 'fin_prueba_suscripcion' => '/reportes/suscripciones',
                    'soporte_respuesta' => $reference > 0 ? '/soporte/ver?id=' . $reference : '/soporte',
                    'alerta_categoria' => '/inicio',
                    default => '/inicio',
                };
                $isUnread = (int) $notification['leida'] === 0;
                ?>
                <div class="notification-row" data-notification-row>
                <a class="notification-item <?= $isUnread ? 'is-unread' : '' ?>"
                   href="<?= e($url) ?>"
                   data-notification-read="<?= (int) $notification['id'] ?>">
                    <span class="notification-icon" aria-hidden="true">
                        <i class="bi <?= match ($type) {
                            'resumen_mensual' => 'bi-journal-text',
                            'gasto_recurrente' => 'bi-receipt',
                            'recordatorio_deuda' => 'bi-credit-card',
                            'recordatorio_suscripcion', 'fin_prueba_suscripcion' => 'bi-arrow-repeat',
                            'soporte_respuesta' => 'bi-life-preserver',
                            'alerta_categoria' => 'bi-exclamation-triangle',
                            'vacaciones_reactivacion' => 'bi-sun',
                            default => 'bi-bell',
                        } ?>"></i>
                    </span>
                    <span class="notification-body">
                        <span class="notification-title">
                            <?= e((string) $notification['titulo']) ?>
                            <?php if ($isUnread): ?>
                                <span class="badge text-bg-primary ms-1"><?= e(__('notifications.new')) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="notification-text"><?= e((string) $notification['cuerpo']) ?></span>
                        <span class="notification-time"><?= e(format_datetime($notification['created_at'] ?? null)) ?></span>
                    </span>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
                <form method="post" action="/notificaciones/descartar" class="notification-dismiss m-0"
                      data-notification-dismiss>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                    <button type="submit" class="btn-close" aria-label="<?= e(__('notifications.dismiss')) ?>"
                            title="<?= e(__('notifications.dismiss')) ?>"></button>
                </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
