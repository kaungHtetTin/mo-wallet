<div class="page-heading"><div><span class="eyebrow"><?= e(t('BRING YOUR PEOPLE WITH YOU')) ?></span><h1><?= e(t('Import contacts')) ?></h1><p><?= e(t('Choose contacts from your phone or upload a contact file, then review before saving.')) ?></p></div><a class="button" href="<?= url('manage/people') ?>"><?= e(t('Back to people')) ?></a></div>
<?php if ($draft): $available=count(array_filter($draft['rows'],fn($row)=>!$row['duplicate'])); $duplicates=count($draft['rows'])-$available; ?>
<section class="panel ledger-panel">
    <div class="panel-heading"><div><span class="eyebrow"><?= e(t('REVIEW BEFORE SAVING')) ?></span><h2><?= e(t('Contact preview')) ?></h2></div><span class="muted small"><?= count($draft['rows']) ?> <?= e(t('contacts')) ?> · <?= $duplicates ?> <?= e(t('duplicates')) ?></span></div>
    <p class="field-hint"><?= e(t('Only selected contacts will be added. Existing people and their balances stay unchanged. Extra phone numbers are kept in Notes.')) ?></p>
    <?php if ($draft['skipped']): ?><div class="notice warning"><?= (int)$draft['skipped'] ?> <?= e(t('contacts could not be read or exceeded field limits and were skipped.')) ?></div><?php endif ?>
    <form method="post" action="<?= url('people/import/save') ?>" data-contact-preview>
        <?= csrf_field() . mutation_field() ?><input type="hidden" name="draft" value="<?= e($draftId) ?>">
        <div class="table-wrap"><table class="contact-table"><thead><tr><th><label class="checkbox-label"><input type="checkbox" data-contact-all <?= $available ? 'checked' : 'disabled' ?>><span><?= e(t('Select all')) ?></span></label></th><th><?= e(t('Name')) ?></th><th><?= e(t('Phone')) ?></th><th><?= e(t('Other phone numbers')) ?></th><th><?= e(t('Status')) ?></th></tr></thead><tbody>
        <?php foreach ($draft['rows'] as $index=>$contact): ?><tr><td><input class="contact-checkbox" type="checkbox" name="selected[]" value="<?= $index ?>" aria-label="<?= e(t('Import contact').': '.$contact['name']) ?>" <?= $contact['duplicate'] ? 'disabled' : 'checked' ?>></td><td><strong><?= e($contact['name']) ?></strong></td><td><?= e($contact['phone'] ?: '—') ?></td><td class="contact-notes"><?= e($contact['notes'] ? substr($contact['notes'],strlen('Other phone numbers: ')) : '—') ?></td><td><span class="badge <?= $contact['duplicate'] ? 'neutral' : 'success' ?>"><?= e(t($contact['duplicate'] ? 'Duplicate · skipped' : 'Ready to import')) ?></span></td></tr><?php endforeach ?>
        </tbody></table></div>
        <div class="form-actions"><span class="muted small" data-selected-count aria-live="polite" data-label="<?= e(t('selected')) ?>"><?= $available ?> <?= e(t('selected')) ?></span><button class="button primary" <?= !$available ? 'disabled' : '' ?> data-import-save><?= e(t('Import selected contacts')) ?></button></div>
    </form>
    <form method="post" action="<?= url('people/import/discard') ?>"><?= csrf_field() ?><input type="hidden" name="draft" value="<?= e($draftId) ?>"><button class="text-button"><?= e(t('Discard preview & start again')) ?></button></form>
</section>
<?php else: ?>
<div class="import-grid">
    <section class="panel import-method"><span class="metric-icon brand"><?= icon('people') ?></span><h2><?= e(t('Choose from phone')) ?></h2><p class="muted"><?= e(t('Open MoWallet on your phone and select the contacts you want to import. Only names and phone numbers are requested.')) ?></p>
        <form method="post" action="<?= url('people/import/preview') ?>" data-contact-picker-form>
            <?= csrf_field() . mutation_field() ?><input type="hidden" name="source" value="phone"><input type="hidden" name="contacts">
            <button class="button" type="button" data-contact-picker disabled data-unavailable="<?= e(t('Direct contact picking is unavailable in this browser. Use a .vcf file below, or a supported phone browser over HTTPS.')) ?>" data-cancelled="<?= e(t('No contacts were selected. You can try again or upload a file.')) ?>" data-error="<?= e(t('The phone contact picker could not open. Try again or upload a .vcf file.')) ?>" data-limit="<?= e(t('Import up to 500 contacts at a time. Export a smaller selection.')) ?>"><?= icon('people') ?><?= e(t('Choose phone contacts')) ?></button>
        </form>
        <p class="field-hint" data-contact-picker-status role="status"><?= e(t('Checking whether this browser can open phone contacts…')) ?></p>
        <noscript><p class="field-hint"><?= e(t('Enable JavaScript to choose contacts directly, or upload a .vcf file.')) ?></p></noscript>
    </section>
    <section class="panel import-method"><span class="metric-icon brand"><?= icon('books') ?></span><h2><?= e(t('Upload contact file')) ?></h2><p class="muted"><?= e(t('In your phone’s Contacts app, export or share contacts as a vCard (.vcf), then choose that file here. You can also transfer the file to this computer.')) ?></p>
        <form method="post" action="<?= url('people/import/preview') ?>" enctype="multipart/form-data" class="stack-form">
            <?= csrf_field() . mutation_field() ?><input type="hidden" name="MAX_FILE_SIZE" value="2097152"><input type="hidden" name="source" value="file">
            <label class="field"><span><?= e(t('Contact file (.vcf)')) ?></span><input type="file" name="contacts_file" accept=".vcf,.vcard,text/vcard,text/x-vcard" required></label>
            <span class="muted small"><?= e(t('Up to 500 contacts and 2 MB per import. English and Myanmar names are supported.')) ?></span>
            <button class="button primary"><?= e(t('Preview contacts')) ?></button>
        </form>
    </section>
</div>
<div class="quiet-note"><?= icon('people') ?><p><?= e(t('Nothing is saved to People until you confirm the preview. Matching names and phone numbers are skipped as duplicates. Contact photos, email addresses, and addresses are not imported.')) ?></p></div>
<?php endif ?>
