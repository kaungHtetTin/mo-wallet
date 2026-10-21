<!doctype html>
<html lang="<?= e($_SESSION['locale'] ?? 'en') ?>" data-theme="light" data-density="compact">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark"><title><?= e(t($title ?? 'Overview')) ?> · MoWallet</title>
    <link rel="icon" href="<?= asset('favicon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700;750;800&family=Noto+Sans+Myanmar:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>"><script src="<?= asset('js/app.js') ?>" defer></script><script src="<?= asset('js/contacts.js') ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main"><?= e(t('Skip to content')) ?></a><div class="nav-overlay" data-nav-close></div>
<aside class="sidebar" id="navigation" aria-label="<?= e(t('Main navigation')) ?>">
    <nav><p class="nav-label"><?= e(t('WORKSPACE')) ?></p>
        <?php foreach (['' => ['dashboard','Overview'], 'transactions' => ['transactions','Transactions'], 'debts' => ['debts','Payables & receivables'], 'reports' => ['reports','Reports & analysis']] as $path => [$glyph,$label]): ?>
        <a class="nav-item <?= active_nav($path) ?>" href="<?= url($path) ?>" title="<?= e(t($label)) ?>" <?= active_nav($path) ? 'aria-current="page"' : '' ?>><?= icon($glyph) ?><span class="nav-copy"><?= e(t($label)) ?></span></a>
        <?php endforeach ?><p class="nav-label"><?= e(t('ORGANIZE')) ?></p>
        <?php foreach (['books' => 'Account books','businesses' => 'Businesses & personal','categories' => 'Categories','people' => 'People'] as $entity => $label): ?>
        <a class="nav-item <?= active_nav('manage/' . $entity) ?>" href="<?= url('manage/' . $entity) ?>" title="<?= e(t($label)) ?>" <?= active_nav('manage/' . $entity) ? 'aria-current="page"' : '' ?>><?= icon($entity) ?><span class="nav-copy"><?= e(t($label)) ?></span></a>
        <?php endforeach ?>
    </nav>
    <div class="sidebar-bottom"><div class="currency-note nav-copy"><span class="currency-symbol">K</span><span><strong><?= e(t('Myanmar Kyat')) ?></strong><small><?= e(t('All records in MMK')) ?></small></span></div><a class="nav-item <?= active_nav('settings') ?>" href="<?= url('settings') ?>" title="<?= e(t('Settings')) ?>"><?= icon('settings') ?><span class="nav-copy"><?= e(t('Settings')) ?></span></a></div>
</aside>
<div class="app-shell">
    <header class="topbar"><div class="topbar-left"><button class="icon-button" type="button" data-nav-toggle aria-label="<?= e(t('Toggle navigation')) ?>" aria-controls="navigation" aria-expanded="false"><?= icon('menu') ?></button><span class="breadcrumb">MoWallet <span>/</span> <strong><?= e(t($title ?? 'Overview')) ?></strong></span></div><div class="topbar-actions"><span class="today"><?= e(date('d M Y')) ?></span>
        <form action="<?= url('locale') ?>" method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e(current_route()) ?>"><button class="text-button language" name="locale" value="<?= ($_SESSION['locale'] ?? 'en') === 'my' ? 'en' : 'my' ?>" lang="<?= ($_SESSION['locale'] ?? 'en') === 'my' ? 'en' : 'my' ?>"><?= ($_SESSION['locale'] ?? 'en') === 'my' ? 'English' : 'မြန်မာ' ?></button></form>
        <button class="icon-button" data-theme-toggle aria-label="<?= e(t('Toggle color theme')) ?>" title="<?= e(t('Toggle color theme')) ?>"><?= icon('sun') ?></button><form action="<?= url('logout') ?>" method="post" class="inline"><?= csrf_field() ?><button class="icon-button" aria-label="<?= e(t('Sign out')) ?>" title="<?= e(t('Sign out')) ?>"><?= icon('logout') ?></button></form>
    </div></header>
    <main id="main" class="content">
        <?php if ($flash = $_SESSION['flash'] ?? null): unset($_SESSION['flash']); ?><div class="notice <?= e($flash['type']) ?>" role="status"><?= e(t($flash['message'])) ?></div><?php endif ?>
        <?php if (!empty($_SESSION['errors']['form'])): ?><div class="notice danger" role="alert"><?= e(t($_SESSION['errors']['form'])) ?></div><?php endif ?>
        <?= $content ?>
        <footer class="page-footer"><span>MoWallet <span class="muted">· <?= e(t('A little clarity, every day.')) ?></span></span><span><?= e(t('MMK')) ?> · <?= e(t('Asia/Yangon')) ?></span></footer>
    </main>
</div>
<dialog id="confirm-dialog" aria-labelledby="confirm-title"><div class="dialog-content"><span class="eyebrow"><?= e(t('PLEASE CONFIRM')) ?></span><h2 id="confirm-title"><?= e(t('Remove this record?')) ?></h2><p id="confirm-message"></p><div class="form-actions"><button type="button" class="button" data-confirm-cancel><?= e(t('Cancel')) ?></button><button type="button" class="button danger-button" data-confirm-accept><?= e(t('Confirm removal')) ?></button></div></div></dialog>
</body>
</html>
