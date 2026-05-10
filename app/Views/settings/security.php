<?php
/**
 * @var array      $user
 * @var bool       $twofa_enabled
 * @var string|null $twofa_secret          (presente solo cuando hay setup en curso)
 * @var string|null $qr_url
 * @var int        $recovery_count
 * @var array|null $new_recovery_codes     (codes plaintext flasheados 1 sola vez)
 * @var array      $sessions
 * @var string     $current_session_hash
 * @var array      $events                 propios del usuario
 * @var array      $tenant_events          del tenant entero
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Seguridad',
    'subtitle' => '2FA, sesiones activas, eventos de seguridad y cambio de password. Todo lo que necesitas para mantener tu cuenta blindada.',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'security']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php if ($new_recovery_codes): ?>
<!-- One-shot recovery codes -->
<div class="mb-6 rounded-2xl border p-5" style="background: linear-gradient(135deg, rgba(245,158,11,.08), rgba(244,63,94,.04)); border-color: rgba(245,158,11,.35);">
    <div class="flex items-start gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl" style="background: rgba(245,158,11,.15);">🔑</div>
        <div class="flex-1 min-w-0">
            <div class="font-bold mb-0.5" style="color: var(--color-text-primary);">Recovery codes — guardalos AHORA</div>
            <p class="text-sm" style="color: var(--color-text-secondary);">Si pierdes acceso a tu app autenticadora, podras entrar con uno de estos. Cada codigo es de un solo uso. No se mostraran de nuevo.</p>
        </div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-3">
        <?php foreach ($new_recovery_codes as $code): ?>
        <code class="font-mono text-sm font-bold px-3 py-2 rounded-lg text-center" style="background: var(--color-bg-secondary); color: var(--color-text-primary);"><?= e((string) $code) ?></code>
        <?php endforeach; ?>
    </div>
    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('recovery-block').textContent); this.innerText='Copiados!'; setTimeout(()=>this.innerText='Copiar todos al portapapeles',1500);"
            class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#F59E0B,#EF4444);">Copiar todos al portapapeles</button>
    <div id="recovery-block" class="sr-only"><?= e(implode("\n", $new_recovery_codes)) ?></div>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">2FA</span>
            <span class="text-xl"><?= $twofa_enabled ? '✅' : '⚠' ?></span>
        </div>
        <div class="text-base font-bold" style="color: <?= $twofa_enabled ? '#0B7C56' : '#B45309' ?>;">
            <?= $twofa_enabled ? 'Activa' : 'Desactivada' ?>
        </div>
    </div>
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">Recovery</span>
            <span class="text-xl">🔑</span>
        </div>
        <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= (int) $recovery_count ?></div>
    </div>
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">Sesiones activas</span>
            <span class="text-xl">💻</span>
        </div>
        <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= count($sessions) ?></div>
    </div>
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">Eventos (30 ult)</span>
            <span class="text-xl">📋</span>
        </div>
        <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= count($events) ?></div>
    </div>
</div>

<!-- 2FA setup -->
<div class="surface p-5 mb-5">
    <div class="flex items-start gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl flex-shrink-0" style="background: <?= $twofa_enabled ? 'rgba(16,185,129,.15)' : 'rgba(245,158,11,.15)' ?>;">🔐</div>
        <div class="flex-1 min-w-0">
            <div class="font-bold mb-0.5" style="color: var(--color-text-primary);">Autenticacion en 2 pasos (TOTP)</div>
            <p class="text-sm" style="color: var(--color-text-secondary);">Tras la password, pedimos un codigo temporal de tu app autenticadora. Soporta Google Authenticator, Authy, 1Password, Microsoft Authenticator y cualquier app TOTP estandar.</p>
        </div>
    </div>

    <?php if ($twofa_enabled): ?>
    <div class="space-y-3">
        <div class="p-3 rounded-lg text-sm flex items-center gap-2" style="background: rgba(16,185,129,.08); color:#0B7C56;">
            <span class="font-semibold">✓ 2FA activa.</span>
            <span style="color: var(--color-text-secondary);">Te quedan <strong><?= (int) $recovery_count ?></strong> recovery codes sin usar.</span>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <form action="<?= url('/settings/security/2fa/recovery/regen') ?>" method="POST" onsubmit="return confirm('Esto invalida los recovery codes actuales y genera nuevos. Continuar?')">
                <?= csrf_field() ?>
                <button class="w-full px-3 py-2 rounded-lg text-sm font-semibold border" style="border-color: var(--color-border); color: var(--color-text-primary);">🔁 Regenerar recovery codes</button>
            </form>
            <form action="<?= url('/settings/security/2fa/disable') ?>" method="POST" x-data="{ open: false }" class="relative">
                <?= csrf_field() ?>
                <button type="button" @click="open = !open" class="w-full px-3 py-2 rounded-lg text-sm font-semibold border" style="color:#BE123C; border-color: rgba(244,63,94,.3); background: rgba(244,63,94,.05);">⚠ Desactivar 2FA</button>
                <div x-show="open" x-cloak class="absolute z-20 mt-2 inset-x-0 p-3 rounded-lg" style="background: var(--color-bg-card); border:1px solid var(--color-border); box-shadow:0 12px 30px rgba(0,0,0,.4);">
                    <label class="text-xs block mb-1" style="color: var(--color-text-secondary);">Confirma tu password</label>
                    <input type="password" name="password" required class="w-full input mb-2">
                    <button class="w-full px-3 py-1.5 rounded-lg text-sm font-semibold text-white" style="background:#BE123C;">Confirmar desactivacion</button>
                </div>
            </form>
        </div>
    </div>
    <?php elseif ($twofa_secret && $qr_url): ?>
    <!-- Setup en curso -->
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <div class="text-xs font-semibold mb-2" style="color: var(--color-text-primary);">1. Escanea con tu app</div>
            <img src="<?= e($qr_url) ?>" alt="QR 2FA" class="rounded-lg border p-2" style="background:#fff; border-color: var(--color-border);">
            <details class="mt-2">
                <summary class="text-xs cursor-pointer" style="color: var(--color-text-tertiary);">No puedes escanear? Ingresalo manual</summary>
                <code class="text-xs font-mono break-all mt-1 block px-2 py-1 rounded" style="background: var(--color-bg-secondary); color: var(--color-text-primary);"><?= e((string) $twofa_secret) ?></code>
            </details>
        </div>
        <form action="<?= url('/settings/security/2fa/confirm') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="text-xs font-semibold mb-2" style="color: var(--color-text-primary);">2. Escribe el codigo actual</div>
            <input type="text" name="code" required inputmode="numeric" maxlength="6" placeholder="000000"
                   autocomplete="one-time-code"
                   class="w-full input font-mono text-lg tracking-widest text-center">
            <p class="text-xs mt-2" style="color: var(--color-text-tertiary);">Esto activa 2FA y te dara 10 recovery codes de un solo uso.</p>
            <button class="mt-3 w-full px-3 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Activar 2FA</button>
        </form>
    </div>
    <?php else: ?>
    <form action="<?= url('/settings/security/2fa/setup') ?>" method="POST">
        <?= csrf_field() ?>
        <button class="px-4 py-2.5 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Activar 2FA ahora</button>
    </form>
    <?php endif; ?>
</div>

<!-- Cambio de password -->
<div class="surface p-5 mb-5">
    <div class="flex items-start gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl flex-shrink-0" style="background: rgba(99,102,241,.15);">🔑</div>
        <div>
            <div class="font-bold" style="color: var(--color-text-primary);">Cambiar password</div>
            <p class="text-sm" style="color: var(--color-text-secondary);">Cambiar la password revoca automaticamente todas tus otras sesiones.</p>
        </div>
    </div>
    <form action="<?= url('/settings/security/password') ?>" method="POST" class="grid sm:grid-cols-3 gap-2">
        <?= csrf_field() ?>
        <input type="password" name="current_password" placeholder="Password actual" required class="input">
        <input type="password" name="new_password"     placeholder="Nueva (min 8)" required minlength="8" class="input">
        <input type="password" name="confirm_password" placeholder="Confirmar" required minlength="8" class="input">
        <div class="sm:col-span-3 flex items-center justify-end">
            <button class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#6366F1,#06B6D4);">Cambiar password</button>
        </div>
    </form>
</div>

<!-- Sesiones activas -->
<div class="surface mb-5 overflow-hidden">
    <div class="px-5 py-3.5 border-b flex items-center justify-between" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Sesiones activas <span class="ml-2 text-xs font-normal" style="color: var(--color-text-tertiary);">(<?= count($sessions) ?>)</span></h3>
        <?php if (count($sessions) > 1): ?>
        <form action="<?= url('/settings/security/sessions/revoke-others') ?>" method="POST" onsubmit="return confirm('Cerrar todas las otras sesiones excepto esta?')">
            <?= csrf_field() ?>
            <button class="text-xs px-3 py-1.5 rounded-lg border" style="color:#BE123C; border-color: rgba(244,63,94,.3); background: rgba(244,63,94,.05);">Cerrar otras sesiones</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if (empty($sessions)): ?>
    <div class="p-6 text-center text-sm" style="color: var(--color-text-secondary);">Sin sesiones registradas (la actual aparecera tras el proximo request).</div>
    <?php else: ?>
    <ul class="divide-y" style="border-color: var(--color-border);">
        <?php foreach ($sessions as $s):
            $isCurrent = $s['session_id'] === $current_session_hash;
            $ua = (string) ($s['user_agent'] ?? '');
            $browser = 'Browser';
            if (stripos($ua, 'Chrome') !== false)       $browser = 'Chrome';
            elseif (stripos($ua, 'Firefox') !== false)  $browser = 'Firefox';
            elseif (stripos($ua, 'Safari') !== false)   $browser = 'Safari';
            elseif (stripos($ua, 'Edge') !== false)     $browser = 'Edge';
            $os = 'OS';
            if (stripos($ua, 'Windows') !== false)      $os = 'Windows';
            elseif (stripos($ua, 'Mac') !== false)      $os = 'macOS';
            elseif (stripos($ua, 'Linux') !== false)    $os = 'Linux';
            elseif (stripos($ua, 'Android') !== false)  $os = 'Android';
            elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iOS') !== false) $os = 'iOS';
        ?>
        <li class="px-5 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-base flex-shrink-0" style="background: <?= $isCurrent ? 'rgba(16,185,129,.15)' : 'var(--color-bg-secondary)' ?>;"><?= $os === 'Android' || $os === 'iOS' ? '📱' : '💻' ?></div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="font-semibold text-sm" style="color: var(--color-text-primary);"><?= e($browser) ?> · <?= e($os) ?></span>
                    <?php if ($isCurrent): ?>
                    <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: rgba(16,185,129,.15); color:#0B7C56;">ESTA SESION</span>
                    <?php endif; ?>
                </div>
                <div class="text-[11px]" style="color: var(--color-text-secondary);"><?= e((string) $s['ip']) ?> · ultima actividad <?= e((string) $s['last_seen_at']) ?></div>
            </div>
            <?php if (!$isCurrent): ?>
            <form action="<?= url('/settings/security/sessions/' . (int) $s['id'] . '/revoke') ?>" method="POST" class="inline">
                <?= csrf_field() ?>
                <button class="text-xs px-2.5 py-1 rounded font-semibold" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">Revocar</button>
            </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<!-- Eventos de seguridad -->
<div class="surface overflow-hidden">
    <div class="px-5 py-3.5 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Eventos recientes</h3>
        <p class="text-xs mt-0.5" style="color: var(--color-text-secondary);">Login, cambios de password, activacion/desactivacion de 2FA, revocacion de sesiones.</p>
    </div>
    <?php if (empty($events)): ?>
    <div class="p-6 text-center text-sm" style="color: var(--color-text-secondary);">Sin eventos aun.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary); background: var(--color-bg-secondary);">
                    <th class="text-left px-4 py-2">Cuando</th>
                    <th class="text-left px-4 py-2">Evento</th>
                    <th class="text-left px-4 py-2">Severidad</th>
                    <th class="text-left px-4 py-2">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $e):
                    $col = match ($e['severity']) {
                        'critical' => '#BE123C',
                        'warning'  => '#B45309',
                        default    => '#0B7C56',
                    };
                ?>
                <tr class="border-t" style="border-color: var(--color-border);">
                    <td class="px-4 py-2 text-xs whitespace-nowrap" style="color: var(--color-text-secondary);"><?= e((string) $e['created_at']) ?></td>
                    <td class="px-4 py-2"><code class="text-xs"><?= e((string) $e['event']) ?></code></td>
                    <td class="px-4 py-2"><span class="text-xs font-semibold" style="color: <?= $col ?>;"><?= e((string) $e['severity']) ?></span></td>
                    <td class="px-4 py-2 text-xs font-mono" style="color: var(--color-text-secondary);"><?= e((string) ($e['ip'] ?? '—')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php \App\Core\View::end(); ?>
