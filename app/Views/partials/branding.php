<?php $branding=\App\Services\Branding::current(); ?>
<section class="panel branding-panel" aria-labelledby="branding-title">
    <div class="panel-heading"><h2 id="branding-title"><?= e(t('Logo & favicon')) ?></h2><span class="muted small"><?= e(t('App header, login screen, and browser tab')) ?></span></div>
    <?php if (!empty($_SESSION['errors']['branding'])): ?><div class="notice danger" role="alert"><?= e(t($_SESSION['errors']['branding'])) ?></div><?php endif ?>
    <form action="<?= url('settings/branding') ?>" method="post" enctype="multipart/form-data" data-branding-form>
        <?= csrf_field() ?><input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        <div class="branding-grid">
            <?php foreach (['logo'=>'App logo','favicon'=>'Browser favicon'] as $kind=>$label): ?>
            <div class="branding-choice">
                <div class="branding-preview <?= $kind==='favicon' ? 'favicon-preview' : '' ?>"><img src="<?= e(\App\Services\Branding::url($kind)) ?>" alt="<?= e(t($label)) ?>" width="56" height="56" data-branding-preview="<?= $kind ?>"></div>
                <label class="field"><span><?= e(t($label)) ?></span><input type="file" name="<?= $kind ?>" accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp" data-branding-file="<?= $kind ?>" data-invalid="<?= e(t('Choose a valid PNG, JPG, or WebP image.')) ?>" data-too-large="<?= e(t('Choose an image smaller than 2 MB.')) ?>"><?= form_error($kind) ?><span class="field-error" data-branding-error="<?= $kind ?>" role="status"></span></label>
            </div>
            <?php endforeach ?>
        </div>
        <div class="form-actions"><p class="field-hint"><?= e(t('Square PNG, JPG, or WebP · up to 2 MB each · 16–2048 pixels. Transparent PNG works well.')) ?></p><button class="button primary" data-branding-save><?= e(t('Save branding')) ?></button></div>
    </form>
    <?php if ($branding['has_logo'] || $branding['has_favicon']): ?>
    <div class="branding-resets">
        <?php foreach (['logo'=>'Reset logo','favicon'=>'Reset favicon'] as $kind=>$label): if (!$branding['has_'.$kind]) continue; ?>
        <form method="post" action="<?= url('settings/branding/reset/'.$kind) ?>" data-confirm="<?= e(t($kind==='logo' ? 'Restore the default app logo?' : 'Restore the default browser favicon?')) ?>" data-confirm-title="<?= e(t('Restore default branding?')) ?>" data-confirm-label="<?= e(t('Restore default')) ?>"><?= csrf_field() ?><button class="text-button"><?= e(t($label)) ?></button></form>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>
