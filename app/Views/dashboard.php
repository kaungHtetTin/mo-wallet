<div class="page-heading"><div><h1><?= e(t('Overview')) ?></h1><p><?= e(t('Income, expenses, and balances across your books.')) ?></p></div><a class="button primary" href="<?= url('transactions/new') ?>"><?= icon('plus') ?><?= e(t('New transaction')) ?></a></div>
<section class="panel filters-panel"><?php $simple=true; require BASE_PATH.'/app/Views/partials/filters.php'; ?></section>
<section class="metrics" aria-label="<?= e(t('Financial summary')) ?>">
    <?php foreach ([['Income',$report['totals']['income'],'arrow-down','success','In selected period'],['Expenses',$report['totals']['expense'],'arrow-up','danger','In selected period'],['Net income',$report['totals']['net'],'reports','brand','Income minus expenses'],['Total book balance',array_sum(array_column($balances,'balance')),'books','neutral','All books · as of today']] as [$label,$value,$glyph,$tone,$hint]): ?>
    <div class="metric <?= $tone ?>"><div><span class="metric-label"><?= e(t($label)) ?></span><strong><?= e(money($value)) ?></strong><small><?= e(t($hint)) ?></small></div><span class="metric-icon <?= $tone ?>"><?= icon($glyph) ?></span></div>
    <?php endforeach ?>
</section>
<div class="dashboard-grid">
    <div class="main-stack">
        <section class="panel ledger-panel"><div class="panel-heading"><h2><?= e(t('Recent transactions')) ?></h2><a class="text-button" href="<?= e(page_link('transactions')) ?>"><?= e(t('View all')) ?> →</a></div><?php $rows=$recent; require BASE_PATH.'/app/Views/partials/ledger.php'; ?></section>
        <section class="panel"><div class="panel-heading"><h2><?= e(t('Account books')) ?></h2><a class="text-button" href="<?= url('manage/books') ?>"><?= e(t('Manage')) ?></a></div>
            <div class="dashboard-books"><?php foreach ($balances as $book): ?><a class="book-row" href="<?= e(url('transactions').'?'.http_build_query(['book_id'=>$book['id'],'from'=>'1900-01-01','to'=>date('Y-m-d')])) ?>"><span class="book-icon"><?= icon('books') ?></span><span><strong><?= e($book['name']) ?></strong><small><?= e(t('Available balance')) ?></small></span><strong class="number"><?= e(money($book['balance'])) ?></strong></a><?php endforeach ?></div>
            <?php if (!$balances): ?><p class="muted small"><?= e(t('Create an account book to start recording.')) ?></p><?php endif ?>
            <div class="book-footer"><span class="muted small"><?= e(t('All books · as of today')) ?></span><a class="text-button" href="<?= url('transactions/new?kind=transfer') ?>"><?= icon('transactions') ?><?= e(t('Transfer between books')) ?></a></div>
        </section>
    </div>
    <div class="side-stack">
        <section class="panel"><div class="panel-heading"><h2><?= e(t('Monthly cash flow')) ?></h2><a class="text-button" href="<?= e(page_link('reports',['page'=>null])) ?>"><?= e(t('View report')) ?> →</a></div><?php require BASE_PATH.'/app/Views/partials/chart.php'; ?></section>
        <section class="panel"><div class="panel-heading"><h2><?= e(t('Unpaid balances')) ?></h2><?= icon('debts') ?></div><p class="muted small"><?= e(t('All dates · selected business')) ?></p>
            <div class="balance-summary"><div><span><?= e(t('To receive')) ?></span><strong class="positive"><?= e(money($receivable)) ?></strong></div><div><span><?= e(t('To pay')) ?></span><strong class="negative"><?= e(money($payable)) ?></strong></div></div>
            <?php foreach (array_slice($debts,0,3) as $debt): ?><a class="due-row" href="<?= url('debts/'.$debt['id']) ?>"><span><strong><?= e($debt['person_name']) ?></strong><small><?= e(t(ucfirst($debt['direction']))) ?><?= $debt['due_date'] ? ' · '.e($debt['due_date']) : '' ?></small></span><span><?= status_badge(debt_status($debt)) ?><small class="number"><?= e(money($debt['remaining'])) ?></small></span></a><?php endforeach ?>
            <a class="text-button panel-link" href="<?= e(page_link('debts',['from'=>null,'to'=>null])) ?>"><?= e(t('Review unpaid balances')) ?> →</a>
        </section>
        <div class="quiet-note"><?= icon('reports') ?><p><?= e(t('Reports follow cash received and paid. Transfers and opening balances are excluded from income and expenses.')) ?></p></div>
    </div>
</div>
