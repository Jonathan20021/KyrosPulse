<?php
/** @var array $stages */
/** @var array $byStage */
/** @var array $totals */
/** @var array $kpis */
/** @var array|null $tenant */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$currency = (string) ($tenant['currency'] ?? 'USD');
$totalCards = array_sum(array_column($totals, 'count'));
?>

<!-- ====== HEADER ====== -->
<div class="flex items-end justify-between gap-4 flex-wrap mb-6">
    <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center text-2xl shadow-lg shadow-violet-500/20" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
            📈
        </div>
        <div>
            <h1 class="text-2xl font-black tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;">Pipeline de ventas</h1>
            <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">Arrastra leads entre etapas. La IA y las órdenes los actualizan automáticamente.</p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= url('/contacts') ?>" class="px-3 py-2 rounded-xl text-sm font-medium" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">
            Contactos
        </a>
        <a href="<?= url('/leads/create') ?>" class="px-4 py-2 rounded-xl text-sm font-semibold text-white shadow-lg shadow-violet-500/30 flex items-center gap-1.5" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuevo lead
        </a>
    </div>
</div>

<!-- ====== KPIs ====== -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
    <?php
    $kpiList = [
        [
            'label'  => 'Pipeline abierto',
            'value'  => $currency . ' ' . number_format((float) $kpis['pipeline_value'], 0),
            'sub'    => $kpis['open_leads'] . ' leads activos',
            'color'  => '#7C3AED',
            'emoji'  => '💼',
        ],
        [
            'label'  => 'Pondrado IA',
            'value'  => $currency . ' ' . number_format((float) $kpis['weighted_value'], 0),
            'sub'    => 'value × probabilidad',
            'color'  => '#06B6D4',
            'emoji'  => '🎯',
        ],
        [
            'label'  => 'Ganado este mes',
            'value'  => $currency . ' ' . number_format((float) $kpis['won_this_month'], 0),
            'sub'    => $kpis['won_leads'] . ' total cerrado',
            'color'  => '#10B981',
            'emoji'  => '🏆',
        ],
        [
            'label'  => 'Win rate',
            'value'  => ((float) $kpis['win_rate']) . '%',
            'sub'    => $kpis['won_leads'] . ' won · ' . $kpis['lost_leads'] . ' lost',
            'color'  => $kpis['win_rate'] >= 50 ? '#22C55E' : ($kpis['win_rate'] >= 30 ? '#F59E0B' : '#EF4444'),
            'emoji'  => '📊',
        ],
        [
            'label'  => 'Ticket promedio',
            'value'  => $currency . ' ' . number_format((float) $kpis['avg_value'], 0),
            'sub'    => $kpis['total_leads'] . ' leads totales',
            'color'  => '#A855F7',
            'emoji'  => '💎',
        ],
        [
            'label'  => 'Leads de IA',
            'value'  => number_format($kpis['ai_generated']),
            'sub'    => $kpis['total_leads'] > 0 ? round(($kpis['ai_generated'] / $kpis['total_leads']) * 100) . '% del total' : '—',
            'color'  => '#EC4899',
            'emoji'  => '🤖',
        ],
    ];
    foreach ($kpiList as $k): ?>
    <div class="surface p-3 relative overflow-hidden group hover:scale-[1.02] transition">
        <div class="absolute -top-6 -right-6 w-20 h-20 rounded-full opacity-10 group-hover:opacity-20 transition" style="background: <?= $k['color'] ?>;"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[9px] uppercase tracking-[0.1em] font-bold" style="color: var(--color-text-tertiary);"><?= e($k['label']) ?></span>
                <span class="text-base"><?= $k['emoji'] ?></span>
            </div>
            <div class="text-base lg:text-lg font-black truncate" style="color: <?= $k['color'] ?>;"><?= e((string) $k['value']) ?></div>
            <div class="text-[10px] mt-0.5 truncate" style="color: var(--color-text-muted);"><?= e($k['sub']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ====== FILTROS ====== -->
<div class="surface p-2 mb-4 flex items-center gap-2 flex-wrap">
    <div class="relative flex-1 min-w-[200px]">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" id="leadSearch" placeholder="Buscar por título, contacto o empresa..." class="input pl-10 text-sm">
    </div>
    <div class="flex items-center gap-1.5 text-xs">
        <button data-filter="all" class="filter-pill active px-3 py-1.5 rounded-full font-medium" style="background: var(--color-bg-active); color: var(--color-text-primary); border: 1px solid rgba(124,58,237,0.2);">
            Todos · <?= $totalCards ?>
        </button>
        <button data-filter="ai" class="filter-pill px-3 py-1.5 rounded-full font-medium" style="background: var(--color-bg-subtle); color: var(--color-text-tertiary);">
            🤖 IA · <?= $kpis['ai_generated'] ?>
        </button>
        <button data-filter="hot" class="filter-pill px-3 py-1.5 rounded-full font-medium" style="background: var(--color-bg-subtle); color: var(--color-text-tertiary);">
            🔥 Hot (≥70)
        </button>
        <button data-filter="overdue" class="filter-pill px-3 py-1.5 rounded-full font-medium" style="background: var(--color-bg-subtle); color: var(--color-text-tertiary);">
            ⏰ Vencidos
        </button>
    </div>
    <div class="ml-auto text-[10px]" style="color: var(--color-text-muted);">
        <span id="visibleCount"><?= $totalCards ?></span> visibles
    </div>
</div>

<!-- ====== EMPTY STATE GLOBAL ====== -->
<?php if ($totalCards === 0): ?>
<div class="surface p-12 text-center">
    <div class="relative w-24 h-24 mx-auto mb-5">
        <div class="absolute inset-0 rounded-3xl blur-2xl opacity-30" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);"></div>
        <div class="relative w-24 h-24 rounded-3xl flex items-center justify-center text-5xl" style="background: linear-gradient(135deg, rgba(124,58,237,.15), rgba(6,182,212,.15)); border: 1px solid rgba(124,58,237,.3);">
            🎯
        </div>
    </div>
    <h3 class="text-xl font-black mb-2" style="color: var(--color-text-primary);">Tu pipeline está vacío</h3>
    <p class="text-sm mb-6 max-w-md mx-auto" style="color: var(--color-text-tertiary);">
        Los leads aparecerán acá automáticamente cuando: la IA cierre una conversación con interés, llegue una orden por WhatsApp, o un cliente complete un formulario.
    </p>
    <div class="flex justify-center gap-2 flex-wrap">
        <a href="<?= url('/leads/create') ?>" class="px-4 py-2 rounded-xl text-white font-semibold shadow-lg shadow-violet-500/30 flex items-center gap-1.5" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Crear lead manual
        </a>
        <a href="<?= url('/contacts/import') ?>" class="px-4 py-2 rounded-xl font-semibold" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">
            Importar CSV
        </a>
        <a href="<?= url('/settings/channels') ?>" class="px-4 py-2 rounded-xl font-semibold" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">
            Conectar WhatsApp
        </a>
    </div>

    <!-- Mini guía -->
    <div class="grid sm:grid-cols-3 gap-3 mt-8 max-w-2xl mx-auto text-left">
        <div class="rounded-xl p-3 border" style="background: var(--color-bg-subtle); border-color: var(--color-border-subtle);">
            <div class="text-2xl mb-1">🤖</div>
            <div class="font-semibold text-xs mb-0.5" style="color: var(--color-text-primary);">Auto desde IA</div>
            <div class="text-[10px]" style="color: var(--color-text-tertiary);">Cada conversación interesada genera un lead.</div>
        </div>
        <div class="rounded-xl p-3 border" style="background: var(--color-bg-subtle); border-color: var(--color-border-subtle);">
            <div class="text-2xl mb-1">📦</div>
            <div class="font-semibold text-xs mb-0.5" style="color: var(--color-text-primary);">Auto desde órdenes</div>
            <div class="text-[10px]" style="color: var(--color-text-tertiary);">Cada orden de restaurante = lead en pipeline.</div>
        </div>
        <div class="rounded-xl p-3 border" style="background: var(--color-bg-subtle); border-color: var(--color-border-subtle);">
            <div class="text-2xl mb-1">🎯</div>
            <div class="font-semibold text-xs mb-0.5" style="color: var(--color-text-primary);">Etapas auto</div>
            <div class="text-[10px]" style="color: var(--color-text-tertiary);">Status de orden mueve la etapa solo.</div>
        </div>
    </div>
