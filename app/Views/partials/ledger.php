<div class="table-wrap"><table class="ledger-table"><thead><tr>
    <th><?= e(t('Date / note')) ?></th><th><?= e(t('Business / personal')) ?></th><th><?= e(t('Category')) ?></th><th><?= e(t('Account book')) ?></th><th><?= e(t('Type')) ?></th><th class="number"><?= e(t('Amount (MMK)')) ?></th><th class="actions-col"><?= e(t('Actions')) ?></th>
</tr></thead><tbody>
<?php foreach ($rows as $entry): ?>
<tr>
    <td><strong><?= e($entry['record_date']) ?></strong><small class="row-note" title="<?= e($entry['notes']) ?>"><?= e($entry['notes'] ?: ($entry['person_name'] ?: '—')) ?></small><span class="mobile-only entry-meta"><?= e(implode(' · ',array_filter([$entry['business_name'],$entry['category_name'],$entry['book_name'] . ($entry['to_book_name'] ? ' → '.$entry['to_book_name'] : '')]))) ?></span></td>
    <td><?= e($entry['business_name'] ?? '—') ?></td>
    <td><?= e($entry['category_name'] ?? '—') ?><?php if ($entry['debt_id']): ?><small><?= e(t('Balance payment')) ?></small><?php endif ?></td>
    <td><?= e($entry['book_name']) ?><?php if ($entry['to_book_name']): ?><small>→ <?= e($entry['to_book_name']) ?></small><?php endif ?></td>
    <td><?= status_badge($entry['kind']) ?></td>
    <td class="number amount <?= $entry['kind']==='income' ? 'positive' : ($entry['kind']==='expense' ? 'negative' : '') ?>"><?= $entry['kind']==='income' ? '+' : ($entry['kind']==='expense' ? '−' : '') ?><?= \App\Services\Money::format($entry['amount']) ?><span class="mobile-only entry-status"><?= status_badge($entry['kind']) ?></span></td>
    <td class="actions-col">
        <?php if ($entry['debt_id']): ?>
        <a class="text-button row-view" href="<?= url('debts/'.$entry['debt_id']) ?>" aria-label="<?= e(t('View balance')) ?>" title="<?= e(t('View balance')) ?>"><span class="row-action-icon"><?= icon('chevron') ?></span><span class="row-action-label"><?= e(t('View balance')) ?></span></a>
        <?php else: ?>
        <div class="row-actions">
            <a class="text-button" href="<?= url('transactions/'.$entry['id'].'/edit').'?return='.urlencode(current_route()) ?>" aria-label="<?= e(t('Edit')) ?>" title="<?= e(t('Edit')) ?>"><span class="row-action-icon"><?= icon('edit') ?></span><span class="row-action-label"><?= e(t('Edit')) ?></span></a>
            <form method="post" action="<?= url('transactions/'.$entry['id'].'/delete') ?>" data-confirm="<?= e(t('Remove this transaction? The account balance and reports will be recalculated.')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e(current_route()) ?>"><button class="text-button danger-text" aria-label="<?= e(t('Delete')) ?>" title="<?= e(t('Delete')) ?>"><span class="row-action-icon"><?= icon('trash') ?></span><span class="row-action-label"><?= e(t('Delete')) ?></span></button></form>
        </div>
        <?php endif ?>
    </td>
</tr>
<?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state"><?= icon('transactions') ?><strong><?= e(t('No transactions yet')) ?></strong><span><?= e(t('Add your first record, or adjust the filters.')) ?></span></div></td></tr><?php endif ?>
</tbody></table></div>
