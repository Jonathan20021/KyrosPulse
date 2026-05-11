<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'profile']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Workspace',
    'title'    => 'Mi perfil',
    'subtitle' => 'Tu informacion personal, firma de agente y password.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<form action="<?= url('/settings/profile') ?>" method="POST" style="max-width: 720px;">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>👤</span> Informacion personal</h2>
                <p class="set-section-desc">Tu identidad dentro del sistema. El email no se puede cambiar desde aqui.</p>
            </div>
        </div>

        <div class="set-field-row cols-2 set-field">
            <div>
                <label class="set-label">Nombre</label>
                <input type="text" name="first_name" maxlength="80"
                       value="<?= e((string) ($user['first_name'] ?? '')) ?>"
                       class="set-input">
            </div>
            <div>
                <label class="set-label">Apellido</label>
                <input type="text" name="last_name" maxlength="80"
                       value="<?= e((string) ($user['last_name'] ?? '')) ?>"
                       class="set-input">
            </div>
        </div>

        <div class="set-field-row cols-2 set-field">
            <div>
                <label class="set-label">Email</label>
                <input type="email" disabled
                       value="<?= e((string) ($user['email'] ?? '')) ?>"
                       class="set-input" style="opacity:.7; cursor:not-allowed;">
                <p class="set-help">Contacta soporte si necesitas cambiarlo.</p>
            </div>
            <div>
                <label class="set-label">Telefono</label>
                <input type="tel" name="phone" maxlength="40"
                       value="<?= e((string) ($user['phone'] ?? '')) ?>"
                       class="set-input">
            </div>
        </div>

        <div class="set-field">
            <label class="set-label">Firma del agente (HTML)</label>
            <textarea name="signature" rows="3" class="set-textarea"
                      placeholder="Saludos,&#10;Tu nombre · Equipo de soporte"><?= e((string) ($user['signature'] ?? '')) ?></textarea>
            <p class="set-help">Se agrega al final de tus mensajes salientes cuando lo configures como template.</p>
        </div>
    </section>

    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>🔒</span> Cambiar password</h2>
                <p class="set-section-desc">Para cambios mas avanzados (2FA, sesiones activas), ve a la seccion <a href="<?= url('/settings/security') ?>" style="color:#8B5CF6; text-decoration:underline;">Seguridad</a>.</p>
            </div>
        </div>

        <div class="set-field-row cols-2 set-field">
            <div>
                <label class="set-label">Nueva password</label>
                <input type="password" name="new_password" minlength="8"
                       placeholder="Minimo 8 caracteres"
                       class="set-input">
            </div>
            <div>
                <label class="set-label">Confirmar password</label>
                <input type="password" name="new_password_confirmation" minlength="8"
                       placeholder="Repite la nueva password"
                       class="set-input">
            </div>
        </div>
    </section>

    <div class="set-actions">
        <button type="submit" class="set-btn set-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Guardar perfil
        </button>
    </div>
</form>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
