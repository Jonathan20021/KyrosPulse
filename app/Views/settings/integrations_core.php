<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$provider     = (string) ($tenant['ai_provider'] ?? 'claude');
$claudeModel  = (string) ($tenant['claude_model'] ?? 'claude-sonnet-4-6');
$openaiModel  = (string) ($tenant['openai_model'] ?? 'gpt-4o-mini');

$claudeAliases = [
    'claude-sonnet-6' => 'claude-sonnet-4-6',
    'claude-opus-6'   => 'claude-opus-4-7',
    'claude-haiku-6'  => 'claude-haiku-4-5-20251001',
];
if (isset($claudeAliases[$claudeModel])) {
    $claudeModel = $claudeAliases[$claudeModel];
}
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'integrations']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Canales',
    'title'    => 'Integraciones core',
    'subtitle' => 'Conecta WhatsApp (Wasapi), email (Resend) e IA (Claude / OpenAI).',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<form id="integrationsForm" action="<?= url('/settings/integrations/core') ?>" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title">
                    <span style="display:inline-flex; width:32px; height:32px; border-radius:8px; background: rgba(16,185,129,.15); align-items:center; justify-content:center;">
                        <svg width="18" height="18" fill="#10B981" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
                    </span>
                    WhatsApp via Wasapi
                </h2>
                <p class="set-section-desc">Conecta tu cuenta para enviar y recibir mensajes.</p>
            </div>
        </div>
        <div class="set-field">
            <label class="set-label">API Key Wasapi</label>
            <input type="password" name="wasapi_api_key" value="<?= e((string) ($tenant['wasapi_api_key'] ?? '')) ?>" class="set-input set-mono">
        </div>
        <div class="set-field">
            <label class="set-label">Numero WhatsApp</label>
            <input type="text" name="wasapi_phone" value="<?= e((string) ($tenant['wasapi_phone'] ?? '')) ?>" placeholder="+18091234567" class="set-input">
        </div>
        <div class="set-field">
            <label class="set-label">Webhook URL para Wasapi</label>
            <div class="set-code-row">
                <code class="set-code"><?= e($webhookUrl) ?></code>
                <button type="button" onclick="navigator.clipboard.writeText('<?= e($webhookUrl) ?>')" class="set-btn set-btn-ghost set-btn-sm">Copiar</button>
            </div>
            <p class="set-help">Configura este URL en tu cuenta Wasapi como webhook de eventos.</p>
        </div>
    </section>

    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title">
                    <span style="display:inline-flex; width:32px; height:32px; border-radius:8px; background: rgba(6,182,212,.15); align-items:center; justify-content:center;">
                        <svg width="18" height="18" fill="none" stroke="#06B6D4" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </span>
                    Email via Resend
                </h2>
                <p class="set-section-desc">Para verificacion, recuperacion, reportes y campanas email.</p>
            </div>
        </div>
        <div class="set-field-row cols-2 set-field">
            <div>
                <label class="set-label">API Key Resend</label>
                <input type="password" name="resend_api_key" value="<?= e((string) ($tenant['resend_api_key'] ?? '')) ?>" class="set-input set-mono">
            </div>
            <div>
                <label class="set-label">Email remitente</label>
                <input type="text" name="resend_from_email" value="<?= e((string) ($tenant['resend_from_email'] ?? '')) ?>" placeholder="Mi Empresa &lt;no-reply@miempresa.com&gt;" class="set-input">
            </div>
        </div>
    </section>

    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>🤖</span> Proveedor de IA</h2>
                <p class="set-section-desc">Selecciona que IA contestara automaticamente y dara sugerencias.</p>
            </div>
        </div>
        <div class="set-provider-grid">
            <label class="set-provider-card <?= $provider === 'claude' ? 'is-active' : '' ?>" style="--prov-color: #10B981;">
                <input type="radio" name="ai_provider" value="claude" <?= $provider === 'claude' ? 'checked' : '' ?>>
                <div class="set-provider-head">
                    <span style="font-size: 22px;">🟣</span>
                    <span class="set-provider-name">Claude (Anthropic)</span>
                </div>
                <p class="set-provider-desc">Sonnet, Opus, Haiku — gran calidad y razonamiento.</p>
            </label>
            <label class="set-provider-card <?= $provider === 'openai' ? 'is-active' : '' ?>" style="--prov-color: #06B6D4;">
                <input type="radio" name="ai_provider" value="openai" <?= $provider === 'openai' ? 'checked' : '' ?>>
                <div class="set-provider-head">
                    <span style="font-size: 22px;">🟢</span>
                    <span class="set-provider-name">OpenAI (GPT)</span>
                </div>
                <p class="set-provider-desc">GPT-4o, GPT-5, mini — rapido y muy economico.</p>
            </label>
        </div>
    </section>

    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>⚡</span> IA Claude (Anthropic)</h2>
                <p class="set-section-desc">Para sugerencias, scoring de leads, resumenes y respuestas automaticas.</p>
            </div>
        </div>
        <div class="set-field-row cols-2 set-field">
            <div>
                <label class="set-label">API Key Claude</label>
                <input type="password" name="claude_api_key" value="<?= e((string) ($tenant['claude_api_key'] ?? '')) ?>" placeholder="sk-ant-..." class="set-input set-mono">
                <p class="set-help">Obten tu key en console.anthropic.com</p>
            </div>
            <div>
                <label class="set-label">Modelo</label>
                <select name="claude_model" class="set-select">
                    <?php
                    $claudeOptions = [
                        'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 (recomendado)',
                        'claude-opus-4-7'           => 'Claude Opus 4.7 (mayor calidad)',
                        'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (rapido y economico)',
                    ];
                    foreach ($claudeOptions as $val => $label):
                    ?>
                    <option value="<?= e($val) ?>" <?= $claudeModel === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>🟢</span> IA OpenAI (GPT)</h2>
                <p class="set-section-desc">Alternativa o respaldo. Mas rapido y barato en consultas masivas.</p>
            </div>
        </div>
        <div class="set-field-row cols-2 set-field">
            <div>
                <label class="set-label">API Key OpenAI</label>
                <input type="password" name="openai_api_key" value="<?= e((string) ($tenant['openai_api_key'] ?? '')) ?>" placeholder="sk-..." class="set-input set-mono">
                <p class="set-help">Obten tu key en platform.openai.com</p>
            </div>
            <div>
                <label class="set-label">Modelo</label>
                <select name="openai_model" class="set-select">
                    <?php
                    $openaiOptions = [
                        'gpt-4o-mini' => 'GPT-4o Mini (recomendado, rapido y barato)',
                        'gpt-4o'      => 'GPT-4o (mayor calidad)',
                        'gpt-4-turbo' => 'GPT-4 Turbo',
                        'gpt-5-mini'  => 'GPT-5 Mini (si tu cuenta tiene acceso)',
                        'gpt-5'       => 'GPT-5 (si tu cuenta tiene acceso)',
                    ];
                    foreach ($openaiOptions as $val => $label):
                    ?>
                    <option value="<?= e($val) ?>" <?= $openaiModel === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <div class="set-actions" style="justify-content: space-between;">
        <button type="button" id="btnTestAi" class="set-btn set-btn-ghost">Probar conexion IA</button>
        <div style="display:flex; align-items:center; gap:12px;">
            <span id="aiTestResult" style="font-size: 12.5px;"></span>
            <button type="submit" class="set-btn set-btn-primary">Guardar integraciones</button>
        </div>
    </div>
