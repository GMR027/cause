<?php

$items = $items ?? [];
$state = (string) ($state ?? 'activo');
$savingsTotal = (int) ($savings_total_centavos ?? 0);
$activeCount = (int) ($active_count ?? 0);
$activeTotal = (int) ($active_total_centavos ?? 0);
$priorities = $priorities ?? [];
$categories = $categories ?? [];
$currency = (string) ($currency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$today = (string) ($today ?? gmdate('Y-m-d'));
$tabs = ['activo', 'comprado', 'descartado', 'todos'];
$pct = static fn (float $value): string => rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
$dateOnly = static fn (?string $date): string => $date !== null && trim($date) !== ''
    ? format_date($date . ' 12:00:00')
    : '—';
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('wishlist.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('wishlist.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('wishlist.subtitle')) ?></p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#wishlistCreate">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
        <?= e(__('wishlist.add')) ?>
    </button>
</div>

<div class="row g-3 mb-4" data-wishlist-savings="<?= $savingsTotal ?>" data-currency="<?= e($currency) ?>">
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('wishlist.filters.activo')) ?></span>
            <span class="finance-card-value"><?= $activeCount ?></span>
            <span class="small text-secondary">
                <?= e(__('wishlist.summary.active_count', [':count' => (string) $activeCount])) ?>
            </span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('wishlist.summary.active_total')) ?></span>
            <span class="finance-card-value"><?= money_html($activeTotal, $currency) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card finance-card-accent">
            <span class="finance-card-label"><?= e(__('wishlist.summary.savings_total')) ?></span>
            <span class="finance-card-value"><?= money_html($savingsTotal, $currency) ?></span>
        </div>
    </div>
</div>

<ul class="nav nav-pills calendar-tabs mb-3">
    <?php foreach ($tabs as $tab): ?>
        <li class="nav-item">
            <a class="nav-link <?= $state === $tab ? 'active' : '' ?>"
               href="/deseos?estado=<?= e($tab) ?>">
                <?= e(__('wishlist.filters.' . $tab)) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($items === []): ?>
    <div class="surface-card text-center py-5">
        <i class="bi bi-stars fs-2 text-secondary" aria-hidden="true"></i>
        <p class="mt-2 mb-1 fw-semibold"><?= e(__('wishlist.empty')) ?></p>
        <p class="small text-secondary mb-3"><?= e(__('wishlist.empty_hint')) ?></p>
        <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#wishlistCreate">
            <?= e(__('wishlist.add')) ?>
        </button>
    </div>
