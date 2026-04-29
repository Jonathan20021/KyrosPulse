<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
?>
<div class="mb-6">
    <h1 class="text-2xl lg:text-[28px] font-bold tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;">Planes</h1>
    <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">Configura limites y precios de cada plan de la plataforma.</p>
</div>

<div class="grid md:grid-cols-2 gap-4">
    <?php foreach ($plans as $plan): ?>
    <form action="<?= url('/admin/plans/' . $plan['id']) ?>" method="POST" class="surface p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="PUT">

        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: var(--gradient-primary); opacity: 0.15;">
                    <span class="font-bold text-lg gradient-text"><?= e(strtoupper(mb_substr($plan['name'], 0, 1))) ?></span>
                </div>
                <div>
                    <h3 class="font-bold" style="color: var(--color-text-primary);"><?= e($plan['name']) ?></h3>
                    <code class="text-xs" style="color: var(--color-text-tertiary);"><?= e($plan['slug']) ?></code>
                </div>
            </div>
            <label class="flex items-center gap-2 text-xs" style="color: var(--color-text-secondary);">
                <input type="checkbox" name="is_active" value="1" <?= !empty($plan['is_active']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                Activo
            </label>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <div>
                <label class="label">Mensual (USD)</label>
                <input type="number" step="0.01" name="price_monthly" value="<?= e((string) $plan['price_monthly']) ?>" class="input text-sm">
            </div>
            <div>
                <label class="label">Anual (USD)</label>
                <input type="number" step="0.01" name="price_yearly" value="<?= e((string) $plan['price_yearly']) ?>" class="input text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <div>
                <label class="label">Usuarios</label>
                <input type="number" name="max_users" value="<?= (int) $plan['max_users'] ?>" class="input text-sm">
            </div>
            <div>
                <label class="label">Contactos</label>
                <input type="number" name="max_contacts" value="<?= (int) $plan['max_contacts'] ?>" class="input text-sm">
            </div>
            <div>
                <label class="label">Mensajes/mes</label>
                <input type="number" name="max_messages" value="<?= (int) $plan['max_messages'] ?>" class="input text-sm">
            </div>
            <div>
                <label class="label">Campanas</label>
                <input type="number" name="max_campaigns" value="<?= (int) $plan['max_campaigns'] ?>" class="input text-sm">
            </div>
        </div>

        <div class="flex flex-wrap gap-3 mb-5 text-sm">
            <label class="flex items-center gap-1.5" style="color: var(--color-text-secondary);">
                <input type="checkbox" name="ai_enabled" value="1" <?= !empty($plan['ai_enabled']) ? 'checked' : '' ?> class="w-4 h-4 rounded"> IA Claude
            </label>
            <label class="flex items-center gap-1.5" style="color: var(--color-text-secondary);">
                <input type="checkbox" name="advanced_reports" value="1" <?= !empty($plan['advanced_reports']) ? 'checked' : '' ?> class="w-4 h-4 rounded"> Reportes avanzados
            </label>
            <label class="flex items-center gap-1.5" style="color: var(--color-text-secondary);">
                <input type="checkbox" name="api_access" value="1" <?= !empty($plan['api_access']) ? 'checked' : '' ?> class="w-4 h-4 rounded"> API access
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-sm w-full">Guardar plan</button>
    </form>
    <?php endforeach; ?>
</div>
<?php \App\Core\View::stop(); ?>
