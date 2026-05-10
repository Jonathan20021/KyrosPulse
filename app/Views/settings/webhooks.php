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
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Webhooks salientes',
    'subtitle' => 'Subscribe URLs externas a eventos del sistema (ordenes, agentes IA, contactos, mensajes). Firmas HMAC-SHA256 + retries automaticos con backoff.',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'webhooks']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php if ($newSecret): ?>
<!-- Banner one-shot del secret -->
<div class="mb-6 rounded-2xl border p-5" style="background: linear-gradient(135deg, rgba(16,185,129,.08), rgba(6,182,212,.05)); border-color: rgba(16,185,129,.35);">
    <div class="flex items-start gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl" style="background: rgba(16,185,129,.15);">🔐</div>
        <div class="flex-1 min-w-0">
            <div class="font-bold mb-0.5" style="color: var(--color-text-primary);">Tu signing secret — copialo AHORA</div>
            <p class="text-sm" style="color: var(--color-text-secondary);">Usa este secret para validar las firmas HMAC en tu servidor. Por seguridad no podremos mostrartelo de nuevo.</p>
        </div>
    </div>
    <div class="flex items-stretch gap-2">
        <input id="newSecretInput" readonly value="<?= e((string) $newSecret['secret']) ?>"
               class="flex-1 font-mono text-sm px-3 py-2.5 rounded-lg border"
               style="background: var(--color-bg-secondary); border-color: var(--color-border); color: var(--color-text-primary);">
        <button type="button" onclick="(()=>{const i=document.getElementById('newSecretInput');i.select();document.execCommand('copy');this.innerText='Copiado!';setTimeout(()=>this.innerText='Copiar',1500);})()"
                class="px-4 py-2.5 rounded-lg font-semibold text-white" style="background: linear-gradient(135deg,#10B981,#0EA572);">Copiar</button>
    </div>
    <p class="text-xs mt-3" style="color: var(--color-text-secondary);">
        Webhook: <strong><?= e((string) $newSecret['name']) ?></strong> · ID: <code><?= (int) $newSecret['id'] ?></code>
    </p>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php
    $totalDeliv = count($deliveries);
    $delivered  = count(array_filter($deliveries, fn($d) => $d['status'] === 'delivered'));
    $failed     = count(array_filter($deliveries, fn($d) => in_array($d['status'], ['failed','dead'], true)));
    foreach ([
        ['🪝', 'Endpoints activos', count($active),  '#10B981'],
        ['📡', 'Entregas (50 mas recientes)', $totalDeliv,  '#06B6D4'],
        ['✅', 'Exitosas',           $delivered,     '#10B981'],
        ['⚠',  'Fallidas',            $failed,        '#F43F5E'],
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

<!-- Doc HMAC -->
<div class="surface p-5 mb-5">
    <div class="font-bold mb-2" style="color: var(--color-text-primary);">Como verificar la firma HMAC en tu servidor</div>
    <p class="text-sm mb-3" style="color: var(--color-text-secondary);">
        Cada delivery viaja con header <code>X-Kyros-Signature: t=&lt;unix&gt;,v1=&lt;hex&gt;</code>.
        Recalcula <code>hash_hmac('sha256', t + '.' + body, $tu_secret)</code> y compara con <code>hash_equals()</code>.
        Rechaza si <code>|t - now| &gt; 300s</code> (defensa anti-replay).
    </p>
<pre class="text-xs font-mono px-3 py-2.5 rounded-lg overflow-x-auto" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">// Node.js
const crypto = require('crypto');
const sig = req.headers['x-kyros-signature']; // "t=1715300000,v1=abc..."
const [t, v1] = sig.split(',').map(p => p.split('=')[1]);
if (Math.abs(Date.now()/1000 - t) > 300) return res.status(400).send('stale');
const expected = crypto.createHmac('sha256', SECRET).update(t + '.' + req.rawBody).digest('hex');
if (!crypto.timingSafeEqual(Buffer.from(v1), Buffer.from(expected))) return res.status(401).send('bad signature');
// ok!</pre>
</div>

<!-- Form crear -->
<div x-data="{ open: false }" class="mb-6">
    <button @click="open = !open" type="button"
            class="px-4 py-2.5 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2"
            style="background: linear-gradient(135deg,#10B981,#0EA572);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Nuevo webhook saliente
    </button>

    <div x-show="open" x-cloak x-transition class="surface mt-4 p-5">
        <form action="<?= url('/settings/webhooks') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="label">Nombre / etiqueta</label>
                    <input name="name" required maxlength="120" placeholder="Mi backend de produccion" class="input">
                </div>
                <div>
                    <label class="label">URL del endpoint</label>
                    <input name="url" required type="url" placeholder="https://midominio.com/webhooks/kyros" class="input">
                </div>
            </div>
            <div>
                <label class="label">Descripcion (opcional)</label>
                <input name="description" maxlength="500" placeholder="Sincroniza ordenes con nuestro ERP" class="input">
            </div>
            <div>
                <label class="label">Eventos a suscribir</label>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                    <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer hover:bg-black/5" style="border-color: var(--color-border);">
                        <input type="checkbox" name="events[]" value="*">
                        <span class="text-sm font-mono font-semibold" style="color: var(--color-text-primary);">* (todos)</span>
                    </label>
                    <?php foreach ($events as $slug => $label): ?>
                    <label class="flex items-start gap-2 px-3 py-2 rounded-lg border cursor-pointer hover:bg-black/5" style="border-color: var(--color-border);">
                        <input type="checkbox" name="events[]" value="<?= e($slug) ?>">
                        <div class="min-w-0">
                            <div class="text-sm font-mono" style="color: var(--color-text-primary);"><?= e($slug) ?></div>
                            <div class="text-xs" style="color: var(--color-text-secondary);"><?= e($label) ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="px-4 py-2 rounded-lg text-sm font-semibold border" style="border-color: var(--color-border); color: var(--color-text-primary);">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#10B981,#0EA572);">Crear webhook</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla endpoints -->
<div class="surface mb-5 overflow-hidden">
    <div class="px-5 py-3.5 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Endpoints suscritos <span class="ml-2 text-xs font-normal" style="color: var(--color-text-tertiary);">(<?= count($endpoints) ?>)</span></h3>
    </div>
    <?php if (empty($endpoints)): ?>
    <div class="p-10 text-center text-sm" style="color: var(--color-text-secondary);">Aun no tienes webhooks configurados.</div>
    <?php else: ?>
    <ul class="divide-y" style="border-color: var(--color-border);">
        <?php foreach ($endpoints as $e):
            $evList = $e['events'] ? json_decode((string) $e['events'], true) : ['*'];
            if (!is_array($evList)) $evList = ['*'];
        ?>
        <li class="px-5 py-4 <?= empty($e['is_active']) ? 'opacity-60' : '' ?>">
            <div class="flex items-start gap-3 mb-2">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-base flex-shrink-0"
                     style="background: <?= !empty($e['is_active']) ? 'rgba(16,185,129,.12)' : 'rgba(148,163,184,.12)' ?>; color: <?= !empty($e['is_active']) ? '#10B981' : '#64748B' ?>;">🪝</div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-0.5">
                        <span class="font-semibold" style="color: var(--color-text-primary);"><?= e((string) $e['name']) ?></span>
                        <?php if (empty($e['is_active'])): ?>
                            <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(148,163,184,.15); color: #475569;">Pausado</span>
                        <?php endif; ?>
                    </div>
                    <code class="text-xs break-all" style="color: var(--color-text-secondary);"><?= e((string) $e['url']) ?></code>
                    <?php if (!empty($e['description'])): ?>
                    <p class="text-xs mt-1" style="color: var(--color-text-tertiary);"><?= e((string) $e['description']) ?></p>
                    <?php endif; ?>
                    <div class="mt-1.5 flex flex-wrap gap-1">
                        <?php foreach (array_slice($evList, 0, 6) as $ev): ?>
                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded" style="background: var(--color-bg-secondary); color: var(--color-text-secondary);"><?= e((string) $ev) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($evList) > 6): ?><span class="text-[10px]" style="color: var(--color-text-tertiary);">+<?= count($evList) - 6 ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="flex-shrink-0 flex items-center gap-1.5">
                    <form action="<?= url('/settings/webhooks/' . (int) $e['id'] . '/toggle') ?>" method="POST" class="inline"><?= csrf_field() ?>
                        <button class="px-2 py-1 rounded text-xs font-semibold" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">
                            <?= !empty($e['is_active']) ? '⏸' : '▶' ?>
                        </button>
                    </form>
                    <form action="<?= url('/settings/webhooks/' . (int) $e['id'] . '/rotate') ?>" method="POST" class="inline" onsubmit="return confirm('Rotar el signing secret? Tendras que actualizarlo en tu servidor.')"><?= csrf_field() ?>
                        <button class="px-2 py-1 rounded text-xs font-semibold" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">🔁 Rotar</button>
                    </form>
                    <form action="<?= url('/settings/webhooks/' . (int) $e['id'] . '/delete') ?>" method="POST" class="inline" onsubmit="return confirm('Eliminar este webhook?')"><?= csrf_field() ?>
                        <button class="px-2 py-1 rounded text-xs font-semibold border" style="color:#BE123C; border-color: rgba(244,63,94,.3); background: rgba(244,63,94,.05);">Eliminar</button>
                    </form>
                </div>
            </div>
            <div class="text-[11px] mt-2 flex items-center gap-3 flex-wrap" style="color: var(--color-text-tertiary);">
                <span>✅ <strong><?= number_format((int) $e['success_count']) ?></strong> ok</span>
                <span>⚠ <strong><?= number_format((int) $e['failure_count']) ?></strong> fallos</span>
                <?php if (!empty($e['last_delivery_at'])): ?>
                <span>· Ultima entrega: <?= e((string) $e['last_delivery_at']) ?></span>
                <?php endif; ?>
                <?php if (!empty($e['last_error'])): ?>
                <span style="color: #BE123C;">· Error: <?= e(mb_substr((string) $e['last_error'], 0, 80)) ?></span>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<!-- Deliveries log -->
<div class="surface overflow-hidden">
    <div class="px-5 py-3.5 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Ultimas 50 entregas</h3>
        <p class="text-xs mt-0.5" style="color: var(--color-text-secondary);">Audit log de cada delivery con status, status code, attempts y latencia. Click en "Reintentar" para los que fallaron.</p>
    </div>
    <?php if (empty($deliveries)): ?>
    <div class="p-8 text-center text-sm" style="color: var(--color-text-secondary);">Aun no hay entregas.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary); background: var(--color-bg-secondary);">
                    <th class="text-left px-4 py-2.5">Cuando</th>
                    <th class="text-left px-4 py-2.5">Endpoint</th>
                    <th class="text-left px-4 py-2.5">Evento</th>
                    <th class="text-left px-4 py-2.5">Status</th>
                    <th class="text-left px-4 py-2.5">Code</th>
                    <th class="text-left px-4 py-2.5">Try</th>
                    <th class="text-right px-4 py-2.5">Latencia</th>
                    <th class="text-right px-4 py-2.5"></th>
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
                <tr class="border-t" style="border-color: var(--color-border);">
                    <td class="px-4 py-2.5 text-xs whitespace-nowrap" style="color: var(--color-text-secondary);"><?= e((string) $d['created_at']) ?></td>
                    <td class="px-4 py-2.5 text-xs"><?= e((string) ($d['endpoint_name'] ?? '—')) ?></td>
                    <td class="px-4 py-2.5"><code class="text-[11px]"><?= e((string) $d['event']) ?></code></td>
                    <td class="px-4 py-2.5"><span class="font-semibold" style="color: <?= $col ?>;"><?= e($st) ?></span></td>
                    <td class="px-4 py-2.5 text-xs"><?= $d['response_code'] ? (int) $d['response_code'] : '—' ?></td>
                    <td class="px-4 py-2.5 text-xs"><?= (int) $d['attempts'] ?></td>
                    <td class="px-4 py-2.5 text-right text-xs" style="color: var(--color-text-secondary);"><?= $d['latency_ms'] ? (int) $d['latency_ms'] . 'ms' : '—' ?></td>
                    <td class="px-4 py-2.5 text-right">
                        <?php if (in_array($st, ['failed','dead','pending'], true)): ?>
                        <form action="<?= url('/settings/webhooks/deliveries/' . e((string) $d['uuid']) . '/replay') ?>" method="POST" class="inline"><?= csrf_field() ?>
                            <button class="px-2 py-1 rounded text-xs font-semibold" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">↻ Reintentar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php \App\Core\View::end(); ?>
