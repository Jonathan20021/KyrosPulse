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
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Alertas inteligentes',
    'subtitle' => 'Te avisamos cuando algo importante pasa: cuota API, webhooks muertos, errores de IA, eventos de seguridad. Reusa tus notification destinations.',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'alerts']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<!-- Destinations check -->
<?php if ($destCount === 0): ?>
<div class="surface p-4 mb-5" style="background: rgba(245,158,11,.05); border-color: rgba(245,158,11,.3);">
    <div class="flex items-start gap-3">
        <span class="text-2xl flex-shrink-0">⚠</span>
        <div class="flex-1">
            <div class="font-semibold mb-0.5" style="color: var(--color-text-primary);">No tienes destinations configurados</div>
            <p class="text-sm" style="color: var(--color-text-secondary);">Las alertas se registran en el history pero <strong>no llegan a ningun lado</strong>. Configura email, Slack, Discord o Telegram para recibirlas.</p>
        </div>
        <a href="<?= url('/settings/notifications?prefill=email') ?>" class="text-xs px-3 py-1.5 rounded-lg font-semibold text-white flex-shrink-0" style="background: linear-gradient(135deg,#F59E0B,#EF4444);">Configurar →</a>
    </div>
</div>
<?php else: ?>
<div class="surface p-3 mb-5" style="background: rgba(16,185,129,.05); border-color: rgba(16,185,129,.2);">
    <div class="flex items-center gap-2 text-sm" style="color: var(--color-text-secondary);">
        <span>✓</span>
        <span><?= $destCount ?> destination(s) recibiran las alertas activas.</span>
        <a href="<?= url('/settings/notifications') ?>" class="ml-auto text-xs underline" style="color: var(--color-text-primary);">Gestionar destinations</a>
    </div>
</div>
<?php endif; ?>

<!-- Reglas -->
<div class="surface mb-5 overflow-hidden">
    <div class="px-5 py-3.5 border-b flex items-center justify-between" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Reglas configuradas <span class="ml-2 text-xs font-normal" style="color: var(--color-text-tertiary);">(<?= count($rules) ?>)</span></h3>
        <form action="<?= url('/settings/alerts/test') ?>" method="POST" class="inline">
            <?= csrf_field() ?>
            <button class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--color-border); color: var(--color-text-primary);">Probar alerta de cuota</button>
        </form>
    </div>
    <ul class="divide-y" style="border-color: var(--color-border);">
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
        <li class="px-5 py-4 <?= $isActive ? '' : 'opacity-60' ?>">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center text-lg" style="background: <?= $sevCol ?>22; color: <?= $sevCol ?>;">
                    <?= $icon ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-0.5">
                        <span class="font-semibold" style="color: var(--color-text-primary);"><?= e((string) $r['name']) ?></span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold uppercase" style="background: <?= $sevCol ?>22; color: <?= $sevCol ?>;"><?= e($sev) ?></span>
                        <code class="text-[10px] px-1.5 py-0.5 rounded" style="background: var(--color-bg-secondary); color: var(--color-text-secondary);"><?= e((string) $r['slug']) ?></code>
                        <?php if ($isBuiltin): ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: rgba(99,102,241,.12); color:#6366F1;">Builtin</span>
                        <?php else: ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: rgba(16,185,129,.1); color:#059669;">Customizada</span>
                        <?php endif; ?>
                        <?php if (!$isActive): ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: rgba(148,163,184,.15); color:#475569;">Pausada</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm" style="color: var(--color-text-secondary);"><?= e((string) ($r['description'] ?? '')) ?></p>
                    <div class="text-[11px] mt-1.5 flex items-center gap-3 flex-wrap" style="color: var(--color-text-tertiary);">
                        <span>Cooldown: <strong><?= (int) ($r['cooldown_minutes'] ?? 60) ?> min</strong></span>
                        <span>·</span>
                        <span>Disparos: <strong><?= $triggerCount ?></strong></span>
                        <?php if ($lastTrig): ?>
                        <span>·</span>
                        <span>Ultima: <?= e((string) $lastTrig) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-shrink-0 flex items-start gap-1.5">
                    <form action="<?= url('/settings/alerts/' . e((string) $r['slug']) . '/cooldown') ?>" method="POST" class="flex items-center gap-1.5">
                        <?= csrf_field() ?>
                        <input type="number" name="minutes" value="<?= (int) ($r['cooldown_minutes'] ?? 60) ?>" min="5" max="10080" class="w-16 px-2 py-1 rounded text-xs" style="background: var(--color-bg-secondary); border:1px solid var(--color-border); color: var(--color-text-primary);">
                        <button class="px-2 py-1 rounded text-xs font-semibold" style="background: var(--color-bg-secondary); color: var(--color-text-primary);" title="Guardar cooldown">↑</button>
                    </form>
                    <form action="<?= url('/settings/alerts/' . e((string) $r['slug']) . '/toggle') ?>" method="POST" class="inline">
                        <?= csrf_field() ?>
                        <?php if ($isActive): ?>
                        <button class="px-2.5 py-1 rounded text-xs font-semibold" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">⏸ Pausar</button>
                        <?php else: ?>
                        <input type="hidden" name="active" value="1">
                        <button class="px-2.5 py-1 rounded text-xs font-semibold text-white" style="background: linear-gradient(135deg,#10B981,#0EA572);">▶ Activar</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- Historial -->
<div class="surface overflow-hidden">
    <div class="px-5 py-3.5 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Historial reciente <span class="ml-2 text-xs font-normal" style="color: var(--color-text-tertiary);">(<?= count($history) ?>)</span></h3>
        <p class="text-xs mt-0.5" style="color: var(--color-text-secondary);">Cada alerta disparada queda registrada aqui, incluso si no hay destinations para recibirla.</p>
    </div>
    <?php if (empty($history)): ?>
    <div class="p-6 text-center text-sm" style="color: var(--color-text-secondary);">Sin alertas disparadas aun.</div>
    <?php else: ?>
    <ul class="divide-y" style="border-color: var(--color-border);">
        <?php foreach ($history as $h):
            $sev = (string) ($h['severity'] ?? 'warning');
            $sevCol = $severityColor[$sev] ?? '#64748B';
            $deliv = (int) ($h['delivered_count'] ?? 0);
            $dest = (int) ($h['destinations_count'] ?? 0);
        ?>
        <li class="px-5 py-3 flex items-start gap-3">
            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-1.5" style="background: <?= $sevCol ?>;"></div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-semibold text-sm" style="color: var(--color-text-primary);"><?= e(mb_substr((string) $h['title'], 0, 100)) ?></span>
                    <code class="text-[10px] px-1.5 py-0.5 rounded" style="background: var(--color-bg-secondary); color: var(--color-text-tertiary);"><?= e((string) $h['rule_slug']) ?></code>
                </div>
                <div class="text-[11px] mt-0.5" style="color: var(--color-text-tertiary);">
                    <?= e((string) $h['created_at']) ?>
                    · entregada a <strong><?= $deliv ?></strong> de <?= $dest ?> destination<?= $dest === 1 ? '' : 's' ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php \App\Core\View::end(); ?>
