<?php
/**
 * @var array $template
 * @var array $definition  decoded JSON: trigger_type, trigger_config, steps[]
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$cfg   = is_array($definition['trigger_config'] ?? null) ? $definition['trigger_config'] : [];
$steps = is_array($definition['steps'] ?? null) ? $definition['steps'] : [];
$meets = !empty($template['_meets']);
$id    = (int) $template['id'];

$stepTypeColor = [
    'action'  => '#06B6D4',
    'branch'  => '#F59E0B',
    'delay'   => '#8B5CF6',
    'set_var' => '#10B981',
    'end'     => '#64748B',
];

$actionIcons = [
    'send_whatsapp' => '💬',
    'run_agent'     => '🧠',
    'http'          => '🌐',
    'add_tag'       => '🏷',
    'webhook_out'   => '🪝',
    'log'           => '📝',
    'noop'          => '⚪',
];
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => $template['name'],
    'subtitle' => $template['description'] ?? '',
]); ?>

<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="mb-4 flex items-center gap-2 flex-wrap">
    <a href="<?= url('/workflows/templates') ?>" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--color-border); color: var(--color-text-secondary);">← Galeria</a>
    <span class="text-xs" style="color: var(--color-text-tertiary);">Template #<?= $id ?> · slug: <code><?= e((string) $template['slug']) ?></code> · clonado <?= (int) $template['clone_count'] ?> veces</span>
</div>

<!-- CTA principal -->
<div class="surface p-5 mb-5">
    <div class="flex items-start gap-3 mb-4">
        <div class="w-12 h-12 rounded-xl flex-shrink-0 flex items-center justify-center text-2xl" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4); color:white;">
            <?= e((string) ($template['icon'] ?? '🪄')) ?>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-xs uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Categoria: <?= e((string) $template['category']) ?></div>
            <p class="text-sm" style="color: var(--color-text-secondary);">Al clonar, se crea un workflow nuevo en tu cuenta (desactivado para que lo revises). Lo activas cuando este listo.</p>
        </div>
    </div>

    <?php if (!empty($template['_requires'])): ?>
    <div class="text-xs p-2.5 rounded-lg mb-3" style="background: <?= $meets ? 'rgba(16,185,129,.08)' : 'rgba(245,158,11,.08)' ?>; color: <?= $meets ? '#0B7C56' : '#B45309' ?>;">
        <strong>Requiere:</strong> <?= e(implode(', ', $template['_requires'])) ?>
        <?php if ($meets): ?> · ✓ tu cuenta cumple<?php else: ?> · ⚠ tu cuenta no lo cumple aun<?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($meets): ?>
    <form action="<?= url('/workflows/templates/' . $id . '/use') ?>" method="POST" class="flex items-end gap-2">
        <?= csrf_field() ?>
        <div class="flex-1">
            <label class="text-xs uppercase tracking-wider font-semibold mb-1 block" style="color: var(--color-text-tertiary);">Nombre del nuevo workflow (opcional)</label>
            <input name="name" maxlength="120" placeholder="<?= e((string) $template['name']) ?>" class="input">
        </div>
        <button class="px-5 py-2.5 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">
            Clonar este template →
        </button>
    </form>
    <?php else: ?>
    <div class="text-sm" style="color: var(--color-text-secondary);">Activa la feature requerida en tu cuenta para usar este template.</div>
    <?php endif; ?>
</div>

<!-- Trigger preview -->
<div class="surface p-4 mb-5">
    <div class="text-[10px] uppercase tracking-wider font-semibold mb-2" style="color: var(--color-text-tertiary);">Trigger</div>
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-xs px-2 py-1 rounded font-mono font-bold" style="background: rgba(139,92,246,.12); color: #8B5CF6;"><?= e((string) ($definition['trigger_type'] ?? 'manual')) ?></span>
        <?php if (($definition['trigger_type'] ?? '') === 'event' && !empty($cfg['event'])): ?>
            <span class="text-sm" style="color: var(--color-text-secondary);">cuando ocurre <code><?= e((string) $cfg['event']) ?></code></span>
        <?php elseif (($definition['trigger_type'] ?? '') === 'schedule' && !empty($cfg['cron'])): ?>
            <span class="text-sm" style="color: var(--color-text-secondary);">cron <code><?= e((string) $cfg['cron']) ?></code></span>
        <?php elseif (($definition['trigger_type'] ?? '') === 'webhook'): ?>
            <span class="text-sm" style="color: var(--color-text-secondary);">URL publica unica (generada al clonar)</span>
        <?php else: ?>
            <span class="text-sm" style="color: var(--color-text-secondary);">manual</span>
        <?php endif; ?>
    </div>
</div>

<!-- Steps preview -->
<div class="surface overflow-hidden">
    <div class="px-5 py-3 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Flujo de steps <span class="ml-2 text-xs font-normal" style="color: var(--color-text-tertiary);">(<?= count($steps) ?>)</span></h3>
    </div>
    <?php if (empty($steps)): ?>
    <div class="p-6 text-center text-sm" style="color: var(--color-text-secondary);">Template sin steps definidos.</div>
    <?php else: ?>
    <ol class="divide-y" style="border-color: var(--color-border);">
        <?php foreach ($steps as $idx => $s):
            $type = (string) ($s['type'] ?? 'action');
            $color = $stepTypeColor[$type] ?? '#64748B';
            $cfgStep = is_array($s['config'] ?? null) ? $s['config'] : [];
            $icon = match ($type) {
                'action'  => isset($cfgStep['action']) ? ($actionIcons[$cfgStep['action']] ?? '⚡') : '⚡',
                'branch'  => '🔀',
                'delay'   => '⏳',
                'set_var' => '📌',
                'end'     => '🏁',
                default   => '⚙',
            };
            $summary = match ($type) {
                'action'  => isset($cfgStep['action']) ? ($cfgStep['action'] . (isset($cfgStep['params']) ? '' : '')) : 'sin accion',
                'branch'  => ($cfgStep['expr'] ?? '?') . ' ' . ($cfgStep['op'] ?? 'truthy') . ' ' . (isset($cfgStep['value']) ? (string) $cfgStep['value'] : ''),
                'delay'   => self_format_delay($cfgStep['seconds'] ?? 0),
                'set_var' => ($cfgStep['key'] ?? '?') . ' = ...',
                'end'     => 'final: ' . ($cfgStep['status'] ?? 'succeeded'),
                default   => '',
            };
        ?>
        <li class="p-4 flex items-start gap-3">
            <div class="text-[10px] font-mono font-bold w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0" style="background: var(--color-bg-secondary); color: var(--color-text-secondary);"><?= $idx + 1 ?></div>
            <span class="text-lg flex-shrink-0"><?= $icon ?></span>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                    <span class="text-[10px] px-1.5 py-0.5 rounded font-mono font-bold" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= e($type) ?></span>
                    <span class="font-mono text-sm font-semibold" style="color: var(--color-text-primary);"><?= e((string) ($s['step_key'] ?? '?')) ?></span>
                </div>
                <div class="text-xs" style="color: var(--color-text-secondary);"><?= e((string) $summary) ?></div>

                <?php if ($type === 'branch'): ?>
                <div class="mt-1 text-[11px] flex items-center gap-2" style="color: var(--color-text-tertiary);">
                    <span style="color:#0B7C56;">TRUE → <code><?= e((string) ($s['branch_yes'] ?? 'fin')) ?></code></span>
                    <span>·</span>
                    <span style="color:#BE123C;">FALSE → <code><?= e((string) ($s['branch_no'] ?? 'fin')) ?></code></span>
                </div>
                <?php elseif (!empty($s['next_step_key']) && $type !== 'end'): ?>
                <div class="mt-1 text-[11px]" style="color: var(--color-text-tertiary);">next → <code><?= e((string) $s['next_step_key']) ?></code></div>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
</div>

<?php
function self_format_delay(int|string $seconds): string {
    $seconds = (int) $seconds;
    if ($seconds >= 86400) return 'esperar ' . round($seconds / 86400, 1) . ' dias';
    if ($seconds >= 3600)  return 'esperar ' . round($seconds / 3600, 1)  . ' horas';
    if ($seconds >= 60)    return 'esperar ' . round($seconds / 60)        . ' minutos';
    return 'esperar ' . $seconds . ' segundos';
}
?>
<?php \App\Core\View::end(); ?>
