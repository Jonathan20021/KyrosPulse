<?php
/** @var array $channels */
/** @var array $stats */
/** @var array $providers */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Canales de WhatsApp',
    'subtitle' => 'Gestiona varios numeros desde una sola bandeja unificada. Soporta Wasapi, Cloud API (Meta), Twilio y mas.',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'channels']); ?>

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

<!-- Resumen rapido -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php
    $totalChannels = count($channels);
    $totalActive   = count(array_filter($channels, fn ($c) => $c['status'] === 'active'));
    $totalCloud    = count(array_filter($channels, fn ($c) => $c['provider'] === 'cloud'));
    $totalConvs    = array_sum(array_column($stats, 'open_convs'));
    $kpis = [
        ['🛰', 'Numeros conectados',  $totalChannels, '#7C3AED'],
        ['✅', 'Numeros activos',     $totalActive,   '#10B981'],
        ['☁',  'Cloud API (Meta)',    $totalCloud,    '#06B6D4'],
        ['💬', 'Conversaciones abiertas', $totalConvs, '#F59E0B'],
    ];
    foreach ($kpis as [$emoji, $label, $value, $color]): ?>
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);"><?= e($label) ?></span>
            <span class="text-xl" style="filter: drop-shadow(0 0 8px <?= $color ?>55);"><?= $emoji ?></span>
        </div>
        <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= number_format($value) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- CTA: nuevo canal -->
<div x-data="{ open: false, provider: 'wasapi' }" class="mb-5">
    <button @click="open = !open" type="button" class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2"
            style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Conectar nuevo numero
    </button>

    <div x-show="open" x-cloak x-transition class="surface mt-4 p-5">
        <h3 class="font-bold text-base mb-1" style="color: var(--color-text-primary);">Conectar canal de WhatsApp</h3>
        <p class="text-xs mb-4" style="color: var(--color-text-tertiary);">Elige el proveedor. Soportamos multiples numeros en simultaneo y los unificamos en una sola bandeja.</p>

        <form action="<?= url('/settings/channels') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <!-- Provider tabs -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <?php foreach ($providers as $key => [$name, $color]): ?>
                <label class="cursor-pointer rounded-xl border-2 p-3 transition" :class="provider === '<?= $key ?>' ? '' : 'opacity-60'" :style="provider === '<?= $key ?>' ? 'border-color:<?= $color ?>; background: <?= $color ?>14' : 'border-color: var(--color-border-subtle);'">
                    <input type="radio" name="provider" value="<?= $key ?>" x-model="provider" class="hidden">
                    <div class="text-sm font-bold" style="color: var(--color-text-primary);"><?= e($name) ?></div>
                    <div class="text-[10px] mt-1" style="color: var(--color-text-tertiary);">
                        <?= match($key) {
                            'wasapi' => 'API key + numero. Listo en 1 minuto.',
                            'cloud'  => 'Oficial de Meta. WABA + Phone Number ID.',
                            'twilio' => 'Account SID + Auth Token.',
                            'dialog360' => 'Partner BSP global.',
                            default => 'HTTP custom: usa tu propio backend.',
                        } ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="label">Etiqueta interna</label>
                    <input type="text" name="label" required maxlength="120" placeholder="Ventas Republica Dominicana" class="input">
                </div>
                <div>
                    <label class="label">Numero (E.164)</label>
                    <input type="text" name="phone" required placeholder="+18091234567" class="input">
                </div>
            </div>

            <!-- Wasapi fields -->
            <div x-show="provider === 'wasapi'" x-transition class="grid md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="label">API Key Wasapi</label>
                    <input type="password" name="api_key" placeholder="wasapi_..." class="input font-mono text-sm">
                </div>
            </div>

            <!-- Cloud API fields -->
            <div x-show="provider === 'cloud'" x-transition class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="label">Phone Number ID</label>
                    <input type="text" name="phone_number_id" placeholder="123456789012345" class="input font-mono text-sm">
                </div>
                <div>
                    <label class="label">WABA ID</label>
                    <input type="text" name="business_account_id" placeholder="987654321012345" class="input font-mono text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="label">System User Token</label>
                    <input type="password" name="access_token" placeholder="EAAB..." class="input font-mono text-sm">
                </div>
                <div>
                    <label class="label">Webhook Verify Token</label>
                    <input type="text" name="webhook_verify" placeholder="cualquier-string" class="input">
                </div>
                <div>
                    <label class="label">Webhook Secret (App secret)</label>
                    <input type="password" name="webhook_secret" placeholder="X-Hub-Signature-256" class="input font-mono text-sm">
                </div>
            </div>

            <!-- Twilio fields -->
            <div x-show="provider === 'twilio'" x-transition class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="label">Account SID</label>
                    <input type="text" name="api_key" placeholder="ACxxxxxxx" class="input font-mono text-sm">
                </div>
                <div>
                    <label class="label">Auth Token</label>
                    <input type="password" name="api_secret" placeholder="••••••" class="input font-mono text-sm">
                </div>
            </div>

            <!-- Dialog360 fields -->
            <div x-show="provider === 'dialog360'" x-transition class="grid md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="label">D360 API Key</label>
                    <input type="password" name="api_key" class="input font-mono text-sm">
                </div>
            </div>

            <!-- Custom fields -->
            <div x-show="provider === 'custom'" x-transition class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="label">Auth (header)</label>
                    <input type="password" name="api_key" class="input font-mono text-sm">
                </div>
                <div>
                    <label class="label">Webhook secret</label>
                    <input type="password" name="webhook_secret" class="input font-mono text-sm">
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="label">Color (UI)</label>
                    <input type="color" name="color" value="#7C3AED" class="input h-[42px] p-1">
                </div>
                <label class="flex items-end gap-2 cursor-pointer">
                    <input type="checkbox" name="is_default" value="1" class="w-4 h-4 mb-3">
                    <span class="text-sm mb-3" style="color: var(--color-text-secondary);">Marcar como canal predeterminado</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="px-4 py-2 rounded-xl text-sm" style="color: var(--color-text-secondary);">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">Conectar canal</button>
            </div>
        </form>
    </div>
