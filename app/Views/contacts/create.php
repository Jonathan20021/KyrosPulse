<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>

<a href="<?= url('/contacts') ?>" class="text-xs flex items-center gap-1 mb-3" style="color: var(--color-text-tertiary);">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Volver a contactos
</a>

<form action="<?= url('/contacts') ?>" method="POST" class="form-shell with-aside" id="contactForm">
    <?= csrf_field() ?>

    <div>
        <!-- Hero -->
        <div class="form-hero mb-4">
            <div class="form-hero-icon">👥</div>
            <div class="relative z-10">
                <h1 class="text-xl font-black tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;">Nuevo contacto</h1>
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">Crea un cliente, lead o prospecto. Si tiene WhatsApp, la IA lo atenderá automáticamente cuando escriba.</p>
            </div>
        </div>

        <?php \App\Core\View::include('contacts._form', ['contact' => null, 'tags' => [], 'agents' => $agents, 'allTags' => $allTags, 'errors' => $errors]); ?>

        <div class="form-actions elevated mt-4">
            <a href="<?= url('/contacts') ?>" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-submit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Guardar contacto
            </button>
        </div>
    </div>

    <aside class="space-y-3">
        <div class="form-tip">
            <div class="form-tip-title">💡 Recomendación</div>
            <p>Carga al menos <strong>nombre + WhatsApp</strong>. Cuando el cliente escriba, la IA tendrá contexto y podrá personalizar el saludo.</p>
        </div>

        <div class="form-tip" style="background: linear-gradient(135deg, rgba(16,185,129,.06), rgba(6,182,212,.04)); border-color: rgba(16,185,129,.2);">
            <div class="form-tip-title" style="color:#34D399;">🎯 Auto-enrich</div>
            <p>Si el contacto luego escribe por WhatsApp con foto de perfil, la IA enriquece <strong>automáticamente</strong> nombre, idioma y zona horaria.</p>
        </div>

        <div class="form-tip" style="background: linear-gradient(135deg, rgba(245,158,11,.06), rgba(244,63,94,.04)); border-color: rgba(245,158,11,.2);">
            <div class="form-tip-title" style="color:#F59E0B;">📥 Importar masivo</div>
            <p>Para cargar muchos contactos a la vez, usa <a href="<?= url('/contacts/import') ?>" class="font-semibold underline" style="color: var(--color-primary);">importar CSV</a>.</p>
        </div>
    </aside>
</form>

<script>
(function () {
    const form = document.getElementById('contactForm');
    if (!form) return;
    form.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') { e.preventDefault(); form.requestSubmit(); }
        if (e.key === 'Escape') { window.location.href = '<?= url('/contacts') ?>'; }
    });
    form.addEventListener('submit', () => {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }
    });
})();
</script>
<?php \App\Core\View::stop(); ?>
