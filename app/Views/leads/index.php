<?php
/** @var array $stages */
/** @var array $byStage */
/** @var array $totals */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>

<?php \App\Core\View::include('components.page_header', [
    'title'       => 'Pipeline de ventas',
    'subtitle'    => 'Arrastra y suelta para mover leads entre etapas. La probabilidad se actualiza automaticamente.',
    'actionUrl'   => url('/leads/create'),
    'actionLabel' => 'Nuevo lead',
    'actionIcon'  => 'plus',
]); ?>

<div class="overflow-x-auto pb-4 -mx-2 px-2">
    <div class="flex gap-3 min-w-max" id="kanban-board">
        <?php foreach ($stages as $stage):
            $sid = (int) $stage['id'];
            $count = $totals[$sid]['count'] ?? 0;
            $value = $totals[$sid]['value'] ?? 0;
        ?>
        <div class="w-[300px] flex-shrink-0">
            <div class="surface p-3 mb-3" style="border-top: 3px solid <?= e((string) $stage['color']) ?>;">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" style="background: <?= e((string) $stage['color']) ?>"></span>
                        <span class="font-bold text-sm" style="color: var(--color-text-primary);"><?= e($stage['name']) ?></span>
                    </div>
                    <span class="badge badge-slate"><?= $count ?></span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span style="color: var(--color-text-tertiary);"><?= format_currency((float) $value) ?></span>
                    <span style="color: var(--color-text-muted);"><?= (int) $stage['probability'] ?>%</span>
                </div>
            </div>

            <div class="space-y-2 min-h-[200px] kanban-column transition rounded-xl p-1"
                 data-stage-id="<?= $sid ?>"
                 data-stage-color="<?= e((string) $stage['color']) ?>">
                <?php foreach (($byStage[$sid] ?? []) as $lead):
                    $name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
                ?>
                <div class="kanban-card surface p-3 cursor-move hover-lift"
                     draggable="true"
                     data-lead-id="<?= (int) $lead['id'] ?>">
                    <a href="<?= url('/leads/' . $lead['id']) ?>" class="block">
                        <div class="font-semibold text-sm mb-1 truncate" style="color: var(--color-text-primary);"><?= e($lead['title']) ?></div>
                        <?php if ($name): ?>
                        <div class="text-xs mb-2 truncate flex items-center gap-1.5" style="color: var(--color-text-tertiary);">
                            <div class="avatar avatar-sm w-4 h-4" style="font-size: 8px;"><?= e(strtoupper(mb_substr($name, 0, 1))) ?></div>
                            <span class="truncate"><?= e($name) ?><?php if (!empty($lead['company'])): ?> · <?= e($lead['company']) ?><?php endif; ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="flex items-center justify-between gap-2 pt-2 border-t" style="border-color: var(--color-border-subtle);">
                            <span class="font-bold text-sm" style="color: var(--color-text-primary);"><?= format_currency((float) $lead['value'], (string) ($lead['currency'] ?? 'USD')) ?></span>
                            <?php if (!empty($lead['ai_score']) && (int) $lead['ai_score'] > 0): ?>
                            <span class="badge badge-primary">🤖 <?= (int) $lead['ai_score'] ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($lead['expected_close'])): ?>
                        <div class="text-[10px] mt-2" style="color: var(--color-text-muted);">📅 <?= date('d M', strtotime($lead['expected_close'])) ?></div>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.kanban-column.drag-over {
    background: var(--color-bg-active);
    box-shadow: inset 0 0 0 2px var(--color-primary);
}
.kanban-card.dragging { opacity: 0.4; transform: scale(0.95); }
</style>

<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let draggedCard = null;

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
                if (!data.success) alert('Error: ' + (data.error || ''));
            } catch (err) {
                alert('Error de red');
            }
        });
    });
})();
</script>

<?php \App\Core\View::stop(); ?>
