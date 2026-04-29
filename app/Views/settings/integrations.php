<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$provider     = (string) ($tenant['ai_provider'] ?? 'claude');
$claudeModel  = (string) ($tenant['claude_model'] ?? 'claude-sonnet-6');
$openaiModel  = (string) ($tenant['openai_model'] ?? 'gpt-5-mini');
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'Integraciones', 'subtitle' => 'Conecta WhatsApp, email e IA.']); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'integrations']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;">
    <?= e((string) $flash) ?>
</div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#FB7185;">
    <?= e((string) $flashErr) ?>
</div>
<?php endif; ?>

<form id="integrationsForm" action="<?= url('/settings/integrations') ?>" method="POST" class="space-y-4">
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

    <!-- Proveedor de IA principal -->
    <div class="glass rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, rgba(124,58,237,.2), rgba(6,182,212,.2));">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#A78BFA;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            </div>
            <div>
                <h3 class="font-bold dark:text-white text-slate-900">Proveedor de IA</h3>
                <p class="text-xs dark:text-slate-400 text-slate-500">Selecciona que IA contestara automaticamente y dara sugerencias.</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <label class="flex-1 min-w-[180px] cursor-pointer rounded-xl border-2 p-3 transition" style="<?= $provider === 'claude' ? 'border-color:#7C3AED; background: rgba(124,58,237,.08);' : 'border-color: var(--color-border-subtle, rgba(148,163,184,.2));' ?>">
                <input type="radio" name="ai_provider" value="claude" class="hidden" <?= $provider === 'claude' ? 'checked' : '' ?>>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-xl">🟣</span>
                    <span class="font-semibold dark:text-white text-slate-900">Claude (Anthropic)</span>
                </div>
                <p class="text-xs dark:text-slate-400 text-slate-500">Sonnet, Opus, Haiku — gran calidad y razonamiento.</p>
            </label>
            <label class="flex-1 min-w-[180px] cursor-pointer rounded-xl border-2 p-3 transition" style="<?= $provider === 'openai' ? 'border-color:#06B6D4; background: rgba(6,182,212,.08);' : 'border-color: var(--color-border-subtle, rgba(148,163,184,.2));' ?>">
                <input type="radio" name="ai_provider" value="openai" class="hidden" <?= $provider === 'openai' ? 'checked' : '' ?>>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-xl">🟢</span>
                    <span class="font-semibold dark:text-white text-slate-900">OpenAI (GPT)</span>
                </div>
                <p class="text-xs dark:text-slate-400 text-slate-500">GPT-4o, GPT-5, mini — rapido y muy economico.</p>
            </label>
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
                <input type="password" name="claude_api_key" value="<?= e((string) ($tenant['claude_api_key'] ?? '')) ?>" placeholder="sk-ant-..."
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 font-mono text-sm">
                <p class="text-[11px] dark:text-slate-500 text-slate-400 mt-1">Obten tu key en console.anthropic.com</p>
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Modelo</label>
                <select name="claude_model" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                    <?php
                    $claudeOptions = [
                        'claude-sonnet-6'           => 'Claude Sonnet 6 (recomendado)',
                        'claude-opus-4-7'           => 'Claude Opus 4.7 (mayor calidad)',
                        'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (rapido)',
                    ];
                    foreach ($claudeOptions as $val => $label):
                    ?>
                    <option value="<?= e($val) ?>" <?= $claudeModel === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- OpenAI -->
    <div class="glass rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                <svg class="w-6 h-6 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M22.282 9.821a5.985 5.985 0 0 0-.516-4.91 6.046 6.046 0 0 0-6.51-2.9A6.065 6.065 0 0 0 4.981 4.18a5.985 5.985 0 0 0-3.998 2.9 6.046 6.046 0 0 0 .743 7.097 5.98 5.98 0 0 0 .51 4.911 6.051 6.051 0 0 0 6.515 2.9A5.985 5.985 0 0 0 13.26 24a6.056 6.056 0 0 0 5.772-4.206 5.99 5.99 0 0 0 3.997-2.9 6.056 6.056 0 0 0-.747-7.073zM13.26 22.43a4.476 4.476 0 0 1-2.876-1.04l.141-.081 4.779-2.758a.795.795 0 0 0 .392-.681v-6.737l2.02 1.168a.071.071 0 0 1 .038.052v5.583a4.504 4.504 0 0 1-4.494 4.494zM3.6 18.304a4.47 4.47 0 0 1-.535-3.014l.142.085 4.783 2.759a.771.771 0 0 0 .78 0l5.843-3.369v2.332a.08.08 0 0 1-.033.062L9.74 19.95a4.5 4.5 0 0 1-6.14-1.646zM2.34 7.896a4.485 4.485 0 0 1 2.366-1.973V11.6a.766.766 0 0 0 .388.676l5.815 3.355-2.02 1.168a.076.076 0 0 1-.071 0l-4.83-2.786A4.504 4.504 0 0 1 2.34 7.872zm16.597 3.855l-5.833-3.387L15.119 7.2a.076.076 0 0 1 .071 0l4.83 2.791a4.494 4.494 0 0 1-.676 8.105v-5.678a.79.79 0 0 0-.407-.667zm2.01-3.023l-.142-.085-4.774-2.782a.776.776 0 0 0-.785 0L9.409 9.23V6.897a.066.066 0 0 1 .028-.061l4.83-2.787a4.5 4.5 0 0 1 6.68 4.66zm-12.64 4.135l-2.02-1.164a.08.08 0 0 1-.038-.057V6.075a4.5 4.5 0 0 1 7.375-3.453l-.142.08L8.704 5.46a.795.795 0 0 0-.393.681zm1.097-2.365l2.602-1.5 2.607 1.5v2.999l-2.597 1.5-2.607-1.5z"/></svg>
            </div>
            <div>
                <h3 class="font-bold dark:text-white text-slate-900">IA OpenAI (GPT)</h3>
                <p class="text-xs dark:text-slate-400 text-slate-500">Alternativa o respaldo. Mas rapido y barato en consultas masivas.</p>
            </div>
        </div>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">API Key OpenAI</label>
                <input type="password" name="openai_api_key" value="<?= e((string) ($tenant['openai_api_key'] ?? '')) ?>" placeholder="sk-..."
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 font-mono text-sm">
                <p class="text-[11px] dark:text-slate-500 text-slate-400 mt-1">Obten tu key en platform.openai.com</p>
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Modelo</label>
                <select name="openai_model" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                    <?php
                    $openaiOptions = [
                        'gpt-5-mini'  => 'GPT-5 Mini (recomendado)',
                        'gpt-5'       => 'GPT-5 (premium)',
                        'gpt-4o'      => 'GPT-4o',
                        'gpt-4o-mini' => 'GPT-4o Mini (economico)',
                        'gpt-4-turbo' => 'GPT-4 Turbo',
                    ];
                    foreach ($openaiOptions as $val => $label):
                    ?>
                    <option value="<?= e($val) ?>" <?= $openaiModel === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 sticky bottom-3 z-10">
        <button type="button" id="btnTestAi" class="px-4 py-2 rounded-xl text-sm font-semibold glass dark:text-white text-slate-900">
            Probar conexion IA
        </button>
        <div class="flex items-center gap-2">
            <span id="aiTestResult" class="text-xs"></span>
            <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold shadow-lg" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">
                Guardar integraciones
            </button>
        </div>
    </div>
