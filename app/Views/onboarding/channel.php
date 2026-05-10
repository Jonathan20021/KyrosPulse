<?php
\App\Core\View::extend('layouts.onboarding');
\App\Core\View::start('content');
?>
<div class="text-center mb-8">
    <div class="text-4xl mb-3">💬</div>
    <h1 class="text-2xl sm:text-3xl font-black text-white mb-2 tracking-tight">Conecta WhatsApp</h1>
    <p class="text-slate-400">Para recibir mensajes y que la IA responda en automatico. Puedes hacerlo despues.</p>
</div>

<form action="<?= url('/onboarding/channel') ?>" method="POST" class="surface p-6 space-y-4">
    <?= csrf_field() ?>

    <div class="grid sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5 tracking-wider uppercase">Tu numero de WhatsApp</label>
            <input name="wasapi_phone" placeholder="+1 809 555 0123"
                   class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/40">
            <p class="text-[10px] text-slate-500 mt-1">Numero que tus clientes ya conocen.</p>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5 tracking-wider uppercase">API key de Wasapi (opcional)</label>
            <input name="wasapi_api_key" type="password" placeholder="..."
                   class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/40">
            <p class="text-[10px] text-slate-500 mt-1">La conseguis en wasapi.io o conectas Cloud API despues.</p>
        </div>
    </div>

    <div class="p-3 rounded-xl text-xs" style="background: rgba(59,130,246,.06); border: 1px solid rgba(59,130,246,.2); color: #93C5FD;">
        💡 <strong>Sin API key tambien funciona:</strong> puedes empezar usando el menu publico web y agentes IA, y conectar WhatsApp despues desde Configuracion → Canales.
    </div>

    <div class="flex items-center justify-between pt-2 gap-3 flex-wrap">
        <button type="submit" name="skip" value="1" class="text-sm text-slate-400 hover:text-white">Saltar este paso →</button>
        <button class="px-6 py-3 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">
            Continuar →
        </button>
    </div>
</form>
<?php \App\Core\View::end(); ?>
