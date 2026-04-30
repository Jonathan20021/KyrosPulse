<?php
/** @var array $catalog */
/** @var array $tenantInts */
/** @var array $categories */
/** @var array $tenant */
/** @var string $planSlug */
/** @var string $filterCat */
/** @var string $q */
/** @var array $totals */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Catalogo de integraciones',
    'subtitle' => 'Conecta tu stack: WhatsApp Cloud API, CRMs, ecommerce, pagos, contact center y mas. Las integraciones premium se desbloquean en planes superiores.',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'integrations']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#FB7185;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php foreach ([
        ['Total disponibles', $totals['total'],      '#7C3AED', '🧩'],
        ['Conectadas',        $totals['connected'],  '#10B981', '✅'],
        ['Sin conectar',      max(0, $totals['disconnected']), '#94A3B8', '⏸'],
        ['Con errores',       $totals['errors'],     '#F43F5E', '⚠'],
    ] as [$lbl, $val, $col, $emoji]): ?>
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);"><?= e($lbl) ?></span>
            <span class="text-xl"><?= $emoji ?></span>
        </div>
        <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= number_format($val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<div class="surface p-3 mb-4 flex items-center gap-2 flex-wrap">
    <form method="GET" class="flex-1 relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Buscar (Slack, Stripe, HubSpot...)" class="input pl-10 text-sm">
        <?php if ($filterCat !== ''): ?>
        <input type="hidden" name="category" value="<?= e($filterCat) ?>">
        <?php endif; ?>
    </form>
    <a href="<?= url('/settings/integrations') ?>" class="px-3 py-1.5 rounded-full text-xs font-medium <?= $filterCat === '' ? 'font-semibold' : '' ?>"
       style="<?= $filterCat === '' ? 'background: var(--color-bg-active); color: var(--color-text-primary); border:1px solid rgba(124,58,237,0.2);' : 'background: var(--color-bg-subtle); color: var(--color-text-tertiary);' ?>">Todas</a>
    <?php foreach ($categories as $key => [$label, $color, $iconKey]):
        $active = $filterCat === $key;
    ?>
    <a href="<?= url('/settings/integrations?category=' . urlencode($key)) ?>" class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap"
       style="<?= $active ? "background: $color" . '22; color: ' . $color . '; border:1px solid ' . $color . '55;' : 'background: var(--color-bg-subtle); color: var(--color-text-tertiary);' ?>">
        <?= e($label) ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($catalog)): ?>
<div class="surface p-10 text-center">
    <div class="text-3xl mb-2">🤷</div>
    <p style="color: var(--color-text-tertiary);">No encontramos integraciones que coincidan.</p>
</div>
<?php else: ?>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
    <?php foreach ($catalog as $entry):
        $cat = $entry['category'] ?? 'general';
        [$catLabel, $catColor] = $categories[$cat] ?? ['General', '#94A3B8'];
        $existing = $tenantInts[$entry['slug']] ?? null;
        $status = $existing['status'] ?? 'disconnected';
        $isPremium = !empty($entry['is_premium']);
        $needsPlan = $entry['min_plan'] ?? null;
        $planRanks = ['starter' => 0, 'professional' => 1, 'business' => 2, 'enterprise' => 3];
        $userRank = $planRanks[$planSlug] ?? 0;
        $needRank = $planRanks[$needsPlan] ?? 0;
        $locked   = $needsPlan && $userRank < $needRank;
        $statusColor = match ($status) { 'connected' => '#10B981', 'error' => '#F43F5E', 'pending' => '#F59E0B', default => '#94A3B8' };
        $statusLabel = match ($status) { 'connected' => 'Conectada', 'error' => 'Error', 'pending' => 'Pendiente', default => 'Disponible' };
    ?>
    <div class="surface p-4 hover:scale-[1.01] transition relative overflow-hidden">
        <?php if ($isPremium): ?>
        <div class="absolute top-3 right-3 text-[9px] px-2 py-0.5 rounded-full uppercase font-bold tracking-wider" style="background: linear-gradient(135deg,#F59E0B,#EF4444); color: white;">Premium</div>
        <?php endif; ?>

        <div class="flex items-start gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl text-white shadow-lg flex-shrink-0" style="background: <?= e($catColor) ?>;">
                <?= e(strtoupper(mb_substr($entry['name'], 0, 1))) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-0.5">
                    <h3 class="font-bold text-sm truncate" style="color: var(--color-text-primary);"><?= e($entry['name']) ?></h3>
                </div>
                <span class="text-[10px] font-semibold uppercase tracking-wider" style="color: <?= $catColor ?>;"><?= e($catLabel) ?></span>
            </div>
        </div>

        <p class="text-xs leading-relaxed mb-3 min-h-[40px]" style="color: var(--color-text-tertiary);"><?= e((string) ($entry['description'] ?? '')) ?></p>

        <div class="flex items-center justify-between gap-2">
            <span class="flex items-center gap-1 text-[11px] font-semibold" style="color: <?= $statusColor ?>;">
                <span class="w-1.5 h-1.5 rounded-full" style="background: <?= $statusColor ?>;"></span>
                <?= e($statusLabel) ?>
            </span>
            <?php if ($locked): ?>
            <a href="<?= url('/settings#plans') ?>" class="text-[11px] font-semibold flex items-center gap-1 px-2.5 py-1 rounded-lg" style="background: rgba(245,158,11,.12); color: #F59E0B;">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                Plan <?= e(ucfirst((string) $needsPlan)) ?>
            </a>
            <?php else: ?>
            <a href="<?= url('/settings/integrations/' . $entry['slug']) ?>" class="text-[11px] font-semibold px-2.5 py-1 rounded-lg" style="<?= $status === 'connected' ? 'background: rgba(16,185,129,.12); color:#10B981;' : 'background: var(--gradient-primary); color:white;' ?>">
                <?= $status === 'connected' ? 'Configurar →' : 'Conectar →' ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php \App\Core\View::stop(); ?>
