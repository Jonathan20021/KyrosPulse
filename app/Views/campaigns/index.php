<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title' => 'Campanas',
    'subtitle' => 'Envia mensajes masivos por WhatsApp respetando opt-in y limites del plan.',
    'actionUrl' => url('/campaigns/create'),
    'actionLabel' => 'Nueva campana',
    'actionIcon' => 'plus',
]); ?>

<?php if (empty($campaigns)): ?>
<div class="surface">
    <?php \App\Core\View::include('components.empty_state', [
        'icon' => '📢',
        'title' => 'Aun no tienes campanas',
        'message' => 'Crea tu primera campana de WhatsApp masiva. Solo se enviaran mensajes a contactos con consentimiento.',
        'ctaUrl' => url('/campaigns/create'),
        'ctaLabel' => 'Crear campana',
    ]); ?>
</div>
<?php else: ?>
<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($campaigns as $c):
        $rate = ($c['total_sent'] ?? 0) > 0 ? round((((int) $c['total_read']) / ((int) $c['total_sent'])) * 100) : 0;
        $statusCls = match ($c['status']) {
            'completed' => 'badge-emerald',
            'sending'   => 'badge-cyan',
            'scheduled' => 'badge-primary',
            'draft'     => 'badge-slate',
            'failed'    => 'badge-rose',
            default     => 'badge-slate',
        };
    ?>
    <a href="<?= url('/campaigns/' . $c['id']) ?>" class="surface p-5 hover-lift block">
        <div class="flex items-center justify-between mb-3">
            <span class="badge <?= $statusCls ?>"><?= e($c['status']) ?></span>
            <span class="text-xs" style="color: var(--color-text-muted);"><?= time_ago((string) $c['created_at']) ?></span>
        </div>
        <h3 class="font-bold mb-2 truncate" style="color: var(--color-text-primary);"><?= e($c['name']) ?></h3>
        <p class="text-sm line-clamp-2 mb-4 h-10" style="color: var(--color-text-tertiary);"><?= e(mb_substr((string) ($c['message'] ?? ''), 0, 100)) ?></p>

        <div class="grid grid-cols-3 gap-2 text-center">
            <div class="p-2 rounded-lg" style="background: var(--color-bg-subtle);">
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= number_format((int) $c['total_recipients']) ?></div>
                <div class="text-[10px] uppercase tracking-wider mt-0.5" style="color: var(--color-text-tertiary);">Audiencia</div>
            </div>
            <div class="p-2 rounded-lg" style="background: var(--color-bg-subtle);">
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= number_format((int) $c['total_sent']) ?></div>
                <div class="text-[10px] uppercase tracking-wider mt-0.5" style="color: var(--color-text-tertiary);">Enviados</div>
            </div>
            <div class="p-2 rounded-lg" style="background: var(--color-bg-subtle);">
                <div class="text-lg font-bold gradient-text"><?= $rate ?>%</div>
                <div class="text-[10px] uppercase tracking-wider mt-0.5" style="color: var(--color-text-tertiary);">Lectura</div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php \App\Core\View::stop(); ?>
