<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'quick_replies']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Inteligencia',
    'title'    => 'Respuestas rapidas',
    'subtitle' => 'Plantillas que tus agentes pueden insertar con un atajo (/comando) en el inbox.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4">
    <!-- Form crear -->
    <form action="<?= url('/settings/quick-replies') ?>" method="POST" class="set-section h-fit" style="margin-bottom:0;">
        <?= csrf_field() ?>
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>➕</span> Nueva respuesta</h2>
                <p class="set-section-desc">Los agentes la insertan escribiendo el atajo en el inbox.</p>
            </div>
        </div>

        <div class="set-field">
            <label class="set-label">Atajo <span class="req">*</span></label>
            <input type="text" name="shortcut" required maxlength="40"
                   placeholder="/saludo"
                   class="set-input" style="font-family: 'JetBrains Mono', monospace;">
            <p class="set-help">Empieza con / por convencion.</p>
        </div>

        <div class="set-field">
            <label class="set-label">Titulo (opcional)</label>
            <input type="text" name="title" maxlength="120"
                   placeholder="Saludo inicial"
                   class="set-input">
        </div>

        <div class="set-field">
            <label class="set-label">Contenido <span class="req">*</span></label>
            <textarea name="body" rows="5" required
                      placeholder="Hola {nombre}, gracias por contactarnos..."
                      class="set-textarea"></textarea>
        </div>

        <button type="submit" class="set-btn set-btn-primary w-full">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Guardar
        </button>
    </form>

    <!-- Lista -->
    <div class="lg:col-span-2">
        <?php if (empty($replies)): ?>
        <div class="set-section">
            <div class="set-empty">
                <div class="set-empty-ico">⚡</div>
                <div class="set-empty-title">Aun no hay respuestas rapidas</div>
                <div class="set-empty-desc">Crea la primera con el formulario de la izquierda.</div>
            </div>
        </div>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($replies as $r): ?>
            <div class="set-section" style="margin-bottom:0; padding: 16px;">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap mb-1.5">
                            <code style="font-size:12px; padding: 2px 8px; border-radius: 6px; background: rgba(139,92,246,.12); color: #7C3AED; font-weight:600;"><?= e($r['shortcut']) ?></code>
                            <?php if (!empty($r['title'])): ?>
                            <span class="font-semibold text-sm" style="color: var(--color-text-primary);"><?= e($r['title']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm" style="color: var(--color-text-secondary); white-space: pre-line; line-height:1.55;"><?= e((string) $r['body']) ?></p>
                    </div>
                    <form action="<?= url('/settings/quick-replies/' . $r['id']) ?>" method="POST" onsubmit="return confirm('Eliminar esta respuesta?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button class="set-btn set-btn-sm set-btn-danger" title="Eliminar">×</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
