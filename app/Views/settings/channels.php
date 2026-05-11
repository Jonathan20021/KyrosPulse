<?php
/** @var array $channels */
/** @var array $stats */
/** @var array $providers */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'channels']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Canales',
    'title'    => 'Canales de WhatsApp',
    'subtitle' => 'Gestiona varios numeros desde una sola bandeja unificada. Soporta Wasapi, Cloud API (Meta), Twilio y mas.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php
$totalChannels = count($channels);
$totalActive   = count(array_filter($channels, fn ($c) => $c['status'] === 'active'));
$totalCloud    = count(array_filter($channels, fn ($c) => $c['provider'] === 'cloud'));
$totalConvs    = array_sum(array_column($stats, 'open_convs'));
?>
<div class="set-kpi-grid">
    <?php foreach ([
        ['🛰', 'Numeros conectados',  $totalChannels, '#10B981'],
        ['✅', 'Numeros activos',     $totalActive,   '#10B981'],
        ['☁',  'Cloud API (Meta)',    $totalCloud,    '#06B6D4'],
        ['💬', 'Conversaciones abiertas', $totalConvs, '#F59E0B'],
    ] as [$em, $lbl, $val, $col]): ?>
    <div class="set-kpi">
        <div class="set-kpi-head">
            <span class="set-kpi-label"><?= e($lbl) ?></span>
            <span class="set-kpi-emoji"><?= $em ?></span>
        </div>
        <div class="set-kpi-value" style="color: <?= $col ?>;"><?= number_format($val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div x-data="{ open: false, provider: 'wasapi' }" class="set-actions-bar">
    <button @click="open = !open" type="button" class="set-btn set-btn-primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Conectar nuevo numero
    </button>

    <section x-show="open" x-cloak x-transition class="set-section" style="margin-top: 16px;">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>📱</span> Conectar canal de WhatsApp</h2>
                <p class="set-section-desc">Elige el proveedor. Soportamos multiples numeros en simultaneo y los unificamos en una sola bandeja.</p>
            </div>
        </div>

        <form action="<?= url('/settings/channels') ?>" method="POST">
            <?= csrf_field() ?>

            <div class="set-provider-grid">
                <?php foreach ($providers as $key => [$name, $color]): ?>
                <label class="set-provider-card" :class="provider === '<?= $key ?>' ? 'is-active' : ''" style="--prov-color: <?= $color ?>;">
                    <input type="radio" name="provider" value="<?= $key ?>" x-model="provider">
                    <div class="set-provider-name"><?= e($name) ?></div>
                    <p class="set-provider-desc">
                        <?= match($key) {
                            'wasapi' => 'API key + numero. Listo en 1 minuto.',
                            'cloud'  => 'Oficial de Meta. WABA + Phone Number ID.',
                            'twilio' => 'Account SID + Auth Token.',
                            'dialog360' => 'Partner BSP global.',
                            default => 'HTTP custom: usa tu propio backend.',
                        } ?>
                    </p>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="set-field-row cols-2 set-field" style="margin-top: 16px;">
                <div>
                    <label class="set-label">Etiqueta interna</label>
                    <input type="text" name="label" required maxlength="120" placeholder="Ventas Republica Dominicana" class="set-input">
                </div>
                <div>
                    <label class="set-label">Numero (E.164)</label>
                    <input type="text" name="phone" required placeholder="+18091234567" class="set-input">
                </div>
            </div>

            <div x-show="provider === 'wasapi'" x-transition class="set-field">
                <label class="set-label">API Key Wasapi</label>
                <input type="password" name="api_key" placeholder="wasapi_..." class="set-input set-mono">
            </div>

            <div x-show="provider === 'cloud'" x-transition>
                <div class="set-field-row cols-2 set-field">
                    <div>
                        <label class="set-label">Phone Number ID</label>
                        <input type="text" name="phone_number_id" placeholder="123456789012345" class="set-input set-mono">
                    </div>
                    <div>
                        <label class="set-label">WABA ID</label>
                        <input type="text" name="business_account_id" placeholder="987654321012345" class="set-input set-mono">
                    </div>
                </div>
                <div class="set-field">
                    <label class="set-label">System User Token</label>
                    <input type="password" name="access_token" placeholder="EAAB..." class="set-input set-mono">
                </div>
                <div class="set-field-row cols-2 set-field">
                    <div>
                        <label class="set-label">Webhook Verify Token</label>
                        <input type="text" name="webhook_verify" placeholder="cualquier-string" class="set-input">
                    </div>
                    <div>
                        <label class="set-label">Webhook Secret (App secret)</label>
                        <input type="password" name="webhook_secret" placeholder="X-Hub-Signature-256" class="set-input set-mono">
                    </div>
                </div>
            </div>

            <div x-show="provider === 'twilio'" x-transition class="set-field-row cols-2 set-field">
                <div>
                    <label class="set-label">Account SID</label>
                    <input type="text" name="api_key" placeholder="ACxxxxxxx" class="set-input set-mono">
                </div>
                <div>
                    <label class="set-label">Auth Token</label>
                    <input type="password" name="api_secret" placeholder="••••••" class="set-input set-mono">
                </div>
            </div>

            <div x-show="provider === 'dialog360'" x-transition class="set-field">
                <label class="set-label">D360 API Key</label>
                <input type="password" name="api_key" class="set-input set-mono">
            </div>

            <div x-show="provider === 'custom'" x-transition class="set-field-row cols-2 set-field">
                <div>
                    <label class="set-label">Auth (header)</label>
                    <input type="password" name="api_key" class="set-input set-mono">
                </div>
                <div>
                    <label class="set-label">Webhook secret</label>
                    <input type="password" name="webhook_secret" class="set-input set-mono">
                </div>
            </div>

            <div class="set-field-row cols-2 set-field">
                <div>
                    <label class="set-label">Color (UI)</label>
                    <input type="color" name="color" value="#10B981" class="set-input" style="height: 42px; padding: 4px;">
                </div>
                <label class="set-check" style="margin: 0;">
                    <input type="checkbox" name="is_default" value="1">
                    <div class="set-check-body">
                        <div class="set-check-title">Marcar como canal predeterminado</div>
                    </div>
                </label>
            </div>

            <div class="set-actions">
                <button type="button" @click="open = false" class="set-btn set-btn-ghost">Cancelar</button>
                <button type="submit" class="set-btn set-btn-primary">Conectar canal</button>
            </div>
        </form>
    </section>
</div>

<?php if (empty($channels)): ?>
<section class="set-section">
    <div class="set-empty">
        <div class="set-empty-icon">📡</div>
        <h3 class="set-empty-title">Aun no tienes canales</h3>
        <p class="set-empty-desc">Conecta tu primer numero de WhatsApp para empezar a recibir mensajes.</p>
    </div>
</section>
<?php else: ?>
<div class="set-card-grid">
    <?php foreach ($channels as $ch):
        [$providerName, $providerColor] = $providers[$ch['provider']] ?? [$ch['provider'], '#10B981'];
        $health = strtolower((string) ($ch['quality_rating'] ?? 'unknown'));
        $healthColor = match ($health) { 'green' => '#10B981', 'yellow' => '#F59E0B', 'red' => '#F43F5E', default => '#94A3B8' };
        $isActive = $ch['status'] === 'active';
        $stat = $stats[$ch['id']] ?? ['msgs_24h' => 0, 'open_convs' => 0];
        $webhookUrl = $ch['provider'] === 'cloud'
            ? url('/webhooks/cloud/' . $ch['uuid'])
            : url('/webhooks/wasapi/' . (\App\Core\Database::fetchColumn("SELECT uuid FROM tenants WHERE id = :id", ['id' => (int) $ch['tenant_id']]) ?? ''));
    ?>
    <div class="set-section set-channel-card" x-data="{ editing: false }">
        <?php if (!empty($ch['is_default'])): ?>
        <div class="set-int-card-premium" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Default</div>
        <?php endif; ?>

        <div class="set-int-card-head">
            <div class="set-int-card-logo" style="background: <?= e((string) ($ch['color'] ?? $providerColor)) ?>;">
                <?= $ch['provider'] === 'cloud' ? '☁' : '📱' ?>
            </div>
            <div class="set-int-card-meta">
                <div class="set-rule-head">
                    <h3 class="set-int-card-name"><?= e($ch['label']) ?></h3>
                    <span class="set-badge" style="background: <?= $providerColor ?>22; color: <?= $providerColor ?>;"><?= e($providerName) ?></span>
                </div>
                <code class="set-mono-xs"><?= e((string) $ch['phone']) ?></code>
                <div class="set-rule-meta">
                    <span style="color: <?= $isActive ? '#10B981' : '#F87171' ?>;">
                        <span class="set-int-dot" style="background: <?= $isActive ? '#10B981' : '#EF4444' ?>;"></span>
                        <?= $isActive ? 'Activo' : ucfirst((string) $ch['status']) ?>
                    </span>
                    <?php if ($health !== 'unknown'): ?>
                    <span class="set-sep">·</span>
                    <span style="color: <?= $healthColor ?>;">
                        <span class="set-int-dot" style="background: <?= $healthColor ?>;"></span>
                        Calidad <?= e($health) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="set-mini-stats">
            <div class="set-mini-stat">
                <div class="set-mini-stat-label">Conv abiertas</div>
                <div class="set-mini-stat-value"><?= number_format($stat['open_convs']) ?></div>
            </div>
            <div class="set-mini-stat">
                <div class="set-mini-stat-label">Mensajes 24h</div>
                <div class="set-mini-stat-value"><?= number_format($stat['msgs_24h']) ?></div>
            </div>
        </div>

        <details class="set-details set-details-sm">
            <summary>Webhook URL</summary>
            <div class="set-details-body">
                <div class="set-code-row">
                    <code class="set-code"><?= e($webhookUrl) ?></code>
                    <button type="button" onclick="navigator.clipboard.writeText('<?= e($webhookUrl) ?>')" class="set-btn set-btn-ghost set-btn-sm">Copiar</button>
                </div>
                <?php if ($ch['provider'] === 'cloud'): ?>
                <p class="set-help">Configura este URL como webhook en Meta Business Manager. Verify token: <code class="set-mono-xs"><?= e((string) ($ch['webhook_verify'] ?? '(definelo arriba)')) ?></code></p>
                <?php endif; ?>
            </div>
        </details>

        <div class="set-channel-actions">
            <button type="button" onclick="testChannel(<?= (int) $ch['id'] ?>, this)" class="set-btn set-btn-ghost set-btn-sm">Test</button>
            <button type="button" @click="editing = !editing" class="set-btn set-btn-ghost set-btn-sm">
                <span x-text="editing ? 'Cerrar' : 'Editar'">Editar</span>
            </button>
            <?php if (empty($ch['is_default'])): ?>
            <form action="<?= url('/settings/channels/' . $ch['id'] . '/default') ?>" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="set-btn set-btn-ghost set-btn-sm">Default</button>
            </form>
            <?php endif; ?>
            <form action="<?= url('/settings/channels/' . $ch['id'] . '/toggle') ?>" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="set-btn set-btn-ghost set-btn-sm"><?= $isActive ? 'Pausar' : 'Activar' ?></button>
            </form>
            <form action="<?= url('/settings/channels/' . $ch['id']) ?>" method="POST" style="display:inline; margin-left:auto;" onsubmit="return confirm('Eliminar canal y todo su historial?')">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="set-btn set-btn-danger set-btn-sm">Eliminar</button>
            </form>
        </div>

        <div x-show="editing" x-cloak x-transition class="set-channel-edit">
            <form action="<?= url('/settings/channels/' . $ch['id']) ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="set-field-row cols-2 set-field">
                    <div>
                        <label class="set-label">Etiqueta</label>
                        <input type="text" name="label" value="<?= e((string) $ch['label']) ?>" maxlength="120" class="set-input">
                    </div>
                    <div>
                        <label class="set-label">Numero (E.164)</label>
                        <input type="text" name="phone" value="<?= e((string) $ch['phone']) ?>" placeholder="+18495551234" class="set-input">
                        <p class="set-help">Debe coincidir con el numero conectado.</p>
                    </div>
                </div>
                <?php if ($ch['provider'] === 'wasapi'): ?>
                <div class="set-field">
                    <label class="set-label">API Key Wasapi</label>
                    <input type="password" name="api_key" value="" placeholder="<?= !empty($ch['api_key']) ? '•••• (deja vacio para mantener)' : 'Tu API key' ?>" autocomplete="off" class="set-input set-mono">
                    <p class="set-help">Solo cambia si rotaste tu API key.</p>
                </div>
                <?php elseif ($ch['provider'] === 'cloud'): ?>
                <div class="set-field-row cols-2 set-field">
                    <div>
                        <label class="set-label">Phone Number ID (Meta)</label>
                        <input type="text" name="phone_number_id" value="<?= e((string) ($ch['phone_number_id'] ?? '')) ?>" class="set-input">
                    </div>
                    <div>
                        <label class="set-label">Business Account ID</label>
                        <input type="text" name="business_account_id" value="<?= e((string) ($ch['business_account_id'] ?? '')) ?>" class="set-input">
                    </div>
                </div>
                <div class="set-field">
                    <label class="set-label">Access Token (Permanent System User)</label>
                    <input type="password" name="access_token" value="" placeholder="<?= !empty($ch['access_token']) ? '•••• (deja vacio para mantener)' : 'EAAxxxxxxxx' ?>" autocomplete="off" class="set-input set-mono">
                </div>
                <div class="set-field">
                    <label class="set-label">Verify Token (webhook)</label>
                    <input type="text" name="webhook_verify" value="<?= e((string) ($ch['webhook_verify'] ?? '')) ?>" class="set-input">
                </div>
                <?php endif; ?>
                <div class="set-actions">
                    <button type="button" @click="editing = false" class="set-btn set-btn-ghost">Cancelar</button>
                    <button type="submit" class="set-btn set-btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>

        <div id="testResult-<?= (int) $ch['id'] ?>" class="set-test-result"></div>
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
            out.style.color = '#10B981';
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

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
