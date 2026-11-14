<?php
?>
<div class="card border-0 shadow-sm auth-card">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 fw-bold mb-2"><?= e(__('auth.recovery.heading')) ?></h1>
        <p class="text-secondary mb-4"><?= e(__('auth.recovery.subtitle')) ?></p>

        <div class="d-grid gap-3">
            <a href="/recuperar/preguntas" class="text-decoration-none">
                <div class="card border-0 bg-light h-100 recovery-option">
                    <div class="card-body">
                        <h2 class="h6 mb-1"><?= e(__('auth.recovery.questions_title')) ?></h2>
                        <p class="small text-secondary mb-0"><?= e(__('auth.recovery.questions_desc')) ?></p>
                    </div>
                </div>
            </a>

            <a href="/recuperar/codigo" class="text-decoration-none">
                <div class="card border-0 bg-light h-100 recovery-option">
                    <div class="card-body">
                        <h2 class="h6 mb-1"><?= e(__('auth.recovery.code_title')) ?></h2>
                        <p class="small text-secondary mb-0"><?= e(__('auth.recovery.code_desc')) ?></p>
                    </div>
                </div>
            </a>

            <a href="/recuperar/contacto" class="text-decoration-none">
                <div class="card border-0 bg-light h-100 recovery-option">
                    <div class="card-body">
                        <h2 class="h6 mb-1"><?= e(__('auth.recovery.contact_title')) ?></h2>
                        <p class="small text-secondary mb-0"><?= e(__('auth.recovery.contact_desc')) ?></p>
                    </div>
                </div>
            </a>
        </div>

        <div class="mt-4 small">
            <a href="/login"><?= e(__('auth.recovery.back_to_login')) ?></a>
        </div>
    </div>
</div>
