<?php
/**
 * Login page. Public.
 */
?>
<div class="row justify-content-center py-4">
  <div class="col-12 col-md-7 col-lg-5 col-xl-4">
    <div class="b-card fade-up">
      <div class="text-center mb-4">
        <img src="<?= asset('img/logo.svg') ?>" alt="Bachat" width="56" height="56">
        <h1 class="section-title mt-2 d-block"><?= e(t('auth.login_title')) ?></h1>
        <p class="text-muted small mb-0"><?= e(t('auth.login_sub')) ?></p>
      </div>

      <form method="post" action="<?= url('/login') ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="login-email"><?= e(t('auth.email')) ?></label>
          <input type="email" class="form-control" id="login-email" name="email" autofocus
                 value="<?= e(old('email')) ?>" required autocomplete="email">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="login-password"><?= e(t('auth.password')) ?></label>
          <input type="password" class="form-control" id="login-password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-brand w-100 b-btn-pill mt-2 py-2">
          <i data-lucide="log-in" class="me-1"></i><?= e(t('auth.login_btn')) ?>
        </button>
      </form>

      <div class="text-center mt-3 small">
        <?= e(t('auth.no_account')) ?>
        <a href="<?= url('/join') ?>" class="fw-semibold text-brand text-decoration-none"><?= e(t('nav.join')) ?></a>
      </div>
    </div>
  </div>
</div>
