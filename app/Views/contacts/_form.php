<?php
/**
 * Form parcial reusable para create/edit.
 * @var array|null $contact
 * @var array $tags
 * @var array $agents
 * @var array $allTags
 * @var array $errors
 */
$contact = $contact ?? [];
$tags    = $tags    ?? [];
$tagNames = array_column($tags, 'name');
?>
<div class="grid md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Nombre *</label>
        <input type="text" name="first_name" required value="<?= e((string) ($contact['first_name'] ?? old('first_name'))) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
        <?php if ($e = error_for('first_name', $errors)): ?><p class="text-xs text-red-400 mt-1"><?= e($e) ?></p><?php endif; ?>
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Apellido</label>
        <input type="text" name="last_name" value="<?= e((string) ($contact['last_name'] ?? old('last_name'))) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Empresa</label>
        <input type="text" name="company" value="<?= e((string) ($contact['company'] ?? old('company'))) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Cargo</label>
        <input type="text" name="position" value="<?= e((string) ($contact['position'] ?? old('position'))) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Email</label>
        <input type="email" name="email" value="<?= e((string) ($contact['email'] ?? old('email'))) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Telefono</label>
        <input type="tel" name="phone" value="<?= e((string) ($contact['phone'] ?? old('phone'))) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">WhatsApp</label>
        <input type="tel" name="whatsapp" value="<?= e((string) ($contact['whatsapp'] ?? old('whatsapp'))) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Documento</label>
        <input type="text" name="document_number" value="<?= e((string) ($contact['document_number'] ?? old('document_number'))) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Direccion</label>
        <input type="text" name="address" value="<?= e((string) ($contact['address'] ?? old('address'))) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Ciudad</label>
        <input type="text" name="city" value="<?= e((string) ($contact['city'] ?? old('city'))) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Pais (ISO 2)</label>
        <input type="text" name="country" maxlength="2" value="<?= e((string) ($contact['country'] ?? 'DO')) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Estado</label>
        <select name="status" class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
            <?php foreach (['lead'=>'Lead','active'=>'Activo','inactive'=>'Inactivo','vip'=>'VIP','blocked'=>'Bloqueado'] as $v=>$lab): ?>
            <option value="<?= $v ?>" <?= ($contact['status'] ?? 'lead') === $v ? 'selected' : '' ?>><?= $lab ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Fuente</label>
        <input type="text" name="source" value="<?= e((string) ($contact['source'] ?? 'manual')) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div>
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Valor estimado</label>
        <input type="number" step="0.01" name="estimated_value" value="<?= e((string) ($contact['estimated_value'] ?? '')) ?>"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Responsable</label>
        <select name="assigned_to" class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
            <option value="">Sin asignar</option>
            <?php foreach ($agents as $a): ?>
            <option value="<?= (int) $a['id'] ?>" <?= ((int) ($contact['assigned_to'] ?? 0)) === (int) $a['id'] ? 'selected' : '' ?>>
                <?= e($a['first_name'] . ' ' . $a['last_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Etiquetas (separadas por coma)</label>
        <input type="text" name="tags" value="<?= e(implode(', ', $tagNames)) ?>"
               placeholder="VIP, Madrid, Recurrente"
               class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
        <?php if (!empty($allTags)): ?>
        <div class="flex flex-wrap gap-1 mt-2">
            <?php foreach ($allTags as $t): ?>
            <span class="px-2 py-0.5 text-xs rounded-full text-white" style="background:<?= e((string) $t['color']) ?>"><?= e($t['name']) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Notas</label>
        <textarea name="notes" rows="3"
                  class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900"><?= e((string) ($contact['notes'] ?? old('notes'))) ?></textarea>
    </div>
    <div class="md:col-span-2">
        <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
            <input type="checkbox" name="consent_marketing" value="1" <?= !empty($contact['consent_marketing']) ? 'checked' : '' ?>
                   class="w-4 h-4 rounded">
            El contacto acepta recibir comunicaciones comerciales.
        </label>
    </div>
</div>
