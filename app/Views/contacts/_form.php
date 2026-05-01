<?php
/**
 * Form parcial reusable para create/edit de contactos.
 * @var array|null $contact
 * @var array $tags
 * @var array $agents
 * @var array $allTags
 * @var array $errors
 */
$contact  = $contact ?? [];
$tags     = $tags    ?? [];
$tagNames = array_column($tags, 'name');
?>

<!-- Section 1: Identidad -->
<div class="form-section">
    <div class="form-section-header">
        <div class="form-section-icon">👤</div>
        <div>
            <div class="form-section-title">Identidad</div>
            <div class="form-section-sub">Quién es este contacto y cómo se llama.</div>
        </div>
    </div>

    <div class="form-row cols-2">
        <div class="field">
            <label class="label">Nombre <span style="color:#F87171;">*</span></label>
            <div class="input-with-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <input type="text" name="first_name" required value="<?= e((string) ($contact['first_name'] ?? old('first_name'))) ?>" class="input" placeholder="Juan">
            </div>
            <?php if ($e = error_for('first_name', $errors)): ?>
            <p class="field-error"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg><?= e($e) ?></p>
            <?php endif; ?>
        </div>
        <div class="field">
            <label class="label">Apellido</label>
            <input type="text" name="last_name" value="<?= e((string) ($contact['last_name'] ?? old('last_name'))) ?>" class="input" placeholder="Pérez">
        </div>
    </div>

    <div class="form-row cols-2 mt-4">
        <div class="field">
            <label class="label">Empresa</label>
            <div class="input-with-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <input type="text" name="company" value="<?= e((string) ($contact['company'] ?? old('company'))) ?>" class="input" placeholder="Acme Corp">
            </div>
        </div>
        <div class="field">
            <label class="label">Cargo</label>
            <div class="input-with-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <input type="text" name="position" value="<?= e((string) ($contact['position'] ?? old('position'))) ?>" class="input" placeholder="Director Comercial">
            </div>
        </div>
    </div>
</div>

<!-- Section 2: Contacto -->
<div class="form-section">
    <div class="form-section-header">
        <div class="form-section-icon" style="background: rgba(16,185,129,.12); color:#34D399;">📱</div>
        <div>
            <div class="form-section-title">Datos de contacto</div>
            <div class="form-section-sub">Cómo lo contactamos. WhatsApp es el canal principal de la IA.</div>
        </div>
    </div>

    <div class="form-row cols-2">
        <div class="field">
            <label class="label">Email</label>
            <div class="input-with-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <input type="email" name="email" value="<?= e((string) ($contact['email'] ?? old('email'))) ?>" class="input" placeholder="cliente@empresa.com">
            </div>
        </div>
        <div class="field">
            <label class="label">Teléfono</label>
            <div class="input-with-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <input type="tel" name="phone" value="<?= e((string) ($contact['phone'] ?? old('phone'))) ?>" class="input" placeholder="+1 809 555 1234">
            </div>
        </div>
    </div>

    <div class="form-row cols-2 mt-4">
        <div class="field">
            <label class="label">WhatsApp</label>
            <div class="input-with-icon">
                <svg fill="currentColor" viewBox="0 0 24 24" style="color:#10B981;"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
                <input type="tel" name="whatsapp" value="<?= e((string) ($contact['whatsapp'] ?? old('whatsapp'))) ?>" class="input" placeholder="+1 809 555 1234">
            </div>
            <p class="field-help">Si está vacío, usamos el teléfono. La IA atiende mensajes de WhatsApp con este número.</p>
        </div>
        <div class="field">
            <label class="label">Documento (cédula/RNC/RFC)</label>
            <div class="input-with-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <input type="text" name="document_number" value="<?= e((string) ($contact['document_number'] ?? old('document_number'))) ?>" class="input">
            </div>
        </div>
    </div>
</div>

<!-- Section 3: Ubicación -->
<div class="form-section">
    <div class="form-section-header">
        <div class="form-section-icon" style="background: rgba(6,182,212,.12); color:#06B6D4;">📍</div>
        <div>
            <div class="form-section-title">Ubicación</div>
            <div class="form-section-sub">Útil para deliveries, segmentación y reportes.</div>
        </div>
    </div>

    <div class="form-row cols-2">
        <div class="field md:col-span-2">
            <label class="label">Dirección</label>
            <input type="text" name="address" value="<?= e((string) ($contact['address'] ?? old('address'))) ?>" class="input" placeholder="Av. Independencia 100, Apt 5B">
        </div>
    </div>
    <div class="form-row cols-2 mt-4">
        <div class="field">
            <label class="label">Ciudad</label>
            <input type="text" name="city" value="<?= e((string) ($contact['city'] ?? old('city'))) ?>" class="input" placeholder="Santo Domingo">
        </div>
        <div class="field">
            <label class="label">País (ISO 2)</label>
            <input type="text" name="country" maxlength="2" value="<?= e((string) ($contact['country'] ?? 'DO')) ?>" class="input" placeholder="DO" style="text-transform: uppercase;">
        </div>
    </div>