</div>
<?php else: ?>

<!-- ====== KANBAN ====== -->
<div class="overflow-x-auto pb-4 -mx-2 px-2" id="kanbanWrap">
    <div class="flex gap-3 min-w-max" id="kanban-board">
        <?php foreach ($stages as $stage):
            $sid = (int) $stage['id'];
            $count = $totals[$sid]['count'] ?? 0;
            $value = $totals[$sid]['value'] ?? 0;
            $color = (string) $stage['color'];
            $isWon = !empty($stage['is_won']);
            $isLost = !empty($stage['is_lost']);
        ?>
        <div class="w-[300px] flex-shrink-0">
            <!-- Column header -->
            <div class="rounded-2xl p-3 mb-3 sticky top-0 z-10 backdrop-blur-xl"
                 style="background: linear-gradient(135deg, <?= $color ?>1A, <?= $color ?>0A); border: 1px solid <?= $color ?>40; box-shadow: 0 0 24px <?= $color ?>15;">
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" style="background: <?= $color ?>; box-shadow: 0 0 12px <?= $color ?>;"></span>
                        <span class="font-bold text-sm" style="color: var(--color-text-primary);"><?= e($stage['name']) ?></span>
                        <?php if ($isWon): ?><span class="text-[8px] px-1.5 py-0.5 rounded font-bold uppercase" style="background: rgba(34,197,94,.2); color:#22C55E;">Won</span><?php endif; ?>
                        <?php if ($isLost): ?><span class="text-[8px] px-1.5 py-0.5 rounded font-bold uppercase" style="background: rgba(239,68,68,.2); color:#EF4444;">Lost</span><?php endif; ?>
                    </div>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= $count ?></span>
                </div>
                <div class="flex items-center justify-between text-[11px]">
                    <span class="font-mono font-semibold" style="color: var(--color-text-secondary);"><?= e($currency) ?> <?= number_format((float) $value, 0) ?></span>
                    <span class="text-[10px]" style="color: var(--color-text-tertiary);"><?= (int) $stage['probability'] ?>% prob</span>
                </div>
                <!-- Progreso de la columna respecto al total -->
                <?php $colPct = $totalCards > 0 ? round(($count / $totalCards) * 100) : 0; ?>
                <div class="h-1 mt-2 rounded-full overflow-hidden" style="background: rgba(255,255,255,0.05);">
                    <div class="h-full rounded-full transition-all duration-700" style="width: <?= $colPct ?>%; background: <?= $color ?>;"></div>
                </div>
            </div>

            <!-- Drop zone -->
            <div class="space-y-2 min-h-[300px] kanban-column transition rounded-xl p-1"
                 data-stage-id="<?= $sid ?>"
                 data-stage-color="<?= e($color) ?>">
                <?php if (empty($byStage[$sid] ?? [])): ?>
                <div class="rounded-xl p-6 text-center border-2 border-dashed transition-colors hover:border-opacity-60" style="border-color: <?= $color ?>30; color: var(--color-text-tertiary);">
                    <div class="text-2xl mb-1 opacity-50">📥</div>
                    <p class="text-[10px]">Arrastra leads aquí o créalos.</p>
                </div>
                <?php else: foreach (($byStage[$sid] ?? []) as $lead):
                    $name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
                    $isAi = str_starts_with((string) ($lead['source'] ?? ''), 'whatsapp_ia');
                    $aiScore = (int) ($lead['ai_score'] ?? 0);
                    $isHot = $aiScore >= 70;
                    $daysSinceUpdate = isset($lead['updated_at']) ? max(0, (int) ((time() - strtotime((string) $lead['updated_at'])) / 86400)) : 0;
                    $isStale = $daysSinceUpdate >= 7 && !$isWon && !$isLost;
                    $isOverdue = !empty($lead['expected_close']) && strtotime((string) $lead['expected_close']) < time() && !$isWon && !$isLost;
                    $initials = strtoupper(mb_substr($name ?: ($lead['title'] ?? '?'), 0, 1));
                ?>
                <div class="kanban-card group relative cursor-move rounded-xl p-3 transition-all hover:translate-y-[-2px]"
                     style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-subtle);"
                     draggable="true"
                     data-lead-id="<?= (int) $lead['id'] ?>"
                     data-search="<?= e(strtolower(($lead['title'] ?? '') . ' ' . $name . ' ' . ($lead['company'] ?? ''))) ?>"
                     data-ai="<?= $isAi ? '1' : '0' ?>"
                     data-hot="<?= $isHot ? '1' : '0' ?>"
                     data-overdue="<?= $isOverdue ? '1' : '0' ?>">

                    <!-- Drag handle visible on hover -->
                    <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-50 transition-opacity">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" style="color: var(--color-text-muted);">
                            <path d="M7 2a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0zM7 18a2 2 0 11-4 0 2 2 0 014 0zM17 2a2 2 0 11-4 0 2 2 0 014 0zM17 10a2 2 0 11-4 0 2 2 0 014 0zM17 18a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>

                    <a href="<?= url('/leads/' . $lead['id']) ?>" class="block">
                        <!-- Title + badges -->
                        <div class="flex items-start gap-2 mb-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0 text-white" style="background: linear-gradient(135deg, <?= $color ?>, <?= $color ?>aa);">
                                <?= e($initials) ?>
                            </div>
                            <div class="flex-1 min-w-0 pr-4">
                                <div class="font-bold text-[13px] leading-tight mb-0.5 truncate" style="color: var(--color-text-primary);"><?= e($lead['title']) ?></div>
                                <?php if ($name): ?>
                                <div class="text-[10px] truncate" style="color: var(--color-text-tertiary);">
                                    <?= e($name) ?><?php if (!empty($lead['company'])): ?> · <?= e($lead['company']) ?><?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Badges row -->
                        <div class="flex items-center gap-1 flex-wrap mb-2">
                            <?php if ($isAi): ?>
                            <span class="text-[9px] px-1.5 py-0.5 rounded font-bold flex items-center gap-0.5" style="background: rgba(124,58,237,.15); color:#A78BFA;">🤖 IA</span>
                            <?php endif; ?>
                            <?php if ($isHot): ?>
                            <span class="text-[9px] px-1.5 py-0.5 rounded font-bold" style="background: rgba(239,68,68,.15); color:#F87171;">🔥 Hot <?= $aiScore ?></span>
                            <?php elseif ($aiScore > 0): ?>
                            <span class="text-[9px] px-1.5 py-0.5 rounded font-bold" style="background: rgba(124,58,237,.1); color:#A78BFA;">Score <?= $aiScore ?></span>
                            <?php endif; ?>
                            <?php if ($isOverdue): ?>
                            <span class="text-[9px] px-1.5 py-0.5 rounded font-bold" style="background: rgba(239,68,68,.15); color:#F87171;">⏰ Vencido</span>
                            <?php elseif ($isStale): ?>
                            <span class="text-[9px] px-1.5 py-0.5 rounded font-bold" style="background: rgba(245,158,11,.15); color:#F59E0B;">💤 <?= $daysSinceUpdate ?>d</span>
                            <?php endif; ?>
                            <?php if (!empty($lead['agent_first'])): ?>
                            <span class="text-[9px] px-1.5 py-0.5 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-tertiary);"><?= e($lead['agent_first']) ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Footer: value + date -->
                        <div class="flex items-center justify-between gap-2 pt-2 border-t" style="border-color: var(--color-border-subtle);">
                            <div>
                                <div class="text-[15px] font-black tracking-tight" style="color: var(--color-text-primary);">
                                    <?= e((string) ($lead['currency'] ?? $currency)) ?> <?= number_format((float) $lead['value'], 0) ?>
                                </div>
                                <?php if (!empty($lead['probability']) && (int) $lead['probability'] > 0): ?>
                                <div class="text-[9px] mt-0.5 font-semibold" style="color: var(--color-text-tertiary);">
                                    × <?= (int) $lead['probability'] ?>% = <?= e((string) ($lead['currency'] ?? $currency)) ?> <?= number_format((float) $lead['value'] * ((int) $lead['probability'] / 100), 0) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <?php if (!empty($lead['expected_close'])): ?>
                                <div class="text-[9px] font-mono" style="color: <?= $isOverdue ? '#F87171' : 'var(--color-text-tertiary)' ?>;"><?= date('d M', strtotime((string) $lead['expected_close'])) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($lead['phone']) || !empty($lead['whatsapp'])): ?>
                                <div class="text-[9px] mt-0.5" style="color: var(--color-text-muted);">📱</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
