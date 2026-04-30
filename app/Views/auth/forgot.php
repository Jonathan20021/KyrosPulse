<?php
\App\Core\View::extend('layouts.auth');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>
<div>
    <div class="mb-7">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-5" style="background: linear-gradient(135deg, rgba(124,58,237,.15), rgba(6,182,212,.15)); border: 1px solid rgba(124,58,237,.3);">
            <svg class="w-7 h-7 text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
        </div>
        <h1 class="text-3xl font-black text-white mb-2 tracking-tight">Recupera tu cuenta</h1>
        <p class="text-slate-400 text-sm">Ingresa tu email y te enviaremos un enlace seguro para restablecer tu contrasena.</p>
    </div>

    <?php \App\Core\View::include('components.flash'); ?>

    <form action="<?= url('/forgot-password') ?>" method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="label">Correo electronico</label>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <input type="email" name="email" required autofocus class="input pl-10" placeholder="tu@empresa.com">
            </div>
            <?php if ($e = error_for('email', $errors)): ?><p class="mt-1.5 text-xs text-rose-400"><?= e($e) ?></p><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-full justify-center">
            Enviar instrucciones
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </button>
    </form>

    <div class="mt-6 p-4 rounded-2xl border border-white/5 bg-white/[0.02] flex items-start gap-3">
        <svg class="w-4 h-4 text-cyan-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <p class="text-xs text-slate-400 leading-relaxed">El enlace expira en 60 minutos. Si no recibes el correo, revisa tu carpeta de spam o contacta al admin de tu workspace.</p>
    </div>

    <div class="mt-6 text-center text-sm">
        <a href="<?= url('/login') ?>" class="text-cyan-400 hover:text-cyan-300 font-semibold inline-flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Volver al login
        </a>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
