<?php
\App\Core\View::extend('layouts.auth');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>
<div>
    <div class="mb-8">
        <div class="w-12 h-12 rounded-2xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
        </div>
        <h1 class="heading-md text-white mb-2">Recupera tu cuenta</h1>
        <p class="text-slate-400 text-sm">Ingresa tu email y te enviaremos un enlace seguro para restablecer tu contrasena.</p>
    </div>

    <?php \App\Core\View::include('components.flash'); ?>

    <form action="<?= url('/forgot-password') ?>" method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="label">Correo electronico</label>
            <input type="email" name="email" required autofocus class="input" placeholder="tu@empresa.com">
            <?php if ($e = error_for('email', $errors)): ?><p class="mt-1.5 text-xs text-rose-400"><?= e($e) ?></p><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-full justify-center">
            Enviar instrucciones
        </button>
    </form>

    <div class="mt-6 text-center text-sm">
        <a href="<?= url('/login') ?>" class="text-cyan-400 hover:text-cyan-300 font-semibold">← Volver al login</a>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
