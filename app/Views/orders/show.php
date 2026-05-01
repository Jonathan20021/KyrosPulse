<?php
/** @var array $order */
/** @var array $items */
/** @var array $events */
/** @var array $statuses */
/** @var array $flow */
/** @var array $zones */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$status = (string) $order['status'];
[$stLbl, $stColor, $stEmoji] = $statuses[$status] ?? [$status, '#94A3B8', '•'];
$nextStates = $flow[$status] ?? [];
$customerName = trim((($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: ($order['customer_name'] ?? 'Cliente'));
?>
<a href="<?= url('/orders') ?>" class="text-xs flex items-center gap-1 mb-4" style="color: var(--color-text-tertiary);">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Volver a ordenes
</a>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>

<div class="surface p-5 mb-4">
    <div class="flex items-start gap-4 flex-wrap">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <h1 class="text-2xl font-black" style="color: var(--color-text-primary);">#<?= e((string) $order['code']) ?></h1>
                <span class="px-2 py-1 rounded-full text-xs font-bold flex items-center gap-1" style="background: <?= $stColor ?>22; color: <?= $stColor ?>;">
                    <?= $stEmoji ?> <?= e($stLbl) ?>
                </span>
                <?php if (!empty($order['is_ai_generated'])): ?>
                <span class="px-2 py-1 rounded-full text-xs font-bold" style="background: rgba(124,58,237,.15); color:#A78BFA;">🤖 Generada por IA</span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3 text-sm flex-wrap" style="color: var(--color-text-secondary);">
                <span class="font-semibold"><?= e($customerName) ?></span>
                <?php if (!empty($order['customer_phone'] ?? $order['contact_phone'])): ?>
                <span class="font-mono"><?= e((string) ($order['customer_phone'] ?: $order['contact_phone'])) ?></span>
                <?php endif; ?>
                <span class="px-2 py-0.5 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);">
                    <?= match($order['delivery_type']) { 'pickup' => '🛍 Pickup', 'dine_in' => '🍴 Mesa', default => '🛵 Delivery' } ?>
                </span>
            </div>
            <?php if (!empty($order['delivery_address'])): ?>
            <div class="text-sm mt-1" style="color: var(--color-text-tertiary);">📍 <?= e((string) $order['delivery_address']) ?><?= !empty($order['zone_name']) ? ' · ' . e((string) $order['zone_name']) : '' ?></div>
            <?php endif; ?>
        </div>

        <div class="flex flex-col items-end">
            <div class="text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary);">Total</div>
            <div class="text-3xl font-black" style="color: var(--color-primary);"><?= e((string) $order['currency']) ?> <?= number_format((float) $order['total'], 2) ?></div>
            <div class="text-[10px] mt-1" style="color: var(--color-text-tertiary);">
                <?= e((string) $order['payment_method']) ?> · <?= e((string) $order['payment_status']) ?>
            </div>
        </div>
    </div>

    <!-- Acciones de estado -->
    <?php if (!empty($nextStates) || $status !== 'cancelled'): ?>
    <div class="flex items-center gap-2 mt-4 pt-4 border-t flex-wrap" style="border-color: var(--color-border-subtle);">
        <?php foreach ($nextStates as $next):
            [$nLbl, $nColor, $nEm] = $statuses[$next] ?? [$next, '#94A3B8', '•'];
        ?>
        <form action="<?= url('/orders/' . $order['id'] . '/status') ?>" method="POST" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="<?= e($next) ?>">
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5" style="background: <?= $nColor ?>22; color: <?= $nColor ?>; border: 1px solid <?= $nColor ?>55;">
                <?= $nEm ?> Marcar <?= e($nLbl) ?>
            </button>
        </form>
        <?php endforeach; ?>
        <?php if ($status !== 'cancelled' && $status !== 'delivered'): ?>
        <form action="<?= url('/orders/' . $order['id'] . '/cancel') ?>" method="POST" class="inline ml-auto" onsubmit="return confirm('Cancelar esta orden?')">
            <?= csrf_field() ?>
            <input type="text" name="reason" placeholder="Motivo (opcional)" class="px-3 py-1.5 rounded-lg text-xs mr-1" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
            <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-semibold" style="background: rgba(244,63,94,.15); color:#F87171;">Cancelar orden</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="grid lg:grid-cols-3 gap-4">
    <!-- Items -->
    <div class="lg:col-span-2 surface p-5">
        <h3 class="font-bold mb-3" style="color: var(--color-text-primary);">Articulos</h3>
        <?php if (empty($items)): ?>
        <p class="text-sm" style="color: var(--color-text-tertiary);">Sin items.</p>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($items as $it):
                $mods = !empty($it['modifiers']) ? (json_decode((string) $it['modifiers'], true) ?: []) : [];
            ?>
            <div class="flex items-start gap-3 p-3 rounded-xl" style="background: var(--color-bg-subtle);">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold flex-shrink-0" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                    <?= (int) $it['qty'] ?>×
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm" style="color: var(--color-text-primary);"><?= e($it['name']) ?></div>
                    <?php if (!empty($mods)): ?>
                    <div class="text-[11px] mt-0.5" style="color: var(--color-text-tertiary);">
                        + <?= e(implode(', ', array_map(fn ($m) => $m['name'], $mods))) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($it['notes'])): ?>
                    <div class="text-[11px] mt-0.5 italic" style="color: var(--color-text-tertiary);">📝 <?= e((string) $it['notes']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-right">
                    <div class="text-xs font-mono" style="color: var(--color-text-tertiary);"><?= e((string) $order['currency']) ?> <?= number_format((float) $it['unit_price'], 2) ?></div>
                    <div class="text-sm font-bold" style="color: var(--color-text-primary);"><?= e((string) $order['currency']) ?> <?= number_format((float) $it['subtotal'], 2) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 pt-4 border-t space-y-1.5 text-sm" style="border-color: var(--color-border-subtle);">
            <div class="flex justify-between"><span style="color: var(--color-text-tertiary);">Subtotal</span><span class="font-mono" style="color: var(--color-text-primary);"><?= e((string) $order['currency']) ?> <?= number_format((float) $order['subtotal'], 2) ?></span></div>
            <?php if ((float) $order['delivery_fee'] > 0): ?>
            <div class="flex justify-between"><span style="color: var(--color-text-tertiary);">Envio</span><span class="font-mono" style="color: var(--color-text-primary);"><?= e((string) $order['currency']) ?> <?= number_format((float) $order['delivery_fee'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ((float) $order['tax'] > 0): ?>
            <div class="flex justify-between"><span style="color: var(--color-text-tertiary);">Impuesto</span><span class="font-mono" style="color: var(--color-text-primary);"><?= e((string) $order['currency']) ?> <?= number_format((float) $order['tax'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ((float) $order['discount'] > 0): ?>
            <div class="flex justify-between"><span style="color: var(--color-text-tertiary);">Descuento</span><span class="font-mono" style="color: #34D399;">- <?= e((string) $order['currency']) ?> <?= number_format((float) $order['discount'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ((float) $order['tip'] > 0): ?>
            <div class="flex justify-between"><span style="color: var(--color-text-tertiary);">Propina</span><span class="font-mono" style="color: var(--color-text-primary);"><?= e((string) $order['currency']) ?> <?= number_format((float) $order['tip'], 2) ?></span></div>
            <?php endif; ?>
            <div class="flex justify-between text-base pt-2 mt-2 border-t font-bold" style="border-color: var(--color-border-subtle); color: var(--color-text-primary);">
                <span>Total</span>
                <span class="font-mono" style="color: var(--color-primary);"><?= e((string) $order['currency']) ?> <?= number_format((float) $order['total'], 2) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar: notas + timeline + payment -->
    <aside class="space-y-4">
        <?php if (!empty($order['payment_link'])): ?>
        <div class="surface p-4">
            <h4 class="text-[10px] uppercase tracking-wider font-semibold mb-2" style="color: var(--color-text-tertiary);">Link de pago</h4>
            <a href="<?= e((string) $order['payment_link']) ?>" target="_blank" class="block text-xs font-mono break-all p-2 rounded" style="background: var(--color-bg-subtle); color: var(--color-primary);"><?= e((string) $order['payment_link']) ?></a>
        </div>
        <?php endif; ?>

        <div class="surface p-4">
            <h4 class="text-[10px] uppercase tracking-wider font-semibold mb-2" style="color: var(--color-text-tertiary);">Notas</h4>
            <form action="<?= url('/orders/' . $order['id'] . '/notes') ?>" method="POST" class="space-y-2">
                <?= csrf_field() ?>
                <textarea name="kitchen_notes" placeholder="Notas para cocina" class="textarea text-xs" rows="2"><?= e((string) ($order['kitchen_notes'] ?? '')) ?></textarea>
                <textarea name="delivery_notes" placeholder="Notas para delivery" class="textarea text-xs" rows="2"><?= e((string) ($order['delivery_notes'] ?? '')) ?></textarea>
                <button type="submit" class="w-full px-3 py-1.5 rounded-lg text-xs font-semibold" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Guardar notas</button>
            </form>
        </div>

        <div class="surface p-4">
            <h4 class="text-[10px] uppercase tracking-wider font-semibold mb-2" style="color: var(--color-text-tertiary);">Timeline</h4>
            <div class="space-y-2 max-h-72 overflow-y-auto">
                <?php foreach ($events as $e):
                    $ec = $statuses[$e['to_status']] ?? null;
                ?>
                <div class="flex items-start gap-2 text-xs">
                    <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background: <?= $ec[1] ?? '#94A3B8' ?>;"></div>
                    <div class="flex-1 min-w-0">
                        <div style="color: var(--color-text-primary);">
                            <?= e($e['event']) ?>
                            <?php if (!empty($e['to_status'])): ?>
                            → <span style="color: <?= $ec[1] ?? '#94A3B8' ?>; font-weight: 600;"><?= e((string) ($ec[0] ?? $e['to_status'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($e['note'])): ?>
                        <div class="text-[10px]" style="color: var(--color-text-tertiary);"><?= e((string) $e['note']) ?></div>
                        <?php endif; ?>
                        <div class="text-[10px] font-mono" style="color: var(--color-text-muted);">
                            <?= date('d M H:i', strtotime((string) $e['created_at'])) ?>
                            <?php if (!empty($e['first_name'])): ?> · <?= e($e['first_name'] . ' ' . ($e['last_name'] ?? '')) ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>
</div>

<?php \App\Core\View::stop(); ?>
