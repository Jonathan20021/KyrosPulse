<?php
/**
 * Gallery del marketplace de workflow templates.
 *
 * @var array  $templates
 * @var array  $categories
 * @var string $active_cat
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$categoryMeta = [
    'general'    => ['label' => 'General',     'color' => '#64748B'],
    'sales'      => ['label' => 'Ventas',      'color' => '#10B981'],
    'support'    => ['label' => 'Soporte',     'color' => '#06B6D4'],
    'marketing'  => ['label' => 'Marketing',   'color' => '#EC4899'],
    'ops'        => ['label' => 'Operaciones', 'color' => '#F59E0B'],
    'restaurant' => ['label' => 'Restaurante', 'color' => '#EF4444'],
    'ai'         => ['label' => 'IA',          'color' => '#8B5CF6'],
];
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Workflow templates',
    'subtitle' => 'Templates pre-armados listos para clonar con 1 click. Los editas despues si quieres. Te ahorran armar workflows desde cero.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="mb-4 flex items-center gap-2 flex-wrap">
    <a href="<?= url('/workflows') ?>" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--color-border); color: var(--color-text-secondary);">← Mis workflows</a>
</div>

<!-- Filtros por categoria -->
<div class="surface p-2 mb-5 inline-flex items-center gap-1 overflow-x-auto">
    <a href="<?= url('/workflows/templates') ?>"
       class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $active_cat === '' ? '' : 'opacity-70 hover:opacity-100' ?>"
       style="<?= $active_cat === '' ? 'background: var(--color-bg-active); color: var(--color-text-primary);' : 'color: var(--color-text-secondary);' ?>">
        Todos <span class="text-[10px] opacity-60">(<?= count($templates) ?>)</span>
    </a>
    <?php foreach ($categories as $c):
        $slug = (string) $c['category'];
        $meta = $categoryMeta[$slug] ?? ['label' => ucfirst($slug), 'color' => '#64748B'];
        $isActive = $active_cat === $slug;
    ?>
    <a href="<?= url('/workflows/templates?category=' . urlencode($slug)) ?>"
       class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap flex items-center gap-1.5 transition <?= $isActive ? '' : 'opacity-70 hover:opacity-100' ?>"
       style="<?= $isActive ? 'background: var(--color-bg-active); color: var(--color-text-primary);' : 'color: var(--color-text-secondary);' ?>">
        <span class="w-1.5 h-1.5 rounded-full" style="background: <?= $meta['color'] ?>;"></span>
        <?= e($meta['label']) ?> <span class="text-[10px] opacity-60">(<?= (int) $c['n'] ?>)</span>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($templates)): ?>
<div class="surface p-10 text-center">
    <div class="text-5xl mb-3">📭</div>
    <h3 class="font-bold text-lg mb-1" style="color: var(--color-text-primary);">Sin templates en esta categoria</h3>
    <p class="text-sm" style="color: var(--color-text-secondary);">Prueba otra o ve a "Todos".</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($templates as $t):
        $catSlug = (string) ($t['category'] ?? 'general');
        $catMeta = $categoryMeta[$catSlug] ?? ['label' => ucfirst($catSlug), 'color' => '#64748B'];
        $meets = $t['_meets'] ?? true;
        $isGlobal = empty($t['tenant_id']);
    ?>
    <article class="surface p-5 flex flex-col transition hover:scale-[1.01]" style="<?= $meets ? '' : 'opacity:.65;' ?>">
        <div class="flex items-start gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl flex-shrink-0 flex items-center justify-center text-2xl"
                 style="background: <?= $catMeta['color'] ?>22;">
                <?= e((string) ($t['icon'] ?? '🪄')) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                    <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: <?= $catMeta['color'] ?>22; color: <?= $catMeta['color'] ?>;"><?= e($catMeta['label']) ?></span>
                    <?php if (!$isGlobal): ?>
                    <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: rgba(16,185,129,.1); color: #059669;">Custom</span>
                    <?php endif; ?>
                    <?php if (!empty($t['clone_count'])): ?>
                    <span class="text-[10px]" style="color: var(--color-text-tertiary);">· clonado <?= (int) $t['clone_count'] ?> veces</span>
                    <?php endif; ?>
                </div>
                <h3 class="font-bold leading-tight" style="color: var(--color-text-primary);"><?= e((string) $t['name']) ?></h3>
            </div>
        </div>
        <p class="text-sm mb-3 flex-1" style="color: var(--color-text-secondary);"><?= e(mb_substr((string) ($t['description'] ?? ''), 0, 200)) ?></p>

        <div class="text-[11px] flex items-center gap-2 mb-3" style="color: var(--color-text-tertiary);">
            <span class="font-mono px-1.5 py-0.5 rounded" style="background: var(--color-bg-secondary);"><?= e((string) ($t['_trigger_type'] ?? 'manual')) ?></span>
            <span>· <?= (int) ($t['_steps_count'] ?? 0) ?> steps</span>
        </div>

        <?php if (!empty($t['_requires'])): ?>
        <div class="text-[11px] mb-3" style="color: <?= $meets ? 'var(--color-text-tertiary)' : '#B45309' ?>;">
            <strong>Requiere:</strong> <?= e(implode(', ', $t['_requires'])) ?>
            <?php if (!$meets): ?> · <em>tu cuenta no lo cumple aun</em><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="flex items-center gap-2">
            <a href="<?= url('/workflows/templates/' . (int) $t['id']) ?>"
               class="flex-1 px-3 py-2 rounded-lg text-xs font-semibold text-center border"
               style="border-color: var(--color-border); color: var(--color-text-primary);">Ver detalle</a>
            <?php if ($meets): ?>
            <form action="<?= url('/workflows/templates/' . (int) $t['id'] . '/use') ?>" method="POST" class="flex-1">
                <?= csrf_field() ?>
                <button class="w-full px-3 py-2 rounded-lg text-xs font-semibold text-white" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">Usar template</button>
            </form>
            <?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php \App\Core\View::end(); ?>
