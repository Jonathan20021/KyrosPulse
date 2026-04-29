<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'Integraciones', 'subtitle' => 'Conecta WhatsApp, email e IA.']); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'integrations']); ?>

<form action="<?= url('/settings/integrations') ?>" method="POST" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <!-- Wasapi -->
    <div class="glass rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                <svg class="w-6 h-6 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
            </div>
            <div>
                <h3 class="font-bold dark:text-white text-slate-900">WhatsApp via Wasapi</h3>
                <p class="text-xs dark:text-slate-400 text-slate-500">Conecta tu cuenta para enviar y recibir mensajes.</p>
            </div>
        </div>
        <div class="grid md:grid-cols-2 gap-3">
            <div class="md:col-span-2">
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">API Key Wasapi</label>
                <input type="password" name="wasapi_api_key" value="<?= e((string) ($tenant['wasapi_api_key'] ?? '')) ?>"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 font-mono text-sm">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Numero WhatsApp</label>
                <input type="text" name="wasapi_phone" value="<?= e((string) ($tenant['wasapi_phone'] ?? '')) ?>" placeholder="+18091234567"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
        </div>
        <div class="mt-3 p-3 rounded-xl dark:bg-white/5 bg-slate-50 border dark:border-white/5 border-slate-200">
            <div class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500 mb-1">Webhook URL para Wasapi</div>
            <div class="flex items-center gap-2">
                <code class="flex-1 dark:text-cyan-300 text-cyan-700 text-sm font-mono break-all"><?= e($webhookUrl) ?></code>
                <button type="button" onclick="navigator.clipboard.writeText('<?= e($webhookUrl) ?>')" class="px-2 py-1 rounded glass text-xs">Copiar</button>
            </div>
            <p class="text-xs dark:text-slate-400 text-slate-500 mt-2">Configura este URL en tu cuenta Wasapi como webhook de eventos.</p>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <form action="<?= url('/settings/integrations/wasapi/templates/sync') ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="px-3 py-2 rounded-lg glass text-xs dark:text-white text-slate-900">Sincronizar plantillas</button>
            </form>
            <span class="text-xs dark:text-slate-400 text-slate-500">Trae las plantillas aprobadas de Wasapi para campanas fuera de ventana.</span>
        </div>
    </div>

    <!-- Resend -->
    <div class="glass rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/20 flex items-center justify-center">
                <svg class="w-6 h-6 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <h3 class="font-bold dark:text-white text-slate-900">Email via Resend</h3>
                <p class="text-xs dark:text-slate-400 text-slate-500">Para verificacion, recuperacion, reportes y campanas email.</p>
            </div>
        </div>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">API Key Resend</label>
                <input type="password" name="resend_api_key" value="<?= e((string) ($tenant['resend_api_key'] ?? '')) ?>"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 font-mono text-sm">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Email remitente</label>
                <input type="text" name="resend_from_email" value="<?= e((string) ($tenant['resend_from_email'] ?? '')) ?>" placeholder="Mi Empresa &lt;no-reply@miempresa.com&gt;"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
        </div>
    </div>

    <!-- Claude -->
    <div class="glass rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-violet-500/20 flex items-center justify-center">
                <svg class="w-6 h-6 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div>
                <h3 class="font-bold dark:text-white text-slate-900">IA Claude (Anthropic)</h3>
                <p class="text-xs dark:text-slate-400 text-slate-500">Para sugerencias, scoring de leads, resumenes y respuestas automaticas.</p>
            </div>
        </div>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">API Key Claude</label>
                <input type="password" name="claude_api_key" value="<?= e((string) ($tenant['claude_api_key'] ?? '')) ?>"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 font-mono text-sm">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Modelo</label>
                <select name="claude_model" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                    <option value="claude-sonnet-6"   <?= ($tenant['claude_model'] ?? 'claude-sonnet-6') === 'claude-sonnet-6'   ? 'selected' : '' ?>>Claude Sonnet 6</option>
                    <option value="claude-opus-4-7"   <?= ($tenant['claude_model'] ?? '') === 'claude-opus-4-7'   ? 'selected' : '' ?>>Claude Opus 4.7</option>
                    <option value="claude-haiku-4-5-20251001" <?= ($tenant['claude_model'] ?? '') === 'claude-haiku-4-5-20251001' ? 'selected' : '' ?>>Claude Haiku 4.5</option>
                </select>
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar integraciones</button>
    </div>
</form>
<?php \App\Core\View::stop(); ?>