</form>

<!-- Form independiente para sincronizar plantillas (no anidado) -->
<form action="<?= url('/settings/integrations/wasapi/templates/sync') ?>" method="POST" class="mt-3">
    <?= csrf_field() ?>
    <div class="glass rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="font-semibold dark:text-white text-slate-900 text-sm">Plantillas de WhatsApp</div>
            <div class="text-xs dark:text-slate-400 text-slate-500">Trae las plantillas aprobadas de Wasapi para campanas fuera de ventana.</div>
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg glass text-xs font-semibold dark:text-white text-slate-900">Sincronizar plantillas</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="ai_provider"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('input[name="ai_provider"]').forEach(r => {
                const card = r.closest('label');
                if (!card) return;
                if (r.checked) {
                    card.style.borderColor = r.value === 'claude' ? '#7C3AED' : '#06B6D4';
                    card.style.background  = r.value === 'claude' ? 'rgba(124,58,237,.08)' : 'rgba(6,182,212,.08)';
                } else {
                    card.style.borderColor = 'rgba(148,163,184,.2)';
                    card.style.background  = '';
                }
            });
        });
    });

    const btn = document.getElementById('btnTestAi');
    const out = document.getElementById('aiTestResult');
    if (btn) {
        btn.addEventListener('click', async () => {
            out.textContent = 'Probando...';
            out.style.color = '';
            const fd = new FormData(document.getElementById('integrationsForm'));
            try {
                const res = await fetch('<?= url('/settings/integrations/test-ai') ?>', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': '<?= csrf_token() ?>', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json();
                if (data.success) {
                    out.textContent = '✓ ' + (data.message || 'Conexion OK');
                    out.style.color = '#34D399';
                } else {
                    out.textContent = '✗ ' + (data.error || 'Fallo');
                    out.style.color = '#FB7185';
                }
            } catch (e) {
                out.textContent = '✗ ' + e.message;
                out.style.color = '#FB7185';
            }
        });
    }
});
</script>

<?php \App\Core\View::stop(); ?>
