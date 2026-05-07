<?php
/** @var array $tenant */
/** @var array $settings */
/** @var array $zones */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Configuracion del restaurante',
    'subtitle' => 'Activa el modo restaurante, configura impuestos, zonas de entrega, metodos de pago y reglas de pedidos.',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'restaurant']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>

<form action="<?= url('/settings/restaurant') ?>" method="POST" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <!-- Modo restaurante toggle -->
    <div class="surface p-5">
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="is_restaurant" value="1" <?= !empty($tenant['is_restaurant']) ? 'checked' : '' ?> class="w-5 h-5 mt-0.5 rounded">
            <div>
                <div class="font-bold text-base" style="color: var(--color-text-primary);">Activar modo restaurante</div>
                <p class="text-xs" style="color: var(--color-text-tertiary);">Inyecta el menu y reglas de toma de pedidos en el prompt de la IA. Activa el modulo de Ordenes y la pestana de Menu.</p>
            </div>
        </label>
    </div>

    <!-- Datos generales -->
    <div class="surface p-5">
        <h3 class="font-bold mb-3" style="color: var(--color-text-primary);">Datos generales</h3>
        <div class="grid md:grid-cols-3 gap-3">
            <div>
                <label class="label">Tipo de cocina</label>
                <input type="text" name="cuisine_type" value="<?= e((string) ($settings['cuisine_type'] ?? '')) ?>" class="input" placeholder="Mexicana, Pizzeria, Sushi…">
            </div>
            <div>
                <label class="label">Moneda</label>
                <input type="text" name="currency" maxlength="3" value="<?= e((string) ($settings['currency'] ?? 'USD')) ?>" class="input">
            </div>
            <div>
                <label class="label">Tiempo prep. promedio (min)</label>
                <input type="number" name="order_prep_min" value="<?= (int) ($settings['order_prep_min'] ?? 25) ?>" class="input">
            </div>
            <div class="md:col-span-3">
                <label class="label">Direccion del local</label>
                <input type="text" name="address" value="<?= e((string) ($settings['address'] ?? '')) ?>" class="input">
            </div>
            <div class="md:col-span-3">
                <label class="label">PDF del menu (opcional, lo manda la IA)</label>
                <input type="url" name="whatsapp_menu_pdf" value="<?= e((string) ($settings['whatsapp_menu_pdf'] ?? '')) ?>" class="input" placeholder="https://midominio.com/menu.pdf">
            </div>
        </div>
    </div>

    <!-- Reglas comerciales -->
    <div class="surface p-5">
        <h3 class="font-bold mb-3" style="color: var(--color-text-primary);">Reglas comerciales</h3>
        <div class="grid md:grid-cols-3 gap-3">
            <div>
                <label class="label">Impuesto (%)</label>
                <input type="number" name="tax_rate" step="0.1" value="<?= e((string) ($settings['tax_rate'] ?? 0)) ?>" class="input">
            </div>
            <div>
                <label class="label">Propina sugerida (%)</label>
                <input type="number" name="tip_default" step="0.1" value="<?= e((string) ($settings['tip_default'] ?? 0)) ?>" class="input">
            </div>
            <div>
                <label class="label">Pedido minimo</label>
                <input type="number" name="min_order" step="0.01" value="<?= e((string) ($settings['min_order'] ?? 0)) ?>" class="input">
            </div>
        </div>

        <div class="mt-3 grid md:grid-cols-3 gap-3">
            <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer" style="background: var(--color-bg-subtle);">
                <input type="checkbox" name="allow_delivery" value="1" <?= !empty($settings['allow_delivery']) ? 'checked' : '' ?>>
                <span style="color: var(--color-text-primary);">🛵 Permitir delivery</span>
            </label>
            <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer" style="background: var(--color-bg-subtle);">
                <input type="checkbox" name="allow_pickup" value="1" <?= !empty($settings['allow_pickup']) ? 'checked' : '' ?>>
                <span style="color: var(--color-text-primary);">🛍 Permitir pickup</span>
            </label>
            <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer" style="background: var(--color-bg-subtle);">
                <input type="checkbox" name="allow_dine_in" value="1" <?= !empty($settings['allow_dine_in']) ? 'checked' : '' ?>>
                <span style="color: var(--color-text-primary);">🍴 Mesa en local</span>
            </label>
        </div>

        <div class="mt-3">
            <label class="label">Metodos de pago aceptados</label>
            <div class="flex flex-wrap gap-2">
                <?php foreach (['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'online' => 'Pago online (Stripe/MP)'] as $key => $lbl):
                    $active = in_array($key, (array) ($settings['payment_methods'] ?? []), true);
                ?>
                <label class="px-3 py-1.5 rounded-lg cursor-pointer text-sm" style="background: <?= $active ? 'rgba(16,185,129,.15); color:#34D399; border:1px solid rgba(16,185,129,.4)' : 'var(--color-bg-subtle); color: var(--color-text-secondary)' ?>;">
                    <input type="checkbox" name="payment_methods[]" value="<?= $key ?>" <?= $active ? 'checked' : '' ?> class="hidden">
                    <?= e($lbl) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-3">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="auto_accept" value="1" <?= !empty($settings['auto_accept']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                <span class="text-sm" style="color: var(--color-text-secondary);">Auto-confirmar ordenes (saltar estado "Nuevo" → directo a "Confirmado")</span>
            </label>
        </div>
    </div>

    <!-- Sales Bot autonomo (Fase 2) -->
    <div class="surface p-5">
        <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
            <div>
                <h3 class="font-bold" style="color: var(--color-text-primary);">🤖 Sales Bot autonomo</h3>
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">Recupera ventas perdidas y reactiva clientes inactivos sin que muevas un dedo. Requiere cron en el servidor (ver al final).</p>
            </div>
        </div>

        <!-- Cart recovery -->
        <div class="rounded-xl p-4 mb-3" style="background: var(--color-bg-subtle); border: 1px solid var(--color-border-subtle);">
            <label class="flex items-start gap-3 cursor-pointer mb-3">
                <input type="checkbox" name="cart_recovery_enabled" value="1" <?= !empty($settings['cart_recovery_enabled'] ?? true) ? 'checked' : '' ?> class="w-4 h-4 rounded mt-0.5">
                <div>
                    <div class="font-semibold" style="color: var(--color-text-primary);">Recuperacion de carrito abandonado</div>
                    <div class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">Si el cliente armo un pedido pero no confirmo, la IA le envia un recordatorio personalizado.</div>
                </div>
            </label>
            <div class="grid md:grid-cols-2 gap-3 mt-2">
                <div>
                    <label class="label text-xs">Esperar antes de recuperar (minutos)</label>
                    <input type="number" name="cart_recovery_min" min="15" step="15" value="<?= e((string) ($settings['cart_recovery_min'] ?? 120)) ?>" class="input">
                    <p class="text-[11px] mt-1" style="color: var(--color-text-tertiary);">Default 120 (2h). Minimo 15 min para evitar interrupciones.</p>
                </div>
                <div>
                    <label class="label text-xs">Maximo de carritos por ciclo</label>
                    <input type="number" name="cart_recovery_batch" min="1" max="100" value="<?= e((string) ($settings['cart_recovery_batch'] ?? 20)) ?>" class="input">
                    <p class="text-[11px] mt-1" style="color: var(--color-text-tertiary);">Cuantos clientes contactar en cada corrida del cron.</p>
                </div>
            </div>
        </div>

        <!-- Re-engagement -->
        <div class="rounded-xl p-4" style="background: var(--color-bg-subtle); border: 1px solid var(--color-border-subtle);">
            <label class="flex items-start gap-3 cursor-pointer mb-3">
                <input type="checkbox" name="re_engagement_enabled" value="1" <?= !empty($settings['re_engagement_enabled']) ? 'checked' : '' ?> class="w-4 h-4 rounded mt-0.5">
                <div>
                    <div class="font-semibold" style="color: var(--color-text-primary);">Re-enganche de clientes inactivos</div>
                    <div class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">Clientes con pedidos previos que llevan tiempo sin escribir reciben un mensaje personalizado de la IA con sus items favoritos.</div>
                </div>
            </label>
            <div class="grid md:grid-cols-2 gap-3 mt-2">
                <div>
                    <label class="label text-xs">Dias de silencio antes de reactivar</label>
                    <input type="number" name="re_engagement_days" min="3" max="180" value="<?= e((string) ($settings['re_engagement_days'] ?? 14)) ?>" class="input">
                    <p class="text-[11px] mt-1" style="color: var(--color-text-tertiary);">Default 14 dias. Cooldown automatico de 30 dias entre reactivaciones al mismo contacto.</p>
                </div>
                <div>
                    <label class="label text-xs">Maximo de clientes por ciclo</label>
                    <input type="number" name="re_engagement_batch" min="1" max="50" value="<?= e((string) ($settings['re_engagement_batch'] ?? 10)) ?>" class="input">
                    <p class="text-[11px] mt-1" style="color: var(--color-text-tertiary);">Cuantos contactos reactivar en cada corrida.</p>
                </div>
            </div>
        </div>

        <details class="mt-3 text-xs" style="color: var(--color-text-tertiary);">
            <summary class="cursor-pointer font-semibold">¿Como configuro el cron?</summary>
            <div class="mt-2 p-3 rounded-lg" style="background: var(--color-bg-subtle); font-family: ui-monospace, monospace;">
                <p class="mb-2">En cPanel → Cron Jobs, anade un job que corra cada 10 minutos con este comando:</p>
                <code class="block break-all">curl -s -X POST -H "X-Cron-Token: TU_TOKEN" <?= e(rtrim((string) (\App\Core\Config::get('app.url', '')), '/')) ?>/api/cron/sales-bot</code>
                <p class="mt-2">Reemplaza <code>TU_TOKEN</code> por el valor de la variable <code>CRON_TOKEN</code> en tu archivo <code>.env</code>. Si no esta seteada, el endpoint devuelve 401.</p>
            </div>
        </details>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="px-5 py-2 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Guardar configuracion</button>
    </div>
</form>

<!-- Zonas de delivery -->
<div class="surface p-5 mt-5">
    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
        <div>
            <h3 class="font-bold" style="color: var(--color-text-primary);">Zonas de entrega</h3>
            <p class="text-xs" style="color: var(--color-text-tertiary);">Define costos y ETA por zona. La IA pregunta al cliente la zona y aplica el costo correcto.</p>
        </div>
    </div>

    <form action="<?= url('/settings/restaurant/zones') ?>" method="POST" class="grid md:grid-cols-5 gap-3 mb-4">
        <?= csrf_field() ?>
        <input type="text" name="name" required class="input" placeholder="Naco, Piantini, Bella Vista…">
        <input type="number" name="fee" required step="0.01" class="input" placeholder="Costo envio">
        <input type="number" name="eta_min" class="input" placeholder="ETA min">
        <input type="number" name="min_order" step="0.01" class="input" placeholder="Min pedido">
        <button type="submit" class="px-3 py-2 rounded-xl text-white text-sm font-semibold" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Agregar zona</button>
    </form>

    <?php if (empty($zones)): ?>
    <p class="text-xs text-center py-4" style="color: var(--color-text-tertiary);">Aun no tienes zonas. Agrega la primera arriba.</p>
    <?php else: ?>
    <div class="space-y-2">
        <?php foreach ($zones as $z): ?>
        <div class="flex items-center gap-3 p-2 rounded-lg" style="background: var(--color-bg-subtle);">
            <span class="flex-1 font-semibold text-sm" style="color: var(--color-text-primary);"><?= e($z['name']) ?></span>
            <span class="text-xs font-mono" style="color: var(--color-text-secondary);"><?= e((string) ($settings['currency'] ?? 'USD')) ?> <?= number_format((float) $z['fee'], 2) ?></span>
            <?php if (!empty($z['eta_min'])): ?><span class="text-xs" style="color: var(--color-text-tertiary);">~<?= (int) $z['eta_min'] ?> min</span><?php endif; ?>
            <span class="text-[10px]" style="color: <?= !empty($z['is_active']) ? '#34D399' : '#F87171' ?>;">● <?= !empty($z['is_active']) ? 'Activa' : 'Pausada' ?></span>
            <form action="<?= url('/settings/restaurant/zones/' . $z['id']) ?>" method="POST" class="inline" onsubmit="return confirm('Eliminar zona?')">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="text-xs" style="color:#F87171;">✗</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php \App\Core\View::stop(); ?>
