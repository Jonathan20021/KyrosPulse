<?php
/**
 * @var array $workflows
 * @var array $featuredTemplates  hasta 3 templates destacados del marketplace
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$featuredTemplates = $featuredTemplates ?? [];
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Workflows',
    'subtitle' => 'Orquestador OS-style: triggers + steps tipados con branching, delays y reanudacion automatica. Conecta agentes IA, mensajes WhatsApp, HTTP calls y mas.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<!-- Hero: marketplace CTA -->
<?php if (!empty($featuredTemplates)): ?>
<div class="surface p-5 mb-5" style="background: linear-gradient(135deg, rgba(139,92,246,.06), rgba(6,182,212,.04));">
    <div class="flex items-start justify-between gap-4 flex-wrap mb-3">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-2xl">🪄</span>
                <h3 class="font-bold text-lg" style="color: var(--color-text-primary);">Empieza con un template listo</h3>
            </div>
            <p class="text-sm" style="color: var(--color-text-secondary);">Templates pre-armados que clonas con 1 click. Te ahorran armar workflows desde cero. Editas todo despues si quieres.</p>
        </div>
        <a href="<?= url('/workflows/templates') ?>" class="px-4 py-2 rounded-xl text-white font-semibold shadow-lg whitespace-nowrap"
           style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">
            Ver galeria completa →
        </a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
        <?php foreach ($featuredTemplates as $t): ?>
        <a href="<?= url('/workflows/templates/' . (int) $t['id']) ?>" class="surface p-3 hover:scale-[1.01] transition-transform flex items-start gap-2.5">
            <div class="w-9 h-9 rounded-lg flex-shrink-0 flex items-center justify-center text-base" style="background: var(--color-bg-secondary);">
                <?= e((string) ($t['icon'] ?? '🪄')) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm leading-tight" style="color: var(--color-text-primary);"><?= e((string) $t['name']) ?></div>
                <div class="text-[10px] mt-0.5" style="color: var(--color-text-tertiary);"><?= e((string) ($t['category'] ?? 'general')) ?> · <?= (int) ($t['clone_count'] ?? 0) ?> clones</div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Form crear -->
<div x-data="{ open: false, type: 'event' }" class="mb-6">
    <div class="flex items-center gap-2 flex-wrap">
        <button @click="open = !open" type="button"
                class="px-4 py-2.5 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2"
                style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuevo workflow (en blanco)
        </button>
        <a href="<?= url('/workflows/templates') ?>" class="px-4 py-2.5 rounded-xl font-semibold flex items-center gap-2 border" style="border-color: var(--color-border); color: var(--color-text-primary);">
            🪄 Crear desde template
        </a>
    </div>

    <div x-show="open" x-cloak x-transition class="surface mt-4 p-5">
        <form action="<?= url('/workflows') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="label">Nombre</label>
                    <input name="name" required maxlength="120" placeholder="Ej: Onboarding cliente nuevo" class="input">
                </div>
                <div>
                    <label class="label">Trigger</label>
                    <select name="trigger_type" x-model="type" class="select">
                        <option value="event">Evento del sistema</option>
                        <option value="schedule">Programado (cron)</option>
                        <option value="webhook">Webhook publico (URL)</option>
                        <option value="manual">Manual (boton)</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="label">Descripcion (opcional)</label>
                <input name="description" maxlength="500" placeholder="Que hace este workflow" class="input">
            </div>
            <div x-show="type === 'event'">
                <label class="label">Evento que dispara</label>
                <input name="trigger_event" placeholder="order.created, agent.run.completed, contact.created, *" class="input">
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">Soporta wildcards: <code>order.*</code> dispara con cualquier evento de orden.</p>
            </div>
            <div x-show="type === 'schedule'">
                <label class="label">Cron expression</label>
                <input name="trigger_cron" placeholder="0 9 * * *" class="input">
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">5 campos: m h dom mon dow. Ej: <code>0 9 * * *</code> = todos los dias 9 AM.</p>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" @click="open = false" class="px-4 py-2 rounded-lg text-sm font-semibold border" style="border-color: var(--color-border); color: var(--color-text-primary);">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">Crear</button>
            </div>
        </form>
    </div>
</div>

<!-- Lista -->
<?php if (empty($workflows)): ?>
<div class="surface p-10 text-center">
    <div class="text-5xl mb-3">🪄</div>
    <h3 class="font-bold text-lg mb-1" style="color: var(--color-text-primary);">Sin workflows aun</h3>
    <p class="text-sm" style="color: var(--color-text-secondary);">Crea el primero arriba. Por ejemplo: "cuando entra una orden nueva → run agente IA → enviar whatsapp con resumen → esperar 1h → notificar a slack si no fue confirmada".</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <?php foreach ($workflows as $w):
        $cfg = $w['trigger_config'] ? json_decode((string) $w['trigger_config'], true) : [];
        if (!is_array($cfg)) $cfg = [];
        $triggerSummary = match ($w['trigger_type']) {
            'event'    => 'evento: ' . ($cfg['event'] ?? '*'),
            'schedule' => 'cron: ' . ($cfg['cron'] ?? '?'),
            'webhook'  => 'webhook publico',
            'manual'   => 'manual',
            default    => $w['trigger_type'],
        };
    ?>
    <a href="<?= url('/workflows/' . (int) $w['id']) ?>" class="surface p-4 hover:scale-[1.005] transition-transform <?= empty($w['is_active']) ? 'opacity-60' : '' ?>">
        <div class="flex items-start gap-3 mb-2">
            <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center text-lg"
                 style="background: <?= !empty($w['is_active']) ? 'linear-gradient(135deg,#8B5CF6,#06B6D4)' : 'rgba(148,163,184,.2)' ?>; color: white;">🪄</div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-0.5">
                    <span class="font-semibold" style="color: var(--color-text-primary);"><?= e((string) $w['name']) ?></span>
                    <?php if (empty($w['is_active'])): ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(148,163,184,.15); color: #475569;">Pausado</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($w['description'])): ?>
                <p class="text-xs" style="color: var(--color-text-secondary);"><?= e(mb_substr((string) $w['description'], 0, 100)) ?></p>
                <?php endif; ?>
                <div class="text-[11px] mt-1.5 flex items-center gap-2 flex-wrap" style="color: var(--color-text-tertiary);">
                    <span><?= e($triggerSummary) ?></span>
                    <span>·</span>
                    <span><?= (int) ($w['steps_count'] ?? 0) ?> steps</span>
                    <span>·</span>
                    <span><?= number_format((int) $w['runs_count']) ?> runs</span>
                    <?php if (!empty($w['last_run_at'])): ?>
                    <span>·</span>
                    <span>ult: <?= e((string) $w['last_run_at']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php \App\Core\View::end(); ?>