</div>

<!-- Section 4: CRM -->
<div class="form-section">
    <div class="form-section-header">
        <div class="form-section-icon" style="background: rgba(245,158,11,.12); color:#F59E0B;">⚡</div>
        <div>
            <div class="form-section-title">Estado y CRM</div>
            <div class="form-section-sub">Cómo lo categorizamos en el pipeline y quién lo gestiona.</div>
        </div>
    </div>

    <div class="form-row cols-3">
        <div class="field">
            <label class="label">Estado</label>
            <select name="status" class="select">
                <?php foreach (['lead'=>'🆕 Lead','active'=>'✅ Activo','vip'=>'⭐ VIP','inactive'=>'💤 Inactivo','blocked'=>'🚫 Bloqueado'] as $v=>$lab): ?>
                <option value="<?= $v ?>" <?= ($contact['status'] ?? 'lead') === $v ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label">Fuente</label>
            <select name="source" class="select">
                <?php $sources = ['manual','whatsapp','whatsapp_ia','web','referido','campana','evento','otro']; ?>
                <?php foreach ($sources as $src): ?>
                <option value="<?= $src ?>" <?= ($contact['source'] ?? 'manual') === $src ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $src))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label">Valor estimado</label>
            <div class="input-with-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2"/></svg>
                <input type="number" step="0.01" min="0" name="estimated_value" value="<?= e((string) ($contact['estimated_value'] ?? '')) ?>" class="input" placeholder="0.00">
            </div>
        </div>
    </div>

    <div class="form-row mt-4">
        <div class="field">
            <label class="label">Responsable (agente asignado)</label>
            <div class="input-with-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <select name="assigned_to" class="select">
                    <option value="">— Sin asignar —</option>
                    <?php foreach ($agents as $a): ?>
                    <option value="<?= (int) $a['id'] ?>" <?= ((int) ($contact['assigned_to'] ?? 0)) === (int) $a['id'] ? 'selected' : '' ?>>
                        <?= e($a['first_name'] . ' ' . $a['last_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="form-row mt-4">
        <div class="field">
            <label class="label">Etiquetas (separadas por coma)</label>
            <div class="input-with-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                <input type="text" name="tags" value="<?= e(implode(', ', $tagNames)) ?>" placeholder="VIP, Madrid, Recurrente" class="input">
            </div>
            <?php if (!empty($allTags)): ?>
            <div class="flex flex-wrap gap-1 mt-2">
                <span class="text-[10px] uppercase tracking-wider mr-1" style="color: var(--color-text-tertiary);">Disponibles:</span>
                <?php foreach (array_slice($allTags, 0, 12) as $t): ?>
                <button type="button" onclick="(function(b){const i=document.querySelector('input[name=tags]');const v=i.value.trim();const exists=v.split(',').map(x=>x.trim().toLowerCase()).includes(b.dataset.name.toLowerCase());i.value=exists?v:(v?v+', '+b.dataset.name:b.dataset.name);})(this)" data-name="<?= e($t['name']) ?>"
                        class="px-2 py-0.5 text-[10px] rounded-full text-white hover:scale-105 transition" style="background:<?= e((string) $t['color']) ?>;"><?= e($t['name']) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Section 5: Notas + consent -->
<div class="form-section">
    <div class="form-section-header">
        <div class="form-section-icon" style="background: rgba(168,85,247,.12); color:#A855F7;">📝</div>
        <div>
            <div class="form-section-title">Notas y consentimiento</div>
            <div class="form-section-sub">Información adicional y permiso para envío de marketing.</div>
        </div>
    </div>

    <div class="field">
        <label class="label">Notas</label>
        <textarea name="notes" rows="4" class="textarea" placeholder="Información relevante: preferencias, historial, contexto..."><?= e((string) ($contact['notes'] ?? old('notes'))) ?></textarea>
    </div>

    <label class="flex items-start gap-3 mt-4 p-3 rounded-xl cursor-pointer" style="background: var(--color-bg-subtle);">
        <input type="checkbox" name="consent_marketing" value="1" <?= !empty($contact['consent_marketing']) ? 'checked' : '' ?> class="w-4 h-4 rounded mt-0.5">
        <div>
            <div class="text-sm font-semibold" style="color: var(--color-text-primary);">Acepta comunicaciones comerciales</div>
            <div class="text-xs" style="color: var(--color-text-tertiary);">El contacto autorizó recibir promociones, campañas y newsletters.</div>
        </div>
    </label>
</div>
