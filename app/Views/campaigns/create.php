<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$errors = $errors ?? [];
$templates = $templates ?? [];
?>

<a href="<?= url('/campaigns') ?>" class="text-xs flex items-center gap-1 mb-3" style="color: var(--color-text-tertiary);">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Volver a campañas
</a>

<form action="<?= url('/campaigns') ?>" method="POST" class="form-shell with-aside" id="campaignForm">
    <?= csrf_field() ?>

    <div>
        <!-- Hero -->
        <div class="form-hero mb-4">
            <div class="form-hero-icon">📣</div>
            <div class="relative z-10">
                <h1 class="text-xl font-black tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;">Nueva campaña masiva</h1>
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">Define mensaje, audiencia y programación. Para envíos fuera de la ventana de 24h usa una plantilla aprobada por Meta.</p>
            </div>
        </div>

        <!-- Section 1: Info basica -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="form-section-icon">📝</div>
                <div>
                    <div class="form-section-title">Información básica</div>
                    <div class="form-section-sub">Nombre interno y canal de envío.</div>
                </div>
            </div>

            <div class="form-row cols-2">
                <div class="field">
                    <label class="label">Nombre interno <span style="color:#F87171;">*</span></label>
                    <div class="input-with-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                        <input type="text" name="name" required value="<?= old('name') ?>" class="input" placeholder="Ej: Black Friday 50% OFF — Octubre">
                    </div>
                    <p class="field-help">Solo lo verás tú. El cliente no ve este nombre.</p>
                </div>
                <div class="field">
                    <label class="label">Canal</label>
                    <div class="input-with-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <select name="channel" class="select">
                            <option value="whatsapp">💬 WhatsApp</option>
                            <option value="email">✉ Email</option>
                            <option value="sms">📱 SMS</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Mensaje -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="form-section-icon" style="background: rgba(16,185,129,.12); color:#34D399;">💬</div>
                <div>
                    <div class="form-section-title">Mensaje</div>
                    <div class="form-section-sub">Esto es lo que recibirá cada contacto. Personaliza con variables para mayor conversión.</div>
                </div>
            </div>

            <div class="field">
                <label class="label">Plantilla aprobada (recomendado para envíos masivos)</label>
                <select name="template_id" class="select">
                    <option value="">— Mensaje libre (solo dentro de ventana 24h) —</option>
                    <?php foreach ($templates as $tpl): ?>
                    <option value="<?= (int) $tpl['id'] ?>"><?= e($tpl['name']) ?> · <?= e($tpl['language']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($templates)): ?>
                <p class="field-help" style="color:#F59E0B;">⚠ No hay plantillas aprobadas. <a href="<?= url('/settings/integrations/core') ?>" class="underline" style="color: var(--color-primary);">Sincronizar plantillas</a></p>
                <?php else: ?>
                <p class="field-help">Las plantillas Meta-aprobadas permiten enviar fuera de la ventana de 24h del cliente.</p>
                <?php endif; ?>
            </div>

            <div class="field mt-4">
                <label class="label">Cuerpo del mensaje</label>
                <textarea name="message" rows="6" class="textarea font-mono text-sm" placeholder="Hola {{first_name}}, tenemos una promoción exclusiva para {{company}}..."><?= old('message') ?></textarea>
                <div class="field-help mt-2">
                    <span>Variables:</span>
                    <button type="button" onclick="insertVar('{{first_name}}')" class="kbd hover:opacity-80 transition cursor-pointer">{{first_name}}</button>
                    <button type="button" onclick="insertVar('{{last_name}}')" class="kbd hover:opacity-80 transition cursor-pointer">{{last_name}}</button>
                    <button type="button" onclick="insertVar('{{company}}')" class="kbd hover:opacity-80 transition cursor-pointer">{{company}}</button>
                </div>
            </div>
        </div>

        <!-- Section 3: Programacion -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="form-section-icon" style="background: rgba(245,158,11,.12); color:#F59E0B;">⏰</div>
                <div>
                    <div class="form-section-title">Programación</div>
                    <div class="form-section-sub">Cuándo se envía la campaña.</div>
                </div>
            </div>

            <div class="field">
                <label class="label">Enviar en (opcional)</label>
                <div class="input-with-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <input type="datetime-local" name="scheduled_at" class="input">
                </div>
                <p class="field-help">Si lo dejas vacío, queda como borrador y la envías manualmente desde el detalle.</p>
            </div>
        </div>

        <div class="form-actions elevated mt-4">
            <a href="<?= url('/campaigns') ?>" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-submit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Crear campaña
            </button>
        </div>
    </div>

    <!-- Aside: audiencia -->
    <aside class="space-y-3">
        <div class="form-section" style="padding: 1.125rem;">
            <div class="form-section-header" style="margin-bottom: 0.75rem; padding-bottom: 0.625rem;">
                <div class="form-section-icon" style="background: rgba(6,182,212,.12); color:#06B6D4;">🎯</div>
                <div>
                    <div class="form-section-title">Audiencia</div>
                    <div class="form-section-sub">Filtra a quién enviarle.</div>
                </div>
            </div>

            <div class="field">
                <label class="label">Estado</label>
                <select name="audience_status" class="select">
                    <option value="">Todos</option>
                    <option value="lead">🆕 Lead</option>
                    <option value="active">✅ Activo</option>
                    <option value="vip">⭐ VIP</option>
                </select>
            </div>

            <div class="field mt-3">
                <label class="label">Etiqueta</label>
                <select name="audience_tag" class="select">
                    <option value="">Cualquiera</option>
                    <?php foreach ($tags as $t): ?>
                    <option value="<?= e($t['name']) ?>"><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field mt-3">
                <label class="label">País (ISO 2)</label>
                <input type="text" name="audience_country" maxlength="2" placeholder="DO, MX, CO..." class="input" style="text-transform: uppercase;">
            </div>

            <div class="field mt-3">
                <label class="label">Fuente</label>
                <input type="text" name="audience_source" placeholder="csv_import, web, manual..." class="input">
            </div>

            <label class="flex items-center gap-2 text-xs mt-4 cursor-pointer p-2 rounded-lg" style="background: rgba(16,185,129,.06); border: 1px solid rgba(16,185,129,.2);">
                <input type="checkbox" name="audience_only_whatsapp" value="1" checked class="w-4 h-4 rounded">
                <span style="color: var(--color-text-secondary);">Solo contactos con WhatsApp</span>
            </label>
        </div>

        <div class="form-tip" style="background: linear-gradient(135deg, rgba(245,158,11,.08), rgba(244,63,94,.04)); border-color: rgba(245,158,11,.25);">
            <div class="form-tip-title" style="color:#F59E0B;">⚠ Ventana 24h</div>
            <p>WhatsApp solo permite mensajes libres dentro de las 24h posteriores al último mensaje del cliente. Para envíos masivos a fríos, usa <strong>plantillas aprobadas</strong>.</p>
        </div>
    </aside>
</form>

<script>
function insertVar(v) {
    const ta = document.querySelector('textarea[name="message"]');
    if (!ta) return;
    const start = ta.selectionStart, end = ta.selectionEnd;
    ta.value = ta.value.slice(0, start) + v + ta.value.slice(end);
    ta.selectionStart = ta.selectionEnd = start + v.length;
    ta.focus();
}
(function () {
    const form = document.getElementById('campaignForm');
    if (!form) return;
    form.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') { e.preventDefault(); form.requestSubmit(); }
        if (e.key === 'Escape') { window.location.href = '<?= url('/campaigns') ?>'; }
    });
})();
</script>
<?php \App\Core\View::stop(); ?>
