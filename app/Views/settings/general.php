<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$days = ['monday'=>'Lunes','tuesday'=>'Martes','wednesday'=>'Miercoles','thursday'=>'Jueves','friday'=>'Viernes','saturday'=>'Sabado','sunday'=>'Domingo'];
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'general']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Workspace',
    'title'    => 'Empresa',
    'subtitle' => 'Datos generales, horarios laborales y mensajes por defecto. Estos valores se usan en facturas, mensajes automaticos y reportes.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<form action="<?= url('/settings') ?>" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <!-- Informacion de la empresa -->
    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>🏢</span> Informacion de la empresa</h2>
                <p class="set-section-desc">Datos comerciales y fiscales que aparecen en facturas y emails.</p>
            </div>
        </div>

        <div class="set-field">
            <label class="set-label">Nombre comercial <span class="req">*</span></label>
            <input type="text" name="name" required maxlength="150"
                   value="<?= e((string) ($tenant['name'] ?? '')) ?>"
                   placeholder="Ej: Pizzeria La Bella"
                   class="set-input">
        </div>

        <div class="set-field-row cols-2 set-field">
            <div>
                <label class="set-label">Razon social</label>
                <input type="text" name="legal_name" maxlength="180"
                       value="<?= e((string) ($tenant['legal_name'] ?? '')) ?>"
                       class="set-input">
            </div>
            <div>
                <label class="set-label">RNC / Tax ID</label>
                <input type="text" name="tax_id" maxlength="60"
                       value="<?= e((string) ($tenant['tax_id'] ?? '')) ?>"
                       class="set-input">
            </div>
        </div>

        <div class="set-field-row cols-2 set-field">
            <div>
                <label class="set-label">Email de contacto</label>
                <input type="email" name="email" maxlength="180"
                       value="<?= e((string) ($tenant['email'] ?? '')) ?>"
                       class="set-input">
            </div>
            <div>
                <label class="set-label">Telefono</label>
                <input type="tel" name="phone" maxlength="40"
                       value="<?= e((string) ($tenant['phone'] ?? '')) ?>"
                       class="set-input">
            </div>
        </div>

        <div class="set-field">
            <label class="set-label">Direccion</label>
            <input type="text" name="address" maxlength="255"
                   value="<?= e((string) ($tenant['address'] ?? '')) ?>"
                   placeholder="Calle, ciudad, pais"
                   class="set-input">
        </div>

        <div class="set-field-row cols-2 set-field">
            <div>
                <label class="set-label">Sitio web</label>
                <input type="url" name="website" maxlength="255"
                       value="<?= e((string) ($tenant['website'] ?? '')) ?>"
                       placeholder="https://tudominio.com"
                       class="set-input">
            </div>
            <div>
                <label class="set-label">Industria</label>
                <input type="text" name="industry" maxlength="80"
                       value="<?= e((string) ($tenant['industry'] ?? '')) ?>"
                       placeholder="Restaurante, e-commerce, salud..."
                       class="set-input">
            </div>
        </div>
    </section>

    <!-- Localizacion -->
    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>🌎</span> Localizacion</h2>
                <p class="set-section-desc">Pais, moneda, idioma y zona horaria. Afecta como se muestran fechas, precios y se evaluan horarios laborales.</p>
            </div>
        </div>

        <div class="set-field-row cols-3 set-field">
            <div>
                <label class="set-label">Pais (ISO-2)</label>
                <input type="text" name="country" maxlength="2"
                       value="<?= e((string) ($tenant['country'] ?? 'DO')) ?>"
                       placeholder="DO, MX, US..."
                       class="set-input" style="text-transform: uppercase;">
            </div>
            <div>
                <label class="set-label">Moneda</label>
                <select name="currency" class="set-select">
                    <?php foreach (['USD','EUR','DOP','MXN','COP','PEN','ARS','CLP','BRL','GTQ','UYU'] as $cur): ?>
                    <option value="<?= $cur ?>" <?= ($tenant['currency'] ?? 'USD') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="set-label">Idioma</label>
                <select name="language" class="set-select">
                    <option value="es" <?= ($tenant['language'] ?? 'es') === 'es' ? 'selected' : '' ?>>Espanol</option>
                    <option value="en" <?= ($tenant['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                </select>
            </div>
        </div>

        <div class="set-field">
            <label class="set-label">Zona horaria</label>
            <input type="text" name="timezone" maxlength="50"
                   value="<?= e((string) ($tenant['timezone'] ?? 'America/Santo_Domingo')) ?>"
                   placeholder="America/Santo_Domingo"
                   class="set-input">
            <p class="set-help">Formato IANA. Ej: America/Mexico_City, America/Bogota, Europe/Madrid.</p>
        </div>
    </section>

    <!-- Horarios -->
    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>🕒</span> Horario laboral</h2>
                <p class="set-section-desc">Las automatizaciones e IA usan estos horarios para diferenciar "horario laboral" vs "fuera de horario".</p>
            </div>
        </div>

        <div class="space-y-1.5">
            <?php foreach ($days as $day => $label):
                $h = $hours[$day] ?? ['enabled' => true, 'start' => '09:00', 'end' => '18:00'];
            ?>
            <div class="flex items-center gap-3 p-2.5 rounded-lg flex-wrap" style="background: var(--color-bg-secondary, rgba(0,0,0,.02));">
                <label class="flex items-center gap-2 w-28 cursor-pointer">
                    <input type="checkbox" name="hours[<?= $day ?>][enabled]" value="1"
                           <?= !empty($h['enabled']) ? 'checked' : '' ?>
                           style="accent-color: #8B5CF6;">
                    <span class="text-sm font-medium" style="color: var(--color-text-primary);"><?= $label ?></span>
                </label>
                <input type="time" name="hours[<?= $day ?>][start]"
                       value="<?= e((string) ($h['start'] ?? '09:00')) ?>"
                       class="set-input" style="max-width: 130px; padding: 7px 10px;">
                <span style="color: var(--color-text-tertiary);">—</span>
                <input type="time" name="hours[<?= $day ?>][end]"
                       value="<?= e((string) ($h['end'] ?? '18:00')) ?>"
                       class="set-input" style="max-width: 130px; padding: 7px 10px;">
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Mensajes automaticos -->
    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>💬</span> Mensajes por defecto</h2>
                <p class="set-section-desc">Plantillas que se usan cuando ninguna automatizacion o agente IA toma el control.</p>
            </div>
        </div>

        <div class="set-field">
            <label class="set-label">Mensaje de bienvenida</label>
            <textarea name="welcome_message" rows="2"
                      placeholder="Hola! Gracias por contactarnos. En un momento te atendemos."
                      class="set-textarea"><?= e((string) ($tenant['welcome_message'] ?? '')) ?></textarea>
            <p class="set-help">Primer mensaje que recibe un cliente nuevo si la IA no esta activa.</p>
        </div>

        <div class="set-field">
            <label class="set-label">Mensaje fuera de horario</label>
            <textarea name="out_of_hours_msg" rows="2"
                      placeholder="Estamos fuera de horario. Te respondemos en cuanto regresemos a las 9 AM."
                      class="set-textarea"><?= e((string) ($tenant['out_of_hours_msg'] ?? '')) ?></textarea>
            <p class="set-help">Se envia cuando un cliente escribe fuera del horario laboral configurado arriba.</p>
        </div>
    </section>

    <div class="set-actions">
        <button type="submit" class="set-btn set-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Guardar configuracion
        </button>
    </div>
</form>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
