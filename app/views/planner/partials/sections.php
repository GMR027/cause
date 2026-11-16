<?php

$sections = $sections ?? [];
$currency = (string) ($currency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$pct = static fn (float $value): string => rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
?>
<?php if ($sections === []): ?>
    <p class="text-secondary mb-0"><?= e(__('planner.form.empty_section')) ?></p>
<?php else: ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($sections as $section): ?>
            <?php
            $isOrphan = (string) $section['nombre'] === '__sin_seccion';
            $label = $isOrphan ? __('planner.sections.sin_seccion') : (string) $section['nombre'];
            $assigned = (int) $section['asignado_centavos'];
            $committed = (int) $section['comprometido_centavos'];
            $available = (int) $section['disponible_centavos'];
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];
            $planned = (int) ($section['items_centavos'] ?? 0);
            $remaining = (int) ($section['items_disponible_centavos'] ?? $available);
            $ratio = $assigned > 0 ? min(100, (int) round(($committed / $assigned) * 100)) : ($committed > 0 ? 100 : 0);
            $over = !empty($section['excedido']) || !empty($section['items_excedido']);
            ?>
            <article class="planner-section<?= $over ? ' is-over' : '' ?>">
                <div class="planner-section-head">
                    <div class="planner-section-info">
                        <span class="planner-section-name">
                            <i class="bi <?= e((string) ($section['icon'] ?? 'bi-dot')) ?> text-primary" aria-hidden="true"></i>
                            <?= e((string) $label) ?>
                            <?php if (!$isOrphan): ?>
                                <span class="text-secondary small fw-normal">(<?= e(__('income.types_category.' . (string) ($section['tipo'] ?? 'controlada'))) ?>)</span>
                            <?php endif; ?>
                        </span>
                        <?php if (!$isOrphan && (float) ($section['porcentaje'] ?? 0) > 0): ?>
                            <span class="planner-section-percent">
                                <?= e(__('planner.sections.percent', [':percent' => $pct((float) $section['porcentaje'])])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($over): ?>
                            <span class="badge text-bg-danger"><?= e(__('planner.sections.over')) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="planner-section-metrics">
                    <div class="planner-metric">
                        <span class="planner-metric-label"><?= e(__('planner.sections.assigned')) ?></span>
                        <span class="planner-metric-value"><?= money_html($assigned, $currency) ?></span>
                    </div>
                    <div class="planner-metric">
                        <span class="planner-metric-label"><?= e(__('planner.sections.committed')) ?></span>
                        <span class="planner-metric-value"><?= money_html($committed, $currency) ?></span>
                    </div>
                    <div class="planner-metric">
                        <span class="planner-metric-label"><?= e(__('planner.sections.available')) ?></span>
                        <span class="planner-metric-value<?= $available < 0 ? ' is-negative' : '' ?>">
                            <?= money_html($available, $currency) ?>
                        </span>
                    </div>
                </div>

                <div class="planner-progress" role="img"
                     aria-label="<?= e(__('planner.sections.committed')) ?>: <?= e(format_money($committed, $currency)) ?>">
                    <div class="planner-progress-bar<?= $ratio >= 100 && $over ? ' is-over' : '' ?>"
                         style="width: <?= $ratio ?>%"></div>
                </div>

                <?php if ($items !== []): ?>
                    <div class="planner-section-foot">
                        <span><?= e(__('planner.sections.planned')) ?>: <?= money_html($planned, $currency) ?></span>
                        <span class="<?= $remaining < 0 ? 'is-negative' : '' ?>">
                            <?= e(__('planner.sections.remaining')) ?>: <?= money_html($remaining, $currency) ?>
                        </span>
                    </div>
                    <ul class="planner-items">
                        <?php foreach ($items as $item): ?>
                            <li class="planner-item">
                                <span><?= e((string) $item['concepto']) ?></span>
                                <span class="planner-item-amount"><?= money_html((int) $item['monto_centavos'], $currency) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (!$isOrphan): ?>
                    <p class="small text-secondary mb-0"><?= e(__('planner.sections.no_items')) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
