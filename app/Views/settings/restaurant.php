<?php
/** @var array $tenant */
/** @var array $settings */
/** @var array $zones */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'restaurant']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Restaurante',
    'title'    => 'Configuracion del restaurante',
    'subtitle' => 'Activa el modo restaurante, configura impuestos, zonas de entrega, metodos de pago y reglas de pedidos.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<form action="<?= url('/settings/restaurant') ?>" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <section class="set-section">
        <div class="set-section-head">
            <h2 class="set-section-title"><span>🍴</span> Modo restaurante</h2>
        </div>
        <label class="set-check">
            <input type="checkbox" name="is_restaurant" value="1" <?= !empty($tenant['is_restaurant']) ? 'checked' : '' ?>>
            <div class="set-check-body">
                <div class="set-check-title">Activar modo restaurante</div>
                <div class="set-check-desc">Inyecta el menu y reglas de toma de pedidos en el prompt de la IA. Activa el modulo de Ordenes y la pestana de Menu.</div>
            </div>
        </label>
    </section>

    <section class="set-section">
        <div class="set-section-head">
            <h2 class="set-section-title"><span>🏪</span> Datos generales</h2>
        </div>
        <div class="set-field-row cols-3 set-field">
            <div>
                <label class="set-label">Tipo de cocina</label>
                <input type="text" name="cuisine_type" value="<?= e((string) ($settings['cuisine_type'] ?? '')) ?>" class="set-input" placeholder="Mexicana, Pizzeria, Sushi…">
            </div>
            <div>
                <label class="set-label">Moneda</label>
                <input type="text" name="currency" maxlength="3" value="<?= e((string) ($settings['currency'] ?? 'USD')) ?>" class="set-input">
            </div>
            <div>
                <label class="set-label">Tiempo prep. promedio (min)</label>
                <input type="number" name="order_prep_min" value="<?= (int) ($settings['order_prep_min'] ?? 25) ?>" class="set-input">
            </div>
        </div>
        <div class="set-field">
            <label class="set-label">Direccion del local</label>
            <input type="text" name="address" value="<?= e((string) ($settings['address'] ?? '')) ?>" class="set-input">
        </div>
        <div class="set-field">
            <label class="set-label">PDF del menu (opcional, lo manda la IA)</label>
            <input type="url" name="whatsapp_menu_pdf" value="<?= e((string) ($settings['whatsapp_menu_pdf'] ?? '')) ?>" class="set-input" placeholder="https://midominio.com/menu.pdf">
        </div>
    </section>

    <section class="set-section">
        <div class="set-section-head">
            <h2 class="set-section-title"><span>💰</span> Reglas comerciales</h2>
        </div>
        <div class="set-field-row cols-3 set-field">
            <div>
                <label class="set-label">Impuesto (%)</label>
                <input type="number" name="tax_rate" step="0.1" value="<?= e((string) ($settings['tax_rate'] ?? 0)) ?>" class="set-input">
            </div>
            <div>
                <label class="set-label">Propina sugerida (%)</label>
                <input type="number" name="tip_default" step="0.1" value="<?= e((string) ($settings['tip_default'] ?? 0)) ?>" class="set-input">
            </div>
            <div>
                <label class="set-label">Pedido minimo</label>
                <input type="number" name="min_order" step="0.01" value="<?= e((string) ($settings['min_order'] ?? 0)) ?>" class="set-input">
            </div>
        </div>

        <div class="set-field">
            <label class="set-label">Modalidades de servicio</label>
            <div class="set-field-row cols-3">
                <label class="set-pill-check">
                    <input type="checkbox" name="allow_delivery" value="1" <?= !empty($settings['allow_delivery']) ? 'checked' : '' ?>>
                    <span>🛵 Permitir delivery</span>
                </label>
                <label class="set-pill-check">
                    <input type="checkbox" name="allow_pickup" value="1" <?= !empty($settings['allow_pickup']) ? 'checked' : '' ?>>
                    <span>🛍 Permitir pickup</span>
                </label>
                <label class="set-pill-check">
                    <input type="checkbox" name="allow_dine_in" value="1" <?= !empty($settings['allow_dine_in']) ? 'checked' : '' ?>>
                    <span>🍴 Mesa en local</span>
                </label>
            </div>
        </div>

        <div class="set-field">
            <label class="set-label">Metodos de pago aceptados</label>
            <div class="set-payment-grid">
                <?php foreach (['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'online' => 'Pago online (Stripe/MP)'] as $key => $lbl):
                    $active = in_array($key, (array) ($settings['payment_methods'] ?? []), true);
                ?>
                <label class="set-pay-chip">
                    <input type="checkbox" name="payment_methods[]" value="<?= $key ?>" <?= $active ? 'checked' : '' ?>>
                    <span><?= e($lbl) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="set-field">
            <label class="set-check">
                <input type="checkbox" name="auto_accept" value="1" <?= !empty($settings['auto_accept']) ? 'checked' : '' ?>>
                <div class="set-check-body">
                    <div class="set-check-title">Auto-confirmar ordenes</div>
                    <div class="set-check-desc">Saltar estado "Nuevo" → directo a "Confirmado".</div>
                </div>
            </label>
        </div>
    </section>

    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>🤖</span> Sales Bot autonomo</h2>
                <p class="set-section-desc">Recupera ventas perdidas y reactiva clientes inactivos sin que muevas un dedo. Requiere cron en el servidor (ver al final).</p>
            </div>
        </div>

        <div class="set-subsection">
            <label class="set-check">
                <input type="checkbox" name="cart_recovery_enabled" value="1" <?= !empty($settings['cart_recovery_enabled'] ?? true) ? 'checked' : '' ?>>
                <div class="set-check-body">
                    <div class="set-check-title">Recuperacion de carrito abandonado</div>
                    <div class="set-check-desc">Si el cliente armo un pedido pero no confirmo, la IA le envia un recordatorio personalizado.</div>
                </div>
            </label>
            <div class="set-field-row cols-2 set-field" style="margin-top: 12px;">
                <div>
                    <label class="set-label">Esperar antes de recuperar (minutos)</label>
                    <input type="number" name="cart_recovery_min" min="15" step="15" value="<?= e((string) ($settings['cart_recovery_min'] ?? 120)) ?>" class="set-input">
                    <p class="set-help">Default 120 (2h). Minimo 15 min para evitar interrupciones.</p>
                </div>
                <div>
                    <label class="set-label">Maximo de carritos por ciclo</label>
                    <input type="number" name="cart_recovery_batch" min="1" max="100" value="<?= e((string) ($settings['cart_recovery_batch'] ?? 20)) ?>" class="set-input">
                    <p class="set-help">Cuantos clientes contactar en cada corrida del cron.</p>
                </div>
            </div>
        </div>

        <div class="set-subsection">
            <label class="set-check">
                <input type="checkbox" name="re_engagement_enabled" value="1" <?= !empty($settings['re_engagement_enabled']) ? 'checked' : '' ?>>
                <div class="set-check-body">
                    <div class="set-check-title">Re-enganche de clientes inactivos</div>
                    <div class="set-check-desc">Clientes con pedidos previos que llevan tiempo sin escribir reciben un mensaje personalizado de la IA con sus items favoritos.</div>
                </div>
            </label>
            <div class="set-field-row cols-2 set-field" style="margin-top: 12px;">
                <div>
                    <label class="set-label">Dias de silencio antes de reactivar</label>
                    <input type="number" name="re_engagement_days" min="3" max="180" value="<?= e((string) ($settings['re_engagement_days'] ?? 14)) ?>" class="set-input">
                    <p class="set-help">Default 14 dias. Cooldown automatico de 30 dias entre reactivaciones al mismo contacto.</p>
                </div>
                <div>
                    <label class="set-label">Maximo de clientes por ciclo</label>
                    <input type="number" name="re_engagement_batch" min="1" max="50" value="<?= e((string) ($settings['re_engagement_batch'] ?? 10)) ?>" class="set-input">
                    <p class="set-help">Cuantos contactos reactivar en cada corrida.</p>
                </div>
            </div>
        </div>

        <details class="set-details">
            <summary>¿Como configuro el cron?</summary>
            <div class="set-details-body">
                <p>En cPanel → Cron Jobs, anade un job que corra cada 10 minutos con este comando:</p>
                <code class="set-code-block">curl -s -X POST -H "X-Cron-Token: TU_TOKEN" <?= e(rtrim((string) (\App\Core\Config::get('app.url', '')), '/')) ?>/api/cron/sales-bot</code>
                <p>Reemplaza <code>TU_TOKEN</code> por el valor de la variable <code>CRON_TOKEN</code> en tu archivo <code>.env</code>. Si no esta seteada, el endpoint devuelve 401.</p>
            </div>
        </details>
    </section>

    <div class="set-actions">
        <button type="submit" class="set-btn set-btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Guardar configuracion
        </button>
    </div>
</form>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>📍</span> Zonas de entrega</h2>
            <p class="set-section-desc">Define costos y ETA por zona. La IA pregunta al cliente la zona y aplica el costo correcto.</p>
        </div>
    </div>

    <form action="<?= url('/settings/restaurant/zones') ?>" method="POST" class="set-zone-form set-field">
        <?= csrf_field() ?>
        <input type="text" name="name" required class="set-input" placeholder="Naco, Piantini, Bella Vista…">
        <input type="number" name="fee" required step="0.01" class="set-input" placeholder="Costo envio">
        <input type="number" name="eta_min" class="set-input" placeholder="ETA min">
        <input type="number" name="min_order" step="0.01" class="set-input" placeholder="Min pedido">
        <button type="submit" class="set-btn set-btn-primary set-btn-sm">Agregar zona</button>
    </form>

    <?php if (empty($zones)): ?>
    <div class="set-empty">
        <p class="set-empty-text">Aun no tienes zonas. Agrega la primera arriba.</p>
    </div>
    <?php else: ?>
    <ul class="set-zone-list">
        <?php foreach ($zones as $z): ?>
        <li class="set-zone-item">
            <span class="set-zone-name"><?= e($z['name']) ?></span>
            <span class="set-zone-fee"><?= e((string) ($settings['currency'] ?? 'USD')) ?> <?= number_format((float) $z['fee'], 2) ?></span>
            <?php if (!empty($z['eta_min'])): ?><span class="set-zone-eta">~<?= (int) $z['eta_min'] ?> min</span><?php endif; ?>
            <span class="set-zone-status" style="color: <?= !empty($z['is_active']) ? '#10B981' : '#F87171' ?>;">● <?= !empty($z['is_active']) ? 'Activa' : 'Pausada' ?></span>
            <form action="<?= url('/settings/restaurant/zones/' . $z['id']) ?>" method="POST" style="display:inline;" onsubmit="return confirm('Eliminar zona?')">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="set-btn set-btn-danger set-btn-sm">✗</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
