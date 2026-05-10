<?php
/**
 * @var array      $keys
 * @var array      $logs
 * @var array      $stats
 * @var array      $scopes
 * @var array|null $newKey
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$active = array_filter($keys, fn($k) => empty($k['revoked_at']));
$revoked = array_filter($keys, fn($k) => !empty($k['revoked_at']));
$baseUrl = url('/api/v1');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'API & Webhooks',
    'subtitle' => 'Genera API keys para integrar Kyros Pulse con tus sistemas. Ejecuta agentes IA, gestiona contactos y ordenes, envia mensajes WhatsApp por codigo.',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'api']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php if ($newKey): ?>
<!-- Banner one-shot del secreto -->
<div class="mb-6 rounded-2xl border p-5" style="background: linear-gradient(135deg, rgba(16,185,129,.08), rgba(6,182,212,.05)); border-color: rgba(16,185,129,.35);">
    <div class="flex items-start gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl" style="background: rgba(16,185,129,.15);">🔐</div>
        <div class="flex-1 min-w-0">
            <div class="font-bold mb-0.5" style="color: var(--color-text-primary);">Tu nueva API key — copiala AHORA</div>
            <p class="text-sm" style="color: var(--color-text-secondary);">Por seguridad no podremos mostrartela de nuevo. Si la pierdes, deberas generar una nueva.</p>
        </div>
    </div>
    <div class="flex items-stretch gap-2">
        <input id="newKeyInput" readonly value="<?= e((string) $newKey['plain_key']) ?>"
               class="flex-1 font-mono text-sm px-3 py-2.5 rounded-lg border"
               style="background: var(--color-bg-secondary); border-color: var(--color-border); color: var(--color-text-primary);">
        <button type="button" onclick="(()=>{const i=document.getElementById('newKeyInput');i.select();document.execCommand('copy');this.innerText='Copiado!';setTimeout(()=>this.innerText='Copiar',1500);})()"
                class="px-4 py-2.5 rounded-lg font-semibold text-white" style="background: linear-gradient(135deg,#10B981,#0EA572);">Copiar</button>
    </div>
    <p class="text-xs mt-3" style="color: var(--color-text-secondary);">
        Nombre: <strong><?= e((string) $newKey['name']) ?></strong> · ID: <code><?= (int) $newKey['id'] ?></code>
    </p>
</div>
<?php endif; ?>

<!-- Quota del plan -->
<?php
$q = $quota ?? [];
$quotaVal = (int) ($q['quota'] ?? 0);
$used     = (int) ($q['used'] ?? 0);
$pct      = (int) ($q['used_pct'] ?? 0);
$barColor = $pct >= 95 ? '#BE123C' : ($pct >= 80 ? '#F59E0B' : '#10B981');
?>
<div class="surface p-4 mb-5">
    <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
        <div class="min-w-0">
            <div class="text-[10px] uppercase tracking-wider font-semibold mb-0.5" style="color: var(--color-text-tertiary);">Cuota mensual del plan</div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-lg" style="color: var(--color-text-primary);">
                    <?php if (!empty($q['unlimited'])): ?>
                        Ilimitado
                    <?php elseif (!empty($q['no_access'])): ?>
                        Sin acceso al API
                    <?php else: ?>
                        <?= number_format($used) ?> / <?= number_format($quotaVal) ?>
                    <?php endif; ?>
                </span>
                <span class="text-xs px-2 py-0.5 rounded font-mono" style="background: var(--color-bg-secondary); color: var(--color-text-secondary);">
                    plan: <?= e((string) ($q['plan_name'] ?? '—')) ?>
                </span>
                <?php if (!empty($q['is_overridden'])): ?>
                <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: rgba(139,92,246,.12); color: #8B5CF6;">override admin</span>
                <?php endif; ?>
            </div>
            <p class="text-xs mt-1" style="color: var(--color-text-secondary);">
                <?php if (!empty($q['unlimited'])): ?>
                    Tu plan no tiene limite de requests.
                <?php elseif (!empty($q['no_access'])): ?>
                    Actualiza a un plan con API access para activar tus keys.
                <?php else: ?>
                    Te quedan <strong><?= number_format((int) ($q['remaining'] ?? 0)) ?></strong> requests · renueva el <strong><?= e(date('d M', strtotime((string) ($q['period_resets_at'] ?? 'now')))) ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php if (!empty($q['quota']) && $quotaVal > 0 && empty($q['unlimited'])): ?>
    <div class="h-2 rounded-full overflow-hidden" style="background: var(--color-bg-secondary);">
        <div class="h-full transition-all duration-500" style="width: <?= $pct ?>%; background: <?= $barColor ?>;"></div>
    </div>
    <?php endif; ?>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php foreach ([
        ['🔑', 'Keys activas',     count($active),         '#10B981'],
        ['📡', 'Requests 7d',      (int) $stats['total'],  '#06B6D4'],
        ['✅', 'Exitosos',         (int) $stats['ok'],     '#10B981'],
        ['⚠',  'Errores',          (int) $stats['errors'], '#F43F5E'],
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

<!-- Doc + base URL -->
<div class="surface p-5 mb-5">
    <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
        <div class="min-w-0">
            <div class="font-bold mb-0.5" style="color: var(--color-text-primary);">Empieza en 30 segundos</div>
            <p class="text-sm" style="color: var(--color-text-secondary);">Genera una key abajo y haz tu primera llamada.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= e($baseUrl . '/openapi') ?>" target="_blank" rel="noopener"
               class="px-3 py-1.5 rounded-lg text-sm font-semibold border" style="border-color: var(--color-border); color: var(--color-text-primary);">OpenAPI 3.0</a>
            <a href="<?= e($baseUrl . '/status') ?>" target="_blank" rel="noopener"
               class="px-3 py-1.5 rounded-lg text-sm font-semibold border" style="border-color: var(--color-border); color: var(--color-text-primary);">Status</a>
        </div>
    </div>
    <div class="space-y-3">
        <div>
            <div class="text-xs uppercase tracking-wider font-semibold mb-1.5" style="color: var(--color-text-tertiary);">Base URL</div>
            <code class="text-sm font-mono px-3 py-2 rounded-lg block" style="background: var(--color-bg-secondary); color: var(--color-text-primary);"><?= e($baseUrl) ?></code>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wider font-semibold mb-1.5" style="color: var(--color-text-tertiary);">Ejemplo curl: ejecutar agente IA</div>
<pre class="text-xs font-mono px-3 py-2.5 rounded-lg overflow-x-auto" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">curl -X POST <?= e($baseUrl) ?>/agents/1/run \
  -H "Authorization: Bearer kp_live_..." \
  -H "Content-Type: application/json" \
  -d '{"input": "Quiero pedir 2 pizzas margarita"}'</pre>
        </div>
    </div>
</div>

<!-- Form crear -->
<div x-data="{ open: false, scopeAll: true }" class="mb-6">
    <button @click="open = !open" type="button"
            class="px-4 py-2.5 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2"
            style="background: linear-gradient(135deg,#10B981,#0EA572);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Generar nueva API key
    </button>

    <div x-show="open" x-cloak x-transition class="surface mt-4 p-5">
        <form action="<?= url('/settings/api-keys') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="label">Nombre / etiqueta</label>
                    <input name="name" required maxlength="120"
                           placeholder="Ej: Servidor de produccion"
                           class="input">
                </div>
                <div>
                    <label class="label">Caduca el (opcional)</label>
                    <input name="expires_at" type="datetime-local" class="input">
                </div>
            </div>

            <div>
                <label class="label flex items-center justify-between">
                    <span>Scopes (permisos)</span>
                    <label class="flex items-center gap-1.5 text-xs font-normal cursor-pointer">
                        <input type="checkbox" x-model="scopeAll" class="rounded">
                        Acceso total (admin)
                    </label>
                </label>
                <template x-if="scopeAll">
                    <div>
                        <input type="hidden" name="scopes[]" value="*">
                        <div class="text-xs px-3 py-2 rounded-lg" style="background: rgba(245,158,11,.08); color: #B45309;">
                            ⚠ Esta key tendra acceso completo. Para mayor seguridad, desmarca y selecciona scopes especificos.
                        </div>
                    </div>
                </template>
                <template x-if="!scopeAll">
                    <div class="grid sm:grid-cols-2 gap-1.5">
                        <?php foreach ($scopes as $slug => $desc): if ($slug === '*') continue; ?>
                        <label class="flex items-start gap-2 px-3 py-2 rounded-lg border cursor-pointer hover:bg-black/5" style="border-color: var(--color-border);">
                            <input type="checkbox" name="scopes[]" value="<?= e($slug) ?>" class="mt-0.5">
                            <div class="min-w-0">
                                <div class="text-sm font-mono font-semibold" style="color: var(--color-text-primary);"><?= e($slug) ?></div>
                                <div class="text-xs" style="color: var(--color-text-secondary);"><?= e($desc) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </template>
            </div>

            <div>
                <label class="label">IPs permitidas (opcional, separadas por coma)</label>
                <input name="allowed_ips" placeholder="192.168.1.10, 10.0.0.0/24" class="input">
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">Soporta IPs y rangos CIDR. Si lo dejas vacio, la key acepta cualquier origen.</p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="px-4 py-2 rounded-lg text-sm font-semibold border" style="border-color: var(--color-border); color: var(--color-text-primary);">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#10B981,#0EA572);">Generar key</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla keys activas -->
<div class="surface mb-5 overflow-hidden">
    <div class="px-5 py-3.5 border-b flex items-center justify-between" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Keys activas <span class="ml-2 text-xs font-normal" style="color: var(--color-text-tertiary);">(<?= count($active) ?>)</span></h3>
    </div>
    <?php if (empty($active)): ?>
    <div class="p-10 text-center text-sm" style="color: var(--color-text-secondary);">
        Aun no tienes API keys. Genera la primera arriba.
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary); background: var(--color-bg-secondary);">
                    <th class="text-left px-4 py-2.5">Nombre</th>
                    <th class="text-left px-4 py-2.5">Prefijo</th>
                    <th class="text-left px-4 py-2.5">Scopes</th>
                    <th class="text-left px-4 py-2.5">Ultimo uso</th>
                    <th class="text-left px-4 py-2.5">Creada</th>
                    <th class="text-left px-4 py-2.5">Caduca</th>
                    <th class="text-right px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active as $k):
                    $sc = $k['scopes'] ? json_decode((string) $k['scopes'], true) : [];
                    if (!is_array($sc)) $sc = [];
                ?>
                <tr class="border-t" style="border-color: var(--color-border);">
                    <td class="px-4 py-3 font-semibold" style="color: var(--color-text-primary);"><?= e((string) $k['name']) ?></td>
                    <td class="px-4 py-3"><code class="text-xs"><?= e((string) $k['prefix']) ?>...<?= e((string) $k['last4']) ?></code></td>
                    <td class="px-4 py-3">
                        <?php foreach (array_slice($sc, 0, 4) as $s): ?>
                            <span class="inline-block text-[10px] font-mono px-1.5 py-0.5 rounded mr-1 mb-0.5" style="background: var(--color-bg-secondary);"><?= e((string) $s) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($sc) > 4): ?>
                            <span class="text-[10px]" style="color: var(--color-text-tertiary);">+<?= count($sc) - 4 ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs" style="color: var(--color-text-secondary);"><?= $k['last_used_at'] ? e((string) $k['last_used_at']) : '<span style="color:var(--color-text-tertiary)">nunca</span>' ?></td>
                    <td class="px-4 py-3 text-xs" style="color: var(--color-text-secondary);"><?= e((string) $k['created_at']) ?></td>
                    <td class="px-4 py-3 text-xs" style="color: var(--color-text-secondary);"><?= $k['expires_at'] ? e((string) $k['expires_at']) : '—' ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <form action="<?= url('/settings/api-keys/' . (int) $k['id'] . '/revoke') ?>" method="POST" class="inline"
                              onsubmit="return confirm('Revocar esta API key? El integrador dejara de funcionar inmediatamente.');">
                            <?= csrf_field() ?>
                            <button class="px-3 py-1.5 rounded-lg text-xs font-semibold border" style="color:#BE123C; border-color: rgba(244,63,94,.3); background: rgba(244,63,94,.05);">Revocar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Logs recientes -->
<div class="surface overflow-hidden">
    <div class="px-5 py-3.5 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Ultimas 50 requests</h3>
        <p class="text-xs mt-0.5" style="color: var(--color-text-secondary);">Auditoria por API key, endpoint, status y latencia.</p>
    </div>
    <?php if (empty($logs)): ?>
    <div class="p-8 text-center text-sm" style="color: var(--color-text-secondary);">Aun no hay requests al API.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary); background: var(--color-bg-secondary);">
                    <th class="text-left px-4 py-2.5">Cuando</th>
                    <th class="text-left px-4 py-2.5">Key</th>
                    <th class="text-left px-4 py-2.5">Metodo</th>
                    <th class="text-left px-4 py-2.5">Endpoint</th>
                    <th class="text-left px-4 py-2.5">Status</th>
                    <th class="text-right px-4 py-2.5">Latencia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l):
                    $st = (int) $l['status_code'];
                    $col = $st >= 500 ? '#BE123C' : ($st >= 400 ? '#B45309' : ($st >= 200 ? '#0B7C56' : '#64748B'));
                ?>
                <tr class="border-t" style="border-color: var(--color-border);">
                    <td class="px-4 py-2.5 text-xs whitespace-nowrap" style="color: var(--color-text-secondary);"><?= e((string) $l['created_at']) ?></td>
                    <td class="px-4 py-2.5 text-xs" style="color: var(--color-text-secondary);"><?= e((string) ($l['key_name'] ?? '—')) ?></td>
                    <td class="px-4 py-2.5"><span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded" style="background: var(--color-bg-secondary);"><?= e((string) $l['method']) ?></span></td>
                    <td class="px-4 py-2.5 font-mono text-xs"><?= e((string) $l['endpoint']) ?></td>
                    <td class="px-4 py-2.5"><span class="font-semibold" style="color: <?= $col ?>;"><?= $st ?></span></td>
                    <td class="px-4 py-2.5 text-right text-xs" style="color: var(--color-text-secondary);"><?= (int) $l['latency_ms'] ?>ms</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php \App\Core\View::end(); ?>
