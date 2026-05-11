<?php
/** @var array $destinations */
/** @var array $events */
/** @var array $types */
/** @var array $logs */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$typeMeta = [
    'email'    => ['label' => 'Email (Resend)',     'icon' => '📧', 'color' => '#0EA572', 'desc' => 'Envia un correo formateado al destinatario via Resend.'],
    'slack'    => ['label' => 'Slack',              'icon' => '💬', 'color' => '#4A154B', 'desc' => 'Publica en un canal via incoming webhook de Slack.'],
    'discord'  => ['label' => 'Discord',            'icon' => '🎮', 'color' => '#5865F2', 'desc' => 'Publica en un canal via webhook de Discord.'],
    'teams'    => ['label' => 'Microsoft Teams',    'icon' => '👥', 'color' => '#5059C9', 'desc' => 'Publica en un canal via Incoming Webhook de Teams.'],
    'telegram' => ['label' => 'Telegram',           'icon' => '✈️', 'color' => '#0088CC', 'desc' => 'Envia mensaje a un chat via Bot API de Telegram.'],
    'webhook'  => ['label' => 'Webhook generico',   'icon' => '🔗', 'color' => '#64748B', 'desc' => 'POST JSON a tu URL con HMAC SHA256 opcional.'],
    'whatsapp' => ['label' => 'WhatsApp interno',   'icon' => '📱', 'color' => '#25D366', 'desc' => 'Envia un mensaje a un numero usando los canales WhatsApp del tenant.'],
];

$totalDest   = count($destinations);
$activeDest  = count(array_filter($destinations, fn ($d) => !empty($d['is_active'])));
$successAll  = array_sum(array_map(fn ($d) => (int) $d['success_count'], $destinations));
$failuresAll = array_sum(array_map(fn ($d) => (int) $d['failure_count'], $destinations));

