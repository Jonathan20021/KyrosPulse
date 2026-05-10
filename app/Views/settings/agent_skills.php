<?php
/**
 * @var array $agent
 * @var array $linked     skills enlazadas con datos de link (priority, is_active, link_config)
 * @var array $available  skills aun no enlazadas a este agente (globales + custom)
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$agentId   = (int) $agent['id'];
$agentName = (string) $agent['name'];
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Skills de "' . $agentName . '"',
    'subtitle' => 'Combina capacidades especializadas (ventas, soporte, cobranza, agendamiento...) en este agente. La IA elige la skill mas relevante segun el mensaje del cliente.',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'ai']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="mb-4 flex items-center gap-2">
    <a href="<?= url('/settings/ai') ?>" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--color-border); color: var(--color-text-secondary);">
        ← Volver a agentes
    </a>
    <span class="text-xs" style="color: var(--color-text-tertiary);">Agente #<?= $agentId ?> · <?= e((string) ($agent['role'] ?? '')) ?></span>
</div>

<!-- Como funciona -->
<div class="surface p-5 mb-5">
    <div class="flex items-start gap-3 mb-2">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg" style="background: linear-gradient(135deg,#10B981,#06B6D4); color: white;">🧠</div>
        <div class="min-w-0">
            <div class="font-bold mb-0.5" style="color: var(--color-text-primary);">Como funciona el hub de skills</div>
            <p class="text-sm" style="color: var(--color-text-secondary);">
                Cada skill aporta su propio prompt + herramientas al agente. Cuando llega un mensaje, un router heuristico
                (sin costo IA) detecta la intencion y prioriza la skill mas relevante. Asi un solo agente puede hacer
                ventas, soporte y cobranza, cambiando de modo segun lo que el cliente diga.
            </p>
        </div>
    </div>
</div>

<!-- Skills enlazadas -->
<div class="surface mb-5 overflow-hidden">
    <div class="px-5 py-3.5 border-b flex items-center justify-between" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">
            Skills activas
            <span class="ml-2 text-xs font-normal" style="color: var(--color-text-tertiary);">(<?= count($linked) ?>)</span>
        </h3>
    </div>
    <?php if (empty($linked)): ?>
    <div class="p-8 text-center text-sm" style="color: var(--color-text-secondary);">
        Aun no has enlazado skills. Anade una abajo para que el agente pueda especializarse.
    </div>
    <?php else: ?>
    <ul class="divide-y" style="border-color: var(--color-border);">
        <?php foreach ($linked as $s):
            $tools = $s['tools'] ? json_decode((string) $s['tools'], true) : [];
            if (!is_array($tools)) $tools = [];
            $isGlobal = empty($s['tenant_id']);
            $linkActive = !empty($s['link_active']);
        ?>
        <li class="px-5 py-4 flex items-start gap-4 <?= $linkActive ? '' : 'opacity-60' ?>">
            <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center text-lg"
                 style="background: <?= $isGlobal ? 'rgba(99,102,241,.12)' : 'rgba(16,185,129,.12)' ?>; color: <?= $isGlobal ? '#6366F1' : '#10B981' ?>;">
                <?= $isGlobal ? '🌐' : '⚡' ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-0.5">
                    <span class="font-semibold" style="color: var(--color-text-primary);"><?= e((string) $s['name']) ?></span>
                    <code class="text-[10px] px-1.5 py-0.5 rounded" style="background: var(--color-bg-secondary);"><?= e((string) $s['slug']) ?></code>
                    <?php if ($isGlobal): ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(99,102,241,.1); color: #4F46E5;">Global</span>
                    <?php else: ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(16,185,129,.1); color: #059669;">Custom</span>
                    <?php endif; ?>
                    <?php if (!$linkActive): ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(148,163,184,.15); color: #475569;">Pausada</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($s['description'])): ?>
                <p class="text-sm" style="color: var(--color-text-secondary);"><?= e((string) $s['description']) ?></p>
                <?php endif; ?>
                <?php if ($tools): ?>
                <div class="mt-2 flex flex-wrap gap-1">
                    <?php foreach ($tools as $t): ?>
                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded" style="background: var(--color-bg-secondary); color: var(--color-text-secondary);"><?= e((string) $t) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="flex-shrink-0 flex flex-col items-end gap-1.5 min-w-[160px]">
                <form action="<?= url('/settings/ai/agents/' . $agentId . '/skills/' . (int) $s['id'] . '/priority') ?>" method="POST" class="flex items-center gap-1.5 text-xs">
                    <?= csrf_field() ?>
                    <label style="color: var(--color-text-tertiary);">Prio</label>
                    <input type="number" name="priority" value="<?= (int) ($s['link_priority'] ?? 100) ?>" min="0" max="999"
                           class="w-16 px-2 py-1 rounded text-xs"
                           style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); color: var(--color-text-primary);">
                    <button class="px-2 py-1 rounded text-xs font-semibold" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">↑</button>
                </form>
                <div class="flex items-center gap-1">
                    <form action="<?= url('/settings/ai/agents/' . $agentId . '/skills/' . (int) $s['id'] . '/toggle') ?>" method="POST" class="inline">
                        <?= csrf_field() ?>
                        <button class="px-2 py-1 rounded text-xs font-semibold" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">
                            <?= $linkActive ? '⏸ Pausar' : '▶ Activar' ?>
                        </button>
                    </form>
                    <form action="<?= url('/settings/ai/agents/' . $agentId . '/skills/' . (int) $s['id'] . '/detach') ?>" method="POST" class="inline" onsubmit="return confirm('Quitar esta skill del agente?')">
                        <?= csrf_field() ?>
                        <button class="px-2 py-1 rounded text-xs font-semibold border" style="color:#BE123C; border-color: rgba(244,63,94,.3); background: rgba(244,63,94,.05);">Quitar</button>
                    </form>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<!-- Skills disponibles -->
<div class="surface mb-5 overflow-hidden">
    <div class="px-5 py-3.5 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">
            Disponibles para anadir
            <span class="ml-2 text-xs font-normal" style="color: var(--color-text-tertiary);">(<?= count($available) ?>)</span>
        </h3>
        <p class="text-xs mt-0.5" style="color: var(--color-text-secondary);">
            Las skills <strong>globales</strong> son del sistema (sales, support, cobranza, scheduling...). Las <strong>custom</strong> las creas tu desde la pagina de IA.
        </p>
    </div>
    <?php if (empty($available)): ?>
    <div class="p-8 text-center text-sm" style="color: var(--color-text-secondary);">
        Ya tienes todas las skills disponibles enlazadas a este agente.
    </div>
    <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 p-5">
        <?php foreach ($available as $s):
            $tools = $s['tools'] ? json_decode((string) $s['tools'], true) : [];
            if (!is_array($tools)) $tools = [];
            $isGlobal = empty($s['tenant_id']);
        ?>
        <form action="<?= url('/settings/ai/agents/' . $agentId . '/skills/attach') ?>" method="POST"
              class="surface p-4 flex flex-col gap-2 hover:scale-[1.01] transition-transform">
            <?= csrf_field() ?>
            <input type="hidden" name="slug" value="<?= e((string) $s['slug']) ?>">
            <div class="flex items-start gap-2">
                <div class="w-9 h-9 rounded-xl flex-shrink-0 flex items-center justify-center text-lg"
                     style="background: <?= $isGlobal ? 'rgba(99,102,241,.12)' : 'rgba(16,185,129,.12)' ?>; color: <?= $isGlobal ? '#6366F1' : '#10B981' ?>;">
                    <?= $isGlobal ? '🌐' : '⚡' ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm" style="color: var(--color-text-primary);"><?= e((string) $s['name']) ?></div>
                    <code class="text-[10px]" style="color: var(--color-text-tertiary);"><?= e((string) $s['slug']) ?></code>
                </div>
            </div>
            <?php if (!empty($s['description'])): ?>
            <p class="text-xs" style="color: var(--color-text-secondary);"><?= e((string) $s['description']) ?></p>
            <?php endif; ?>
            <?php if ($tools): ?>
            <div class="flex flex-wrap gap-1">
                <?php foreach (array_slice($tools, 0, 4) as $t): ?>
                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded" style="background: var(--color-bg-secondary); color: var(--color-text-secondary);"><?= e((string) $t) ?></span>
                <?php endforeach; ?>
                <?php if (count($tools) > 4): ?><span class="text-[10px]" style="color: var(--color-text-tertiary);">+<?= count($tools) - 4 ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="flex items-center gap-2 mt-1">
                <input type="number" name="priority" value="100" min="0" max="999"
                       class="w-16 px-2 py-1 rounded text-xs"
                       style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); color: var(--color-text-primary);">
                <button class="flex-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background: linear-gradient(135deg,#10B981,#0EA572);">
                    + Anadir al agente
                </button>
            </div>
        </form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php \App\Core\View::end(); ?>
