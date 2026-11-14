<?php
$document = $document ?? [];
$content = (string) ($content ?? '');
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-9">
        <article class="surface-card legal-document">
            <header class="mb-4">
                <span class="badge phase-badge mb-2"><?= e(__('legal.badge')) ?></span>
                <h1 class="h3 mb-2"><?= e((string) ($document['titulo_es'] ?? '')) ?></h1>
                <p class="small text-secondary mb-0">
                    <?= e(__('legal.version', [':version' => (string) ($document['version'] ?? '')])) ?>
                    · <?= e(format_datetime($document['published_at'] ?? null)) ?>
                </p>
            </header>

            <div class="legal-body"><?= nl2br(e($content)) ?></div>

            <hr class="my-4">

            <nav class="d-flex flex-wrap gap-2 small">
                <a href="/privacidad"><?= e(__('legal.titles.privacidad')) ?></a>
                <a href="/terminos"><?= e(__('legal.titles.terminos')) ?></a>
                <a href="/aviso-legal"><?= e(__('legal.titles.aviso')) ?></a>
            </nav>
        </article>
    </div>
</div>
