<?php
/**
 * @var array      $user
 * @var bool       $twofa_enabled
 * @var string|null $twofa_secret
 * @var string|null $qr_url
 * @var int        $recovery_count
 * @var array|null $new_recovery_codes
 * @var array      $sessions
 * @var string     $current_session_hash
 * @var array      $events
 * @var array      $tenant_events
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'security']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Plataforma',
    'title'    => 'Seguridad',
    'subtitle' => '2FA, sesiones activas, eventos de seguridad y cambio de password. Todo lo que necesitas para mantener tu cuenta blindada.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php if ($new_recovery_codes): ?>
<div class="set-secret-banner set-secret-banner-warn">
    <div class="set-secret-head">
        <div class="set-secret-icon" style="background: rgba(245,158,11,.15);">🔑</div>
        <div>
            <div class="set-secret-title">Recovery codes — guardalos AHORA</div>
            <p class="set-secret-desc">Si pierdes acceso a tu app autenticadora, podras entrar con uno de estos. Cada codigo es de un solo uso. No se mostraran de nuevo.</p>
        </div>
    </div>
    <div class="set-recovery-grid">
        <?php foreach ($new_recovery_codes as $code): ?>
        <code class="set-recovery-code"><?= e((string) $code) ?></code>
        <?php endforeach; ?>
    </div>
    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('recovery-block').textContent); this.innerText='Copiados!'; setTimeout(()=>this.innerText='Copiar todos al portapapeles',1500);"
            class="set-btn set-btn-warn" style="margin-top: 12px;">Copiar todos al portapapeles</button>
    <div id="recovery-block" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);"><?= e(implode("\n", $new_recovery_codes)) ?></div>
</div>
<?php endif; ?>

<div class="set-kpi-grid">
    <div class="set-kpi">
        <div class="set-kpi-head">
            <span class="set-kpi-label">2FA</span>
            <span class="set-kpi-emoji"><?= $twofa_enabled ? '✅' : '⚠' ?></span>
        </div>
        <div class="set-kpi-value" style="color: <?= $twofa_enabled ? '#0B7C56' : '#B45309' ?>; font-size: 18px;">
            <?= $twofa_enabled ? 'Activa' : 'Desactivada' ?>
        </div>
    </div>
    <div class="set-kpi">
        <div class="set-kpi-head">
            <span class="set-kpi-label">Recovery</span>
            <span class="set-kpi-emoji">🔑</span>
        </div>
        <div class="set-kpi-value"><?= (int) $recovery_count ?></div>
    </div>
    <div class="set-kpi">
        <div class="set-kpi-head">
            <span class="set-kpi-label">Sesiones activas</span>
            <span class="set-kpi-emoji">💻</span>
        </div>
        <div class="set-kpi-value"><?= count($sessions) ?></div>
    </div>
    <div class="set-kpi">
        <div class="set-kpi-head">
            <span class="set-kpi-label">Eventos (30 ult)</span>
            <span class="set-kpi-emoji">📋</span>
        </div>
        <div class="set-kpi-value"><?= count($events) ?></div>
    </div>
</div>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>🔐</span> Autenticacion en 2 pasos (TOTP)</h2>
            <p class="set-section-desc">Tras la password, pedimos un codigo temporal de tu app autenticadora. Soporta Google Authenticator, Authy, 1Password, Microsoft Authenticator y cualquier app TOTP estandar.</p>
        </div>
    </div>

    <?php if ($twofa_enabled): ?>
    <div class="set-flash set-flash-success" style="margin-bottom: 12px;">
        <span>✓</span>
        <span><strong>2FA activa.</strong> Te quedan <strong><?= (int) $recovery_count ?></strong> recovery codes sin usar.</span>
    </div>

    <div class="set-field-row cols-2 set-field">
        <form action="<?= url('/settings/security/2fa/recovery/regen') ?>" method="POST" onsubmit="return confirm('Esto invalida los recovery codes actuales y genera nuevos. Continuar?')">
            <?= csrf_field() ?>
            <button class="set-btn set-btn-ghost set-btn-block">🔁 Regenerar recovery codes</button>
        </form>
        <form action="<?= url('/settings/security/2fa/disable') ?>" method="POST" x-data="{ open: false }" style="position:relative;">
            <?= csrf_field() ?>
            <button type="button" @click="open = !open" class="set-btn set-btn-danger set-btn-block">⚠ Desactivar 2FA</button>
            <div x-show="open" x-cloak class="set-popover">
                <label class="set-label">Confirma tu password</label>
                <input type="password" name="password" required class="set-input">
                <button class="set-btn set-btn-danger set-btn-block" style="margin-top: 8px;">Confirmar desactivacion</button>
            </div>
        </form>
    </div>
    <?php elseif ($twofa_secret && $qr_url): ?>
    <div class="set-field-row cols-2 set-field">
        <div>
            <div class="set-step-label">1. Escanea con tu app</div>
            <img src="<?= e($qr_url) ?>" alt="QR 2FA" class="set-qr">
            <details style="margin-top: 8px;">
                <summary class="set-help" style="cursor: pointer;">No puedes escanear? Ingresalo manual</summary>
                <code class="set-mono-xs set-break-all" style="display:block; margin-top: 6px; padding: 6px 10px; background: var(--color-bg-subtle); border-radius: 6px;"><?= e((string) $twofa_secret) ?></code>
            </details>
        </div>
        <form action="<?= url('/settings/security/2fa/confirm') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="set-step-label">2. Escribe el codigo actual</div>
            <input type="text" name="code" required inputmode="numeric" maxlength="6" placeholder="000000"
                   autocomplete="one-time-code"
                   class="set-input set-input-otp">
            <p class="set-help">Esto activa 2FA y te dara 10 recovery codes de un solo uso.</p>
            <button class="set-btn set-btn-primary set-btn-block" style="margin-top: 12px;">Activar 2FA</button>
        </form>
    </div>
    <?php else: ?>
    <form action="<?= url('/settings/security/2fa/setup') ?>" method="POST">
        <?= csrf_field() ?>
        <button class="set-btn set-btn-primary">Activar 2FA ahora</button>
    </form>
    <?php endif; ?>
</section>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>🔑</span> Cambiar password</h2>
            <p class="set-section-desc">Cambiar la password revoca automaticamente todas tus otras sesiones.</p>
        </div>
    </div>
    <form action="<?= url('/settings/security/password') ?>" method="POST">
        <?= csrf_field() ?>
        <div class="set-field-row cols-3 set-field">
            <input type="password" name="current_password" placeholder="Password actual" required class="set-input">
            <input type="password" name="new_password" placeholder="Nueva (min 8)" required minlength="8" class="set-input">
            <input type="password" name="confirm_password" placeholder="Confirmar" required minlength="8" class="set-input">
        </div>
        <div class="set-actions">
            <button class="set-btn set-btn-primary">Cambiar password</button>
        </div>
    </form>
</section>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>💻</span> Sesiones activas <span class="set-count">(<?= count($sessions) ?>)</span></h2>
        </div>
        <?php if (count($sessions) > 1): ?>
        <form action="<?= url('/settings/security/sessions/revoke-others') ?>" method="POST" onsubmit="return confirm('Cerrar todas las otras sesiones excepto esta?')">
            <?= csrf_field() ?>
            <button class="set-btn set-btn-danger set-btn-sm">Cerrar otras sesiones</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if (empty($sessions)): ?>
    <div class="set-empty">
        <p class="set-empty-text">Sin sesiones registradas (la actual aparecera tras el proximo request).</p>
    </div>
    <?php else: ?>
    <ul class="set-rule-list">
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
        <li class="set-rule-item">
            <div class="set-rule-icon" style="background: <?= $isCurrent ? 'rgba(16,185,129,.15)' : 'var(--color-bg-subtle)' ?>;"><?= $os === 'Android' || $os === 'iOS' ? '📱' : '💻' ?></div>
            <div class="set-rule-body">
                <div class="set-rule-head">
                    <span class="set-rule-name"><?= e($browser) ?> · <?= e($os) ?></span>
                    <?php if ($isCurrent): ?>
                    <span class="set-badge" style="background: rgba(16,185,129,.15); color:#0B7C56;">ESTA SESION</span>
                    <?php endif; ?>
                </div>
                <div class="set-rule-meta"><?= e((string) $s['ip']) ?> · ultima actividad <?= e((string) $s['last_seen_at']) ?></div>
            </div>
            <?php if (!$isCurrent): ?>
            <div class="set-rule-actions">
                <form action="<?= url('/settings/security/sessions/' . (int) $s['id'] . '/revoke') ?>" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="set-btn set-btn-ghost set-btn-sm">Revocar</button>
                </form>
            </div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>📋</span> Eventos recientes</h2>
            <p class="set-section-desc">Login, cambios de password, activacion/desactivacion de 2FA, revocacion de sesiones.</p>
        </div>
    </div>
    <?php if (empty($events)): ?>
    <div class="set-empty">
        <p class="set-empty-text">Sin eventos aun.</p>
    </div>
    <?php else: ?>
    <div class="set-table-wrap">
        <table class="set-table">
            <thead>
                <tr>
                    <th>Cuando</th>
                    <th>Evento</th>
                    <th>Severidad</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $ev):
                    $col = match ($ev['severity']) {
                        'critical' => '#BE123C',
                        'warning'  => '#B45309',
                        default    => '#0B7C56',
                    };
                ?>
                <tr>
                    <td class="set-td-meta"><?= e((string) $ev['created_at']) ?></td>
                    <td><code class="set-mono-xs"><?= e((string) $ev['event']) ?></code></td>
                    <td><span class="set-td-strong" style="color: <?= $col ?>;"><?= e((string) $ev['severity']) ?></span></td>
                    <td class="set-td-meta set-mono-xs"><?= e((string) ($ev['ip'] ?? '—')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