</div>

<!-- Lista de canales -->
<?php if (empty($channels)): ?>
<div class="surface p-10 text-center">
    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center text-3xl" style="background: var(--gradient-mesh);">📡</div>
    <h3 class="font-bold text-lg mb-1" style="color: var(--color-text-primary);">Aun no tienes canales</h3>
    <p class="text-sm mb-4" style="color: var(--color-text-tertiary);">Conecta tu primer numero de WhatsApp para empezar a recibir mensajes.</p>
</div>
<?php else: ?>
<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($channels as $ch):
        [$providerName, $providerColor] = $providers[$ch['provider']] ?? [$ch['provider'], '#7C3AED'];
        $health = strtolower((string) ($ch['quality_rating'] ?? 'unknown'));
        $healthColor = match ($health) { 'green' => '#10B981', 'yellow' => '#F59E0B', 'red' => '#F43F5E', default => '#94A3B8' };
        $isActive = $ch['status'] === 'active';
        $stat = $stats[$ch['id']] ?? ['msgs_24h' => 0, 'open_convs' => 0];
        $webhookUrl = $ch['provider'] === 'cloud'
            ? url('/webhooks/cloud/' . $ch['uuid'])
            : url('/webhooks/wasapi/' . (\App\Core\Database::fetchColumn("SELECT uuid FROM tenants WHERE id = :id", ['id' => (int) $ch['tenant_id']]) ?? ''));
    ?>
    <div class="surface p-5 relative overflow-hidden" x-data="{ editing: false }">
        <?php if (!empty($ch['is_default'])): ?>
        <div class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[9px] uppercase font-bold tracking-wider" style="background: linear-gradient(135deg,#7C3AED,#06B6D4); color: white;">Default</div>
        <?php endif; ?>

        <div class="flex items-start gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl text-white shadow-lg" style="background: <?= e((string) ($ch['color'] ?? $providerColor)) ?>;">
                <?php if ($ch['provider'] === 'cloud'): ?>
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M17.7 17.5h-2.5l-2.4-3.7-2.5 3.7H7.7L11 13l-3.2-4.5h2.6L12.7 12l2.3-3.5h2.5L14.4 13l3.3 4.5z"/></svg>
                <?php else: ?>
                📱
                <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                    <h3 class="font-bold text-sm truncate" style="color: var(--color-text-primary);"><?= e($ch['label']) ?></h3>
                    <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: <?= $providerColor ?>22; color: <?= $providerColor ?>;"><?= e($providerName) ?></span>
                </div>
                <div class="text-xs font-mono" style="color: var(--color-text-tertiary);"><?= e((string) $ch['phone']) ?></div>
                <div class="flex items-center gap-2 mt-1">
                    <span class="flex items-center gap-1 text-[10px]" style="color: <?= $isActive ? '#34D399' : '#F87171' ?>;">
                        <span class="w-1.5 h-1.5 rounded-full" style="background: <?= $isActive ? '#10B981' : '#EF4444' ?>;"></span>
                        <?= $isActive ? 'Activo' : ucfirst((string) $ch['status']) ?>
                    </span>
                    <?php if ($health !== 'unknown'): ?>
                    <span class="text-[10px] flex items-center gap-1" style="color: <?= $healthColor ?>;">
                        <span class="w-1.5 h-1.5 rounded-full" style="background: <?= $healthColor ?>;"></span>
                        Calidad <?= e($health) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 mb-3">
            <div class="rounded-lg p-2" style="background: var(--color-bg-subtle);">
                <div class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Conv abiertas</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= number_format($stat['open_convs']) ?></div>
            </div>
            <div class="rounded-lg p-2" style="background: var(--color-bg-subtle);">
                <div class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Mensajes 24h</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= number_format($stat['msgs_24h']) ?></div>
            </div>
        </div>

        <details class="text-xs mb-3">
            <summary class="cursor-pointer font-semibold" style="color: var(--color-primary);">Webhook URL</summary>
            <div class="mt-2 flex items-center gap-2">
                <code class="flex-1 text-[10px] font-mono break-all rounded p-2 leading-snug" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);"><?= e($webhookUrl) ?></code>
                <button type="button" onclick="navigator.clipboard.writeText('<?= e($webhookUrl) ?>')" class="px-2 py-1 rounded glass text-[10px]">Copiar</button>
            </div>
            <?php if ($ch['provider'] === 'cloud'): ?>
            <p class="mt-2 text-[10px]" style="color: var(--color-text-tertiary);">Configura este URL como webhook en Meta Business Manager. Verify token: <code class="font-mono" style="color:#06B6D4;"><?= e((string) ($ch['webhook_verify'] ?? '(definelo arriba)')) ?></code></p>
            <?php endif; ?>
        </details>

        <div class="flex items-center gap-1.5 flex-wrap">
            <button type="button" onclick="testChannel(<?= (int) $ch['id'] ?>, this)" class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Test
            </button>
            <?php if (empty($ch['is_default'])): ?>
            <form action="<?= url('/settings/channels/' . $ch['id'] . '/default') ?>" method="POST" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs" style="color: var(--color-text-secondary);">Default</button>
            </form>
            <?php endif; ?>
            <form action="<?= url('/settings/channels/' . $ch['id'] . '/toggle') ?>" method="POST" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs" style="color: var(--color-text-secondary);"><?= $isActive ? 'Pausar' : 'Activar' ?></button>
            </form>
            <form action="<?= url('/settings/channels/' . $ch['id']) ?>" method="POST" class="inline ml-auto" onsubmit="return confirm('Eliminar canal y todo su historial?')">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs" style="color:#F87171;">Eliminar</button>
            </form>
        </div>

        <div id="testResult-<?= (int) $ch['id'] ?>" class="mt-2 text-xs"></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
async function testChannel(id, btn) {
    const out = document.getElementById('testResult-' + id);
    out.textContent = 'Probando...';
    out.style.color = '';
    try {
        const res = await fetch('<?= url('/settings/channels/') ?>' + id + '/test', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= csrf_token() ?>',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });
        const data = await res.json();
        if (data.success) {
            out.textContent = '✓ Conectado correctamente';
            out.style.color = '#34D399';
        } else {
            out.textContent = '✗ ' + (data.error || 'Falla');
            out.style.color = '#F87171';
        }
    } catch (e) {
        out.textContent = '✗ ' + e.message;
        out.style.color = '#F87171';
    }
}
</script>

<?php \App\Core\View::stop(); ?>
