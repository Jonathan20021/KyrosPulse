<?php
\App\Core\View::extend('layouts.auth');
\App\Core\View::start('content');
?>
<div class="text-center">
    <div class="relative w-20 h-20 mx-auto mb-6">
        <div class="absolute inset-0 rounded-2xl blur-xl opacity-50" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);"></div>
        <div class="relative w-20 h-20 rounded-2xl flex items-center justify-center" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
    </div>

    <h1 class="text-3xl font-black text-white mb-2 tracking-tight">Verifica tu correo</h1>
    <p class="text-slate-400 text-sm mb-7 max-w-sm mx-auto">Te enviamos un enlace de verificacion. Revisa tu bandeja para activar tu cuenta y empezar a operar.</p>

    <?php \App\Core\View::include('components.flash'); ?>

    <form action="<?= url('/email/verify-resend') ?>" method="POST" class="mb-3">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-lg w-full justify-center">
            Reenviar correo de verificacion
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        </button>
    </form>

    <a href="<?= url('/dashboard') ?>" class="block text-center text-sm text-cyan-400 hover:text-cyan-300 mb-6">Continuar al dashboard →</a>

    <div class="p-4 rounded-2xl border border-white/5 bg-white/[0.02] text-left">
        <div class="text-[10px] uppercase font-bold tracking-wider text-slate-500 mb-2">Mientras tanto</div>
        <ul class="space-y-2 text-xs text-slate-400">
            <li class="flex items-start gap-2">
                <div class="w-5 h-5 rounded flex items-center justify-center flex-shrink-0" style="background:#10B98122; color:#10B981;">1</div>
                <span>Conecta tu primer numero de WhatsApp en Configuracion → Canales</span>
            </li>
            <li class="flex items-start gap-2">
                <div class="w-5 h-5 rounded flex items-center justify-center flex-shrink-0" style="background:#06B6D422; color:#06B6D4;">2</div>
                <span>Carga tu catalogo y FAQ para alimentar a la IA</span>
            </li>
            <li class="flex items-start gap-2">
                <div class="w-5 h-5 rounded flex items-center justify-center flex-shrink-0" style="background:#7C3AED22; color:#7C3AED;">3</div>
                <span>Crea tu primer agente IA (vendedor o soporte)</span>
            </li>
        </ul>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
