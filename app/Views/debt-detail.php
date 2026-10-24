<div class="page-heading"><div><h1><?= e($row['person_name']) ?></h1><p class="record-context"><?= e($row['business_name']) ?> · <?= e($row['category_name']) ?><?= $row['phone'] ? ' · '.e($row['phone']) : '' ?></p></div><div class="heading-actions"><a class="button" href="<?= url('debts') ?>"><?= e(t('Back to balances')) ?></a><a class="button" href="<?= url('debts/'.$row['id'].'/edit') ?>"><?= e(t('Edit balance')) ?></a></div></div>
<section class="metrics three-metrics">
    <?php foreach (['amount'=>'Original amount','paid'=>'Paid / received','remaining'=>'Remaining'] as $key=>$label): ?><div class="metric"><div><span class="metric-label"><?= e(t($label)) ?></span><strong><?= e(money($row[$key])) ?></strong></div></div><?php endforeach ?>
</section>
<div class="detail-grid">
    <div class="main-stack">
        <section class="panel"><div class="panel-heading"><h2><?= e(t('Balance detail')) ?></h2><?= status_badge(debt_status($row)) ?></div>
            <dl class="detail-facts"><div><dt><?= e(t('Record date')) ?></dt><dd><?= e($row['record_date']) ?></dd></div><div><dt><?= e(t('Due date')) ?></dt><dd><?= e($row['due_date']?:'—') ?></dd></div><div class="span-2"><dt><?= e(t('Direction')) ?></dt><dd><?= e(t($row['direction']==='receivable' ? 'To receive (someone owes me)' : 'To pay (I owe someone)')) ?></dd></div><div class="span-2"><dt><?= e(t('Notes')) ?></dt><dd class="preserve-lines"><?= e($row['notes']?:'—') ?></dd></div></dl>
        </section>
        <section class="panel ledger-panel"><div class="panel-heading"><h2><?= e(t('Payment history')) ?></h2><span class="muted small"><?= count($payments) ?> <?= e(t('records')) ?></span></div>
            <div class="table-wrap"><table class="payment-table"><thead><tr><th><?= e(t('Date / note')) ?></th><th><?= e(t('Account book')) ?></th><th class="number"><?= e(t('Amount (MMK)')) ?></th><th class="actions-col"><?= e(t('Actions')) ?></th></tr></thead><tbody>
                <?php foreach ($payments as $payment): ?><tr>
                    <td><strong><?= e($payment['record_date']) ?></strong><small class="row-note"><?= e($payment['notes']) ?></small><span class="mobile-only entry-meta"><?= e($payment['book_name']) ?></span></td>
                    <td><?= e($payment['book_name']) ?></td><td class="number amount"><?= \App\Services\Money::format($payment['amount']) ?></td>
                    <td class="actions-col"><form method="post" action="<?= url('transactions/'.$payment['id'].'/delete') ?>" data-confirm="<?= e(t('Undo this payment? The outstanding balance will increase and its cash transaction will be removed.')) ?>"><?= csrf_field() ?><button class="text-button danger-text row-view" aria-label="<?= e(t('Undo payment')) ?>" title="<?= e(t('Undo payment')) ?>"><span class="row-action-icon"><?= icon('trash') ?></span><span class="row-action-label"><?= e(t('Undo payment')) ?></span></button></form></td>
                </tr><?php endforeach ?>
                <?php if (!$payments): ?><tr><td colspan="4"><div class="empty-state"><strong><?= e(t('No payments recorded')) ?></strong><span><?= e(t('Record a partial or full payment to update this balance.')) ?></span></div></td></tr><?php endif ?>
            </tbody></table></div>
        </section>
        <?php if (!$payments): ?><form method="post" action="<?= url('debts/'.$row['id'].'/delete') ?>" data-confirm="<?= e(t('Remove this unpaid balance?')) ?>"><?= csrf_field() ?><button class="text-button danger-text"><?= e(t('Delete balance')) ?></button></form><?php endif ?>
    </div>
    <section class="panel settlement-panel">
        <?php if (\App\Services\Money::compare($row['remaining'], '0') > 0): ?>
        <h2><?= e(t($row['direction']==='receivable' ? 'Record money received' : 'Record a payment')) ?></h2><p class="muted small"><?= e(t('Partial payments are welcome. The remaining balance updates automatically.')) ?></p>
        <form method="post" action="<?= url('debts/'.$row['id'].'/settle') ?>" class="stack-form settlement-form"><?= csrf_field().mutation_field() ?>
            <?php text_field('amount','Amount (MMK)',[],'number',true,(string)$row['remaining'],'min="0.01" max="'.e($row['remaining']).'" step="0.01" inputmode="decimal"'); select_field('book_id','Account book',$books); text_field('record_date','Payment date',[],'date',true,date('Y-m-d'),'min="'.e($row['record_date']).'" max="9999-12-31"'); notes_field([]); ?>
            <button class="button primary full-width"><?= e(t('Record payment')) ?></button>
        </form>
        <?php else: ?><div class="empty-state"><span class="settled-check">✓</span><h2><?= e(t('All settled')) ?></h2><p><?= e(t('This balance has been fully paid.')) ?></p></div><?php endif ?>
        <p class="field-hint"><?= e(t('A matching income or expense is created in the selected account book. Do not add the same payment again in Transactions.')) ?></p>
    </section>
</div>
