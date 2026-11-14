<?php
$customInstruments = $customInstruments ?? [];
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('investment.custom.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('investment.custom.hint')) ?></p>
    </div>
    <a href="/inversion" class="btn btn-outline-secondary"><?= e(__('investment.custom.back')) ?></a>
</div>

<div class="surface-card mb-3">
    <form method="post" action="/inversion/tipos" class="d-flex flex-wrap gap-2 align-items-end">
        <?= csrf_field() ?>
        <div class="flex-grow-1">
            <label class="form-label" for="instrument_new_name"><?= e(__('investment.custom.name')) ?></label>
            <input type="text" class="form-control" id="instrument_new_name" name="nombre" minlength="2" maxlength="60"
                   placeholder="<?= e(__('investment.custom.placeholder')) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary"><?= e(__('investment.custom.add')) ?></button>
    </form>
</div>

<div class="surface-card">
    <?php if ($customInstruments === []): ?>
        <p class="text-secondary small mb-0"><?= e(__('investment.custom.empty')) ?></p>
    <?php else: ?>
        <div class="finance-list">
            <?php foreach ($customInstruments as $item): ?>
                <div class="finance-row flex-wrap">
                    <form method="post" action="/inversion/tipos/editar" class="d-flex gap-2 flex-grow-1 m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <input type="text" class="form-control" name="nombre" value="<?= e((string) $item['nombre']) ?>"
                               minlength="2" maxlength="60" required data-no-help
                               aria-label="<?= e(__('investment.custom.name')) ?>">
                        <button type="submit" class="btn btn-outline-secondary"><?= e(__('investment.custom.save')) ?></button>
                    </form>
                    <form method="post" action="/inversion/tipos/eliminar" class="m-0"
                          onsubmit="return confirm('<?= e(__('investment.custom.confirm_delete')) ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger" aria-label="<?= e(__('investment.custom.delete')) ?>">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
