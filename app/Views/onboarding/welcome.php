<?php
\App\Core\View::extend('layouts.onboarding');
\App\Core\View::start('content');
$tenant = $tenant ?? [];
?>
<div class="text-center mb-10">
    <div class="inline-block mb-5">
        <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto text-4xl shadow-2xl"
             style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">ðŸ‘‹</div>
    </div>
    <h1 class="text-3xl sm:text-4xl font-black text-white mb-3 tracking-tight">Bienvenido a <?= e((string) ($tenant['name'] ?? 'Evallish Pulse')) ?></h1>
    <p class="text-slate-400 text-base sm:text-lg max-w-xl mx-auto">En 4 pasos vas a tener una operacion completa de ventas + IA + automatizacion corriendo. Toma menos de 3 minutos.</p>
</div>

<div class="grid sm:grid-cols-2 gap-3 mb-8">
    <?php foreach ([
        ['icon' => 'ðŸ’¬', 'title' => 'WhatsApp conectado', 'desc' => 'Recibe y responde mensajes en automatico desde la inbox.'],
        ['icon' => 'ðŸ§ ', 'title' => 'Agente IA propio',   'desc' => 'Califica leads, recomienda productos y cierra ventas mientras duermes.'],
        ['icon' => 'ðŸª„', 'title' => 'Workflows pre-armados', 'desc' => 'Bienvenida automatica, recuperacion de carrito, escalamiento de tickets.'],
        ['icon' => 'ðŸ“Š', 'title' => 'Dashboard ejecutivo', 'desc' => 'KPIs cross-feature en una sola vista. Saber como va tu negocio en 10 segundos.'],
    ] as $f): ?>
    <div class="surface p-4 flex items-start gap-3">
        <div class="text-2xl flex-shrink-0"><?= $f['icon'] ?></div>
        <div>
            <div class="font-semibold text-white text-sm mb-0.5"><?= e($f['title']) ?></div>
            <p class="text-xs text-slate-400 leading-snug"><?= e($f['desc']) ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<form action="<?= url('/onboarding/welcome') ?>" method="POST" class="text-center">
    <?= csrf_field() ?>
    <button class="px-8 py-3.5 rounded-xl text-white font-bold shadow-2xl text-base"
            style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">
        Empezar setup â†’
    </button>
    <p class="text-xs text-slate-500 mt-3">Puedes saltarte cualquier paso. Todo se configura despues si quieres.</p>
</form>
<?php \App\Core\View::end(); ?>
