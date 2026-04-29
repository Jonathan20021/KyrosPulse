<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
?>
<div class="mb-6">
    <h1 class="text-2xl lg:text-[28px] font-bold tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;">Empresas</h1>
    <p class="text-sm mt-1" style="color: var(--color-text-tertiary);"><?= count($tenants) ?> tenants registrados en la plataforma.</p>
</div>

<div class="surface overflow-hidden">
    <div class="overflow-x-auto">
        <table class="kt-table">
            <thead><tr><th>Empresa</th><th>Plan</th><th>Estado</th><th>Trial / Expira</th><th>Creado</th><th class="text-right"></th></tr></thead>
            <tbody>
                <?php foreach ($tenants as $t):
                    $sCls = match ($t['status']) { 'active' => 'badge-emerald', 'trial' => 'badge-cyan', 'suspended' => 'badge-rose', 'expired' => 'badge-amber', default => 'badge-slate' };
                ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="avatar avatar-md"><?= e(strtoupper(mb_substr((string) $t['name'], 0, 2))) ?></div>
                            <div>
                                <div class="font-semibold" style="color: var(--color-text-primary);"><?= e($t['name']) ?></div>
                                <div class="text-xs" style="color: var(--color-text-tertiary);"><?= e((string) $t['email']) ?> · <?= e((string) $t['country']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <form action="<?= url('/admin/tenants/' . $t['id']) ?>" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_method" value="PUT">
                            <select name="plan_id" onchange="this.form.submit()" class="select text-xs py-1">
                                <?php foreach ($plans as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= ((int) $t['plan_id']) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td><span class="badge <?= $sCls ?>"><?= e($t['status']) ?></span></td>
                    <td class="text-xs">
                        <?php if (!empty($t['trial_ends_at'])): ?>
                        <div style="color: var(--color-text-secondary);">Trial: <?= date('d/m/Y', strtotime($t['trial_ends_at'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($t['expires_at'])): ?>
                        <div style="color: var(--color-text-secondary);">Expira: <?= date('d/m/Y', strtotime($t['expires_at'])) ?></div>
                        <?php endif; ?>
                        <?php if (empty($t['trial_ends_at']) && empty($t['expires_at'])): ?>
                        <span style="color: var(--color-text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-xs" style="color: var(--color-text-tertiary);"><?= time_ago((string) $t['created_at']) ?></td>
                    <td class="text-right">
                        <?php if ($t['status'] === 'suspended'): ?>
                        <form action="<?= url('/admin/tenants/' . $t['id'] . '/activate') ?>" method="POST" class="inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm text-emerald-500">Activar</button>
                        </form>
                        <?php else: ?>
                        <form action="<?= url('/admin/tenants/' . $t['id'] . '/suspend') ?>" method="POST" onsubmit="return confirm('Suspender?')" class="inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm text-rose-500">Suspender</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
