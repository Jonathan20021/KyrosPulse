<?php
/**
 * @var array $rules
 * @var array $history
 * @var int   $destCount
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$severityColor = [
    'info'     => '#06B6D4',
    'warning'  => '#F59E0B',
    'critical' => '#BE123C',
];
$ruleTypeIcon = [
    'api.quota.threshold' => '🔋',
    'webhook.dead.count'  => '🪝',
    'agent.error_rate'    => '🧠',
    'security.critical'   => '🚨',
    'workflow.failed'     => '🪄',
];
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'alerts']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Plataforma',
    'title'    => 'Alertas inteligentes',
    'subtitle' => 'Te avisamos cuando algo importante pasa: cuota API, webhooks muertos, errores de IA, eventos de seguridad. Reusa tus notification destinations.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php if ($destCount === 0): ?>
<div class="set-notice set-notice-warning">
    <div class="set-notice-icon">⚠</div>
    <div class="set-notice-body">
        <div class="set-notice-title">No tienes destinations configurados</div>
        <p class="set-notice-desc">Las alertas se registran en el history pero <strong>no llegan a ningun lado</strong>. Configura email, Slack, Discord o Telegram para recibirlas.</p>
    </div>
    <a href="<?= url('/settings/notifications?prefill=email') ?>" class="set-btn set-btn-primary set-btn-sm">Configurar →</a>
</div>
<?php else: ?>
<div class="set-notice set-notice-success">
    <div class="set-notice-icon">✓</div>
    <div class="set-notice-body">
        <p class="set-notice-desc"><?= $destCount ?> destination(s) recibiran las alertas activas.</p>
    </div>
    <a href="<?= url('/settings/notifications') ?>" class="set-link">Gestionar destinations</a>
</div>
<?php endif; ?>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>🔔</span> Reglas configuradas <span class="set-count">(<?= count($rules) ?>)</span></h2>
        </div>
        <form action="<?= url('/settings/alerts/test') ?>" method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <button class="set-btn set-btn-ghost set-btn-sm">Probar alerta de cuota</button>
        </form>
    </div>

    <ul class="set-rule-list">
        <?php foreach ($rules as $r):
            $isActive = !empty($r['is_active']);
            $sev = (string) ($r['severity'] ?? 'warning');
            $sevCol = $severityColor[$sev] ?? '#64748B';
            $ruleType = (string) $r['rule_type'];
            $icon = $ruleTypeIcon[$ruleType] ?? '🔔';
            $isBuiltin = empty($r['tenant_id']);
            $lastTrig = $r['last_triggered_at'] ?? null;
            $triggerCount = (int) ($r['trigger_count'] ?? 0);
        ?>
        <li class="set-rule-item <?= $isActive ? '' : 'is-paused' ?>">
            <div class="set-rule-icon" style="background: <?= $sevCol ?>22; color: <?= $sevCol ?>;"><?= $icon ?></div>
            <div class="set-rule-body">
                <div class="set-rule-head">
                    <span class="set-rule-name"><?= e((string) $r['name']) ?></span>
                    <span class="set-badge" style="background: <?= $sevCol ?>22; color: <?= $sevCol ?>;"><?= e($sev) ?></span>
                    <code class="set-badge set-badge-mono"><?= e((string) $r['slug']) ?></code>
                    <?php if ($isBuiltin): ?>
                    <span class="set-badge" style="background: rgba(99,102,241,.12); color:#6366F1;">Builtin</span>
                    <?php else: ?>
                    <span class="set-badge" style="background: rgba(16,185,129,.1); color:#059669;">Custom</span>
                    <?php endif; ?>
                    <?php if (!$isActive): ?>
                    <span class="set-badge" style="background: rgba(148,163,184,.15); color:#475569;">Pausada</span>
                    <?php endif; ?>
                </div>
                <p class="set-rule-desc"><?= e((string) ($r['description'] ?? '')) ?></p>
                <div class="set-rule-meta">
                    <span>Cooldown: <strong><?= (int) ($r['cooldown_minutes'] ?? 60) ?> min</strong></span>
                    <span class="set-sep">·</span>
                    <span>Disparos: <strong><?= $triggerCount ?></strong></span>
                    <?php if ($lastTrig): ?>
                    <span class="set-sep">·</span>
                    <span>Ultima: <?= e((string) $lastTrig) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="set-rule-actions">
                <form action="<?= url('/settings/alerts/' . e((string) $r['slug']) . '/cooldown') ?>" method="POST" class="set-inline-form">
                    <?= csrf_field() ?>
                    <input type="number" name="minutes" value="<?= (int) ($r['cooldown_minutes'] ?? 60) ?>" min="5" max="10080" class="set-input set-input-xs">
                    <button class="set-btn set-btn-ghost set-btn-sm" title="Guardar cooldown">↑</button>
                </form>
                <form action="<?= url('/settings/alerts/' . e((string) $r['slug']) . '/toggle') ?>" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <?php if ($isActive): ?>
                    <button class="set-btn set-btn-ghost set-btn-sm">⏸ Pausar</button>
                    <?php else: ?>
                    <input type="hidden" name="active" value="1">
                    <button class="set-btn set-btn-success set-btn-sm">▶ Activar</button>
                    <?php endif; ?>
                </form>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>📜</span> Historial reciente <span class="set-count">(<?= count($history) ?>)</span></h2>
            <p class="set-section-desc">Cada alerta disparada queda registrada aqui, incluso si no hay destinations para recibirla.</p>
        </div>
    </div>

    <?php if (empty($history)): ?>
    <div class="set-empty">
        <div class="set-empty-icon">📭</div>
        <p class="set-empty-text">Sin alertas disparadas aun.</p>
    </div>
    <?php else: ?>
    <ul class="set-history-list">
        <?php foreach ($history as $h):
            $sev = (string) ($h['severity'] ?? 'warning');
            $sevCol = $severityColor[$sev] ?? '#64748B';
            $deliv = (int) ($h['delivered_count'] ?? 0);
            $dest = (int) ($h['destinations_count'] ?? 0);
        ?>
        <li class="set-history-item">
            <span class="set-history-dot" style="background: <?= $sevCol ?>;"></span>
            <div class="set-history-body">
                <div class="set-history-head">
                    <span class="set-history-title"><?= e(mb_substr((string) $h['title'], 0, 100)) ?></span>
                    <code class="set-badge set-badge-mono"><?= e((string) $h['rule_slug']) ?></code>
                </div>
                <div class="set-history-meta">
                    <?= e((string) $h['created_at']) ?>
                    · entregada a <strong><?= $deliv ?></strong> de <?= $dest ?> destination<?= $dest === 1 ? '' : 's' ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
