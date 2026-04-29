<?php
/**
 * @var string $icon
 * @var string $title
 * @var string $message
 * @var string|null $ctaUrl
 * @var string|null $ctaLabel
 */
$icon    = $icon ?? '📭';
$ctaUrl  = $ctaUrl ?? null;
$ctaLabel= $ctaLabel ?? null;
?>
<div class="text-center py-16 px-6">
    <div class="w-20 h-20 mx-auto rounded-2xl flex items-center justify-center mb-5 text-4xl relative" style="background: var(--color-bg-subtle); border: 1px solid var(--color-border-subtle);">
        <?= $icon ?>
        <div class="absolute inset-0 rounded-2xl" style="background: var(--gradient-primary); opacity: .04;"></div>
    </div>
    <h4 class="text-lg font-bold mb-2" style="color: var(--color-text-primary);"><?= e($title) ?></h4>
    <p class="text-sm max-w-md mx-auto mb-5" style="color: var(--color-text-tertiary);"><?= e($message) ?></p>
    <?php if ($ctaUrl && $ctaLabel): ?>
    <a href="<?= e($ctaUrl) ?>" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        <?= e($ctaLabel) ?>
    </a>
    <?php endif; ?>
</div>
