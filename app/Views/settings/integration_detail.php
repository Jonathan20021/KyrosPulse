<?php
/** @var array $entry */
/** @var array|null $existing */
/** @var array $creds */
/** @var string $webhookUrl */
/** @var array $logs */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$status = $existing['status'] ?? 'disconnected';
$isConnected = $status === 'connected';
$config = $existing && !empty($existing['config']) ? (json_decode((string) $existing['config'], true) ?: []) : [];
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'integrations']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Canales / Integraciones',
    'title'    => $entry['name'],
    'subtitle' => $entry['description'] ?? '',
    'back'     => ['href' => url('/settings/integrations'), 'label' => 'Volver al catalogo'],
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="set-split-2-1">
    <div class="set-split-main">
        <section class="set-section">
            <div class="set-section-head">
                <div>
                    <h2 class="set-section-title">
                        <span class="set-status-pill" style="--pill-color: <?= $isConnected ? '#10B981' : '#94A3B8' ?>;">
                            <?= $isConnected ? '● Conectada' : '○ Desconectada' ?>
                        </span>
                    </h2>
                    <?php if (!empty($entry['docs'])): ?>
                    <p class="set-section-desc"><a href="<?= e($entry['docs']) ?>" target="_blank" class="set-link">Documentacion oficial ↗</a></p>
                    <?php endif; ?>
                </div>
                <?php if ($isConnected): ?>
                <div class="set-inline-actions">
                    <button type="button" onclick="testNow()" class="set-btn set-btn-ghost set-btn-sm">Probar conexion</button>
                    <form action="<?= url('/settings/integrations/' . $entry['slug'] . '/disconnect') ?>" method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <button type="submit" class="set-btn set-btn-danger set-btn-sm" onclick="return confirm('Desconectar esta integracion?')">Desconectar</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <form action="<?= url('/settings/integrations/' . $entry['slug'] . '/connect') ?>" method="POST">
                <?= csrf_field() ?>
                <?php foreach ($entry['fields'] ?? [] as $field):
                    $type = $field['type'] ?? 'text';
                    $val  = $creds[$field['key']] ?? $config[$field['key']] ?? '';
                ?>
                <div class="set-field">
                    <label class="set-label">
                        <?= e($field['label']) ?>
                        <?php if (!empty($field['required'])): ?><span class="req">*</span><?php endif; ?>
                    </label>
                    <?php if ($type === 'textarea'): ?>
                    <textarea name="<?= e($field['key']) ?>" class="set-textarea set-mono" rows="6" <?= !empty($field['required']) && empty($val) ? 'required' : '' ?>><?= e((string) $val) ?></textarea>
                    <?php elseif ($type === 'color'): ?>
                    <input type="color" name="<?= e($field['key']) ?>" value="<?= e((string) ($val ?: '#10B981')) ?>" class="set-input" style="height:42px; padding:4px;">
                    <?php elseif ($type === 'password'): ?>
                    <input type="password" name="<?= e($field['key']) ?>" placeholder="<?= !empty($val) ? '•••••• (reemplazar)' : '' ?>" class="set-input set-mono">
                    <?php if (!empty($val)): ?><p class="set-help">Deja vacio para mantener el valor actual.</p><?php endif; ?>
                    <?php else: ?>
                    <input type="<?= e($type) ?>" name="<?= e($field['key']) ?>" value="<?= e((string) $val) ?>" class="set-input" <?= !empty($field['required']) ? 'required' : '' ?>>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <div class="set-actions">
                    <button type="submit" class="set-btn set-btn-primary">
                        <?= $isConnected ? 'Guardar cambios' : 'Conectar ' . e($entry['name']) ?>
                    </button>
                </div>
            </form>
        </section>

        <?php if (!empty($logs)): ?>
        <section class="set-section">
            <div class="set-section-head">
                <h2 class="set-section-title"><span>📋</span> Actividad reciente</h2>
            </div>
            <ul class="set-log-list">
                <?php foreach ($logs as $log): ?>
                <li class="set-log-item">
                    <span class="set-log-dot" style="background: <?= !empty($log['success']) ? '#10B981' : '#F43F5E' ?>;"></span>
                    <span class="set-log-time"><?= date('d M H:i', strtotime((string) $log['created_at'])) ?></span>
                    <span class="set-log-event"><?= e($log['event']) ?></span>
                    <?php if (!empty($log['error_message'])): ?>
                    <span class="set-log-err"><?= e((string) $log['error_message']) ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>

    <aside class="set-split-aside">
        <section class="set-section">
            <h4 class="set-aside-title">Webhook URL</h4>
            <div class="set-code-row">
                <code class="set-code"><?= e($webhookUrl) ?></code>
                <button type="button" onclick="navigator.clipboard.writeText('<?= e($webhookUrl) ?>')" class="set-btn set-btn-ghost set-btn-sm">Copiar</button>
            </div>
            <p class="set-help">Configura este URL como webhook en <?= e($entry['name']) ?> para recibir eventos en tiempo real.</p>
        </section>

        <?php if (!empty($entry['fields'])): ?>
        <section class="set-section">
            <h4 class="set-aside-title">Que necesitas</h4>
            <ul class="set-check-list">
                <?php foreach ($entry['fields'] as $field): ?>
                <li><span class="set-check-mark">✓</span> <?= e($field['label']) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if (!empty($entry['is_premium'])): ?>
        <section class="set-section set-section-premium">
            <h4 class="set-aside-title" style="color: #F59E0B;">Integracion Premium</h4>
            <p class="set-help" style="margin-top: 4px;">Esta integracion forma parte del plan <?= e(ucfirst((string) ($entry['min_plan'] ?? 'business'))) ?> o superior.</p>
            <a href="<?= url('/admin/plans') ?>" class="set-link">Ver planes →</a>
        </section>
        <?php endif; ?>
    </aside>
</div>

<script>
async function testNow() {
    try {
        const res = await fetch('<?= url('/settings/integrations/' . $entry['slug'] . '/test') ?>', {
            method: 'POST',
            headers: { 'X-CSRF-Token': '<?= csrf_token() ?>', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        const data = await res.json();
        alert(data.success ? 'Conexion OK: ' + (data.message || '') : 'Error: ' + (data.error || ''));
    } catch (e) { alert('Error: ' + e.message); }
}
</script>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
