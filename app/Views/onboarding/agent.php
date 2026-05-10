<?php
\App\Core\View::extend('layouts.onboarding');
\App\Core\View::start('content');
$tenant = $tenant ?? [];
$isRestaurant = !empty($tenant['is_restaurant']);
?>
<div class="text-center mb-8">
    <div class="text-4xl mb-3">🧠</div>
    <h1 class="text-2xl sm:text-3xl font-black text-white mb-2 tracking-tight">Tu primer agente IA</h1>
    <p class="text-slate-400">Elige un preset para arrancar rapido. Lo editas todo despues.</p>
</div>

<form action="<?= url('/onboarding/agent') ?>" method="POST" x-data="{ preset: '<?= $isRestaurant ? 'restaurant' : 'sales' ?>', custom: false }" class="space-y-4">
    <?= csrf_field() ?>

    <!-- Presets -->
    <div class="grid sm:grid-cols-3 gap-3">
        <?php
        $presets = [
            'sales'      => ['icon' => '💼', 'name' => 'Vendedor',   'desc' => 'Califica leads, recomienda productos, cierra ventas.'],
            'support'    => ['icon' => '🎧', 'name' => 'Soporte',    'desc' => 'Resuelve dudas, abre tickets, escala a humano.'],
            'restaurant' => ['icon' => '🍽', 'name' => 'Mesero IA',  'desc' => 'Toma pedidos del menu, arma orden con delivery/pickup.'],
        ];
        foreach ($presets as $key => $p): ?>
        <label class="surface p-4 cursor-pointer transition hover:scale-[1.01]"
               :style="preset === '<?= $key ?>' && !custom ? 'border-color: rgba(139,92,246,.6); background: rgba(139,92,246,.08);' : ''">
            <input type="radio" name="preset" value="<?= $key ?>" x-model="preset" @change="custom = false" class="sr-only">
            <div class="text-3xl mb-2"><?= $p['icon'] ?></div>
            <div class="font-bold text-white text-sm mb-1"><?= e($p['name']) ?></div>
            <p class="text-[11px] text-slate-400 leading-snug"><?= e($p['desc']) ?></p>
        </label>
        <?php endforeach; ?>
    </div>

    <!-- Custom option -->
    <div class="surface p-4">
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" x-model="custom" @change="if (custom) preset = ''" class="mt-1">
            <div class="flex-1">
                <div class="font-semibold text-white text-sm">O define el tuyo desde cero</div>
                <p class="text-xs text-slate-400 mt-0.5 mb-3">Para casos especificos que no encajan en los presets.</p>
            </div>
        </label>
        <div x-show="custom" x-cloak class="space-y-2 mt-2">
            <input name="name" placeholder="Nombre del agente (ej: Lucia, Asistente Premium)"
                   class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
            <input name="tone" placeholder="Tono (ej: amable, casual, profesional)"
                   value="profesional, cercano y claro"
                   class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
        </div>
    </div>

    <div class="p-3 rounded-xl text-xs" style="background: rgba(139,92,246,.06); border: 1px solid rgba(139,92,246,.2); color: #C4B5FD;">
        ✨ El agente arranca con auto-respuesta activada en WhatsApp. Si despues quieres pausarlo o ajustarlo, vas a Configuracion → IA.
    </div>

    <div class="flex items-center justify-between pt-2 gap-3 flex-wrap">
        <button type="submit" name="skip" value="1" class="text-sm text-slate-400 hover:text-white">Saltar este paso →</button>
        <button class="px-6 py-3 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">
            Crear agente →
        </button>
    </div>
</form>
<?php \App\Core\View::end(); ?>
