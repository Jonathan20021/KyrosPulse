<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'Usuarios', 'subtitle' => 'Gestiona el equipo de tu empresa.']); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'users']); ?>

<div class="grid lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 glass rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="dark:bg-white/5 bg-slate-50 dark:text-slate-300 text-slate-600 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-4 py-3">Usuario</th>
                        <th class="text-left px-4 py-3">Email</th>
                        <th class="text-left px-4 py-3">Roles</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-left px-4 py-3">Ultimo acceso</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-white/5 divide-slate-100">
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">
                                    <?= e(strtoupper(mb_substr((string) $u['first_name'], 0, 1) . mb_substr((string) $u['last_name'], 0, 1))) ?>
                                </div>
                                <span class="font-semibold dark:text-white text-slate-900"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 dark:text-slate-300 text-slate-700"><?= e((string) $u['email']) ?></td>
                        <td class="px-4 py-3"><span class="text-xs dark:text-slate-300 text-slate-700"><?= e((string) ($u['roles'] ?? '—')) ?></span></td>
                        <td class="px-4 py-3">
                            <?php if ($u['is_active']): ?>
                            <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-500/20 text-emerald-300">Activo</span>
                            <?php else: ?>
                            <span class="px-2 py-0.5 text-xs rounded-full bg-red-500/20 text-red-300">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs dark:text-slate-400 text-slate-500"><?= !empty($u['last_login_at']) ? time_ago((string) $u['last_login_at']) : 'Nunca' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <form action="<?= url('/settings/users/invite') ?>" method="POST" class="glass rounded-2xl p-5 h-fit">
        <?= csrf_field() ?>
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Invitar usuario</h3>
        <div class="space-y-3">
            <input type="text" name="first_name" required placeholder="Nombre" class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
            <input type="text" name="last_name" required placeholder="Apellido" class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
            <input type="email" name="email" required placeholder="Email" class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
            <select name="role_id" required class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                <option value="">Selecciona rol</option>
                <?php foreach ($roles as $r): ?>
                <option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="w-full px-4 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Invitar</button>
        </div>
    </form>
</div>
<?php \App\Core\View::stop(); ?>
