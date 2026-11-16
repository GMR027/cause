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
$methods = $methods ?? [];
$selectedMethodId = (int) ($selectedMethodId ?? 0);
$openMethodEditor = (bool) ($openMethodEditor ?? false);
$methodLabelOf = static function (?string $code, ?string $name): string {
    $key = 'onboarding.methods.' . (string) $code . '.name';

    return __($key) === $key ? (string) $name : __($key);
};
$methodLabel = $methodName !== null ? $methodLabelOf($methodCode, $methodName) : null;
$pct = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
$catalogMethods = array_values(array_filter($methods, static fn (array $m): bool => empty($m['propio'])));
$ownMethods = array_values(array_filter($methods, static fn (array $m): bool => !empty($m['propio'])));
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
                    <span class="fw-semibold"><?= e($pct((float) $categoria['porcentaje_usuario'])) ?>%</span>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($methods !== []): ?>
            <details class="method-editor mt-3" <?= $openMethodEditor ? 'open' : '' ?>>
                <summary class="btn btn-outline-primary">
                    <i class="bi bi-sliders me-1" aria-hidden="true"></i><?= e(__('settings.metodo.toggle')) ?>
                </summary>

                <form method="post" action="/ajustes/ingreso/metodo" class="mt-3" data-method-form>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="metodo_id"><?= e(__('settings.metodo.choose')) ?></label>
                        <select class="form-select" id="metodo_id" name="metodo_id" data-method-select>
                            <optgroup label="<?= e(__('settings.metodo.group_catalog')) ?>">
                                <?php foreach ($catalogMethods as $method): ?>
                                    <option value="<?= (int) $method['id'] ?>" <?= (int) $method['id'] === $selectedMethodId ? 'selected' : '' ?>>
                                        <?= e($methodLabelOf((string) $method['codigo'], (string) $method['nombre'])) ?><?= !empty($method['activo']) ? ' · ' . e(__('settings.metodo.current')) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php if ($ownMethods !== []): ?>
                                <optgroup label="<?= e(__('settings.metodo.group_own')) ?>">
                                    <?php foreach ($ownMethods as $method): ?>
                                        <option value="<?= (int) $method['id'] ?>" <?= (int) $method['id'] === $selectedMethodId ? 'selected' : '' ?>>
                                            <?= e((string) $method['nombre']) ?><?= !empty($method['activo']) ? ' · ' . e(__('settings.metodo.current')) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <div class="form-text"><?= e(__('settings.metodo.choose_hint')) ?></div>
                    </div>

                    <?php foreach ($methods as $method): ?>
                        <?php $mid = (int) $method['id']; ?>
                        <fieldset class="method-panel" data-method-panel="<?= $mid ?>" <?= $mid === $selectedMethodId ? '' : 'hidden disabled' ?>>
                            <legend class="visually-hidden"><?= e($methodLabelOf((string) $method['codigo'], (string) $method['nombre'])) ?></legend>
                            <div class="extra-grid">
                                <?php foreach ($method['categorias'] as $index => $categoria): ?>
                                    <?php
                                    $name = (string) $categoria['nombre'];
                                    $value = (float) ($method['porcentajes'][$name] ?? 0);
                                    $default = $categoria['porcentaje'] !== null ? (float) $categoria['porcentaje'] : 0.0;
                                    $inputId = 'pct_' . $mid . '_' . $index;
                                    ?>
                                    <div class="extra-row">
                                        <label class="form-label small mb-1" for="<?= e($inputId) ?>">
                                            <?= e($name) ?>
                                            <span class="text-secondary">(<?= e(__('income.types_category.' . (string) $categoria['tipo'])) ?>)</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="<?= e($inputId) ?>"
                                                   name="porcentajes[<?= $mid ?>][<?= e($name) ?>]" min="0" max="100" step="0.5"
                                                   value="<?= e($pct($value)) ?>" data-method-input
                                                   data-default="<?= e($pct($default)) ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php if (empty($method['propio'])): ?>
                                    <button type="button" class="btn btn-sm btn-link px-0" data-method-reset>
                                        <?= e(__('settings.metodo.reset')) ?>
                                    </button>
                                <?php else: ?>
                                    <a class="btn btn-sm btn-link px-0" href="/ajustes/ingreso/metodos/editar?id=<?= $mid ?>">
                                        <i class="bi bi-pencil me-1" aria-hidden="true"></i><?= e(__('settings.metodo.edit')) ?>
                                    </a>
                                    <?php if (empty($method['activo'])): ?>
                                        <button type="submit" class="btn btn-sm btn-link link-danger px-0" form="delete-method-<?= $mid ?>">
                                            <i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('settings.metodo.delete')) ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-link px-0" href="/ajustes/ingreso/metodos/nuevo?desde=<?= $mid ?>">
                                    <i class="bi bi-copy me-1" aria-hidden="true"></i><?= e(__('settings.metodo.copy')) ?>
                                </a>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <span class="small text-secondary">
                            <?= e(__('settings.ingreso.extra_total')) ?>:
                            <span class="fw-semibold" data-method-total>0</span>%
                        </span>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="/ajustes/ingreso/metodos/nuevo" class="btn btn-outline-secondary">
                                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('settings.metodo.new')) ?>
                            </a>
                            <button type="submit" class="btn btn-primary"><?= e(__('settings.metodo.apply')) ?></button>
                        </div>
                    </div>

                    <p class="small text-secondary mt-3 mb-0"><?= e(__('settings.metodo.future_note')) ?></p>
                </form>

                <?php foreach ($ownMethods as $method): ?>
                    <?php if (empty($method['activo'])): ?>
                        <form method="post" action="/ajustes/ingreso/metodos/eliminar" id="delete-method-<?= (int) $method['id'] ?>"
                              data-confirm="<?= e(__('settings.metodo.delete_confirm')) ?>" data-confirm-danger hidden>
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $method['id'] ?>">
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </details>
        <?php endif; ?>
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
