<?php
/**
 * @var array      $endpoints
 * @var array      $deliveries
 * @var array      $events       slug => label catalogo
 * @var array|null $newSecret    {id, secret, name} si se acaba de crear/rotar
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$active   = array_filter($endpoints, fn($e) => !empty($e['is_active']));
$paused   = array_filter($endpoints, fn($e) => empty($e['is_active']));
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'webhooks']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Plataforma',
    'title'    => 'Webhooks salientes',
    'subtitle' => 'Subscribe URLs externas a eventos del sistema (ordenes, agentes IA, contactos, mensajes). Firmas HMAC-SHA256 + retries automaticos con backoff.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php if ($newSecret): ?>
<div class="set-secret-banner">
    <div class="set-secret-head">
        <div class="set-secret-icon">🔐</div>
        <div>
            <div class="set-secret-title">Tu signing secret — copialo AHORA</div>
            <p class="set-secret-desc">Usa este secret para validar las firmas HMAC en tu servidor. Por seguridad no podremos mostrartelo de nuevo.</p>
        </div>
    </div>
    <div class="set-secret-row">
        <input id="newSecretInput" readonly value="<?= e((string) $newSecret['secret']) ?>" class="set-input set-mono">
        <button type="button" onclick="(()=>{const i=document.getElementById('newSecretInput');i.select();document.execCommand('copy');this.innerText='Copiado!';setTimeout(()=>this.innerText='Copiar',1500);})()"
                class="set-btn set-btn-success">Copiar</button>
    </div>
    <p class="set-help">Webhook: <strong><?= e((string) $newSecret['name']) ?></strong> · ID: <code><?= (int) $newSecret['id'] ?></code></p>
</div>
<?php endif; ?>

<?php
$totalDeliv = count($deliveries);
$delivered  = count(array_filter($deliveries, fn($d) => $d['status'] === 'delivered'));
$failed     = count(array_filter($deliveries, fn($d) => in_array($d['status'], ['failed','dead'], true)));
?>
<div class="set-kpi-grid">
    <?php foreach ([
        ['🪝', 'Endpoints activos', count($active),  '#10B981'],
        ['📡', 'Entregas (50 mas recientes)', $totalDeliv,  '#06B6D4'],
        ['✅', 'Exitosas',           $delivered,     '#10B981'],
        ['⚠',  'Fallidas',            $failed,        '#F43F5E'],
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

<section class="set-section">
    <div class="set-section-head">
        <h2 class="set-section-title"><span>🔏</span> Como verificar la firma HMAC en tu servidor</h2>
    </div>
    <p class="set-help" style="margin-bottom: 12px;">
        Cada delivery viaja con header <code class="set-mono-xs">X-Kyros-Signature: t=&lt;unix&gt;,v1=&lt;hex&gt;</code>.
        Recalcula <code class="set-mono-xs">hash_hmac('sha256', t + '.' + body, $tu_secret)</code> y compara con <code class="set-mono-xs">hash_equals()</code>.
        Rechaza si <code class="set-mono-xs">|t - now| &gt; 300s</code> (defensa anti-replay).
    </p>
<pre class="set-code-block">// Node.js
const crypto = require('crypto');
const sig = req.headers['x-kyros-signature']; // "t=1715300000,v1=abc..."
const [t, v1] = sig.split(',').map(p => p.split('=')[1]);
if (Math.abs(Date.now()/1000 - t) > 300) return res.status(400).send('stale');
const expected = crypto.createHmac('sha256', SECRET).update(t + '.' + req.rawBody).digest('hex');
if (!crypto.timingSafeEqual(Buffer.from(v1), Buffer.from(expected))) return res.status(401).send('bad signature');
// ok!</pre>
</section>

<div x-data="{ open: false }" class="set-actions-bar">
    <button @click="open = !open" type="button" class="set-btn set-btn-success">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Nuevo webhook saliente
    </button>

    <section x-show="open" x-cloak x-transition class="set-section" style="margin-top: 16px;">
        <form action="<?= url('/settings/webhooks') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="set-field-row cols-2 set-field">
                <div>
                    <label class="set-label">Nombre / etiqueta</label>
                    <input name="name" required maxlength="120" placeholder="Mi backend de produccion" class="set-input">
                </div>
                <div>
                    <label class="set-label">URL del endpoint</label>
                    <input name="url" required type="url" placeholder="https://midominio.com/webhooks/kyros" class="set-input">
                </div>
            </div>
            <div class="set-field">
                <label class="set-label">Descripcion (opcional)</label>
                <input name="description" maxlength="500" placeholder="Sincroniza ordenes con nuestro ERP" class="set-input">
            </div>
            <div class="set-field">
                <label class="set-label">Eventos a suscribir</label>
                <div class="set-scope-grid">
                    <label class="set-check">
                        <input type="checkbox" name="events[]" value="*">
                        <div class="set-check-body">
                            <div class="set-check-title set-mono">* (todos)</div>
                            <div class="set-check-desc">Recibe cualquier evento del sistema</div>
                        </div>
                    </label>
                    <?php foreach ($events as $slug => $label): ?>
                    <label class="set-check">
                        <input type="checkbox" name="events[]" value="<?= e($slug) ?>">
                        <div class="set-check-body">
                            <div class="set-check-title set-mono"><?= e($slug) ?></div>
                            <div class="set-check-desc"><?= e($label) ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="set-actions">
                <button type="button" @click="open = false" class="set-btn set-btn-ghost">Cancelar</button>
                <button type="submit" class="set-btn set-btn-success">Crear webhook</button>
            </div>
        </form>
    </section>
</div>

<section class="set-section">
    <div class="set-section-head">
        <h2 class="set-section-title"><span>🪝</span> Endpoints suscritos <span class="set-count">(<?= count($endpoints) ?>)</span></h2>
    </div>
    <?php if (empty($endpoints)): ?>
    <div class="set-empty">
        <div class="set-empty-icon">🪝</div>
        <p class="set-empty-text">Aun no tienes webhooks configurados.</p>
    </div>
    <?php else: ?>
    <ul class="set-rule-list">
        <?php foreach ($endpoints as $e):
            $evList = $e['events'] ? json_decode((string) $e['events'], true) : ['*'];
            if (!is_array($evList)) $evList = ['*'];
        ?>
        <li class="set-rule-item <?= empty($e['is_active']) ? 'is-paused' : '' ?>">
            <div class="set-rule-icon"
                 style="background: <?= !empty($e['is_active']) ? 'rgba(16,185,129,.12)' : 'rgba(148,163,184,.12)' ?>; color: <?= !empty($e['is_active']) ? '#10B981' : '#64748B' ?>;">🪝</div>
            <div class="set-rule-body">
                <div class="set-rule-head">
                    <span class="set-rule-name"><?= e((string) $e['name']) ?></span>
                    <?php if (empty($e['is_active'])): ?>
                    <span class="set-badge" style="background: rgba(148,163,184,.15); color: #475569;">Pausado</span>
                    <?php endif; ?>
                </div>
                <code class="set-mono-xs set-break-all"><?= e((string) $e['url']) ?></code>
                <?php if (!empty($e['description'])): ?>
                <p class="set-rule-desc"><?= e((string) $e['description']) ?></p>
                <?php endif; ?>
                <div class="set-tool-chips">
                    <?php foreach (array_slice($evList, 0, 6) as $ev): ?>
                    <span class="set-tool-chip"><?= e((string) $ev) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($evList) > 6): ?><span class="set-tool-more">+<?= count($evList) - 6 ?></span><?php endif; ?>
                </div>
                <div class="set-rule-meta">
                    <span>✅ <strong><?= number_format((int) $e['success_count']) ?></strong> ok</span>
                    <span>⚠ <strong><?= number_format((int) $e['failure_count']) ?></strong> fallos</span>
                    <?php if (!empty($e['last_delivery_at'])): ?>
                    <span class="set-sep">·</span>
                    <span>Ultima entrega: <?= e((string) $e['last_delivery_at']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($e['last_error'])): ?>
                    <span class="set-sep">·</span>
                    <span style="color: #BE123C;">Error: <?= e(mb_substr((string) $e['last_error'], 0, 80)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="set-rule-actions">
                <form action="<?= url('/settings/webhooks/' . (int) $e['id'] . '/toggle') ?>" method="POST" style="display:inline;"><?= csrf_field() ?>
                    <button class="set-btn set-btn-ghost set-btn-sm">
                        <?= !empty($e['is_active']) ? '⏸' : '▶' ?>
                    </button>
                </form>
                <form action="<?= url('/settings/webhooks/' . (int) $e['id'] . '/rotate') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Rotar el signing secret? Tendras que actualizarlo en tu servidor.')"><?= csrf_field() ?>
                    <button class="set-btn set-btn-ghost set-btn-sm">🔁 Rotar</button>
                </form>
                <form action="<?= url('/settings/webhooks/' . (int) $e['id'] . '/delete') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Eliminar este webhook?')"><?= csrf_field() ?>
                    <button class="set-btn set-btn-danger set-btn-sm">Eliminar</button>
                </form>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>📋</span> Ultimas 50 entregas</h2>
            <p class="set-section-desc">Audit log de cada delivery con status, status code, attempts y latencia. Click en "Reintentar" para los que fallaron.</p>
        </div>
    </div>
    <?php if (empty($deliveries)): ?>
    <div class="set-empty">
        <div class="set-empty-icon">📭</div>
        <p class="set-empty-text">Aun no hay entregas.</p>
    </div>
    <?php else: ?>
    <div class="set-table-wrap">
        <table class="set-table">
            <thead>
                <tr>
                    <th>Cuando</th>
                    <th>Endpoint</th>
                    <th>Evento</th>
                    <th>Status</th>
                    <th>Code</th>
                    <th>Try</th>
                    <th class="set-td-right">Latencia</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deliveries as $d):
                    $st = (string) $d['status'];
                    $col = match($st) {
                        'delivered' => '#0B7C56',
                        'failed','dead' => '#BE123C',
                        'pending' => '#B45309',
                        default => '#64748B',
                    };
                ?>
                <tr>
                    <td class="set-td-meta"><?= e((string) $d['created_at']) ?></td>
                    <td class="set-td-meta"><?= e((string) ($d['endpoint_name'] ?? '—')) ?></td>
                    <td><code class="set-mono-xs"><?= e((string) $d['event']) ?></code></td>
                    <td><span class="set-td-strong" style="color: <?= $col ?>;"><?= e($st) ?></span></td>
                    <td class="set-td-meta"><?= $d['response_code'] ? (int) $d['response_code'] : '—' ?></td>
                    <td class="set-td-meta"><?= (int) $d['attempts'] ?></td>
                    <td class="set-td-meta set-td-right"><?= $d['latency_ms'] ? (int) $d['latency_ms'] . 'ms' : '—' ?></td>
                    <td class="set-td-actions">
                        <?php if (in_array($st, ['failed','dead','pending'], true)): ?>
                        <form action="<?= url('/settings/webhooks/deliveries/' . e((string) $d['uuid']) . '/replay') ?>" method="POST" style="display:inline;"><?= csrf_field() ?>
                            <button class="set-btn set-btn-ghost set-btn-sm">↻ Reintentar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