$prefillType = (string) ($_GET['prefill'] ?? '');
$prefillType = in_array($prefillType, ['email','slack','discord','teams','telegram','webhook','whatsapp'], true) ? $prefillType : '';
$prefillLabels = [
    'slack'    => 'Te trajimos aqui desde Integraciones. Configura tu webhook de Slack abajo.',
    'discord'  => 'Te trajimos aqui desde Integraciones. Configura tu webhook de Discord abajo.',
    'teams'    => 'Te trajimos aqui desde Integraciones. Configura tu webhook de Microsoft Teams abajo.',
    'telegram' => 'Te trajimos aqui desde Integraciones. Configura tu bot de Telegram abajo.',
];
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'notifications']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Canales',
    'title'    => 'Notificaciones',
    'subtitle' => 'Configura a donde quieres que lleguen las ordenes y eventos del sistema. Soporta email (Resend), Slack, Discord, Teams, Telegram, WhatsApp y webhooks genericos.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="set-kpi-grid">
    <?php foreach ([
        ['🎯', 'Destinos configurados', $totalDest,  '#10B981'],
        ['✅', 'Activos',                $activeDest, '#10B981'],
        ['📬', 'Envios exitosos',        $successAll, '#06B6D4'],
        ['⚠',  'Errores',                $failuresAll, '#F43F5E'],
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

<?php if ($prefillType && isset($prefillLabels[$prefillType])): ?>
<div class="set-flash set-flash-info"><span>💡</span><?= e($prefillLabels[$prefillType]) ?></div>
<?php endif; ?>

<div x-data="{ open: <?= $prefillType ? 'true' : 'false' ?>, editing: null, type: '<?= $prefillType ?: 'email' ?>', selectedEvents: [] }" class="set-actions-bar">
    <button @click="open = !open; editing = null; type='email'; selectedEvents=[]" type="button" class="set-btn set-btn-success">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Nuevo destino de notificacion
    </button>

    <section x-show="open" x-cloak x-transition class="set-section" style="margin-top: 16px;">
        <form :action="editing ? '<?= url('/settings/notifications/') ?>' + editing.id : '<?= url('/settings/notifications') ?>'" method="POST">
            <?= csrf_field() ?>
            <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

            <div class="set-field-row cols-3 set-field">
                <div>
                    <label class="set-label">Canal</label>
                    <select name="type" x-model="type" class="set-select">
                        <?php foreach ($typeMeta as $t => $m): ?>
                        <option value="<?= e($t) ?>"><?= $m['icon'] ?> <?= e($m['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="set-help" x-text="({ email: '<?= e($typeMeta['email']['desc']) ?>', slack: '<?= e($typeMeta['slack']['desc']) ?>', discord: '<?= e($typeMeta['discord']['desc']) ?>', teams: '<?= e($typeMeta['teams']['desc']) ?>', telegram: '<?= e($typeMeta['telegram']['desc']) ?>', webhook: '<?= e($typeMeta['webhook']['desc']) ?>', whatsapp: '<?= e($typeMeta['whatsapp']['desc']) ?>' })[type]"></p>
                </div>
                <div style="grid-column: span 2;">
                    <label class="set-label">Etiqueta interna</label>
                    <input type="text" name="label" :value="editing?.label" required maxlength="120" placeholder="Ej. Email de gerencia / Slack #ordenes / Bot Telegram cocina" class="set-input">
                </div>
            </div>

            <fieldset class="set-fieldset">
                <legend>Configuracion</legend>

                <template x-if="type === 'email'">
                <div x-data="emailChips()" x-init="init(editing)">
                    <label class="set-label">Emails destinatarios</label>
                    <div class="set-chips-wrap" :class="{ 'has-focus': focused }" @click="$refs.input.focus()">
                        <template x-for="(em, idx) in emails" :key="idx">
                            <span class="set-email-chip">
                                <span x-text="em"></span>
                                <button type="button" @click.stop="remove(idx)" title="Quitar">×</button>
                            </span>
                        </template>
                        <input x-ref="input" type="text" x-model="draft"
                            @keydown.enter.prevent="commit()" @keydown.tab="commit()"
                            @keydown.comma.prevent="commit()" @keydown.semicolon.prevent="commit()"
                            @keydown.backspace="if (!draft.length && emails.length) emails.pop()"
                            @blur="focused = false; commit()" @focus="focused = true" @paste="onPaste($event)"
                            placeholder="ordenes@miempresa.com, gerente@miempresa.com"
                            class="set-chips-input">
                    </div>
                    <input type="hidden" name="emails" :value="emails.join(',')">
                    <p class="set-help">
                        Pulsa <kbd class="set-kbd">Enter</kbd>, <kbd class="set-kbd">Tab</kbd> o coma para agregar. Maximo 20 emails.
                        <span x-show="emails.length > 0" x-text="'(' + emails.length + ' agregado' + (emails.length === 1 ? '' : 's') + ')'"></span>
                    </p>
                </div>
                </template>

                <template x-if="type === 'slack'">
                <div class="set-field">
                    <label class="set-label">Webhook URL de Slack</label>
                    <input type="url" name="webhook_url" :value="editing?.config?.webhook_url" placeholder="https://hooks.slack.com/services/T.../B.../..." class="set-input">
                    <p class="set-help">En Slack: Apps → Incoming Webhooks → Crear → Copiar URL.</p>
                </div>
                </template>

                <template x-if="type === 'discord'">
                <div class="set-field-row cols-2 set-field">
                    <div>
                        <label class="set-label">Webhook URL de Discord</label>
                        <input type="url" name="webhook_url" :value="editing?.config?.webhook_url" placeholder="https://discord.com/api/webhooks/..." class="set-input">
                    </div>
                    <div>
                        <label class="set-label">Username del bot</label>
                        <input type="text" name="username" :value="editing?.config?.username || 'Evallish Pulse'" placeholder="Evallish Pulse" class="set-input">
                    </div>
                </div>
                </template>

                <template x-if="type === 'teams'">
                <div class="set-field">
                    <label class="set-label">Incoming Webhook URL de Teams</label>
                    <input type="url" name="webhook_url" :value="editing?.config?.webhook_url" placeholder="https://xxx.webhook.office.com/webhookb2/..." class="set-input">
                    <p class="set-help">En Teams: tres puntos del canal → Conectores → Incoming Webhook → Crear → Copiar URL.</p>
                </div>
                </template>

                <template x-if="type === 'telegram'">
                <div class="set-field-row cols-2 set-field">
                    <div>
                        <label class="set-label">Bot token</label>
                        <input type="text" name="bot_token" :value="editing?.config?.bot_token" placeholder="123456789:ABC..." class="set-input">
                        <p class="set-help">Obten un token con @BotFather en Telegram.</p>
                    </div>
                    <div>
                        <label class="set-label">Chat ID</label>
                        <input type="text" name="chat_id" :value="editing?.config?.chat_id" placeholder="-100123456789 (grupo) o 123456 (privado)" class="set-input">
                        <p class="set-help">Habla con tu bot y visita https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates.</p>
                    </div>
                </div>
                </template>

                <template x-if="type === 'webhook'">
                <div class="set-field-row cols-2 set-field">
                    <div>
                        <label class="set-label">URL destino</label>
                        <input type="url" name="url" :value="editing?.config?.url" placeholder="https://mi-servidor.com/hook" class="set-input">
                    </div>
                    <div>
                        <label class="set-label">Secret HMAC (opcional)</label>
                        <input type="text" name="secret" :value="editing?.config?.secret" placeholder="(deja vacio para no firmar)" class="set-input">
                        <p class="set-help">Si se rellena, el header <code>X-Kyros-Signature</code> traera SHA256 HMAC del body.</p>
                    </div>
                </div>
                </template>

                <template x-if="type === 'whatsapp'">
                <div class="set-field">
                    <label class="set-label">Numero destino (E.164)</label>
                    <input type="text" name="phone" :value="editing?.config?.phone" placeholder="+18095551234" class="set-input">
                    <p class="set-help">Usara automaticamente uno de los canales WhatsApp activos del tenant para enviar.</p>
                </div>
                </template>
            </fieldset>

            <fieldset class="set-fieldset">
                <legend>Eventos a notificar</legend>

                <?php foreach ($events as $entityKey => $entityEvents): ?>
                <div class="set-event-group">
                    <div class="set-event-group-label"><?= e(ucfirst($entityKey)) ?></div>
                    <div class="set-event-grid">
                        <?php foreach ($entityEvents as $eKey => $eLabel): ?>
                        <label class="set-check">
                            <input type="checkbox" name="events[]" value="<?= e($eKey) ?>"
                                   :checked="(editing?.events || []).includes('<?= e($eKey) ?>')">
                            <div class="set-check-body">
                                <div class="set-check-title"><?= e($eLabel) ?></div>
                                <div class="set-check-desc set-mono"><?= e($eKey) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <input type="hidden" name="entity" value="order">
            </fieldset>

            <div class="set-actions" style="justify-content: space-between;">
                <label class="set-check" style="margin: 0;">
                    <input type="checkbox" name="is_active" value="1" :checked="editing ? !!editing.is_active : true">
                    <div class="set-check-body">
                        <div class="set-check-title">Destino activo</div>
                    </div>
                </label>
                <div style="display: flex; gap: 8px;">
                    <button @click="open = false" type="button" class="set-btn set-btn-ghost">Cancelar</button>
                    <button type="submit" class="set-btn set-btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span x-text="editing ? 'Guardar cambios' : 'Crear destino'"></span>
                    </button>
                </div>
            </div>
        </form>
    </section>

    <?php if (empty($destinations)): ?>
    <div class="set-empty" style="margin-top: 20px;">
        <div class="set-empty-icon">📭</div>
        <h3 class="set-empty-title">Sin destinos configurados</h3>
        <p class="set-empty-desc">Cuando crees uno, las ordenes y eventos del sistema llegaran al canal que elijas.</p>
    </div>
    <?php else: ?>
    <ul class="set-rule-list" style="margin-top: 20px;">
        <?php foreach ($destinations as $d):
            $meta = $typeMeta[$d['type']] ?? ['icon' => '📡', 'label' => $d['type'], 'color' => '#64748B'];
            $eventsList = is_array($d['events']) ? $d['events'] : (json_decode((string) $d['events'], true) ?: []);
            $configFmt = '';
            $configMissing = '';
            $cfg = is_array($d['config']) ? $d['config'] : [];
            switch ($d['type']) {
                case 'email':
                    if (empty($cfg['emails']) && empty($cfg['email'])) $configMissing = 'Sin destinatarios';
                    break;
                case 'slack':
                case 'discord':
                case 'teams':
                    if (empty(trim((string) ($cfg['webhook_url'] ?? '')))) $configMissing = 'Sin Webhook URL';
                    break;
                case 'telegram':
                    if (empty($cfg['bot_token']) || empty($cfg['chat_id'])) $configMissing = 'Falta bot_token o chat_id';
                    break;
                case 'webhook':
                    if (empty(trim((string) ($cfg['url'] ?? '')))) $configMissing = 'Sin URL';
                    break;
                case 'whatsapp':
                    if (empty($cfg['phone'])) $configMissing = 'Sin numero destino';
                    break;
            }
            switch ($d['type']) {
                case 'email':
                    $emails = $d['config']['emails'] ?? null;
                    if (is_array($emails) && !empty($emails)) {
                        $configFmt = count($emails) === 1
                            ? (string) $emails[0]
                            : count($emails) . ' destinatarios: ' . implode(', ', array_slice($emails, 0, 3)) . (count($emails) > 3 ? ' +' . (count($emails) - 3) . ' mas' : '');
                    } else {
                        $configFmt = (string) ($d['config']['email'] ?? '—');
                    }
                    break;
                case 'slack':    $configFmt = mb_strimwidth((string) ($d['config']['webhook_url'] ?? '—'), 0, 60, '...'); break;
                case 'discord':  $configFmt = mb_strimwidth((string) ($d['config']['webhook_url'] ?? '—'), 0, 60, '...'); break;
                case 'teams':    $configFmt = mb_strimwidth((string) ($d['config']['webhook_url'] ?? '—'), 0, 60, '...'); break;
                case 'telegram': $configFmt = 'chat_id ' . ($d['config']['chat_id'] ?? '—'); break;
                case 'webhook':  $configFmt = mb_strimwidth((string) ($d['config']['url'] ?? '—'), 0, 60, '...'); break;
                case 'whatsapp': $configFmt = (string) ($d['config']['phone'] ?? '—'); break;
            }
        ?>
        <li class="set-rule-item">
            <div class="set-rule-icon" style="background: <?= $meta['color'] ?>20; color: <?= $meta['color'] ?>;"><?= $meta['icon'] ?></div>
            <div class="set-rule-body">
                <div class="set-rule-head">
                    <span class="set-rule-name"><?= e($d['label']) ?></span>
                    <span class="set-badge" style="background: <?= $meta['color'] ?>22; color: <?= $meta['color'] ?>;"><?= e($meta['label']) ?></span>
                    <?php if ($configMissing): ?>
                    <span class="set-badge" style="background: rgba(244,63,94,.15); color:#BE123C;" title="<?= e($configMissing) ?>">⚠ Config incompleta</span>
                    <?php elseif (!empty($d['is_active'])): ?>
                    <span class="set-badge" style="background: rgba(16,185,129,.15); color:#10B981;">● Activo</span>
                    <?php else: ?>
                    <span class="set-badge" style="background: rgba(148,163,184,.15); color:#475569;">Pausado</span>
                    <?php endif; ?>
                </div>
                <code class="set-mono-xs set-rule-desc"><?= e($configFmt) ?></code>
                <div class="set-tool-chips">
                    <?php foreach ($eventsList as $ev): ?>
                    <span class="set-tool-chip"><?= e($ev) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="set-rule-meta">
                    <span>✓ <?= number_format((int) $d['success_count']) ?> exitos</span>
                    <span>✗ <?= number_format((int) $d['failure_count']) ?> fallos</span>
                    <?php if (!empty($d['last_used_at'])): ?>
                    <span class="set-sep">·</span>
                    <span>ultima vez: <?= e(time_ago((string) $d['last_used_at'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($d['last_error'])): ?>
                    <span class="set-sep">·</span>
                    <span style="color:#BE123C;" title="<?= e((string) $d['last_error']) ?>">error reciente</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="set-rule-actions">
                <form action="<?= url('/settings/notifications/' . $d['id'] . '/test') ?>" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="set-btn set-btn-ghost set-btn-sm" title="Enviar prueba">Probar</button>
                </form>
                <form action="<?= url('/settings/notifications/' . $d['id'] . '/toggle') ?>" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="set-btn set-btn-ghost set-btn-sm" title="Activar/Pausar">
                        <?= !empty($d['is_active']) ? '⏸' : '▶' ?>
                    </button>
                </form>
                <button type="button" @click='editing = <?= json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT) ?>; type = editing.type; open = true' class="set-btn set-btn-ghost set-btn-sm" title="Editar">✎</button>
                <form action="<?= url('/settings/notifications/' . $d['id']) ?>" method="POST" style="display:inline;" onsubmit="return confirm('Eliminar este destino? No se borraran los logs anteriores.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="set-btn set-btn-danger set-btn-sm">🗑</button>
                </form>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?php if (!empty($logs)): ?>
<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>📋</span> Actividad reciente</h2>
            <p class="set-section-desc">Ultimos 30 envios.</p>
        </div>
    </div>
    <div class="set-table-wrap">
        <table class="set-table">
            <thead>
                <tr><th>Cuando</th><th>Destino</th><th>Tipo</th><th>Evento</th><th>Status</th><th>Detalle</th></tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="set-td-meta"><?= e(time_ago((string) $l['created_at'])) ?></td>
                    <td class="set-td-meta"><?= e((string) ($l['label'] ?? '—')) ?></td>
                    <td><span class="set-badge"><?= e((string) $l['type']) ?></span></td>
                    <td><code class="set-mono-xs"><?= e((string) $l['event']) ?></code></td>
                    <td>
                        <?php if ($l['status'] === 'success'): ?>
                        <span class="set-badge" style="background: rgba(16,185,129,.15); color:#10B981;">✓ ok</span>
                        <?php else: ?>
                        <span class="set-badge" style="background: rgba(244,63,94,.15); color:#BE123C;">✗ fallo</span>
                        <?php endif; ?>
                    </td>
                    <td class="set-td-meta" style="max-width: 320px;">
                        <?= e(mb_strimwidth((string) ($l['error'] ?: $l['response'] ?: '—'), 0, 80, '...')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<script>
window.emailChips = function () {
    return {
        emails: [],
        draft: '',
        focused: false,
        init(editing) {
            let initial = [];
            if (editing && editing.config) {
                if (Array.isArray(editing.config.emails)) {
                    initial = editing.config.emails;
                } else if (typeof editing.config.emails === 'string' && editing.config.emails.length) {
                    initial = editing.config.emails.split(/[,;\s]+/);
                } else if (editing.config.email) {
                    initial = [editing.config.email];
                }
            }
            this.emails = initial.map(s => String(s).trim()).filter(Boolean).filter((v, i, a) => a.indexOf(v) === i).slice(0, 20);
        },
        commit() {
            const raw = this.draft.trim().replace(/[,;]+$/, '').trim();
            if (!raw) return;
            const parts = raw.split(/[,;\s]+/).map(s => s.trim()).filter(Boolean);
            for (const p of parts) {
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(p)) continue;
                if (this.emails.length >= 20) break;
                if (this.emails.includes(p)) continue;
                this.emails.push(p);
            }
            this.draft = '';
        },
        remove(idx) { this.emails.splice(idx, 1); },
        onPaste(e) {
            const text = (e.clipboardData || window.clipboardData).getData('text');
            if (!text) return;
            if (/[,;\s\n]/.test(text)) {
                e.preventDefault();
                this.draft = text;
                this.commit();
            }
        },
    };
};
</script>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
