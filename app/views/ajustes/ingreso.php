<?php

$recurrence = (string) ($recurrence ?? 'mensual');
$startDay = (int) ($startDay ?? 1);
$period = $period ?? null;
$changeFrom = $changeFrom ?? null;
$recurrences = $recurrences ?? [];
$categories = $categories ?? [];
$extra = $extra ?? [];
$errors = $errors ?? [];
$methodCode = $methodCode ?? null;
$methodName = $methodName ?? null;
$methodLabel = null;

if ($methodName !== null) {
    $methodKey = 'onboarding.methods.' . (string) $methodCode . '.name';
    $methodLabel = __($methodKey) === $methodKey ? (string) $methodName : __($methodKey);
}
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('settings.ingreso.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('settings.ingreso.subtitle')) ?></p>
    </div>
    <a href="/ajustes" class="btn btn-outline-secondary d-none d-xl-inline-flex"><?= e(__('common.actions.back')) ?></a>
</div>

<?= partial('partials/errors', ['errors' => $errors]) ?>

<?php if ($categories !== []): ?>
    <div class="surface-card mb-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
            <div>
                <h2 class="h5 mb-1"><?= e(__('settings.ingreso.method_title', [':method' => (string) $methodLabel])) ?></h2>
                <p class="small text-secondary mb-0"><?= e(__('settings.ingreso.method_hint')) ?></p>
            </div>
        </div>
        <ul class="method-list mt-3">
            <?php foreach ($categories as $categoria): ?>
                <li>
                    <span>
                        <?= e((string) $categoria['nombre']) ?>
                        <span class="text-secondary small">(<?= e(__('income.types_category.' . (string) $categoria['tipo'])) ?>)</span>
                    </span>
                    <span class="fw-semibold"><?= e((string) round((float) $categoria['porcentaje_usuario'], 2)) ?>%</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('settings.ingreso.period_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('settings.ingreso.period_hint')) ?></p>

            <dl class="row small mb-3">
                <dt class="col-5 text-secondary fw-normal"><?= e(__('settings.ingreso.current_period')) ?></dt>
                <dd class="col-7 mb-2">
                    <?= e(format_date($period['inicio'] ?? null)) ?> – <?= e(format_date($period['fin'] ?? null)) ?>
                </dd>

                <dt class="col-5 text-secondary fw-normal"><?= e(__('settings.ingreso.current_recurrence')) ?></dt>
                <dd class="col-7 mb-0"><?= e(__('onboarding.recurrencia.' . $recurrence)) ?></dd>
            </dl>

            <?php if ($changeFrom !== null): ?>
                <div class="alert alert-info small mb-3" role="status">
                    <?= e(__('settings.ingreso.change_pending', [':date' => format_datetime($changeFrom)])) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/ajustes/ingreso/recurrencia">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="recurrencia"><?= e(__('settings.ingreso.recurrence')) ?></label>
                    <select class="form-select" id="recurrencia" name="recurrencia">
                        <?php foreach ($recurrences as $option): ?>
                            <option value="<?= e((string) $option) ?>" <?= $recurrence === $option ? 'selected' : '' ?>>
                                <?= e(__('onboarding.recurrencia.' . (string) $option)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text"><?= e(__('settings.ingreso.recurrence_hint')) ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="periodo_inicio_dia"><?= e(__('settings.ingreso.start_day')) ?></label>
                    <input type="number" class="form-control" id="periodo_inicio_dia" name="periodo_inicio_dia"
                           min="1" max="31" value="<?= $startDay ?>" required>
                    <div class="form-text"><?= e(__('settings.ingreso.start_day_hint')) ?></div>
                </div>

                <button type="submit" class="btn btn-primary"><?= e(__('common.actions.save')) ?></button>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('settings.ingreso.extra_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('settings.ingreso.extra_hint')) ?></p>

            <?php if ($categories === []): ?>
                <div class="alert alert-warning mb-0" role="alert">
                    <?= e(__('settings.ingreso.no_method')) ?>
                </div>
            <?php else: ?>
                <form method="post" action="/ajustes/ingreso/extra" data-extra-form>
                    <?= csrf_field() ?>

                    <div class="extra-grid">
                        <?php foreach ($categories as $categoria): ?>
                            <?php $name = (string) $categoria['nombre']; ?>
                            <div class="extra-row">
                                <label class="form-label small mb-1" for="extra_<?= e((string) $categoria['orden']) ?>">
                                    <?= e($name) ?>
                                    <span class="text-secondary">
                                        (<?= e(__('income.types_category.' . (string) $categoria['tipo'])) ?>)
                                    </span>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="extra_<?= e((string) $categoria['orden']) ?>"
                                           name="reparto[<?= e($name) ?>]" min="0" max="100" step="1"
                                           value="<?= (int) ($extra[$name] ?? 0) ?>" data-extra-input
                                           data-method-percent="<?= (int) round((float) $categoria['porcentaje_usuario']) ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="small text-secondary">
                            <?= e(__('settings.ingreso.extra_total')) ?>:
                            <span class="fw-semibold" data-extra-total>0</span>%
                        </span>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-extra-use-method>
                                <?= e(__('settings.ingreso.extra_use_method')) ?>
                            </button>
                            <button type="submit" class="btn btn-primary"><?= e(__('common.actions.save')) ?></button>
                        </div>
                    </div>

                    <p class="small text-secondary mt-3 mb-0"><?= e(__('settings.ingreso.extra_fallback')) ?></p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
