<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$errors = $errors ?? [];
$templates = $templates ?? [];
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'Nueva campana', 'subtitle' => 'Define audiencia, mensaje y programacion.']); ?>

<form action="<?= url('/campaigns') ?>" method="POST" class="grid lg:grid-cols-3 gap-4">
    <?= csrf_field() ?>

    <div class="lg:col-span-2 space-y-4">
        <div class="glass rounded-2xl p-5">
            <h3 class="font-bold dark:text-white text-slate-900 mb-3">Mensaje</h3>
            <input type="text" name="name" required value="<?= old('name') ?>" placeholder="Nombre interno *"
                   class="w-full mb-3 px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
            <select name="channel" class="w-full mb-3 px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
                <option value="whatsapp">WhatsApp</option>
                <option value="email">Email</option>
                <option value="sms">SMS</option>
            </select>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Plantilla aprobada Wasapi (opcional)</label>
            <select name="template_id" class="w-full mt-1 mb-3 px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
                <option value="">Enviar mensaje libre dentro de ventana de conversacion</option>
                <?php foreach ($templates as $tpl): ?>
                <option value="<?= (int) $tpl['id'] ?>"><?= e($tpl['name']) ?> · <?= e($tpl['language']) ?></option>
                <?php endforeach; ?>
            </select>
            <textarea name="message" rows="6" placeholder="Escribe tu mensaje. Variables: {{first_name}}, {{last_name}}, {{company}}. Para envios fuera de 24h usa una plantilla aprobada."
                      class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900"><?= old('message') ?></textarea>
            <p class="text-xs dark:text-slate-400 text-slate-500 mt-2">Variables disponibles: <code>{{first_name}}</code> <code>{{last_name}}</code> <code>{{company}}</code></p>
            <?php if (empty($templates)): ?>
            <p class="text-xs text-amber-400 mt-2">No hay plantillas aprobadas sincronizadas. Ve a Integraciones y usa "Sincronizar plantillas".</p>
            <?php endif; ?>
        </div>

        <div class="glass rounded-2xl p-5">
            <h3 class="font-bold dark:text-white text-slate-900 mb-3">Programacion</h3>
            <label class="block text-sm dark:text-slate-300 text-slate-700 mb-1.5">Enviar en (opcional)</label>
            <input type="datetime-local" name="scheduled_at"
                   class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
            <p class="text-xs dark:text-slate-400 text-slate-500 mt-2">Si lo dejas vacio, queda como borrador y la envias manualmente.</p>
        </div>
    </div>

    <!-- Audiencia -->
    <div class="glass rounded-2xl p-5 h-fit space-y-3">
        <h3 class="font-bold dark:text-white text-slate-900 mb-2">Audiencia</h3>

        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Estado</label>
            <select name="audience_status" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                <option value="">Todos</option>
                <option value="lead">Lead</option>
                <option value="active">Activo</option>
                <option value="vip">VIP</option>
            </select>
        </div>

        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Etiqueta</label>
            <select name="audience_tag" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                <option value="">Cualquiera</option>
                <?php foreach ($tags as $t): ?>
                <option value="<?= e($t['name']) ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Pais (ISO 2)</label>
            <input type="text" name="audience_country" maxlength="2" placeholder="DO, MX, CO..."
                   class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
        </div>

        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Fuente</label>
            <input type="text" name="audience_source" placeholder="csv_import, web, manual..."
                   class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
        </div>

        <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
            <input type="checkbox" name="audience_only_whatsapp" value="1" checked class="w-4 h-4 rounded">
            Solo contactos con WhatsApp
        </label>

        <button type="submit" class="w-full mt-4 px-4 py-2.5 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Crear campana</button>
        <a href="<?= url('/campaigns') ?>" class="block text-center text-xs dark:text-slate-400 text-slate-500 hover:underline">Cancelar</a>
    </div>
</form>
<?php \App\Core\View::stop(); ?>
