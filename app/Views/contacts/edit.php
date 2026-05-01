<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
$initials = strtoupper(mb_substr($name ?: '?', 0, 1));
?>

<a href="<?= url('/contacts/' . $contact['id']) ?>" class="text-xs flex items-center gap-1 mb-3" style="color: var(--color-text-tertiary);">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Volver al contacto
</a>

<div class="form-shell with-aside">
    <div>
        <!-- Hero -->
        <div class="form-hero mb-4">
            <div class="form-hero-icon"><?= e($initials) ?></div>
            <div class="relative z-10 flex-1">
                <h1 class="text-xl font-black tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;">Editar contacto</h1>
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">Actualizando <strong style="color: var(--color-text-primary);"><?= e($name) ?></strong>. Los cambios se aplican inmediatamente.</p>
                <div class="flex items-center gap-2 mt-2 flex-wrap">
                    <?php if (!empty($contact['status'])): ?>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);"><?= e($contact['status']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($contact['email'])): ?>
                    <span class="text-[11px]" style="color: var(--color-text-tertiary);">✉ <?= e((string) $contact['email']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($contact['phone']) || !empty($contact['whatsapp'])): ?>
                    <span class="text-[11px]" style="color: var(--color-text-tertiary);">📱 <?= e((string) ($contact['whatsapp'] ?: $contact['phone'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form action="<?= url('/contacts/' . $contact['id']) ?>" method="POST" id="contactForm">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">
            <?php \App\Core\View::include('contacts._form', ['contact' => $contact, 'tags' => $tags, 'agents' => $agents, 'allTags' => $allTags, 'errors' => $errors]); ?>

            <div class="form-actions elevated mt-4">
                <button type="button" onclick="document.getElementById('deleteForm').submit()" class="btn-cancel mr-auto" style="color:#F87171;" onclick="return confirm('Eliminar contacto?')">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Eliminar
                </button>
                <a href="<?= url('/contacts/' . $contact['id']) ?>" class="btn-cancel">Cancelar</a>
                <button type="submit" class="btn-submit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Guardar cambios
                </button>
            </div>
        </form>

        <!-- Form de eliminar separado para evitar nested forms -->
        <form action="<?= url('/contacts/' . $contact['id']) ?>" method="POST" id="deleteForm" class="hidden" onsubmit="return confirm('¿Eliminar este contacto definitivamente?')">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="DELETE">
        </form>
    </div>

    <aside class="space-y-3">
        <div class="form-tip">
            <div class="form-tip-title">📊 Stats</div>
            <ul class="space-y-1 text-xs">
                <?php if (!empty($contact['estimated_value']) && (float) $contact['estimated_value'] > 0): ?>
                <li>Valor estimado: <strong><?= number_format((float) $contact['estimated_value'], 2) ?></strong></li>
                <?php endif; ?>
                <?php if (!empty($contact['score'])): ?>
                <li>Score IA: <strong><?= (int) $contact['score'] ?>/100</strong></li>
                <?php endif; ?>
                <?php if (!empty($contact['last_interaction'])): ?>
                <li>Ultima interaccion: <strong><?= date('d M Y', strtotime((string) $contact['last_interaction'])) ?></strong></li>
                <?php endif; ?>
                <?php if (!empty($contact['lifecycle_stage'])): ?>
                <li>Etapa lifecycle: <strong><?= e((string) $contact['lifecycle_stage']) ?></strong></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="form-tip" style="background: linear-gradient(135deg, rgba(244,63,94,.06), rgba(245,158,11,.04)); border-color: rgba(244,63,94,.2);">
            <div class="form-tip-title" style="color:#F87171;">⚠ Cuidado</div>
            <p>Eliminar el contacto <strong>borra también</strong> sus conversaciones, leads asociados y notas. Esta acción no se puede deshacer.</p>
        </div>
    </aside>
</div>

<script>
(function () {
    const form = document.getElementById('contactForm');
    if (!form) return;
    form.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') { e.preventDefault(); form.requestSubmit(); }
        if (e.key === 'Escape') { window.location.href = '<?= url('/contacts/' . $contact['id']) ?>'; }
    });
})();
</script>
<?php \App\Core\View::stop(); ?>
