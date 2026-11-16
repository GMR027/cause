<?php

$warnings = $warnings ?? [];
?>
<?php if ($warnings !== []): ?>
    <div class="planner-warnings" role="status">
        <?php foreach ($warnings as $warning): ?>
            <?php
            $key = is_array($warning) ? (string) ($warning['key'] ?? '') : (string) $warning;
            $params = is_array($warning) && is_array($warning['params'] ?? null) ? $warning['params'] : [];
            $message = $key !== '' ? __($key, $params) : '';
            ?>
            <?php if ($message !== ''): ?>
                <div class="alert alert-warning py-2 mb-0 d-flex align-items-start gap-2">
                    <i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i>
                    <span><?= e($message) ?></span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
