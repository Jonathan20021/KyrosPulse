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
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Notificaciones',
    'subtitle' => 'Configura a donde quieres que lleguen las ordenes y eventos del sistema. Soporta email (Resend), Slack, Discord, Teams, Telegram, WhatsApp y webhooks genericos.',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'notifications']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php foreach ([
        ['🎯', 'Destinos configurados', $totalDest,  '#10B981'],
        ['✅', 'Activos',                $activeDest, '#10B981'],
        ['📬', 'Envios exitosos',        $successAll, '#06B6D4'],
        ['⚠',  'Errores',                $failuresAll, '#F43F5E'],
    ] as [$em, $lbl, $val, $col]): ?>
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);"><?= e($lbl) ?></span>
            <span class="text-xl"><?= $em ?></span>
        </div>
        <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= number_format($val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Form crear / editar -->
<div x-data="{ open: false, editing: null, type: 'email', selectedEvents: [] }" class="mb-6">
    <button @click="open = !open; editing = null; type='email'; selectedEvents=[]" type="button" class="px-4 py-2.5 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2"
            style="background: linear-gradient(135deg,#10B981,#0EA572);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Nuevo destino de notificacion
    </button>

    <div x-show="open" x-cloak x-transition class="surface mt-4 p-5">
        <form :action="editing ? '<?= url('/settings/notifications/') ?>' + editing.id : '<?= url('/settings/notifications') ?>'" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

            <!-- Tipo + label -->
            <div class="grid md:grid-cols-3 gap-3">
                <div class="md:col-span-1">
                    <label class="label">Canal</label>
                    <select name="type" x-model="type" class="select">
                        <?php foreach ($typeMeta as $t => $m): ?>
                        <option value="<?= e($t) ?>"><?= $m['icon'] ?> <?= e($m['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-[11px] mt-1" style="color: var(--color-text-tertiary);">
                        <template x-for="meta in [<?= json_encode($typeMeta) ?>]">
                            <span x-text="meta[type]?.desc"></span>
                        </template>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="label">Etiqueta interna</label>
                    <input type="text" name="label" :value="editing?.label" required maxlength="120" placeholder="Ej. Email de gerencia / Slack #ordenes / Bot Telegram cocina" class="input">
                </div>
            </div>

            <!-- Config dinamica por tipo -->
            <div class="form-section">
                <div class="form-section-header">
                    <div class="form-section-icon">⚙</div>
                    <div>
                        <div class="form-section-title">Configuracion</div>
                        <div class="form-section-sub">Datos especificos del canal seleccionado.</div>
                    </div>
                </div>

                <!-- Email -->
                <div x-show="type === 'email'">
                    <label class="label">Email destinatario</label>
                    <input type="email" name="email" :value="editing?.config?.email" placeholder="ordenes@miempresa.com" class="input">
                    <div class="field-help">Asegurate de tener una API key Resend valida en Configuracion → Wasapi & IA.</div>
                </div>

                <!-- Slack -->
                <div x-show="type === 'slack'">
                    <label class="label">Webhook URL de Slack</label>
                    <input type="url" name="webhook_url" :value="editing?.config?.webhook_url" placeholder="https://hooks.slack.com/services/T.../B.../..." class="input">
                    <div class="field-help">En Slack: Apps → Incoming Webhooks → Crear → Copiar URL.</div>
                </div>

                <!-- Discord -->
                <div x-show="type === 'discord'" class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="label">Webhook URL de Discord</label>
                        <input type="url" name="webhook_url" :value="editing?.config?.webhook_url" placeholder="https://discord.com/api/webhooks/..." class="input">
                    </div>
                    <div>
                        <label class="label">Username del bot</label>
                        <input type="text" name="username" :value="editing?.config?.username || 'Kyros Pulse'" placeholder="Kyros Pulse" class="input">
                    </div>
                </div>

                <!-- Teams -->
                <div x-show="type === 'teams'">
                    <label class="label">Incoming Webhook URL de Teams</label>
                    <input type="url" name="webhook_url" :value="editing?.config?.webhook_url" placeholder="https://xxx.webhook.office.com/webhookb2/..." class="input">
                    <div class="field-help">En Teams: tres puntos del canal → Conectores → Incoming Webhook → Crear → Copiar URL.</div>
                </div>

                <!-- Telegram -->
                <div x-show="type === 'telegram'" class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="label">Bot token</label>
                        <input type="text" name="bot_token" :value="editing?.config?.bot_token" placeholder="123456789:ABC..." class="input">
                        <div class="field-help">Obten un token con @BotFather en Telegram.</div>
                    </div>
                    <div>
                        <label class="label">Chat ID</label>
                        <input type="text" name="chat_id" :value="editing?.config?.chat_id" placeholder="-100123456789 (grupo) o 123456 (privado)" class="input">
                        <div class="field-help">Habla con tu bot y luego visita https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates para ver tu chat_id.</div>
                    </div>
                </div>

                <!-- Webhook -->
                <div x-show="type === 'webhook'" class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="label">URL destino</label>
                        <input type="url" name="url" :value="editing?.config?.url" placeholder="https://mi-servidor.com/hook" class="input">
                    </div>
                    <div>
                        <label class="label">Secret HMAC (opcional)</label>
                        <input type="text" name="secret" :value="editing?.config?.secret" placeholder="(deja vacio para no firmar)" class="input">
                        <div class="field-help">Si se rellena, el header <code>X-Kyros-Signature</code> traera SHA256 HMAC del body.</div>
                    </div>
                </div>

                <!-- WhatsApp -->
                <div x-show="type === 'whatsapp'">
                    <label class="label">Numero destino (E.164)</label>
                    <input type="text" name="phone" :value="editing?.config?.phone" placeholder="+18095551234" class="input">
                    <div class="field-help">Usara automaticamente uno de los canales WhatsApp activos del tenant para enviar.</div>
                </div>
            </div>

            <!-- Eventos -->
            <div class="form-section">
                <div class="form-section-header">
                    <div class="form-section-icon">🔔</div>
                    <div>
                        <div class="form-section-title">Eventos a notificar</div>
                        <div class="form-section-sub">Marca todos los eventos en los que quieras recibir esta notificacion.</div>
                    </div>
                </div>

                <?php foreach ($events as $entityKey => $entityEvents): ?>
                <div class="mb-3">
                    <div class="text-[11px] uppercase font-bold tracking-wider mb-2" style="color: var(--color-text-tertiary);"><?= e(ucfirst($entityKey)) ?></div>
                    <div class="grid md:grid-cols-2 gap-2">
                        <?php foreach ($entityEvents as $eKey => $eLabel): ?>
                        <label class="flex items-start gap-2 px-3 py-2 rounded-lg border cursor-pointer transition" style="background: var(--color-bg-subtle); border-color: var(--color-border-subtle);">
                            <input type="checkbox" name="events[]" value="<?= e($eKey) ?>"
                                   :checked="(editing?.events || []).includes('<?= e($eKey) ?>')"
                                   class="w-4 h-4 rounded mt-0.5" style="accent-color: var(--color-primary);">
                            <div>
                                <div class="text-sm font-medium" style="color: var(--color-text-primary);"><?= e($eLabel) ?></div>
                                <div class="text-[10px] font-mono" style="color: var(--color-text-tertiary);"><?= e($eKey) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <input type="hidden" name="entity" value="order">
            </div>

            <!-- Activo + acciones -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer" style="background: var(--color-bg-subtle);">
                    <input type="checkbox" name="is_active" value="1" :checked="editing ? !!editing.is_active : true" class="w-4 h-4 rounded" style="accent-color: var(--color-primary);">
                    <span class="text-sm font-medium" style="color: var(--color-text-primary);">Destino activo</span>
                </label>
                <div class="flex items-center gap-2">
                    <button @click="open = false" type="button" class="btn-cancel">Cancelar</button>
                    <button type="submit" class="btn-submit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span x-text="editing ? 'Guardar cambios' : 'Crear destino'"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Lista de destinos -->
    <?php if (empty($destinations)): ?>
    <div class="surface mt-5 p-10 text-center">
        <div class="text-5xl mb-3 opacity-60">📭</div>
        <h3 class="text-base font-bold mb-1" style="color: var(--color-text-primary);">Sin destinos configurados</h3>
        <p class="text-sm max-w-md mx-auto" style="color: var(--color-text-tertiary);">Cuando crees uno, las ordenes y eventos del sistema llegaran al canal que elijas (correo, Slack, Telegram, WhatsApp, etc.).</p>
    </div>
    <?php else: ?>
    <div class="space-y-2 mt-5">
        <?php foreach ($destinations as $d):
            $meta = $typeMeta[$d['type']] ?? ['icon' => '📡', 'label' => $d['type'], 'color' => '#64748B'];
            $eventsList = is_array($d['events']) ? $d['events'] : (json_decode((string) $d['events'], true) ?: []);
            $configFmt = '';
            switch ($d['type']) {
                case 'email':    $configFmt = (string) ($d['config']['email'] ?? '—'); break;
                case 'slack':    $configFmt = mb_strimwidth((string) ($d['config']['webhook_url'] ?? '—'), 0, 60, '...'); break;
                case 'discord':  $configFmt = mb_strimwidth((string) ($d['config']['webhook_url'] ?? '—'), 0, 60, '...'); break;
                case 'teams':    $configFmt = mb_strimwidth((string) ($d['config']['webhook_url'] ?? '—'), 0, 60, '...'); break;
                case 'telegram': $configFmt = 'chat_id ' . ($d['config']['chat_id'] ?? '—'); break;
                case 'webhook':  $configFmt = mb_strimwidth((string) ($d['config']['url'] ?? '—'), 0, 60, '...'); break;
                case 'whatsapp': $configFmt = (string) ($d['config']['phone'] ?? '—'); break;
            }
        ?>
        <div class="surface p-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background: <?= $meta['color'] ?>20; border: 1px solid <?= $meta['color'] ?>40;"><?= $meta['icon'] ?></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <h4 class="font-bold text-sm" style="color: var(--color-text-primary);"><?= e($d['label']) ?></h4>
                        <span class="badge badge-emerald"><?= e($meta['label']) ?></span>
                        <?php if (!empty($d['is_active'])): ?>
                        <span class="badge badge-emerald badge-dot">Activo</span>
                        <?php else: ?>
                        <span class="badge badge-slate badge-dot">Pausado</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs font-mono mb-2 truncate" style="color: var(--color-text-tertiary);"><?= e($configFmt) ?></div>
                    <div class="flex flex-wrap gap-1 mb-2">
                        <?php foreach ($eventsList as $ev): ?>
                        <span class="text-[10px] px-2 py-0.5 rounded-full" style="background: var(--color-bg-subtle); color: var(--color-text-secondary); border: 1px solid var(--color-border-subtle);">
                            <?= e($ev) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex items-center gap-3 text-[11px]" style="color: var(--color-text-tertiary);">
                        <span>✓ <?= number_format((int) $d['success_count']) ?> exitos</span>
                        <span>✗ <?= number_format((int) $d['failure_count']) ?> fallos</span>
                        <?php if (!empty($d['last_used_at'])): ?>
                        <span>· ultima vez: <?= e(time_ago((string) $d['last_used_at'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($d['last_error'])): ?>
                        <span style="color:#BE123C;" title="<?= e((string) $d['last_error']) ?>">· error reciente</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <form action="<?= url('/settings/notifications/' . $d['id'] . '/test') ?>" method="POST">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary btn-sm" title="Enviar prueba">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                            Probar
                        </button>
                    </form>
                    <form action="<?= url('/settings/notifications/' . $d['id'] . '/toggle') ?>" method="POST">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-ghost btn-icon" title="Activar/Pausar">
                            <?php if (!empty($d['is_active'])): ?>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php else: ?>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php endif; ?>
                        </button>
                    </form>
                    <button type="button" @click='editing = <?= json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT) ?>; type = editing.type; open = true' class="btn btn-ghost btn-icon" title="Editar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <form action="<?= url('/settings/notifications/' . $d['id']) ?>" method="POST" onsubmit="return confirm('Eliminar este destino? No se borraran los logs anteriores.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-ghost btn-icon" title="Eliminar" style="color:#DC2A47;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Logs recientes -->
<?php if (!empty($logs)): ?>
<div class="surface p-5 mt-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-bold text-sm" style="color: var(--color-text-primary);">Actividad reciente</h3>
        <span class="text-[10px]" style="color: var(--color-text-tertiary);">Ultimos 30 envios</span>
    </div>
    <div class="overflow-x-auto">
        <table class="kt-table">
            <thead>
                <tr><th>Cuando</th><th>Destino</th><th>Tipo</th><th>Evento</th><th>Status</th><th>Detalle</th></tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="text-xs whitespace-nowrap"><?= e(time_ago((string) $l['created_at'])) ?></td>
                    <td class="text-xs"><?= e((string) ($l['label'] ?? '—')) ?></td>
                    <td class="text-xs"><span class="badge badge-slate"><?= e((string) $l['type']) ?></span></td>
                    <td class="text-xs font-mono"><?= e((string) $l['event']) ?></td>
                    <td>
                        <?php if ($l['status'] === 'success'): ?>
                        <span class="badge badge-emerald">✓ ok</span>
                        <?php else: ?>
                        <span class="badge badge-rose">✗ fallo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-xs" style="max-width: 320px; color: var(--color-text-tertiary);">
                        <?= e(mb_strimwidth((string) ($l['error'] ?: $l['response'] ?: '—'), 0, 80, '...')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php \App\Core\View::stop(); ?>
