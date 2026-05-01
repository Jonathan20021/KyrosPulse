<?php
/** @var array $item */
$mods = !empty($item['modifiers']) ? (json_decode((string) $item['modifiers'], true) ?: []) : [];
$available = !empty($item['is_available']);
?>
<div class="rounded-xl border p-3 flex flex-col" style="background: var(--color-bg-elevated); border-color: var(--color-border-subtle);">
    <?php if (!empty($item['photo'])): ?>
    <div class="rounded-lg mb-2 overflow-hidden" style="aspect-ratio: 16/9; background: var(--color-bg-subtle);">
        <img src="<?= e($item['photo']) ?>" alt="" class="w-full h-full object-cover" loading="lazy">
    </div>
    <?php endif; ?>
    <div class="flex items-start gap-2 mb-1">
        <h4 class="font-bold text-sm flex-1" style="color: var(--color-text-primary);"><?= e($item['name']) ?></h4>
        <span class="text-sm font-bold" style="color: var(--color-primary);"><?= e((string) ($item['currency'] ?? 'USD')) ?> <?= number_format((float) $item['price'], 2) ?></span>
    </div>
    <?php if (!empty($item['description'])): ?>
    <p class="text-xs mb-2 line-clamp-2" style="color: var(--color-text-tertiary);"><?= e((string) $item['description']) ?></p>
    <?php endif; ?>

    <?php if (!empty($mods)): ?>
    <div class="text-[10px] mb-2" style="color: var(--color-text-tertiary);">
        <span class="font-semibold">Modificadores:</span>
        <?= e(implode(', ', array_map(fn ($m) => $m['name'] . (isset($m['price']) && (float) $m['price'] > 0 ? ' (+' . number_format((float) $m['price'], 2) . ')' : ''), $mods))) ?>
    </div>
    <?php endif; ?>

    <div class="flex items-center gap-1 text-[10px] mb-2 flex-wrap">
        <?php if (!empty($item['sku'])): ?><span class="font-mono px-1.5 py-0.5 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);"><?= e($item['sku']) ?></span><?php endif; ?>
        <?php if (!empty($item['prep_time_min'])): ?><span style="color: var(--color-text-tertiary);">⏱ <?= (int) $item['prep_time_min'] ?> min</span><?php endif; ?>
        <?php if (!empty($item['is_combo'])): ?><span class="px-1.5 py-0.5 rounded" style="background: rgba(124,58,237,.15); color:#A78BFA;">Combo</span><?php endif; ?>
        <?php if (!empty($item['is_featured'])): ?><span class="px-1.5 py-0.5 rounded" style="background: rgba(245,158,11,.15); color:#F59E0B;">⭐ Destacado</span><?php endif; ?>
    </div>

    <div class="flex items-center gap-1 mt-auto">
        <span class="flex items-center gap-1 text-[10px] font-semibold" style="color: <?= $available ? '#34D399' : '#F87171' ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background: <?= $available ? '#10B981' : '#EF4444' ?>;"></span>
            <?= $available ? 'Disponible' : 'Agotado' ?>
        </span>
        <form action="<?= url('/menu/items/' . $item['id'] . '/toggle') ?>" method="POST" class="inline ml-auto">
            <?= csrf_field() ?>
            <button type="submit" class="text-[10px] px-2 py-1 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);"><?= $available ? 'Pausar' : 'Activar' ?></button>
        </form>
        <form action="<?= url('/menu/items/' . $item['id']) ?>" method="POST" class="inline" onsubmit="return confirm('Eliminar articulo?')">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="text-[10px] px-2 py-1 rounded" style="color: #F87171;">✗</button>
        </form>
    </div>
</div>
