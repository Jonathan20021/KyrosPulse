<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title' => 'Automatizaciones',
    'subtitle' => 'Reglas que se disparan ante eventos como nuevo mensaje, lead movido o sin respuesta.',
    'actionUrl' => url('/automations/create'),
    'actionLabel' => 'Nueva automatizacion',
    'actionIcon' => 'plus',
]); ?>

<?php if (empty($automations)): ?>
<div class="surface">
    <?php \App\Core\View::include('components.empty_state', [
        'icon' => '⚡',
        'title' => 'Aun no hay automatizaciones',
        'message' => 'Crea reglas que respondan automaticamente a mensajes, asignen leads o etiqueten contactos.',
        'ctaUrl' => url('/automations/create'),
        'ctaLabel' => 'Crear primera regla',
    ]); ?>
</div>
<?php else: ?>
<div class="space-y-3">
    <?php foreach ($automations as $a):
        $triggerName = $triggers[$a['trigger_event']] ?? $a['trigger_event'];
        $actionsArr  = json_decode((string) $a['actions'], true) ?: [];
    ?>
    <div class="surface p-5 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-4 min-w-0 flex-1">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 text-xl"
                 style="background: <?= $a['is_active'] ? 'linear-gradient(135deg, rgba(124,58,237,.15), rgba(6,182,212,.15))' : 'var(--color-bg-subtle)' ?>;">
                ⚡
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                    <a href="<?= url('/automations/' . $a['id']) ?>" class="font-semibold hover:underline" style="color: var(--color-text-primary);"><?= e($a['name']) ?></a>
                    <span class="badge <?= $a['is_active'] ? 'badge-emerald' : 'badge-slate' ?>">
                        <?= $a['is_active'] ? 'Activa' : 'Pausada' ?>
                    </span>
                </div>
                <div class="text-xs flex items-center gap-2" style="color: var(--color-text-tertiary);">
                    <span class="badge badge-cyan">Cuando</span>
                    <span><?= e($triggerName) ?></span>
                    <span style="color: var(--color-text-muted);">·</span>
                    <span class="badge badge-primary"><?= count($actionsArr) ?> acciones</span>
                </div>
                <div class="text-xs mt-1" style="color: var(--color-text-muted);">
                    Ejecutada <?= (int) $a['runs_count'] ?> veces
                    <?= !empty($a['last_run_at']) ? ' · Ultima: ' . time_ago((string) $a['last_run_at']) : '' ?>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-1.5">
            <form action="<?= url('/automations/' . $a['id'] . '/toggle') ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm <?= $a['is_active'] ? '' : 'text-emerald-500' ?>">
                    <?= $a['is_active'] ? '⏸ Pausar' : '▶ Activar' ?>
                </button>
            </form>
            <a href="<?= url('/automations/' . $a['id']) ?>" class="btn btn-ghost btn-sm">Editar</a>
            <form action="<?= url('/automations/' . $a['id']) ?>" method="POST" onsubmit="return confirm('Eliminar?')">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button class="btn btn-ghost btn-icon text-rose-500 hover:bg-rose-500/10">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php \App\Core\View::stop(); ?>
