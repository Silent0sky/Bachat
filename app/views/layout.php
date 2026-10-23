<?php
/**
 * Bachat main layout.
 * Available here: $content (page HTML), $user, $society, $lang, plus any extra vars
 * the controller passed to view() — e.g. $active, $pageTitle.
 */
$user    = $user ?? null;
$society = $society ?? null;
$active  = $active ?? '';
$role    = $user['role'] ?? null;
$isAdmin = in_array($role, ['super_admin', 'society_admin'], true);
$isResident = $role === 'resident';
$brandColor = $society['primary_color'] ?? '#A8E63E';
$inAdminPanel = str_starts_with($active, 'super-') || str_starts_with($active, 'admin-') || str_starts_with($active, 'vendor-');
$hasSidebar = ($isAdmin || $role === 'vendor') && $inAdminPanel;
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : ($lang === 'hi' ? 'hi' : 'mr')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#7C3AED">
<meta name="color-scheme" content="light">
<title><?= e(($pageTitle ?? t('app.name'))) ?> · <?= e(t('app.name')) ?></title>
<link rel="icon" href="<?= asset('img/logo.svg') ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>?v=<?= @filemtime(BACHAT_ROOT . '/assets/css/app.css') ?: time() ?>" rel="stylesheet">
</head>
<body class="<?= $hasSidebar ? 'has-sidebar' : '' ?>" data-role="<?= e($role ?? 'guest') ?>">
<a class="visually-hidden-focusable" href="#main-content"><?= e(t('common.details')) ?></a>

<?php include APP . '/views/partials/nav.php'; ?>

<div class="<?= $hasSidebar ? 'b-shell container-fluid' : 'b-shell-noside container' ?>">
    <?php if ($hasSidebar) include APP . '/views/partials/sidebar.php'; ?>
    <main id="main-content" class="<?= $hasSidebar ? 'b-main' : 'pb-5' ?>">
        <?php if ($hasSidebar) include APP . '/views/partials/mobile_admin_bar.php'; ?>
        <?php include APP . '/views/partials/toasts.php'; ?>
        <?= $content ?>
    </main>
</div>

<?php include APP . '/views/partials/footer.php'; ?>
<?php if ($isResident) include APP . '/views/partials/bottomnav.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<?php if (!empty($useCharts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script>window.BACHAT_CSRF = <?= json_encode(csrf_token()) ?>; window.BACHAT_URL = <?= json_encode(url('/')) ?>;</script>
<script src="<?= asset('js/app.js') ?>?v=<?= @filemtime(BACHAT_ROOT . '/assets/js/app.js') ?: time() ?>"></script>
</body>
</html>
