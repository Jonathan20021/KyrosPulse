<?php
/** @var array $errors */
\App\Core\View::extend('layouts.auth');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>
<div>
    <div class="mb-8">
        <h1 class="heading-md text-white mb-2">Bienvenido de vuelta 👋</h1>
        <p class="text-slate-400 text-sm">Accede a tu panel para continuar.</p>
    </div>

    <?php \App\Core\View::include('components.flash'); ?>

    <form action="<?= url('/login') ?>" method="POST" class="space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="label">Correo electronico</label>
            <input type="email" name="email" required value="<?= old('email') ?>" autofocus
                   class="input"
                   placeholder="tu@empresa.com">
            <?php if ($e = error_for('email', $errors)): ?>
            <p class="mt-1.5 text-xs text-rose-400 flex items-center gap-1"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg><?= e($e) ?></p>
            <?php endif; ?>
        </div>

        <div x-data="{ show: false }">
            <div class="flex items-center justify-between mb-1.5">
                <label class="label !mb-0">Contrasena</label>
                <a href="<?= url('/forgot-password') ?>" class="text-xs text-cyan-400 hover:text-cyan-300 font-medium">Olvidaste tu contrasena?</a>
            </div>
            <div class="relative">
                <input :type="show ? 'text' : 'password'" name="password" required
                       class="input pr-11"
                       placeholder="••••••••">
                <button type="button" @click="show = !show" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-white/5">
                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                </button>
            </div>
            <?php if ($e = error_for('password', $errors)): ?>
            <p class="mt-1.5 text-xs text-rose-400"><?= e($e) ?></p>
            <?php endif; ?>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
            <input type="checkbox" name="remember" value="1" class="w-4 h-4 rounded border-white/10 bg-white/5 text-violet-500">
            <span>Mantener sesion iniciada</span>
        </label>

        <button type="submit" class="btn btn-primary btn-lg w-full justify-center">
            Iniciar sesion
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </button>
    </form>

    <div class="my-8 flex items-center gap-3">
        <div class="flex-1 h-px bg-white/10"></div>
        <span class="text-xs uppercase tracking-wider text-slate-500">o</span>
        <div class="flex-1 h-px bg-white/10"></div>
    </div>

    <div class="text-center text-sm text-slate-400">
        No tienes cuenta?
        <a href="<?= url('/register') ?>" class="text-white font-semibold hover:text-violet-400 transition">Crear cuenta gratis</a>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
