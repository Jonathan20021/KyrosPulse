<?php
/**
 * @var array $workflow
 * @var array $run
 * @var array $steps
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$context = $run['context'] ? json_decode((string) $run['context'], true) : [];
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Run #' . (int) $run['id'],
    'subtitle' => 'Workflow: ' . $workflow['name'] . ' · ' . $run['status'],
]); ?>

<div class="mb-4">
    <a href="<?= url('/workflows/' . (int) $workflow['id']) ?>" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--color-border); color: var(--color-text-secondary);">← Volver al workflow</a>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-5">
    <div class="surface p-4">
        <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Trigger</div>
        <div class="font-mono text-sm font-semibold" style="color: var(--color-text-primary);"><?= e((string) $run['trigger_type']) ?></div>
        <div class="text-xs mt-0.5" style="color: var(--color-text-secondary);"><?= e((string) $run['created_at']) ?></div>
    </div>
    <div class="surface p-4">
        <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Status</div>
        <div class="font-bold" style="color: <?= match ($run['status']) {
            'succeeded' => '#0B7C56',
            'failed','cancelled' => '#BE123C',
            'waiting' => '#B45309',
            'running' => '#06B6D4',
            default => '#64748B',
        } ?>;"><?= e((string) $run['status']) ?></div>
        <?php if (!empty($run['current_step_key'])): ?>
        <div class="text-xs mt-0.5" style="color: var(--color-text-secondary);">step: <code><?= e((string) $run['current_step_key']) ?></code></div>
        <?php endif; ?>
        <?php if (!empty($run['wait_until'])): ?>
        <div class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">resume: <?= e((string) $run['wait_until']) ?></div>
        <?php endif; ?>
    </div>
    <div class="surface p-4">
        <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Duracion</div>
        <?php
            $started = !empty($run['started_at']) ? strtotime((string) $run['started_at']) : null;
            $finished = !empty($run['finished_at']) ? strtotime((string) $run['finished_at']) : null;
            $dur = ($started && $finished) ? ($finished - $started) : null;
        ?>
        <div class="font-bold" style="color: var(--color-text-primary);"><?= $dur !== null ? $dur . 's' : '—' ?></div>
    </div>
</div>

<?php if (!empty($run['error'])): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;">
    <strong>Error:</strong> <?= e((string) $run['error']) ?>
</div>
<?php endif; ?>

<!-- Steps ejecutados -->
<div class="surface mb-5 overflow-hidden">
    <div class="px-5 py-3.5 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Steps ejecutados</h3>
    </div>
    <?php if (empty($steps)): ?>
    <div class="p-6 text-center text-sm" style="color: var(--color-text-secondary);">Sin steps registrados.</div>
    <?php else: ?>
    <ul class="divide-y" style="border-color: var(--color-border);">
        <?php foreach ($steps as $s):
            $col = match ($s['status']) {
                'succeeded' => '#0B7C56',
                'failed' => '#BE123C',
                'skipped' => '#64748B',
                default => '#06B6D4',
            };
        ?>
        <li class="p-4">
            <div class="flex items-start gap-3 mb-1.5">
                <span class="text-[10px] px-2 py-0.5 rounded font-mono mt-0.5" style="background: <?= $col ?>22; color: <?= $col ?>;"><?= e((string) $s['status']) ?></span>
                <div class="flex-1 min-w-0">
                    <div class="font-mono text-sm font-semibold" style="color: var(--color-text-primary);"><?= e((string) $s['step_key']) ?> <span class="text-xs" style="color: var(--color-text-tertiary);">(<?= e((string) $s['step_type']) ?>)</span></div>
                    <div class="text-[11px] mt-0.5" style="color: var(--color-text-tertiary);"><?= e((string) $s['started_at']) ?> · <?= (int) ($s['latency_ms'] ?? 0) ?>ms</div>
                </div>
            </div>
            <?php if (!empty($s['error'])): ?>
            <div class="text-xs px-3 py-2 rounded-lg" style="background: rgba(244,63,94,.06); color:#BE123C;"><?= e((string) $s['error']) ?></div>
            <?php endif; ?>
            <?php if (!empty($s['output'])):
                $out = json_decode((string) $s['output'], true);
            ?>
            <details class="mt-2">
                <summary class="cursor-pointer text-xs" style="color: var(--color-text-secondary);">Ver output</summary>
                <pre class="text-[11px] font-mono mt-1 p-2 rounded" style="background: var(--color-bg-secondary); color: var(--color-text-primary); overflow-x:auto;"><?= e(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
            </details>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<!-- Context final -->
<div class="surface overflow-hidden">
    <div class="px-5 py-3.5 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Context (vars del run)</h3>
    </div>
    <pre class="p-4 text-[11px] font-mono" style="color: var(--color-text-primary); overflow-x:auto;"><?= e(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
</div>
<?php \App\Core\View::end(); ?>