<?php else: ?>
    <div class="wishlist-grid">
        <?php foreach ($items as $item): ?>
            <?php
            $itemState = (string) $item['estado'];
            $isActive = $itemState === 'activo';
            $priority = (int) ($item['prioridad'] ?? 2);
            $priorityKey = $priorities[$priority] ?? 'media';
            $impact = (array) ($item['impact'] ?? []);
            $coolingActive = !empty($item['cooling_active']);
            $amountRaw = number_format((int) $item['monto_estimado_centavos'] / 100, 2, '.', '');
            ?>
            <article class="wishlist-card <?= $isActive ? '' : 'is-closed' ?>">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                        <h2 class="h6 mb-1"><?= e((string) $item['nombre']) ?></h2>
                        <span class="badge priority-<?= $priority ?>">
                            <?= e(__('wishlist.priorities.' . $priority)) ?>
                        </span>
                        <?php if (!$isActive): ?>
                            <span class="badge text-bg-secondary ms-1">
                                <?= e(__('wishlist.states.' . $itemState)) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="wishlist-amount"><?= money_html((int) $item['monto_estimado_centavos'], (string) $item['moneda']) ?></span>
                </div>

                <div class="wishlist-meta">
                    <?php if (!empty($item['fecha_objetivo'])): ?>
                        <span class="small text-secondary">
                            <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>
                            <?= e(__('wishlist.card.target', [':date' => $dateOnly($item['fecha_objetivo'] ?? null)])) ?>
                        </span>
                    <?php else: ?>
                        <span class="small text-secondary">
                            <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>
                            <?= e(__('wishlist.card.no_target')) ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($coolingActive): ?>
                        <span class="badge text-bg-warning">
                            <?= e(__('wishlist.cooling.active', [':date' => $dateOnly($item['fecha_enfriamiento'] ?? null)])) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($item['nota'])): ?>
                    <p class="small text-secondary mb-0"><?= e((string) $item['nota']) ?></p>
                <?php endif; ?>

                <?php if ($isActive): ?>
                    <div class="wishlist-impact">
                        <span class="small text-secondary d-block"><?= e(__('wishlist.impact.title')) ?></span>
                        <?php if ($savingsTotal <= 0): ?>
                            <span class="small text-secondary"><?= e(__('wishlist.impact.no_savings')) ?></span>
                        <?php else: ?>
                            <span class="small d-block">
                                <?= __('wishlist.impact.line', [
                                    ':amount' => money_html((int) ($impact['after'] ?? 0), $currency),
                                ]) ?>
                            </span>
                            <span class="small text-secondary">
                                <?= e(__('wishlist.impact.percent', [':percent' => $pct((float) ($impact['percent'] ?? 0))])) ?>
                            </span>
                            <?php if (!empty($impact['exceeds'])): ?>
                                <span class="small text-danger d-block"><?= e(__('wishlist.impact.exceeds')) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($itemState === 'comprado'): ?>
                    <p class="small text-secondary mb-0">
                        <?= e(__('wishlist.card.purchased_at', [
                            ':date' => format_datetime($item['comprado_at'] ?? null),
                        ])) ?>
                        <?php if (!empty($item['gasto_id'])): ?>
                            · <a href="/gastos/ver?id=<?= (int) $item['gasto_id'] ?>"><?= e(__('wishlist.card.linked_expense')) ?></a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if ($isActive): ?>
                    <div class="wishlist-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-wishlist-edit
                                data-id="<?= (int) $item['id'] ?>"
                                data-name="<?= e((string) $item['nombre']) ?>"
                                data-amount="<?= e($amountRaw) ?>"
                                data-priority="<?= $priority ?>"
                                data-target-date="<?= e((string) ($item['fecha_objetivo'] ?? '')) ?>"
                                data-cooling-date="<?= e((string) ($item['fecha_enfriamiento'] ?? '')) ?>"
                                data-note="<?= e((string) ($item['nota'] ?? '')) ?>"
                                data-currency="<?= e((string) ($item['moneda'] ?? 'MXN')) ?>"
                                data-bs-toggle="offcanvas" data-bs-target="#wishlistEdit">
                            <?= e(__('wishlist.actions.edit')) ?>
                        </button>

                        <button type="button" class="btn btn-sm btn-primary"
                                data-wishlist-buy
                                data-id="<?= (int) $item['id'] ?>"
                                data-name="<?= e((string) $item['nombre']) ?>"
                                data-amount="<?= e($amountRaw) ?>"
                                data-cooling-active="<?= $coolingActive ? '1' : '0' ?>"
                                data-cooling-date="<?= e((string) ($item['fecha_enfriamiento'] ?? '')) ?>"
                                data-cooling-label="<?= e($dateOnly($item['fecha_enfriamiento'] ?? null)) ?>"
                                data-bs-toggle="offcanvas" data-bs-target="#wishlistBuy">
                            <?= e(__('wishlist.actions.buy')) ?>
                        </button>

                        <form method="post" action="/deseos/descartar" class="m-0"
                              onsubmit="return confirm('<?= e(__('wishlist.discard.confirm')) ?>')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <?= e(__('wishlist.actions.discard')) ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="offcanvas offcanvas-end" tabindex="-1" id="wishlistCreate"
     aria-labelledby="wishlistCreateTitle">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="wishlistCreateTitle"><?= e(__('wishlist.add')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="<?= e(__('wishlist.actions.cancel')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/deseos" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12">
                <label class="form-label" for="wishlist-name"><?= e(__('wishlist.fields.name')) ?></label>
                <input type="text" class="form-control" id="wishlist-name" name="nombre"
                       maxlength="120" required placeholder="<?= e(__('wishlist.fields.name_placeholder')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-amount"><?= e(__('wishlist.fields.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" inputmode="decimal" class="form-control" id="wishlist-amount"
                           name="monto" placeholder="0.00" required>
                    <span class="input-group-text"><?= e($currency) ?></span>
                </div>
            </div>
            <div class="col-12">
                <?= partial('partials/currency-select', ['name' => 'moneda', 'id' => 'wishlist-create-currency', 'selected' => (string) ($_POST['moneda'] ?? ($currency ?? 'MXN'))]) ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-priority"><?= e(__('wishlist.fields.priority')) ?></label>
                <select class="form-select" id="wishlist-priority" name="prioridad">
                    <?php foreach ([1, 2, 3] as $priority): ?>
                        <option value="<?= $priority ?>" <?= $priority === 2 ? 'selected' : '' ?>>
                            <?= e(__('wishlist.priorities.' . $priority)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-target"><?= e(__('wishlist.fields.target_date')) ?></label>
                <input type="date" class="form-control" id="wishlist-target" name="fecha_objetivo">
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-cooling"><?= e(__('wishlist.fields.cooling')) ?></label>
                <select class="form-select" id="wishlist-cooling" name="dias_enfriamiento">
                    <option value="0"><?= e(__('wishlist.cooling.none')) ?></option>
                    <option value="7"><?= e(__('wishlist.cooling.days_7')) ?></option>
                    <option value="15"><?= e(__('wishlist.cooling.days_15')) ?></option>
                    <option value="30"><?= e(__('wishlist.cooling.days_30')) ?></option>
                </select>
                <span class="form-text"><?= e(__('wishlist.fields.cooling_hint')) ?></span>
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-cooling-date"><?= e(__('wishlist.cooling.custom')) ?></label>
                <input type="date" class="form-control" id="wishlist-cooling-date" name="fecha_enfriamiento">
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-note"><?= e(__('wishlist.fields.note')) ?></label>
                <textarea class="form-control" id="wishlist-note" name="nota" rows="2" maxlength="255"
                          placeholder="<?= e(__('wishlist.fields.note_placeholder')) ?>"></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><?= e(__('wishlist.actions.create')) ?></button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">
                    <?= e(__('wishlist.actions.cancel')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="wishlistEdit"
     aria-labelledby="wishlistEditTitle">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="wishlistEditTitle"><?= e(__('wishlist.edit')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="<?= e(__('wishlist.actions.cancel')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/deseos/actualizar" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="wishlist-edit-id" value="0">
            <div class="col-12">
                <label class="form-label" for="wishlist-edit-name"><?= e(__('wishlist.fields.name')) ?></label>
                <input type="text" class="form-control" id="wishlist-edit-name" name="nombre"
                       maxlength="120" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-edit-amount"><?= e(__('wishlist.fields.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" inputmode="decimal" class="form-control" id="wishlist-edit-amount"
                           name="monto" placeholder="0.00" required>
                    <span class="input-group-text"><?= e($currency) ?></span>
                </div>
            </div>
            <div class="col-12">
                <?= partial('partials/currency-select', ['name' => 'moneda', 'id' => 'wishlist-edit-currency', 'selected' => (string) ($_POST['moneda'] ?? ($currency ?? 'MXN'))]) ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-edit-priority"><?= e(__('wishlist.fields.priority')) ?></label>
                <select class="form-select" id="wishlist-edit-priority" name="prioridad">
                    <?php foreach ([1, 2, 3] as $priority): ?>
                        <option value="<?= $priority ?>"><?= e(__('wishlist.priorities.' . $priority)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-edit-target"><?= e(__('wishlist.fields.target_date')) ?></label>
                <input type="date" class="form-control" id="wishlist-edit-target" name="fecha_objetivo">
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-edit-cooling"><?= e(__('wishlist.fields.cooling')) ?></label>
                <select class="form-select" id="wishlist-edit-cooling" name="dias_enfriamiento">
                    <option value="0"><?= e(__('wishlist.cooling.none')) ?></option>
                    <option value="7"><?= e(__('wishlist.cooling.days_7')) ?></option>
                    <option value="15"><?= e(__('wishlist.cooling.days_15')) ?></option>
                    <option value="30"><?= e(__('wishlist.cooling.days_30')) ?></option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-edit-cooling-date"><?= e(__('wishlist.cooling.custom')) ?></label>
                <input type="date" class="form-control" id="wishlist-edit-cooling-date" name="fecha_enfriamiento">
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-edit-note"><?= e(__('wishlist.fields.note')) ?></label>
                <textarea class="form-control" id="wishlist-edit-note" name="nota" rows="2" maxlength="255"></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><?= e(__('wishlist.actions.save')) ?></button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">
                    <?= e(__('wishlist.actions.cancel')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="wishlistBuy"
     aria-labelledby="wishlistBuyTitle">
    <div class="offcanvas-header">
        <div>
            <span class="small text-secondary d-block"><?= e(__('wishlist.actions.buy')) ?></span>
            <h2 class="offcanvas-title h5 mb-0" id="wishlistBuyTitle"><?= e(__('wishlist.buy.title')) ?></h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="<?= e(__('wishlist.actions.cancel')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/deseos/comprar" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="wishlist-buy-id" value="0">
            <div class="col-12">
                <p class="fw-semibold mb-0" data-wishlist-buy-name></p>
                <p class="small text-secondary mb-0"><?= e(__('wishlist.buy.hint')) ?></p>
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-buy-amount"><?= e(__('wishlist.buy.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" inputmode="decimal" class="form-control" id="wishlist-buy-amount"
                           name="monto" required>
                    <span class="input-group-text"><?= e($currency) ?></span>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-buy-date"><?= e(__('wishlist.buy.date')) ?></label>
                <input type="date" class="form-control" id="wishlist-buy-date" name="fecha"
                       value="<?= e($today) ?>" required>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="wishlist-register-expense"
                           name="registrar_gasto" value="1">
                    <label class="form-check-label" for="wishlist-register-expense">
                        <?= e(__('wishlist.buy.register_expense')) ?>
                    </label>
                    <div class="form-text"><?= e(__('wishlist.buy.register_hint')) ?></div>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="wishlist-buy-category"><?= e(__('wishlist.buy.category')) ?></label>
                <select class="form-select" id="wishlist-buy-category" name="parte_metodo">
                    <option value=""><?= e(__('wishlist.buy.category_hint')) ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['nombre']) ?>"><?= e((string) $category['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-none" data-wishlist-cooling-warning>
                <div class="alert alert-warning py-2 small mb-0" data-wishlist-cooling-text></div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="wishlist-skip-cooling"
                           name="saltar_enfriamiento" value="1">
                    <label class="form-check-label" for="wishlist-skip-cooling">
                        <?= e(__('wishlist.buy.skip_cooling')) ?>
                    </label>
                </div>
            </div>
            <div class="col-12">
                <div class="wishlist-impact">
                    <span class="small text-secondary d-block"><?= e(__('wishlist.impact.title')) ?></span>
                    <span class="small" data-wishlist-impact></span>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><?= e(__('wishlist.buy.submit')) ?></button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">
                    <?= e(__('wishlist.actions.cancel')) ?>
                </button>
            </div>
        </form>
    </div>
</div>
