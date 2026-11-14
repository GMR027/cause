<?php
?>
<div class="onboarding-card surface-card">
    <div id="tutorialCarousel" class="carousel slide" data-bs-interval="false" data-bs-touch="true">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#tutorialCarousel" data-bs-slide-to="0" class="active"
                    aria-current="true" aria-label="1"></button>
            <button type="button" data-bs-target="#tutorialCarousel" data-bs-slide-to="1" aria-label="2"></button>
            <button type="button" data-bs-target="#tutorialCarousel" data-bs-slide-to="2" aria-label="3"></button>
        </div>

        <div class="carousel-inner">
            <div class="carousel-item active">
                <div class="tutorial-slide">
                    <span class="tutorial-icon" aria-hidden="true"><i class="bi bi-compass"></i></span>
                    <h1 class="h4 mb-2"><?= e(__('onboarding.tutorial.slides.0.title')) ?></h1>
                    <p class="text-secondary mb-4"><?= e(__('onboarding.tutorial.slides.0.body')) ?></p>
                    <button type="button" class="btn btn-primary" data-bs-target="#tutorialCarousel" data-bs-slide="next">
                        <?= e(__('common.actions.continue')) ?>
                    </button>
                </div>
            </div>

            <div class="carousel-item">
                <div class="tutorial-slide">
                    <span class="tutorial-icon" aria-hidden="true"><i class="bi bi-pie-chart"></i></span>
                    <h2 class="h4 mb-2"><?= e(__('onboarding.tutorial.slides.1.title')) ?></h2>
                    <p class="text-secondary mb-4"><?= e(__('onboarding.tutorial.slides.1.body')) ?></p>
                    <button type="button" class="btn btn-primary" data-bs-target="#tutorialCarousel" data-bs-slide="next">
                        <?= e(__('common.actions.continue')) ?>
                    </button>
                </div>
            </div>

            <div class="carousel-item">
                <div class="tutorial-slide">
                    <span class="tutorial-icon" aria-hidden="true"><i class="bi bi-hand-index-thumb"></i></span>
                    <h2 class="h4 mb-2"><?= e(__('onboarding.tutorial.slides.2.title')) ?></h2>
                    <p class="text-secondary mb-4"><?= e(__('onboarding.tutorial.slides.2.body')) ?></p>
                    <form method="post" action="/onboarding/tutorial">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary"><?= e(__('onboarding.tutorial.start')) ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
