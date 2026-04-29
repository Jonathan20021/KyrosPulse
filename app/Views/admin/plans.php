<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-white mb-1">Planes y precios</h1>
    <p class="text-sm text-slate-400">Configura limites, precios y features que cada plan incluye.</p>
</div>

<div class="grid md:grid-cols-2 gap-4">
    <?php foreach ($plans as $plan): ?>
    <form action="<?= url('/admin/plans/' . $plan['id']) ?>" method="POST" class="admin-card rounded-2xl p-5">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="PUT">

        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl font-bold text-white shadow-lg shadow-violet-500/30" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);"><?= e(strtoupper(mb_substr($plan['name'], 0, 1))) ?></div>
                <div>
                    <h3 class="font-bold text-white"><?= e($plan['name']) ?></h3>
                    <code class="text-xs text-slate-500"><?= e($plan['slug']) ?></code>
                </div>
            </div>
            <label class="flex items-center gap-2 text-xs text-slate-300 px-3 py-1.5 rounded-lg bg-white/5 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" <?= !empty($plan['is_active']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                Activo
            </label>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-3">
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Mensual (USD)</label>
                <input type="number" step="0.01" name="price_monthly" value="<?= e((string) $plan['price_monthly']) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Anual (USD)</label>
                <input type="number" step="0.01" name="price_yearly" value="<?= e((string) $plan['price_yearly']) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <?php
            $limits = [
                ['max_users','Usuarios'],
                ['max_contacts','Contactos'],
                ['max_messages','Mensajes/mes'],
                ['max_campaigns','Campanas'],
            ];
            foreach ($limits as [$k, $label]):
            ?>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400"><?= e($label) ?></label>
                <input type="number" name="<?= e($k) ?>" value="<?= (int) ($plan[$k] ?? 0) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <?php endforeach; ?>
        </div>

        <div class="space-y-2 mb-4">
            <?php
            $features = [
                ['ai_enabled', 'IA (Claude / OpenAI)', '🤖'],
                ['advanced_reports', 'Reportes avanzados', '📊'],
                ['api_access', 'Acceso API', '🔌'],
            ];
            foreach ($features as [$k, $label, $i]):
            ?>
            <label class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-white/5 border border-white/10 cursor-pointer">
                <span class="text-sm text-slate-300 flex items-center gap-2"><?= $i ?> <?= e($label) ?></span>
                <input type="checkbox" name="<?= e($k) ?>" value="1" <?= !empty($plan[$k]) ? 'checked' : '' ?> class="w-4 h-4 rounded">
            </label>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="w-full px-4 py-2 rounded-lg text-white text-sm font-semibold shadow-lg shadow-violet-500/20" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">Guardar plan</button>
    </form>
    <?php endforeach; ?>
</div>

<?php \App\Core\View::stop(); ?>