</form>

<div class="set-field-row cols-2 set-field" style="margin-top: 16px;">
    <form action="<?= url('/settings/integrations/wasapi/templates/sync') ?>" method="POST">
        <?= csrf_field() ?>
        <div class="set-section set-sync-card">
            <div>
                <div class="set-sync-title">Plantillas WhatsApp</div>
                <p class="set-sync-desc">Trae plantillas aprobadas para campanas fuera de ventana.</p>
            </div>
            <button type="submit" class="set-btn set-btn-ghost set-btn-sm">Sincronizar</button>
        </div>
    </form>
    <form action="<?= url('/settings/integrations/wasapi/contacts/sync') ?>" method="POST">
        <?= csrf_field() ?>
        <div class="set-section set-sync-card">
            <div>
                <div class="set-sync-title">Nombres de contactos</div>
                <p class="set-sync-desc">Reemplaza "Contacto WhatsApp" con el nombre real del perfil.</p>
            </div>
            <button type="submit" class="set-btn set-btn-ghost set-btn-sm">Sincronizar</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="ai_provider"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('input[name="ai_provider"]').forEach(r => {
                const card = r.closest('.set-provider-card');
                if (!card) return;
                card.classList.toggle('is-active', r.checked);
            });
        });
    });

    const btn = document.getElementById('btnTestAi');
    const out = document.getElementById('aiTestResult');
    if (btn) {
        btn.addEventListener('click', async () => {
            out.textContent = 'Probando...';
            out.style.color = '';
            const form = document.getElementById('integrationsForm');
            const provider = form.querySelector('input[name="ai_provider"]:checked')?.value || 'claude';
            const payload = {
                ai_provider:    provider,
                claude_api_key: form.querySelector('[name="claude_api_key"]')?.value || '',
                claude_model:   form.querySelector('[name="claude_model"]')?.value || '',
                openai_api_key: form.querySelector('[name="openai_api_key"]')?.value || '',
                openai_model:   form.querySelector('[name="openai_model"]')?.value || '',
            };
            try {
                const res = await fetch('<?= url('/settings/integrations/test-ai') ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type':    'application/json',
                        'Accept':          'application/json',
                        'X-CSRF-Token':    '<?= csrf_token() ?>',
                        'X-Requested-With':'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });
                let data;
                try { data = await res.json(); }
                catch (parseErr) {
                    out.textContent = '✗ Respuesta invalida (HTTP ' + res.status + ').';
                    out.style.color = '#F87171';
                    return;
                }
                if (data.success) {
                    out.textContent = '✓ ' + (data.message || 'Conexion OK');
                    out.style.color = '#10B981';
                } else {
                    out.textContent = '✗ ' + (data.error || 'Fallo');
                    out.style.color = '#F87171';
                }
            } catch (e) {
                out.textContent = '✗ No se pudo conectar (' + (e.message || 'fetch error') + ').';
                out.style.color = '#F87171';
            }
        });
    }
});
</script>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
