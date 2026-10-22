<?php
$debtFilters = ($filterMode ?? '') === 'debts';
$filterCount = count(array_filter($filters, fn($value, $key) => !in_array($key,['from','to'],true) && $value !== '' && $value !== null && !($debtFilters && $key==='status' && $value==='open'), ARRAY_FILTER_USE_BOTH));
?>
<details class="filter-disclosure" data-filter-disclosure data-has-errors="<?= !empty($_SESSION['errors']) ? 'true' : 'false' ?>" open>
    <summary><?= icon('settings') ?><strong><?= e(t('Filters')) ?></strong><?php if ($filterCount): ?><span class="filter-count"><?= $filterCount ?></span><?php endif ?><span class="filter-period"><?= $debtFilters ? e(t('All dates')) : e($filters['from'] . ' – ' . $filters['to']) ?></span><?= icon('chevron') ?></summary>
    <form method="get" class="filter-bar" aria-label="<?= e(t('Filter records')) ?>">
        <div class="filter-scroll"><div class="filter-fields">
            <?php if (!empty($search) || $debtFilters): ?>
            <label class="field search-field"><span><?= e(t('Search')) ?></span><input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="<?= e(t('Notes or person’s name')) ?>"></label>
            <?php endif ?>
            <label class="field business-field"><span><?= e(t('Business / personal')) ?></span><select name="business_id"><option value=""><?= e(t('All businesses & personal')) ?></option><?php foreach ($businesses as $business): ?><option value="<?= $business['id'] ?>"<?= selected($filters['business_id'] ?? '',$business['id']) ?>><?= e($business['name']) ?></option><?php endforeach ?></select></label>
            <?php if ($debtFilters): ?>
                <label class="field"><span><?= e(t('Person')) ?></span><select name="person_id"><option value=""><?= e(t('All people')) ?></option><?php foreach ($people as $person): ?><option value="<?= $person['id'] ?>"<?= selected($filters['person_id']??'',$person['id']) ?>><?= e($person['name']) ?></option><?php endforeach ?></select></label>
                <label class="field"><span><?= e(t('Direction')) ?></span><select name="direction"><option value=""><?= e(t('Both directions')) ?></option><?php foreach (['receivable'=>'To receive','payable'=>'To pay'] as $key=>$label): ?><option value="<?= $key ?>"<?= selected($filters['direction']??'',$key) ?>><?= e(t($label)) ?></option><?php endforeach ?></select></label>
                <label class="field"><span><?= e(t('Status')) ?></span><select name="status"><?php foreach ([''=>'All statuses','open'=>'Outstanding','overdue'=>'Overdue','settled'=>'Settled'] as $key=>$label): ?><option value="<?= $key ?>"<?= selected($filters['status']??'',$key) ?>><?= e(t($label)) ?></option><?php endforeach ?></select></label>
            <?php elseif (empty($simple)): ?>
                <label class="field"><span><?= e(t('Account book')) ?></span><select name="book_id"><option value=""><?= e(t('All account books')) ?></option><?php foreach ($books as $book): ?><option value="<?= $book['id'] ?>"<?= selected($filters['book_id'] ?? '',$book['id']) ?>><?= e($book['name']) ?></option><?php endforeach ?></select></label>
                <label class="field"><span><?= e(t('Category')) ?></span><select name="category_id"><option value=""><?= e(t('All categories')) ?></option><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>"<?= selected($filters['category_id'] ?? '',$category['id']) ?>><?= e($category['name']) ?></option><?php endforeach ?></select></label>
                <label class="field"><span><?= e(t('Type')) ?></span><select name="kind"><option value=""><?= e(t('All types')) ?></option><?php foreach (['income','expense','transfer'] as $kind): ?><option value="<?= $kind ?>"<?= selected($filters['kind'] ?? '',$kind) ?>><?= e(t(ucfirst($kind))) ?></option><?php endforeach ?></select></label>
            <?php endif ?>
            <?php if (!$debtFilters): ?>
                <label class="field date-field"><span><?= e(t('From')) ?></span><input type="date" name="from" value="<?= e($filters['from']) ?>" required min="1900-01-01" max="9999-12-31"></label>
                <label class="field date-field"><span><?= e(t('To')) ?></span><input type="date" name="to" value="<?= e($filters['to']) ?>" required min="1900-01-01" max="9999-12-31"></label>
            <?php endif ?>
        </div></div>
        <div class="filter-actions"><button class="button" type="submit"><?= e(t('Apply filters')) ?></button><a class="text-button" href="<?= url(strtok(current_route(),'?') ?: '') ?>"><?= e(t('Reset')) ?></a></div>
    </form>
</details>
