<?php
/**
 * Header de subpagina de settings con breadcrumb opcional.
 *
 * @var string      $title
 * @var string|null $subtitle  (opcional)
 * @var string|null $crumb     (opcional, ej "Plataforma")
 * @var string      $actions   HTML opcional para botones a la derecha
 */
$title = $title ?? '';
$subtitle = $subtitle ?? null;
$crumb = $crumb ?? null;
$actions = $actions ?? '';
$back = $back ?? null;
?>
<header class="set-header">
    <?php if ($back): ?>
    <a href="<?= e((string) $back['href']) ?>" class="set-back-link">
        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        <?= e((string) ($back['label'] ?? 'Volver')) ?>
    </a>
    <?php endif; ?>
    <?php if ($crumb): ?>
    <div class="set-crumbs">
        <a href="<?= url('/settings') ?>">Configuracion</a>
        <span class="sep">›</span>
        <span><?= e((string) $crumb) ?></span>
    </div>
    <?php endif; ?>
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="min-w-0">
            <h1 class="set-title"><?= e((string) $title) ?></h1>
            <?php if ($subtitle): ?>
            <p class="set-subtitle"><?= e((string) $subtitle) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($actions): ?>
        <div class="flex items-center gap-2 flex-shrink-0"><?= $actions ?></div>
        <?php endif; ?>
    </div>
</header>
