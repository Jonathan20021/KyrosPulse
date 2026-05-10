<?php
/** @var array $errors */
\App\Core\View::extend('layouts.auth');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>
<div>
    <div class="mb-8">
        <div class="inline-flex items-center gap-2 px-3 py-1 mb-4 rounded-full bg-indigo-500/10 border border-indigo-500/20">
            <span class="text-[11px] font-semibold text-indigo-300 tracking-wider uppercase">Verificacion en 2 pasos</span>
        </div>
        <h1 class="text-3xl font-black text-white mb-2 tracking-tight">Codigo de tu app</h1>
        <p class="text-slate-400 text-sm">Abre tu app autenticadora (Google Authenticator, Authy, 1Password) y escribe el codigo de 6 digitos. Si no tienes acceso, usa un recovery code.</p>
    </div>

    <?php \App\Core\View::include('components.flash'); ?>

    <form action="<?= url('/login/2fa') ?>" method="POST" class="space-y-4">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5 tracking-wider uppercase">Codigo o recovery code</label>
            <input type="text"
                   name="code"
                   inputmode="numeric"
                   autocomplete="one-time-code"
                   autofocus
                   maxlength="9"
                   placeholder="000000  o  XXXX-XXXX"
                   class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white text-lg font-mono tracking-widest text-center focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/40">
            <?php if (!empty($errors['code'])): ?>
            <p class="mt-1.5 text-xs text-rose-300"><?= e((string) $errors['code'][0]) ?></p>
            <?php endif; ?>
        </div>

        <button type="submit" class="w-full px-4 py-3 rounded-xl text-white font-semibold shadow-lg"
                style="background: linear-gradient(135deg,#6366F1,#06B6D4);">
            Verificar y entrar
        </button>

        <p class="text-center">
            <a href="<?= url('/login') ?>" class="text-xs text-slate-400 hover:text-slate-200">← Volver al login</a>
        </p>
    </form>
</div>
<?php \App\Core\View::end(); ?>
