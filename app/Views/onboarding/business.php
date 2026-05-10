<?php
\App\Core\View::extend('layouts.onboarding');
\App\Core\View::start('content');
$tenant = $tenant ?? [];
$currencies = ['USD','DOP','MXN','EUR','COP','CLP','PEN','ARS','BRL','GTQ','UYU'];
$timezones  = ['America/Santo_Domingo','America/Mexico_City','America/New_York','America/Bogota','America/Lima','America/Santiago','America/Buenos_Aires','America/Sao_Paulo','Europe/Madrid'];
?>
<div class="text-center mb-8">
    <div class="text-4xl mb-3">🏢</div>
    <h1 class="text-2xl sm:text-3xl font-black text-white mb-2 tracking-tight">Tu negocio</h1>
    <p class="text-slate-400">Lo basico para personalizar tu workspace.</p>
</div>

<form action="<?= url('/onboarding/business') ?>" method="POST" class="surface p-6 space-y-4">
    <?= csrf_field() ?>

    <div>
        <label class="block text-xs font-semibold text-slate-300 mb-1.5 tracking-wider uppercase">Nombre del negocio *</label>
        <input name="name" required maxlength="150" value="<?= e((string) ($tenant['name'] ?? '')) ?>"
               placeholder="Ej: Pizzeria La Bella"
               class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/40">
    </div>

    <div>
        <label class="block text-xs font-semibold text-slate-300 mb-1.5 tracking-wider uppercase">Industria</label>
        <input name="industry" maxlength="80" value="<?= e((string) ($tenant['industry'] ?? '')) ?>"
               placeholder="Restaurante, e-commerce, servicios, salud, etc."
               class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/40">
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5 tracking-wider uppercase">Moneda</label>
            <select name="currency" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-violet-500/50">
                <?php foreach ($currencies as $c): ?>
                <option value="<?= $c ?>" <?= ($tenant['currency'] ?? 'USD') === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5 tracking-wider uppercase">Zona horaria</label>
            <select name="timezone" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-violet-500/50">
                <?php foreach ($timezones as $tz): ?>
                <option value="<?= $tz ?>" <?= ($tenant['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <label class="flex items-start gap-3 p-3 rounded-xl cursor-pointer hover:bg-white/5 transition" style="background: rgba(245,158,11,.05); border: 1px solid rgba(245,158,11,.2);">
        <input type="checkbox" name="is_restaurant" value="1" <?= !empty($tenant['is_restaurant']) ? 'checked' : '' ?> class="mt-1">
        <div>
            <div class="font-semibold text-white text-sm">🍽 Soy restaurante / food delivery</div>
            <div class="text-xs text-slate-400 mt-0.5">Activa modulos especificos: menu publico, carrito, zonas de delivery, agente mesero.</div>
        </div>
    </label>

    <div class="flex items-center justify-between pt-3">
        <a href="<?= url('/onboarding') ?>" class="text-sm text-slate-400 hover:text-white">← Volver</a>
        <button class="px-6 py-3 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">
            Continuar →
        </button>
    </div>
</form>
<?php \App\Core\View::end(); ?>
