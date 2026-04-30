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
<?php \App\Core\View::include('components.page_header', [
    'title'    => $entry['name'],
    'subtitle' => $entry['description'] ?? '',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'integrations']); ?>

<a href="<?= url('/settings/integrations') ?>" class="text-xs flex items-center gap-1 mb-4" style="color: var(--color-text-tertiary);">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Volver al catalogo
</a>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#FB7185;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
        <div class="surface p-5">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold" style="background: <?= $isConnected ? 'rgba(16,185,129,.15)' : 'var(--color-bg-subtle)' ?>; color: <?= $isConnected ? '#10B981' : 'var(--color-text-tertiary)' ?>;">
                        <?= $isConnected ? '● Conectada' : '○ Desconectada' ?>
                    </span>
                    <?php if (!empty($entry['docs'])): ?>
                    <a href="<?= e($entry['docs']) ?>" target="_blank" class="text-xs underline" style="color: var(--color-primary);">Documentacion oficial ↗</a>
                    <?php endif; ?>
                </div>
                <?php if ($isConnected): ?>
                <div class="flex items-center gap-1.5">
                    <button type="button" onclick="testNow()" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Probar conexion</button>
                    <form action="<?= url('/settings/integrations/' . $entry['slug'] . '/disconnect') ?>" method="POST" class="inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs" style="color:#F87171;" onclick="return confirm('Desconectar esta integracion?')">Desconectar</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <form action="<?= url('/settings/integrations/' . $entry['slug'] . '/connect') ?>" method="POST" class="space-y-3">
                <?= csrf_field() ?>
                <?php foreach ($entry['fields'] ?? [] as $field):
                    $type = $field['type'] ?? 'text';
                    $val  = $creds[$field['key']] ?? $config[$field['key']] ?? '';
                ?>
                <div>
                    <label class="label">
                        <?= e($field['label']) ?>
                        <?php if (!empty($field['required'])): ?><span style="color:#F87171;">*</span><?php endif; ?>
                    </label>
                    <?php if ($type === 'textarea'): ?>
                    <textarea name="<?= e($field['key']) ?>" class="input font-mono text-xs" rows="6" <?= !empty($field['required']) && empty($val) ? 'required' : '' ?>><?= e((string) $val) ?></textarea>
                    <?php elseif ($type === 'color'): ?>
                    <input type="color" name="<?= e($field['key']) ?>" value="<?= e((string) ($val ?: '#7C3AED')) ?>" class="input h-[42px] p-1">
                    <?php elseif ($type === 'password'): ?>
                    <input type="password" name="<?= e($field['key']) ?>" placeholder="<?= !empty($val) ? '•••••• (reemplazar)' : '' ?>" class="input font-mono text-sm">
                    <?php if (!empty($val)): ?><p class="text-[10px] mt-1" style="color: var(--color-text-tertiary);">Deja vacio para mantener el valor actual.</p><?php endif; ?>
                    <?php else: ?>
                    <input type="<?= e($type) ?>" name="<?= e($field['key']) ?>" value="<?= e((string) $val) ?>" class="input" <?= !empty($field['required']) ? 'required' : '' ?>>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="submit" class="px-5 py-2 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                        <?= $isConnected ? 'Guardar cambios' : 'Conectar ' . e($entry['name']) ?>
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($logs)): ?>
        <div class="surface p-5">
            <h3 class="font-bold text-sm mb-3" style="color: var(--color-text-primary);">Actividad reciente</h3>
            <div class="space-y-1 max-h-72 overflow-y-auto">
                <?php foreach ($logs as $log): ?>
                <div class="flex items-center gap-2 text-xs py-1.5 border-b" style="border-color: var(--color-border-subtle);">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: <?= !empty($log['success']) ? '#10B981' : '#F43F5E' ?>;"></span>
                    <span class="font-mono text-[10px] flex-shrink-0" style="color: var(--color-text-muted);"><?= date('d M H:i', strtotime((string) $log['created_at'])) ?></span>
                    <span class="font-semibold flex-shrink-0" style="color: var(--color-text-secondary);"><?= e($log['event']) ?></span>
                    <?php if (!empty($log['error_message'])): ?>
                    <span class="truncate" style="color:#F87171;"><?= e((string) $log['error_message']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <aside class="space-y-4">
        <div class="surface p-4">
            <h4 class="text-[10px] uppercase tracking-wider font-semibold mb-2" style="color: var(--color-text-tertiary);">Webhook URL</h4>
            <div class="flex items-center gap-2">
                <code class="flex-1 text-[10px] font-mono break-all p-2 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);"><?= e($webhookUrl) ?></code>
                <button type="button" onclick="navigator.clipboard.writeText('<?= e($webhookUrl) ?>')" class="px-2 py-1 rounded glass text-[10px]">Copiar</button>
            </div>
            <p class="text-[10px] mt-2" style="color: var(--color-text-tertiary);">Configura este URL como webhook en <?= e($entry['name']) ?> para recibir eventos en tiempo real.</p>
        </div>

        <?php if (!empty($entry['fields'])): ?>
        <div class="surface p-4">
            <h4 class="text-[10px] uppercase tracking-wider font-semibold mb-2" style="color: var(--color-text-tertiary);">Que necesitas</h4>
            <ul class="space-y-1.5">
                <?php foreach ($entry['fields'] as $field): ?>
                <li class="text-xs flex items-start gap-1.5" style="color: var(--color-text-secondary);">
                    <span style="color:#34D399;">✓</span>
                    <?= e($field['label']) ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($entry['is_premium'])): ?>
        <div class="surface p-4 relative overflow-hidden" style="background: linear-gradient(135deg, rgba(245,158,11,.08), rgba(244,63,94,.08));">
            <div class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: #F59E0B;">Integracion Premium</div>
            <p class="text-xs mb-2" style="color: var(--color-text-secondary);">Esta integracion forma parte del plan <?= e(ucfirst((string) ($entry['min_plan'] ?? 'business'))) ?> o superior.</p>
            <a href="<?= url('/admin/plans') ?>" class="text-xs font-semibold" style="color: var(--color-primary);">Ver planes →</a>
        </div>
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

<?php \App\Core\View::stop(); ?>
