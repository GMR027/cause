<?php

$commitments = $commitments ?? [];
$currency = (string) ($currency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$showSection = (bool) ($showSection ?? true);
$dateOnly = static fn (?string $date): string => $date !== null && trim($date) !== ''
    ? format_date($date . ' 12:00:00')
    : '—';
?>
<?php if ($commitments === []): ?>
    <p class="text-secondary mb-0"><?= e(__('planner.commitments.empty')) ?></p>
<?php else: ?>
    <div class="planner-commitments">
        <?php foreach ($commitments as $commitment): ?>
            <div class="planner-commitment">
                <div class="planner-commitment-main">
                    <span class="planner-commitment-name"><?= e((string) $commitment['nombre']) ?></span>
                    <div class="planner-commitment-meta">
                        <span class="badge text-bg-light">
                            <?= e(__('planner.commitments.types.' . (string) ($commitment['tipo'] ?? 'recurrente'))) ?>
                        </span>
                        <?php if (!empty($commitment['es_suscripcion'])): ?>
                            <span class="badge text-bg-info"><?= e(__('planner.commitments.badges.suscripcion')) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($commitment['monto_variable'])): ?>
                            <span class="badge text-bg-secondary"><?= e(__('planner.commitments.badges.variable')) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($commitment['no_controlado'])): ?>
                            <span class="badge text-bg-secondary"><?= e(__('planner.commitments.badges.no_controlado')) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($commitment['vencido'])): ?>
                            <span class="badge text-bg-danger"><?= e(__('planner.commitments.badges.vencido')) ?></span>
                        <?php elseif (!empty($commitment['hoy'])): ?>
                            <span class="badge text-bg-warning"><?= e(__('planner.commitments.badges.hoy')) ?></span>
                        <?php endif; ?>
                        <span class="badge text-bg-light">
                            <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>
                            <?= e($dateOnly($commitment['fecha'] ?? null)) ?>
                        </span>
                        <?php if ($showSection): ?>
                            <?php $sectionName = (string) ($commitment['seccion'] ?? ''); ?>
                            <span class="badge text-bg-light">
                                <?= e(__('planner.commitments.section')) ?>:
                                <?= e($sectionName !== '' ? $sectionName : __('planner.sections.sin_seccion')) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="planner-commitment-amount">
                    <?= money_html((int) $commitment['monto_centavos'], $currency) ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
