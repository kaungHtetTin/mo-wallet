<div class="page-heading"><div><h1><?= e(t('Payables & receivables')) ?></h1><p><?= e(t('A clear list of who owes you, and who you need to pay.')) ?></p></div><a class="button primary" href="<?= url('debts/new') ?>"><?= icon('plus') ?><?= e(t('New unpaid balance')) ?></a></div>
<section class="metrics two-metrics">
    <?php foreach (['receivable'=>['To receive','success','arrow-down'],'payable'=>['To pay','danger','arrow-up']] as $direction=>[$label,$tone,$glyph]): ?>
    <div class="metric"><div><span class="metric-label"><?= e(t($label)) ?></span><strong class="<?= $direction==='receivable' ? 'positive' : 'negative' ?>"><?= e(money($totals[$direction])) ?></strong><small><?= e(t('Outstanding · matching filters')) ?></small></div><span class="metric-icon <?= $tone ?>"><?= icon($glyph) ?></span></div>
    <?php endforeach ?>
</section>
<section class="panel ledger-panel">
    <?php $filterMode='debts'; require BASE_PATH.'/app/Views/partials/filters.php'; unset($filterMode); ?>
    <div class="list-summary"><span><?= $totalRows ?> <?= e(t('records')) ?></span><span><?= e(t('Amounts in MMK')) ?></span></div>
    <div class="table-wrap"><table class="debt-table"><thead><tr><th><?= e(t('Person / business')) ?></th><th><?= e(t('Direction')) ?></th><th><?= e(t('Due date')) ?></th><th class="number"><?= e(t('Original amount')) ?></th><th class="number"><?= e(t('Paid / received')) ?></th><th class="number"><?= e(t('Remaining')) ?></th><th><?= e(t('Status')) ?></th><th class="actions-col"><?= e(t('Actions')) ?></th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
    <tr>
        <td><a class="identity-link" href="<?= url('debts/'.$row['id']) ?>"><strong><?= e($row['person_name']) ?></strong></a><small><?= e($row['business_name']) ?></small><span class="mobile-only entry-meta"><?= e(t(ucfirst($row['direction']))) ?><?= $row['due_date'] ? ' · '.e($row['due_date']) : '' ?></span></td>
        <td><?= status_badge($row['direction']) ?></td><td><?= e($row['due_date']?:'—') ?></td><td class="number"><?= number_format((int)$row['amount']) ?></td><td class="number"><?= number_format((int)$row['paid']) ?></td>
        <td class="number amount"><?= number_format((int)$row['remaining']) ?><span class="mobile-only entry-status"><?= status_badge(debt_status($row)) ?></span></td>
        <td><?= status_badge(debt_status($row)) ?></td>
        <td class="actions-col"><a class="text-button row-view" href="<?= url('debts/'.$row['id']) ?>" aria-label="<?= e(t('View / pay')) ?>" title="<?= e(t('View / pay')) ?>"><span class="row-action-icon"><?= icon('chevron') ?></span><span class="row-action-label"><?= e(t('View / pay')) ?> →</span></a></td>
    </tr>
    <?php endforeach ?>
    <?php if (!$rows): ?><tr><td colspan="8"><div class="empty-state"><?= icon('debts') ?><strong><?= e(t('No matching unpaid balances')) ?></strong><span><?= e(t('Add a balance or change the filters to see settled records.')) ?></span></div></td></tr><?php endif ?>
    </tbody></table></div>
    <?php require BASE_PATH.'/app/Views/partials/pagination.php'; ?>
</section>
