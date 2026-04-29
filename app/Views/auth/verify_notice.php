<?php
\App\Core\View::extend('layouts.auth');
\App\Core\View::start('content');
?>
<div class="w-full max-w-md">
    <div class="glass rounded-3xl p-8 shadow-2xl text-center">
        <div class="w-20 h-20 mx-auto rounded-2xl bg-gradient-to-br from-primary to-cyan flex items-center justify-center mb-6" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">
            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
        <h1 class="text-2xl font-extrabold text-white mb-2">Verifica tu correo</h1>
        <p class="text-slate-400 mb-6">Te hemos enviado un correo con un enlace de verificacion. Si no lo recibiste, puedes solicitar uno nuevo.</p>

        <?php \App\Core\View::include('components.flash'); ?>

        <form action="<?= url('/email/verify-resend') ?>" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="btn-primary w-full text-white py-3 rounded-xl font-semibold mb-3" style="background: linear-gradient(135deg,#7C3AED,#06B6D4)">
                Reenviar correo
            </button>
        </form>

        <a href="<?= url('/dashboard') ?>" class="text-sm text-cyan-400 hover:text-cyan-300">Continuar al dashboard</a>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
