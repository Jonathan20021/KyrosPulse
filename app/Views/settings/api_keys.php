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
<?php \App\Core\View::include('settings._tabs', ['tab' => 'api']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Plataforma',
    'title'    => 'API & Webhooks',
    'subtitle' => 'Genera API keys para integrar Kyros Pulse con tus sistemas. Ejecuta agentes IA, gestiona contactos y ordenes, envia mensajes WhatsApp por codigo.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php if ($newKey): ?>
<div class="set-secret-banner">
    <div class="set-secret-head">
        <div class="set-secret-icon">🔐</div>
        <div>
            <div class="set-secret-title">Tu nueva API key — copiala AHORA</div>
            <p class="set-secret-desc">Por seguridad no podremos mostrartela de nuevo. Si la pierdes, deberas generar una nueva.</p>
        </div>
    </div>
    <div class="set-secret-row">
        <input id="newKeyInput" readonly value="<?= e((string) $newKey['plain_key']) ?>" class="set-input set-mono">
        <button type="button" onclick="(()=>{const i=document.getElementById('newKeyInput');i.select();document.execCommand('copy');this.innerText='Copiado!';setTimeout(()=>this.innerText='Copiar',1500);})()"
                class="set-btn set-btn-success">Copiar</button>
    </div>
    <p class="set-help">Nombre: <strong><?= e((string) $newKey['name']) ?></strong> · ID: <code><?= (int) $newKey['id'] ?></code></p>
</div>
<?php endif; ?>

<?php
$q = $quota ?? [];
$quotaVal = (int) ($q['quota'] ?? 0);
$used     = (int) ($q['used'] ?? 0);
$pct      = (int) ($q['used_pct'] ?? 0);
$barColor = $pct >= 95 ? '#BE123C' : ($pct >= 80 ? '#F59E0B' : '#10B981');
?>
<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>📊</span> Cuota mensual del plan</h2>
        </div>
        <span class="set-badge set-badge-mono">plan: <?= e((string) ($q['plan_name'] ?? '—')) ?></span>
    </div>
    <div class="set-quota">
        <div class="set-quota-head">
            <span class="set-quota-value">
                <?php if (!empty($q['unlimited'])): ?>
                    Ilimitado
                <?php elseif (!empty($q['no_access'])): ?>
                    Sin acceso al API
                <?php else: ?>
                    <?= number_format($used) ?> / <?= number_format($quotaVal) ?>
                <?php endif; ?>
            </span>
            <?php if (!empty($q['is_overridden'])): ?>
            <span class="set-badge" style="background: rgba(139,92,246,.12); color: #8B5CF6;">override admin</span>
            <?php endif; ?>
        </div>
        <p class="set-help">
            <?php if (!empty($q['unlimited'])): ?>
                Tu plan no tiene limite de requests.
            <?php elseif (!empty($q['no_access'])): ?>
                Actualiza a un plan con API access para activar tus keys.
            <?php else: ?>
                Te quedan <strong><?= number_format((int) ($q['remaining'] ?? 0)) ?></strong> requests · renueva el <strong><?= e(date('d M', strtotime((string) ($q['period_resets_at'] ?? 'now')))) ?></strong>
            <?php endif; ?>
        </p>
        <?php if (!empty($q['quota']) && $quotaVal > 0 && empty($q['unlimited'])): ?>
        <div class="set-progress">
            <div class="set-progress-bar" style="width: <?= $pct ?>%; background: <?= $barColor ?>;"></div>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="set-kpi-grid">
    <?php foreach ([
        ['🔑', 'Keys activas',     count($active),         '#10B981'],
        ['📡', 'Requests 7d',      (int) $stats['total'],  '#06B6D4'],
        ['✅', 'Exitosos',         (int) $stats['ok'],     '#10B981'],
        ['⚠',  'Errores',          (int) $stats['errors'], '#F43F5E'],
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
        <div>
            <h2 class="set-section-title"><span>🚀</span> Empieza en 30 segundos</h2>
            <p class="set-section-desc">Genera una key abajo y haz tu primera llamada.</p>
        </div>
        <div class="set-inline-actions">
            <a href="<?= e($baseUrl . '/openapi') ?>" target="_blank" rel="noopener" class="set-btn set-btn-ghost set-btn-sm">OpenAPI 3.0</a>
            <a href="<?= e($baseUrl . '/status') ?>" target="_blank" rel="noopener" class="set-btn set-btn-ghost set-btn-sm">Status</a>
        </div>
    </div>
    <div class="set-field">
        <label class="set-label">Base URL</label>
        <code class="set-code-block"><?= e($baseUrl) ?></code>
    </div>
    <div class="set-field">
        <label class="set-label">Ejemplo curl: ejecutar agente IA</label>
<pre class="set-code-block">curl -X POST <?= e($baseUrl) ?>/agents/1/run \
  -H "Authorization: Bearer kp_live_..." \
  -H "Content-Type: application/json" \
  -d '{"input": "Quiero pedir 2 pizzas margarita"}'</pre>
    </div>
</section>

<div x-data="{ open: false, scopeAll: true }" class="set-actions-bar">
    <button @click="open = !open" type="button" class="set-btn set-btn-success">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Generar nueva API key
    </button>

    <section x-show="open" x-cloak x-transition class="set-section" style="margin-top: 16px;">
        <form action="<?= url('/settings/api-keys') ?>" method="POST">
            <?= csrf_field() ?>

            <div class="set-field-row cols-2 set-field">
                <div>
                    <label class="set-label">Nombre / etiqueta</label>
                    <input name="name" required maxlength="120" placeholder="Ej: Servidor de produccion" class="set-input">
                </div>
                <div>
                    <label class="set-label">Caduca el (opcional)</label>
                    <input name="expires_at" type="datetime-local" class="set-input">
                </div>
            </div>

            <div class="set-field">
                <label class="set-label" style="display:flex; align-items:center; justify-content:space-between;">
                    <span>Scopes (permisos)</span>
                    <label class="set-inline-check">
                        <input type="checkbox" x-model="scopeAll">
                        Acceso total (admin)
                    </label>
                </label>
                <template x-if="scopeAll">
                    <div>
                        <input type="hidden" name="scopes[]" value="*">
                        <div class="set-flash set-flash-warning" style="margin: 0;">
                            ⚠ Esta key tendra acceso completo. Para mayor seguridad, desmarca y selecciona scopes especificos.
                        </div>
                    </div>
                </template>
                <template x-if="!scopeAll">
                    <div class="set-scope-grid">
                        <?php foreach ($scopes as $slug => $desc): if ($slug === '*') continue; ?>
                        <label class="set-check">
                            <input type="checkbox" name="scopes[]" value="<?= e($slug) ?>">
                            <div class="set-check-body">
                                <div class="set-check-title set-mono"><?= e($slug) ?></div>
                                <div class="set-check-desc"><?= e($desc) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </template>
            </div>

            <div class="set-field">
                <label class="set-label">IPs permitidas (opcional, separadas por coma)</label>
                <input name="allowed_ips" placeholder="192.168.1.10, 10.0.0.0/24" class="set-input">
                <p class="set-help">Soporta IPs y rangos CIDR. Si lo dejas vacio, la key acepta cualquier origen.</p>
            </div>

            <div class="set-actions">
                <button type="button" @click="open = false" class="set-btn set-btn-ghost">Cancelar</button>
                <button type="submit" class="set-btn set-btn-success">Generar key</button>
            </div>
        </form>
    </section>
</div>

<section class="set-section">
    <div class="set-section-head">
        <h2 class="set-section-title"><span>🔑</span> Keys activas <span class="set-count">(<?= count($active) ?>)</span></h2>
    </div>
    <?php if (empty($active)): ?>
    <div class="set-empty">
        <div class="set-empty-icon">🗝️</div>
        <p class="set-empty-text">Aun no tienes API keys. Genera la primera arriba.</p>
    </div>
    <?php else: ?>
    <div class="set-table-wrap">
        <table class="set-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Prefijo</th>
                    <th>Scopes</th>
                    <th>Ultimo uso</th>
                    <th>Creada</th>
                    <th>Caduca</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active as $k):
                    $sc = $k['scopes'] ? json_decode((string) $k['scopes'], true) : [];
                    if (!is_array($sc)) $sc = [];
                ?>
                <tr>
                    <td class="set-td-strong"><?= e((string) $k['name']) ?></td>
                    <td><code class="set-mono-xs"><?= e((string) $k['prefix']) ?>...<?= e((string) $k['last4']) ?></code></td>
                    <td>
                        <?php foreach (array_slice($sc, 0, 4) as $s): ?>
                            <span class="set-badge set-badge-mono"><?= e((string) $s) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($sc) > 4): ?>
                            <span class="set-td-muted">+<?= count($sc) - 4 ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="set-td-meta"><?= $k['last_used_at'] ? e((string) $k['last_used_at']) : '<span class="set-td-muted">nunca</span>' ?></td>
                    <td class="set-td-meta"><?= e((string) $k['created_at']) ?></td>
                    <td class="set-td-meta"><?= $k['expires_at'] ? e((string) $k['expires_at']) : '—' ?></td>
                    <td class="set-td-actions">
                        <form action="<?= url('/settings/api-keys/' . (int) $k['id'] . '/revoke') ?>" method="POST" style="display:inline;"
                              onsubmit="return confirm('Revocar esta API key? El integrador dejara de funcionar inmediatamente.');">
                            <?= csrf_field() ?>
                            <button class="set-btn set-btn-danger set-btn-sm">Revocar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>📋</span> Ultimas 50 requests</h2>
            <p class="set-section-desc">Auditoria por API key, endpoint, status y latencia.</p>
        </div>
    </div>
    <?php if (empty($logs)): ?>
    <div class="set-empty">
        <div class="set-empty-icon">📭</div>
        <p class="set-empty-text">Aun no hay requests al API.</p>
    </div>
    <?php else: ?>
    <div class="set-table-wrap">
        <table class="set-table">
            <thead>
                <tr>
                    <th>Cuando</th>
                    <th>Key</th>
                    <th>Metodo</th>
                    <th>Endpoint</th>
                    <th>Status</th>
                    <th class="set-td-right">Latencia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l):
                    $st = (int) $l['status_code'];
                    $col = $st >= 500 ? '#BE123C' : ($st >= 400 ? '#B45309' : ($st >= 200 ? '#0B7C56' : '#64748B'));
                ?>
                <tr>
                    <td class="set-td-meta"><?= e((string) $l['created_at']) ?></td>
                    <td class="set-td-meta"><?= e((string) ($l['key_name'] ?? '—')) ?></td>
                    <td><span class="set-badge set-badge-mono"><?= e((string) $l['method']) ?></span></td>
                    <td class="set-mono-xs"><?= e((string) $l['endpoint']) ?></td>
                    <td><span class="set-td-strong" style="color: <?= $col ?>;"><?= $st ?></span></td>
                    <td class="set-td-meta set-td-right"><?= (int) $l['latency_ms'] ?>ms</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
