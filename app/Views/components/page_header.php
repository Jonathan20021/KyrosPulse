<?php
/**
 * @var string $title
 * @var string $subtitle
 * @var string|null $actionUrl
 * @var string|null $actionLabel
 * @var string|null $actionIcon
 */
$subtitle    = $subtitle ?? '';
$actionUrl   = $actionUrl ?? null;
$actionLabel = $actionLabel ?? null;
$actionIcon  = $actionIcon ?? null;
?>
<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl lg:text-[28px] font-bold tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;"><?= e($title) ?></h1>
        <?php if ($subtitle !== ''): ?>
        <p class="text-sm mt-1" style="color: var(--color-text-tertiary);"><?= e($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($actionUrl && $actionLabel): ?>
    <a href="<?= e($actionUrl) ?>" class="btn btn-primary">
        <?php if ($actionIcon === 'plus'): ?>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        <?php endif; ?>
        <?= e($actionLabel) ?>
    </a>
    <?php endif; ?>
</div>
