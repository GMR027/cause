<?php

use Cauce\Services\RepartoService;

$repartoService = new RepartoService();
$amount = (int) ($state['monto_centavos'] ?? 0);
$amountValue = number_format($amount / 100, 2, '.', '');
$preview = (string) ($state['metodo_preview'] ?? '');
$recommended = $recommended ?? null;
$recommendedMethod = $recommendedMethod ?? null;
$quiz = is_array($state['quiz'] ?? null) ? $state['quiz'] : [];
$errors = $errors ?? [];
$methods = $methods ?? [];

$codes = array_map(static fn (array $method): string => (string) $method['codigo'], $methods);

if (!in_array($preview, $codes, true)) {
    $preview = $codes[0] ?? '';
}
?>
<div class="onboarding-card surface-card">
    <h1 class="h4 mb-1"><?= e(__('onboarding.simulador.heading')) ?></h1>
    <p class="text-secondary mb-4"><?= e(__('onboarding.simulador.subtitle')) ?></p>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <?php if ($recommendedMethod !== null): ?>
        <?php
        $recCode = (string) ($recommendedMethod['codigo'] ?? '');
        $recKey = 'onboarding.methods.' . $recCode . '.name';
        $recName = __($recKey);
        $recName = $recName === $recKey ? (string) ($recommendedMethod['nombre'] ?? '') : $recName;
        ?>
        <div class="alert alert-success d-flex align-items-center gap-2" role="status">
            <i class="bi bi-stars" aria-hidden="true"></i>
            <span>
                <?= e(__('onboarding.simulador.recommended', [':method' => $recName])) ?>
            </span>
        </div>
    <?php endif; ?>

    <form method="post" action="/onboarding/simulador" data-simulator>
        <?= csrf_field() ?>
        <input type="hidden" name="metodo_preview" value="<?= e($preview) ?>" data-sim-metodo>

        <div class="mb-4">
            <label class="form-label fw-semibold" for="monto"><?= e(__('onboarding.simulador.amount_label')) ?></label>
            <div class="input-group input-group-lg">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" id="monto" name="monto"
                       value="<?= e($amountValue) ?>" min="0" step="0.01" inputmode="decimal"
                       data-sim-amount aria-describedby="monto-help">
                <span class="input-group-text"><?= e((string) ($state['moneda'] ?? 'MXN')) ?></span>
            </div>
            <div class="form-text" id="monto-help"><?= e(__('onboarding.simulador.amount_hint')) ?></div>
        </div>

        <div class="nav nav-pills sim-tabs mb-3" role="tablist">
            <?php foreach ($methods as $index => $method): ?>
                <?php
                $code = (string) $method['codigo'];
                $paneId = 'pane-' . str_replace('_', '-', $code);
                $tabId = 'tab-' . str_replace('_', '-', $code);
                $nameKey = 'onboarding.methods.' . $code . '.name';
                $name = __($nameKey);
                $name = $name === $nameKey ? (string) $method['nombre'] : $name;
                $isActive = $code === $preview;
                $isRecommended = $recommended !== null && $recommended === $code;
                ?>
                <button class="nav-link <?= $isActive ? 'active' : '' ?>" id="<?= e($tabId) ?>"
                        data-bs-toggle="pill" data-bs-target="#<?= e($paneId) ?>" type="button"
                        role="tab" aria-controls="<?= e($paneId) ?>" aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                        data-sim-tab="<?= e($code) ?>">
                    <i class="bi <?= e((string) $method['icono']) ?> me-1" aria-hidden="true"></i>
                    <?= e($name) ?>
                    <?php if ($isRecommended): ?>
                        <span class="badge text-bg-success ms-1"><?= e(__('onboarding.simulador.recommended_badge')) ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="tab-content">
            <?php foreach ($methods as $method): ?>
                <?php
                $code = (string) $method['codigo'];
                $paneId = 'pane-' . str_replace('_', '-', $code);
                $nameKey = 'onboarding.methods.' . $code . '.name';
                $descKey = 'onboarding.methods.' . $code . '.desc';
                $name = __($nameKey);
                $name = $name === $nameKey ? (string) $method['nombre'] : $name;
                $description = __($descKey);
                $description = $description === $descKey ? (string) $method['descripcion_corta'] : $description;
                $map = [];

                foreach ((array) $method['categorias'] as $categoria) {
                    if ($categoria['porcentaje'] !== null) {
                        $map[(string) $categoria['nombre']] = (int) $categoria['porcentaje'];
                    }
                }

                $split = $map === [] ? [] : $repartoService->repartirIngreso($amount, $map);
                ?>
                <div class="tab-pane fade <?= $code === $preview ? 'show active' : '' ?>" id="<?= e($paneId) ?>"
                     role="tabpanel" aria-labelledby="tab-<?= e(str_replace('_', '-', $code)) ?>"
                     data-sim-pane="<?= e($code) ?>"
                     data-porcentajes="<?= e((string) json_encode($map, JSON_UNESCAPED_UNICODE)) ?>">
                    <div class="sim-card">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <div>
                                <h2 class="h5 mb-1"><?= e($name) ?></h2>
                                <p class="text-secondary small mb-0"><?= e($description) ?></p>
                            </div>
                            <i class="bi <?= e((string) $method['icono']) ?> sim-card-icon" aria-hidden="true"></i>
                        </div>

                        <ul class="sim-list">
                            <?php foreach ($map as $categoryName => $percentage): ?>
                                <li>
                                    <span class="sim-cat"><?= e((string) $categoryName) ?></span>
                                    <span class="sim-pct"><?= (int) $percentage ?>%</span>
                                    <span class="sim-amount" data-cat="<?= e((string) $categoryName) ?>">
                                        <?= money_html((int) ($split[$categoryName] ?? 0), (string) ($state['moneda'] ?? 'MXN')) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if (!empty($method['es_cultural'])): ?>
                            <p class="cultural-note mb-0">
                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                                <?= e(__('onboarding.simulador.cultural_note')) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="quiz-box mt-4">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse"
                    data-bs-target="#quizBox" aria-expanded="false" aria-controls="quizBox">
                <i class="bi bi-question-circle me-1" aria-hidden="true"></i>
                <?= e(__('onboarding.simulador.quiz_button')) ?>
            </button>

            <div class="collapse mt-3" id="quizBox">
                <div class="quiz-inner">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small" for="quiz-p1"><?= e(__('onboarding.simulador.quiz.q1')) ?></label>
                            <select class="form-select" id="quiz-p1" name="quiz[p1]">
                                <option value=""><?= e(__('onboarding.simulador.quiz.choose')) ?></option>
                                <option value="fijo" <?= ($quiz['p1'] ?? '') === 'fijo' ? 'selected' : '' ?>><?= e(__('onboarding.simulador.quiz.p1.fijo')) ?></option>
                                <option value="variable" <?= ($quiz['p1'] ?? '') === 'variable' ? 'selected' : '' ?>><?= e(__('onboarding.simulador.quiz.p1.variable')) ?></option>
                                <option value="mixto" <?= ($quiz['p1'] ?? '') === 'mixto' ? 'selected' : '' ?>><?= e(__('onboarding.simulador.quiz.p1.mixto')) ?></option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small" for="quiz-p2"><?= e(__('onboarding.simulador.quiz.q2')) ?></label>
                            <select class="form-select" id="quiz-p2" name="quiz[p2]">
                                <option value=""><?= e(__('onboarding.simulador.quiz.choose')) ?></option>
                                <option value="si" <?= ($quiz['p2'] ?? '') === 'si' ? 'selected' : '' ?>><?= e(__('onboarding.simulador.quiz.p2.si')) ?></option>
                                <option value="no" <?= ($quiz['p2'] ?? '') === 'no' ? 'selected' : '' ?>><?= e(__('onboarding.simulador.quiz.p2.no')) ?></option>
                                <option value="no_seguro" <?= ($quiz['p2'] ?? '') === 'no_seguro' ? 'selected' : '' ?>><?= e(__('onboarding.simulador.quiz.p2.no_seguro')) ?></option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small" for="quiz-p3"><?= e(__('onboarding.simulador.quiz.q3')) ?></label>
                            <select class="form-select" id="quiz-p3" name="quiz[p3]">
                                <option value=""><?= e(__('onboarding.simulador.quiz.choose')) ?></option>
                                <option value="ahorrar" <?= ($quiz['p3'] ?? '') === 'ahorrar' ? 'selected' : '' ?>><?= e(__('onboarding.simulador.quiz.p3.ahorrar')) ?></option>
                                <option value="invertir" <?= ($quiz['p3'] ?? '') === 'invertir' ? 'selected' : '' ?>><?= e(__('onboarding.simulador.quiz.p3.invertir')) ?></option>
                                <option value="libertad" <?= ($quiz['p3'] ?? '') === 'libertad' ? 'selected' : '' ?>><?= e(__('onboarding.simulador.quiz.p3.libertad')) ?></option>
                                <option value="donar" <?= ($quiz['p3'] ?? '') === 'donar' ? 'selected' : '' ?>><?= e(__('onboarding.simulador.quiz.p3.donar')) ?></option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="accion" value="quiz" class="btn btn-outline-primary btn-sm mt-3">
                        <?= e(__('onboarding.simulador.quiz.submit')) ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="onboarding-actions">
            <button type="submit" name="accion" value="continuar" class="btn btn-primary">
                <?= e(__('common.actions.continue')) ?>
            </button>
        </div>
    </form>
</div>