.kanban-column.drag-over {
    background: var(--color-bg-active);
    box-shadow: inset 0 0 0 2px var(--color-primary);
    border-radius: 12px;
}
.kanban-card.dragging { opacity: 0.4; transform: scale(0.95) rotate(-2deg); }
.kanban-card { box-shadow: 0 1px 3px rgba(0,0,0,.05); }
.kanban-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,.12), 0 0 0 1px rgba(124,58,237,.3);
    border-color: rgba(124,58,237,.4) !important;
}
.filter-pill { transition: all 0.15s; }
.filter-pill:hover { color: var(--color-text-primary) !important; }
.filter-pill.active { background: var(--color-bg-active) !important; color: var(--color-text-primary) !important; border: 1px solid rgba(124,58,237,0.2) !important; }
.kanban-card.hidden { display: none; }
</style>

<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let draggedCard = null;

    // ============== Drag & drop ==============
    document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', e => {
            draggedCard = card;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => {
            if (draggedCard) draggedCard.classList.remove('dragging');
            draggedCard = null;
            document.querySelectorAll('.kanban-column').forEach(c => c.classList.remove('drag-over'));
        });
    });

    document.querySelectorAll('.kanban-column').forEach(col => {
        col.addEventListener('dragover', e => {
            e.preventDefault();
            col.classList.add('drag-over');
        });
        col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
        col.addEventListener('drop', async e => {
            e.preventDefault();
            col.classList.remove('drag-over');
            if (!draggedCard) return;

            const stageId = col.dataset.stageId;
            const leadId  = draggedCard.dataset.leadId;
            const fromStage = draggedCard.parentElement.dataset.stageId;
            if (fromStage === stageId) return;

            col.appendChild(draggedCard);

            try {
                const res = await fetch('<?= url('/leads/move') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ lead_id: leadId, stage_id: stageId }),
                });
                const data = await res.json();
                if (!data.success) {
                    alert('Error moviendo lead: ' + (data.error || ''));
                } else {
                    // Sutil feedback visual
                    draggedCard.style.transition = 'background 0.4s';
                    draggedCard.style.background = 'rgba(34,197,94,.08)';
                    setTimeout(() => { draggedCard.style.background = ''; }, 800);
                }
            } catch (err) {
                alert('Error de red');
            }
        });
    });

    // ============== Search ==============
    const searchInput = document.getElementById('leadSearch');
    const visibleCount = document.getElementById('visibleCount');
    let activeFilter = 'all';

    function applyFilters() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('.kanban-card').forEach(card => {
            const matchesSearch = !query || (card.dataset.search || '').includes(query);
            let matchesFilter = true;
            if (activeFilter === 'ai')      matchesFilter = card.dataset.ai === '1';
            if (activeFilter === 'hot')     matchesFilter = card.dataset.hot === '1';
            if (activeFilter === 'overdue') matchesFilter = card.dataset.overdue === '1';

            if (matchesSearch && matchesFilter) {
                card.classList.remove('hidden');
                visible++;
            } else {
                card.classList.add('hidden');
            }
        });
        if (visibleCount) visibleCount.textContent = visible;
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    document.querySelectorAll('.filter-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeFilter = btn.dataset.filter;
            applyFilters();
        });
    });
})();
</script>

<?php \App\Core\View::stop(); ?>
