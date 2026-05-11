<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'users']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Workspace',
    'title'    => 'Usuarios y roles',
    'subtitle' => 'Equipo que tiene acceso a tu workspace. Cada usuario puede tener uno o mas roles.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4">
    <!-- Lista de usuarios -->
    <section class="set-section lg:col-span-2" style="padding: 0;">
        <div style="padding: 22px 22px 14px;">
            <h2 class="set-section-title"><span>👥</span> Miembros del equipo
                <span class="badge" style="background: var(--color-bg-secondary, rgba(0,0,0,.05)); color: var(--color-text-tertiary); font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight:600;"><?= count($users) ?></span>
            </h2>
        </div>
        <div class="overflow-x-auto" style="border-top: 1px solid var(--color-border-default);">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--color-bg-secondary, rgba(0,0,0,.02));">
                        <th class="text-left px-4 py-2.5" style="font-size:11px; text-transform:uppercase; letter-spacing:.06em; color: var(--color-text-tertiary); font-weight:600;">Usuario</th>
                        <th class="text-left px-4 py-2.5" style="font-size:11px; text-transform:uppercase; letter-spacing:.06em; color: var(--color-text-tertiary); font-weight:600;">Email</th>
                        <th class="text-left px-4 py-2.5" style="font-size:11px; text-transform:uppercase; letter-spacing:.06em; color: var(--color-text-tertiary); font-weight:600;">Rol</th>
                        <th class="text-left px-4 py-2.5" style="font-size:11px; text-transform:uppercase; letter-spacing:.06em; color: var(--color-text-tertiary); font-weight:600;">Estado</th>
                        <th class="text-left px-4 py-2.5" style="font-size:11px; text-transform:uppercase; letter-spacing:.06em; color: var(--color-text-tertiary); font-weight:600;">Ultimo acceso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr style="border-top: 1px solid var(--color-border-default);">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">
                                    <?= e(strtoupper(mb_substr((string) $u['first_name'], 0, 1) . mb_substr((string) $u['last_name'], 0, 1))) ?>
                                </div>
                                <span class="font-semibold" style="color: var(--color-text-primary);"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3" style="color: var(--color-text-secondary);"><?= e((string) $u['email']) ?></td>
                        <td class="px-4 py-3"><span class="text-xs" style="color: var(--color-text-secondary);"><?= e((string) ($u['roles'] ?? '—')) ?></span></td>
                        <td class="px-4 py-3">
                            <?php if ($u['is_active']): ?>
                            <span style="font-size:11px; padding: 2px 8px; border-radius: 999px; background: rgba(16,185,129,.12); color:#059669; font-weight:600;">Activo</span>
                            <?php else: ?>
                            <span style="font-size:11px; padding: 2px 8px; border-radius: 999px; background: rgba(239,68,68,.12); color:#DC2626; font-weight:600;">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs" style="color: var(--color-text-tertiary);"><?= !empty($u['last_login_at']) ? time_ago((string) $u['last_login_at']) : 'Nunca' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="5"><div class="set-empty"><div class="set-empty-ico">👥</div><div class="set-empty-title">Sin miembros aun</div><div class="set-empty-desc">Invita al primer usuario desde el panel de la derecha.</div></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Invitar usuario -->
    <form action="<?= url('/settings/users/invite') ?>" method="POST" class="set-section h-fit" style="margin-bottom:0;">
        <?= csrf_field() ?>
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>➕</span> Invitar usuario</h2>
                <p class="set-section-desc">Recibira un email para crear su password.</p>
            </div>
        </div>

        <div class="set-field-row cols-2 set-field">
            <div>
                <label class="set-label">Nombre</label>
                <input type="text" name="first_name" required maxlength="80" class="set-input">
            </div>
            <div>
                <label class="set-label">Apellido</label>
                <input type="text" name="last_name" required maxlength="80" class="set-input">
            </div>
        </div>

        <div class="set-field">
            <label class="set-label">Email</label>
            <input type="email" name="email" required maxlength="180" placeholder="email@dominio.com" class="set-input">
        </div>

        <div class="set-field">
            <label class="set-label">Rol</label>
            <select name="role_id" required class="set-select">
                <option value="">— Selecciona rol —</option>
                <?php foreach ($roles as $r): ?>
                <option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="set-btn set-btn-primary w-full">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
            Enviar invitacion
        </button>
    </form>
</div>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
