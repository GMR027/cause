<div class="card border-0 shadow-sm auth-card">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 fw-bold mb-2"><?= e(__('auth.recovery.contact_heading')) ?></h1>
        <p class="text-secondary mb-4"><?= e(__('auth.recovery.contact_body')) ?></p>

        <div class="alert alert-info">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-life-preserver mt-1" aria-hidden="true"></i>
                <div>
                    <strong class="d-block"><?= e(__('auth.recovery.contact_channel')) ?></strong>
                    <span class="small"><?= e(__('auth.recovery.contact_hint')) ?></span>
                </div>
            </div>
        </div>

        <div class="d-grid gap-2">
            <a href="/login?next=%2Fsoporte" class="btn btn-primary"><?= e(__('auth.recovery.contact_login')) ?></a>
            <a href="/recuperar" class="btn btn-outline-secondary"><?= e(__('common.actions.back')) ?></a>
        </div>
    </div>
</div>
