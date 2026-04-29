<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
?>

<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-white mb-1">Empresas y licencias</h1>
        <p class="text-sm text-slate-400"><?= count($tenants) ?> tenants registrados · gestiona plan, vigencia, IA y acceso.</p>
    </div>
    <a href="<?= url('/admin/tenants/create') ?>" class="px-4 py-2 rounded-xl text-white text-sm font-semibold shadow-lg shadow-violet-500/30" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">+ Nueva empresa</a>
</div>

<div class="space-y-3">
    <?php foreach ($tenants as $t):
        $statusClr = match ($t['status'] ?? '') {
            'active'    => '#10B981',
            'trial'     => '#F59E0B',
            'suspended' => '#F43F5E',
            'expired'   => '#A78BFA',
            'cancelled' => '#64748B',
            default     => '#64748B',
        };
        $trialLeft = !empty($t['trial_ends_at']) ? floor((strtotime((string) $t['trial_ends_at']) - time()) / 86400) : null;
    ?>
    <details class="admin-card rounded-2xl">
        <summary class="cursor-pointer list-none p-4 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-base font-bold text-white flex-shrink-0" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);"><?= e(strtoupper(mb_substr((string) $t['name'], 0, 2))) ?></div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-white truncate"><?= e($t['name']) ?></span>
                        <span class="text-[10px] uppercase tracking-wider font-bold px-2 py-0.5 rounded" style="background: <?= $statusClr ?>22; color: <?= $statusClr ?>;"><?= e($t['status']) ?></span>
                        <?php if (!empty($t['plan_name'] ?? null)): ?>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-violet-500/15 text-violet-300"><?= e((string) $t['plan_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-slate-500 mt-0.5"><?= e((string) $t['email']) ?> · <?= e((string) $t['country']) ?></div>
                </div>
            </div>
            <div class="text-right text-xs text-slate-400 flex-shrink-0">
                <?php if ($trialLeft !== null && $t['status'] === 'trial'): ?>
                <div class="<?= $trialLeft < 4 ? 'text-amber-400 font-semibold' : '' ?>">Trial: <?= max(0, (int) $trialLeft) ?> dias</div>
                <?php endif; ?>
                <?php if (!empty($t['expires_at'])): ?>
                <div>Expira: <?= date('d M Y', strtotime((string) $t['expires_at'])) ?></div>
                <?php endif; ?>
                <div class="text-slate-500">Creado <?= time_ago((string) $t['created_at']) ?></div>
            </div>
        </summary>

        <div class="border-t border-white/5 p-4 space-y-3">
            <!-- Plan + estado -->
            <form action="<?= url('/admin/tenants/' . $t['id']) ?>" method="POST" class="grid md:grid-cols-3 gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Plan</label>
                    <select name="plan_id" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                        <?php foreach ($plans as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= ((int) $t['plan_id']) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> — $<?= number_format((float) $p['price_monthly']) ?>/mes</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Estado</label>
                    <select name="status" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                        <?php foreach (['trial','active','suspended','cancelled','expired'] as $s): ?>
                        <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 rounded-lg text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">Guardar plan</button>
                </div>
            </form>

            <!-- Vigencia -->
            <div class="grid md:grid-cols-2 gap-3">
                <form action="<?= url('/admin/tenants/' . $t['id'] . '/extend-trial') ?>" method="POST" class="flex items-end gap-2 p-3 rounded-lg bg-amber-500/5 border border-amber-500/15">
                    <?= csrf_field() ?>
                    <div class="flex-1">
                        <label class="text-[10px] uppercase tracking-wider text-amber-300">Extender trial</label>
                        <input type="number" name="days" value="14" min="1" max="180" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg text-white text-sm font-semibold bg-amber-500/30 hover:bg-amber-500/40">+ Dias</button>
                </form>
                <form action="<?= url('/admin/tenants/' . $t['id'] . '/expiry') ?>" method="POST" class="flex items-end gap-2 p-3 rounded-lg bg-violet-500/5 border border-violet-500/15">
                    <?= csrf_field() ?>
                    <div class="flex-1">
                        <label class="text-[10px] uppercase tracking-wider text-violet-300">Vigencia de licencia (expires_at)</label>
                        <input type="date" name="expires_at" value="<?= !empty($t['expires_at']) ? date('Y-m-d', strtotime((string) $t['expires_at'])) : '' ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg text-white text-sm font-semibold bg-violet-500/30 hover:bg-violet-500/40">Guardar</button>
                </form>
            </div>

            <!-- IA y cuota de tokens -->
            <form action="<?= url('/admin/tenants/' . $t['id'] . '/ai-assign') ?>" method="POST" class="p-3 rounded-lg bg-violet-500/5 border border-violet-500/15 space-y-3">
                <?= csrf_field() ?>
                <div class="flex items-center justify-between">
                    <div class="text-[10px] uppercase tracking-wider text-violet-300 font-bold">🤖 IA y tokens</div>
                    <?php
                    $usedTokens = (int) ($t['ai_tokens_used_period'] ?? 0);
                    $quota = $t['ai_token_quota'] !== null ? (int) $t['ai_token_quota'] : null;
                    $pct = $quota && $quota > 0 ? min(100, (int) round($usedTokens / $quota * 100)) : 0;
                    ?>
                    <div class="text-[11px] text-slate-300 font-mono">
                        <?php if ($quota): ?>
                        <?= number_format($usedTokens) ?> / <?= number_format($quota) ?> tokens · <?= $pct ?>%
                        <?php else: ?>
                        <?= number_format($usedTokens) ?> tokens · ilimitado
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($quota): ?>
                <div class="h-1.5 rounded-full bg-white/5 overflow-hidden">
                    <div class="h-full rounded-full <?= $pct >= 90 ? 'bg-rose-500' : ($pct >= 70 ? 'bg-amber-500' : '') ?>"
                         style="width: <?= $pct ?>%; <?= $pct < 70 ? 'background: linear-gradient(90deg,#7C3AED,#06B6D4);' : '' ?>"></div>
                </div>
                <?php endif; ?>
                <div class="grid md:grid-cols-3 gap-2">
                    <div>
                        <label class="text-[10px] text-violet-300/80">Proveedor IA global asignado</label>
                        <select name="global_ai_provider_id" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                            <option value="">— Que use el default / su key propia —</option>
                            <?php foreach ($providers as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= ((int) ($t['global_ai_provider_id'] ?? 0)) === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?> (<?= e(strtoupper($p['provider'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] text-violet-300/80">Cuota tokens/mes</label>
                        <input type="number" name="ai_token_quota" value="<?= e((string) ($t['ai_token_quota'] ?? '')) ?>" placeholder="vacio = ilimitado" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="flex items-center gap-1.5 text-xs text-slate-300 px-2 py-1.5 rounded bg-white/5">
                            <input type="checkbox" name="reset_period" value="1" class="w-3.5 h-3.5 rounded">
                            Reset
                        </label>
                        <label class="flex items-center gap-1.5 text-xs text-slate-300 px-2 py-1.5 rounded bg-white/5">
                            <input type="checkbox" name="enable_ai" value="1" class="w-3.5 h-3.5 rounded">
                            Activar IA
                        </label>
                        <button type="submit" class="flex-1 px-3 py-2 rounded-lg text-white text-xs font-semibold bg-violet-500/30 hover:bg-violet-500/40">Guardar IA</button>
                    </div>
                </div>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    El tenant puede agregar su propia API key en <code class="text-cyan-300">Configuracion → Integraciones</code> (siempre tiene prioridad).
                    Si no tiene key propia y aqui asignas una, el SaaS le da acceso al provider global. Si dejas vacio y existe un provider con <em>default = ON</em>, usara ese.
                </p>
            </form>

            <!-- Acciones -->
            <div class="flex flex-wrap gap-2 pt-2">
                <?php if ($t['status'] === 'suspended' || $t['status'] === 'expired'): ?>
                <form action="<?= url('/admin/tenants/' . $t['id'] . '/activate') ?>" method="POST">
                    <?= csrf_field() ?>
                    <button class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500/25">✓ Reactivar</button>
                </form>
                <?php else: ?>
                <form action="<?= url('/admin/tenants/' . $t['id'] . '/suspend') ?>" method="POST" onsubmit="return confirm('Suspender este tenant?')">
                    <?= csrf_field() ?>
                    <button class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-500/15 text-rose-300 hover:bg-rose-500/25">⏸ Suspender</button>
                </form>
                <?php endif; ?>
                <form action="<?= url('/admin/tenants/' . $t['id'] . '/impersonate') ?>" method="POST" onsubmit="return confirm('Iniciar sesion como owner de este tenant?')">
                    <?= csrf_field() ?>
                    <button class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-cyan-500/15 text-cyan-300 hover:bg-cyan-500/25">👤 Impersonar owner</button>
                </form>
                <span class="text-[11px] text-slate-500 ml-auto self-center font-mono"><?= e((string) ($t['uuid'] ?? '')) ?></span>
            </div>
        </div>
    </details>
    <?php endforeach; ?>
</div>

<?php \App\Core\View::stop(); ?>
