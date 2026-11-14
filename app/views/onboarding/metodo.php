<?php

$methods = $methods ?? [];
$recommended = $recommended ?? null;
$recommendedMethod = $recommendedMethod ?? null;
$errors = $errors ?? [];
$selected = (string) ($state['metodo'] ?? '');
?>
<div class="onboarding-card surface-card">
    <h1 class="h4 mb-1"><?= e(__('onboarding.metodo.heading')) ?></h1>
    <p class="text-secondary mb-4"><?= e(__('onboarding.metodo.subtitle')) ?></p>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <div class="row g-3">
        <?php foreach ($methods as $method): ?>
            <?php
            $code = (string) $method['codigo'];
            $nameKey = 'onboarding.methods.' . $code . '.name';
            $descKey = 'onboarding.methods.' . $code . '.desc';
            $name = __($nameKey);
            $name = $name === $nameKey ? (string) $method['nombre'] : $name;
            $description = __($descKey);
            $description = $description === $descKey ? (string) $method['descripcion_corta'] : $description;
            $isRecommended = $recommended !== null && $recommended === $code;
            ?>
            <div class="col-12 col-md-6">
                <div class="method-card <?= $isRecommended ? 'is-recommended' : '' ?> <?= $selected === $code ? 'is-selected' : '' ?>">
                    <?php if ($isRecommended): ?>
                        <span class="badge text-bg-success mb-2">
                            <i class="bi bi-stars me-1" aria-hidden="true"></i><?= e(__('onboarding.metodo.recommended_badge')) ?>
                        </span>
                    <?php endif; ?>

                    <div class="d-flex align-items-start gap-3 mb-2">
                        <span class="method-icon" aria-hidden="true">
                            <i class="bi <?= e((string) $method['icono']) ?>"></i>
                        </span>
                        <div>
                            <h2 class="h5 mb-1"><?= e($name) ?></h2>
                            <p class="text-secondary small mb-0"><?= e($description) ?></p>
                        </div>
                    </div>

                    <ul class="method-categories">
                        <?php foreach ((array) $method['categorias'] as $categoria): ?>
                            <?php if ($categoria['porcentaje'] !== null): ?>
                                <li>
                                    <span><?= e((string) $categoria['nombre']) ?></span>
                                    <span class="text-secondary"><?= (int) $categoria['porcentaje'] ?>%</span>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($method['es_cultural'])): ?>
                        <p class="cultural-note">
                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                            <?= e(__('onboarding.simulador.cultural_note')) ?>
                        </p>
                    <?php endif; ?>

                    <form method="post" action="/onboarding/metodo" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="metodo" value="<?= e($code) ?>">
                        <button type="submit" class="btn <?= $isRecommended ? 'btn-primary' : 'btn-outline-primary' ?> w-100">
                            <?= e(__('onboarding.metodo.choose')) ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
