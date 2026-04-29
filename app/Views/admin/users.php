<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
$users = $users ?? [];
?>

<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-white mb-1">Usuarios</h1>
        <p class="text-sm text-slate-400">Todos los usuarios del SaaS · <?= count($users) ?> resultados.</p>
    </div>
</div>

<form method="GET" class="admin-card rounded-2xl p-4 mb-4 grid md:grid-cols-3 gap-3">
    <div class="md:col-span-2">
        <label class="text-[10px] uppercase tracking-wider text-slate-400">Buscar</label>
        <input type="text" name="q" value="<?= e((string) $q) ?>" placeholder="email, nombre o apellido" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
    </div>
    <div>
        <label class="text-[10px] uppercase tracking-wider text-slate-400">Filtrar por empresa</label>
        <select name="tenant" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            <option value="">— Todas —</option>
            <?php foreach ($tenants as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= (int) $tenantFilter === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-3 flex justify-end">
        <button type="submit" class="px-4 py-2 rounded-lg text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">Buscar</button>
    </div>
</form>

<div class="admin-card rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-slate-400 text-[10px] uppercase tracking-wider">
                <tr>
                    <th class="text-left px-4 py-3">Usuario</th>
                    <th class="text-left px-4 py-3">Empresa</th>
                    <th class="text-left px-4 py-3">Roles</th>
                    <th class="text-left px-4 py-3">Estado</th>
                    <th class="text-left px-4 py-3">Ultimo login</th>
                    <th class="text-right px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php foreach ($users as $u):
                    $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['email'];
                    $roles = !empty($u['role_slugs']) ? explode(',', (string) $u['role_slugs']) : [];
                ?>
                <tr class="hover:bg-white/[0.03] transition">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                                <?= e(strtoupper(mb_substr($name, 0, 1) . mb_substr((string) ($u['last_name'] ?? ''), 0, 1))) ?>
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold text-white text-sm truncate"><?= e($name) ?></div>
                                <div class="text-[11px] text-slate-500 truncate"><?= e($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs">
                        <?php if (!empty($u['tenant_name'])): ?>
                        <span class="text-slate-300"><?= e((string) $u['tenant_name']) ?></span>
                        <?php else: ?>
                        <span class="text-slate-500 italic">Sin tenant</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($roles as $role):
                                $clr = match (trim($role)) {
                                    'super_admin' => 'bg-rose-500/15 text-rose-300',
                                    'owner'       => 'bg-violet-500/15 text-violet-300',
                                    'admin'       => 'bg-cyan-500/15 text-cyan-300',
                                    'supervisor'  => 'bg-amber-500/15 text-amber-300',
                                    'agent'       => 'bg-emerald-500/15 text-emerald-300',
                                    default       => 'bg-slate-500/15 text-slate-400',
                                };
                            ?>
                            <span class="text-[10px] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider <?= $clr ?>"><?= e(trim($role)) ?></span>
                            <?php endforeach; ?>
                            <?php if (!empty($u['is_super_admin'])): ?>
                            <span class="text-[10px] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider bg-rose-500/15 text-rose-300">SUPER</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <?php if (!empty($u['is_active'])): ?>
                        <span class="text-[10px] px-2 py-0.5 rounded font-bold bg-emerald-500/15 text-emerald-300">● Activo</span>
                        <?php else: ?>
                        <span class="text-[10px] px-2 py-0.5 rounded font-bold bg-slate-500/15 text-slate-400">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500"><?= !empty($u['last_login_at']) ? time_ago((string) $u['last_login_at']) : '—' ?></td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-1 flex-wrap justify-end">
                            <form action="<?= url('/admin/users/' . $u['id'] . '/toggle') ?>" method="POST" class="inline">
                                <?= csrf_field() ?>
                                <button class="px-2 py-1 rounded text-[10px] font-semibold <?= !empty($u['is_active']) ? 'bg-amber-500/15 text-amber-300 hover:bg-amber-500/25' : 'bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500/25' ?>" title="<?= !empty($u['is_active']) ? 'Desactivar' : 'Activar' ?>">
                                    <?= !empty($u['is_active']) ? '⏸ Pausar' : '▶ Activar' ?>
                                </button>
                            </form>
                            <form action="<?= url('/admin/users/' . $u['id'] . '/reset-pass') ?>" method="POST" class="inline" onsubmit="return confirm('Generar nueva password temporal para este usuario?')">
                                <?= csrf_field() ?>
                                <button class="px-2 py-1 rounded text-[10px] font-semibold bg-cyan-500/15 text-cyan-300 hover:bg-cyan-500/25">🔑 Reset</button>
                            </form>
                            <?php if (empty($u['is_super_admin'])): ?>
                            <form action="<?= url('/admin/users/' . $u['id']) ?>" method="POST" class="inline" onsubmit="return confirm('Eliminar usuario? (soft delete, recuperable)')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="px-2 py-1 rounded text-[10px] font-semibold bg-rose-500/15 text-rose-300 hover:bg-rose-500/25">✕</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                        <div class="text-3xl mb-2">🔍</div>
                        Sin resultados con el filtro actual.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php \App\Core\View::stop(); ?>
