<?php
/**
 * @var array       $workflow
 * @var array       $steps
 * @var array       $runs
 * @var string|null $publicUrl
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$cfg = $workflow['trigger_config'] ? json_decode((string) $workflow['trigger_config'], true) : [];
if (!is_array($cfg)) $cfg = [];
$wfId = (int) $workflow['id'];
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => $workflow['name'],
    'subtitle' => 'Workflow #' . $wfId . ' · trigger: ' . $workflow['trigger_type'],
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="mb-4 flex items-center gap-2 flex-wrap">
    <a href="<?= url('/workflows') ?>" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--color-border); color: var(--color-text-secondary);">← Volver</a>

    <form action="<?= url('/workflows/' . $wfId . '/toggle') ?>" method="POST" class="inline"><?= csrf_field() ?>
        <button class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--color-border); color: var(--color-text-primary);">
            <?= !empty($workflow['is_active']) ? '⏸ Pausar' : '▶ Activar' ?>
        </button>
    </form>

    <?php if ($workflow['trigger_type'] === 'manual' && !empty($steps)): ?>
    <form action="<?= url('/workflows/' . $wfId . '/run-now') ?>" method="POST" class="inline"><?= csrf_field() ?>
        <button class="text-xs px-3 py-1.5 rounded-lg text-white font-semibold" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">▶ Ejecutar ahora</button>
    </form>
    <?php endif; ?>

    <form action="<?= url('/workflows/' . $wfId . '/delete') ?>" method="POST" class="inline ml-auto" onsubmit="return confirm('Eliminar este workflow?')"><?= csrf_field() ?>
        <button class="text-xs px-3 py-1.5 rounded-lg border" style="color:#BE123C; border-color: rgba(244,63,94,.3); background: rgba(244,63,94,.05);">Eliminar</button>
    </form>
</div>

<!-- Trigger info -->
<div class="surface p-5 mb-5">
    <div class="text-xs uppercase tracking-wider font-semibold mb-2" style="color: var(--color-text-tertiary);">Trigger</div>
    <?php if ($workflow['trigger_type'] === 'event'): ?>
    <form action="<?= url('/workflows/' . $wfId) ?>" method="POST" class="flex gap-2 items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="POST">
        <div class="flex-1">
            <label class="label">Evento</label>
            <input name="trigger_event" value="<?= e((string) ($cfg['event'] ?? '*')) ?>" class="input" placeholder="order.created, agent.run.completed, *">
        </div>
        <button class="px-3 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">Guardar</button>
    </form>
    <?php elseif ($workflow['trigger_type'] === 'schedule'): ?>
    <form action="<?= url('/workflows/' . $wfId) ?>" method="POST" class="flex gap-2 items-end">
        <?= csrf_field() ?>
        <div class="flex-1">
            <label class="label">Cron expression</label>
            <input name="trigger_cron" value="<?= e((string) ($cfg['cron'] ?? '0 9 * * *')) ?>" class="input" placeholder="0 9 * * *">
        </div>
        <button class="px-3 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">Guardar</button>
    </form>
    <p class="text-xs mt-2" style="color: var(--color-text-tertiary);">Proxima ejecucion: <strong><?= e((string) ($workflow['next_run_at'] ?? 'pendiente del cron tick')) ?></strong></p>
    <?php elseif ($workflow['trigger_type'] === 'webhook' && $publicUrl): ?>
    <p class="text-sm mb-2" style="color: var(--color-text-secondary);">Esta URL recibe POST con JSON arbitrario y dispara el workflow:</p>
    <code class="text-xs font-mono px-3 py-2 rounded-lg block break-all" style="background: var(--color-bg-secondary); color: var(--color-text-primary);"><?= e($publicUrl) ?></code>
    <p class="text-xs mt-2" style="color: var(--color-text-tertiary);">El body llega al context como <code>webhook_payload</code>.</p>
    <?php else: ?>
    <p class="text-sm" style="color: var(--color-text-secondary);">Trigger manual: usa el boton "Ejecutar ahora" o llama a <code>POST /api/v1/workflows/<?= $wfId ?>/run</code>.</p>
    <?php endif; ?>
</div>

<!-- Steps -->
<div class="surface mb-5 overflow-hidden">
    <div class="px-5 py-3.5 border-b flex items-center justify-between" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Steps <span class="ml-2 text-xs font-normal" style="color: var(--color-text-tertiary);">(<?= count($steps) ?>)</span></h3>
    </div>
    <?php if (empty($steps)): ?>
    <div class="p-8 text-center text-sm" style="color: var(--color-text-secondary);">Sin steps. Anade el primero abajo.</div>
    <?php else: ?>
    <ul class="divide-y" style="border-color: var(--color-border);">
        <?php foreach ($steps as $s):
            $cfgStep = $s['config'] ? json_decode((string) $s['config'], true) : [];
            if (!is_array($cfgStep)) $cfgStep = [];
            $typeColor = match ($s['type']) {
                'action'  => '#06B6D4',
                'branch'  => '#F59E0B',
                'delay'   => '#8B5CF6',
                'set_var' => '#10B981',
                'end'     => '#64748B',
                default   => '#64748B',
            };
        ?>
        <li class="p-5">
            <details>
                <summary class="cursor-pointer flex items-start gap-3">
                    <span class="text-[10px] px-2 py-0.5 rounded font-mono font-bold mt-0.5" style="background: <?= $typeColor ?>22; color: <?= $typeColor ?>;"><?= e((string) $s['type']) ?></span>
                    <div class="flex-1 min-w-0">
                        <div class="font-mono text-sm font-semibold" style="color: var(--color-text-primary);"><?= e((string) $s['step_key']) ?></div>
                        <div class="text-xs mt-0.5" style="color: var(--color-text-secondary);">
                            <?php if ($s['type'] === 'action'): ?>
                                accion: <code><?= e((string) ($cfgStep['action'] ?? '?')) ?></code>
                            <?php elseif ($s['type'] === 'branch'): ?>
                                if <code><?= e((string) ($cfgStep['expr'] ?? '?')) ?></code> <?= e((string) ($cfgStep['op'] ?? 'truthy')) ?> → <code><?= e((string) ($s['branch_yes'] ?? '?')) ?></code>, else <code><?= e((string) ($s['branch_no'] ?? '?')) ?></code>
                            <?php elseif ($s['type'] === 'delay'): ?>
                                espera <?= (int) ($cfgStep['seconds'] ?? 60) ?> segundos
                            <?php elseif ($s['type'] === 'set_var'): ?>
                                <code><?= e((string) ($cfgStep['key'] ?? '?')) ?></code> = <?= e(is_scalar($cfgStep['value'] ?? '') ? (string) ($cfgStep['value'] ?? '') : json_encode($cfgStep['value'] ?? null)) ?>
                            <?php elseif ($s['type'] === 'end'): ?>
                                final: <?= e((string) ($cfgStep['status'] ?? 'succeeded')) ?>
                            <?php endif; ?>
                            <?php if (!empty($s['next_step_key']) && $s['type'] !== 'branch' && $s['type'] !== 'end'): ?>
                                · next → <code><?= e((string) $s['next_step_key']) ?></code>
                            <?php endif; ?>
                        </div>
                    </div>
                </summary>
                <form action="<?= url('/workflows/' . $wfId . '/steps/' . (int) $s['id'] . '/update') ?>" method="POST" class="mt-3 space-y-2">
                    <?= csrf_field() ?>
                    <div>
                        <label class="label text-xs">config (JSON)</label>
                        <textarea name="config_json" rows="6" class="input font-mono text-xs"><?= e(json_encode($cfgStep, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-2">
                        <input name="next_step_key" value="<?= e((string) ($s['next_step_key'] ?? '')) ?>" placeholder="next_step_key" class="input">
                        <input name="branch_yes"    value="<?= e((string) ($s['branch_yes'] ?? '')) ?>" placeholder="branch_yes (si type=branch)" class="input">
                        <input name="branch_no"     value="<?= e((string) ($s['branch_no']  ?? '')) ?>" placeholder="branch_no" class="input">
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">Guardar step</button>
                        <button type="button" class="px-3 py-1.5 rounded-lg text-xs border" style="color:#BE123C; border-color: rgba(244,63,94,.3); background: rgba(244,63,94,.05);"
                                onclick="if(confirm('Eliminar step?')) document.getElementById('del-<?= (int) $s['id'] ?>').submit();">Eliminar</button>
                    </div>
                </form>
                <form id="del-<?= (int) $s['id'] ?>" action="<?= url('/workflows/' . $wfId . '/steps/' . (int) $s['id'] . '/delete') ?>" method="POST" class="hidden"><?= csrf_field() ?></form>
            </details>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <!-- Add step form -->
    <div class="px-5 py-4 border-t" style="border-color: var(--color-border); background: var(--color-bg-secondary);">
        <details>
            <summary class="cursor-pointer text-sm font-semibold" style="color: var(--color-text-primary);">+ Agregar step</summary>
            <form action="<?= url('/workflows/' . $wfId . '/steps') ?>" method="POST" class="mt-3 space-y-2">
                <?= csrf_field() ?>
                <div class="grid sm:grid-cols-2 gap-2">
                    <div>
                        <label class="label text-xs">step_key</label>
                        <input name="step_key" required pattern="[a-z][a-z0-9_]{0,39}" placeholder="send_welcome" class="input font-mono">
                    </div>
                    <div>
                        <label class="label text-xs">type</label>
                        <select name="type" class="select">
                            <option value="action">action</option>
                            <option value="branch">branch</option>
                            <option value="delay">delay</option>
                            <option value="set_var">set_var</option>
                            <option value="end">end</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label text-xs">config (JSON)</label>
                    <textarea name="config_json" rows="5" class="input font-mono text-xs" placeholder='{"action":"send_whatsapp","params":{"to":"{{ payload.contact_phone }}","text":"Hola {{ payload.first_name }}"}}'>{}</textarea>
                </div>
                <div class="grid sm:grid-cols-3 gap-2">
                    <input name="next_step_key" placeholder="next_step_key" class="input">
                    <input name="branch_yes"    placeholder="branch_yes (si type=branch)" class="input">
                    <input name="branch_no"     placeholder="branch_no" class="input">
                </div>
                <div class="flex items-center justify-end">
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">Agregar</button>
                </div>
            </form>
        </details>
    </div>
</div>

<!-- Runs recientes -->
<div class="surface overflow-hidden">
    <div class="px-5 py-3.5 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Runs recientes</h3>
    </div>
    <?php if (empty($runs)): ?>
    <div class="p-8 text-center text-sm" style="color: var(--color-text-secondary);">Sin ejecuciones aun.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary); background: var(--color-bg-secondary);">
                    <th class="text-left px-4 py-2.5">Cuando</th>
                    <th class="text-left px-4 py-2.5">Trigger</th>
                    <th class="text-left px-4 py-2.5">Status</th>
                    <th class="text-left px-4 py-2.5">Step actual</th>
                    <th class="text-right px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($runs as $r):
                    $col = match ($r['status']) {
                        'succeeded' => '#0B7C56',
                        'failed','cancelled' => '#BE123C',
                        'waiting' => '#B45309',
                        'running' => '#06B6D4',
                        default   => '#64748B',
                    };
                ?>
                <tr class="border-t" style="border-color: var(--color-border);">
                    <td class="px-4 py-2.5 text-xs" style="color: var(--color-text-secondary);"><?= e((string) $r['created_at']) ?></td>
                    <td class="px-4 py-2.5 text-xs"><code><?= e((string) $r['trigger_type']) ?></code></td>
                    <td class="px-4 py-2.5"><span class="font-semibold" style="color: <?= $col ?>;"><?= e((string) $r['status']) ?></span></td>
                    <td class="px-4 py-2.5 text-xs"><code><?= e((string) ($r['current_step_key'] ?? '—')) ?></code></td>
                    <td class="px-4 py-2.5 text-right">
                        <a href="<?= url('/workflows/' . $wfId . '/runs/' . (int) $r['id']) ?>" class="text-xs px-2 py-1 rounded font-semibold" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php \App\Core\View::end(); ?>
