<?php
/**
 * Portada pequeña de una tarjeta, con botones para cambiarla o quitarla.
 *
 * @var string $target  deseo | meta | bolsa | deuda | posicion
 * @var int $id
 * @var ?string $path   valor de portada_path
 * @var string $name    nombre de la tarjeta (texto alternativo)
 * @var string $icon    icono de Bootstrap Icons para la portada vacía
 * @var bool $small     versión compacta para filas de lista
 * @var bool $editable
 */
$target = (string) ($target ?? '');
$id = (int) ($id ?? 0);
$path = isset($path) && is_string($path) && $path !== '' ? $path : null;
$name = (string) ($name ?? '');
$icon = (string) ($icon ?? 'image');
$small = !empty($small);
$editable = !isset($editable) || (bool) $editable;
$inputId = 'cover-' . $target . '-' . $id;
?>
<div class="card-cover <?= $small ? 'card-cover-sm' : '' ?> <?= $path === null ? 'is-empty' : '' ?>">
    <?php if ($path !== null): ?>
        <img src="/portadas/ver?tipo=<?= e($target) ?>&amp;id=<?= $id ?>&amp;v=<?= e(substr(md5($path), 0, 8)) ?>"
             alt="<?= e(__('covers.alt', [':name' => $name])) ?>"
             width="200" height="200" loading="lazy" decoding="async">
    <?php else: ?>
        <span class="card-cover-placeholder" aria-hidden="true"><i class="bi bi-<?= e($icon) ?>"></i></span>
    <?php endif; ?>

    <?php if ($editable && $id > 0): ?>
        <form method="post" action="/portadas" enctype="multipart/form-data" class="card-cover-upload">
            <?= csrf_field() ?>
            <input type="hidden" name="tipo" value="<?= e($target) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <label class="card-cover-btn card-cover-edit" for="<?= e($inputId) ?>"
                   title="<?= e(__($path === null ? 'covers.add' : 'covers.change')) ?>">
                <i class="bi bi-camera" aria-hidden="true"></i>
                <span class="visually-hidden"><?= e(__($path === null ? 'covers.add' : 'covers.change')) ?>: <?= e($name) ?></span>
            </label>
            <input type="file" class="visually-hidden" id="<?= e($inputId) ?>" name="portada"
                   accept="image/jpeg,image/png,image/webp,image/gif" data-cover-input
                   data-cover-too-big="<?= e(__('covers.errors.too_big')) ?>">
            <noscript><button type="submit" class="btn btn-sm btn-outline-secondary mt-1"><?= e(__('covers.change')) ?></button></noscript>
        </form>

        <?php if ($path !== null): ?>
            <form method="post" action="/portadas" class="card-cover-remove"
                  onsubmit="return confirm('<?= e(__('covers.remove_confirm')) ?>')">
                <?= csrf_field() ?>
                <input type="hidden" name="tipo" value="<?= e($target) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="quitar" value="1">
                <button type="submit" class="card-cover-btn" title="<?= e(__('covers.remove')) ?>">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                    <span class="visually-hidden"><?= e(__('covers.remove')) ?>: <?= e($name) ?></span>
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
