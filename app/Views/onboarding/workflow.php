<?php
\App\Core\View::extend('layouts.onboarding');
\App\Core\View::start('content');
$featured = $featuredTemplates ?? [];
$tenant   = $tenant ?? [];
?>
<div class="text-center mb-8">
    <div class="text-4xl mb-3">🪄</div>
    <h1 class="text-2xl sm:text-3xl font-black text-white mb-2 tracking-tight">Tu primer workflow</h1>
    <p class="text-slate-400">Elige una automatizacion lista para arrancar. La activamos inmediatamente.</p>
</div>

<form action="<?= url('/onboarding/workflow') ?>" method="POST" x-data="{ selected: 0 }" class="space-y-4">
    <?= csrf_field() ?>

    <?php if (!empty($featured)): ?>
    <div class="grid sm:grid-cols-2 gap-3">
        <?php foreach ($featured as $t): ?>
        <label class="surface p-4 cursor-pointer transition hover:scale-[1.01]"
               :style="selected === <?= (int) $t['id'] ?> ? 'border-color: rgba(139,92,246,.6); background: rgba(139,92,246,.08);' : ''">
            <input type="radio" name="template_id" value="<?= (int) $t['id'] ?>" @click="selected = <?= (int) $t['id'] ?>" class="sr-only">
            <div class="flex items-start gap-3">
                <div class="text-2xl flex-shrink-0"><?= e((string) ($t['icon'] ?? '🪄')) ?></div>
                <div class="min-w-0">
                    <div class="font-bold text-white text-sm mb-0.5"><?= e((string) $t['name']) ?></div>
                    <p class="text-[11px] text-slate-400 leading-snug"><?= e(mb_substr((string) $t['description'], 0, 100)) ?></p>
                    <div class="text-[10px] mt-1.5 text-slate-500"><?= e((string) $t['category']) ?> · <?= (int) ($t['clone_count'] ?? 0) ?> clones</div>
                </div>
            </div>
        </label>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="surface p-6 text-center text-sm text-slate-400">
        Sin templates disponibles. Puedes saltar este paso y crear workflows desde cero despues.
    </div>
    <?php endif; ?>

    <div class="p-3 rounded-xl text-xs" style="background: rgba(16,185,129,.06); border: 1px solid rgba(16,185,129,.2); color: #6EE7B7;">
        🚀 El workflow se activa automaticamente al confirmar. Empieza a trabajar para ti desde el primer minuto.
    </div>

    <div class="flex items-center justify-between pt-2 gap-3 flex-wrap">
        <button type="submit" name="skip" value="1" class="text-sm text-slate-400 hover:text-white">Saltar y terminar →</button>
        <button class="px-6 py-3 rounded-xl text-white font-bold shadow-2xl text-base" style="background: linear-gradient(135deg,#10B981,#06B6D4);">
            🎉 Finalizar setup
        </button>
    </div>
</form>
<?php \App\Core\View::end(); ?>
