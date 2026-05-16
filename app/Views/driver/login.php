<?php
\App\Core\View::extend('layouts.driver');
\App\Core\View::start('content');
?>
<div class="min-h-screen flex flex-col items-center justify-center px-6 py-10">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="w-20 h-20 mx-auto mb-4 rounded-3xl flex items-center justify-center text-5xl" style="background: linear-gradient(135deg,#10B981,#06B6D4); box-shadow: 0 10px 40px rgba(16,185,129,.4);">🛵</div>
            <h1 class="text-2xl font-bold mb-1">Portal del Repartidor</h1>
            <p class="text-sm" style="color: var(--color-text-tertiary);">Ingresa con tu telefono y PIN.</p>
        </div>

        <?php if ($flash = flash('error')): ?>
        <div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.3); color:#F87171;"><?= e((string) $flash) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= url('/driver/login') ?>" class="space-y-3">
            <?= csrf_field() ?>
            <div>
                <label class="text-[10px] uppercase tracking-wider font-semibold mb-1 block" style="color: var(--color-text-tertiary);">Telefono</label>
                <input name="phone" type="tel" required autocomplete="tel" placeholder="809-555-1234"
                       class="w-full px-4 py-3 rounded-xl text-lg font-semibold"
                       style="background: var(--color-bg-elevated); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider font-semibold mb-1 block" style="color: var(--color-text-tertiary);">PIN</label>
                <input name="pin" type="password" required inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="current-password" placeholder="••••"
                       class="w-full px-4 py-3 rounded-xl text-2xl font-bold text-center tracking-[.3em]"
                       style="background: var(--color-bg-elevated); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
            </div>
            <button class="tap w-full px-4 py-3 rounded-xl text-white font-bold text-lg" style="background: linear-gradient(135deg,#10B981,#06B6D4); box-shadow: 0 8px 24px rgba(16,185,129,.3);">
                Entrar →
            </button>
        </form>

        <div class="mt-8 text-center text-xs" style="color: var(--color-text-tertiary);">
            Si no tienes credenciales, pidelas al admin de tu restaurante.
        </div>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
