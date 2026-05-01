<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>

<a href="<?= url('/tickets') ?>" class="text-xs flex items-center gap-1 mb-3" style="color: var(--color-text-tertiary);">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Volver a tickets
</a>

<form action="<?= url('/tickets') ?>" method="POST" class="form-shell with-aside" id="ticketForm">
    <?= csrf_field() ?>

    <div>
        <!-- Hero -->
        <div class="form-hero mb-4">
            <div class="form-hero-icon">🎫</div>
            <div class="relative z-10">
                <h1 class="text-xl font-black tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;">Nuevo ticket de soporte</h1>
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">Registra un caso para hacerle seguimiento, asignarlo a un agente y medir el SLA.</p>
            </div>
        </div>

        <!-- Section 1: Caso -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="form-section-icon">📋</div>
                <div>
                    <div class="form-section-title">El caso</div>
                    <div class="form-section-sub">Asunto claro y descripción detallada para que cualquier agente entienda el contexto.</div>
                </div>
            </div>

            <div class="field">
                <label class="label">Asunto <span style="color:#F87171;">*</span></label>
                <div class="input-with-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                    <input type="text" name="subject" required value="<?= old('subject') ?>" class="input" placeholder="Ej: No puedo activar mi suscripción">
                </div>
                <?php if ($e = error_for('subject', $errors)): ?>
                <p class="field-error"><?= e($e) ?></p>
                <?php else: ?>
                <p class="field-help">Sé específico — el cliente lo verá en sus emails de seguimiento.</p>
                <?php endif; ?>
            </div>

            <div class="field mt-4">
                <label class="label">Descripción</label>
                <textarea name="description" rows="6" class="textarea" placeholder="Describe el problema, pasos para reproducirlo, mensajes de error, capturas, contexto del cliente..."></textarea>
            </div>
        </div>

        <!-- Section 2: Routing -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="form-section-icon" style="background: rgba(245,158,11,.12); color:#F59E0B;">⚡</div>
                <div>
                    <div class="form-section-title">Prioridad y asignación</div>
                    <div class="form-section-sub">Cómo de urgente es y quién lo resuelve.</div>
                </div>
            </div>

            <div class="form-row cols-3">
                <div class="field">
                    <label class="label">Prioridad</label>
                    <select name="priority" class="select">
                        <option value="low">🟢 Baja</option>
                        <option value="medium" selected>🟡 Media</option>
                        <option value="high">🟠 Alta</option>
                        <option value="critical">🔴 Crítica</option>
                    </select>
                </div>
                <div class="field">
                    <label class="label">Categoría</label>
                    <select name="category" class="select">
                        <?php foreach (['general'=>'General','soporte'=>'Soporte técnico','facturacion'=>'Facturación','envio'=>'Envío','reclamo'=>'Reclamo','otro'=>'Otro'] as $v=>$lbl): ?>
                        <option value="<?= $v ?>"><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label">Canal</label>
                    <select name="channel" class="select">
                        <option value="whatsapp">💬 WhatsApp</option>
                        <option value="email">✉ Email</option>
                        <option value="web">🌐 Sitio web</option>
                        <option value="telefono">📞 Teléfono</option>
                        <option value="presencial">🏢 Presencial</option>
                    </select>
                </div>
            </div>

            <div class="form-row mt-4">
                <div class="field">
                    <label class="label">Asignar a</label>
                    <div class="input-with-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <select name="assigned_to" class="select">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($agents as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="field-help">Si lo dejas sin asignar, las reglas de routing decidirán por ti.</p>
                </div>
            </div>
        </div>

        <div class="form-actions elevated mt-4">
            <a href="<?= url('/tickets') ?>" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-submit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Crear ticket
            </button>
        </div>
    </div>

    <aside class="space-y-3">
        <div class="form-tip">
            <div class="form-tip-title">💡 Sobre prioridades</div>
            <ul class="text-xs space-y-1">
                <li><strong>🔴 Crítica</strong>: cliente bloqueado, ingresos en riesgo</li>
                <li><strong>🟠 Alta</strong>: error grave pero hay workaround</li>
                <li><strong>🟡 Media</strong>: defecto sin urgencia</li>
                <li><strong>🟢 Baja</strong>: mejora o feedback</li>
            </ul>
        </div>

        <div class="form-tip" style="background: linear-gradient(135deg, rgba(16,185,129,.06), rgba(6,182,212,.04)); border-color: rgba(16,185,129,.2);">
            <div class="form-tip-title" style="color:#34D399;">🤖 Tip IA</div>
            <p>Tras crear el ticket, abre el chat asociado y la IA puede sugerir respuesta basada en tu base de conocimiento.</p>
        </div>
    </aside>
</form>

<script>
(function () {
    const form = document.getElementById('ticketForm');
    if (!form) return;
    form.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') { e.preventDefault(); form.requestSubmit(); }
        if (e.key === 'Escape') { window.location.href = '<?= url('/tickets') ?>'; }
    });
})();
</script>
<?php \App\Core\View::stop(); ?>
