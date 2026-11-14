<?php
$features = lang_array('landing.features.items');
$steps = lang_array('landing.how.steps');
$methods = lang_array('landing.methods.items');
$controlPoints = lang_array('landing.control.points');
$privacyPoints = lang_array('landing.privacy.points');
$mockup = lang_array('landing.hero.mockup');
?>
<section class="landing-hero">
    <div class="landing-hero-orb landing-hero-orb-one" aria-hidden="true"></div>
    <div class="landing-hero-orb landing-hero-orb-two" aria-hidden="true"></div>

    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-reveal>
                <span class="landing-eyebrow">
                    <span class="landing-pulse" aria-hidden="true"></span>
                    <?= e(__('landing.hero.eyebrow')) ?>
                </span>

                <h1 class="landing-title">
                    <?= e(__('landing.hero.title_1')) ?>
                    <span class="landing-title-accent"><?= e(__('landing.hero.title_2')) ?></span>
                </h1>

                <p class="landing-lead"><?= e(__('landing.hero.lead')) ?></p>

                <div class="landing-cta">
                    <a href="/registro" class="btn landing-btn-primary">
                        <?= e(__('landing.hero.cta_primary')) ?>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                    <a href="#como-funciona" class="btn landing-btn-secondary">
                        <?= e(__('landing.hero.cta_secondary')) ?>
                    </a>
                </div>

                <ul class="landing-trust">
                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i><?= e(__('landing.hero.trust_1')) ?></li>
                    <li><i class="bi bi-shield-check" aria-hidden="true"></i><?= e(__('landing.hero.trust_2')) ?></li>
                    <li><i class="bi bi-currency-exchange" aria-hidden="true"></i><?= e(__('landing.hero.trust_3')) ?></li>
                </ul>
            </div>

            <div class="col-lg-6" data-reveal>
                <div class="landing-mockup-wrap">
                    <div class="landing-mockup">
                        <div class="mockup-top">
                            <div>
                                <span class="mockup-label"><?= e($mockup['period'] ?? '') ?></span>
                                <strong><?= e($mockup['period_dates'] ?? '') ?></strong>
                            </div>
                            <span class="mockup-dot" aria-hidden="true"></span>
                        </div>

                        <div class="mockup-balance">
                            <span class="mockup-label"><?= e($mockup['income'] ?? '') ?></span>
                            <strong><?= e($mockup['income_value'] ?? '') ?></strong>
                            <span class="mockup-available">
                                <?= e($mockup['available'] ?? '') ?>
                                <b><?= e($mockup['available_value'] ?? '') ?></b>
                            </span>
                        </div>

                        <div class="mockup-categories">
                            <div class="mockup-section-head">
                                <span><?= e($mockup['categories'] ?? '') ?></span>
                                <i class="bi bi-three-dots" aria-hidden="true"></i>
                            </div>

                            <div class="mockup-category">
                                <div class="mockup-category-row">
                                    <span><?= e($mockup['needs'] ?? '') ?></span>
                                    <span>50%</span>
                                </div>
                                <span class="mockup-bar"><span style="width: 82%"></span></span>
                            </div>
                            <div class="mockup-category">
                                <div class="mockup-category-row">
                                    <span><?= e($mockup['wants'] ?? '') ?></span>
                                    <span>30%</span>
                                </div>
                                <span class="mockup-bar"><span style="width: 64%"></span></span>
                            </div>
                            <div class="mockup-category">
                                <div class="mockup-category-row">
                                    <span><?= e($mockup['savings'] ?? '') ?></span>
                                    <span>20%</span>
                                </div>
                                <span class="mockup-bar"><span style="width: 46%"></span></span>
                            </div>
                        </div>

                        <div class="mockup-bottom">
                            <div class="mockup-score">
                                <span class="mockup-label"><?= e($mockup['score'] ?? '') ?></span>
                                <strong><?= e($mockup['score_value'] ?? '') ?></strong>
                                <span class="mockup-trend">
                                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                                    <?= e($mockup['score_trend'] ?? '') ?>
                                </span>
                            </div>
                            <div class="mockup-goal">
                                <span class="mockup-label"><?= e($mockup['goal'] ?? '') ?></span>
                                <strong><?= e($mockup['goal_value'] ?? '') ?></strong>
                                <span class="mockup-goal-bar"><span style="width: 45%"></span></span>
                            </div>
                        </div>
                    </div>

                    <div class="landing-float landing-float-projection">
                        <span class="landing-float-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
                        <div>
                            <small><?= e($mockup['projection'] ?? '') ?></small>
                            <strong><?= e($mockup['projection_value'] ?? '') ?></strong>
                        </div>
                    </div>

                    <div class="landing-float landing-float-debt">
                        <span class="landing-float-icon landing-float-icon-warm"><i class="bi bi-credit-card" aria-hidden="true"></i></span>
                        <div>
                            <small><?= e($mockup['debt'] ?? '') ?></small>
                            <strong><?= e($mockup['debt_value'] ?? '') ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="landing-methods" id="metodos">
    <div class="container">
        <div class="landing-methods-inner" data-reveal>
            <div class="landing-methods-copy">
                <span class="landing-kicker"><?= e(__('landing.methods.title')) ?></span>
                <p><?= e(__('landing.methods.hint')) ?></p>
            </div>
            <div class="landing-method-pills">
                <?php foreach ($methods as $method): ?>
                    <span class="landing-method-pill"><?= e((string) $method) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="landing-section" id="producto">
    <div class="container">
        <div class="landing-section-head" data-reveal>
            <span class="landing-eyebrow"><?= e(__('landing.features.eyebrow')) ?></span>
            <h2 class="landing-section-title"><?= e(__('landing.features.title')) ?></h2>
            <p class="landing-section-lead"><?= e(__('landing.features.lead')) ?></p>
        </div>

        <div class="landing-feature-grid">
            <?php foreach ($features as $index => $feature): ?>
                <article class="landing-feature-card" data-reveal style="--reveal-delay: <?= $index * 70 ?>ms">
                    <span class="landing-feature-icon">
                        <i class="bi <?= e((string) ($feature['icon'] ?? 'bi-stars')) ?>" aria-hidden="true"></i>
                    </span>
                    <h3><?= e((string) ($feature['title'] ?? '')) ?></h3>
                    <p><?= e((string) ($feature['desc'] ?? '')) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="landing-section landing-how" id="como-funciona">
    <div class="container">
        <div class="landing-section-head landing-section-head-center" data-reveal>
            <span class="landing-eyebrow"><?= e(__('landing.how.eyebrow')) ?></span>
            <h2 class="landing-section-title"><?= e(__('landing.how.title')) ?></h2>
            <p class="landing-section-lead"><?= e(__('landing.how.lead')) ?></p>
        </div>

        <div class="landing-steps">
            <?php foreach ($steps as $index => $step): ?>
                <article class="landing-step" data-reveal style="--reveal-delay: <?= $index * 90 ?>ms">
                    <span class="landing-step-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="landing-step-label"><?= e(__('landing.how.step_label')) ?> <?= $index + 1 ?></span>
                    <h3><?= e((string) ($step['title'] ?? '')) ?></h3>
                    <p><?= e((string) ($step['desc'] ?? '')) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="landing-section landing-control">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-reveal>
                <span class="landing-eyebrow"><?= e(__('landing.control.eyebrow')) ?></span>
                <h2 class="landing-section-title"><?= e(__('landing.control.title')) ?></h2>
                <p class="landing-section-lead"><?= e(__('landing.control.lead')) ?></p>

                <ul class="landing-check-list">
                    <?php foreach ($controlPoints as $point): ?>
                        <li>
                            <span class="landing-check"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                            <?= e((string) $point) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-lg-6" data-reveal>
                <div class="landing-report">
                    <div class="landing-report-head">
                        <div>
                            <span class="mockup-label"><?= e(__('landing.control.visual_hint')) ?></span>
                            <strong><?= e(__('landing.control.visual_title')) ?></strong>
                        </div>
                        <span class="landing-report-badge">Cauce</span>
                    </div>

                    <div class="landing-report-bars">
                        <div class="landing-report-bar">
                            <div class="landing-report-bar-label">
                                <span><?= e(__('landing.control.visual_income')) ?></span>
                                <strong>100%</strong>
                            </div>
                            <span class="landing-report-track"><span style="width: 100%"></span></span>
                        </div>
                        <div class="landing-report-bar">
                            <div class="landing-report-bar-label">
                                <span><?= e(__('landing.control.visual_expense')) ?></span>
                                <strong>68%</strong>
                            </div>
                            <span class="landing-report-track landing-report-track-warm"><span style="width: 68%"></span></span>
                        </div>
                        <div class="landing-report-bar">
                            <div class="landing-report-bar-label">
                                <span><?= e(__('landing.control.visual_savings')) ?></span>
                                <strong>22%</strong>
                            </div>
                            <span class="landing-report-track landing-report-track-cool"><span style="width: 22%"></span></span>
                        </div>
                        <div class="landing-report-bar">
                            <div class="landing-report-bar-label">
                                <span><?= e(__('landing.control.visual_available')) ?></span>
                                <strong>10%</strong>
                            </div>
                            <span class="landing-report-track landing-report-track-soft"><span style="width: 10%"></span></span>
                        </div>
                    </div>

                    <div class="landing-report-foot">
                        <span><i class="bi bi-check2-circle" aria-hidden="true"></i> <?= e(__('landing.control.visual_hint')) ?></span>
                        <span class="landing-report-pill"><?= e(__('landing.hero.mockup.score')) ?> 86</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="landing-privacy" id="privacidad">
    <div class="container">
        <div class="landing-privacy-inner" data-reveal>
            <div class="landing-privacy-copy">
                <span class="landing-eyebrow landing-eyebrow-light"><?= e(__('landing.privacy.eyebrow')) ?></span>
                <h2 class="landing-section-title text-white"><?= e(__('landing.privacy.title')) ?></h2>
                <p class="landing-section-lead landing-section-lead-light"><?= e(__('landing.privacy.lead')) ?></p>
                <span class="landing-privacy-badge">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    <?= e(__('landing.privacy.badge')) ?>
                </span>
            </div>

            <ul class="landing-privacy-list">
                <?php foreach ($privacyPoints as $point): ?>
                    <li>
                        <i class="bi bi-check2" aria-hidden="true"></i>
                        <?= e((string) $point) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<section class="landing-final">
    <div class="container">
        <div class="landing-final-inner" data-reveal>
            <span class="landing-eyebrow landing-eyebrow-light"><?= e(__('landing.cta.eyebrow')) ?></span>
            <h2><?= e(__('landing.cta.title')) ?></h2>
            <p><?= e(__('landing.cta.lead')) ?></p>
            <div class="landing-cta landing-cta-center">
                <a href="/registro" class="btn landing-btn-primary landing-btn-light">
                    <?= e(__('landing.cta.primary')) ?>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="/login" class="btn landing-btn-secondary landing-btn-secondary-light">
                    <?= e(__('landing.cta.secondary')) ?>
                </a>
            </div>
            <small><?= e(__('landing.cta.note')) ?></small>
        </div>
    </div>
</section>
