<?php
?>
<div class="flow-card surface-card text-center">
    <span class="tutorial-icon mx-auto" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
    <h1 class="h4 mb-2"><?= e(__('expenses.blocked.title')) ?></h1>
    <p class="text-secondary mb-4"><?= e(__('expenses.blocked.body')) ?></p>

    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
        <a href="/ingresos/nuevo" class="btn btn-primary"><?= e(__('expenses.blocked.cta')) ?></a>
        <a href="/inicio" class="btn btn-outline-secondary"><?= e(__('common.actions.back')) ?></a>
    </div>
</div>
