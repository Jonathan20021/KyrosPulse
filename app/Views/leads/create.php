<?php
/** @var array $stages */
/** @var array $agents */
/** @var array|null $contact */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>

<a href="<?= url('/leads') ?>" class="text-xs flex items-center gap-1 mb-3" style="color: var(--color-text-tertiary);">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Volver al pipeline
</a>

<form action="<?= url('/leads') ?>" method="POST" class="form-shell with-aside" id="leadForm">
    <?= csrf_field() ?>
    <?php if ($contact): ?><input type="hidden" name="contact_id" value="<?= (int) $contact['id'] ?>"><?php endif; ?>

    <!-- Main column -->
    <div>
        <!-- Hero -->
        <div class="form-hero mb-4">
            <div class="form-hero-icon">📈</div>
            <div class="relative z-10">
                <h1 class="text-xl font-black tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;">Nueva oportunidad de venta</h1>
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">Captura un lead, asignalo y muevelo en el pipeline. Si el cliente firma, se cerrara como ganado automaticamente.</p>
                <?php if ($contact): ?>
                <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs" style="background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.3); color: #34D399;">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Asociado a <strong><?= e(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section 1: Info basica -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="form-section-icon">📝</div>
                <div>
                    <div class="form-section-title">Informacion basica</div>
                    <div class="form-section-sub">Como llamarias a esta oportunidad y en que fase del proceso esta.</div>
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label class="label">Titulo de la oportunidad <span style="color:#F87171;">*</span></label>
                    <div class="input-with-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <input type="text" name="title" required value="<?= old('title') ?>" placeholder="Ej: Cotización 50 unidades — Cliente Pro" class="input">
                    </div>
                    <?php if ($e = error_for('title', $errors)): ?>
                    <p class="field-error"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg><?= e($e) ?></p>
                    <?php else: ?>
                    <p class="field-help">Un titulo descriptivo te ayuda a identificar el deal en el kanban.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row cols-2 mt-4">
                <div class="field">
                    <label class="label">Etapa <span style="color:#F87171;">*</span></label>
                    <div class="input-with-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        <select name="stage_id" required class="select">
                            <?php foreach ($stages as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> · <?= (int) $s['probability'] ?>% probabilidad</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Responsable</label>
                    <div class="input-with-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <select name="assigned_to" class="select">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($agents as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Detalles comerciales -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="form-section-icon" style="background: rgba(16,185,129,.12); color:#34D399;">💰</div>
                <div>
                    <div class="form-section-title">Detalles comerciales</div>
                    <div class="form-section-sub">Valor, moneda y fecha esperada de cierre. Esto alimenta el dashboard.</div>
                </div>
            </div>

            <div class="form-row cols-2-1">
                <div class="field">
                    <label class="label">Valor estimado</label>
                    <div class="flex gap-2">
                        <div class="input-with-icon flex-1">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                            <input type="number" step="0.01" min="0" name="value" value="<?= old('value', '0') ?>" class="input" placeholder="0.00">
                        </div>
                        <select name="currency" class="select" style="width: 90px;">
                            <option>USD</option><option>EUR</option><option>DOP</option><option>MXN</option><option>COP</option><option>PEN</option><option>ARS</option>
                        </select>
                    </div>
                    <p class="field-help">El monto que cobrarás si la venta se cierra.</p>
                </div>

                <div class="field">
                    <label class="label">Cierre esperado</label>
                    <div class="input-with-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <input type="date" name="expected_close" class="input" value="<?= old('expected_close', date('Y-m-d', strtotime('+7 days'))) ?>">
                    </div>
                </div>
            </div>

            <div class="form-row cols-2 mt-4">
                <div class="field">
                    <label class="label">Fuente</label>
                    <div class="input-with-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <select name="source" class="select">
                            <?php $sources = ['manual' => 'Manual', 'whatsapp' => 'WhatsApp', 'whatsapp_ia' => 'WhatsApp IA', 'web' => 'Sitio web', 'referido' => 'Referido', 'campaña' => 'Campaña', 'evento' => 'Evento', 'otro' => 'Otro']; ?>
                            <?php foreach ($sources as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= old('source', 'manual') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Tags (opcional, separadas por coma)</label>
                    <div class="input-with-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        <input type="text" name="tags" placeholder="vip, urgente, b2b" class="input">
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Notas -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="form-section-icon" style="background: rgba(245,158,11,.12); color:#F59E0B;">📓</div>
                <div>
                    <div class="form-section-title">Contexto y notas</div>
                    <div class="form-section-sub">Lo que sepas del cliente: necesidad, objeciones, timeline, decisor.</div>
                </div>
            </div>

            <div class="field">
                <label class="label">Descripcion</label>
                <textarea name="description" rows="5" class="textarea" placeholder="Ej: Cliente busca solucion para 50 sucursales. Decisor es CFO. Cierre antes Q2. Ya vio demo y enviamos cotizacion..."></textarea>
                <p class="field-help">Esta info la usa la IA para sugerirte proxima accion y calificar el lead.</p>
            </div>
        </div>

        <!-- Footer actions -->
        <div class="form-actions elevated mt-4">
            <a href="<?= url('/leads') ?>" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-submit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Crear lead
            </button>
        </div>
    </div>

    <!-- Aside -->
    <aside class="space-y-3">
        <div class="form-tip">
            <div class="form-tip-title">💡 Tip</div>
            <p>Si conectas WhatsApp y un cliente se interesa, la IA crea el lead <strong>sola</strong> en la etapa correcta. Esto es para registros manuales.</p>
        </div>

        <div class="form-tip" style="background: linear-gradient(135deg, rgba(16,185,129,.06), rgba(6,182,212,.04)); border-color: rgba(16,185,129,.2);">
            <div class="form-tip-title" style="color:#34D399;">⚡ Atajos</div>
            <ul class="space-y-1 text-xs">
                <li><span class="kbd">Tab</span> para navegar campos</li>
                <li><span class="kbd">⌘</span><span class="kbd">↵</span> envía el form</li>
                <li><span class="kbd">Esc</span> cancela</li>
            </ul>
        </div>

        <div class="form-tip" style="background: linear-gradient(135deg, rgba(245,158,11,.06), rgba(244,63,94,.04)); border-color: rgba(245,158,11,.2);">
            <div class="form-tip-title" style="color:#F59E0B;">🤖 Auto-magic</div>
            <p>La IA recalcula el <strong>score</strong> del lead cuando lo guardes. Etapas con probabilidad alta priorizan el dashboard.</p>
        </div>
    </aside>
</form>

<script>
(function () {
    const form = document.getElementById('leadForm');
    if (!form) return;
    // Cmd/Ctrl + Enter envía
    form.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault(); form.requestSubmit();
        }
        if (e.key === 'Escape') {
            window.location.href = '<?= url('/leads') ?>';
        }
    });
    // Disable submit while submitting (anti double-click)
    form.addEventListener('submit', () => {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-opacity="0.3"/><path d="M12 2a10 10 0 0110 10"/></svg> Creando...';
        }
    });
})();
</script>
<?php \App\Core\View::stop(); ?>
