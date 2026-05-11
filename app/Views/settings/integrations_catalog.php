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
<?php \App\Core\View::include('settings._tabs', ['tab' => 'integrations']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Canales',
    'title'    => 'Catalogo de integraciones',
    'subtitle' => 'Conecta tu stack: WhatsApp Cloud API, CRMs, ecommerce, pagos, contact center y mas. Las integraciones premium se desbloquean en planes superiores.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="set-notice set-notice-info">
    <div class="set-notice-icon">🔔</div>
    <div class="set-notice-body">
        <div class="set-notice-title">¿Quieres recibir ordenes, leads o tickets en Slack, Discord, Teams, Telegram o por correo?</div>
        <p class="set-notice-desc">
            Esos canales viven en el panel de <strong>Notificaciones</strong>, donde defines a que eventos suscribirte
            (orden lista, orden entregada, lead nuevo, etc.) y configuras multiples destinatarios.
        </p>
    </div>
    <a href="<?= url('/settings/notifications') ?>" class="set-btn set-btn-primary set-btn-sm">Ir a Notificaciones →</a>
</div>

<div class="set-kpi-grid">
    <?php foreach ([
        ['Total disponibles', $totals['total'],      '#10B981', '🧩'],
        ['Conectadas',        $totals['connected'],  '#10B981', '✅'],
        ['Sin conectar',      max(0, $totals['disconnected']), '#94A3B8', '⏸'],
        ['Con errores',       $totals['errors'],     '#F43F5E', '⚠'],
    ] as [$lbl, $val, $col, $emoji]): ?>
    <div class="set-kpi">
        <div class="set-kpi-head">
            <span class="set-kpi-label"><?= e($lbl) ?></span>
            <span class="set-kpi-emoji"><?= $emoji ?></span>
        </div>
        <div class="set-kpi-value" style="color: <?= $col ?>;"><?= number_format($val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="set-toolbar">
    <form method="GET" class="set-toolbar-search">
        <svg class="set-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Buscar (Slack, Stripe, HubSpot...)" class="set-input set-input-search">
        <?php if ($filterCat !== ''): ?>
        <input type="hidden" name="category" value="<?= e($filterCat) ?>">
        <?php endif; ?>
    </form>
    <div class="set-chip-group">
        <a href="<?= url('/settings/integrations') ?>" class="set-chip <?= $filterCat === '' ? 'is-active' : '' ?>">Todas</a>
        <?php foreach ($categories as $key => [$label, $color, $iconKey]):
            $active = $filterCat === $key;
        ?>
        <a href="<?= url('/settings/integrations?category=' . urlencode($key)) ?>" class="set-chip <?= $active ? 'is-active' : '' ?>"
           style="<?= $active ? '--chip-color: ' . $color . ';' : '' ?>">
            <?= e($label) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($catalog)): ?>
<div class="set-empty">
    <div class="set-empty-icon">🤷</div>
    <p class="set-empty-text">No encontramos integraciones que coincidan.</p>
</div>
<?php else: ?>
<div class="set-card-grid">
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
    <div class="set-int-card">
        <?php if ($isPremium): ?>
        <div class="set-int-card-premium">Premium</div>
        <?php endif; ?>

        <div class="set-int-card-head">
            <div class="set-int-card-logo" style="background: <?= e($catColor) ?>;">
                <?= e(strtoupper(mb_substr($entry['name'], 0, 1))) ?>
            </div>
            <div class="set-int-card-meta">
                <h3 class="set-int-card-name"><?= e($entry['name']) ?></h3>
                <span class="set-int-card-cat" style="color: <?= $catColor ?>;"><?= e($catLabel) ?></span>
            </div>
        </div>

        <p class="set-int-card-desc"><?= e((string) ($entry['description'] ?? '')) ?></p>

        <div class="set-int-card-foot">
            <span class="set-int-card-status" style="color: <?= $statusColor ?>;">
                <span class="set-int-dot" style="background: <?= $statusColor ?>;"></span>
                <?= e($statusLabel) ?>
            </span>
            <?php if ($locked): ?>
            <a href="<?= url('/settings#plans') ?>" class="set-int-locked">
                <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                Plan <?= e(ucfirst((string) $needsPlan)) ?>
            </a>
            <?php else: ?>
            <a href="<?= url('/settings/integrations/' . $entry['slug']) ?>"
               class="set-btn set-btn-sm <?= $status === 'connected' ? 'set-btn-soft' : 'set-btn-primary' ?>">
                <?= $status === 'connected' ? 'Configurar →' : 'Conectar →' ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
