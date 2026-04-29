<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$days = ['monday'=>'Lunes','tuesday'=>'Martes','wednesday'=>'Miercoles','thursday'=>'Jueves','friday'=>'Viernes','saturday'=>'Sabado','sunday'=>'Domingo'];
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'Configuracion', 'subtitle' => 'Gestiona los parametros de tu empresa.']); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'general']); ?>

<form action="<?= url('/settings') ?>" method="POST" class="grid lg:grid-cols-2 gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <div class="glass rounded-2xl p-5">
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Informacion de la empresa</h3>
        <div class="space-y-3">
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Nombre comercial</label>
                <input type="text" name="name" value="<?= e((string) ($tenant['name'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Razon social</label>
                    <input type="text" name="legal_name" value="<?= e((string) ($tenant['legal_name'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">RNC / Tax ID</label>
                    <input type="text" name="tax_id" value="<?= e((string) ($tenant['tax_id'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Email</label>
                    <input type="email" name="email" value="<?= e((string) ($tenant['email'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Telefono</label>
                    <input type="tel" name="phone" value="<?= e((string) ($tenant['phone'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Pais</label>
                    <input type="text" name="country" maxlength="2" value="<?= e((string) ($tenant['country'] ?? 'DO')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Moneda</label>
                    <select name="currency" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                        <?php foreach (['USD','EUR','DOP','MXN','COP','PEN','ARS','CLP'] as $cur): ?>
                        <option value="<?= $cur ?>" <?= ($tenant['currency'] ?? 'USD') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Idioma</label>
                    <select name="language" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                        <option value="es" <?= ($tenant['language'] ?? 'es') === 'es' ? 'selected' : '' ?>>Espanol</option>
                        <option value="en" <?= ($tenant['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Zona horaria</label>
                <input type="text" name="timezone" value="<?= e((string) ($tenant['timezone'] ?? 'America/Santo_Domingo')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Industria</label>
                <input type="text" name="industry" value="<?= e((string) ($tenant['industry'] ?? '')) ?>" placeholder="Inmobiliaria, retail, salud..."
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Web</label>
                <input type="url" name="website" value="<?= e((string) ($tenant['website'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Direccion</label>
                <input type="text" name="address" value="<?= e((string) ($tenant['address'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-5">
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Horario laboral</h3>
        <p class="text-xs dark:text-slate-400 text-slate-500 mb-3">Las automatizaciones evaluan estos horarios para "horario laboral" / "fuera de horario".</p>
        <div class="space-y-2">
            <?php foreach ($days as $day => $label):
                $h = $hours[$day] ?? ['enabled' => true, 'start' => '09:00', 'end' => '18:00'];
            ?>
            <div class="flex items-center gap-2">
                <label class="flex items-center gap-2 w-32">
                    <input type="checkbox" name="hours[<?= $day ?>][enabled]" value="1" <?= !empty($h['enabled']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                    <span class="text-sm dark:text-slate-300 text-slate-700"><?= $label ?></span>
                </label>
                <input type="time" name="hours[<?= $day ?>][start]" value="<?= e((string) ($h['start'] ?? '09:00')) ?>"
                       class="px-2 py-1 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded text-sm dark:text-white text-slate-900">
                <span class="dark:text-slate-400 text-slate-500">—</span>
                <input type="time" name="hours[<?= $day ?>][end]" value="<?= e((string) ($h['end'] ?? '18:00')) ?>"
                       class="px-2 py-1 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded text-sm dark:text-white text-slate-900">
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4">
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Mensaje fuera de horario</label>
            <textarea name="out_of_hours_msg" rows="2"
                      class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($tenant['out_of_hours_msg'] ?? '')) ?></textarea>
        </div>
        <div class="mt-3">
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Mensaje de bienvenida</label>
            <textarea name="welcome_message" rows="2"
                      class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($tenant['welcome_message'] ?? '')) ?></textarea>
        </div>
    </div>

    <div class="lg:col-span-2 flex justify-end">
        <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar configuracion</button>
    </div>
</form>
<?php \App\Core\View::stop(); ?>
